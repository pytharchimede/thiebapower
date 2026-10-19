<?php use App\Services\Auth; $navEscape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<?php if(Auth::can('dashboard.view',$adminUser)): ?><a href="/admin" <?= $adminPath==='/admin'?'aria-current="page"':'' ?>><i class="fa-solid fa-house" aria-hidden="true"></i><span>Accueil général</span></a><?php endif; ?>
<?php foreach($navGroups as $groupKey=>$group):
$groupActive=$adminPath===$group['href'];
foreach($group['items'] as $item){if(\App\Services\AdminNavigation::active($item[1],$adminPath))$groupActive=true;}
?>
<details class="tb-nav-group" <?= $groupActive?'open':'' ?>>
<summary><i class="fa-solid <?= $navEscape($group['icon']) ?>" aria-hidden="true"></i><span><?= $navEscape($group['label']) ?></span><i class="fa-solid fa-chevron-down tb-nav-chevron" aria-hidden="true"></i></summary>
<div class="tb-nav-children">
<?php if($groupKey!=='finances'||$group['href']!=='/admin/finance'): ?><a href="<?= $navEscape($group['href']) ?>" <?= $adminPath===$group['href']?'aria-current="page"':'' ?>>Vue d’ensemble</a><?php endif; ?>
<?php foreach($group['items'] as [$label,$href,$permission,$name]): ?><a href="<?= $navEscape($href) ?>" <?= $isActive($name,$href)?'aria-current="page"':'' ?>><?= $navEscape($label) ?></a><?php endforeach; ?>
</div></details>
<?php endforeach; ?>
