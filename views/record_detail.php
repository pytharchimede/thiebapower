<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Détails · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body">
<?php
$adminPageTitle = $title;
$adminPageSubtitle = "Les informations de votre fiche, en un coup d’œil.";
require __DIR__ . "/partials/admin_shell_start.php";
$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8");
?>
<main class="management-main"><div class="tb-page-intro"><a class="tb-link-button" href="<?= $esc(
    $back,
) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour au listing</a><?php if (
    $stationLink !== ""
): ?><a class="tb-link-button" href="<?= $esc(
    $stationLink,
) ?>">Voir le terminal <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php endif; ?></div><section class="management-card"><span class="tb-eyebrow">FICHE DÉTAILLÉE</span><h2><?= $esc(
    $title,
) ?></h2><dl class="tb-detail-grid"><?php foreach (
    $fields
    as $label => $value
): ?><div><dt><?= $esc($label) ?></dt><dd><?= $esc(
    $value,
) ?></dd></div><?php endforeach; ?></dl>
<?php if(!empty($usage['started_at'])): ?><div class="tb-use-timer" data-reference="<?= htmlspecialchars($usage['reference'],ENT_QUOTES,'UTF-8') ?>" data-start="<?= strtotime($usage['started_at'].' UTC') ?>" data-due="<?= !empty($usage['due_at'])?strtotime($usage['due_at'].' UTC'):0 ?>" data-end="<?= !empty($usage['returned_at'])?strtotime($usage['returned_at'].' UTC'):0 ?>" data-running="<?= $usage['status']==='active'?'1':'0' ?>"></div><p class="tb-muted">Depuis la sortie confirmée · <?= $esc($usage['reference']) ?></p><?php endif; ?></section></main><?php require __DIR__ .
    "/partials/admin_shell_end.php"; ?></body></html>
