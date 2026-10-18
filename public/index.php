<?php 
declare(strict_types=1);

// TODO: session and cookie init

require __DIR__ . '/../vendor/autoload.php';

use Src\Core\Database;

/** @var Database $database */
$database = Database::getInstance();
/** @var \PDO $db */
$db = $database->getConnection();

// TODO: router
// TODO: routes

echo "<h1>Cigi Nigi</h1>";
var_dump($database);
echo "<br/>";
var_dump($db);

phpInfo();

