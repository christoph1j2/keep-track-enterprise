<?php

namespace Src\Controllers;

use Src\Models\ClientModel;

class ClientController extends BaseController
{
    /**
     * Renders all of the user's clients.
     *
     * @return void
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function indexAction(): void
    {
        $model = new ClientModel($this->db);
        $clients = $model->getAllClients();

        $this->render('Client/clients.twig', ['clients' => $clients]);
    }
}