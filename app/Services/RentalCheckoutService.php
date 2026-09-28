<?php
namespace App\Services;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalCheckoutService {
 public function begin(array $input):string {
  $name=trim((string)($input['name']??''));$email=(string)($input['email']??'');
  $phone=preg_replace('/\s+/', '',(string)($input['phone']??''));
  $station=trim((string)($input['station_code']??''));$channel=(string)($input['payout_channel']??'');
  $batteryId=filter_var($input['battery_id']??null,FILTER_VALIDATE_INT);
  if($name===''||strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\+?[0-9]{10,16}$/',$phone)||$station===''||strlen($station)>120||!$batteryId||!in_array($channel,['WAVECI','MOMOCI','OMCIV','FLOOZ'],true))throw new \InvalidArgumentException('Informations de location invalides');
  $db=App::db();$db->beginTransaction();
  try {
   $q=$db->prepare("SELECT * FROM batteries WHERE id=? AND status='available' FOR UPDATE");$q->execute([$batteryId]);$battery=$q->fetch();
   if(!$battery)throw new \RuntimeException('Batterie indisponible');
   // Station ownership must be checked with the HeyCharge Open API before PUBLIC_RENTALS_ENABLED is enabled.
   $price=$db->query('SELECT * FROM pricing WHERE id=1')->fetch();$reference='TBP-'.strtoupper(bin2hex(random_bytes(8)));
   $db->prepare("INSERT INTO rentals(reference,battery_id,station_code,payment_environment,customer_name,customer_email,customer_phone,payout_channel,rental_fee,deposit,late_percent,duration_minutes,status,reservation_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'pending_payment',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE))")
      ->execute([$reference,$batteryId,$station,IntegrationSettings::all()['paiementpro'],$name,$email,$phone,$channel,(int)$price['rental_fee'],(int)($battery['deposit_override']??$price['default_deposit']),(int)$price['late_percent'],(int)$price['duration_minutes']]);
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
     $db->prepare("UPDATE batteries SET status='available' WHERE id=? AND status='reserved'")->execute([$r['battery_id']]);
    }
    $db->commit();
   }catch(\Throwable $inner){if($db->inTransaction())$db->rollBack();error_log($inner);}
   throw $e;
  }
 }
}
