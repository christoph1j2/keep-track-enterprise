<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

abstract class BaseController {
    protected PDO $db;
    private Environment $twig;

    /**
     * The constructor.
     * @param PDO $db - PDO instance.
     */
    public function __construct(PDO $db)
    {
        // Accept database from router
        $this->db = $db;

        // Prepare twig for all child controllers
        $loader = new FilesystemLoader(__DIR__ . "/../Views");
        $this->twig = new Environment($loader);
        $this->twig->addGlobal('session', $_SESSION); // global injection of SESSION
    }

    /**
     * Helper render method to render twig with csrf token included
     *
     * @param string $view - twig file
     * @param array $params - parameters to be passed to twig file
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function render(string $view, array $params = [])
    {
        if (!isset($params['csrf_token']))
        {
            // TODO: CSRF
            // $params['csrf_token'] = CSRF::getToken();
            //TEMP
            $params['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (!isset($params['csrf_field']))
        {
            // TODO: CSRF
            // $params['csrf_field'] = CSRF::getField();
            $params['csrf_field'] = bin2hex(random_bytes(32));
        }

        try
        {
            echo $this->twig->render($view, $params);
        }
        catch (LoaderError|RuntimeError|SyntaxError $e)
        {
            echo "An unexpected error has occurred.";
        }
    }
}