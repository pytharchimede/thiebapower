<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\StationFleetService;
use App\Services\RentalLifecycleService;
final class StationController {
 public function save():void {
  Auth::requirePermission('fleet.manage',true);
  $imei=trim((string)($_POST['imei']??''));$label=trim((string)($_POST['label']??''));
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)||strlen($label)>160){http_response_code(422);exit('Station invalide');}
  App::db()->prepare("INSERT INTO stations(imei,label,enabled) VALUES(?,?,0) ON DUPLICATE KEY UPDATE label=VALUES(label)")->execute([$imei,$label]);
  Audit::event('station.saved','station',$imei);App::redirect('/admin#stations');
 }
 public function toggle():void {
  Auth::requirePermission('fleet.manage',true);$imei=(string)($_POST['imei']??'');$enabled=($_POST['enabled']??'')==='1'?1:0;
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(422);return;}
  App::db()->prepare('UPDATE stations SET enabled=? WHERE imei=?')->execute([$enabled,$imei]);
  Audit::event('station.enabled','station',$imei,['enabled'=>$enabled]);App::redirect('/admin#stations');
 }
 public function sync():void {
  Auth::requirePermission('fleet.manage',true);$imei=(string)($_POST['imei']??'');
  $q=App::db()->prepare('SELECT imei FROM stations WHERE imei=?');$q->execute([$imei]);
  if(!$q->fetchColumn()){http_response_code(404);return;}
  try {(new StationFleetService)->sync($imei);Audit::event('station.synced','station',$imei);}
  catch(\Throwable $e){error_log($e);http_response_code(503);exit('Station indisponible');}
  App::redirect('/admin#stations');
 }
 public function confirmPayment():void {
  Auth::requirePermission('rentals.manage',true);
  $reference=(string)($_POST['reference']??'');$proof=trim((string)($_POST['provider_proof']??''));
  if(!preg_match('/^TBP-[A-F0-9]{16}$/D',$reference)||strlen($proof)<6||strlen($proof)>120||($_POST['confirm_paid']??'')!=='1'){http_response_code(422);exit('Confirmation invalide');}
  $q=App::db()->prepare('SELECT * FROM rentals WHERE reference=?');$q->execute([$reference]);$r=$q->fetch();
  if(!$r||$r['status']!=='pending_payment'||!$r['payment_session_id']){http_response_code(409);exit('Location non éligible');}
  if(\App\Services\IntegrationSettings::all()['heycharge']!=='normal'){http_response_code(409);exit('Mode matériel inactif');}
  Audit::event('payment.manually_verified','rental',$reference,['provider_proof'=>$proof,'session'=>$r['payment_session_id']]);
  (new RentalLifecycleService)->confirmedPayment($reference);
  App::redirect('/admin#activity');
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
  if(!$q->fetchColumn()){http_response_code(404);echo json_encode(['code'=>1,'message'=>'unknown station']);return;}
  App::db()->prepare('INSERT INTO heycharge_events(event_type,imei,battery_serial,payload) VALUES(?,?,?,?)')
    ->execute([$type,$imei,substr((string)($data['battery_id']??''),0,100)?:null,json_encode($data)]);
  if($type==='status' && (string)($data['status']??'')==='0')App::db()->prepare("UPDATE stations SET status='offline' WHERE imei=?")->execute([$imei]);
  header('Content-Type: application/json');echo json_encode(['code'=>0,'message'=>'success']);
 }
 public function register():void {$this->event('register');}
 public function returned():void {$this->event('return');}
 public function status():void {$this->event('status');}
}
