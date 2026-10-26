<?php

namespace Src\Models;

use Src\Models\BaseModel;

class ClientModel extends BaseModel
{
    protected string $table = 'clients';

    /**
     * Gets client array from database for current user
     *
     * @return array|array|\mixed[][]|\stdClass[]
     */
    public function getAllClients(): array
    {
        $userId = $_SESSION['user_id'];
        if (!$userId) return [];

        $stmt = $this->db->prepare("SELECT * FROM ". $this->table ." WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll();
    }
}