<?php
declare(strict_types=1);

namespace Src\Core;
use PDO;

final class Database
{
    // Single instance (Singleton)
    private static ?Database $instance = null;

    // PDO object
    private ?PDO $connection;

    /**
     * Private constructor to prevent external instantiation
     */
    private function __construct()
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
     * Getter for database singleton instance
     *
     * @return Database|null - The Database instance.
     */
    public static function getInstance(): ?Database
    {
        // if not instantiated
        if (self::$instance === null)
        {
            // instantiate
            self::$instance = new Database();
        }

        // return
        return self::$instance;
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