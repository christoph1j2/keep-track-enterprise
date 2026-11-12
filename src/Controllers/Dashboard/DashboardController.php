<?php
declare(strict_types=1);

namespace Src\Controllers\Dashboard;

use PDO;
use Src\Controllers\BaseController;
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

        $unpaidInvoices = $paymentModel->getUnpaidInvoices();

        $totalRemaining = array_sum(array_column($unpaidInvoices, 'remaining_amount'));
        $paidPercent    = $invoiceSum > 0
            ? (int) round((($invoiceSum - $totalRemaining) * 100) / $invoiceSum)
            : 0;

        $stats = [
            "clients"  => $clientCount,
            "invoices" => $invoiceSum,
            "income"   => $paymentSum,
            "expenses" => $expenseSum,
            "remaining" => $totalRemaining,
            "paidPct"     => $paidPercent,
        ];

        $this->render("Dashboard/dashboard.twig", [
            "stats" => $stats,
            "role"  => $role,
            "unpaidInvoices" => $unpaidInvoices,
        ]);
    }

}
