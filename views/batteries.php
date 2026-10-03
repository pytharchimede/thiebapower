<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Batteries · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body">
<?php
$adminPageTitle = "Batteries";
$adminPageSubtitle = "Retrouvez chaque batterie, sa station et son état.";
require __DIR__ . "/partials/admin_shell_start.php";
?>
<main class="management-main">
<?php if(($_GET['saved']??'')==='1'): ?><p class="tb-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Modifications enregistrées.</p><?php endif; ?>
<div class="tb-page-intro"><div><span class="tb-eyebrow">VOTRE PARC</span><h2><?= count(
    $batteries,
) ?> batteries enregistrées</h2><p>Recherchez un numéro de série ou une station, puis ouvrez la fiche du matériel.</p></div><a class="tb-link-button" href="/admin/stations"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i> Voir les terminaux</a></div>            <?php if (
     App\Services\Auth::can("fleet.manage")
 ): ?>
            <section id="fleet" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">MATÉRIEL</span><h2>Parc batteries</h2><p>Une caution propre à chaque batterie peut remplacer le montant par défaut.</p></div></div>
                <form action="/admin/batteries" method="post" class="admin-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
                    $csrf,
                    ENT_QUOTES,
                    "UTF-8",
                ) ?>"><label>Numéro de série<input name="serial" maxlength="100" required></label><label>Caution spécifique (FCFA)<input name="deposit_override" type="number" min="0" placeholder="Vide = caution par défaut"></label><div class="form-action"><span>Le numéro doit correspondre au matériel.</span><button class="admin-button">Enregistrer la batterie</button></div></form>
                <div class="admin-table-wrap"><table><thead><tr><th>Batterie</th><th>Station</th><th>Emplacement</th><th>Charge</th><th>État</th><th>Caution</th></tr></thead><tbody><?php foreach (
                    $batteries
                    as $battery
                ): ?><tr><td><a href="/admin/batteries/detail?id=<?= (int) $battery[
    "id"
] ?>"><?= htmlspecialchars(
    $battery["serial"],
    ENT_QUOTES,
    "UTF-8",
) ?></a></td><td><?= htmlspecialchars(
    (string) ($battery["station_imei"] ?? "—"),
    ENT_QUOTES,
    "UTF-8",
) ?></td><td><?= htmlspecialchars(
    (string) ($battery["slot_id"] ?? "—"),
    ENT_QUOTES,
    "UTF-8",
) ?></td><td><?= $battery["battery_capacity"] === null
    ? "—"
    : (int) $battery["battery_capacity"] .
        " %" ?></td><td><span class="status-pill"><?= htmlspecialchars(
    $battery["status"],
    ENT_QUOTES,
    "UTF-8",
) ?></span></td><td><?= $battery["deposit_override"] === null
    ? "Par défaut"
    : number_format((int) $battery["deposit_override"], 0, ",", " ") .
        " FCFA" ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
            <?php endif; ?>

</main><?php require __DIR__ . "/partials/admin_shell_end.php"; ?></body></html>
