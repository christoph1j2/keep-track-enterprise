<?php
declare(strict_types=1);

namespace Src\Middleware;

use Src\Controllers\ErrorController;

final class Auth
{
    public static function check(): void
    {
        if (!isset($_SESSION['user_id']))
        {
            header('Location: /user/login');
            exit;
        }
    }

    public static function role(string $role, \PDO $db): void
    {
        self::check();

        if ($_SESSION['role'] !== $role)
        {
            $err = new ErrorController($db);
            $err->forbiddenAction();
            exit;
        }
    }
}