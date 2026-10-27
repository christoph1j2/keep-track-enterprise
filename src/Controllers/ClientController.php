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

        $this->render('Client/clients.twig', ['clients' => $clients]);
    }

    public function addClientAction(): void
    {
        // we get data from form (demonstration purposes only)
        $email = $_POST['contact_email'] ?? '';

        // TODO: model call, save client to db - FINISH THE AJAX BS
//        $model = new ClientModel($this->db);
//        $model->addClient($email);

        // prep res
        $response = ['status' => 'success', 'message' => 'Klient úspěšně přidán.'];

        // send header and json res
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}