<?php
use App\Services\Auth;
$adminPath =
    parse_url($_SERVER["REQUEST_URI"] ?? "/admin", PHP_URL_PATH) ?: "/admin";
$navItems = [
    ["Tableau de bord", "/admin", "dashboard.view", "overview"],
    ["Locations", "/admin/rentals", "rentals.manage", "rentals"],
    ["Caisse et finances", "/admin/finance", "finance.view", "finance"],
    ["Terminaux", "/admin/stations", "fleet.manage", "stations"],
    ["Étiquettes QR", "/admin/stations/labels", "fleet.manage", "labels"],
    ["Tarification", "/admin/pricing", "pricing.manage", "pricing"],
    ["Batteries", "/admin/batteries", "fleet.manage", "fleet"],
    ["Reversements API", "/admin/payout", "payout.view", "payout"],
    ["Journal et visites", "/admin/audit", "audit.view", "audit"],
    ["Comptes et droits", "/admin/users", "users.manage", "users"],
];
$navIcons = [
    "overview" => "fa-chart-pie",
    "rentals" => "fa-arrow-right-arrow-left",
    "finance" => "fa-wallet",
    "stations" => "fa-tower-broadcast",
    "labels" => "fa-qrcode",
    "pricing" => "fa-tags",
    "fleet" => "fa-battery-full",
    "payout" => "fa-money-bill-transfer",
    "audit" => "fa-clock-rotate-left",
    "users" => "fa-user-shield",
];
$navDescriptions = [
    "rentals" => "Suivi et fiches des locations",
    "finance" => "Caisse et points financiers",
    "stations" => "Inventaire de vos terminaux",
    "labels" => "Étiquettes et QR codes",
    "pricing" => "Tarifs et règles de caution",
    "fleet" => "État et fiches des batteries",
    "payout" => "Essais et suivi PaiementPro",
    "audit" => "Historique des opérations",
    "users" => "Accès et permissions",
];
$adminPageTitle = $adminPageTitle ?? "Administration";
$adminPageOverline = $adminPageOverline ?? "ESPACE DE GESTION";
$adminPageSubtitle = $adminPageSubtitle ?? "";
$adminUser = Auth::user();
$adminCsrf = $_SESSION["csrf"] ?? "";
$isActive = static function (string $name, string $href) use (
    $adminPath,
): bool {
    return match ($name) {
        "overview" => $adminPath === "/admin",
        "stations" => str_starts_with($adminPath, "/admin/stations") &&
            $adminPath !== "/admin/stations/labels",
        "labels" => $adminPath === "/admin/stations/labels",
        "fleet" => str_starts_with($adminPath, "/admin/batteries"),
        "rentals" => str_starts_with($adminPath, "/admin/rentals"),
        default => $adminPath === $href,
    };
};
?>
<link rel="stylesheet" href="/admin-icons.css?v=6.7.2" referrerpolicy="no-referrer">
<link rel="stylesheet" href="/admin-design.css?v=20261003-2">
<script src="/admin-design.js?v=20261003-2" defer></script>
<a class="tb-skip-link" href="#tb-page-content">Aller au contenu</a>
<div class="admin-layout">
 <aside class="admin-sidebar" aria-label="Menu de gestion">
  <a class="admin-brand" href="/admin">THIEBA<span>POWER</span></a>
  <div class="admin-caption">ESPACE DE GESTION</div>
  <nav aria-label="Navigation principale">
   <?php foreach ($navItems as [$label, $href, $permission, $name]):

       if (!Auth::can($permission, $adminUser)) {
           continue;
       }
       $active = $isActive($name, $href);
       ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, "UTF-8") ?>" <?= $active
    ? 'aria-current="page"'
    : "" ?>><i class="fa-solid <?= htmlspecialchars(
    $navIcons[$name] ?? "fa-layer-group",
    ENT_QUOTES,
    "UTF-8",
) ?>" aria-hidden="true"></i><span><?= htmlspecialchars(
    $label,
    ENT_QUOTES,
    "UTF-8",
) ?></span></a>
   <?php
   endforeach; ?>
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
<?php if(Auth::can('dashboard.view',$adminUser)): ?><details class="tb-notifications" data-csrf="<?= htmlspecialchars($adminCsrf,ENT_QUOTES,'UTF-8') ?>"><summary aria-label="Notifications système"><i class="fa-solid fa-bell" aria-hidden="true"></i><span class="tb-notification-count" hidden>0</span></summary><div class="tb-notification-panel"><h2>Notifications</h2><p class="tb-monitor-status" role="status">Chargement du suivi…</p><div class="tb-notification-list"></div></div></details><?php endif; ?><script src="/admin-monitoring.js?v=20261003-1" defer></script><a class="admin-site-link" href="/">Kiosque</a><span class="admin-user-name"><?= htmlspecialchars(
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
   <?php foreach ($navItems as [$label, $href, $permission, $name]):
       if (!Auth::can($permission, $adminUser)) {
           continue;
       } ?><a href="<?= htmlspecialchars(
    $href,
    ENT_QUOTES,
    "UTF-8",
) ?>" <?= $isActive($name, $href)
    ? 'aria-current="page"'
    : "" ?>><i class="fa-solid <?= htmlspecialchars(
    $navIcons[$name] ?? "fa-layer-group",
    ENT_QUOTES,
    "UTF-8",
) ?>" aria-hidden="true"></i><span><?= htmlspecialchars(
    $label,
    ENT_QUOTES,
    "UTF-8",
) ?></span></a><?php
   endforeach; ?>
  </nav></details>
