<?php
namespace App\Services;
use App\Core\App;
final class HeyChargeAccountService {
 /** The documented API retrieves a single station by IMEI; no account list exists. */
 public function discover(string $imei):int {
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei))throw new \InvalidArgumentException('Renseignez un IMEI valide.');
  $q=App::db()->prepare('SELECT 1 FROM stations WHERE imei=?');$q->execute([$imei]);$exists=(bool)$q->fetchColumn();
  (new StationFleetService)->sync($imei);
  return $exists?0:1;
 }
}
