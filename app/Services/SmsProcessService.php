<?php
namespace App\Services;
use App\Core\App;
final class SmsProcessService
{
 public static function activeSettings(): ?array
 {
  if(!OrangeSmsSettings::serverEnabled())return null;$s=OrangeSmsSettings::all();return $s['enabled']&&$s['mode']==='production'?$s:null;
 }
 public static function queue(string $key,int $rentalId,string $suffix=''): void
 {
  // SMS must never cause a payment, release or return transaction to fail.
  try{
   if(!self::activeSettings())return;$t=SmsTemplates::all()[$key]??null;if(!$t||!$t['enabled'])return;
   $q=App::db()->prepare('SELECT payment_environment FROM rentals WHERE id=?');$q->execute([$rentalId]);if($q->fetchColumn()!=='production')return;
   App::db()->prepare("INSERT IGNORE INTO sms_outbox(template_key,rental_id,event_key) VALUES(?,?,?)")->execute([$key,$rentalId,hash('sha256',$key.':'.$rentalId.':'.$suffix)]);
  }catch(\Throwable $e){error_log('SMS process queue unavailable: '.$key);}
 }
 public static function variables(array $r,array $settlement=[]): array
 {
  $name=trim((string)$r['customer_name']);if($name==='')$name='client';
  $due=isset($r['due_at'])?new \DateTimeImmutable($r['due_at'],new \DateTimeZone('UTC')):null;
  return ['customer_name'=>$name,'reference'=>$r['reference'],'amount'=>(string)((int)$r['rental_fee']+(int)$r['deposit']),'duration_minutes'=>(string)$r['duration_minutes'],'due_time'=>$due?$due->setTimezone(new \DateTimeZone('Africa/Abidjan'))->format('H:i'):'--:--','remaining_minutes'=>$due?(string)max(0,(int)ceil(($due->getTimestamp()-time())/60)):'0','late_charge'=>(string)($r['late_charge']??0),'refund_amount'=>(string)($settlement['refund_amount']??0)];
 }
 public static function reminderEligible(array $r,int $now): bool
 {
  if($r['status']!=='active'||empty($r['due_at']))return false;
  $left=(new \DateTimeImmutable($r['due_at'],new \DateTimeZone('UTC')))->getTimestamp()-$now;
  return $left>240&&$left<=300;
 }
 public static function run(): array
 {
  $s=self::activeSettings();if(!$s)return ['disabled'=>true];$db=App::db();
  if((int)$db->query("SELECT GET_LOCK('thiebapower_sms_worker',0)")->fetchColumn()!==1)return ['busy'=>true];
  $counts=[];
  try{
   $templates=SmsTemplates::all();
   if($templates['reminder_5min']['enabled'])foreach($db->query("SELECT id,due_at FROM rentals WHERE status='active' AND due_at>DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 MINUTE) AND due_at<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) LIMIT 100")->fetchAll() as $r)self::queue('reminder_5min',(int)$r['id'],$r['due_at']);
   if($templates['rental_overdue']['enabled'])foreach($db->query("SELECT id,due_at FROM rentals WHERE status='active' AND due_at<=UTC_TIMESTAMP() ORDER BY due_at DESC LIMIT 100")->fetchAll() as $r)self::queue('rental_overdue',(int)$r['id'],$r['due_at']);
   // A crashed worker is ambiguous. Never retry potentially accepted SMS automatically.
   $db->exec("UPDATE sms_outbox SET state='unknown' WHERE state='processing' AND claimed_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)");
   $client=new OrangeSmsClient($s);
   foreach($db->query("SELECT * FROM sms_outbox WHERE state='queued' ORDER BY id LIMIT 20")->fetchAll() as $job){
    if(!self::activeSettings())break;
    $q=$db->prepare('SELECT * FROM rentals WHERE id=?');$q->execute([$job['rental_id']]);$r=$q->fetch();
    $template=SmsTemplates::all()[$job['template_key']]??null;
    $expired=(new \DateTimeImmutable($job['created_at'],new \DateTimeZone('UTC')))->getTimestamp()<time()-3600;
    $invalid=!$r||($r['payment_environment']??'')!=='production'||!$template||!$template['enabled']||$expired;
    $expected=['payment_pending'=>['pending_payment'],'payment_failed'=>['payment_failed'],'release_failed'=>['release_failed'],'rental_started'=>['active']];
    if(!$invalid&&isset($expected[$job['template_key']]))$invalid=!in_array($r['status'],$expected[$job['template_key']],true);
    if(!$invalid&&$job['template_key']==='reminder_5min')$invalid=!self::reminderEligible($r,time());
    if(!$invalid&&$job['template_key']==='rental_overdue')$invalid=$r['status']!=='active';
    if($invalid){$db->prepare("UPDATE sms_outbox SET state='skipped' WHERE id=? AND state='queued'")->execute([$job['id']]);continue;}
    $q=$db->prepare("UPDATE sms_outbox SET state='processing',claimed_at=UTC_TIMESTAMP() WHERE id=? AND state='queued'");$q->execute([$job['id']]);if($q->rowCount()!==1)continue;
    $state='failed';$http=null;$resource=null;$logId=null;
    try{
     $settlement=[];$q=$db->prepare('SELECT refund_amount,status FROM deposit_settlements WHERE rental_id=?');$q->execute([$r['id']]);$settlement=$q->fetch()?:[];
     if(($job['template_key']==='refund_pending' && !in_array($settlement['status']??'', ['pending','processing','initiated'],true)) || ($job['template_key']==='refund_confirmed' && (($settlement['status']??'')!=='refunded'||(int)($settlement['refund_amount']??0)<=0))) { $db->prepare("UPDATE sms_outbox SET state='skipped' WHERE id=?")->execute([$job['id']]); continue; }
     $text=SmsTemplates::render($job['template_key'],$template['content'],self::variables($r,$settlement));
     $phone=OrangeSmsClient::phone($r['customer_phone']);
     $db->prepare('INSERT INTO orange_sms_logs(operation,mode,recipient_masked) VALUES(?,?,?)')->execute([$job['template_key'],'production','+225******'.substr($phone,-4)]);$logId=(int)$db->lastInsertId();
     $reply=$client->send($phone,$text);$state=$reply['state'];$http=$reply['http_status'];$resource=$reply['resource_id'];
    }catch(\InvalidArgumentException $e){$state='failed';}catch(\Throwable $e){$state='unknown';}
    $db->prepare('UPDATE sms_outbox SET state=?,http_status=?,resource_id=? WHERE id=?')->execute([$state,$http,$resource,$job['id']]);
    if($logId!==null)$db->prepare('UPDATE orange_sms_logs SET state=?,http_status=?,resource_id=? WHERE id=?')->execute([$state,$http,$resource,$logId]);
    $counts[$state]=($counts[$state]??0)+1;usleep(250000);
   }
  }finally{$db->query("SELECT RELEASE_LOCK('thiebapower_sms_worker')");}
  return $counts;
 }
}
