<?php
declare(strict_types=1);

namespace Src\Controllers;

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Random\RandomException;
use Src\Middleware\Auth;
use Src\Models\UserModel;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

abstract class BaseController {
    protected PDO $db;
    private Environment $twig;

    /** @var array|string[] array of public routes, which can be accessed without auth */
    private array $publicRoutes = [ // TODO: implement dynamic public routes in the future
        '/',
        '/about',
        '/features',
        '/register',
        '/login',
        '/logout'
    ];

    /**
     * The constructor.
     * @param PDO $db - PDO instance.
     */
    public function __construct(PDO $db)
    {
        // Accept database from router
        $this->db = $db;

        // Session status
        if (session_status() == PHP_SESSION_NONE)
        {
            session_start();
        }
        $csrfToken = $this->ensureCsrfToken();

        if (!$this instanceof ErrorController)
        {
            $this->checkAuth();
            $this->verifyCsrf();
        }

        // Prepare twig for all child controllers
        $loader = new FilesystemLoader(__DIR__ . "/../Views");
        $this->twig = new Environment($loader);
        $this->twig->addGlobal('session', $_SESSION); // global injection of SESSION
        $this->twig->addGlobal('csrf_token', $csrfToken);
        $this->twig->addGlobal('csrf_field', $this->getCsrfField());

        $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->twig->addGlobal('current_path', $request); // global injection of currpath
    }

    /**
     * Helper method that calls the roles check from Auth middleware.
     *
     * @param array|string $roles - array of acceptable roles
     * @return void
     */
    protected function requireRole(array|string $roles): void
    {
        Auth::roles($roles, $this->db);
    }

    /**
     * Stores a one-time flash message in session for the next rendered page.
     *
     * @param string $type - Bootstrap alert type (success, danger, warning, info)
     * @param string $message - Message text
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Ensures a CSRF token exists in the current session and returns it.
     *
     * @return string
     * @throws RandomException
     */
    protected function ensureCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token']))
        {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Returns HTML hidden input containing the current CSRF token.
     *
     * @return string
     * @throws RandomException
     */
    protected function getCsrfField(): string
    {
        $token = htmlspecialchars($this->ensureCsrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    /**
     * Verifies CSRF token on POST requests.
     *
     * @return void
     */
    private function verifyCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
            return;

        $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected  = $this->ensureCsrfToken();

        if (!is_string($submitted) || !hash_equals($expected, $submitted))
        {
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax)
            {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Neplatný bezpečnostní token (CSRF). Obnovte prosím stránku.'
                ]);
                exit;
            }

            (new ErrorController($this->db))->forbiddenAction();
        }
    }

    /**
     * Helper render method to render twig with csrf token included
     *
     * @param string $view - twig file
     * @param array $params - parameters to be passed to twig file
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError|RandomException
     */
    protected function render(string $view, array $params = []): void
    {
        if (!array_key_exists('flash', $params) && isset($_SESSION['flash']))
        {
            $params['flash'] = $_SESSION['flash'];
            unset($_SESSION['flash']);
        }

        try
        {
            echo $this->twig->render($view, $params);
        }
        catch (LoaderError|RuntimeError|SyntaxError $e)
        {
            echo "An unexpected error has occurred.";
            echo "<br/>";
            echo $e->getMessage();
        }
    }

    /**
     * Redirects to specified url.
     *
     * @param string $url - url to redirect to
     * @return void
     */
    #[NoReturn]
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    private function checkAuth(): void
    {
        // exception for login page or routes which dont require auth
        $currUri = $_SERVER['REQUEST_URI'] ?? '/';
        $currPath = parse_url($currUri, PHP_URL_PATH);

        // if current path is public, continue
        if (in_array($currPath, $this->publicRoutes, true)) return;

        // if user is logged in, verify user still exists and isn't blocked
        if (isset($_SESSION['user_id']))
        {
            $stmt = $this->db->prepare("SELECT is_blocked FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int) $_SESSION['user_id']]);
            $row = $stmt->fetch();

            if ($row && empty($row['is_blocked']))
                return;

            session_unset();
            $this->flash('danger', 'Váš účet byl zablokován nebo odstraněn.');
        }

        // check if request is AJAX
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax)
        {
            // send 401 for ajax and end script
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'unauthorized',
                'message' => 'Relace vypršela. Přihlaste se znovu.'
            ]);
            exit;
        }

        header('Location: /login');
        exit;
    }

    /**
     * User ID, whose data are being displayed
     * - OSVC sees their own data,
     * - accountant sees data of currently selected (and linked) OSVC
     *
     * @return int
     */
    protected function effectiveUserId(): int
    {
        $me = (int) $_SESSION['user_id'];

        if (($_SESSION['role'] ?? '') !== 'ucetni')
            return $me;

        $active = (int) ($_SESSION['active_osvc_id'] ?? 0);

        if ($active === 0 || !(new UserModel($this->db))->isClientOf($me, $active))
        {
            unset($_SESSION['active_osvc_id']);
            $this->flash('info', 'Nejprve vyberte klienta.');
            $this->redirect('/accountant/clients'); // select osvc
        }

        return $active;
    }

    /**
     * Helper method to Display a receipt file in new tab
     *
     * @param array $r
     * @return void
     */
    #[NoReturn]
    protected function sendReceipt(array $r): void
    {
        $dir  = realpath(__DIR__ . '/../../storage/receipts');
        $path = $dir !== false ? realpath($dir . '/' . $r['receipt_filename']) : false;

        if ($path === false
            || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR)
            || !is_file($path))
        {
            (new ErrorController($this->db))->notFoundAction();
        }

        header('Content-Type: ' . $r['receipt_mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($r['receipt_original_name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }
}