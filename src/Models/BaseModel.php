<?php
declare(strict_types=1);

namespace Src\Models;

use PDO;

abstract class BaseModel {
    
    // Database connection
    protected PDO $db;
    
    // Table name for the model
    protected string $table;
    
    // Whitelist of allowed tables
    protected array $allowedTables =
    [
        'users',
        'clients',
        'client_types',
        'invoices',
        'invoice_items',
        'payments',
        'invoice_payment',
        'expenses',
    ];

    /**
     * BaseModel constructor.
     * @param PDO $db - Database connection
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Gets all database entries.
     * @return array - Array of all DB entries
     */
    public function getAll(): array
    {
        // If table is not whitelisted, throw exception
        if (!in_array($this->table, $this->allowedTables, true))
        {
            throw new \InvalidArgumentException("Invalid table.");
        }

        $stmt = $this->db->query("SELECT * FROM ". $this->table);
        return $stmt->fetchAll();
    }

    /**
     * Fetches a specific entry by using ID.
     *
     * @param $id - Unique identifier
     * @return array|false - array if found, else false
     */
    public function getById($id): false|array
    {
        if (!in_array($this->table, $this->allowedTables, true))
        {
            throw new \InvalidArgumentException("Invalid table.");
        }

        $stmt = $this->db->prepare("SELECT * FROM ". $this->table ." WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Deletes a specific entry by using ID.
     *
     * @param $id - Unique identifier
     * @return bool
     */
    public function delete($id): bool
    {
        if (!in_array($this->table, $this->allowedTables, true))
        {
            throw new \InvalidArgumentException("Invalid table.");
        }

        $stmt = $this->db->prepare("DELETE FROM ". $this->table ." WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}