<?php
declare(strict_types=1);

namespace Src\Controllers\Dashboard;

use JetBrains\PhpStorm\NoReturn;
use PDO;
use Src\Controllers\BaseController;
use Src\Middleware\Auth;
use Src\Models\ClientModel;
use Src\Models\UserModel;

class AdminController extends BaseController
{
    /**
     * The constructor. Restricts access to admin and superadmin roles.
     *
     * @param PDO $db
     */
    public function __construct(PDO $db)
    {
        parent::__construct($db);
        Auth::check();
        $this->requireRole(['admin', 'superadmin']);
    }

    /**
     * Displays the system-wide admin overview page.
     *
     * @return void
     */
    public function overviewAction(): void
    {
        $userModel = new UserModel($this->db);
        $stats = $userModel->getAdminOverviewStats();

        $this->render('Admin/overview.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Displays paginated list of users for administration.
     *
     * @return void
     */
    public function usersAction(): void
    {
        $userModel = new UserModel($this->db);

        $perPage = 15;
        $total   = $userModel->totalUsersCount();
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);

        $users = $userModel->getPaginatedUsers($page, $perPage);
        $roles = $userModel->getAllRoles();

        $this->render('Admin/users.twig', [
            'users'        => $users,
            'roles'        => $roles,
            'currentId'    => (int) $_SESSION['user_id'],
            'isSuperAdmin' => (($_SESSION['role'] ?? '') === 'superadmin'),
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    /**
     * Blocks or unblocks a user (respecting SuperAdmin protection).
     *
     * @return void
     */
    #[NoReturn]
    public function toggleBlockAction(): void
    {
        $targetId = (int) ($_POST['id'] ?? 0);
        if ($targetId <= 0)
        {
            $this->flash('danger', 'Nevalidní ID uživatele.');
            $this->redirect('/admin/users');
        }

        $model = new UserModel($this->db);
        $target = $model->findById($targetId);

        if (!$target || !$this->canManageUser($target))
        {
            $this->flash('danger', 'Nemáte oprávnění upravovat tohoto uživatele.');
            $this->redirect('/admin/users');
        }

        $newBlocked = empty($target['is_blocked']);
        $ok = $model->setBlocked($targetId, $newBlocked);

        if ($ok)
        {
            $this->flash(
                $newBlocked ? 'warning' : 'success',
                $newBlocked
                    ? "Uživatel {$target['username']} byl zablokován."
                    : "Uživatel {$target['username']} byl odblokován."
            );
        }
        else
        {
            $this->flash('danger', 'Nepodařilo se změnit stav blokace uživatele.');
        }

        $this->redirect('/admin/users');
    }

    /**
     * Changes a user's role (only superadmin can manage or assign admin role).
     *
     * @return void
     */
    #[NoReturn]
    public function changeRoleAction(): void
    {
        $targetId = (int) ($_POST['id'] ?? 0);
        $newRole  = trim((string) ($_POST['role'] ?? ''));

        if ($targetId <= 0 || $newRole === '')
        {
            $this->flash('danger', 'Chybné parametry pro změnu role.');
            $this->redirect('/admin/users');
        }

        $model  = new UserModel($this->db);
        $target = $model->findById($targetId);
        $roleRow = $model->getRoleByName($newRole);

        if (!$target || !$roleRow || !$this->canManageUser($target, $newRole))
        {
            $this->flash('danger', 'Nemáte oprávnění nastavit tuto roli danému uživateli.');
            $this->redirect('/admin/users');
        }

        if (($target['role_name'] ?? '') === $newRole)
        {
            $this->flash('info', 'Uživatel již tuto roli má.');
            $this->redirect('/admin/users');
        }

        $ok = $model->updateUserRole($targetId, (int) $roleRow['id']);
        $this->flash(
            $ok ? 'success' : 'danger',
            $ok
                ? "Role uživatele {$target['username']} byla změněna na {$newRole}."
                : 'Nepodařilo se změnit roli uživatele.'
        );

        $this->redirect('/admin/users');
    }

    /**
     * Deletes a user (respecting SuperAdmin protection).
     *
     * @return void
     */
    #[NoReturn]
    public function deleteUserAction(): void
    {
        $targetId = (int) ($_POST['id'] ?? 0);
        if ($targetId <= 0)
        {
            $this->flash('danger', 'Nevalidní ID uživatele.');
            $this->redirect('/admin/users');
        }

        $model  = new UserModel($this->db);
        $target = $model->findById($targetId);

        if (!$target || !$this->canManageUser($target))
        {
            $this->flash('danger', 'Nemáte oprávnění smazat tohoto uživatele.');
            $this->redirect('/admin/users');
        }

        $ok = $model->deleteUser($targetId);
        $this->flash(
            $ok ? 'success' : 'danger',
            $ok
                ? "Uživatel {$target['username']} byl odstraněn."
                : 'Nepodařilo se odstranit uživatele.'
        );

        $this->redirect('/admin/users');
    }

    /**
     * SuperAdmin protection check:
     * - Nobody can modify/delete themselves or any superadmin.
     * - Regular admin can only manage osvc/ucetni and cannot assign admin/superadmin.
     * - Superadmin can manage osvc/ucetni/admin and assign osvc/ucetni/admin.
     *
     * @param array $targetUser
     * @param string|null $newRole
     * @return bool
     */
    private function canManageUser(array $targetUser, ?string $newRole = null): bool
    {
        $actorId    = (int) $_SESSION['user_id'];
        $actorRole  = $_SESSION['role'] ?? '';
        $targetRole = $targetUser['role_name'] ?? '';

        if ((int) $targetUser['id'] === $actorId)
            return false;

        if ($targetRole === 'superadmin')
            return false;

        if ($newRole === 'superadmin')
            return false;

        if ($actorRole !== 'superadmin')
        {
            if ($targetRole === 'admin')
                return false;

            if ($newRole !== null && !in_array($newRole, ['osvc', 'ucetni'], true))
                return false;
        }
        elseif ($newRole !== null && !in_array($newRole, ['osvc', 'ucetni', 'admin'], true))
        {
            return false;
        }

        return true;
    }

    // ####### Client Types CRUD ########

    /**
     * Displays client types codebook management page.
     *
     * @return void
     */
    public function clientTypesAction(): void
    {
        $clientModel = new ClientModel($this->db);
        $clientTypes = $clientModel->getAllClientTypesWithUsage();

        $this->render('Admin/clientTypes.twig', [
            'clientTypes' => $clientTypes,
        ]);
    }

    /**
     * AJAX: Returns a single client type by ID.
     *
     * @return void
     */
    public function getClientTypeAjaxAction(): void
    {
        header('Content-Type: application/json');

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id)
        {
            echo json_encode(['status' => 'error', 'message' => 'Nevalidní ID typu klienta.']);
            return;
        }

        $model = new ClientModel($this->db);
        $clientType = $model->findClientTypeById($id);

        if (!$clientType)
        {
            echo json_encode(['status' => 'error', 'message' => 'Typ klienta nenalezen.']);
            return;
        }

        echo json_encode(['status' => 'success', 'clientType' => $clientType]);
    }

    /**
     * AJAX: Creates a new client type.
     *
     * @return void
     */
    public function addClientTypeAction(): void
    {
        header('Content-Type: application/json');

        $name = trim($_POST['name'] ?? '');
        if ($name === '')
        {
            echo json_encode(['status' => 'error', 'message' => 'Název typu klienta je povinný.']);
            return;
        }

        $model = new ClientModel($this->db);
        if ($model->clientTypeNameExists($name))
        {
            echo json_encode(['status' => 'error', 'message' => 'Typ klienta s tímto názvem již existuje.']);
            return;
        }

        $ok = $model->addClientType($name);
        if ($ok)
        {
            $msg = 'Typ klienta úspěšně přidán.';
            $this->flash('success', $msg);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        }
        else
        {
            echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se přidat typ klienta.']);
        }
    }

    /**
     * AJAX: Updates an existing client type.
     *
     * @return void
     */
    public function editClientTypeAction(): void
    {
        header('Content-Type: application/json');

        $id   = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');

        if (!$id || $name === '')
        {
            echo json_encode(['status' => 'error', 'message' => 'Vyplňte platné ID a název typu klienta.']);
            return;
        }

        $model = new ClientModel($this->db);
        if (!$model->findClientTypeById($id))
        {
            echo json_encode(['status' => 'error', 'message' => 'Typ klienta nenalezen.']);
            return;
        }

        if ($model->clientTypeNameExists($name, $id))
        {
            echo json_encode(['status' => 'error', 'message' => 'Typ klienta s tímto názvem již existuje.']);
            return;
        }

        $ok = $model->editClientType($id, $name);
        echo json_encode(
            $ok
                ? ['status' => 'success', 'message' => 'Typ klienta úspěšně upraven.']
                : ['status' => 'error', 'message' => 'Nepodařilo se upravit typ klienta.']
        );
    }

    /**
     * AJAX: Deletes a client type.
     *
     * @return void
     */
    public function deleteClientTypeAction(): void
    {
        header('Content-Type: application/json');

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id)
        {
            echo json_encode(['status' => 'error', 'message' => 'Chybí ID typu klienta.']);
            return;
        }

        $model = new ClientModel($this->db);
        $deleted = $model->deleteClientType($id);

        echo json_encode(
            $deleted
                ? ['status' => 'success', 'message' => 'Typ klienta byl úspěšně smazán.']
                : ['status' => 'error', 'message' => 'Typ klienta se nepodařilo smazat.']
        );
    }
}
