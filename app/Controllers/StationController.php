<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\StationFleetService;
use App\Services\RentalLifecycleService;
final class StationController {
 public function index():void {
  Auth::requirePermission('fleet.manage');
  $db=App::db();
  $stations=$db->query("SELECT s.*,COUNT(b.id) batteries_count,SUM(b.status='available') available_count,MIN(b.battery_capacity) minimum_capacity FROM stations s LEFT JOIN batteries b ON b.station_imei=s.imei GROUP BY s.imei ORDER BY s.imei")->fetchAll();
  $totals=$db->query('SELECT status,COUNT(*) quantity FROM batteries GROUP BY status')->fetchAll();
  App::view('stations',compact('stations','totals'));
 }
 public function detail():void {
  Auth::requirePermission('fleet.manage');
  $imei=(string)($_GET['imei']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(404);return;}
  $db=App::db();$q=$db->prepare('SELECT * FROM stations WHERE imei=?');$q->execute([$imei]);$station=$q->fetch();
  if(!$station){http_response_code(404);return;}
  $q=$db->prepare('SELECT * FROM batteries WHERE station_imei=? ORDER BY CAST(slot_id AS UNSIGNED),slot_id,serial');$q->execute([$imei]);$batteries=$q->fetchAll();
  $q=$db->prepare('SELECT id,event_type,battery_serial,received_at FROM heycharge_events WHERE imei=? ORDER BY id DESC LIMIT 30');$q->execute([$imei]);$events=$q->fetchAll();
  $q=$db->prepare('SELECT reference,status,created_at,started_at,returned_at FROM rentals WHERE station_code=? ORDER BY id DESC LIMIT 20');$q->execute([$imei]);$rentals=$q->fetchAll();
  App::view('station_detail',compact('station','batteries','events','rentals'));
 }
 public function save():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=trim((string)($_POST['imei']??''));$label=trim((string)($_POST['label']??''));
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)||strlen($label)>160){http_response_code(422);exit('Station invalide');}
  App::db()->prepare("INSERT INTO stations(imei,label,enabled) VALUES(?,?,0) ON DUPLICATE KEY UPDATE label=VALUES(label)")->execute([$imei,$label]);
  Audit::event('station.saved','station',$imei);App::redirect('/admin/stations/detail?imei='.rawurlencode($imei));
 }
 public function toggle():void {
  Auth::requirePermission('fleet.manage',true);$imei=(string)($_POST['imei']??'');$enabled=($_POST['enabled']??'')==='1'?1:0;
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(422);return;}
  App::db()->prepare('UPDATE stations SET enabled=? WHERE imei=?')->execute([$enabled,$imei]);
  Audit::event('station.enabled','station',$imei,['enabled'=>$enabled]);App::redirect('/admin/stations/detail?imei='.rawurlencode($imei));
 }
 public function sync():void {
  Auth::requirePermission('fleet.manage',true);$imei=(string)($_POST['imei']??'');
  $q=App::db()->prepare('SELECT imei FROM stations WHERE imei=?');$q->execute([$imei]);
  if(!$q->fetchColumn()){http_response_code(404);return;}
  try {(new StationFleetService)->sync($imei);Audit::event('station.synced','station',$imei);}
  catch(\Throwable $e){error_log($e);http_response_code(503);exit('Station indisponible');}
  App::redirect('/admin/stations/detail?imei='.rawurlencode($imei));
 }
 public function reconcile():void {
  Auth::requirePermission('rentals.manage',true);
  $reference=(string)($_POST['reference']??'');
  if(!preg_match('/^TBP-[A-F0-9]{16}$/D',$reference)){http_response_code(422);return;}
  try {(new RentalLifecycleService)->reconcileStation($reference);}
  catch(\Throwable $e){error_log($e);http_response_code(409);exit('Rapprochement matériel impossible');}
  App::redirect('/admin#activity');
 }
 /** Events have no documented authentication. Record only; never trust them to release or refund. */
 public function event(string $type):void {
  $raw=file_get_contents('php://input');$data=json_decode($raw,true);
  $imei=is_array($data)?(string)($data['imei']??''):'';
  if(strlen($raw)>65536||!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(400);echo json_encode(['code'=>1,'message'=>'invalid']);return;}
  $q=App::db()->prepare('SELECT 1 FROM stations WHERE imei=?');$q->execute([$imei]);
  if(!$q->fetchColumn()) {
   if($type!=='register'){http_response_code(404);echo json_encode(['code'=>1,'message'=>'unknown station']);return;}
   App::db()->prepare("INSERT IGNORE INTO stations(imei,iccid,enabled) VALUES(?,?,0)")->execute([$imei,substr((string)($data['iccid']??''),0,32)?:null]);
  }
  App::db()->prepare('INSERT INTO heycharge_events(event_type,imei,battery_serial,payload) VALUES(?,?,?,?)')
    ->execute([$type,$imei,substr((string)($data['battery_id']??''),0,100)?:null,json_encode($data)]);
  header('Content-Type: application/json');echo json_encode(['code'=>0,'message'=>'success']);
 }
 public function register():void {$this->event('register');}
 public function returned():void {$this->event('return');}
 public function status():void {$this->event('status');}
}
