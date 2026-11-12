<?php

namespace Src\Controllers\Dashboard;

use PDO;
use Src\Controllers\BaseController;
use Src\Middleware\Auth;

abstract class OsvcController extends BaseController
{
    /**
     * The constructor.
     *
     * @param PDO $db
     */
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        Auth::check();
        $this->requireRole('osvc');
    }
}