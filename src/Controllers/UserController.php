<?php

namespace Src\Controllers;

use Src\Controllers\BaseController;
use Src\Models\UserModel;

class UserController extends BaseController
{
    public function profileAction()
    {
        // sanity check
        if (!$_SESSION['user_id']) $this->redirect('/');

        // get user to parse into view
        $model = new UserModel($this->db);
        $user = $model->findById($_SESSION['user_id']);

        $this->render('User/profile.twig', [
            'user' => $user
        ]);
    }
}