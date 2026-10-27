<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;

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
        // TODO: fetch from models, hardcoded values for demonstration purposes
        $stats = [
            "clients" => 1,
            "invoices" => 123456,
            "income" => 12345,
            "expenses" => 12345,
        ];

        $this->render("Dashboard/dashboard.twig", ["stats" => $stats]);
    }

}
