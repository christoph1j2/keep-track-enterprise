<?php
declare(strict_types=1);

namespace Src\Controllers;

use Src\Middleware\Auth;
use Src\Models\PaymentModel;

class PaymentController extends BaseController
{
    /**
     * Displays the payments page after verifying auth
     *
     * @return void
     */
    public function paymentsAction(): void
    {
        // sanity check
        Auth::check();

        $paymentModel = new PaymentModel($this->db);

        $perPage = 15;
        $total   = $paymentModel->totalCount();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $payments = $paymentModel->getPaginated($page, $perPage);

        $unpaidInvoices = $paymentModel->getUnpaidInvoices();

        $this->render('Payment/payments.twig', [
            'payments'       => $payments,
            'unpaidInvoices' => $unpaidInvoices,
            'page'           => $page,
            'pages'          => $pages,
            'total'          => $total,
        ]);
    }

    /**
     * Handles creating of a new payment from submitting a form
     *
     * @return void
     */
    public function addPaymentAction(): void
    {
        // sanity check
        Auth::check();
        header('Content-Type: application/json');

        // we get data from form
        $specific_symbol = trim($_POST['specific_symbol'] ?? '');
        $amount = $_POST['amount'] ?? null;
        $date_paid = $_POST['date_paid'] ?? '';
        $note = $_POST['note'] ?? '';

        $pairings = $_POST['pairings'] ?? [];

        // Validate (todo: mvoe to validator?)
        // required fields
        if (!is_numeric($amount) || (float)$amount <= 0 || empty($specific_symbol) || empty($date_paid) || empty($pairings))
        {
            $response = ['status' => 'error', 'message' => 'Chybné vyplnění povinných polí.'];
            echo json_encode($response);
            return;
        }

        // check sum of amount_assigned doesnt exceed total payment amount
        $sum = 0.0;
        foreach ($pairings as $pairing)
        {
            $sum += $pairing['amount_assigned'];
            if ($sum > $amount)
            {
                $response = ['status' => 'error', 'message' => 'Překročení částky!'];
                echo json_encode($response);
                return;
            }
        }

        // prep payment data
        $paymentData = [
            ':specific_symbol' => $specific_symbol,
            ':amount' => $amount,
            ':date_paid' => $date_paid,
            ':note' => $note,
        ];

        // save
        $model = new PaymentModel($this->db);
        $paymentId = $model->createPaymentWithPairing($paymentData, $pairings);

        // json res
        if ($paymentId)
            $response = ['status' => 'success', 'message' => 'Platba úspěšně vytvořena.'];
        else
            $response = ['status' => 'error', 'message' => 'Nepodařilo se vytvořit platbu.'];

        echo json_encode($response);
    }

    /**
     * Handles an AJAX request for payment detail data.
     *
     * @return void
     */
    public function getPaymentDetailAjaxAction(): void
    {
        // sanity check
        Auth::check();
        header('Content-Type: application/json');

        // Get ID from AJAX rq
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID platby.'];
            echo json_encode($response);
            return;
        }

        // get payment and pairing from model
        $model = new PaymentModel($this->db);
        $payment = $model->getPaymentById((int)$id);
        $pairing = $model->getPaymentPairings((int)$id);

        // check fi payment exists
        if (!$payment)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID platby'];
            echo json_encode($response);
            return;
        }

        // json res
        $response = [
            'status' => 'success',
            'payment' => $payment,
            'pairings' => $pairing ?: []
        ];
        echo json_encode($response);
    }

    /**
     * Handles an AJAX request to delete a payment
     *
     * @return void
     */
    public function deletePaymentAction(): void
    {
        // sanity check
        Auth::check();

        $paymentId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$paymentId)
        {
            header('Content-Type: application/json');
            $response = ['status' => 'error', 'message' => 'Nevalidní ID platby'];
            echo json_encode($response);
            return;
        }

        $model = new PaymentModel($this->db);
        $deleted = $model->deletePayment($paymentId);

        header('Content-Type: application/json');
        if ($deleted)
            $response = ['status' => 'success', 'message' => 'Platba úspěšně smazána'];
        else
            $response = ['status' => 'error', 'message' => 'Nepodařilo se smazat platbu'];
        echo json_encode($response);
    }
}