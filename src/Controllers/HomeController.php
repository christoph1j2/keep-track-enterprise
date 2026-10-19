<?php
declare(strict_types=1);

namespace Src\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        echo $this->twig->render("Home/home.twig");
    }
}