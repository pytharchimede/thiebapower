<?php
namespace App\Services;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalCheckoutService {
 public function begin(array $input):string {
  $name=trim((string)($input['name']??''));$email='';
  $phone=preg_replace('/\s+/', '',(string)($input['phone']??''));
  $station=trim((string)($input['station_code']??''));$channel=(string)($input['payout_channel']??'');
  $depositEnabled=(int)(App::db()->query('SELECT deposit_enabled FROM pricing WHERE id=1')->fetchColumn())===1;
  $batteryId=filter_var($input['battery_id']??null,FILTER_VALIDATE_INT);
  if($name===''||strlen($name)>160||!preg_match('/^\+?[0-9]{10,16}$/',$phone)||$station===''||strlen($station)>120||!$batteryId||($depositEnabled&&!in_array($channel,['WAVECI','MOMOCI','OMCIV','FLOOZ'],true)))throw new \InvalidArgumentException('Informations de location invalides');
  // Validate the required provider contact before reserving a battery.
  PaiementProService::customerEmail(['customer_email'=>'']);
  $db=App::db();
  $physical=IntegrationSettings::all()['heycharge']==='normal';
  if($physical){
   $q=$db->prepare('SELECT serial,slot_id,station_imei FROM batteries WHERE id=?');$q->execute([$batteryId]);$candidate=$q->fetch();
   if(!$candidate||$candidate['station_imei']!==$station||!$candidate['slot_id'])throw new \RuntimeException('Station ou batterie indisponible');
   $remote=(new HeyChargeOpenApi)->station($station);
   if(($remote['imei']??'')!==$station||!StationFleetService::availableAt($remote,$candidate['serial'],$candidate['slot_id']))throw new \RuntimeException('Batterie indisponible sur la station');
  }
  $db->beginTransaction();
  try {
   $q=$db->prepare("SELECT * FROM batteries WHERE id=? AND status='available' FOR UPDATE");$q->execute([$batteryId]);$battery=$q->fetch();
   if(!$battery)throw new \RuntimeException('Batterie indisponible');
   if($physical){
    $q=$db->prepare("SELECT enabled FROM stations WHERE imei=?");$q->execute([$station]);
    if((int)$q->fetchColumn()!==1||$battery['station_imei']!==$station||!$battery['slot_id']||$battery['slot_id']!==$candidate['slot_id']||$battery['serial']!==$candidate['serial'])throw new \RuntimeException('Station ou batterie indisponible');
   }
   $price=$db->query('SELECT * FROM pricing WHERE id=1')->fetch();$reference='TBP-'.strtoupper(bin2hex(random_bytes(8)));
   $db->prepare("INSERT INTO rentals(reference,battery_id,station_code,payment_environment,customer_name,customer_email,customer_phone,payout_channel,rental_fee,deposit,late_percent,duration_minutes,status,reservation_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'pending_payment',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))")
      ->execute([$reference,$batteryId,$station,IntegrationSettings::all()['paiementpro'],$name,$email,$phone,$channel,(int)$price['rental_fee'],($depositEnabled?(int)($battery['deposit_override']??$price['default_deposit']):0),(int)$price['late_percent'],(int)$price['duration_minutes']]);
   $db->prepare("UPDATE batteries SET status='reserved' WHERE id=?")->execute([$batteryId]);$db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  $session=null;
  try {
   $r=(new RentalRepository)->find($reference);
   $session=(new PaiementProService)->initiateSession($r);
   $db->prepare('UPDATE rentals SET payment_session_id=? WHERE reference=?')->execute([$session['id'],$reference]);
   return $session['url'];
  }catch(\Throwable $e){
   if($session!==null){error_log('Payment session created but could not be persisted for '.$reference.': '.$e->getMessage());throw $e;}
   $db->beginTransaction();
   try {
    $q=$db->prepare('SELECT * FROM rentals WHERE reference=? FOR UPDATE');$q->execute([$reference]);$r=$q->fetch();
    if($r && $r['status']==='pending_payment'){
     $db->prepare("UPDATE rentals SET status='payment_failed' WHERE id=?")->execute([$r['id']]);
    }
    $db->commit();
   }catch(\Throwable $inner){if($db->inTransaction())$db->rollBack();error_log($inner);}
   throw $e;
  }
 }
}
