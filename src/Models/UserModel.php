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
                        AND accountant_id IS NULL
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
        // sanity check
        $stmt = $this->db->prepare("SELECT accountant_id FROM " . $this->table . " WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || $user['accountant_id'] !== null) {
            return '';
        }

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

    // ####### Admin User Management logic ########

    /**
     * Gets all available roles.
     *
     * @return array
     */
    public function getAllRoles(): array
    {
        $stmt = $this->db->query("SELECT id, role FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Finds a role by name.
     *
     * @param string $roleName
     * @return array|false
     */
    public function getRoleByName(string $roleName): array|false
    {
        $stmt = $this->db->prepare("SELECT id, role FROM roles WHERE role = :role LIMIT 1");
        $stmt->execute([':role' => $roleName]);
        return $stmt->fetch() ?: false;
    }

    /**
     * Returns total count of users in the system.
     *
     * @return int
     */
    public function totalUsersCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM " . $this->table)->fetchColumn();
    }

    public function totalBlockedCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM " . $this->table ." WHERE is_blocked = 1")->fetchColumn();
    }

    /**
     * Returns paginated list of users with their role names (without password hashes).
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getPaginatedUsers(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT u.id, u.username, u.email, u.profession, u.is_blocked, u.role_id, r.role AS role_name
                FROM " . $this->table . " u
                LEFT JOIN roles r ON u.role_id = r.id
                ORDER BY u.id ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        // PDO::PARAM_INT is required for LIMIT/OFFSET when ATTR_EMULATE_PREPARES is false
        $stmt->bindParam(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Updates blocked status for a user.
     *
     * @param int $userId
     * @param bool $blocked
     * @return bool
     */
    public function setBlocked(int $userId, bool $blocked): bool
    {
        $stmt = $this->db->prepare("UPDATE " . $this->table . " SET is_blocked = :blocked WHERE id = :id");
        $stmt->execute([
            ':blocked' => $blocked ? 1 : 0,
            ':id'      => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Updates role_id for a user.
     *
     * @param int $userId
     * @param int $roleId
     * @return bool
     */
    public function updateUserRole(int $userId, int $roleId): bool
    {
        $stmt = $this->db->prepare("UPDATE " . $this->table . " SET role_id = :role_id WHERE id = :id");
        $stmt->execute([
            ':role_id' => $roleId,
            ':id'      => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deletes a user and clears any dangling accountant_id references.
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUser(int $userId): bool
    {
        try
        {
            $this->db->beginTransaction();

            // Unlink OSVC users if deleted user was their accountant
            $stmt = $this->db->prepare("UPDATE " . $this->table . " SET accountant_id = NULL WHERE accountant_id = :id");
            $stmt->execute([':id' => $userId]);

            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $deleted = $stmt->rowCount() > 0;

            $this->db->commit();
            return $deleted;
        }
        catch (\Throwable $e)
        {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            return false;
        }
    }

    /**
     * Returns system-wide counts for the admin overview page.
     *
     * @return array
     */
    public function getAdminOverviewStats(): array
    {
        $roleRows = $this->db->query("
            SELECT COALESCE(r.role, 'none') AS role_name, COUNT(*) AS cnt
            FROM " . $this->table . " u
            LEFT JOIN roles r ON u.role_id = r.id
            GROUP BY r.role
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $cCount = $this->db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $iCount = $this->db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
        $pCount = $this->db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
        $eCount = $this->db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();

        return [
            'users_total'  => $this->totalUsersCount(),
            'users_blocked'=> $this->totalBlockedCount(),
            'osvc'         => (int) ($roleRows['osvc'] ?? 0),
            'ucetni'       => (int) ($roleRows['ucetni'] ?? 0),
            'admins'       => (int) (($roleRows['admin'] ?? 0) + ($roleRows['superadmin'] ?? 0)),
            'clients'      => (int) ($cCount),
            'invoices'     => (int) ($iCount),
            'payments'     => (int) ($pCount),
            'expenses'     => (int) ($eCount),
        ];
    }
}