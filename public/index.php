<?php 
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$sessionLifetime = (int) (getenv('SESSION_LIFETIME') ?: 1800);
ini_set('session.gc_maxlifetime', $sessionLifetime);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict',
]);
session_start();

// sliding expiratoin
if (isset($_COOKIE[session_name()]) && session_id())
{
    setcookie(session_name(), session_id(), [
        'expires' => time() + $sessionLifetime,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httpOnly' => true,
        'samesite' => 'Strict',
    ]);
}

use Src\Controllers\AuthController;
use Src\Controllers\ClientController;
use Src\Controllers\DashboardController;
use Src\Controllers\HomeController;
use Src\Core\Database;
use Src\Core\Router;

$database = new Database();
$db = $database->getConnection();

$router = new Router();

// route registrations
// public routes
$router->get('/', HomeController::class, 'indexAction');
$router->get('/about', HomeController::class, 'aboutAction');
$router->get('/features', HomeController::class, 'featuresAction');

$router->get('/register', AuthController::class, 'registerFormAction');
$router->post('/register', AuthController::class, 'registerSubmitAction');
$router->get('/login', AuthController::class, 'loginFormAction');
$router->post('/login', AuthController::class, 'loginSubmitAction');
$router->post('/logout', AuthController::class, 'logoutAction');

// private routes (authed only)
$router->get('/dashboard', DashboardController::class, 'dashboardAction');
$router->get('/clients', ClientController::class, 'clientsAction');
$router->post('/clients/get', ClientController::class, 'getClientByIdAjaxAction');
$router->post('/clients/add', ClientController::class, 'addClientAction');
$router->post('/clients/delete', ClientController::class, 'deleteClientAction');
$router->post('/clients/edit', ClientController::class, 'editClientAction');


$router->dispatch($db);

