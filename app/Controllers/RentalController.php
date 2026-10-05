<?php
namespace App\Controllers;
use App\Core\App;
use App\Repositories\RentalRepository;
use App\Services\Audit;
final class RentalController {
 public function index():void {
  $station=(string)($_GET['station']??'');
  if($station!==''){$this->station($station);return;}
  $db=App::db();
  $stations=$db->query("SELECT imei,label FROM stations WHERE enabled=1 ORDER BY label,imei")->fetchAll();
  $selected=(string)($_GET['kiosk']??'');
  if($selected==='')$selected=(string)($stations[0]['imei']??'');
  $matched=false;foreach($stations as $row)if($row['imei']===$selected)$matched=true;
  if(!$matched)$selected='';
  $qr=null;
  if($selected!==''){
   $url=rtrim(App::env('APP_URL'),'/').'/rent?station='.rawurlencode($selected);
   try {$qr=\App\Services\StationQr::svg($url);}catch(\InvalidArgumentException $e){error_log($e);}
  }
  App::view('kiosk',compact('stations','selected','qr'));
 }
 public function station(string $imei):void {
  if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){http_response_code(404);return;}
  $db=App::db();$q=$db->prepare('SELECT imei,label,enabled,status,last_seen_at FROM stations WHERE imei=?');$q->execute([$imei]);$station=$q->fetch();
  if(!$station||(int)$station['enabled']!==1){http_response_code(404);return;}
  $inventoryError=false;
  $fresh=$station['last_seen_at']&&strtotime($station['last_seen_at'].' UTC')>=time()-20&&$station['status']==='online';
  if(\App\Services\IntegrationSettings::all()['heycharge']==='normal'&&!$fresh){
   try {$inventoryError=!(new \App\Services\StationFleetService)->syncPublic($imei);}
   catch(\Throwable $e){error_log('Public station inventory '.$imei.': '.$e->getMessage());$inventoryError=true;}
  }
  $prices=App::db()->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $modes=\App\Services\IntegrationSettings::all();
  $q=$db->prepare("SELECT id,serial,slot_id,battery_capacity,deposit_override,station_imei FROM batteries WHERE station_imei=? AND status='available' AND slot_id IS NOT NULL AND battery_capacity>=70 ORDER BY CAST(slot_id AS UNSIGNED),slot_id");$q->execute([$imei]);
  $batteries=$inventoryError?[]:$q->fetchAll();
  App::view('rent',['prices'=>$prices,'batteries'=>$batteries,'station'=>$station,'inventoryError'=>$inventoryError,'checkoutEnabled'=>$this->checkoutEnabled()&&!$inventoryError,'depositEnabled'=>(int)$prices['deposit_enabled']===1]);
 }
 private function checkoutEnabled():bool {
  if(App::env('PUBLIC_RENTALS_ENABLED')!=='1')return false;
  $modes=\App\Services\IntegrationSettings::all();
  if($modes['heycharge']==='simulation')return App::env('SIMULATED_RENTALS_ENABLED')==='1' && ($modes['paiementpro']==='production'?App::env('PAIEMENTPRO_MERCHANT_ID')!=='' : App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='');
  return (new \App\Services\PaymentVerification)->ready() && ($modes['paiementpro']==='production' ? App::env('PAIEMENTPRO_MERCHANT_ID')!=='' : App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='') && (new \App\Services\HeyChargeOpenApi)->configured();
 }
 public function create():void {
  if(!$this->checkoutEnabled()){
   http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Les locations seront disponibles prochainement.';return;
  }
  try {$url=(new \App\Services\RentalCheckoutService)->begin($_POST);Audit::event('rental.checkout_started','station',(string)($_POST['station_code']??''));App::redirect($url);}
  catch(\App\Services\CheckoutConflict $e){
   http_response_code(409);header('Retry-After: 3');header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
   $station=trim((string)($_POST['station_code']??''));$back='/rent?station='.rawurlencode($station);
   echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Location · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="kiosk"><main class="kiosk-shell" style="max-width:700px;margin:8vh auto;padding:32px"><a class="kiosk-logo" href="/">THIEBA<span>POWER</span></a><h1>Votre demande de location</h1><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p><a class="touch-button outline" href="/my-rentals">Consulter Mes locations</a><a class="touch-button outline" href="'.htmlspecialchars($back,ENT_QUOTES,'UTF-8').'">Actualiser les batteries disponibles</a></main></body></html>';
  }
  catch(\InvalidArgumentException $e){http_response_code(422);echo 'Informations de location invalides';}
  catch(\Throwable $e){error_log($e);http_response_code(503);echo 'La location n’a pas pu être finalisée. Rechargez la page avant de réessayer. Référence de diagnostic : '.Audit::requestId();}
 }
 public function status():void {
  $reference=$_GET['reference']??'';
  $r=(new RentalRepository)->find($reference);
  if(!$r){http_response_code(404);return;}
  header('Content-Type: application/json');
  header('Cache-Control: no-store');
  $data=['reference'=>$r['reference'],'status'=>$r['status'],'due_at'=>$r['due_at']];
  $token=(string)($_GET['token']??'');
  if($token!=='' && !empty($r['checkout_token']) && hash_equals($r['checkout_token'],$token)) {
   $data['caution']=\App\Services\DepositWallet::remaining($r);
   if($r['status']==='returned'){ $q=App::db()->prepare('SELECT status,refund_amount FROM deposit_settlements WHERE rental_id=?');$q->execute([$r['id']]);$data['settlement']=$q->fetch()?:null;}
  }
  echo json_encode($data);
 }
}
