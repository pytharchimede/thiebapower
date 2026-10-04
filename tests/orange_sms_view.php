<?php
// Render the real view and shared layout with isolated authorization fixtures.
namespace App\Services {
 final class Auth {
  public static array $permissions=[];
  public static function can(string $permission,?array $user=null): bool {return in_array($permission,self::$permissions,true);}
  public static function user(): array {return ['display_name'=>'Test owner','role'=>'owner'];}
 }
 final class SystemStorage {public static function countersEnabled(): bool {return false;}}
}
namespace {
 spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
 function render(array $permissions): string {
  \App\Services\Auth::$permissions=$permissions;
  $_SESSION=['csrf'=>'test-csrf']; $_SERVER['REQUEST_URI']='/admin/sms';
  $settings=\App\Services\OrangeSmsSettings::defaults();$secretConfigured=false;$installed=true;$history=[];$result=null;
  ob_start();try{require dirname(__DIR__).'/views/orange_sms.php';return ob_get_clean();}catch(\Throwable $e){ob_end_clean();throw $e;}
 }
 $html=render(['sms.view','sms.manage','sms.test']);
 foreach(['name="client_id"','name="client_secret"','name="sender_mode"','action="/admin/sms/test"','name="csrf"','</html>'] as $marker)if(!str_contains($html,$marker))throw new \RuntimeException('Missing view marker '.$marker);
 $html=render(['sms.view']);
 foreach(['name="client_id"','name="client_secret"','action="/admin/sms/test"'] as $marker)if(str_contains($html,$marker))throw new \RuntimeException('Unauthorized form rendered');
 echo "Orange SMS view: full rendering, credentials, sender modes and permissions OK\n";
}
