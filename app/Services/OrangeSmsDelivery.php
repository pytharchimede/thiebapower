<?php
namespace App\Services;
use App\Core\App;
final class OrangeSmsDelivery
{
 public const STATUSES=['DeliveredToTerminal','DeliveredToNetwork','DeliveryUncertain','DeliveryImpossible','MessageWaiting'];
 public static function authorized(string $token,string $ip): bool
 {
  $expected=App::env('ORANGE_SMS_CALLBACK_TOKEN');
  $ips=array_filter(array_map('trim',explode(',',App::env('ORANGE_SMS_CALLBACK_IPS'))));
  return strlen($expected)>=32 && hash_equals($expected,$token) && in_array($ip,$ips,true);
 }
 public static function parse(string $raw): array
 {
  if(strlen($raw)>16384)throw new \InvalidArgumentException('Notification trop volumineuse.');
  $n=json_decode($raw,true,16,JSON_THROW_ON_ERROR)['deliveryInfoNotification']??[];
  $id=$n['callbackData']??null;$info=$n['deliveryInfo']??[];$status=$info['deliveryStatus']??null;
  if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,190}$/D',$id)||!in_array($status,self::STATUSES,true)||!is_string($info['address']??null))throw new \InvalidArgumentException('Notification invalide.');
  $phone=OrangeSmsClient::phone(preg_replace('/^tel:/','',$info['address']));
  return ['resource_id'=>$id,'status'=>$status,'recipient_masked'=>'+225******'.substr($phone,-4)];
 }
 public static function record(array $n): void
 {
  $db=App::db();$db->beginTransaction();
  try {
   // Serialize receipts for a resource. A confirmed terminal delivery never regresses.
   $q=$db->prepare('SELECT id,recipient_masked FROM orange_sms_logs WHERE resource_id=? FOR UPDATE');$q->execute([$n['resource_id']]);$logs=$q->fetchAll();
   foreach($logs as $log)if($log['recipient_masked']!==$n['recipient_masked'])throw new \InvalidArgumentException('Destinataire incohérent.');
   $db->prepare('INSERT IGNORE INTO orange_sms_receipts(resource_id,delivery_status,recipient_masked) VALUES(?,?,?)')->execute([$n['resource_id'],$n['status'],$n['recipient_masked']]);
   $q=$db->prepare("SELECT delivery_status FROM orange_sms_receipts WHERE resource_id=? ORDER BY (delivery_status='DeliveredToTerminal') DESC,id DESC LIMIT 1");$q->execute([$n['resource_id']]);$status=$q->fetchColumn();
   // Keep API acceptance separate from delivery status.
   $db->prepare('UPDATE orange_sms_logs SET delivery_status=?,delivery_received_at=UTC_TIMESTAMP() WHERE resource_id=?')->execute([$status,$n['resource_id']]);
   $db->commit();
  }catch(\Throwable $e){$db->rollBack();throw $e;}
 }
}
