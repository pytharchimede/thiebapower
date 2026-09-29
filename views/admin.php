<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau de bord · Thiebapower</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body class="admin-body">
<div class="admin-layout">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="/admin">THIEBA<span>POWER</span></a>
        <div class="admin-caption">ESPACE DE GESTION</div>
        <nav aria-label="Navigation principale">
            <a href="#overview">Vue d’ensemble</a>
            <?php if (App\Services\Auth::can('payout.view')): ?><a href="/admin/payout">Reversements API</a><?php endif; ?>
            <?php if (App\Services\Auth::can('pricing.manage')): ?><a href="#pricing">Tarification</a><?php endif; ?>
            <?php if (App\Services\Auth::can('fleet.manage')): ?><a href="#fleet">Batteries</a><a href="/admin/stations">Terminaux</a><?php endif; ?>
            <a href="/admin/rentals">Locations</a>
            <?php if (App\Services\Auth::can('audit.view')): ?><a href="/admin/audit">Journal et visites</a><?php endif; ?>
            <?php if (App\Services\Auth::can('users.manage')): ?><a href="/admin/users">Comptes et droits</a><?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <span class="sidebar-indicator"></span> HeyCharge <?= htmlspecialchars($modes['heycharge'],ENT_QUOTES,'UTF-8') ?> · Paiement Pro <?= htmlspecialchars($modes['paiementpro'],ENT_QUOTES,'UTF-8') ?>
        </div>
    </aside>
    <div class="admin-content">
        <header class="admin-top">
            <div>
                <span class="admin-overline">TABLEAU DE BORD</span>
                <h1>Bonjour, <?= htmlspecialchars($currentUser['display_name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Suivez votre parc, les locations et les opérations financières.</p>
            </div>
            <div class="admin-top-actions">
                <a class="admin-site-link" href="/">Ouvrir le kiosque</a>
                <form method="post" action="/admin/logout">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="admin-ghost">Déconnexion</button>
                </form>
            </div>
        </header>
        <main class="admin-main">
            <section id="overview" class="admin-overview">
                <div class="admin-section-heading"><div><span class="admin-overline">PILOTAGE</span><h2>Vue d’ensemble</h2></div><span class="admin-date"><?= date('d/m/Y') ?></span></div>
                <div class="admin-stats">
                    <article><span>Batteries</span><strong><?= count($batteries) ?></strong><small>Enregistrées</small></article>
                    <article><span>Disponibles</span><strong><?= count(array_filter($batteries, fn ($b) => $b['status'] === 'available')) ?></strong><small>Prêtes à louer</small></article>
                    <article><span>Locations</span><strong><?= array_sum(array_column($stats, 'quantity')) ?></strong><small>Tous états</small></article>
                    <article><span>Location</span><strong><?= number_format((int) $prices['rental_fee'], 0, ',', ' ') ?> F</strong><small>Pour <?= (int) $prices['duration_minutes'] ?> minutes</small></article>
                </div>
                <div class="admin-banner"><div><strong>Exploitation du parc</strong><p>HeyCharge : <?= htmlspecialchars($modes['heycharge'],ENT_QUOTES,'UTF-8') ?> · Paiement Pro : <?= htmlspecialchars($modes['paiementpro'],ENT_QUOTES,'UTF-8') ?> · Worker : <?= $workerRecent?'actif':'à vérifier' ?>.</p></div></div>
            </section>

            <?php if (App\Services\Auth::can('pricing.manage')): ?>
            <section id="pricing" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">PARAMÈTRES</span><h2>Tarification</h2><p>Le tarif est figé à la création de chaque location.</p></div></div>
                <form action="/admin/prices" method="post" class="admin-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label>Location (FCFA)<input id="rental-fee" type="number" min="1" name="rental_fee" value="<?= (int) $prices['rental_fee'] ?>" required></label>
                    <label class="lab-confirm"><input type="checkbox" name="deposit_enabled" value="1" <?= (int)$prices['deposit_enabled']===1?'checked':'' ?>> Activer la caution sur les nouvelles locations</label>
                    <label>Caution par défaut (FCFA)<input id="default-deposit" type="number" min="0" name="default_deposit" value="<?= (int) $prices['default_deposit'] ?>" required></label>
                    <label>Durée incluse (minutes)<input type="number" min="1" name="duration_minutes" value="<?= (int) $prices['duration_minutes'] ?>" required></label>
                    <label>Retenue par heure entamée (%)<input type="number" min="0" max="100" name="late_percent" value="<?= (int) $prices['late_percent'] ?>" required></label>
                    <div class="form-action"><span>Le changement ne modifie pas les locations déjà créées.</span><button class="admin-button">Enregistrer les tarifs</button></div>
                </form>
            </section>
            <?php endif; ?>

            <?php if (App\Services\Auth::can('fleet.manage')): ?>
            <section id="fleet" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">MATÉRIEL</span><h2>Parc batteries</h2><p>Une caution propre à chaque batterie peut remplacer le montant par défaut.</p></div></div>
                <form action="/admin/batteries" method="post" class="admin-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label>Numéro de série<input name="serial" maxlength="100" required></label><label>Caution spécifique (FCFA)<input name="deposit_override" type="number" min="0" placeholder="Vide = caution par défaut"></label><div class="form-action"><span>Le numéro doit correspondre au matériel.</span><button class="admin-button">Enregistrer la batterie</button></div></form>
                <div class="admin-table-wrap"><table><thead><tr><th>Batterie</th><th>Station</th><th>Emplacement</th><th>Charge</th><th>État</th><th>Caution</th></tr></thead><tbody><?php foreach ($batteries as $battery): ?><tr><td><?= htmlspecialchars($battery['serial'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($battery['station_imei'] ?? '—'),ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)($battery['slot_id'] ?? '—'),ENT_QUOTES,'UTF-8') ?></td><td><?= $battery['battery_capacity'] === null ? '—' : (int)$battery['battery_capacity'].' %' ?></td><td><span class="status-pill"><?= htmlspecialchars($battery['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= $battery['deposit_override'] === null ? 'Par défaut' : number_format((int) $battery['deposit_override'], 0, ',', ' ') . ' FCFA' ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <?php endif; ?>

            <?php if (App\Services\Auth::can('fleet.manage')): ?>
            <section id="stations" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">HEYCHARGE</span><h2>Terminaux</h2><p>Les terminaux associés au compte sont importés automatiquement. Vérifiez leur inventaire, puis activez-les.</p></div><a class="admin-site-link" href="/admin/stations">Voir tout le parc et les étiquettes QR</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>IMEI</th><th>Nom</th><th>État</th><th>Vu le</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($stations as $station): ?><tr><td><a href="/admin/stations/detail?imei=<?= rawurlencode($station['imei']) ?>"><?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></a></td><td><?= htmlspecialchars((string)$station['label'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($station['status'],ENT_QUOTES,'UTF-8') ?> · <?= $station['enabled'] ? 'activé' : 'désactivé' ?></td><td><?= htmlspecialchars((string)$station['last_seen_at'],ENT_QUOTES,'UTF-8') ?></td><td>
                <form method="post" action="/admin/stations/sync"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="imei" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Synchroniser</button></form>
                <form method="post" action="/admin/stations/toggle"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="imei" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="enabled" value="<?= $station['enabled'] ? 0 : 1 ?>"><button class="admin-ghost"><?= $station['enabled'] ? 'Désactiver' : 'Activer' ?></button></form>
                </td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <?php endif; ?>

            <section id="activity" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">SUIVI</span><h2>Dernières locations</h2></div><a class="admin-site-link" href="/admin/rentals">Voir toutes les locations</a></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Référence</th><th>Client</th><th>Location</th><th>Caution</th><th>État</th><th>Date</th></tr></thead><tbody><?php foreach ($rentals as $r): ?><tr><td><?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($r['customer_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= number_format((int) $r['rental_fee'], 0, ',', ' ') ?> F</td><td><?= number_format((int) $r['deposit'], 0, ',', ' ') ?> F</td><td><span class="status-pill"><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (App\Services\Auth::can('rentals.manage') && $modes['heycharge']==='normal'): ?>
                    <?php if (in_array($r['status'],['releasing','release_failed','active'],true)): ?><form method="post" action="/admin/stations/reconcile"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'],ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Vérifier la station</button></form><?php endif; ?>
                    <?php endif; ?></td><td><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>

        </main>
    </div>
</div>

</body>
</html>
