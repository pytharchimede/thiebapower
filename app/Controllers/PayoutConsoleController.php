<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\IntegrationSettings;
use App\Services\Auth;
final class PayoutConsoleController {
 public function index():void {
  Auth::requirePermission('payout.view');
  $csrf=$_SESSION['csrf'];
  $db=App::db();
  $operations=$db->query("SELECT * FROM payment_lab_operations WHERE kind='payout' ORDER BY id DESC LIMIT 20")->fetchAll();
  $settlements=$db->query('SELECT s.*,r.reference rental_reference,r.customer_phone,r.payout_channel FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id ORDER BY s.id DESC LIMIT 20')->fetchAll();
  $events=$db->query('SELECT * FROM payout_api_events ORDER BY id DESC LIMIT 40')->fetchAll();
  $requests=$db->query('SELECT * FROM payout_api_requests ORDER BY created_at DESC LIMIT 20')->fetchAll();
  $labOpen=(int)$db->query("SELECT COUNT(*) FROM payment_lab_operations WHERE kind='payout' AND status IN ('unknown','processing','created','initiated')")->fetchColumn();
  $mode=IntegrationSettings::all()['paiementpro'];
  $endpoint=$mode==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_PAYOUT_WSDL'):'https://paiementpro.net/webservice/v2/payout/soap.php?wsdl';
  $enabled=App::env('PAYMENT_LAB_PAYOUT_ENABLED')==='1'||App::env('AUTOMATIC_REFUNDS_ENABLED')==='1';
  $canSend=Auth::can('payout.send');
  $reports=[];
  // Fetch the complete journal for each visible test, independent of dashboard limits.
  $rq=$db->prepare('SELECT * FROM payout_api_requests WHERE reference=? ORDER BY created_at');
  $ev=$db->prepare('SELECT * FROM payout_api_events WHERE reference=? ORDER BY id');
  foreach($operations as $op){
   $rq->execute([$op['reference']]);$opRequests=$rq->fetchAll();
   $ev->execute([$op['reference']]);
   $reports[$op['id']]=\App\Services\PayoutTestReport::build($op,$opRequests,$ev->fetchAll());
  }
  App::view('payout',compact('csrf','operations','settlements','events','requests','labOpen','mode','endpoint','enabled','canSend','reports'));
 }
}
