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
$router->post('/logout', AuthController::class, 'logoutAction');

$router->dispatch($db);

// TEMPORARY

//echo "<h1>Cigi Nigi</h1>";
//var_dump($database);
//echo "<br/>";
//var_dump($db);
//
//phpInfo();

