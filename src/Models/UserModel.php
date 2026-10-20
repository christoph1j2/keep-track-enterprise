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
    public function createUser(string $username, string $email, string $password_hash): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO ". $this->table ." (username, email, password_hash) 
            VALUES (:username, :email, :password_hash)");
        return $stmt->execute([
            ":username" => $username,
            ":email" => $email,
            ":password_hash" => $password_hash
        ]);
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