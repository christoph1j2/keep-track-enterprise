<?php

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
     * Fetches specific user using id
     *
     * @param int $id
     * @return mixed
     */
    public function findById(int $id): mixed
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ". $this->table ."
            WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }
}