<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Middleware\Auth;
use Src\Models\ClientModel;

class ClientController extends OsvcController
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
        $clientTypes = $model->getAllClientTypes();

        $perPage = 15;
        $total   = $model->totalCount();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $clients = $model->getPaginated($page, $perPage);

        $this->render('Client/clients.twig', [
            'clients' => $clients,
            'clientTypes' => $clientTypes,
            'page'      => $page,
            'pages'   => $pages,
            'total'     => $total,
        ]);
    }

    /**
     * Loads data from form and tries to save to DB.
     *
     * @return void
     */
    public function addClientAction(): void
    {
        header('Content-Type: application/json');

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

        // validate (todo: move to validator?)
        if (!$name)
        {
            $response = ['status' => 'error', 'message' => 'Nebyla zadána všechna povinná pole'];
            echo json_encode($response);
            return;
        }

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
        echo json_encode($response);
    }

    /**
     * Gets client by ID
     *
     * @param int $id
     * @return array
     */
    public function getClientByIdAjaxAction(): void
    {
        header('Content-Type: application/json');
        // Gets ID from AJAX request
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID klienta'];
            echo json_encode($response);
            return;
        }

        // get client from model
        $model = new ClientModel($this->db);
        $client = $model->getClientById($id);

        // check if client exists
        if (!$client)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID klienta'];
            echo json_encode($response);
            return;
        }

        // json res
        $response = ['status' => 'success', 'client' => $client];
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

    /**
     * Loads data from form and tries to save updates params to DB.
     *
     * @return void
     */
    public function editClientAction(): void
    {
        $clientId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$clientId)
        {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Chybí ID klienta.']);
            return;
        }

        $model = new ClientModel($this->db);
        $name = $_POST['name'];
        $email = $_POST['contact_email'] ?? null;
        $phone = $_POST['contact_phone'] ?? null;
        $ico = $_POST['ico'] ?? null;
        $dic = $_POST['dic'] ?? null;
        $description = $_POST['description'] ?? null;
        $client_type =
            empty($_POST['client_type'])
                ? null
                : (int) $_POST['client_type'];

        // validate (todo: move to validator?)
        if (!$name)
        {
            $response = ['status' => 'error', 'message' => 'Nebyla zadána všechna povinná pole'];
            echo json_encode($response);
            return;
        }

        // check if client type is valid
        if ($client_type !== null)
        {
            $exists = $model->getClientTypeById($client_type);
            if (!$exists)
            {
                $response = ['status' => 'error', 'message' => 'Neplatný typ klienta.'];
            }
            else {
                $model->editClient($clientId, $name, $email, $phone, $ico, $dic, $description, $client_type);
                $response = ['status' => 'success', 'message' => 'Klient úspěšně upraven.'];
            }
        }
        else
        {
            $model->editClient($clientId, $name, $email, $phone, $ico, $dic, $description, null);
            $response = ['status' => 'success', 'message' => 'Klient úspěšně upraven.'];
        }

        // send header and json res
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}