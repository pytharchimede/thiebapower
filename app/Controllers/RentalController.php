<?php
namespace App\Controllers;
use App\Core\App;
use App\Repositories\RentalRepository;
use App\Services\Audit;
final class RentalController {
 public function index():void {
  $prices=App::db()->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $modes=\App\Services\IntegrationSettings::all();
  $batteries=App::db()->query($modes['heycharge']==='normal' ? "SELECT b.id,b.serial,b.deposit_override,b.station_imei FROM batteries b JOIN stations s ON s.imei=b.station_imei WHERE b.status='available' AND s.enabled=1 AND s.status='online' ORDER BY b.id" : "SELECT id,serial,deposit_override,station_imei FROM batteries WHERE status='available' ORDER BY id")->fetchAll();
  App::view('rent',['prices'=>$prices,'batteries'=>$batteries,'checkoutEnabled'=>$this->checkoutEnabled(),'depositEnabled'=>(int)$prices['deposit_enabled']===1]);
 }
 private function checkoutEnabled():bool {
  if(App::env('PUBLIC_RENTALS_ENABLED')!=='1')return false;
  $modes=\App\Services\IntegrationSettings::all();
  if($modes['heycharge']==='simulation')return App::env('SIMULATED_RENTALS_ENABLED')==='1' && ($modes['paiementpro']==='production'?App::env('PAIEMENTPRO_MERCHANT_ID')!=='' : App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='');
  return (new \App\Services\PaymentVerification)->ready() && ($modes['paiementpro']==='production' ? App::env('PAIEMENTPRO_MERCHANT_ID')!=='' : App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID')!=='') && (new \App\Services\HeyChargeOpenApi)->configured();
 }
 public function create():void {
  if(!$this->checkoutEnabled()){
   http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Les locations seront disponibles prochainement.';return;
  }
  try {$url=(new \App\Services\RentalCheckoutService)->begin($_POST);Audit::event('rental.checkout_started','station',(string)($_POST['station_code']??''));App::redirect($url);}
  catch(\InvalidArgumentException $e){http_response_code(422);echo 'Informations de location invalides';}
  catch(\Throwable $e){error_log($e);http_response_code(503);echo 'Location momentanément indisponible';}
 }
 public function status():void {
  $reference=$_GET['reference']??'';
  $r=(new RentalRepository)->find($reference);
  if(!$r){http_response_code(404);return;}
  header('Content-Type: application/json');
  echo json_encode(['reference'=>$r['reference'],'status'=>$r['status'],'due_at'=>$r['due_at']]);
 }
}
