<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\StationFleetService;
use App\Services\RentalLifecycleService;
use App\Services\HeyChargeOpenApi;
use App\Services\IntegrationSettings;
use App\Services\HeyChargeAccountService;
use App\Services\StationQr;
use App\Services\StationLabelPdf;
use App\Services\StationLabelSettings;
final class StationController {
 public function callbackStatus():void {
  header('Content-Type: application/json; charset=utf-8');
  if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')http_response_code(400);
  echo json_encode(['code'=>($_SERVER['REQUEST_METHOD']??'GET')==='GET'?0:1,'message'=>($_SERVER['REQUEST_METHOD']??'GET')==='GET'?'HeyCharge callback ready':'Use /register, /return or /status']);
 }
 public function index():void {
  Auth::requirePermission('fleet.manage');
  $db=App::db();
  $stations=$db->query("SELECT s.*,COUNT(b.id) batteries_count,SUM(b.status='available') available_count,MIN(b.battery_capacity) minimum_capacity FROM stations s LEFT JOIN batteries b ON b.station_imei=s.imei GROUP BY s.imei ORDER BY s.imei")->fetchAll();
  $totals=$db->query('SELECT status,COUNT(*) quantity FROM batteries GROUP BY status')->fetchAll();
  App::view('stations',compact('stations','totals'));
 }
 public function discover():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=trim((string)($_POST['imei']??''));
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(422);echo 'Renseignez l’IMEI de la station à importer.';return;}
  try {$count=(new HeyChargeAccountService)->discover($imei);Audit::event('station.imported_by_imei','station',$imei,['new'=>$count]);}
  catch(\Throwable $e){error_log('HeyCharge station import: '.$e->getMessage());http_response_code(503);echo 'Impossible de lire cette station. Vérifiez son IMEI et son rattachement au compte HeyCharge.';return;}
  App::redirect('/admin/stations/detail?imei='.rawurlencode($imei));
 }
 public function labels():void {
  Auth::requirePermission('fleet.manage');
  $labels=$this->labelData();
  $margins=StationLabelSettings::load();
  App::view('station_labels',compact('labels','margins'));
 }
 public function labelsPdf():void {
  Auth::requirePermission('fleet.manage');
  $labels=$this->labelData();
  if(!$labels){http_response_code(404);echo 'Aucune station à imprimer';return;}
  try {$margins=isset($_GET['left'])?StationLabelSettings::fromCentimetres($_GET):StationLabelSettings::load();}
  catch(\InvalidArgumentException $e){http_response_code(422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');return;}
  $pdf=(new StationLabelPdf)->render($labels,$margins);
  header('Content-Type: application/pdf');
  header('Content-Disposition: '.(isset($_GET['preview'])?'inline':'attachment').'; filename="thiebapower-etiquettes-stations-a4.pdf"');
  header('Cache-Control: private, no-store');
  header('Content-Length: '.strlen($pdf));
  echo $pdf;
 }
 public function saveLabelSettings():void {
  Auth::requirePermission('fleet.manage',true);
  try {$margins=StationLabelSettings::fromCentimetres($_POST);StationLabelSettings::save($margins);}
  catch(\InvalidArgumentException $e){http_response_code(422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');return;}
  Audit::event('station.label_settings_saved','station','labels',$margins);
  $imei=(string)($_POST['imei']??'');
  App::redirect('/admin/stations/labels?saved=1'.($imei!==''?'&imei='.rawurlencode($imei):''));
 }
 private function labelData():array {
  $db=App::db();$imei=(string)($_GET['imei']??'');
  if($imei!==''&&!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(404);return [];}
  $q=$db->prepare("SELECT imei,label,enabled FROM stations WHERE (?='' OR imei=?) ORDER BY label,imei");$q->execute([$imei,$imei]);
  $stations=$q->fetchAll();
  if($imei!==''&&!$stations){http_response_code(404);return [];}
  $labels=[];$base=rtrim(App::env('APP_URL'),'/');
  foreach($stations as $station){
   try {$url=$base.'/rent?station='.rawurlencode($station['imei']);$station['qr']=StationQr::svg($url);$station['url']=$url;$labels[]=$station;}
   catch(\InvalidArgumentException $e){error_log('Station label '.$station['imei'].': '.$e->getMessage());}
  }
  return $labels;
 }
 public function detail():void {
  Auth::requirePermission('fleet.manage');
  $imei=(string)($_GET['imei']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(404);return;}
  $db=App::db();$q=$db->prepare('SELECT * FROM stations WHERE imei=?');$q->execute([$imei]);$station=$q->fetch();
  if(!$station){http_response_code(404);return;}
  $q=$db->prepare('SELECT * FROM batteries WHERE station_imei=? ORDER BY CAST(slot_id AS UNSIGNED),slot_id,serial');$q->execute([$imei]);$batteries=$q->fetchAll();
  $q=$db->prepare("SELECT battery_id,status,requested_at FROM manual_release_commands WHERE station_imei=? AND status IN ('requested','unknown') ORDER BY id DESC");$q->execute([$imei]);
  $openReleases=[];foreach($q->fetchAll() as $row)$openReleases[$row['battery_id']]=$row;
  $q=$db->prepare('SELECT battery_serial,slot_id,status,requested_at,confirmed_at FROM manual_release_commands WHERE station_imei=? ORDER BY id DESC LIMIT 20');$q->execute([$imei]);$manualReleases=$q->fetchAll();
  $q=$db->prepare('SELECT id,event_type,battery_serial,received_at FROM heycharge_events WHERE imei=? ORDER BY id DESC LIMIT 30');$q->execute([$imei]);$events=$q->fetchAll();
  $q=$db->prepare('SELECT reference,status,created_at,started_at,returned_at FROM rentals WHERE station_code=? ORDER BY id DESC LIMIT 20');$q->execute([$imei]);$rentals=$q->fetchAll();
  App::view('station_detail',compact('station','batteries','events','rentals','openReleases','manualReleases'));
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
 public function releaseBattery():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=(string)($_POST['imei']??'');$batteryId=filter_var($_POST['battery_id']??null,FILTER_VALIDATE_INT);
  $serial=(string)($_POST['confirm_serial']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)||!$batteryId||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$serial)||IntegrationSettings::all()['heycharge']!=='normal'){http_response_code(422);exit('Commande invalide');}
  $db=App::db();$q=$db->prepare('SELECT id,serial,slot_id,status FROM batteries WHERE id=? AND station_imei=?');$q->execute([$batteryId,$imei]);$battery=$q->fetch();
  if(!$battery||$battery['serial']!==$serial||!in_array($battery['status'],['available','maintenance','charging'],true)||!$battery['slot_id']){http_response_code(409);exit('Batterie indisponible pour une éjection manuelle');}
  try {
   $remote=(new HeyChargeOpenApi)->station($imei);
   if(($remote['imei']??'')!==$imei||!StationFleetService::contains($remote,$serial)||!StationFleetService::battery($remote,$serial)||
      (string)(StationFleetService::battery($remote,$serial)['slot_id']??'')!==$battery['slot_id'])throw new \RuntimeException('Batterie absente de cet emplacement');
  }catch(\Throwable $e){error_log('Manual release preflight: '.$e->getMessage());http_response_code(503);exit('État du terminal indisponible');}
  $db->beginTransaction();
  try {
   $q=$db->prepare('SELECT serial,slot_id,status FROM batteries WHERE id=? AND station_imei=? FOR UPDATE');$q->execute([$batteryId,$imei]);$current=$q->fetch();
   $q=$db->prepare("SELECT id FROM manual_release_commands WHERE battery_id=? AND status IN ('requested','unknown') LIMIT 1");$q->execute([$batteryId]);
   if(!$current||$current['serial']!==$serial||$current['slot_id']!==$battery['slot_id']||!in_array($current['status'],['available','maintenance','charging'],true)||$q->fetchColumn())throw new \LogicException('Commande déjà en cours ou batterie réservée');
   $db->prepare("UPDATE batteries SET status='maintenance' WHERE id=?")->execute([$batteryId]);
   $db->prepare('INSERT INTO manual_release_commands(battery_id,station_imei,battery_serial,slot_id,requested_by) VALUES(?,?,?,?,?)')->execute([$batteryId,$imei,$serial,$battery['slot_id'],Auth::id()]);
   $commandId=(int)$db->lastInsertId();$db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(409);exit('Éjection déjà en cours ou batterie indisponible');}
  Audit::event('battery.manual_release_requested','battery',$serial,['station'=>$imei,'slot'=>$battery['slot_id'],'command_id'=>$commandId]);
  try {(new HeyChargeOpenApi)->release($imei,$serial,$battery['slot_id']);}
  catch(\Throwable $e){
   error_log('Manual release '.$commandId.': '.$e->getMessage());
   $db->prepare("UPDATE manual_release_commands SET status='unknown' WHERE id=? AND status='requested'")->execute([$commandId]);
   Audit::event('battery.manual_release_unknown','battery',$serial,['command_id'=>$commandId]);
  }
  App::redirect('/admin/stations/detail?imei='.rawurlencode($imei));
 }
 public function confirmReinsertion():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=(string)($_POST['imei']??'');$batteryId=filter_var($_POST['battery_id']??null,FILTER_VALIDATE_INT);
  $serial=(string)($_POST['confirm_serial']??'');
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)||!$batteryId||!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$serial)||IntegrationSettings::all()['heycharge']!=='normal'){http_response_code(422);exit('Confirmation invalide');}
  try {
   $remote=(new HeyChargeOpenApi)->station($imei);
   $item=StationFleetService::battery($remote,$serial);
   if(($remote['imei']??'')!==$imei||!$item||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',(string)($item['slot_id']??'')))throw new \RuntimeException('Batterie non retrouvée dans ce terminal');
  }catch(\Throwable $e){error_log('Manual reinsertion verification: '.$e->getMessage());http_response_code(503);exit('Réinsertion non vérifiée auprès de HeyCharge');}
  $db=App::db();$db->beginTransaction();
  try {
   $q=$db->prepare('SELECT serial,status,station_imei,slot_id FROM batteries WHERE id=? FOR UPDATE');$q->execute([$batteryId]);$battery=$q->fetch();
   $q=$db->prepare("SELECT id FROM manual_release_commands WHERE battery_id=? AND station_imei=? AND battery_serial=? AND status IN ('requested','unknown') ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$batteryId,$imei,$serial]);$commandId=$q->fetchColumn();
   if(!$battery||$battery['serial']!==$serial||$battery['status']!=='maintenance'||$battery['station_imei']!==$imei||!$commandId)throw new \LogicException('Éjection non éligible à une réinsertion');
   $status=StationFleetService::rentable($item)?'available':'charging';
   $db->prepare('UPDATE batteries SET status=?,slot_id=?,battery_capacity=?,battery_abnormal=?,cable_abnormal=? WHERE id=?')
    ->execute([$status,(string)$item['slot_id'],min(100,max(0,(int)($item['battery_capacity']??0))),(int)($item['battery_abnormal']??0)?1:0,(int)($item['cable_abnormal']??0)?1:0,$batteryId]);
   $db->prepare("UPDATE manual_release_commands SET status='reinserted',confirmed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$commandId]);
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(409);exit('Réinsertion non éligible');}
  Audit::event('battery.manual_reinsertion_confirmed','battery',$serial,['station'=>$imei,'command_id'=>$commandId,'status'=>$status]);
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
