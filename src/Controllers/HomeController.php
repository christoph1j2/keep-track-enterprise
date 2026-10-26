<?php
declare(strict_types=1);

namespace Src\Controllers;

class HomeController extends BaseController
{
    /**
     * Method for loading the '/' index route of the application.
     */
    public function indexAction(): void
    {
        $this->render("Home/home.twig");
    }

    /**
     * Method for loading the '/about' route of the application.
     */
    public function aboutAction(): void
    {
        $this->render("Home/about.twig");
    }

    /**
     * Method for loading the '/features' route of the application.
     */
    public function featuresAction(): void
    {
        $this->render("Home/features.twig");
    }

}
