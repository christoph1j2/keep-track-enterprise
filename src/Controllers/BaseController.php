<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;
// use Src\Core\CSRF;

abstract class BaseController {
    protected PDO $db;

    /**
     * The constructor.
     * @param PDO $db - PDO instance.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


}