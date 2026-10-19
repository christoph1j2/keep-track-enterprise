<?php

namespace Src\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->twig->render("Home/home.twig");
    }
}