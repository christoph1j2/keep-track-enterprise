<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Middleware\Auth;
use Src\Models\ClientModel;
use Src\Models\InvoiceModel;

class InvoiceController extends BaseController
{
    /**
     * Displays the invoices page after verifying authentication.
     *
     * @return void
     */
    public function invoicesAction(): void
    {
        // sanity check
        Auth::check();

        $invoiceModel = new InvoiceModel($this->db);
        $invoices = $invoiceModel->getAllInvoices();

        $clientModel = new ClientModel($this->db);
        $clients = $clientModel->getAllClients();

        $this->render('Invoice/invoices.twig', [
            'invoices' => $invoices,
            'clients' => $clients,
        ]);
    }

    /**
     * Handles creation of a new invoice from submitted POST data.
     *
     * @return void
     */
    public function addInvoiceAction(): void
    {
        // sanity check
        Auth::check();
        header('Content-Type: application/json');

        // we get data from form
        $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $date_issued = $_POST['date_issued'] ?? '';
        $date_due = $_POST['date_due'] ?? '';
        $items = $_POST['items'] ?? [];

        // validate (todo: move to validator?)
        if (!$clientId || empty($invoice_number) || empty($date_issued) || empty($items))
        {
            $response = ['status' => 'error', 'message' => 'Nebyla zadána všechna povinná pole'];
            echo json_encode($response);
            return;
        }

        // calculate total amount
        $total_amount = 0.0;
        foreach ($items as $item)
        {
            $quantity = (int)($item['quantity'] ?? 0);
            $price = (float)($item['unit_price'] ?? 0);
            if ($quantity <= 0 || $price < 0) {
                echo json_encode(['status' => 'error', 'message' => 'Neplatné množství nebo cena položky.']);
                return;
            }
            $total_amount += ($quantity * $price);
        }

        // prepare invoice data
        $invoiceData = [
            ':client_id' => (int)$clientId,
            ':invoice_number' => $invoice_number,
            ':name' => $name,
            ':total_amount' => $total_amount,
            ':paid' => 0, // nova faktura
            ':date_issued' => $date_issued,
            ':date_due' => $date_due ?: null,
        ];

        // save
        $model = new InvoiceModel($this->db);
        $invoiceId = $model->createInvoiceWithItems($invoiceData, $items);

        // json res
        if ($invoiceId)
        {
            $response = ['status' => 'success', 'message' => 'Faktura úspěšně vytvořena'];
        }
        else
        {
            $response = ['status' => 'error', 'message' => 'Nepodařilo se vytvořit fakturu'];
        }
        echo json_encode($response);
    }

    /**
     * Handles an AJAX request for invoice detail data.
     *
     * @return void
     */
    public function getInvoiceDetailAjaxAction(): void
    {
        // sanity check
        Auth::check();

        header('Content-Type: application/json');
        // Gets ID from AJAX request
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID faktury'];
            echo json_encode($response);
            return;
        }

        // get invoice from model
        $model = new InvoiceModel($this->db);
        $invoice = $model->getInvoiceById($id);

        // check if invoice exists
        if (!$invoice)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID faktury'];
            echo json_encode($response);
            return;
        }

        // load items
        $items = $model->getInvoiceItems($invoice['id']);

        // json res
        $response = ['status' => 'success', 'invoice' => $invoice, 'items' => $items];
        echo json_encode($response);
    }

    /**
     * Handles an AJAX request to delete an invoice.
     *
     * @return void
     */
    public function deleteInvoiceAction(): void
    {
        // sanity check
        Auth::check();

        $invoiceId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$invoiceId)
        {
            header('Content-Type: application/json');
            $response = ['status' => 'error', 'message' => 'Nevalidní ID faktury'];
            echo json_encode($response);
            return;
        }

        $model = new InvoiceModel($this->db);
        $deleted = $model->deleteInvoice($invoiceId);

        header('Content-Type: application/json');
        if ($deleted)
        {
            $response = ['status' => 'success', 'message' => 'Faktura úspěšně smazána'];
        }
        else
        {
            $response = ['status' => 'error', 'message' => 'Nepodařilo se smazat fakturu'];
        }
        echo json_encode($response);
    }
}