<?php
use App\Services\Auth;
$adminPath=parse_url($_SERVER['REQUEST_URI']??'/admin',PHP_URL_PATH)?:'/admin';
$navItems=[
 ['Tableau de bord','/admin','dashboard.view','overview'],
 ['Locations','/admin/rentals','dashboard.view','rentals'],
 ['Terminaux','/admin/stations','fleet.manage','stations'],
 ['Étiquettes QR','/admin/stations/labels','fleet.manage','labels'],
 ['Tarification','/admin#pricing','pricing.manage','pricing'],
 ['Batteries','/admin#fleet','fleet.manage','fleet'],
 ['Reversements API','/admin/payout','payout.view','payout'],
 ['Journal et visites','/admin/audit','audit.view','audit'],
 ['Comptes et droits','/admin/users','users.manage','users'],
];
$adminPageTitle=$adminPageTitle??'Administration';
$adminPageOverline=$adminPageOverline??'ESPACE DE GESTION';
$adminPageSubtitle=$adminPageSubtitle??'';
$adminUser=Auth::user();
$adminCsrf=$_SESSION['csrf']??'';
$isActive=static function(string $name,string $href)use($adminPath):bool{
 return match($name){
  'overview'=>$adminPath==='/admin',
  'stations'=>str_starts_with($adminPath,'/admin/stations')&&$adminPath!=='/admin/stations/labels',
  'labels'=>$adminPath==='/admin/stations/labels',
  default=>$adminPath===$href,
 };
};
?>
<div class="admin-layout">
 <aside class="admin-sidebar" aria-label="Menu de gestion">
  <a class="admin-brand" href="/admin">THIEBA<span>POWER</span></a>
  <div class="admin-caption">ESPACE DE GESTION</div>
  <nav aria-label="Navigation principale">
   <?php foreach($navItems as [$label,$href,$permission,$name]):if(!Auth::can($permission,$adminUser))continue;
    $active=$isActive($name,$href); ?>
    <a href="<?= htmlspecialchars($href,ENT_QUOTES,'UTF-8') ?>" <?= $active?'aria-current="page"':'' ?>><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></a>
   <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom"><span class="sidebar-indicator"></span>Thiebapower · Gestion du parc</div>
 </aside>
 <div class="admin-content">
  <header class="admin-top admin-global-top">
   <div class="admin-top-identity"><span class="admin-overline"><?= htmlspecialchars($adminPageOverline,ENT_QUOTES,'UTF-8') ?></span><h1><?= htmlspecialchars($adminPageTitle,ENT_QUOTES,'UTF-8') ?></h1><?php if($adminPageSubtitle!==''): ?><p><?= htmlspecialchars($adminPageSubtitle,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?></div>
   <div class="admin-top-actions"><a class="admin-site-link" href="/">Kiosque</a><span class="admin-user-name"><?= htmlspecialchars($adminUser['display_name']??'',ENT_QUOTES,'UTF-8') ?></span><form method="post" action="/admin/logout"><input type="hidden" name="csrf" value="<?= htmlspecialchars($adminCsrf,ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Déconnexion</button></form></div>
  </header>
  <details class="admin-mobile-menu"><summary>Menu de gestion <span aria-hidden="true">☰</span></summary><nav aria-label="Navigation mobile">
   <?php foreach($navItems as [$label,$href,$permission,$name]):if(!Auth::can($permission,$adminUser))continue; ?><a href="<?= htmlspecialchars($href,ENT_QUOTES,'UTF-8') ?>" <?= $isActive($name,$href)?'aria-current="page"':'' ?>><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></a><?php endforeach; ?>
  </nav></details>
