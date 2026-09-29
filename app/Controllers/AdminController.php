<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\IntegrationSettings;
use App\Services\Auth;
use App\Services\Audit;
final class AdminController {
 public function index():void {
  $currentUser=Auth::requirePermission('dashboard.view');
  $csrf=$_SESSION['csrf'];
  $db=App::db();
  $prices=$db->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $batteries=$db->query('SELECT * FROM batteries ORDER BY id')->fetchAll();
  $stations=$db->query('SELECT * FROM stations ORDER BY imei')->fetchAll();
  $modes=IntegrationSettings::all();
  $stats=$db->query("SELECT status,COUNT(*) quantity FROM rentals GROUP BY status")->fetchAll();
  $rentals=$db->query('SELECT r.id,r.reference,r.customer_name,r.customer_phone,r.rental_fee,r.deposit,r.status,r.payment_session_id,r.due_at,r.created_at,s.refund_amount,s.status refund_status,s.provider_session_id refund_session FROM rentals r LEFT JOIN deposit_settlements s ON s.rental_id=r.id ORDER BY r.id DESC LIMIT 10')->fetchAll();
  $workerLastRun=$db->query("SELECT last_run_at FROM service_heartbeats WHERE name='heycharge'")->fetchColumn()?:null;
  $workerRecent=$workerLastRun && strtotime($workerLastRun.' UTC')>=time()-240;
  $ready=['paiementpro_sandbox'=>App::env('PAIEMENTPRO_SANDBOX_WSDL')!=='' && App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='',
          'paiementpro_production'=>App::env('PAIEMENTPRO_MERCHANT_ID')!=='',
          'heycharge_normal'=>App::env('HEYCHARGE_API_BASE')!=='' && App::env('HEYCHARGE_API_KEY')!=='',
          'payment_callback'=>(new \App\Services\PaymentVerification)->ready()];
  App::view('admin',compact('prices','batteries','modes','stats','rentals','csrf','ready','currentUser','stations','workerLastRun','workerRecent'));
 }
 public function modes():void {
  Auth::requirePermission('integrations.manage',true);
  $db=App::db();$db->beginTransaction();
  try {
   IntegrationSettings::set('paiementpro',(string)($_POST['paiementpro_mode']??''));
   IntegrationSettings::set('heycharge',(string)($_POST['heycharge_mode']??''));
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();http_response_code(422);exit('Mode invalide');}
  Audit::event('integrations.modes_updated','integration',null,['paiementpro'=>$_POST['paiementpro_mode']??'','heycharge'=>$_POST['heycharge_mode']??'']);
  App::redirect('/admin');
 }
 public function prices():void {
  Auth::requirePermission('pricing.manage',true);
  $fee=filter_input(INPUT_POST,'rental_fee',FILTER_VALIDATE_INT);
  $deposit=filter_input(INPUT_POST,'default_deposit',FILTER_VALIDATE_INT);
  $minutes=filter_input(INPUT_POST,'duration_minutes',FILTER_VALIDATE_INT);
  $percent=filter_input(INPUT_POST,'late_percent',FILTER_VALIDATE_INT);
  $depositEnabled=($_POST['deposit_enabled']??'')==='1'?1:0;
  if(!is_int($fee)||$fee<1||!is_int($deposit)||$deposit<0||!is_int($minutes)||$minutes<1||!is_int($percent)||$percent<0||$percent>100){http_response_code(422);exit('Tarifs invalides');}
  App::db()->prepare('UPDATE pricing SET rental_fee=?,default_deposit=?,duration_minutes=?,late_percent=?,deposit_enabled=? WHERE id=1')->execute([$fee,$deposit,$minutes,$percent,$depositEnabled]);
  Audit::event('pricing.updated','pricing','1',['rental_fee'=>$fee,'default_deposit'=>$deposit,'duration_minutes'=>$minutes,'late_percent'=>$percent,'deposit_enabled'=>$depositEnabled]);
  App::redirect('/admin');
 }
 public function battery():void {
  Auth::requirePermission('fleet.manage',true);
  $serial=trim((string)($_POST['serial']??''));$raw=(string)($_POST['deposit_override']??'');
  $deposit=$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT);
  if($serial===''||strlen($serial)>100||($deposit!==null&&($deposit===false||$deposit<0))){http_response_code(422);exit('Batterie invalide');}
  App::db()->prepare("INSERT INTO batteries(serial,deposit_override,status) VALUES(?,?,'available') ON DUPLICATE KEY UPDATE deposit_override=VALUES(deposit_override)")->execute([$serial,$deposit]);
  Audit::event('battery.saved','battery',$serial,['deposit_override'=>$deposit]);
  App::redirect('/admin');
 }
}
