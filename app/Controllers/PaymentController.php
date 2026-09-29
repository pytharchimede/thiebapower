<?php
namespace App\Controllers;
use App\Core\App;use App\Repositories\RentalRepository;use App\Services\PaymentVerification;use App\Services\RentalLifecycleService;
use App\Services\Audit;
final class PaymentController {
 public function callback():void {
  $p=$_POST; if(!$p){$p=json_decode(file_get_contents('php://input'),true)?:[];}
  $reference=(string)($p['referenceNumber']??'');$r=(new RentalRepository)->find($reference);
  if(!$r){http_response_code(404);echo 'unknown';return;}
  $verified=(new PaymentVerification)->verified($p,$r,(string)($_GET['token']??''));
  if(!$verified){http_response_code(403);echo 'invalid notification';return;}
  $db=App::db();$db->prepare('INSERT INTO payment_notifications(rental_id,payload,received_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$r['id'],json_encode($p)]);
  Audit::event('payment.notification_verified','rental',$reference,['amount'=>$p['amount']]);
  (new RentalLifecycleService)->confirmedPayment($reference);
  http_response_code(202);echo 'release pending';
 }
 public function returnPage():void {
  // Paiement Pro may append "?merchantId=..." to a URL that already has a query string.
  $candidate=explode('?',(string)($_GET['reference']??''),2)[0];
  $reference=preg_match('/^TBP-[A-F0-9]{16}$/D',$candidate)?$candidate:'';
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Paiement · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="kiosk"><main class="kiosk-shell" style="max-width:700px;margin:8vh auto;padding:32px"><a href="/" class="kiosk-logo">THIEBA<span>POWER</span></a><h1>Paiement en cours de vérification</h1><p>Votre retour sur cette page ne confirme pas encore l’encaissement. Conservez votre référence. La batterie est libérée après confirmation du paiement ; suivez son état ci-dessous.</p><p>Référence : <strong>'.htmlspecialchars($reference!==''?$reference:'indisponible',ENT_QUOTES,'UTF-8').'</strong></p><p id="rental-status" role="status">Vérification en cours…</p><a href="/" class="touch-button outline">Retour au kiosque</a></main><script>const ref='.json_encode($reference).';if(ref){const tick=async()=>{try{const r=await fetch("/rentals/status?reference="+encodeURIComponent(ref));if(r.ok){const d=await r.json();document.getElementById("rental-status").textContent=({pending_payment:"Paiement en cours de vérification",releasing:"Sortie de batterie en cours",release_failed:"Sortie à vérifier auprès du personnel",active:"Batterie disponible : retirez-la de la station",returned:"Batterie rendue"})[d.status]||"Statut : "+d.status;}}catch(e){}};tick();setInterval(tick,5000);}</script></body></html>';
 }
}
