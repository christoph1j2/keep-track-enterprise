<?php

namespace Src\Models;

use Src\Models\BaseModel;

class ClientModel extends BaseModel
{
    public function getAllClients()
    {
        $userId = $_SESSION['user_id'];
    }
}