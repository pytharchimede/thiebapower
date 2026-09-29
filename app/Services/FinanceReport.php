<?php
namespace App\Services;

use App\Core\App;

/** Provider money and physical cash remain separate throughout the report. */
final class FinanceReport {
 public function forPeriod(string $start,string $end):array {
  $db=App::db();$from=$start.' 00:00:00';$until=(new \DateTimeImmutable($end,new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d').' 00:00:00';
  // One verified successful callback per rental, even if Paiement Pro retries it.
  $q=$db->prepare("SELECT r.reference,r.rental_fee,r.deposit,MIN(n.received_at) paid_at
   FROM rentals r JOIN payment_notifications n ON n.rental_id=r.id
   WHERE r.payment_environment='production' AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0'
   GROUP BY r.id,r.reference,r.rental_fee,r.deposit HAVING paid_at>=? AND paid_at<? ORDER BY paid_at DESC");
  $q->execute([$from,$until]);$payments=$q->fetchAll();
  $q=$db->prepare("SELECT r.reference,s.refund_amount,s.deduction,s.confirmed_at FROM deposit_settlements s
   JOIN rentals r ON r.id=s.rental_id WHERE r.payment_environment='production' AND s.status='refunded'
   AND s.confirmed_at>=? AND s.confirmed_at<? ORDER BY s.confirmed_at DESC");
  $q->execute([$from,$until]);$refunds=$q->fetchAll();
  $q=$db->prepare('SELECT c.*,u.display_name actor FROM cash_entries c LEFT JOIN users u ON u.id=c.created_by WHERE c.occurred_at>=? AND c.occurred_at<? ORDER BY c.occurred_at DESC,c.id DESC');
  $q->execute([$from,$until]);$cash=$q->fetchAll();
  $q=$db->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END),0) FROM cash_entries WHERE occurred_at<?");
  $q->execute([$from]);$openingCash=(int)$q->fetchColumn();
  $sum=static fn(array $rows,string $key):int=>array_sum(array_map(static fn($r)=>(int)$r[$key],$rows));
  $cashIn=0;$cashOut=0;foreach($cash as $entry){if($entry['direction']==='in')$cashIn+=(int)$entry['amount'];else $cashOut+=(int)$entry['amount'];}
  $daily=[];
  $day=static function(string $date)use(&$daily):array { $key=substr($date,0,10);return $daily[$key]??['date'=>$key,'collected'=>0,'refunded'=>0,'cashIn'=>0,'cashOut'=>0]; };
  foreach($payments as $row){$entry=$day($row['paid_at']);$entry['collected']+=(int)$row['rental_fee']+(int)$row['deposit'];$daily[$entry['date']]=$entry;}
  foreach($refunds as $row){$entry=$day($row['confirmed_at']);$entry['refunded']+=(int)$row['refund_amount'];$daily[$entry['date']]=$entry;}
  foreach($cash as $row){$entry=$day($row['occurred_at']);$entry[$row['direction']==='in'?'cashIn':'cashOut']+=(int)$row['amount'];$daily[$entry['date']]=$entry;}
  krsort($daily);
  return compact('payments','refunds','cash','cashIn','cashOut','daily')+[
   'rentalRevenue'=>$sum($payments,'rental_fee'),'depositsCollected'=>$sum($payments,'deposit'),
   'collected'=>$sum($payments,'rental_fee')+$sum($payments,'deposit'),
   'refunded'=>$sum($refunds,'refund_amount'),'deductions'=>$sum($refunds,'deduction'),
   'providerNet'=>$sum($payments,'rental_fee')+$sum($payments,'deposit')-$sum($refunds,'refund_amount'),
   'openingCash'=>$openingCash,'cashBalance'=>$openingCash+$cashIn-$cashOut,
  ];
 }
}
