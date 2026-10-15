<?php
declare(strict_types=1);

namespace Src\Core;
use PDO;

final class Database
{
    private ?PDO $connection = null;

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO)
        {
            return $this->connection;
        }

        $host = getenv('DB_HOST');
        $db = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Create a new PDO instance
        try
        {
            $this->connection = new PDO($dsn, $user, $pass, $options);
            return $this->connection;
        } 
        catch (\PDOException $e) 
        {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }
}