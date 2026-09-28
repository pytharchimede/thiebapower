<?php
namespace App\Controllers;
use App\Core\App;use App\Repositories\RentalRepository;use App\Services\PaymentVerification;
final class PaymentController {
 public function callback():void {
  $p=$_POST; if(!$p){$p=json_decode(file_get_contents('php://input'),true)?:[];}
  $reference=(string)($p['referenceNumber']??'');$r=(new RentalRepository)->find($reference);
  if(!$r||!hash_equals((string)App::env('PAIEMENTPRO_MERCHANT_ID'),(string)($p['merchantId']??''))|| (int)($p['amount']??-1)!==(int)$r['rental_fee']+(int)$r['deposit']){http_response_code(400);echo 'invalid';return;}
  $db=App::db();$db->prepare('INSERT INTO payment_notifications(rental_id,payload,received_at) VALUES(?,?,NOW())')->execute([$r['id'],json_encode($p)]);
  if(!(new PaymentVerification)->verified($p,$r)){http_response_code(202);echo 'pending verification';return;}
  // Future confirmed-payment workflow: lock rental, enforce idempotency,
  // call HeyCharge using a persistent outbox, reconcile physical release.
  http_response_code(202);echo 'pending release';
 }
 public function returnPage():void {echo 'Paiement en cours de vérification. Référence : '.htmlspecialchars($_GET['reference']??'',ENT_QUOTES,'UTF-8');}
}
