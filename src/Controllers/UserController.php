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

    /**
     * Method for unlinking an accountant from an OSVC account
     *
     * @return void
     */
    public function unlinkAccountantAction()
    {
        // sanity check
        Auth::check();

        // delete all references between user and accountant
        $model = new UserModel($this->db);
        $model->unlinkAccountant($_SESSION['user_id']);

        $this->redirect('/profile');
    }

    /**
     * Accountant only action
     * Used to switch between multiple OSVC clients for 1 accountant
     *
     * @return void
     */
    public function switchClientAction(): void
    {
        $this->requireRole('ucetni');

        $userId = $_SESSION['user_id'];
        $osvcId = (int) ($_POST['osvc_id'] ?? 0);
        $model = new UserModel($this->db);

        // if accountant has osvc linked
        if ($model->isClientOf($userId, $osvcId))
            $_SESSION['active_osvc_id'] = $osvcId;

        $this->redirect('/dashboard');
    }

    /**
     * Accountant only action
     * Used to redeem a key after accountant is already registered
     * to connect to more clients (osvc)
     *
     * @return void
     */
    public function redeemKeyAction(): void
    {
        $this->requireRole('ucetni');

        $userId = $_SESSION['user_id'];
        $osvcId = (int) ($_POST['osvc_id'] ?? 0);

        $key = trim($_POST['key'] ?? '');
        $model = new UserModel($this->db);

        $ok = $model->redeemKey($key, $userId);
        // todo: throw invalid/expired key

        $this->redirect('/accountant/clients');
    }
}