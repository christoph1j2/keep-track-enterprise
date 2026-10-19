<?php
declare(strict_types=1);

namespace Src\Controllers;

class HomeController extends BaseController
{
    /**
     * Method for loading the '/' index route of the application.
     */
    public function index(): void
    {
        echo $this->render("Home/home.twig");
    }

    /**
     * Method for loading the '/about' route of the application.
     */
    public function about(): void
    {
        echo $this->render("Home/about.twig");
    }

    /**
     * Method for loading the '/features' route of the application.
     */
    public function features(): void
    {
        echo $this->render("Home/features.twig");
    }

}
