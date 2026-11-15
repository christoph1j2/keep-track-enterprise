<?php
declare(strict_types=1);

namespace Src\Middleware;

use PDO;
use Random\RandomException;
use Src\Controllers\ErrorController;

final class CSRF
{
    /**
     * Ensures a CSRF token exists in the current session and returns it.
     *
     * @return string
     * @throws RandomException
     */
    public static function token(): string
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
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    /**
     * Verifies CSRF token on POST requests.
     *
     * @param PDO $db
     * @return void
     * @throws RandomException
     */
    public static function verify(PDO $db): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
            return;

        $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected  = self::token();

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

            (new ErrorController($db))->forbiddenAction();
        }
    }
}
