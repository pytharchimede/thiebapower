<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\IntegrationSettings;
final class PayoutConsoleController {
 public function index():void {
  if(!hash_equals(App::env('ADMIN_USERNAME','admin'),(string)($_SERVER['PHP_AUTH_USER']??''))||!password_verify((string)($_SERVER['PHP_AUTH_PW']??''),App::env('ADMIN_PASSWORD_HASH'))){header('WWW-Authenticate: Basic realm="Thiebapower"');http_response_code(401);exit('Authentification requise');}
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  $_SESSION['csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf'];
  $db=App::db();
  $operations=$db->query("SELECT * FROM payment_lab_operations WHERE kind='payout' ORDER BY id DESC LIMIT 20")->fetchAll();
  $settlements=$db->query('SELECT s.*,r.reference rental_reference,r.customer_phone,r.payout_channel FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id ORDER BY s.id DESC LIMIT 20')->fetchAll();
  $events=$db->query('SELECT * FROM payout_api_events ORDER BY id DESC LIMIT 40')->fetchAll();
  $requests=$db->query('SELECT * FROM payout_api_requests ORDER BY created_at DESC LIMIT 20')->fetchAll();
  $labOpen=(int)$db->query("SELECT COUNT(*) FROM payment_lab_operations WHERE kind='payout' AND status IN ('unknown','processing','created','initiated')")->fetchColumn();
  $mode=IntegrationSettings::all()['paiementpro'];
  $endpoint=$mode==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_PAYOUT_WSDL'):'https://paiementpro.net/webservice/v2/payout/soap.php?wsdl';
  $enabled=App::env('PAYMENT_LAB_PAYOUT_ENABLED')==='1'||App::env('AUTOMATIC_REFUNDS_ENABLED')==='1';
  App::view('payout',compact('csrf','operations','settlements','events','requests','labOpen','mode','endpoint','enabled'));
 }
}
