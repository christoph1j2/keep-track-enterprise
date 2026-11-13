<?php
declare(strict_types=1);

namespace Src\Models;

use finfo;
use Random\RandomException;

class ExpenseModel extends BaseModel
{
    private const MAX_SIZE      = 5 * 1024 * 1024; // 5MB
    private const MIME_ALLOWED  = ['application/pdf', 'image/jpeg', 'image/png'];
    private const DIR           = __DIR__ . '/../../storage/receipts';

    protected string $table = 'expenses';

    /**
     * Gets all expenses of a user, data including corresponding file
     *
     * @return array
     */
    public function getAllExpenses(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = "SELECT * FROM ".$this->table."
                WHERE user_id = :user_id
                ORDER BY expense_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    /**
     * Gets a specific expense of a user by id, data including corresponding file
     *
     * @param int $id
     * @return array|false
     */
    public function getExpenseById(int $id): array|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $sql = "SELECT DISTINCT * FROM ".$this->table."
                WHERE user_id = :user_id AND id = :expense_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':expense_id' => $id]);

        return $stmt->fetch() ?: false;
    }

    /**
     * Returns file data for specific user by id
     *
     * @param int $id
     * @return array|false
     */
    public function findReceiptById(int $id, int $userId): array|false
    {
        if ($userId == null)
            $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $sql = "SELECT receipt_filename, receipt_original_name, receipt_mime
                FROM ".$this->table."
                WHERE user_id = :user_id 
                    AND id = :expense_id 
                    AND receipt_filename IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':expense_id' => $id]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Saves a new expense record to DB, if a file is included in the request,
     *
     * @param array $expenseData
     * @return bool
     * @throws RandomException
     */
    public function createExpense(array $expenseData): string|false
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $receipt = null;
        $file = $expenseData['receipt'] ?? null;

        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE)
            $receipt = $this->saveFile($file);

        try
        {
            $sql = "
                INSERT INTO ".$this->table."
                    (user_id, 
                    name,
                    total_amount, 
                    expense_date, 
                    note, 
                    receipt_filename, 
                    receipt_original_name, 
                    receipt_mime)
                VALUES
                    (:user_id,
                    :name,
                    :total_amount,
                    :expense_date,
                    :note,
                    :receipt_filename,
                    :receipt_original_name,
                    :receipt_mime)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $expenseData['name'],
                ':total_amount' => $expenseData['total_amount'],
                ':expense_date' => $expenseData['expense_date'],
                ':note' => $expenseData['note'] ?? null,
                ':receipt_filename' => $receipt['filename'] ?? null,
                ':receipt_original_name' => $receipt['original'] ?? null,
                ':receipt_mime' => $receipt['mime'] ?? null,
            ]);
            return $this->db->lastInsertId();
        }
        catch (\Throwable $e)
        {
            // if an exception occurs during record creating, delete the file
            if ($receipt) @unlink(self::DIR . '/' . $receipt['filename']);
            throw $e;
            return false;
        }
    }

    /**
     * Helper method to save a file
     *
     * @param array $f
     * @return array
     * @throws \Random\RandomException
     */
    private function saveFile(array $f): array
    {
        // throw exception if upload_err_ok flag is not set
        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($f['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký (max 5 MB).',
                UPLOAD_ERR_PARTIAL                        => 'Nahrávání bylo přerušeno, zkus to znovu.',
                default                                   => 'Nahrání souboru selhalo.',
            });
        }        // throw exception if file_size > 5MB
        if ($f['size']  > self::MAX_SIZE) throw new \RuntimeException('Soubor je příliš velký.');

        // get mime via finfo lib; throw exception if mime isnt in allowed array
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime, self::MIME_ALLOWED, true)) throw new \RuntimeException('Invalid file type');

        // generate 32 byte pseudorandom name
        $name = bin2hex(random_bytes(32)); // 1 in 2^256 chance of 2 names being the same
        // try to move to upload file directory, throw exception if unable
        if (!move_uploaded_file($f['tmp_name'], self::DIR . '/' . $name))
            throw new \RuntimeException('Failed to move uploaded file');

        return ['filename' => $name, 'original' => basename($f['name']), 'mime' => $mime];
    }

    /**
     * Updates an expense with new data, can remove old file and add new file
     *
     * @param int $id
     * @param array $expenseData
     * @return bool
     * @throws RandomException
     * @throws \Throwable
     */
    public function updateExpense(int $id, array $expenseData): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // current row
        $current = $this->getExpenseById($id);
        if (!$current) return false;

        $oldFilename = $current['receipt_filename'];
        $newReceipt = null; // only if we save new file
        $file = $expenseData['receipt'] ?? null;

        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE)
        {
            // 1) new file uploaded -> replace old file
            $newReceipt = $this->saveFile($file);
            $receipt = $newReceipt;
        }
        elseif (!empty($expenseData['remove_receipt']))
        {
            // 2) just remove the receipt
            $receipt = ['filename' => null, 'original' => null, 'mime' => null];
        }
        else
        {
            // 3) keep what we have
            $receipt = [
                'filename' => $current['receipt_filename'],
                'original' => $current['receipt_original_name'],
                'mime' => $current['receipt_mime'],
            ];
        }

        try
        {
            $sql = "UPDATE ".$this->table."
                    SET name = :name,
                        total_amount = :total_amount,
                        expense_date = :expense_date,
                        note = :note,
                        receipt_filename = :receipt_filename,
                        receipt_original_name = :receipt_original_name,
                        receipt_mime = :receipt_mime
                    WHERE id = :expense_id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':name' => $expenseData['name'],
                ':total_amount' => $expenseData['total_amount'],
                ':expense_date' => $expenseData['expense_date'],
                ':note' => $expenseData['note'] ?: null,
                ':receipt_filename' => $receipt['filename'],
                ':receipt_original_name' => $receipt['original'],
                ':receipt_mime' => $receipt['mime'],
                ':expense_id' => $id,
                ':user_id' => $userId
            ]);
        } catch (\Throwable $e)
        {
            // update failed -> throw away the new file, old one still referenced
            if ($newReceipt) @unlink(self::DIR . '/' . $newReceipt['filename']);
            throw $e;
        }

        // DB is updated, now remove old file as it is no longer referenced
        if ($oldFilename && $oldFilename !== $receipt['filename'])
        {
            $path = self::DIR . '/' . $oldFilename;
            if (is_file($path)) @unlink($path);
        }

        return true;
    }

    /**
     * Delets an existing expense
     *
     * @param int $id
     * @return bool
     */
    public function deleteExpense(int $id): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // first we get reference to receipt file
        $receipt = $this->findReceiptById($id);
        $filename = $receipt['receipt_filename'] ?? null;

        try
        {
            // then try to delete record
            $sql = "DELETE FROM ".$this->table."
                    WHERE id = :id AND user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id, ':user_id' => $userId]);
            if ($stmt->rowCount() === 0)
            {
                return false;
            }

        }
        catch (\Throwable $e)
        {
            return false;
        }

        // now delete the file itself, after we know the deletion was successful
        if ($filename) {
            $path = self::DIR . '/' . $filename;
            if (is_file($path)) @unlink($path);
        }
        return true;
    }


    /**
     * Returns paginated expenses for the current user
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginated(int $page, int $perPage, ?int $userId): array
    {
        if ($userId == null)
            $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM ".$this->table."
                WHERE user_id = :user_id
                ORDER BY expense_date DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':limit' => $perPage, ':offset' => $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Gets a total count of all expenses for the current user. (KPI / pagination purposes)
     *
     * @return int
     */
    public function totalCount(?int $userId): int
    {
        if ($userId == null)
            $userId = $_SESSION['user_id'];
        if (!$userId) return -1;

        $sql = "SELECT COUNT(*) AS total FROM ".$this->table." WHERE user_id = :user_id";
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

        $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM ".$this->table." e
                WHERE e.user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return (float) $stmt->fetchColumn();
    }
}