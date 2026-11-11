<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;
use Src\Models\ClientModel;
use Src\Models\ExpenseModel;
use Src\Models\InvoiceModel;
use Src\Models\PaymentModel;

class DashboardController extends BaseController
{
    /**
     * The constructor. Doesn't allow
     * unautheticated users access to the dashboard.
     *
     * @param PDO $db - PDO instance
     */
    public function __construct(PDO $db)
    {
        parent::__construct($db);

        if (!isset($_SESSION["user_id"]))
        {
            $this->redirect("/login");
        }
    }

    /**
     * Method for loading the '/dashboard' route of the application.
     */
    public function dashboardAction(): void
    {
        $role   = $_SESSION["role"];

        $clientModel  = new ClientModel($this->db);
        $invoiceModel = new InvoiceModel($this->db);
        $paymentModel = new PaymentModel($this->db);
        $expenseModel = new ExpenseModel($this->db);

        $clientCount  = $clientModel->totalCount();
        $invoiceSum   = $invoiceModel->totalSum();
        $paymentSum   = $paymentModel->totalSum();
        $expenseSum   = $expenseModel->totalSum();

        // TODO: fetch from models, hardcoded values for demonstration purposes
        $stats = [
            "clients"  => $clientCount,
            "invoices" => $invoiceSum,
            "income"   => $paymentSum,
            "expenses" => $expenseSum,
        ];

        $this->render("Dashboard/dashboard.twig", [
            "stats" => $stats,
            "role"  => $role,
        ]);
    }

}
