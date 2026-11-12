<?php
declare(strict_types=1);

namespace Src\Models;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    /**
     * Method for creating a user (registering).
     *
     * @param string $username - the inputted username
     * @param string $email - the inputted email
     * @param string $password_hash - hashed password
     * @return bool
     */
    public function createUser(string $username, string $email, string $password_hash, ?string $accountant_key): int|false
    {
        try {
            $this->db->beginTransaction();

            $roleId = ($accountant_key !== null) ? 4 : 3; // 4 = acc; 3 = osvc

            // insert new user
            $stmt = $this->db->prepare("
                INSERT INTO " . $this->table . " (username, email, password_hash, role_id)
                VALUES (:username, :email, :password_hash, :role_id)
            ");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':role_id' => $roleId
            ]);

            $newUserId = (int) $this->db->lastInsertId();

            // if we're adding an accountant, find corresponding user and connect them
            // via the accountant_id column in user table
            if ($accountant_key !== null)
            {
                $stmt = $this->db->prepare("
                    UPDATE ".$this->table." 
                    SET accountant_id = :accountant_id,
                        accountant_key = NULL,
                        accountant_key_expires = NULL
                    WHERE accountant_key = :key
                        AND accountant_key_expires > NOW()
                ");
                $stmt->execute([
                    ':accountant_id' => $newUserId,
                    ':key' => $accountant_key
                ]);

                // if key expired during transaction or wasnt found then rollback
                if ($stmt->rowCount() === 0)
                {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return $newUserId;
        }
        catch (\PDOException $e)
        {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            return false;
        }
    }

    /**
     * Fetches specific user using email.
     *
     * @param string $email - Email of the user
     * @return mixed
     */
    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.role AS role_name
            FROM ". $this->table ." u
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = :email
        ");
        $stmt->execute([':email' => $email]);

        return $stmt->fetch();
    }

    /**
     * Fetches specific user using username.
     *
     * @param string $username - Username of the user
     * @return mixed
     */
    public function findByUsername(string $username)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ". $this->table ." 
            WHERE username = :username");
        $stmt->execute([':username' => $username]);

        return $stmt->fetch();
    }

    /**
     * Fetches specific user using id, including the role name
     *
     * @param int $id
     * @return mixed
     */
    public function findById(int $id): mixed
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.role as role_name 
            FROM ". $this->table ." u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    /**
     * Generates a key for linking a user with an accountant
     *
     * @param int $id
     * @return string
     * @throws \Random\RandomException
     */
    public function generateAccountantKey(int $id): string
    {
        // generate key
        $key = bin2hex(random_bytes(8));
        // set expiry
        $expiration = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        // update user
        $stmt = $this->db->prepare("
            UPDATE " . $this->table . "
            SET accountant_key = :key, accountant_key_expires = :expiration
            WHERE id = :id"
        );
        $stmt->execute([
            ':id' => $id,
            ':key' => $key,
            ':expiration' => $expiration
        ]);

        return $key;
    }

    /**
     * Unlinks the accountant with user with specified id
     *
     * @param int $id
     * @return bool
     */
    public function unlinkAccountant(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE " . $this->table . "
            SET accountant_id = NULL
            WHERE id = :id"
        );
        $stmt->execute([
            ':id' => $id,
        ]);

        return (bool) $stmt->rowCount();
    }

    // ####### Accountant AC logic ########

    /**
     * Gets all linked OSVC to accountant
     *
     * @param int $accountantId
     * @return array
     */
    public function getClientsOf(int $accountantId): array
    {
        $sql = "SELECT id, username, profession FROM ".$this->table." 
                WHERE accountant_id = :accountantId 
                ORDER BY username";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':accountantId' => $accountantId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns bool value of if a specific osvc is a client of a specific accountant
     *
     * @param int $accountantId
     * @param int $osvcId
     * @return bool
     */
    public function isClientOf(int $accountantId, int $osvcId): bool
    {
        $sql = "SELECT 1 FROM ".$this->table." 
                WHERE 
                    accountant_id = :accountantId
                    AND id = :osvcId
                LIMIT 1";
        $stmt = $this->db->prepare($sql);

        $stmt->execute([':accountantId' => $accountantId, ':osvcId' => $osvcId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * This method is used to link more clients to
     * accountants after they had already registered
     *
     * @param string $key
     * @param int $accountantId
     * @return bool
     */
    public function redeemKey(string $key, int $accountantId): bool
    {
        if ($key === '')
            return false;

        // check that osvc doesnt have accountant yet and that
        // his key is not expired and valid, if all checks pass,
        // then update the specific user
        $sql = "UPDATE ".$this->table."
                SET accountant_id = :accountantId,
                    accountant_key = NULL,
                    accountant_key_expires = NULL
                WHERE accountant_key = :key
                AND accountant_key_expires > :now
                AND accountant_id IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':key' => $key,
            ':now' => date("Y-m-d H:i:s"),
            'accountantId' => $accountantId,
        ]);

        return $stmt->rowCount() === 1;
    }
}