<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Controllers\BaseController;
use Src\Middleware\Auth;
use Src\Models\UserModel;

class UserController extends BaseController
{
    /**
     * Method for loading the '/profile' page
     *
     * @return void
     */
    public function profileAction()
    {
        // sanity check
        Auth::check();

        // get user to parse into view
        $model = new UserModel($this->db);
        $user = $model->findById($_SESSION['user_id']);

        // get accountant name
        $accountant_name = ($user['accountant_id'])
            ? ($model->findById($user['accountant_id']))['username']
            : 'N/A';

        $this->render('User/profile.twig', [
            'user' => $user,
            'accountant_name' => $accountant_name
        ]);
    }

    /**
     * Method for calling the accountant key gen
     *
     * @return void
     */
    public function generateAccountantKeyAction(): void
    {
        // sanity check
        Auth::check();

        // generate the key
        $model = new UserModel($this->db);
        $model->generateAccountantKey($_SESSION['user_id']);

        $this->redirect('/profile');
    }

    public function unlinkAccountantAction()
    {
        // sanity check
        Auth::check();

        // delete all references between user and accountant
        $model = new UserModel($this->db);
        $model->unlinkAccountant($_SESSION['user_id']);

        $this->redirect('/profile');
    }
}