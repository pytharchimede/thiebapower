<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comptes et permissions · Thiebapower</title>
<link rel="stylesheet" href="/style.css">
</head>
<body class="admin-body">
<?php $adminPageTitle='Comptes et droits'; $adminPageOverline='SÉCURITÉ'; $adminPageSubtitle='Utilisateurs et permissions'; require __DIR__.'/partials/admin_shell_start.php'; ?>
<main class="management-main">
<p class="admin-overline">ADMINISTRATION</p>
<h1>Comptes et permissions</h1>
<p>Chaque membre possède son propre compte. Le propriétaire garde le contrôle des accès et des rôles.</p>
<section class="management-card">
<h2>Créer un compte</h2>
<form action="/admin/users" method="post" class="management-grid">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<label>Identifiant<input name="username" required maxlength="80" autocomplete="username">
<small>1 à 80 caractères. Nom, adresse e-mail, espaces et accents acceptés.</small>
</label>
<label>Nom affiché<input name="display_name" required maxlength="160">
</label>
<label>Rôle<select name="role" required>
<?php foreach (App\Services\Auth::ROLES as $key => $label): ?>
<option value="<?= $key ?>">
<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Mot de passe provisoire<input name="password" type="password" autocomplete="new-password" required>
</label>
<button class="admin-button">Créer le compte</button>
</form>
</section>
<section class="management-card">
<h2>Utilisateurs</h2>
<div class="management-table-wrap">
<table>
<thead>
<tr>
<th>Compte</th>
<th>Rôle</th>
<th>État</th>
<th>Dernière connexion</th>
<th>Modification</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
<td>
<strong>
<?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?>
</strong>
<br>
<small>
<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
</small>
</td>
<td>
<?= htmlspecialchars(App\Services\Auth::ROLES[$user['role']], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= $user['is_active'] ? 'Actif' : 'Suspendu' ?>
</td>
<td>
<?= htmlspecialchars((string) ($user['last_login_at'] ?? 'Jamais'), ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<details>
<summary>Modifier</summary>
<form method="post" action="/admin/users/update" class="management-stack">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
<label>Rôle<select name="role">
<?php foreach (App\Services\Auth::ROLES as $key => $label): ?>
<option value="<?= $key ?>" <?= $key === $user['role'] ? 'selected' : '' ?>>
<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>État<select name="is_active">
<option value="1" <?= $user['is_active'] ? 'selected' : '' ?>>Actif</option>
<option value="0" <?= !$user['is_active'] ? 'selected' : '' ?>>Suspendu</option>
</select>
</label>
<label>Nouveau mot de passe (laisser vide pour conserver)<input type="password" name="password" autocomplete="new-password">
</label>
<button class="admin-button">Enregistrer</button>
</form>
</details>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<section class="management-card">
<h2>Permissions par rôle</h2>
<p>Le propriétaire possède toutes les permissions. La gestion des comptes lui est réservée.</p>
<div class="management-grid">
<?php foreach (['manager' => 'Gestionnaire', 'operator' => 'Opérateur', 'auditor' => 'Auditeur'] as $role => $title): ?>
<form method="post" action="/admin/roles/permissions" class="permission-card">
<h3>
<?= $title ?>
</h3>
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="role" value="<?= $role ?>">
<?php foreach (App\Services\Auth::PERMISSIONS as $permission => $label): ?>
<?php if ($permission === 'users.manage') continue; ?>
<label class="permission-option">
<input type="checkbox" name="permissions[]" value="<?= $permission ?>" <?= isset($grants[$role][$permission]) ? 'checked' : '' ?>>
<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</label>
<?php endforeach; ?>
<button class="admin-button">Enregistrer les droits</button>
</form>
<?php endforeach; ?>
</div>
</section>
</main>
<?php require __DIR__.'/partials/admin_shell_end.php'; ?>
</body>
</html>
