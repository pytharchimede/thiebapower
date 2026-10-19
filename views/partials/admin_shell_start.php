<?php
use App\Services\Auth;
$adminPath =
    parse_url($_SERVER["REQUEST_URI"] ?? "/admin", PHP_URL_PATH) ?: "/admin";
require_once dirname(__DIR__,2).'/app/Services/AdminNavigation.php';
$adminUser=Auth::user();
$navGroups=\App\Services\AdminNavigation::visible($adminUser);
$navItems=[["Tableau de bord","/admin","dashboard.view","overview"]];
$navIcons=['overview'=>'fa-chart-pie'];$navDescriptions=[];
foreach($navGroups as $group){foreach($group['items'] as $item){
    $navItems[]=array_slice($item,0,4);$navIcons[$item[3]]=$group['icon'];$navDescriptions[$item[3]]=$item[4];
}}
$adminPageTitle = $adminPageTitle ?? "Administration";
$adminPageOverline = $adminPageOverline ?? "ESPACE DE GESTION";
$adminPageSubtitle = $adminPageSubtitle ?? "";
$adminUser = Auth::user();
$adminCsrf = $_SESSION["csrf"] ?? "";
$isActive=static fn(string $name,string $href):bool=>\App\Services\AdminNavigation::active($href,$adminPath);
?>
<meta name="tb-csrf" content="<?= htmlspecialchars($adminCsrf,ENT_QUOTES,'UTF-8') ?>">
<script>window.TB_CAN_EXPORT=<?= Auth::can("reports.export") ? "true" : "false" ?>;</script>
<link rel="stylesheet" href="/admin-icons.css?v=6.7.2" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/admin-design.css?v=20261019-ux1">
<script src="/admin-design.js?v=20261019-ux1" defer></script>
<?php if(Auth::can('finance.view') && Auth::can('rentals.view')): ?><script src="/rental-deposits.js?v=20261005-1" defer></script><link rel="stylesheet" href="/rental-deposits.css?v=20261003-1"><?php endif; ?>
<link rel="stylesheet" href="/admin-navigation.css?v=20261019-ux1">
<a class="tb-skip-link" href="#tb-page-content">Aller au contenu</a>
<div class="admin-layout">
 <aside class="admin-sidebar" aria-label="Menu de gestion">
  <a class="admin-brand" href="/admin">THIEBA<span>POWER</span></a>
  <div class="admin-caption">ESPACE DE GESTION</div>
  <nav aria-label="Navigation principale">
   <?php require __DIR__.'/admin_navigation.php'; ?>
  </nav>
  <div class="sidebar-bottom"><span class="sidebar-indicator"></span>Thiebapower · Gestion du parc</div>
 </aside>
 <div class="admin-content" id="tb-page-content">
  <header class="admin-top admin-global-top">
   <div class="admin-top-identity"><span class="admin-overline"><?= htmlspecialchars(
       $adminPageOverline,
       ENT_QUOTES,
       "UTF-8",
   ) ?></span><h1><?= htmlspecialchars(
    $adminPageTitle,
    ENT_QUOTES,
    "UTF-8",
) ?></h1><?php if ($adminPageSubtitle !== ""): ?><p><?= htmlspecialchars(
    $adminPageSubtitle,
    ENT_QUOTES,
    "UTF-8",
) ?></p><?php endif; ?></div>
   <div class="admin-top-actions">
<?php if(Auth::can('dashboard.view',$adminUser)): ?><details class="tb-notifications" data-csrf="<?= htmlspecialchars($adminCsrf,ENT_QUOTES,'UTF-8') ?>"><summary aria-label="Notifications système"><i class="fa-solid fa-bell" aria-hidden="true"></i><span class="tb-notification-count" hidden>0</span></summary><div class="tb-notification-panel"><h2>Notifications</h2><p class="tb-monitor-status" role="status">Chargement du suivi…</p><div class="tb-notification-list"></div></div></details><?php endif; ?><script src="/admin-monitoring.js?v=20261003-6" data-counters-enabled="<?= !class_exists(\App\Services\SystemStorage::class)||\App\Services\SystemStorage::countersEnabled()?'1':'0' ?>" defer></script><a class="admin-site-link" href="/">Kiosque</a><span class="admin-user-name"><?= htmlspecialchars(
       $adminUser["display_name"] ?? "",
       ENT_QUOTES,
       "UTF-8",
   ) ?></span><form method="post" action="/admin/logout"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $adminCsrf,
    ENT_QUOTES,
    "UTF-8",
) ?>"><button class="admin-ghost">Déconnexion</button></form></div>
  </header>
  <details class="admin-mobile-menu"><summary><span><i class="fa-solid fa-grip" aria-hidden="true"></i> Tous les espaces</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary><nav aria-label="Navigation mobile">
   <?php require __DIR__.'/admin_navigation.php'; ?>
  </nav></details>
