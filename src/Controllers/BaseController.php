<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

abstract class BaseController {
    protected PDO $db;
    protected Environment $twig;

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
    }

    protected function render(string $view, array &$params = []): void
    {
        $viewFile = __DIR__ . "/../Views/" . $view . ".php";

        if (!is_file($viewFile))
        {
            // TODO: ErrorController static call error 500
            echo "Error 500";
        }

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

        extract($params, EXTR_SKIP);
        require $viewFile;

    }
}