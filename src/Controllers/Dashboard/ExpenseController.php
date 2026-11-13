<?php
declare (strict_types = 1);

namespace Src\Controllers\Dashboard;

use JetBrains\PhpStorm\NoReturn;
use Src\Controllers\ErrorController;
use Src\Models\ExpenseModel;

class ExpenseController extends OsvcController
{
    /**
     * Display the expenses page after verifying auth
     *
     * @return void
     */
    public function expensesAction(): void
    {
        $expenseModel = new ExpenseModel($this->db);

        $perPage = 15;
        $total   = $expenseModel->totalCount();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $expenses = $expenseModel->getPaginated($page, $perPage);

        $this->render('Expense/expenses.twig', [
            'expenses' => $expenses,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
        ]);
    }

    /**
     * Display a receipt file in new tab
     *
     * @return void
     */
    #[NoReturn]
    public function receiptAction(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $id = (int) ($_GET['id'] ?? 0);
        $r  = $id > 0
            ? new ExpenseModel($this->db)->findReceiptById($id, $userId)
            : null;

        if (!$r)
        {
            new ErrorController($this->db)->notFoundAction();
        }

        $this->sendReceipt($r);
    }

    /**
     * Handles creating of a new expense from submitting a form
     *
     * @return void
     */
    public function addExpenseAction(): void
    {
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

        try {
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
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log((string)$e);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se vytvořit výdaj.']);
        }
    }

    /**
     * Gets expense by ID
     *
     * @return void
     */
    public function getExpenseByIdAjaxAction(): void
    {
        header("Content-type: application/json");
        // gets id from ajax request
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID výdaje'];
            echo json_encode($response);
            return;
        }

        // get expense from model
        $model = new ExpenseModel($this->db);
        $expense = $model->getExpenseById($id);

        // check if expense exists
        if (!$expense)
        {
            $response = ['status' => 'error', 'message' => 'Nevalidní ID výdaje'];
            echo json_encode($response);
            return;
        }

        //json res
        $response = ['status'=>'success', 'expense' => $expense];
        echo json_encode($response);
    }

    /**
     * Handles editing of an existing expense from submitting a form
     *
     * @return void
     */
    public function editExpenseAction(): void
    {
        $expenseId = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (!$expenseId)
        {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Chybí ID výdaje']);
            return;
        }

        $model = new ExpenseModel($this->db);
        $name = trim($_POST['name'] ?? '');
        $total_amount = $_POST['total_amount'] ?? null;
        $expense_date = $_POST['expense_date'] ?? null;
        $note = trim($_POST['note'] ?? '');

        // validate (todo: move to validator?)
        if ($expenseId < 1 || !is_numeric($total_amount) || (float)$total_amount <= 0 || $name === '' || $expense_date === '' || empty($expense_date))
        {
            $response = ['status' => 'error', 'message' => 'Povinná pole vyplněna chybně.'];
            echo json_encode($response);
            return;
        }

        try
        {
            $ok = $model->updateExpense($expenseId, [
                'name' => $name,
                'total_amount' => $total_amount,
                'expense_date' => $expense_date,
                'note' => $note,
                'receipt' => $_FILES['file'] ?? null,
                'remove_receipt' => isset($_POST['remove_receipt']),
            ]);

            if (!$ok)
            {
                $response = ['status' => 'error', 'message' => 'Výdaj nenalezen.'];
                echo json_encode($response);
                return;
            }
            header('Content-Type: application/json');
            $response = ['status' => 'success', 'message' => 'Výdaj nalezen!'];
            echo json_encode($response);
        }
        catch (\RuntimeException $e) {
            $response = ['status' => 'error', 'message' => $e->getMessage()];
            echo json_encode($response);
        } catch (\Throwable $e) {
            $response = ['status' => 'error', 'message' => 'Nepodařilo se upravit výdaj.'];
            echo json_encode($response);
        }

    }

    /**
     * Handles an AJAX request to delete an expense
     *
     * @return void
     */
    public function deleteExpenseAction(): void
    {
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