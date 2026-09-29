<?php
namespace App\Services;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalLifecycleService {
 /** Must be called only after an authenticated Paiement Pro success notification. */
 public function confirmedPayment(string $reference):void {
  $db=App::db();$db->beginTransaction();
  try {
   $q=$db->prepare('SELECT * FROM rentals WHERE reference=? FOR UPDATE');$q->execute([$reference]);$r=$q->fetch();
   if(!$r||$r['status']!=='pending_payment'){$db->commit();return;}
   $db->prepare("UPDATE rentals SET status='releasing' WHERE id=?")->execute([$r['id']]);$db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  // A committed releasing state prevents duplicate commands on duplicate callbacks.
  // Reconcile an uncertain command with the station; never issue it twice blindly.
  try {
   $q=$db->prepare('SELECT serial,slot_id FROM batteries WHERE id=?');$q->execute([$r['battery_id']]);$battery=$q->fetch();
   if(!$battery||!$battery['slot_id'])throw new \RuntimeException('Emplacement inconnu');
   $station=(new HeyChargeOpenApi)->station($r['station_code']);
   $match=false;
   foreach($station['batteries']??[] as $item)if(($item['battery_id']??'')===$battery['serial']&&($item['slot_id']??'')===$battery['slot_id']&& (string)($item['battery_abnormal']??'0')==='0')$match=true;
   if(!$match)throw new \RuntimeException('Batterie absente ou défectueuse');
   $db->prepare('UPDATE rentals SET release_command_at=UTC_TIMESTAMP() WHERE id=?')->execute([$r['id']]);
   (new HeyChargeOpenApi)->release($r['station_code'],$battery['serial'],$battery['slot_id']);
  }catch(\Throwable $e){error_log('Station release outcome unknown '.$reference.': '.$e->getMessage());$db->prepare("UPDATE rentals SET status='release_failed' WHERE id=? AND status='releasing'")->execute([$r['id']]);}

 }
 public function reconcileStation(string $reference):void {
  $db=App::db();$q=$db->prepare('SELECT r.*,b.serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.reference=?');$q->execute([$reference]);$r=$q->fetch();
  if(!$r||!in_array($r['status'],['releasing','release_failed','active'],true))throw new \LogicException('Location non éligible');
  $station=(new HeyChargeOpenApi)->station($r['station_code']);
  if(($station['imei']??'')!==$r['station_code']||!isset($station['batteries']))throw new \RuntimeException('Réponse station invalide');
  $present=StationFleetService::contains($station,$r['serial']);
  if(!$present && $r['status']!=='active' && $r['release_command_at'])$this->confirmedPhysicalRelease($reference,new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
  if($r['status']==='active'){
   $returned=$present;
   if(!$returned){
    $candidate=$db->prepare("SELECT DISTINCT e.imei FROM heycharge_events e JOIN stations s ON s.imei=e.imei WHERE e.event_type='return' AND e.battery_serial=? AND e.received_at>=? AND s.enabled=1 ORDER BY e.imei LIMIT 20");
    $candidate->execute([$r['serial'],$r['started_at']]);
    foreach($candidate->fetchAll() as $row){
     $remote=(new HeyChargeOpenApi)->station($row['imei']);
     if(($remote['imei']??'')===$row['imei'] && StationFleetService::contains($remote,$r['serial'])){$returned=true;break;}
    }
   }
   if($returned)$this->confirmedReturn((int)$r['id'],new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
  }
  Audit::event('station.reconciled','rental',$reference,['battery_present'=>$present]);
 }
 /** Must be called only after a verified physical release event from HeyCharge. */
 public function confirmedPhysicalRelease(string $reference,\DateTimeImmutable $releasedAt):void {
  $db=App::db();$db->beginTransaction();
  try {
   $q=$db->prepare('SELECT * FROM rentals WHERE reference=? FOR UPDATE');$q->execute([$reference]);$r=$q->fetch();
   if(!$r||!in_array($r['status'],['releasing','release_failed'],true)){$db->commit();return;}
   $start=$releasedAt->setTimezone(new \DateTimeZone('UTC'));
   $due=$start->modify('+'.(int)$r['duration_minutes'].' minutes');
   $db->prepare("UPDATE rentals SET status='active',started_at=?,due_at=? WHERE id=?")->execute([$start->format('Y-m-d H:i:s'),$due->format('Y-m-d H:i:s'),$r['id']]);
   $db->prepare("UPDATE batteries SET status='rented' WHERE id=?")->execute([$r['battery_id']]);$db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 /** Must be called only after a verified physical return event from HeyCharge. */
 public function confirmedReturn(int $rentalId,\DateTimeImmutable $returnedAt):int {
  $settlement=(new AutomaticDepositRefundService)->recordVerifiedReturn($rentalId,$returnedAt);
  $q=App::db()->prepare('SELECT battery_id FROM rentals WHERE id=?');$q->execute([$rentalId]);
  App::db()->prepare("UPDATE batteries SET status='available' WHERE id=? AND status='rented'")->execute([$q->fetchColumn()]);
  if(App::env('AUTOMATIC_REFUNDS_ENABLED')==='1')(new AutomaticDepositRefundService)->dispatch($settlement);
  return $settlement;
 }
}
