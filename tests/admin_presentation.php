<?php
declare(strict_types=1);
namespace App\Services {
    // Isolated view fixtures: no session authentication, DB, API or payment calls.
    final class Auth {
        public static array $denied = [];
        public static function can(string $permission, ?array $user=null): bool { return !in_array($permission, self::$denied, true); }
        public static function user(): array { return ['display_name'=>'Compte de test']; }
    }
}
namespace {
    require_once dirname(__DIR__).'/app/Services/ReleaseInfo.php';
    function renderView(string $view, array $data): string {
        extract($data); ob_start(); require dirname(__DIR__).'/views/'.$view.'.php'; return ob_get_clean();
    }
    function verify(bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); }
    $_SESSION=['csrf'=>'fixture-csrf']; $_SERVER['REQUEST_URI']='/admin';
    $fixture=['csrf'=>'fixture-csrf','prices'=>['rental_fee'=>100,'duration_minutes'=>60,'default_deposit'=>200,'deposit_enabled'=>0,'late_percent'=>10], 'batteries'=>[['id'=>1,'serial'=>'<script>alert(1)</script>','status'=>'available','station_imei'=>'STATION-1','slot_id'=>'1','battery_capacity'=>80,'deposit_override'=>null]], 'stations'=>[], 'rentals'=>[], 'stats'=>[], 'modes'=>['heycharge'=>'normal','paiementpro'=>'production'], 'workerRecent'=>true];
    $dashboard=renderView('admin',$fixture);
    verify(!str_contains($dashboard,'action="/admin/prices"')&&!str_contains($dashboard,'action="/admin/batteries"'),'Dashboard must only summarize dedicated pricing/fleet pages');
    verify(str_contains($dashboard,'href="/admin/pricing"')&&str_contains($dashboard,'href="/admin/batteries"'),'Dedicated navigation missing');
    verify(!str_contains($dashboard,'<script>alert(1)</script>')&&str_contains($dashboard,'&lt;script&gt;'),'Battery serial must be escaped');
    $pricing=renderView('pricing',$fixture);
    foreach(['rental_fee','deposit_enabled','default_deposit','duration_minutes','late_percent','csrf'] as $field) verify(str_contains($pricing,'name="'.$field.'"'),'Pricing field missing: '.$field);
    verify(str_contains($pricing,'action="/admin/prices"'),'Pricing POST endpoint changed');
    $batteries=renderView('batteries',$fixture);
    foreach(['serial','deposit_override','csrf'] as $field) verify(str_contains($batteries,'name="'.$field.'"'),'Battery field missing: '.$field);
    verify(str_contains($batteries,'action="/admin/batteries"')&&str_contains($batteries,'/admin/batteries/detail?id=1'),'Battery form/detail missing');
    \App\Services\Auth::$denied=['fleet.manage','pricing.manage','stations.view','batteries.view','labels.view','pricing.view'];
    $restricted=renderView('admin',$fixture);
    verify(!str_contains($restricted,'Aperçu des batteries')&&!str_contains($restricted,'href="/admin/pricing"')&&!str_contains($restricted,'href="/admin/batteries"'),'Permission-controlled sections leaked');
    \App\Services\Auth::$denied=['pricing.manage','fleet.manage'];
    $readonly=renderView('pricing',$fixture);
    verify(!str_contains($readonly,'action="/admin/prices"')&&str_contains($readonly,'href="/admin/pricing"'),'Read access must not grant write access');
    echo "Admin presentation OK (forms, permissions, escaping; no network or DB)\n";
}
