<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Controllers\BaseController;
use Src\Models\UserModel;

class AuthController extends BaseController
{
    /**
     * Method for displaying the register form
     *
     * @param array $data - $_POST data
     * @param array $errors
     * @return void
     */
    public function registerFormAction(array $data = [], array $errors = [])
    {
        $this->render('registerForm.twig', [
            'data' => $data,
            'errors' => $errors
        ]);
    }

    /**
     * Submit method for registering user.
     *
     * @return void
     */
    public function registerSubmitAction()
    {
        // sanity check
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

            // automatically login after register
            session_regenerate_id(true); // protect against session fixation
            $_SESSION['user_id'] = $this->db->lastInsertId();
            /* $_SESSION['role'] = ... */
            $_SESSION['username'] = $username;

            header('Location: /'); // TODO: rework
            exit;
        }

        $this->registerFormAction($_POST, [] /*TODO: validator get errors - empty array for now*/);
    }

    /**
     * Method for displaying the login form
     *
     * @param array $data - $_POST data
     * @param array $errors
     * @return void
     */
    public function loginFormAction(array $data = [], array $errors = [])
    {
        $this->render('loginForm.twig', [
            'data' => $data,
            'errors' => $errors
        ]);
    }

    /**
     * Submit method for login.
     *
     * @return void
     */
    public function loginSubmitAction()
    {
        // sanity check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            $err = new ErrorController($this->db);
            $err->methodNotAllowedAction();
        }

        $model = new UserModel($this->db);

        // extract email and password from post request
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'];

        // fetch user
        $user = $model->findByEmail($email);
        if (!$user)
        {
            $errors = ['Your email or password is wrong.'];
            $this->loginFormAction($_POST, $errors);
            return;
        }

        // check password correctness
        if (password_verify($password, $user['password_hash']))
        {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            // $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            header('Location: /'); // TODO: rework
            exit;
        }

        $errors = ['password' => 'Your email or password is wrong.'];
        $this->loginFormAction($_POST, $errors);
    }
}