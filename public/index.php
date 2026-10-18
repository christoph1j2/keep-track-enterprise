<?php 
declare(strict_types=1);

// TODO: session and cookie init

require __DIR__ . '/../vendor/autoload.php';

use Src\Core\Database;
use Src\Core\Router;

/** @var Database $database */
$database = Database::getInstance();
$db = $database->getConnection();

$router = new Router();

// route registrations
// ...

$router->dispatch($db);

// TEMPORARY

echo "<h1>Cigi Nigi</h1>";
var_dump($database);
echo "<br/>";
var_dump($db);

phpInfo();

