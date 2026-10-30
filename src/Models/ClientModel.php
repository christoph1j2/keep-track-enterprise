<?php

namespace Src\Models;

use Src\Models\BaseModel;

class ClientModel extends BaseModel
{
    protected string $table = 'clients';
    protected string $table_types = 'client_types';

    /**
     * Gets client array from database for current user
     *
     * @return array|array|\mixed[][]|\stdClass[]
     */
    public function getAllClients(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = 'SELECT c.*, ct.name AS type_name
                FROM '. $this->table .' c
                LEFT JOIN '. $this->table_types .' ct ON ct.id = c.type_id
                WHERE c.user_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Returns user with specific ID
     *
     * @param int $id
     * @return array
     */
    public function getClientById(int $id): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $sql = 'SELECT c.*, ct.name AS type_name
                FROM '. $this->table .' c
                LEFT JOIN '. $this->table_types .' ct ON ct.id = c.type_id
                WHERE c.id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Adds a client with all params
     *
     * @param string $name
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $ico
     * @param string|null $dic
     * @param string|null $description
     * @param int|null $type
     * @return bool
     */
    public function addClient(
        string $name,
        ?string $email,
        ?string $phone,
        ?string $ico,
        ?string $dic,
        ?string $description,
        ?int $type
    ): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $stmt = $this->db->prepare(
            "INSERT INTO " . $this->table .
            " (name, user_id, contact_email, contact_phone, description, ico, dic, type_id)
            VALUES (:name, :user_id, :email, :phone, :description, :ico, :dic, :type_id)");
        return $stmt->execute([
            ':name' => $name,
            ':user_id' => $userId,
            ':email' => $email,
            ':phone' => $phone,
            ':description' => $description,
            ':ico' => $ico,
            ':dic' => $dic,
            ':type_id' => $type
        ]);
    }

    /**
     * Deletes a client from DB
     *
     * @param int $id - id of the client entry
     * @return bool
     */
    public function deleteClient(int $id): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    /**
     * Saves a client with a specific ID with updates parameters
     *
     * @param int $id
     * @param string $name
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $ico
     * @param string|null $dic
     * @param string|null $description
     * @param int|null $type
     * @return bool
     */
    public function editClient(
        int $id,
        string $name,
        ?string $email,
        ?string $phone,
        ?string $ico,
        ?string $dic,
        ?string $description,
        ?int $type): bool
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return false;

        $stmt = $this->db->prepare("UPDATE " . $this->table . "
                            SET name = :name,
                                contact_email = :email,
                                contact_phone = :phone,
                                description = :description,
                                ico = :ico,
                                dic = :dic,
                                type_id = :type_id
                            WHERE id = :id AND user_id = :user_id");

        return $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':description' => $description,
            ':ico' => $ico,
            ':dic' => $dic,
            ':type_id' => $type,
            ':id' => $id,
            ':user_id' => $userId
        ]);
    }

    //? --- CLIENT TYPE LOGIC ---
    /**
     * Gets all client types from database.
     *
     * @return array - array of client types
     */
    public function getAllClientTypes(): array
    {
        $stmt = $this->db->query("SELECT * FROM ". $this->table_types );
        return $stmt->fetchAll();
    }

    public function getClientTypeById(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM ". $this->table_types ." WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn();
    }
}