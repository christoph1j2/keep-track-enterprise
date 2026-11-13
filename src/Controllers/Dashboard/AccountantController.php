<?php

namespace Src\Controllers\Dashboard;

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Src\Controllers\BaseController;
use Src\Controllers\ErrorController;
use Src\Middleware\Auth;
use Src\Models\ExpenseModel;
use Src\Models\InvoiceModel;
use Src\Models\PaymentModel;
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
        $userId = (int) $_SESSION['user_id'];
        $model  = new UserModel($this->db);

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        $this->render('Accountant/clients.twig', [
            'clients'      => $model->getClientsOf($userId),
            'activeOsvcId' => $_SESSION['active_osvc_id'] ?? null,
            'flash'        => $flash,
        ]);
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

        $userId = (int) $_SESSION['user_id'];
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

        $userId = (int) $_SESSION['user_id'];

        $key = trim($_POST['key'] ?? '');
        $model = new UserModel($this->db);

        $ok = $model->redeemKey($key, $userId);

        $this->redirect('/profile');
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
        $userId = $this->effectiveUserId();
        $model  = new ExpenseModel($this->db);

        $perPage  = 15;
        $total    = $model->totalCount($userId);
        $pages    = max(1, (int) ceil($total / $perPage));
        $page     = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $this->render('Expense/expenses.twig', [
            'expenses'   => $model->getPaginated($page, $perPage, $userId),
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'readOnly'   => true,
            'receiptUrl' => '/accountant/receipt',
            'basePath'   => '/accountant/expenses',
        ]);
    }


    /**
     * Method for rendering a receipt of the selected client
     *
     * @return void
     */
    public function receiptAction(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $r  = $id > 0
            ? (new ExpenseModel($this->db))->findReceiptById($id, $this->effectiveUserId())
            : null;

        if (!$r)
            (new ErrorController($this->db))->notFoundAction();

        $this->sendReceipt($r);
    }

    // #### #### ####


    // #### Invoices Tab ####

    /**
     * Method for rendering the invoices of the selected client
     *
     * @return void
     */
    // TODO: FIX THROWS 403
    public function invoicesAction(): void
    {
        $userId = $this->effectiveUserId();
        $model  = new InvoiceModel($this->db);

        $perPage = 15;
        $total   = $model->totalCount($userId);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $this->render('Invoice/invoices.twig', [
            'invoices' => $model->getPaginated($page, $perPage, $userId),
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'readOnly' => true,
            'basePath' => '/accountant/invoices',
        ]);
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
        $userId = $this->effectiveUserId();
        $model  = new PaymentModel($this->db);

        $perPage = 15;
        $total   = $model->totalCount($userId);
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $this->render('Payment/payments.twig', [
            'payments' => $model->getPaginated($page, $perPage, $userId),
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'readOnly' => true,
            'basePath' => '/accountant/payments',
        ]);
    }

    // #### #### ####
}