<?php
namespace App\Controllers;
use App\Core\App;
final class PayoutConsoleController {
 public function index():void {
  if(!hash_equals(App::env('ADMIN_USERNAME','admin'),(string)($_SERVER['PHP_AUTH_USER']??''))||!password_verify((string)($_SERVER['PHP_AUTH_PW']??''),App::env('ADMIN_PASSWORD_HASH'))){header('WWW-Authenticate: Basic realm="Thiebapower"');http_response_code(401);exit('Authentification requise');}
  if(session_status()!==PHP_SESSION_ACTIVE)session_start();
  $_SESSION['csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['csrf'];
  $db=App::db();
  $operations=$db->query("SELECT * FROM payment_lab_operations WHERE kind='payout' ORDER BY id DESC LIMIT 20")->fetchAll();
  $settlements=$db->query('SELECT s.*,r.reference rental_reference FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id ORDER BY s.id DESC LIMIT 20')->fetchAll();
  $events=$db->query('SELECT * FROM payout_api_events ORDER BY id DESC LIMIT 40')->fetchAll();
  $open=(int)$db->query("SELECT (SELECT COUNT(*) FROM deposit_settlements WHERE status IN ('unknown','processing'))+(SELECT COUNT(*) FROM payment_lab_operations WHERE kind='payout' AND status IN ('unknown','processing','created','initiated'))")->fetchColumn();
  App::view('payout',compact('csrf','operations','settlements','events','open'));
 }
}
