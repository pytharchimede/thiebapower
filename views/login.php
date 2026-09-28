<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · Thiebapower</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body class="login-page">
<main class="login-card">
    <a class="kiosk-logo" href="/">THIEBA<span>POWER</span></a>
    <p class="admin-overline">ESPACE DE GESTION</p>
    <h1>Bienvenue</h1>
    <p>Connectez-vous pour piloter vos locations et consulter les opérations.</p>
    <?php if ($error): ?>
        <div class="login-error" role="alert">Identifiants invalides ou trop de tentatives. Réessayez plus tard.</div>
    <?php endif; ?>
    <form method="post" action="/admin/login">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Identifiant<input name="username" autocomplete="username" required autofocus></label>
        <label>Mot de passe<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="admin-button" type="submit">Se connecter</button>
    </form>
</main>
</body>
</html>
