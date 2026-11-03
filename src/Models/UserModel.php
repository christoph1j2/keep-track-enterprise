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
    public function createUser(string $username, string $email, string $password_hash, ?string $accountant_key): bool
    {
        if ($accountant_key === null)
        {
            // insert user into db
            $stmt = $this->db->prepare("
                INSERT INTO ". $this->table ." (username, email, password_hash, role_id) 
                VALUES (:username, :email, :password_hash, :role_id)");
            return $stmt->execute([
                ":username" => $username,
                ":email" => $email,
                ":password_hash" => $password_hash,
                ":role_id" => 3
            ]);
        }
        else
        {
            // insert accountant into db
            $stmt = $this->db->prepare("
                INSERT INTO ". $this->table ." (username, email, password_hash, role_id)
                VALUES (:username, :email, :password_hash, :role_id)");
            $stmt->execute([
                ":username" => $username,
                ":email" => $email,
                ":password_hash" => $password_hash,
                ":role_id" => 4
            ]);

            // update linked user with accountant id
            $stmt = $this->db->prepare("
                UPDATE ". $this->table ." 
                SET accountant_id :acc_id 
                WHERE user_id = :user_id"
            );
            $accountant_id = $this->db->lastInsertId();
            // TODO FINISH
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
        $stmt = $this->db->prepare("SELECT * FROM ". $this->table ." WHERE email = :email");
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
        $stmt = $this->db->prepare("SELECT * FROM ". $this->table ." WHERE username = :username");
        $stmt->execute([':username' => $username]);

        return $stmt->fetch();
    }
}