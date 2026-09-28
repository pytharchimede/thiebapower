<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\IntegrationSettings;
final class AdminController {
 private function auth():void {
  $user=$_SERVER['PHP_AUTH_USER']??'';
  $password=$_SERVER['PHP_AUTH_PW']??'';
  if (!hash_equals(App::env('ADMIN_USERNAME','admin'),$user) || !password_verify($password,App::env('ADMIN_PASSWORD_HASH'))) {
   header('WWW-Authenticate: Basic realm="Thiebapower"');
   http_response_code(401);exit('Authentification requise');
  }
 }
 private function csrf():void {
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  if(!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Formulaire expiré');}
 }
 public function index():void {
  $this->auth();
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  $_SESSION['csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf'];
  $db=App::db();
  $prices=$db->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $batteries=$db->query('SELECT * FROM batteries ORDER BY id')->fetchAll();
  $modes=IntegrationSettings::all();
  $stats=$db->query("SELECT status,COUNT(*) quantity FROM rentals GROUP BY status")->fetchAll();
  $rentals=$db->query('SELECT r.id,r.reference,r.customer_name,r.customer_phone,r.rental_fee,r.deposit,r.status,r.payment_session_id,r.due_at,r.created_at,s.refund_amount,s.status refund_status,s.provider_session_id refund_session FROM rentals r LEFT JOIN deposit_settlements s ON s.rental_id=r.id ORDER BY r.id DESC LIMIT 10')->fetchAll();
  $testOperations=$db->query('SELECT * FROM payment_lab_operations ORDER BY id DESC LIMIT 10')->fetchAll();
  $payinEnabled=App::env('PAYMENT_LAB_PAYIN_ENABLED')==='1';
  $payoutEnabled=App::env('PAYMENT_LAB_PAYOUT_ENABLED')==='1';
  $simulationEnabled=App::env('SIMULATED_RENTALS_ENABLED')==='1' && $modes['heycharge']==='simulation';
  $autoRefundEnabled=App::env('AUTOMATIC_REFUNDS_ENABLED')==='1';
  $ready=['paiementpro_sandbox'=>App::env('PAIEMENTPRO_SANDBOX_WSDL')!=='' && App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='',
          'paiementpro_production'=>App::env('PAIEMENTPRO_MERCHANT_ID')!=='',
          'heycharge_normal'=>App::env('HEYCHARGE_API_BASE')!=='' && App::env('HEYCHARGE_API_KEY')!==''];
  App::view('admin',compact('prices','batteries','modes','stats','rentals','testOperations','csrf','ready','payinEnabled','payoutEnabled','simulationEnabled','autoRefundEnabled'));
 }
 public function modes():void {
  $this->auth();$this->csrf();
  try {
   IntegrationSettings::set('paiementpro',(string)($_POST['paiementpro_mode']??''));
   IntegrationSettings::set('heycharge',(string)($_POST['heycharge_mode']??''));
  }catch(\InvalidArgumentException){http_response_code(422);exit('Mode invalide');}
  App::redirect('/admin');
 }
 public function prices():void {
  $this->auth();$this->csrf();
  $fee=filter_input(INPUT_POST,'rental_fee',FILTER_VALIDATE_INT);
  $deposit=filter_input(INPUT_POST,'default_deposit',FILTER_VALIDATE_INT);
  $minutes=filter_input(INPUT_POST,'duration_minutes',FILTER_VALIDATE_INT);
  $percent=filter_input(INPUT_POST,'late_percent',FILTER_VALIDATE_INT);
  if(!is_int($fee)||$fee<0||!is_int($deposit)||$deposit<0||!is_int($minutes)||$minutes<1||!is_int($percent)||$percent<0||$percent>100){http_response_code(422);exit('Tarifs invalides');}
  App::db()->prepare('UPDATE pricing SET rental_fee=?,default_deposit=?,duration_minutes=?,late_percent=? WHERE id=1')->execute([$fee,$deposit,$minutes,$percent]);
  App::redirect('/admin');
 }
 public function battery():void {
  $this->auth();$this->csrf();
  $serial=trim((string)($_POST['serial']??''));$raw=(string)($_POST['deposit_override']??'');
  $deposit=$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT);
  if($serial===''||strlen($serial)>100||($deposit!==null&&($deposit===false||$deposit<0))){http_response_code(422);exit('Batterie invalide');}
  App::db()->prepare("INSERT INTO batteries(serial,deposit_override,status) VALUES(?,?,'available') ON DUPLICATE KEY UPDATE deposit_override=VALUES(deposit_override)")->execute([$serial,$deposit]);
  App::redirect('/admin');
 }
}
