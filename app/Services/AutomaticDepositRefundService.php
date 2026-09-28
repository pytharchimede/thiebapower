<?php
namespace App\Services;
use App\Core\App;
use App\Models\Rental;
final class AutomaticDepositRefundService {
 /** Only call this method after an authenticated HeyCharge return event has been matched to the active rental. */
 public function recordVerifiedReturn(int $rentalId,\DateTimeImmutable $returnedAt):int {
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
   $id=(int)$db->lastInsertId();$db->commit();return $id;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 /** Called by a trusted background worker after recordVerifiedReturn; never from a browser return URL. */
 public function dispatch(int $settlementId):void {
  $db=App::db();$db->beginTransaction();
  try {
   $s=$db->prepare('SELECT s.*,r.customer_name,r.customer_phone,r.payout_channel,r.payment_environment,r.reference FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE s.id=? FOR UPDATE');
   $s->execute([$settlementId]);$row=$s->fetch();
   if(!$row||$row['status']!=='pending'||(int)$row['refund_amount']<=0){$db->commit();return;}
   if(!$row['payout_channel'])throw new \RuntimeException('Canal de restitution manquant');
   $reference='TBP-REFUND-'.$settlementId;
   $db->prepare("UPDATE deposit_settlements SET status='processing',provider_reference=?,sent_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'")->execute([$reference,$settlementId]);
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  // The reference and processing state are committed before the network request.
  // Never retry an unknown response automatically: reconcile it with Paiement Pro first.
  try {
   $request=(new PaiementProPayoutService)->prepare($reference,(int)$row['refund_amount'],$row['payout_channel'],$row['customer_phone'],$row['customer_name'],$row['payment_environment']);
   $client=new \SoapClient($request['wsdl'],['connection_timeout'=>10,'cache_wsdl'=>WSDL_CACHE_NONE]);
   $reply=$client->initTransact($request['params']);
   // An initiation response alone does not prove the beneficiary received the funds.
   $session=(string)($reply->sessionid??'');
   if(strtoupper((string)($reply->status??''))==='FAILED'){$db->prepare("UPDATE deposit_settlements SET status='failed' WHERE id=? AND status='processing'")->execute([$settlementId]);error_log('Thiebapower payout rejected '.$reference.' code '.(string)($reply->code??''));}
   elseif($session!==''){$db->prepare('UPDATE deposit_settlements SET provider_session_id=? WHERE id=?')->execute([$session,$settlementId]);}
   else {$db->prepare("UPDATE deposit_settlements SET status='unknown' WHERE id=?")->execute([$settlementId]);}
  }catch(\Throwable $e){error_log('Thiebapower payout outcome unknown '.$reference.': '.$e->getMessage());$db->prepare("UPDATE deposit_settlements SET status='unknown' WHERE id=? AND status='processing'")->execute([$settlementId]);}
 }
 /** Poll the provider for a payout with a known session; verify every amount and reference before closing it. */
 public function reconcile(int $settlementId):bool {
  $db=App::db();$s=$db->prepare('SELECT s.*,r.payment_environment FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE s.id=?');$s->execute([$settlementId]);$row=$s->fetch();
  if(!$row||$row['status']!=='processing'||!$row['provider_session_id'])return false;
  $reply=(new PaiementProPayoutService)->status($row['provider_session_id'],$row['payment_environment']);
  if((string)($reply->status??'')==='FAILED'){
   $db->prepare("UPDATE deposit_settlements SET status='failed' WHERE id=? AND status='processing'")->execute([$settlementId]);return false;
  }
  if((string)($reply->status??'')!=='SUCCESS')return false;
  if(!hash_equals((string)$row['provider_reference'],(string)($reply->referenceNo??''))||(int)($reply->amount??-1)!==(int)$row['refund_amount'])throw new \RuntimeException('Discordance de reversement fournisseur');
  $db->prepare("UPDATE deposit_settlements SET status='refunded' WHERE id=? AND status='processing'")->execute([$settlementId]);
  return true;
 }
}
