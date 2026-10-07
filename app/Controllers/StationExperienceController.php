<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\StationProfile;
use App\Services\PlaceSearch;
final class StationExperienceController
{
 public function profile():void {
  Auth::requirePermission('stations.view');
  $q=App::db()->prepare('SELECT s.*,p.address,p.latitude,p.longitude,p.venue_type,p.opening_hours,p.manager_name,p.manager_phone,p.manager_email,p.manager_notes,p.investment FROM stations s LEFT JOIN station_profiles p ON p.station_imei=s.imei WHERE s.imei=?');
  $q->execute([(string)($_GET['imei']??'')]);$station=$q->fetch();
  if(!$station){http_response_code(404);echo 'Station introuvable';return;}
  App::view('station_profile',compact('station'));
 }
 public function save():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=(string)($_POST['imei']??'');
  try{$profile=StationProfile::validate($_POST);}catch(\InvalidArgumentException $e){http_response_code(422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');return;}
  $db=App::db();$db->beginTransaction();
  try{
   $q=$db->prepare('SELECT imei FROM stations WHERE imei=? FOR UPDATE');$q->execute([$imei]);
   if(!$q->fetchColumn()){$db->rollBack();http_response_code(404);return;}
   $db->prepare('UPDATE stations SET label=? WHERE imei=?')->execute([$profile['label'],$imei]);unset($profile['label']);
   $keys=array_keys($profile);$columns=implode(',',$keys);$updates=implode(',',array_map(static fn($key)=>$key.'=VALUES('.$key.')',$keys));
   $db->prepare('INSERT INTO station_profiles(station_imei,'.$columns.') VALUES('.implode(',',array_fill(0,count($keys)+1,'?')).') ON DUPLICATE KEY UPDATE '.$updates)->execute(array_merge([$imei],array_values($profile)));
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  Audit::event('station.profile_saved','station',$imei);App::redirect('/admin/stations/profile?imei='.rawurlencode($imei).'&saved=1');
 }
 public function places():void {
  Auth::requirePermission('fleet.manage');
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
  $now=microtime(true);$last=(float)($_SESSION['place_search_at']??0);
  if($now-$last<0.7){http_response_code(429);header('Retry-After: 1');echo '{"error":"Veuillez patienter un instant."}';return;}
  $_SESSION['place_search_at']=$now;session_write_close();
  try{echo json_encode(['results'=>(new PlaceSearch)->search(trim((string)($_GET['q']??'')))],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);}
  catch(\InvalidArgumentException $e){http_response_code(422);echo '{"error":"Tapez au moins trois caractères."}';}
  catch(\Throwable $e){error_log('Place search: '.$e->getMessage());http_response_code(503);echo '{"error":"Recherche indisponible. Vous pouvez cliquer sur la carte pour placer la station."}';}
 }
 public function map():void {App::view('station_map');}
 public function publicStations():void {
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: public, max-age=30');
  $rows=App::db()->query("SELECT s.imei,s.label,s.enabled,s.status,s.last_seen_at,p.address,p.latitude,p.longitude,p.venue_type,p.opening_hours,p.manager_name,p.manager_phone,COALESCE(b.available_count,0) available_count FROM stations s LEFT JOIN station_profiles p ON p.station_imei=s.imei LEFT JOIN (SELECT station_imei,COUNT(*) available_count FROM batteries WHERE status='available' AND slot_id IS NOT NULL AND battery_capacity>=70 GROUP BY station_imei) b ON b.station_imei=s.imei ORDER BY s.enabled DESC,s.label,s.imei")->fetchAll();
  $visible=\App\Services\PublicExperienceSettings::all()['visible'];
  foreach($rows as &$row){if(!$visible['manager_name'])$row['manager_name']='';if(!$visible['manager_phone'])$row['manager_phone']='';}unset($row);
  echo json_encode(['stations'=>array_map([StationProfile::class,'publicFields'],$rows)],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
 }
}
