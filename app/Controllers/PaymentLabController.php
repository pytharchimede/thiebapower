<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\PaiementProService;
use App\Services\PaiementProPayoutService;
use App\Services\IntegrationSettings;
use App\Services\PayoutApiAudit;
final class PaymentLabController {
 private function guard():void {
  if(!hash_equals(App::env('ADMIN_USERNAME','admin'),(string)($_SERVER['PHP_AUTH_USER']??''))||!password_verify((string)($_SERVER['PHP_AUTH_PW']??''),App::env('ADMIN_PASSWORD_HASH'))){header('WWW-Authenticate: Basic realm="Thiebapower"');http_response_code(401);exit('Authentification requise');}
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  if(!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Formulaire expiré');}
 }
 private function enabled(string $kind):void {
  $key=$kind==='payin'?'PAYMENT_LAB_PAYIN_ENABLED':'PAYMENT_LAB_PAYOUT_ENABLED';
  if(App::env($key)!=='1'&& !($kind==='payout'&&App::env('AUTOMATIC_REFUNDS_ENABLED')==='1')){http_response_code(403);exit('Essai financier désactivé');}
 }
 private function reference(string $kind):string {return 'TBP-TEST-'.$kind.'-'.strtoupper(bin2hex(random_bytes(7)));}
 private function fail(string $message):void {http_response_code(422);echo htmlspecialchars($message,ENT_QUOTES,'UTF-8');}
 public function payin():void {
  $this->guard();$this->enabled('payin');$name=trim((string)($_POST['name']??''));$email=(string)($_POST['email']??'');$phone=preg_replace('/\s+/', '',(string)($_POST['phone']??''));
  if($name===''||strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\+?[0-9]{10,16}$/',$phone)||($_POST['confirm_amount']??'')!=='300'){$this->fail('Coordonnées ou montant d’essai invalides');return;}
  $mode=IntegrationSettings::all()['paiementpro'];$ref=$this->reference('PAYIN');$db=App::db();
  $db->prepare("INSERT INTO payment_lab_operations(reference,kind,amount,environment) VALUES(?,'payin',300,?)")->execute([$ref,$mode]);
  try {
   $url=(new PaiementProService)->initiateTest(['reference'=>$ref,'customer_name'=>$name,'customer_email'=>$email,'customer_phone'=>$phone,'rental_fee'=>100,'deposit'=>200,'payment_environment'=>$mode]);
   $db->prepare("UPDATE payment_lab_operations SET status='initiated' WHERE reference=?")->execute([$ref]);
   header('Location: '.$url,true,303);
  }catch(\Throwable $e){error_log('Payment lab payin '.$ref.': '.$e->getMessage());$db->prepare("UPDATE payment_lab_operations SET status='failed',provider_message='Initialisation refusée' WHERE reference=?")->execute([$ref]);http_response_code(503);echo 'Initialisation refusée. Référence : '.htmlspecialchars($ref);}
 }
 public function payout():void {
  $this->guard();$this->enabled('payout');$phone=preg_replace('/\s+/', '',(string)($_POST['phone']??''));$channel=(string)($_POST['channel']??'');
  if(($_POST['confirm_amount']??'')!=='200'||!preg_match('/^\+?[0-9]{10,16}$/',$phone)||!in_array($channel,['WAVECI','MOMOCI','OMCIV','FLOOZ'],true)){$this->fail('Bénéficiaire ou montant invalide');return;}
  $db=App::db();$active=(int)$db->query("SELECT COUNT(*) FROM payment_lab_operations WHERE kind='payout' AND status IN ('created','initiated','processing','unknown')")->fetchColumn();
  if($active>0){$this->fail('Un essai payout est déjà en cours ou non rapproché');return;}
  $mode=IntegrationSettings::all()['paiementpro'];$ref=$this->reference('PAYOUT');
  // Validate configuration before recording a pending financial operation.
  try {$request=(new PaiementProPayoutService)->prepare($ref,200,$channel,$phone,'Test Thiebapower',$mode);}
  catch(\Throwable $e){$this->fail('Reversement non configuré : '.$e->getMessage());return;}
  $db->prepare("INSERT INTO payment_lab_operations(reference,kind,amount,environment,status,recipient_channel,recipient_phone) VALUES(?,'payout',200,?,'unknown',?,?)")->execute([$ref,$mode,$channel,$phone]);
  PayoutApiAudit::request($ref,$request);
  try {
   $client=new \SoapClient($request['wsdl'],['connection_timeout'=>10,'cache_wsdl'=>WSDL_CACHE_NONE]);
   $reply=$client->initTransact($request['params']);
   PayoutApiAudit::record($ref,'init',$reply);
   $status=strtoupper((string)($reply->status??''));
   $session=(string)($reply->sessionid??'');
   if($status==='FAILED'){
    $reason=substr((string)($reply->code??'').': '.(string)($reply->description??'Refus fournisseur'),0,250);
    $db->prepare("UPDATE payment_lab_operations SET status='failed',provider_message=? WHERE reference=?")->execute([$reason,$ref]);
   }elseif($session!==''){
    $db->prepare("UPDATE payment_lab_operations SET status='processing',provider_session_id=?,provider_message=? WHERE reference=?")->execute([$session,substr($status,0,250),$ref]);
   }elseif($status==='INITIATED'&&(string)($reply->code??'')==='0'){
    $db->prepare("UPDATE payment_lab_operations SET status='initiated',provider_message='Initiation acceptée sans session ; versement à confirmer' WHERE reference=?")->execute([$ref]);
   }
   App::redirect('/admin/payout');
  }catch(\Throwable $e){PayoutApiAudit::record($ref,'error',['exception'=>get_class($e),'description'=>$e->getMessage(),'faultcode'=>$e instanceof \SoapFault?$e->faultcode:'']);error_log('Payment lab payout outcome unknown '.$ref.': '.$e->getMessage());App::redirect('/admin/payout');}
 }
 public function reconcile():void {
  $this->guard();$this->enabled('payout');$id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);if(!$id){$this->fail('Opération invalide');return;}
  $db=App::db();$s=$db->prepare("SELECT * FROM payment_lab_operations WHERE id=? AND kind='payout'");$s->execute([$id]);$op=$s->fetch();
  if(!$op||$op['status']!=='processing'||!$op['provider_session_id']){$this->fail('Aucune session à vérifier');return;}
  try {
   $reply=(new PaiementProPayoutService)->status($op['provider_session_id'],$op['environment']);
   PayoutApiAudit::record($op['reference'],'status',$reply);
   if((string)($reply->status??'')==='SUCCESS'){
    if(!hash_equals($op['reference'],(string)($reply->referenceNo??''))||(int)($reply->amount??-1)!==200)throw new \RuntimeException('Référence ou montant incohérent');
    $db->prepare("UPDATE payment_lab_operations SET status='succeeded' WHERE id=? AND status='processing'")->execute([$id]);
   }elseif((string)($reply->status??'')==='FAILED'){$db->prepare("UPDATE payment_lab_operations SET status='failed' WHERE id=? AND status='processing'")->execute([$id]);}
   App::redirect('/admin/payout');
  }catch(\Throwable $e){PayoutApiAudit::record($op['reference'],'error',['exception'=>get_class($e),'description'=>$e->getMessage()]);error_log('Payment lab status '.$op['reference'].': '.$e->getMessage());http_response_code(503);echo 'Statut indisponible ; ne relancez pas le reversement.';}
 }
 public function archive():void {
  $this->guard();$this->enabled('payout');
  $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT);
  $reference=trim((string)($_POST['confirm_reference']??''));$note=trim((string)($_POST['archive_note']??''));
  if(!$id||($_POST['confirm_archive']??'')!=='1'||strlen($note)<6||strlen($note)>250){$this->fail('Vérification et motif requis');return;}
  $db=App::db();$db->beginTransaction();
  try {
   $s=$db->prepare("SELECT * FROM payment_lab_operations WHERE id=? AND kind='payout' FOR UPDATE");$s->execute([$id]);$op=$s->fetch();
   if(!$op||!in_array($op['status'],['unknown','initiated'],true)||!hash_equals($op['reference'],$reference))throw new \LogicException('Essai non éligible');
   $db->prepare("UPDATE payment_lab_operations SET status='archived',archived_at=UTC_TIMESTAMP(),archive_note=? WHERE id=?")->execute([$note,$id]);
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(409);exit('Impossible de clore cet essai');}
  App::redirect('/admin/payout');
 }
 public function notification():void {
  $p=$_POST ?: (json_decode(file_get_contents('php://input'),true)?:[]);$ref=(string)($p['referenceNumber']??'');
  $db=App::db();$s=$db->prepare("SELECT * FROM payment_lab_operations WHERE reference=? AND kind='payin'");$s->execute([$ref]);$op=$s->fetch();
  $merchant=$op && $op['environment']==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID'):App::env('PAIEMENTPRO_MERCHANT_ID');
  if(!$op||!$merchant||!hash_equals($merchant,(string)($p['merchantId']??''))||(int)($p['amount']??-1)!==300){http_response_code(400);echo 'invalid';return;}
  $db->prepare("UPDATE payment_lab_operations SET status='notification_unverified',provider_message=? WHERE id=? AND status='initiated'")->execute([substr((string)($p['responsecode']??''),0,50),$op['id']]);
  http_response_code(202);echo 'verification pending';
 }
 public function payoutNotification():void {
  if(($_SERVER['REQUEST_METHOD']??'')==='GET'&&$_GET===[]){header('Content-Type: text/plain; charset=utf-8');echo 'payout callback ready';return;}
  if((int)($_SERVER['CONTENT_LENGTH']??0)>8192){http_response_code(413);return;}
  $raw=substr(file_get_contents('php://input'),0,8192);
  $decoded=json_decode($raw,true);
  $p=$_POST ?: (is_array($decoded)?$decoded:$_GET);
  $refValue=is_array($p)?($p['referenceNo']??$p['referenceNumber']??$p['reference']??''):'';
  $ref=is_scalar($refValue)?(string)$refValue:'';
  if($ref===''&&isset($p['returnContext'])&&is_scalar($p['returnContext'])&&preg_match('/(?:^|&)reference=([A-Za-z0-9-]{1,120})/',(string)$p['returnContext'],$match))$ref=$match[1];
  $known=false;
  if($ref!==''&&strlen($ref)<=120){
   $q=App::db()->prepare("SELECT (SELECT COUNT(*) FROM deposit_settlements WHERE provider_reference=?)+(SELECT COUNT(*) FROM payment_lab_operations WHERE reference=? AND kind='payout')");$q->execute([$ref,$ref]);
   $known=(int)$q->fetchColumn()>0;
  }
  if(!$known){
   $ref='UNMATCHED';
   $q=App::db()->query("SELECT COUNT(*) FROM payout_api_events WHERE reference='UNMATCHED' AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE)");
   if((int)$q->fetchColumn()>0){http_response_code(202);echo 'status verification pending';return;}
  }
  $p['method']=$_SERVER['REQUEST_METHOD']??'';
  $p['contentType']=substr((string)($_SERVER['CONTENT_TYPE']??''),0,120);
  $p['bodyHash']=hash('sha256',$raw);
  $p['fieldNames']=implode(',',array_slice(array_keys($p),0,30));
  PayoutApiAudit::record($ref,'callback',$p);
  // The callback format/signature is not documented; it never marks a payout as successful.
  http_response_code(202);echo 'status verification pending';
 }
 public function returnPage():void {echo 'Retour Paiement Pro. Vérifiez l’opération dans le tableau de bord : https://thiebapower.com/admin';}
}
