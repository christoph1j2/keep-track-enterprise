<?php

namespace Src\Controllers;

use Src\Models\ClientModel;

class ClientController extends BaseController
{
    /**
     * Renders all the user's clients.
     *
     * @return void
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function clientsAction(): void
    {
        $model = new ClientModel($this->db);
        $clients = $model->getAllClients();
        $clientTypes = $model->getAllClientTypes();

        $this->render('Client/clients.twig', [
            'clients' => $clients,
            'clientTypes' => $clientTypes
        ]);
    }

    /**
     * Loads data from form and tries to save to DB.
     *
     * @return void
     */
    public function addClientAction(): void
    {
        // we get data from form
        $name = $_POST['name'];
        $email = $_POST['contact_email'] ?? null;
        $phone = $_POST['contact_phone'] ?? null;
        $ico  = $_POST['ico'] ?? null;
        $dic  = $_POST['dic'] ?? null;
        $description = $_POST['description'] ?? null;

        $client_type =
            empty($_POST['client_type'])
                ? null
                : (int) $_POST['client_type'];

        $model = new ClientModel($this->db);
        // check if client_type is valid
        if ($client_type !== null)
        {
            $exists = $model->getClientTypeById($client_type);
            if (!$exists)
            {
                $response = ['status' => 'error', 'message' => 'Neplatný typ klienta'];
            }
            else {
                $model->addClient($name, $email, $phone, $ico, $dic, $description, $client_type);
                $response = ['status' => 'success', 'message' => 'Klient úspěšně přidán.'];
            }
        }
        else
        {
            $model->addClient($name, $email, $phone, $ico, $dic, $description, null);
            $response = ['status' => 'success', 'message' => 'Klient úspěšně přidán.'];
        }

        // send header and json res
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /**
     * Gets client by ID
     *
     * @param int $id
     * @return array
     */
    public function getClientByIdAction(int $id): void
    {
        $model = new ClientModel($this->db);
        $client = $model->getClientById($id);
        header('Content-Type: application/json');
        if ($client)
        {
            $response = ['status' => 'success', 'client' => $client];
        }
        else
        {
            $response = ['status' => 'error', 'message' => 'Klient nenalezen.'];
        }
        echo json_encode($response);
    }

    /**
     * Handles the delete client action.
     *
     * @return void
     */
    public function deleteClientAction(): void
    {
        $clientId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$clientId)
        {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Chybí ID klienta.']);
            return;
        }

        $model = new ClientModel($this->db);
        $deleted = $model->deleteClient($clientId);

        header('Content-Type: application/json');
        if ($deleted)
            echo json_encode(['status' => 'success', 'message' => 'Klient byl úspěšně smazán.']);
        else
            echo json_encode(['status' => 'error', 'message' => 'Klienta se nepodařilo smazat.']);
    }
}