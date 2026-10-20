<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Controllers\BaseController;
use Src\Models\UserModel;

class AuthController extends BaseController
{
    public function registerFormAction()
    {

    }

    public function registerSubmitAction()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            $err = new ErrorController($this->db);
            $err->methodNotAllowedAction();
        }

        $username = $_POST['username'] ?? null;
        $email = filter_var($_POST['email'] ?? null, FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? null;
        $password_confirm = $_POST['password_confirm'] ?? null;

        // TODO: Validator

        if (true /*validator is valid*/)
        {
            $model = new UserModel($this->db);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $model->createUser($username, $email, $password_hash);

            // TODO: session login after registering
            // session_regenerate_id(true) // protect against session fixation
            // $_SESSION['user_id'] = $this->db->lastInsertId();
            // $_SESSION['role'] = ...
            // $_SESSION['username'} = $username;

            header('Location: /');
            exit;
        }

        $this->registerFormAction($_POST, [] /*TODO: validator get errors - empty array for now*/);
    }
}