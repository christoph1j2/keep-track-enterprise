<?php
declare(strict_types=1);

namespace Src\Models;

class PaymentModel extends BaseModel
{
    protected string $table = 'payments';
    protected array $helperTables = [
        'join' => 'invoice_payment',
        'invoice' => 'invoices',
        'client' => 'clients',
    ];

    /**
     * Gets all invoices for the current user JOIN on clients; JOIN on invoices; join on invoice_payment
     *
     * @return array
     */
    public function getAllPayments(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        // join thru payments -> invoice_payment -> invoices -> clients -> users
        $sql = "SELECT 
                    p.*,
                    GROUP_CONCAT(i.invoice_number SEPARATOR ', ' ) AS invoice_numbers
                FROM ".$this->table." p
                    INNER JOIN ".$this->helperTables['join']." ip ON p.id = ip.payment_id
                    INNER JOIN ".$this->helperTables['invoice']." i ON ip.invoice_id = i.id
                    INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE c.user_id = :user_id
                GROUP BY p.id
                ORDER BY p.date_paid DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Gets a single invoice by ID JOIN on clients, invoices and join table
     *
     * @param int $id
     * @return array|false
     */
    public function getPaymentById(int $id): array|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = "SELECT DISTINCT p.*
                FROM ".$this->table." p
                    INNER JOIN ".$this->helperTables['join']." ip ON p.id = ip.payment_id
                    INNER JOIN ".$this->helperTables['invoice']." i ON ip.invoice_id = i.id
                    INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE 
                    c.user_id = :user_id
                    AND p.id = :id
                ORDER BY p.date_paid DESC
                ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $userId
        ]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Gets a single pairing (amount assigned, invoice number & invoice total amount) by payment ID
     *
     * @param int $id
     * @return array|false
     */
    public function getPaymentPairings(int $id): array|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = "SELECT 
                    ip.amount_assigned, 
                    i.invoice_number, 
                    i.total_amount
                FROM ".$this->helperTables['join']." ip
                    INNER JOIN ".$this->helperTables['invoice']." i ON ip.invoice_id = i.id
                WHERE ip.payment_id = :payment_id
              ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':payment_id' => $id
        ]);
        return $stmt->fetchAll() ?: false;
    }

    /**
     * Helper method for add payment form, fetches unpaid invoices and the remaining amount
     *
     * @return array
     */
    public function getUnpaidInvoices(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = "
            SELECT 
                i.id AS invoice_id,
                i.invoice_number,
                i.date_due,
                i.total_amount,
                COALESCE(SUM(ip.amount_assigned), 0) AS total_assigned,
                (i.total_amount - COALESCE(SUM(ip.amount_assigned), 0)) AS remaining_amount
            FROM ".$this->helperTables['invoice']." i
            LEFT JOIN ".$this->helperTables['join']." ip ON i.id = ip.invoice_id
            INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
            WHERE 
                c.user_id = :user_id
                AND i.paid = 0       -- only unpaid invoices
            GROUP BY
                i.id,
                i.invoice_number,
                i.date_due,
                i.total_amount
            ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Creates a payment with pairings to invoices
     *
     * @param array $paymentData
     * @param array $pairings
     * @return bool
     */
    public function createPaymentWithPairing(array $paymentData, array $pairings): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // atomic transaction
        $this->db->beginTransaction();
        try
        {
            // insert record into payments
            $paymentId = $this->createPayment($paymentData);
            if (!$paymentId)
            {
                $this->db->rollBack();
                return false;
            }

            // create pairings (pairing = join table record)
            foreach ($pairings as $pairing) {
                $pairing['payment_id'] = $paymentId;
                $this->createPairing($pairing);
                $this->checkAndUpdateInvoicePaidStatus((int) $pairing['invoice_id']);
            }

            $this->db->commit();
            return true;
        }
        catch (\Throwable $e)
        {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Creates a new payment record and returns its id
     *
     * @param array $data
     * @return string
     */
    private function createPayment(array $data): string
    {
        $sql = "INSERT INTO ".$this->table." 
                (specific_symbol, amount, date_paid, note)
                VALUES (:specific_symbol, :amount, :date_paid, :note)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $this->db->lastInsertId();
    }

    /**
     * Creates a new pairing (join table) record
     *
     * @param array $pairing
     * @return void
     */
    private function createPairing(array $pairing): void
    {
        $sql = "INSERT INTO ".$this->helperTables['join']."
                (invoice_id, payment_id, amount_assigned)
                VALUES (:invoice_id, :payment_id, :amount_assigned)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':invoice_id' => $pairing['invoice_id'],
            ':payment_id' => $pairing['payment_id'],
            ':amount_assigned' => $pairing['amount_assigned']
        ]);
    }

    /**
     * Method for updating the paid status of an invoice after a payment has been added
     *
     * @param int $invoiceId
     * @return bool
     */
    private function checkAndUpdateInvoicePaidStatus(int $invoiceId): bool
    {
        try
        {
            // get total paid (via coalesce+sum) and get total amount to be paid (from invoice)
            $sql = "SELECT
                        i.total_amount,
                        COALESCE(SUM(amount_assigned), 0) AS total_paid 
                    FROM ".$this->helperTables['invoice']." i
                    LEFT JOIN ".$this->helperTables['join']." ip ON i.id = ip.invoice_id
                    WHERE i.id = :invoice_id
                    GROUP BY i.id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':invoice_id' => $invoiceId
            ]);
            $invoiceData = $stmt->fetch();

            if (!$invoiceData) return false;

            $totalAmount = (float) $invoiceData['total_amount'];
            $paidAmount = (float) $invoiceData['total_paid'];

            // compare amounts and set new state (paid / not paid)
            $isPaid = ($paidAmount >= $totalAmount) ? 1 : 0;

            // update state in invoice table
            $sql = "UPDATE " . $this->helperTables['invoice'] ."
                          SET paid = :paid 
                          WHERE id = :invoice_id";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':paid' => $isPaid,
                ':invoice_id' => $invoiceId
            ]);
        }
        catch (\Throwable $e)
        {
            return false;
        }

    }

    /**
     * Deletes a payment record
     *
     * @param int $paymentId
     * @return bool
     */
    public function deletePayment(int $paymentId): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // get IDs of linked invoices
        $sql = "SELECT invoice_id FROM ".$this->helperTables['join']." 
                WHERE payment_id = :payment_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':payment_id' => $paymentId
        ]);
        $invoiceIds = $stmt->fetchAll(\PDO::FETCH_COLUMN); // fetch column only instead of assoc array

        // join thru payments -> invoice_payment -> invoices -> clients -> users
        $sql = "DELETE p FROM ".$this->table." p
                INNER JOIN ".$this->helperTables['join']." pi ON p.id = pi.payment_id
                INNER JOIN ".$this->helperTables['invoice']." i ON pi.invoice_id = i.id
                INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE c.user_id = :user_id AND p.id = :payment_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':payment_id' => $paymentId
        ]);

        // update invoices and their paid status
        foreach ($invoiceIds as $invoiceId)
            $this->checkAndUpdateInvoicePaidStatus($invoiceId);

        return $stmt->rowCount() > 0;
    }

    /**
     * Returns paginated payments for the current user
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginated(int $page, int $perPage): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    p.*,
                    GROUP_CONCAT(i.invoice_number SEPARATOR ', ') AS invoice_numbers
                FROM ".$this->table." p
                    INNER JOIN ".$this->helperTables['join']." ip ON p.id = ip.payment_id
                    INNER JOIN ".$this->helperTables['invoice']." i ON ip.invoice_id = i.id
                    INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE c.user_id = :user_id
                GROUP BY p.id
                ORDER BY p.date_paid DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':limit' => $perPage, ':offset' => $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Gets a total count of all payments for the current user. (KPI / pagination purposes)
     *
     * @return int
     */
    public function totalCount(): int
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return -1;

        $sql = "SELECT COUNT(DISTINCT p.id) AS total
                FROM ".$this->table." p
                    INNER JOIN ".$this->helperTables['join']." ip ON p.id = ip.payment_id
                    INNER JOIN ".$this->helperTables['invoice']." i ON ip.invoice_id = i.id
                    INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE c.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Returns sum of all total_amount entries
     *
     * @return float
     */
    public function totalSum(): float
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return -1.0;

        $sql = "SELECT COALESCE(SUM(amount_assigned), 0) FROM ".$this->table." p
                INNER JOIN ".$this->helperTables['join']." pi ON p.id = pi.payment_id
                INNER JOIN ".$this->helperTables['invoice']." i ON pi.invoice_id = i.id
                INNER JOIN ".$this->helperTables['client']." c ON i.client_id = c.id
                WHERE c.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }
}