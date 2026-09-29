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
            <?php if (App\Services\Auth::can('integrations.manage')): ?><a href="#integrations">Intégrations</a><?php endif; ?>
            <?php if (App\Services\Auth::can('payout.view')): ?><a href="/admin/payout">Reversements API</a><?php endif; ?>
            <?php if (App\Services\Auth::can('payout.send')): ?><a href="#payment-lab">Essais financiers</a><?php endif; ?>
            <?php if (App\Services\Auth::can('pricing.manage')): ?><a href="#pricing">Tarification</a><?php endif; ?>
            <?php if (App\Services\Auth::can('fleet.manage')): ?><a href="#fleet">Batteries</a><a href="#stations">Terminaux</a><?php endif; ?>
            <a href="#activity">Locations</a>
            <?php if (App\Services\Auth::can('rentals.manage')): ?><a href="#simulation">Simulation</a><?php endif; ?>
            <?php if (App\Services\Auth::can('audit.view')): ?><a href="/admin/audit">Journal et visites</a><?php endif; ?>
            <?php if (App\Services\Auth::can('users.manage')): ?><a href="/admin/users">Comptes et droits</a><?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <span class="sidebar-indicator"></span> HeyCharge en <?= $modes['heycharge'] === 'simulation' ? 'simulation' : 'normal' ?>
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
                <div class="admin-banner"><div><strong>Première version pilotée</strong><p>Le kiosque et les paiements réels peuvent être testés avec une station simulée. La commande physique HeyCharge est disponible sous contrôle administrateur. Un payout initié ne prouve pas que le bénéficiaire est crédité.</p></div><span>V1</span></div>
            </section>

            <?php if (App\Services\Auth::can('integrations.manage')): ?>
            <section id="integrations" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">CONNEXIONS</span><h2>Modes d’intégration</h2><p>Changer de mode ne lance aucun paiement ni commande de station.</p></div></div>
                <form method="post" action="/admin/modes">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="integration-grid">
                        <fieldset><legend>Paiement Pro</legend><p>Encaissements et reversements</p>
                            <label class="mode-option"><input type="radio" name="paiementpro_mode" value="sandbox" <?= $modes['paiementpro'] === 'sandbox' ? 'checked' : '' ?>><span><strong>Sandbox</strong><small>Accès de test distinct</small></span></label>
                            <label class="mode-option"><input type="radio" name="paiementpro_mode" value="production" <?= $modes['paiementpro'] === 'production' ? 'checked' : '' ?>><span><strong>Production</strong><small>Transactions réelles</small></span></label>
                            <div class="provider-status">Sandbox : <?= $ready['paiementpro_sandbox'] ? 'configurée' : 'à configurer' ?> · Production : <?= $ready['paiementpro_production'] ? 'configurée' : 'à configurer' ?></div>
                        </fieldset>
                        <fieldset><legend>HeyCharge</legend><p>Stations et batteries</p>
                            <label class="mode-option"><input type="radio" name="heycharge_mode" value="simulation" <?= $modes['heycharge'] === 'simulation' ? 'checked' : '' ?>><span><strong>Simulation</strong><small>Aucune batterie physique éjectée</small></span></label>
                            <label class="mode-option"><input type="radio" name="heycharge_mode" value="normal" <?= $modes['heycharge'] === 'normal' ? 'checked' : '' ?>><span><strong>Normal</strong><small>Stations synchronisées et libération contrôlée</small></span></label>
                            <div class="provider-status">Clé Open API : <?= $ready['heycharge_normal'] ? 'renseignée' : 'à compléter' ?></div>
                        </fieldset>
                    </div>
                    <button class="admin-button">Enregistrer les modes</button>
                </form>
            </section>
            <?php endif; ?>

            <?php if (App\Services\Auth::can('payout.send')): ?>
            <section id="payment-lab" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">ESSAIS FINANCIERS</span><h2>Encaissement et reversement</h2><p>Deux opérations distinctes : 300 FCFA encaissés et 200 FCFA versés.</p></div><a class="admin-site-link" href="/admin/payout">Console payout</a></div>
                <div class="admin-banner"><div><strong>Contrôlez l’issue fournisseur</strong><p>Une réponse INITIATED ne garantit pas que l’argent est arrivé. Consultez le journal de l’API et le portail Paiement Pro.</p></div></div>
                <div class="lab-grid">
                    <form method="post" action="/admin/payment-lab/payin" class="lab-card">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="admin-overline">ENCAISSEMENT</span><h3>Encaisser 300 FCFA</h3><p>100 FCFA de location et 200 FCFA de caution.</p>
                        <label>Nom<input name="name" required maxlength="120"></label><label>Email<input name="email" type="email" required></label><label>Téléphone<input name="phone" type="tel" required placeholder="+225..."></label>
                        <label class="lab-confirm"><input type="checkbox" name="confirm_amount" value="300" required> Je confirme un paiement réel de 300 FCFA.</label>
                        <button class="admin-button" <?= $payinEnabled ? '' : 'disabled' ?>>Ouvrir Paiement Pro</button>
                    </form>
                    <form method="post" action="/admin/payment-lab/payout" class="lab-card">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="admin-overline">PAYOUT</span><h3>Verser 200 FCFA</h3><p>Essai indépendant des cautions.</p>
                        <label>Numéro bénéficiaire<input name="phone" type="tel" required placeholder="+225..."></label>
                        <label>Canal<select name="channel" required><option value="">Choisir</option><option value="WAVECI">Wave CI</option><option value="MOMOCI">MTN MoMo CI</option><option value="OMCIV">Orange Money CI</option><option value="FLOOZ">Flooz</option></select></label>
                        <label class="lab-confirm"><input type="checkbox" name="confirm_amount" value="200" required> Je confirme un versement réel de 200 FCFA.</label>
                        <button class="admin-button" <?= $payoutEnabled || $autoRefundEnabled ? '' : 'disabled' ?>>Ouvrir la console payout</button>
                    </form>
                </div>
                <div class="admin-table-wrap"><table><thead><tr><th>Référence</th><th>Type</th><th>Montant</th><th>Statut</th><th>Réponse</th></tr></thead><tbody>
                    <?php foreach ($testOperations as $op): ?><tr><td><?= htmlspecialchars($op['reference'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($op['kind'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $op['amount'] ?> FCFA</td><td><span class="status-pill"><?= htmlspecialchars($op['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars((string) ($op['provider_message'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
            <?php endif; ?>

            <?php if (App\Services\Auth::can('pricing.manage')): ?>
            <section id="pricing" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">PARAMÈTRES</span><h2>Tarification</h2><p>Le tarif est figé à la création de chaque location.</p></div><button class="admin-ghost" type="button" id="preset-test">Préremplir 100 F + 200 F</button></div>
                <form action="/admin/prices" method="post" class="admin-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label>Location (FCFA)<input id="rental-fee" type="number" min="0" name="rental_fee" value="<?= (int) $prices['rental_fee'] ?>" required></label>
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
                <div class="admin-table-wrap"><table><thead><tr><th>Batterie</th><th>État</th><th>Caution</th></tr></thead><tbody><?php foreach ($batteries as $battery): ?><tr><td><?= htmlspecialchars($battery['serial'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="status-pill"><?= htmlspecialchars($battery['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= $battery['deposit_override'] === null ? 'Par défaut' : number_format((int) $battery['deposit_override'], 0, ',', ' ') . ' FCFA' ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <?php endif; ?>

            <?php if (App\Services\Auth::can('fleet.manage')): ?>
            <section id="stations" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">HEYCHARGE</span><h2>Terminaux</h2><p>Enregistrez un IMEI, synchronisez son inventaire, puis activez la station.</p></div></div>
                <form action="/admin/stations" method="post" class="admin-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label>IMEI<input name="imei" required pattern="[A-Za-z0-9_-]+" maxlength="120"></label><label>Nom du terminal<input name="label" maxlength="160"></label><button class="admin-button">Enregistrer</button></form>
                <div class="admin-table-wrap"><table><thead><tr><th>IMEI</th><th>Nom</th><th>État</th><th>Vu le</th><th>Actions</th></tr></thead><tbody>
                <?php foreach ($stations as $station): ?><tr><td><?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$station['label'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($station['status'],ENT_QUOTES,'UTF-8') ?> · <?= $station['enabled'] ? 'activé' : 'désactivé' ?></td><td><?= htmlspecialchars((string)$station['last_seen_at'],ENT_QUOTES,'UTF-8') ?></td><td>
                <form method="post" action="/admin/stations/sync"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="imei" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Synchroniser</button></form>
                <form method="post" action="/admin/stations/toggle"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="imei" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="enabled" value="<?= $station['enabled'] ? 0 : 1 ?>"><button class="admin-ghost"><?= $station['enabled'] ? 'Désactiver' : 'Activer' ?></button></form>
                </td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <?php endif; ?>

            <section id="activity" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">SUIVI</span><h2>Dernières locations</h2></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Référence</th><th>Client</th><th>Location</th><th>Caution</th><th>État</th><th>Date</th></tr></thead><tbody><?php foreach ($rentals as $r): ?><tr><td><?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($r['customer_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= number_format((int) $r['rental_fee'], 0, ',', ' ') ?> F</td><td><?= number_format((int) $r['deposit'], 0, ',', ' ') ?> F</td><td><span class="status-pill"><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (App\Services\Auth::can('rentals.manage') && $modes['heycharge']==='normal'): ?>
                    <?php if ($r['status']==='pending_payment' && $r['payment_session_id']): ?><form method="post" action="/admin/stations/confirm-payment"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'],ENT_QUOTES,'UTF-8') ?>"><label>Preuve Paiement Pro<input name="provider_proof" required minlength="6"></label><label><input type="checkbox" name="confirm_paid" value="1" required> Paiement et montant vérifiés</label><button class="admin-ghost">Confirmer et éjecter</button></form><?php endif; ?>
                    <?php if (in_array($r['status'],['releasing','release_failed','active'],true)): ?><form method="post" action="/admin/stations/reconcile"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'],ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Vérifier la station</button></form><?php endif; ?>
                    <?php endif; ?></td><td><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>

            <?php if (App\Services\Auth::can('rentals.manage')): ?>
            <section id="simulation" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">PARCOURS CONTRÔLÉ</span><h2>Sortie et retour simulés</h2><p>L’encaissement et les reversements sont réels ; les événements HeyCharge sont simulés.</p></div><span class="lab-badge"><?= $simulationEnabled ? 'SIMULATION ACTIVE' : 'SIMULATION VERROUILLÉE' ?></span></div>
                <div class="admin-banner"><div><strong>Confirmez chaque paiement chez Paiement Pro</strong><p>Comparez référence, session et montant. Restitution automatique : <?= $autoRefundEnabled ? 'activée' : 'désactivée' ?>.</p></div></div>
                <div class="admin-table-wrap"><table><thead><tr><th>Location</th><th>Encaissement</th><th>État et caution</th><th>Action</th></tr></thead><tbody>
                    <?php foreach ($rentals as $r): ?><tr>
                        <td><strong><?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?></strong><br><small>Session : <?= htmlspecialchars((string) ($r['payment_session_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= (int) $r['rental_fee'] + (int) $r['deposit'] ?> FCFA</td>
                        <td><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?><?php if ($r['refund_status']): ?><br><small>Restitution : <?= htmlspecialchars($r['refund_status'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $r['refund_amount'] ?> FCFA</small><?php endif; ?></td>
                        <td>
                            <?php if ($simulationEnabled && $r['status'] === 'pending_payment' && $r['payment_session_id']): ?>
                                <form method="post" action="/admin/simulation/paid" class="simulation-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?>"><label>Recopier la référence<input name="confirm_reference" required autocomplete="off"></label><label>Preuve fournisseur<input name="provider_proof" required></label><label class="lab-confirm"><input type="checkbox" name="confirm_paid" value="1" required> Paiement confirmé</label><button class="admin-button">Simuler la sortie</button></form>
                            <?php elseif ($simulationEnabled && $r['status'] === 'active'): ?>
                                <form method="post" action="/admin/simulation/returned" class="simulation-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?>"><label>Recopier la référence<input name="confirm_reference" required autocomplete="off"></label><button class="admin-button">Simuler le retour</button></form>
                            <?php elseif ($simulationEnabled && $r['refund_status'] === 'processing'): ?>
                                <form method="post" action="/admin/simulation/reconcile" class="simulation-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference'], ENT_QUOTES, 'UTF-8') ?>"><label>Recopier la référence<input name="confirm_reference" required autocomplete="off"></label><button class="admin-ghost">Vérifier le reversement</button></form>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
    document.getElementById('preset-test')?.addEventListener('click', () => {
        document.getElementById('rental-fee').value = '100';
        document.getElementById('default-deposit').value = '200';
        document.getElementById('rental-fee').focus();
    });
</script>
</body>
</html>
