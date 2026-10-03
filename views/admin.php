<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tableau de bord · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body">
<?php
$adminPageTitle = "Tableau de bord";
$adminPageSubtitle = "Votre activité, votre parc et vos actions essentielles.";
require __DIR__ . "/partials/admin_shell_start.php";
?>
<main class="management-main">
<?php
$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
$available = count(
    array_filter($batteries, static fn($b) => $b["status"] === "available"),
);
$active = 0;
foreach ($stats as $stat) {
    if ($stat["status"] === "active") {
        $active = (int) $stat["quantity"];
    }
}
?>
<section class="tb-hero"><div><span class="tb-eyebrow">THIEBAPOWER · VUE D’ENSEMBLE</span><h2>De l’énergie.<br>Et une activité sous contrôle.</h2><p>Retrouvez l’essentiel et accédez rapidement à chaque espace de gestion.</p><div class="tb-hero-meta"><span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> <?= date(
    "d/m/Y",
) ?></span><span><span class="tb-live-dot"></span> Worker <?= $workerRecent
    ? "actif"
    : "à vérifier" ?></span></div></div><div class="tb-hero-art" aria-hidden="true"><i class="fa-solid fa-bolt"></i><span>POWER<br>ON.</span></div></section>
<section class="admin-stats tb-stats" aria-label="Indicateurs du parc"><article><i class="fa-solid fa-battery-full" aria-hidden="true"></i><span>Batteries disponibles</span><strong><?= $available ?></strong><small>Sur <?= count(
    $batteries,
) ?> batteries enregistrées</small></article><article><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i><span>Terminaux</span><strong><?= count(
     $stations,
 ) ?></strong><small>Stations enregistrées</small></article><article><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i><span>Locations actives</span><strong><?= $active ?></strong><small><?= array_sum(
    array_column($stats, "quantity"),
) ?> locations au total</small></article><article><i class="fa-solid fa-tag" aria-hidden="true"></i><span>Tarif de location</span><strong><?= number_format(
     (int) $prices["rental_fee"],
     0,
     ",",
     " ",
 ) ?> <small>F</small></strong><small><?= (int) $prices[
     "duration_minutes"
 ] ?> min · Caution <?= (int) $prices["deposit_enabled"] === 1
     ? "active"
     : "désactivée" ?></small></article></section>
<section><div class="admin-section-heading"><div><span class="tb-eyebrow">À PORTÉE DE MAIN</span><h2>Vos espaces de gestion</h2></div><span class="tb-muted">Choisissez une action pour commencer</span></div><div class="tb-module-grid"><?php foreach (
    $navItems
    as [$label, $href, $permission, $name]
):
    if (
        $name === "overview" ||
        !App\Services\Auth::can($permission, $adminUser)
    ) {
        continue;
    } ?><a class="tb-module-card" href="<?= $esc(
    $href,
) ?>"><span class="tb-module-icon"><i class="fa-solid <?= $esc(
    $navIcons[$name] ?? "fa-layer-group",
) ?>" aria-hidden="true"></i></span><strong><?= $esc(
    $label,
) ?></strong><span class="tb-muted"><?= $esc(
    $navDescriptions[$name] ?? "Ouvrir cet espace",
) ?></span><i class="fa-solid fa-arrow-up-right-from-square tb-card-arrow" aria-hidden="true"></i></a><?php
endforeach; ?></div></section>
<?php if (
    App\Services\Auth::can("fleet.manage")
): ?><section class="admin-card"><div class="admin-section-heading"><div><span class="tb-eyebrow">VOTRE MATÉRIEL</span><h2>Aperçu des batteries</h2><p>Les 6 premières batteries du parc.</p></div><a class="tb-link-button" href="/admin/batteries">Tout le parc <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div><div class="tb-battery-grid"><?php
foreach (array_slice($batteries, 0, 6) as $b):
    $charge =
        $b["battery_capacity"] ??
        null; ?><a class="tb-battery-card" href="/admin/batteries/detail?id=<?= (int) $b[
    "id"
] ?>"><span class="tb-battery-top"><i class="fa-solid fa-battery-three-quarters" aria-hidden="true"></i><span class="status-pill"><?= $esc(
    $b["status"],
) ?></span></span><strong><?= $esc(
    $b["serial"],
) ?></strong><span class="tb-muted">Station <?= $esc(
    $b["station_imei"] ?? "non affectée",
) ?></span><span class="tb-charge"><span style="width:<?= $charge === null
    ? 0
    : min(
        100,
        max(0, (int) $charge),
    ) ?>%"></span></span><span class="tb-battery-bottom">Charge <?= $charge ===
null
    ? "inconnue"
    : (int) $charge . " %" ?><span>Voir la fiche →</span></span></a><?php
endforeach;
if (
    !$batteries
): ?><p class="tb-empty">Aucune batterie enregistrée. Retrouvez les options d’ajout dans Batteries.</p><?php endif;
?></div></section><?php endif; ?>
<?php if(App\Services\Auth::can('rentals.manage')): ?><section class="admin-card"><span class="tb-eyebrow">UTILISATION EN DIRECT</span><h2>Locations en cours</h2><button type="button" class="admin-ghost tb-counter-toggle" aria-pressed="false">Masquer les compteurs sur cet appareil</button><p>Temps écoulé depuis la sortie confirmée · Durée restante et dépassement.</p><p class="tb-monitor-status" role="status">Chargement du suivi…</p><div class="tb-active-rentals tb-battery-grid"></div></section><?php endif; ?>
<section id="activity" class="admin-card"><div class="admin-section-heading"><div><span class="tb-eyebrow">ACTIVITÉ RÉCENTE</span><h2>Dernières locations</h2><p>Les 10 dernières locations enregistrées.</p></div><?php if (
    App\Services\Auth::can("rentals.manage")
): ?><a class="tb-link-button" href="/admin/rentals">Toutes les locations <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?></div><div class="admin-table-wrap"><table><thead><tr><th>Référence</th><th>Client</th><th>Tarif</th><th>Caution</th><th>État</th><th>Création</th></tr></thead><tbody><?php foreach (
    $rentals
    as $r
): ?><tr><td><?php if (
    App\Services\Auth::can("rentals.manage")
): ?><a href="/admin/rentals/detail?reference=<?= rawurlencode(
    $r["reference"],
) ?>"><?= $esc($r["reference"]) ?></a><?php else: ?><?= $esc($r["reference"]) ?><?php endif; ?></td><td><?= $esc(
    $r["customer_name"],
) ?></td><td><?= number_format(
    (int) $r["rental_fee"],
    0,
    ",",
    " ",
) ?> FCFA</td><td><?= number_format(
     (int) $r["deposit"],
     0,
     ",",
     " ",
 ) ?> FCFA</td><td><span class="status-pill"><?= $esc(
     $r["status"],
 ) ?></span><?php if (
    App\Services\Auth::can("rentals.manage") &&
    $modes["heycharge"] === "normal" &&
    in_array($r["status"], ["releasing", "release_failed", "active"], true)
): ?><form method="post" action="/admin/stations/reconcile"><input type="hidden" name="csrf" value="<?= $esc(
    $csrf,
) ?>"><input type="hidden" name="reference" value="<?= $esc(
    $r["reference"],
) ?>"><button class="admin-ghost">Vérifier la station</button></form><?php endif; ?></td><td><?= $esc(
    $r["created_at"],
) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="tb-system-strip"><span><i class="fa-solid fa-plug" aria-hidden="true"></i> HeyCharge : <?= $esc(
    $modes["heycharge"],
) ?></span><span>PaiementPro : <?= $esc(
    $modes["paiementpro"],
) ?></span><span>Worker : <?= $workerRecent
    ? "actif"
    : "à vérifier" ?></span></section>
</main><?php require __DIR__ . "/partials/admin_shell_end.php"; ?></body></html>
