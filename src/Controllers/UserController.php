<?php

namespace Src\Controllers;

use Src\Controllers\BaseController;
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
        if (!$_SESSION['user_id']) $this->redirect('/');

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
    public function generateAccountantKeyAction()
    {
        // sanity check
        if (!$_SESSION['user_id']) $this->redirect('/');

        // generate the key
        $model = new UserModel($this->db);
        $accountant_key = $model->generateAccountantKey();

        $this->profileAction();
    }
}