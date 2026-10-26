<?php
declare(strict_types=1);

namespace Src\Controllers;

class DashboardController extends BaseController
{
    /**
     * Method for loading the '/dashboard' route of the application.
     */
    public function dashboardAction(): void
    {
        $this->render("Dashboard/dashboard.twig");
    }

}
