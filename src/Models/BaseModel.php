<?php
declare(strict_types=1);

namespace Src\Models;

use PDO;

abstract class BaseModel{
    
    // Database connection
    protected PDO $db;
    
    // Table name for the model
    protected $table;
    
    // Whitelist of allowed tables
    protected $allowedTables =
    [
        'users',
        'clients',
        'client_types',
        'invoices',
        'invoice_items',
        'payments',
        'invoice_payment',
        'expenses',
        'expense_categories',
    ];

    /**
     * BaseModel constructor.
     * @param PDO $db - Database connection
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

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
}