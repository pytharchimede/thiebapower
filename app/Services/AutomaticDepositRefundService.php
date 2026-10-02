<?php
namespace App\Services;
use App\Core\App;
use App\Models\Rental;
use App\Services\PayoutApiAudit;
final class AutomaticDepositRefundService {
 private PaiementProPayoutService $payout;
 public function __construct(?PaiementProPayoutService $payout=null){$this->payout=$payout??new PaiementProPayoutService;}
 /** Only call this method after an authenticated HeyCharge return event has been matched to the active rental. */
 public function recordVerifiedReturn(int $rentalId,\DateTimeImmutable $returnedAt,?array $batteryLocation=null):int {
  $db=App::db();$db->beginTransaction();
  try {
   $s=$db->prepare('SELECT * FROM rentals WHERE id=? FOR UPDATE');$s->execute([$rentalId]);$r=$s->fetch();
   if(!$r)throw new \RuntimeException('Location introuvable');
   $existing=$db->prepare('SELECT id FROM deposit_settlements WHERE rental_id=?');$existing->execute([$rentalId]);
   if($id=$existing->fetchColumn()){$db->commit();return (int)$id;}
   if($r['status']!=='active'||!$r['due_at'])throw new \LogicException('Retour non éligible');
   $due=new \DateTimeImmutable($r['due_at'],new \DateTimeZone('UTC'));
   $late=max(0,$returnedAt->getTimestamp()-$due->getTimestamp());
   $deduction=Rental::due((int)$r['deposit'],(int)$r['late_percent'],$late);
   $refund=(int)$r['deposit']-$deduction;
   $db->prepare("UPDATE rentals SET status='returned',returned_at=?,late_charge=? WHERE id=?")
      ->execute([$returnedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$deduction,$rentalId]);
   $db->prepare('INSERT INTO deposit_settlements(rental_id,deduction,refund_amount,status) VALUES(?,?,?,?)')
      ->execute([$rentalId,$deduction,$refund,$refund===0?'refunded':'pending']);
   if($batteryLocation!==null){
    $q=$db->prepare("UPDATE batteries SET status=?,station_imei=?,slot_id=?,battery_capacity=?,battery_abnormal=?,cable_abnormal=? WHERE id=? AND status='rented'");
    $q->execute([$batteryLocation['status'],$batteryLocation['station_imei'],$batteryLocation['slot_id'],$batteryLocation['battery_capacity'],$batteryLocation['battery_abnormal'],$batteryLocation['cable_abnormal'],$r['battery_id']]);
   }else{
    $q=$db->prepare("UPDATE batteries SET status='available' WHERE id=? AND status='rented'");$q->execute([$r['battery_id']]);
   }
   if($q->rowCount()!==1)throw new \LogicException('Batterie non louée au moment du retour');
   $id=(int)$db->lastInsertId();$db->commit();
   Audit::event('rental.return_recorded','rental',(string)$rentalId,['deduction'=>$deduction,'refund_amount'=>$refund,'settlement_id'=>$id]);
   return $id;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 /** Called by a trusted background worker after recordVerifiedReturn; never from a browser return URL. */
 public function dispatch(int $settlementId):void {
  $db=App::db();$db->beginTransaction();
  try {
   $s=$db->prepare('SELECT s.*,r.customer_name,r.customer_phone,r.payout_channel,r.payment_environment,r.reference FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE s.id=? FOR UPDATE');
   $s->execute([$settlementId]);$row=$s->fetch();
   if(!$row||!PayoutResult::dispatchable((string)$row['status'])||(int)$row['refund_amount']<=0){$db->commit();return;}
   if(!$row['payout_channel'])throw new \RuntimeException('Canal de restitution manquant');
   $reference='TBP-REFUND-'.$settlementId;
   $db->prepare("UPDATE deposit_settlements SET status='processing',provider_reference=?,sent_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'")->execute([$reference,$settlementId]);
   $db->commit();
   Audit::event('payout.dispatch_started','settlement',(string)$settlementId,['reference'=>$reference,'amount'=>$row['refund_amount']]);
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  // The reference and processing state are committed before the network request.
  // Never retry an unknown response automatically: reconcile it with Paiement Pro first.
  try {
   $request=$this->payout->prepare($reference,(int)$row['refund_amount'],$row['payout_channel'],$row['customer_phone'],$row['customer_name'],$row['payment_environment']);
   PayoutApiAudit::request($reference,$request);
   $reply=$this->payout->initiate($request);
   PayoutApiAudit::record($reference,'init',$reply);
   Audit::event('payout.initiation_response','settlement',(string)$settlementId,['status'=>$reply->status??'','code'=>$reply->code??'']);
   // An initiation response alone does not prove the beneficiary received the funds.
   $outcome=PayoutResult::initiation($reply);
   if($outcome['state']==='failed'){$db->prepare("UPDATE deposit_settlements SET status='failed' WHERE id=? AND status='processing'")->execute([$settlementId]);error_log('Thiebapower payout rejected '.$reference.' code '.(string)($reply->code??''));}
   elseif($outcome['state']==='processing'){$db->prepare('UPDATE deposit_settlements SET provider_session_id=? WHERE id=?')->execute([$outcome['session'],$settlementId]);}
   else {$db->prepare("UPDATE deposit_settlements SET status=? WHERE id=? AND status='processing'")->execute([$outcome['state'],$settlementId]);}
  }catch(\Throwable $e){PayoutApiAudit::record($reference,'error',['exception'=>get_class($e),'description'=>$e->getMessage(),'faultcode'=>$e instanceof \SoapFault?$e->faultcode:'']);error_log('Thiebapower payout outcome unknown '.$reference.': '.$e->getMessage());$db->prepare("UPDATE deposit_settlements SET status='unknown' WHERE id=? AND status='processing'")->execute([$settlementId]);}
 }
 /** Poll the provider for a payout with a known session; verify every amount and reference before closing it. */
 public function reconcile(int $settlementId):bool {
  $db=App::db();$s=$db->prepare('SELECT s.*,r.payment_environment,r.payout_channel,r.customer_phone FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE s.id=?');$s->execute([$settlementId]);$row=$s->fetch();
  if(!$row||$row['status']!=='processing'||!$row['provider_session_id'])return false;
  $reply=$this->payout->status($row['provider_session_id'],$row['payment_environment'],$row['provider_reference']);
  PayoutApiAudit::record($row['provider_reference'],'status',$reply);
  $result=PayoutResult::finalStatus($reply,['sessionid'=>$row['provider_session_id'],'referenceNo'=>$row['provider_reference'],'amount'=>$row['refund_amount'],'currency'=>'XOF','channel'=>$row['payout_channel'],'payeeNo'=>PaiementProPayoutService::normalizePhone($row['customer_phone'])]);
  if($result==='failed'){
   $db->prepare("UPDATE deposit_settlements SET status='failed' WHERE id=? AND status='processing'")->execute([$settlementId]);return false;
  }
  if($result==='mismatch')throw new \RuntimeException('Discordance de reversement fournisseur (session, référence, montant, devise, canal ou bénéficiaire)');
  if($result!=='succeeded')return false;
  $db->prepare("UPDATE deposit_settlements SET status='refunded',confirmed_at=UTC_TIMESTAMP() WHERE id=? AND status='processing'")->execute([$settlementId]);
  return true;
 }
}
