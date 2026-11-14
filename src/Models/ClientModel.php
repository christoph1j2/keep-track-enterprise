<?php
declare(strict_types=1);

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
     * Returns client with specific ID belonging to current user
     *
     * @param int $id
     * @return array|false
     */
    public function getClientById(int $id): array|false
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$userId) return false;

        $sql = 'SELECT c.*, ct.name AS type_name
                FROM '. $this->table .' c
                LEFT JOIN '. $this->table_types .' ct ON ct.id = c.type_id
                WHERE c.id = :id AND c.user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: false;
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

    /**
     * Returns paginated clients
     *
     * @param int $page
     * @param int $perPage
     * @param int|null $userId
     * @return array
     */
    public function getPaginated(int $page, int $perPage, ?int $userId = null): array
    {
        if ($userId === null)
            $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$userId) return [];

        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT c.*, ct.name AS type_name
                FROM '. $this->table .' c
                LEFT JOIN '. $this->table_types .' ct ON ct.id = c.type_id
                WHERE c.user_id = :user_id
                ORDER BY c.name
                LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindParam(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Gets a total count of all rows in a db table. (KPI purposes)
     *
     * @return int
     */
    public function totalCount(?int $userId = null): int
    {
        if ($userId === null)
            $userId = $_SESSION['user_id'];
        if (!$userId) return -1;

        $sql = "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchColumn();
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
        return (bool) $stmt->fetchColumn();
    }
}