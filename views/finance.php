<?php require_once __DIR__.'/../app/Services/AdminNavigation.php'; $financeSection=$financeSection??'overview';$financePages=\App\Services\AdminNavigation::groups()['finances']['items'];$pageLabel='Tableau de bord financier';foreach($financePages as $item){if($item[3]===$financeSection)$pageLabel=$item[0];} ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageLabel,ENT_QUOTES,'UTF-8') ?> · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body">
<?php $adminPageTitle=$pageLabel;$adminPageOverline='GESTION FINANCIÈRE';$adminPageSubtitle='Encaissements confirmés, cautions et caisse physique';require __DIR__.'/partials/admin_shell_start.php';
$money=static fn(int $n)=>number_format($n,0,',',' ').' FCFA';$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<main class="admin-main finance-page">
<nav class="tb-module-breadcrumb" aria-label="Fil d’Ariane"><a href="/admin/finance">Finances</a><?php if($financeSection!=='overview'): ?><span aria-hidden="true">/</span><span aria-current="page"><?= $escape($pageLabel) ?></span><?php endif; ?></nav>
<?php if(in_array($financeSection,['overview','withdrawals'],true))require __DIR__.'/partials/finance_balances.php'; ?>
<?php if($financeSection==='overview'): ?>
<section class="admin-card"><div class="admin-section-heading"><div><span class="admin-overline">QUE SOUHAITEZ-VOUS FAIRE ?</span><h2>Accès rapides aux finances</h2><p>Choisissez une tâche. Chaque écran conserve ses contrôles et ses exports.</p></div></div>
<div class="tb-module-grid"><?php foreach(\App\Services\AdminNavigation::visible($adminUser)['finances']['items']??[] as $item): if($item[3]==='finance')continue; ?><a class="tb-module-card" href="<?= $escape($item[1]) ?>"><strong><?= $escape($item[0]) ?></strong><span class="tb-muted"><?= $escape($item[4]) ?></span></a><?php endforeach; ?></div></section>
<?php require __DIR__.'/partials/finance/filter.php';require __DIR__.'/partials/finance/summary.php'; ?>
<?php else: ?>
<?php if($financeSection!=='withdrawals')require __DIR__.'/partials/finance/filter.php'; ?>
<?php require __DIR__.'/partials/finance/'.$financeSection.'.php'; ?>
<?php endif; ?>
</main><?php require __DIR__.'/partials/admin_shell_end.php'; ?></body></html>
