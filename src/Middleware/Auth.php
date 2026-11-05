<?php
declare(strict_types=1);

namespace Src\Middleware;

use Src\Controllers\ErrorController;

final class Auth
{
    /**
     * Method for checking if user is authed while
     * accessing protected routes, redirect to login if not
     *
     * @return void
     */
    public static function check(): void
    {
        if (!isset($_SESSION['user_id']))
        {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Method to check if user has the role required to access
     * a specific route.
     *
     * @param string $roles
     * @param \PDO $db
     * @return void
     */
    public static function roles(array|string $roles, \PDO $db): void
    {
        self::check();

        $roles = (array) $roles;
        $user_role = $_SESSION['role'] ?? null;

        if (!in_array($user_role, $roles, true))
        {
            // ajax check
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax)
            {
                http_response_code(403);
                header('Content-type: application/json');
                echo json_encode([
                    'status' => 'forbidden',
                    'message' => 'You are not allowed to access this page.',
                ]);
                exit;
            }

            $err = new ErrorController($db);
            $err->forbiddenAction();
            exit;
        }
    }
}