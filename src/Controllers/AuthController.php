<?php
declare(strict_types=1);

namespace Src\Controllers;

use JetBrains\PhpStorm\NoReturn;
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
    public function registerFormAction(array $data = [], array $errors = [], string $modeArg = null): void
    {
        /** @var string|null $modeParam - Gets the mode as query param */
        $modeParam = ($modeArg == null) ? $_GET['mode'] ?? null : $modeArg;

        $mode = ($modeParam !== null)
            ? (($modeParam == 'accountant')
                ? 'accountant'
                : 'osvc')
            : 'osvc';

        //var_dump($mode, $modeParam, $modeArg); // TODO: REMOVE

        $this->render('User/registerForm.twig', [
            'data' => $data,
            'errors' => $errors,
            'mode' => $mode
        ]);
    }

    /**
     * Submit method for registering user.
     *
     * @return void
     */
    public function registerSubmitAction(): void
    {
        /** @var string $mode - Uses the mode from POST to redirect correctly and save the user correctly */
        $mode = $_POST['mode'] ?? 'osvc';

        // sanity check
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            $err = new ErrorController($this->db);
            $err->methodNotAllowedAction();
        }

        // All modes field check
        $username = $_POST['username'] ?? null;
        $email = filter_var($_POST['email'] ?? null, FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? null;
        $password_confirm = $_POST['password_confirm'] ?? null;

        // Accountant mode only field check
        $accountant_key = ($mode === 'accountant') ? ($_POST['accountant_key'] ?? null) : null;

        // Validate
        $validator = new RegisterValidator($_POST, $this->db);
        $validator->validate($mode);

        if ($validator->isValid())
        {
            $model = new UserModel($this->db);
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $userId = $model->createUser($username, $email, $password_hash, $accountant_key);

            // automatically login after register
            if ($userId)
            {
                session_regenerate_id(true); // protect against session fixation
                $_SESSION['user_id'] = $userId;
                $_SESSION['role'] = ($mode === 'accountant') ? 'ucetni' : 'osvc';
                $_SESSION['username'] = $username;
               $this->redirect('/dashboard');
            }
        }
        $this->registerFormAction($_POST, $validator->getErrors(), $mode);
    }

    /**
     * Method for displaying the login form
     *
     * @param array $data - $_POST data
     * @param array $errors
     * @return void
     */
    public function loginFormAction(array $data = [], array $errors = []): void
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
    public function loginSubmitAction(): void
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
            if (!empty($user['is_blocked']))
            {
                $errors = ['email' => 'Váš účet byl zablokován správcem.'];
                $this->loginFormAction($_POST, $errors);
                return;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];

            $this->redirect('/dashboard'); // TODO: if admin then redirect to /admin
        }

        $errors = ['password' => 'Vaše přihlašovací údaje jsou chybné.'];
        $this->loginFormAction($_POST, $errors);
    }

    /**
     * Logs the user out
     * @return void
     */
    #[NoReturn]
    public function logoutAction(): void
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