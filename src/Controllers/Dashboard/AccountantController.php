<?php

namespace Src\Controllers\Dashboard;

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Src\Controllers\BaseController;
use Src\Middleware\Auth;
use Src\Models\UserModel;

class AccountantController extends BaseController
{
    /**
     * The constructor
     *
     * @param PDO $db
     */
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        Auth::check();
        $this->requireRole('ucetni');
    }

    // #### Clients Tab ####

    /**
     * Method for rendering the overview of clients linked to the accountant.
     *
     * @return void
     */
    public function clientsAction(): void
    {
        // TODO
    }

    /**
     * Method for switching between clients linked to the accountant.
     *
     * @return void
     */
    #[NoReturn]
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
    #[NoReturn]
    public function redeemKeyAction(): void
    {
        $this->requireRole('ucetni');

        $userId = $_SESSION['user_id'];

        $key = trim($_POST['key'] ?? '');
        $model = new UserModel($this->db);

        $ok = $model->redeemKey($key, $userId);

        $this->redirect('/accountant/clients');
    }

    // #### #### ####

    // #### Expenses Tab ####

    /**
     * Method for rendering the expenses of the selected client
     *
     * @return void
     */
    public function expensesAction(): void
    {
        // TODO
    }

    // #### #### ####


    // #### Invoices Tab ####

    /**
     * Method for rendering the invoices of the selected client
     *
     * @return void
     */
    public function invoicesAction(): void
    {
        // TODO
    }

    // #### #### ####


    // #### Payments Tab ####

    /**
     * Method for rendering the payments of the selected client
     *
     * @return void
     */
    public function paymentsAction(): void
    {
        // TODO
    }


    /**
     * Method for rendering a receipt of the selected client
     *
     * @return void
     */
    public function receiptAction(): void
    {
        // TODO
    }

    // #### #### ####
}