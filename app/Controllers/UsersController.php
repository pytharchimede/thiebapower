<?php
namespace App\Controllers;

use App\Core\App;
use App\Services\Audit;
use App\Services\Auth;

final class UsersController
{
    public function index(): void
    {
        Auth::requirePermission('users.manage');
        $users = App::db()->query('SELECT id,username,display_name,role,is_active,last_login_at,created_at FROM users ORDER BY id')->fetchAll();
        $rows = App::db()->query('SELECT role,permission FROM role_permissions')->fetchAll();
        $grants = [];
        foreach ($rows as $row) {
            $grants[$row['role']][$row['permission']] = true;
        }
        App::view('users', ['users' => $users, 'grants' => $grants, 'csrf' => $_SESSION['csrf'], 'current' => Auth::user()]);
    }

    public function create(): void
    {
        Auth::requirePermission('users.manage', true);
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $name = trim((string) ($_POST['display_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,80}$/D', $username) || $name === '' || strlen($name) > 160 ||
            $password === '' || strlen($password) > 256 || !isset(Auth::ROLES[$role])) {
            http_response_code(422);
            exit('Compte invalide : vérifiez les champs et renseignez un mot de passe');
        }
        try {
            App::db()->prepare('INSERT INTO users(username,display_name,password_hash,role) VALUES(?,?,?,?)')
                ->execute([$username, $name, password_hash($password, PASSWORD_DEFAULT), $role]);
            Audit::event('user.created', 'user', (string) App::db()->lastInsertId(), ['username' => $username, 'role' => $role]);
        } catch (\PDOException $e) {
            http_response_code(409);
            exit('Identifiant déjà utilisé');
        }
        App::redirect('/admin/users');
    }

    public function update(): void
    {
        $current = Auth::requirePermission('users.manage', true);
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $role = (string) ($_POST['role'] ?? '');
        $active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');
        if (!$id || !isset(Auth::ROLES[$role]) || strlen($password) > 256) {
            http_response_code(422);
            exit('Modification invalide');
        }
        $db = App::db();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
            $query->execute([$id]);
            $target = $query->fetch();
            if (!$target) {
                throw new \LogicException('Compte introuvable');
            }
            if ((int) $id === (int) $current['id'] && ($active === 0 || $role !== 'owner')) {
                throw new \LogicException('Impossible de retirer votre propre accès propriétaire');
            }
            if ($target['role'] === 'owner' && ($role !== 'owner' || $active === 0)) {
                $owners = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='owner' AND is_active=1")->fetchColumn();
                if ($owners <= 1) {
                    throw new \LogicException('Dernier propriétaire actif');
                }
            }
            $db->prepare('UPDATE users SET role=?,is_active=? WHERE id=?')->execute([$role, $active, $id]);
            if ($password !== '') {
                $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            $db->commit();
            Audit::event('user.updated', 'user', (string) $id, ['role' => $role, 'active' => $active, 'password_changed' => $password !== '']);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(409);
            exit('Modification refusée : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        App::redirect('/admin/users');
    }

    public function permissions(): void
    {
        Auth::requirePermission('users.manage', true);
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, ['manager', 'operator', 'auditor'], true)) {
            http_response_code(422);
            exit('Rôle invalide');
        }
        $requested = $_POST['permissions'] ?? [];
        if (!is_array($requested)) {
            http_response_code(422);
            exit('Permissions invalides');
        }
        $grants = array_values(array_diff(array_intersect(array_keys(Auth::PERMISSIONS), array_map('strval', $requested)), ['users.manage']));
        $db = App::db();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM role_permissions WHERE role=?')->execute([$role]);
            $insert = $db->prepare('INSERT INTO role_permissions(role,permission) VALUES(?,?)');
            foreach ($grants as $permission) {
                $insert->execute([$role, $permission]);
            }
            $db->commit();
            Audit::event('role.permissions_updated', 'role', $role, ['permissions' => implode(',', $grants)]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        App::redirect('/admin/users');
    }
}
