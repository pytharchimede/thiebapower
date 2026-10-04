<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Audit;
use App\Services\RentalBilling;
final class CustomerExperienceController
{
 public function index():void {header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');App::view('my_rentals');}
 private function input():?array {
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
  // JSON-only POST prevents form-based cross-site mutations; tokens never appear in URLs or visit logs.
  if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){http_response_code(415);echo '{"error":"Requête invalide"}';return null;}
  $raw=file_get_contents('php://input',false,null,0,12000);$input=json_decode($raw,true);
  if(!is_array($input)){http_response_code(422);echo '{"error":"Requête invalide"}';return null;}return $input;
 }
 public function snapshot():void {
  $input=$this->input();if($input===null)return;
  $tokens=$input['tokens']??[];
  if(!is_array($tokens)||count($tokens)>30){http_response_code(422);echo '{"error":"Historique trop long"}';return;}
  $tokens=array_values(array_unique(array_filter($tokens,static fn($v)=>is_string($v)&&preg_match('/^[a-f0-9]{32}$/D',$v))));
  if(!$tokens){echo '{"rentals":[]}';return;}
  $q=App::db()->prepare('SELECT r.*,b.serial battery_serial,s.label station_label,d.status refund_status,d.refund_amount,t.status support_status,t.resolution support_resolution FROM rentals r JOIN batteries b ON b.id=r.battery_id LEFT JOIN stations s ON s.imei=r.station_code LEFT JOIN deposit_settlements d ON d.rental_id=r.id LEFT JOIN rental_support_requests t ON t.id=(SELECT MAX(last.id) FROM rental_support_requests last WHERE last.rental_id=r.id) WHERE r.checkout_token IN ('.implode(',',array_fill(0,count($tokens),'?')).') ORDER BY r.created_at DESC');$q->execute($tokens);$rows=[];
  foreach($q->fetchAll() as $r){
   $end=$r['returned_at']?strtotime($r['returned_at'].' UTC'):time();$late=$r['due_at']?max(0,$end-strtotime($r['due_at'].' UTC')):0;
   $rows[]=['token'=>$r['checkout_token'],'reference'=>$r['reference'],'status'=>$r['status'],'station'=>$r['station_label']?:$r['station_code'],'battery'=>$r['battery_serial'],'created_at'=>$r['created_at'],'started_at'=>$r['started_at'],'due_at'=>$r['due_at'],'returned_at'=>$r['returned_at'],'rental_fee'=>(int)$r['rental_fee'],'deposit'=>(int)$r['deposit'],'duration_minutes'=>(int)$r['duration_minutes'],'billing'=>RentalBilling::calculate($r,$late),'support_status'=>$r['support_status'],'support_resolution'=>$r['support_resolution'],'refund_status'=>$r['refund_status'],'refund_amount'=>$r['refund_amount']===null?null:(int)$r['refund_amount']];
  }
  echo json_encode(['rentals'=>$rows],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
 }
 public function support():void {
  $input=$this->input();if($input===null)return;$token=$input['token']??'';$issue=$input['issue']??'';$message=trim((string)($input['message']??''));
  if(!is_string($token)||!preg_match('/^[a-f0-9]{32}$/D',$token)||!in_array($issue,['release','return','payment','other'],true)||strlen($message)<5||strlen($message)>2000){http_response_code(422);echo '{"error":"Précisez le problème rencontré (5 à 2 000 caractères)."}';return;}
  $db=App::db();$db->beginTransaction();
  try{
   $q=$db->prepare('SELECT id,reference FROM rentals WHERE checkout_token=? FOR UPDATE');$q->execute([$token]);$r=$q->fetch();
   if(!$r){$db->rollBack();http_response_code(404);echo '{"error":"Location introuvable"}';return;}
   $q=$db->prepare("SELECT id FROM rental_support_requests WHERE rental_id=? AND (status='open' OR created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)) LIMIT 1");$q->execute([$r['id']]);
   if($q->fetchColumn()){$db->rollBack();http_response_code(409);echo '{"error":"Une demande existe déjà. Notre équipe peut consulter votre location."}';return;}
   $db->prepare('INSERT INTO rental_support_requests(rental_id,issue,message) VALUES(?,?,?)')->execute([$r['id'],$issue,$message]);$db->commit();Audit::event('support.requested','rental',$r['reference']);http_response_code(201);echo '{"message":"Demande enregistrée. Notre équipe dispose des informations de votre location pour vous contacter."}';
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
