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
 public function returnPage():void {
  // Paiement Pro may append "?merchantId=..." to a URL that already has a query string.
  $candidate=explode('?',(string)($_GET['reference']??''),2)[0];
  $reference=preg_match('/^TBP-[A-F0-9]{16}$/D',$candidate)?$candidate:'';
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Paiement · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="kiosk"><main class="kiosk-shell" style="max-width:700px;margin:8vh auto;padding:32px"><a href="/" class="kiosk-logo">THIEBA<span>POWER</span></a><h1>Paiement en cours de vérification</h1><p>Votre retour sur cette page ne confirme pas encore l’encaissement. Conservez votre référence et attendez la confirmation du personnel avant de retirer une batterie.</p><p>Référence : <strong>'.htmlspecialchars($reference!==''?$reference:'indisponible',ENT_QUOTES,'UTF-8').'</strong></p><a href="/" class="touch-button outline">Retour au kiosque</a></main></body></html>';
 }
}
