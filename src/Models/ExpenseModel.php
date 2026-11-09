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
    public function findReceiptById(int $id): array|false
    {
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

        // TODO: FIX fails on jpg (so maybe also png) upload ... only tested for pdf (which works)
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
        if ($f['error'] !== UPLOAD_ERR_OK) throw new \RuntimeException('Upload failed');
        // throw exception if file_size > 5MB
        if ($f['size']  > self::MAX_SIZE) throw new \RuntimeException('File too large');

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

    public function updateExpense(int $id, array $expenseData): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        // TODO: Implement (+fileupload)
        return false;
    }

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


}