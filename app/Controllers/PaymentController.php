<?php
namespace App\Controllers;
use App\Core\App;use App\Repositories\RentalRepository;use App\Services\PaymentVerification;use App\Services\RentalLifecycleService;
final class PaymentController {
 public function callback():void {
  $p=$_POST; if(!$p){$p=json_decode(file_get_contents('php://input'),true)?:[];}
  $reference=(string)($p['referenceNumber']??'');$r=(new RentalRepository)->find($reference);
  $merchant=$r && $r['payment_environment']==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID'):App::env('PAIEMENTPRO_MERCHANT_ID');
  if(!$r||!$merchant||!hash_equals((string)$merchant,(string)($p['merchantId']??''))|| (int)($p['amount']??-1)!==(int)$r['rental_fee']+(int)$r['deposit']){http_response_code(400);echo 'invalid';return;}
  $db=App::db();$db->prepare('INSERT INTO payment_notifications(rental_id,payload,received_at) VALUES(?,?,NOW())')->execute([$r['id'],json_encode($p)]);
  if(!(new PaymentVerification)->verified($p,$r)){http_response_code(202);echo 'pending verification';return;}
  (new RentalLifecycleService)->confirmedPayment($reference);
  http_response_code(202);echo 'release pending';
 }
 public function returnPage():void {echo 'Paiement en cours de vérification. Référence : '.htmlspecialchars($_GET['reference']??'',ENT_QUOTES,'UTF-8');}
}
