<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Traçabilité · Thiebapower</title>
<link rel="stylesheet" href="/style.css">
</head>
<body class="management-page">
<header class="management-header">
<a class="kiosk-logo" href="/admin">THIEBA<span>POWER</span>
</a>
<nav>
<a href="/admin">Tableau de bord</a>
<?php if (App\Services\Auth::can('users.manage')): ?>
<a href="/admin/users">Comptes</a>
<?php endif; ?>
</nav>
</header>
<main class="management-main">
<p class="admin-overline">CONTRÔLE</p>
<h1>Journal et visites</h1>
<p>Actions, connexions et requêtes de l’application. Les mots de passe, clés et tokens ne sont pas journalisés.</p>
<section class="management-card">
<h2>Actions récentes</h2>
<div class="management-table-wrap">
<table>
<thead>
<tr>
<th>Date</th>
<th>Compte</th>
<th>Action</th>
<th>Objet</th>
<th>Détails</th>
<th>IP</th>
</tr>
</thead>
<tbody>
<?php foreach ($actions as $row): ?>
<tr>
<td>
<?= htmlspecialchars($row['occurred_at'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars((string) ($row['username'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars((string) ($row['subject_type'] ?? '') . ' ' . (string) ($row['subject_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<code>
<?= htmlspecialchars((string) ($row['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</code>
</td>
<td>
<?= htmlspecialchars((string) ($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<section class="management-card">
<h2>Connexions</h2>
<div class="management-table-wrap">
<table>
<thead>
<tr>
<th>Date</th>
<th>Identifiant tenté</th>
<th>IP</th>
<th>Résultat</th>
</tr>
</thead>
<tbody>
<?php foreach ($attempts as $row): ?>
<tr>
<td>
<?= htmlspecialchars($row['occurred_at'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= $row['successful'] ? 'Acceptée' : 'Refusée' ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<section class="management-card">
<h2>Visites et réponses HTTP</h2>
<div class="management-table-wrap">
<table>
<thead>
<tr>
<th>Date</th>
<th>Utilisateur</th>
<th>Méthode</th>
<th>Page</th>
<th>HTTP</th>
<th>IP</th>
<th>Durée</th>
</tr>
</thead>
<tbody>
<?php foreach ($visits as $row): ?>
<tr>
<td>
<?= htmlspecialchars($row['occurred_at'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars((string) ($row['username'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= htmlspecialchars($row['method'], ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<code>
<?= htmlspecialchars($row['path'], ENT_QUOTES, 'UTF-8') ?>
</code>
</td>
<td>
<?= (int) $row['status_code'] ?>
</td>
<td>
<?= htmlspecialchars((string) ($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
</td>
<td>
<?= (int) $row['duration_ms'] ?> ms</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
