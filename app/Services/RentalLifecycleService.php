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
  try {(new HeyChargeOpenApi)->release($r['station_code'],$this->batterySerial((int)$r['battery_id']),$reference);}
  catch(\Throwable $e){error_log('Station release outcome unknown '.$reference.': '.$e->getMessage());$db->prepare("UPDATE rentals SET status='release_failed' WHERE id=? AND status='releasing'")->execute([$r['id']]);}
 }
 private function batterySerial(int $id):string {$q=App::db()->prepare('SELECT serial FROM batteries WHERE id=?');$q->execute([$id]);return (string)$q->fetchColumn();}
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
