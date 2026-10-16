<?php

namespace Src\Models;

class UserModel extends BaseModel
{
    protected $table = 'users';

    /**
     * Fetches specific user using email.
     *
     * @param string $email - Email of the user
     * @return mixed
     */
    public function findByEmail(string $email)
    {
        $stmt = $this->db->query("SELECT * FROM ". $this->table ." WHERE email = :email");
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
        $stmt = $this->db->query("SELECT * FROM ". $this->table ." WHERE username = :username");
        $stmt->execute([':username' => $username]);

        return $stmt->fetch();
    }
}