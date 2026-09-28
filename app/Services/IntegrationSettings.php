<?php
namespace App\Services;
use App\Core\App;
final class IntegrationSettings {
 public static function all():array {
  $rows=App::db()->query('SELECT name, mode FROM integration_settings')->fetchAll();
  $modes=['paiementpro'=>'sandbox','heycharge'=>'simulation'];
  foreach($rows as $row) $modes[$row['name']]=$row['mode'];
  return $modes;
 }
 public static function set(string $provider,string $mode):void {
  $allowed=['paiementpro'=>['sandbox','production'],'heycharge'=>['simulation','normal']];
  if(!isset($allowed[$provider])||!in_array($mode,$allowed[$provider],true)) throw new \InvalidArgumentException('Mode invalide');
  App::db()->prepare('UPDATE integration_settings SET mode=? WHERE name=?')->execute([$mode,$provider]);
 }
}
