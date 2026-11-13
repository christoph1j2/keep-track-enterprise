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
        $this->checkAuth();

        // Prepare twig for all child controllers
        $loader = new FilesystemLoader(__DIR__ . "/../Views");
        $this->twig = new Environment($loader);
        $this->twig->addGlobal('session', $_SESSION); // global injection of SESSION

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
        if (!isset($params['csrf_token']))
        {
            // TODO: CSRF
            // $params['csrf_token'] = CSRF::getToken();
            //TEMP
            $params['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!isset($params['csrf_field']))
        {
            // TODO: CSRF
            // $params['csrf_field'] = CSRF::getField();
            $params['csrf_field'] = bin2hex(random_bytes(32));
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

        // if user is logged in, continue
        if (isset($_SESSION['user_id'])) return;

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