<?php 
declare(strict_types=1);

// TODO: session and cookie init

require __DIR__ . '/../vendor/autoload.php';

use Src\Controllers\HomeController;
use Src\Core\Database;
use Src\Core\Router;

$database = new Database();
$db = $database->getConnection();

$router = new Router();

// route registrations
$router->get('/', HomeController::class, 'index');
$router->get('/about', HomeController::class, 'about');
$router->get('/features', HomeController::class, 'features');

$router->dispatch($db);

// TEMPORARY

//echo "<h1>Cigi Nigi</h1>";
//var_dump($database);
//echo "<br/>";
//var_dump($db);
//
//phpInfo();

