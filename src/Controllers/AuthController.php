<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Models\UserModel;
use Src\Validators\RegisterValidator;

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
        $this->render('User/registerForm.twig', [
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

        $validator = new RegisterValidator($_POST, $this->db);
        $validator->validate();

        if ($validator->isValid())
        {
            $model = new UserModel($this->db);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $model->createUser($username, $email, $password_hash);

            // automatically login after register
            session_regenerate_id(true); // protect against session fixation
            $_SESSION['user_id'] = $this->db->lastInsertId();
            /* $_SESSION['role'] = ... */
            $_SESSION['username'] = $username;

            $this->redirect('/dashboard');
        }

        $this->registerFormAction($_POST, $validator->getErrors());
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
        $this->render('User/loginForm.twig', [
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
        if ($email == null)
        {
            $errors = ['email' => 'Prosím vyplňte své údaje.'];
            $this->loginFormAction($_POST, $errors);
            return;
        }
        $user = $model->findByEmail($email);
        if (!$user)
        {
            $errors = ['email' => 'Vaše přihlašovací údaje jsou chybné.'];
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

            $this->redirect('/dashboard');
        }

        $errors = ['password' => 'Vaše přihlašovací údaje jsou chybné.'];
        $this->loginFormAction($_POST, $errors);
    }

    /**
     * Logs the user out
     * @return void
     */
    public function logoutAction()
    {
        session_unset();
        session_destroy();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        $this->redirect('/');
    }

    // TODO (probably after the semester project):
    // - forgotPasswordFormAction()
    // - forgotPasswordSubmitAction()
    // - resetPasswordFormAction()
    // - resetPasswordSubmitAction()
}