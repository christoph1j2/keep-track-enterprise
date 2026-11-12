<?php

namespace Src\Controllers;

use PDO;
use Src\Middleware\Auth;

abstract class OsvcController extends BaseController
{
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        Auth::check();
        $this->requireRole('osvc');
    }
}