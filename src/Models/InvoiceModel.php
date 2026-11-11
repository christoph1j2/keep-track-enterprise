<?php
declare(strict_types=1);

namespace Src\Models;

class InvoiceModel extends BaseModel
{
    protected string $table = 'invoices';
    protected string $invoiceItemsTable = 'invoice_items';
    protected string $clientTable = 'clients';

    /**
     * Gets all invoices for the current user JOIN on clients
     *
     * @return array
     */
    public function getAllInvoices(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        // we have to join through clients
        $sql = "SELECT i.*, c.name AS client_name
                FROM ". $this->table ." i
                LEFT JOIN clients c ON i.client_id = c.id
                WHERE c.user_id = :id
                ORDER BY i.date_issued DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Gets a single invoice by ID JOIN on clients
     *
     * @param int $id
     * @return array|false
     */
    public function getInvoiceById(int $id): array|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $sql = "SELECT i.*, c.name AS client_name
                FROM ". $this->table ." i
                LEFT JOIN ". $this->clientTable ." c ON i.client_id = c.id
                WHERE i.id = :id AND c.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Gets all invoice items for a given invoice ID
     *
     * @param int $id
     * @return array|false
     */
    public function getInvoiceItems(int $id): array|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $sql = "SELECT * FROM ". $this->invoiceItemsTable ." WHERE invoice_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll() ?: false;
    }

    /**
     * Creates an invoice with items
     *
     * @param array $invoiceData
     * @param array $items
     * @return string|bool
     */
    public function createInvoiceWithItems(array $invoiceData, array $items): string|bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // atomicka transakce
        $this->db->beginTransaction();
        try
        {
            // create invoice
            $invoiceId = $this->createInvoice($invoiceData);
            if (!$invoiceId)
            {
                $this->db->rollBack();
                return false;
            }

            // create invoice items
            foreach ($items as $item) {
                $item['invoice_id'] = $invoiceId;
                $this->createInvoiceItem($item);
            }

            $this->db->commit();
            return $invoiceId;
        }
        catch (\Throwable $e)
        {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Creates a new invoice record and returns its generated ID.
     *
     * @param array $data
     * @return false|string
     */
    private function createInvoice(array $data): false|string
    {
        $sql = "INSERT INTO ". $this->table ." 
                (client_id, invoice_number, name, date_due, date_issued, total_amount, paid) 
                VALUES (:client_id, :invoice_number, :name, :date_due, :date_issued, :total_amount, :paid)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Creates a new invoice item record and returns its generated ID.
     *
     * @param array $item
     * @return void
     */
    private function createInvoiceItem(array $item): void
    {
        $sql = "INSERT INTO ". $this->invoiceItemsTable ." 
                (invoice_id, name, quantity, unit_price) 
                VALUES (:invoice_id, :name, :quantity, :unit_price)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':invoice_id' => (int)$item['invoice_id'],
            ':name' => $item['name'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price']
        ]);
    }

    /**
     * Deletes an invoice record
     *
     * @param int $invoiceId
     * @return bool
     */
    public function deleteInvoice(int $invoiceId): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // we have to join through clients
        $sql = "DELETE i FROM ". $this->table ." i
                INNER JOIN clients c ON i.client_id = c.id
                WHERE i.id = :id AND c.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $invoiceId, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function totalSum(): float
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return -1;

        // TODO FIX
        $sql = "
            SELECT COALESCE(SUM(total_amount), 0) AS total FROM " . $this->table . " i 
            INNER JOIN clients c on i.client_id = c.id
            WHERE c.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch()['total'];
    }
}