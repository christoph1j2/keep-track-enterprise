<?php
declare (strict_types = 1);

namespace Src\Controllers;

use Src\Middleware\Auth;
use Src\Models\ExpenseModel;

class ExpenseController extends BaseController
{
    /**
     * Display the expenses page after verifying auth
     *
     * @return void
     */
    public function expensesAction(): void
    {
        // sanity check
        Auth::check();

        $expenseModel = new ExpenseModel($this->db);
        $expenses = $expenseModel->getAllExpenses();

        $this->render('Expense/expenses.twig', [
            'expenses' => $expenses,
        ]);
    }

    /**
     * Display a receipt file in new tab
     *
     * @return void
     */
    public function receiptAction(): void
    {
        Auth::check();
        //TODO: when you go to .../receipt?id=X where X is a non-existent id, then many PHP errors popup, which is BAD!!! FIX!!!
        $id = (int)($_GET['id'] ?? 0);
        $model = new ExpenseModel($this->db);
        $r = $model->findReceiptById($id);

        $path = __DIR__ . '/../../storage/receipts/' . $r['receipt_filename'];
        if (!is_file($path)) {
            $err = new ErrorController($this->db);
            $err->notFoundAction();
        }

        header('Content-Type: ' . $r['receipt_mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($r['receipt_original_name']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    /**
     * Handles an AJAX request for expense detail data
     *
     * @return void
     */
    public function getExpenseDatailAjaxAction(): void
    {
        // sanity check
        Auth::check();

        // TODO: Implement
    }

    /**
     * Handles creating of a new expense from submitting a form
     *
     * @return void
     */
    public function addExpenseAction(): void
    {
        // sanity check
        Auth::check();

        // we get data from form
        $name = trim($_POST['name'] ?? '');
        $total_amount = $_POST['total_amount'] ?? null;
        $expense_date = $_POST['expense_date'] ?? null;
        $note = trim($_POST['note'] ?? '');
        $receipt = $_FILES['receipt'] ?? null;

        // Validate (todo: move to validator?)
        // requried fields
        if (!is_numeric($total_amount) || (float)$total_amount <= 0 || empty($name) || empty($expense_date))
        {
            $response = ['status' => 'error', 'message' => 'Chybné vyplnění povinných polí.'];
            echo json_encode($response);
            return;
        }

        // prep expense date
        $expenseData = [
            'name' => $name,
            'total_amount' => $total_amount,
            'expense_date' => $expense_date,
            'note' => $note,
            'receipt' => $receipt,
        ];

        // save
        $model = new ExpenseModel($this->db);
        $expenseId = $model->createExpense($expenseData);

        // json res
        if ($expenseId)
            $response = ['status' => 'success', 'message' => 'Výdaj úspěšně vytvořen!'];
        else
            $response = ['status' => 'error', 'message' => 'Nepodařilo se vytvořit výdaj'];

        echo json_encode($response);
    }

    /**
     * Handles editing of an existing expense from submitting a form
     *
     * @return void
     */
    public function editExpenseAction(): void
    {
        // sanity check
        Auth::check();

        // TODO: Implement
    }

    /**
     * Handles an AJAX request to delete an expense
     *
     * @return void
     */
    public function deleteExpenseAction(): void
    {
        // sanity check
        Auth::check();

        $expenseId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$expenseId)
        {
            header('Content-Type: application/json');
            $response = ['status' => 'error', 'message' => 'Nevalidní ID výdaje!'];
            echo json_encode($response);
            return;
        }

        $model = new ExpenseModel($this->db);
        $deleted = $model->deleteExpense($expenseId);

        header('Content-Type: application/json');
        if ($deleted)
            $response = ['status' => 'success', 'message' => 'Výdaj úspěšně smazán'];
        else
            $response = ['status' => 'error', 'message' => 'Nepodařilo se smazat výdaj'];

        echo json_encode($response);
    }
}