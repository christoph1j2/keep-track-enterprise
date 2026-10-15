<?php 
declare(strict_types=1);
use Src\Models\BaseModel;

require __DIR__ . '/../vendor/autoload.php';

echo "<h1>Cigi Nigi</h1>";

phpInfo();

// temp
// $baseModel = new BaseModel(new PDO('mysql:host=db;dbname=keeptrack;charset=utf8mb4', 'myuser', 'mypassword'));
// $baseModel->table = "client_types";
// $types = $baseModel->getAll();
// foreach ($types as $type) {
//     echo "<p>Client Type: " . htmlspecialchars($type['name']) . "</p>";
// }

?>