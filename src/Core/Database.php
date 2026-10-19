<?php
declare(strict_types=1);

namespace Src\Core;
use PDO;

final class Database
{
    // PDO object
    private ?PDO $connection;

    /**
     * The  constructor.
     */
    public function __construct()
    {
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

        try
        {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        }
        catch (\PDOException $e)
        {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Getter for database connection PDO object
     *
     * @return PDO - The PDO instance.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}