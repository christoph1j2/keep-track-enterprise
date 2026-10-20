<?php 
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// TODO: session and cookie init
session_start();

use Src\Controllers\AuthController;
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

$router->get('/register', AuthController::class, 'registerFormAction');
$router->post('/register', AuthController::class, 'registerSubmitAction');
$router->get('/login', AuthController::class, 'loginFormAction');
$router->post('/login', AuthController::class, 'loginSubmitAction');

$router->dispatch($db);

// TEMPORARY

//echo "<h1>Cigi Nigi</h1>";
//var_dump($database);
//echo "<br/>";
//var_dump($db);
//
//phpInfo();

