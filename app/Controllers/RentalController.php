<?php
namespace App\Controllers;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalController {
 public function index():void {
  $prices=App::db()->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $batteries=App::db()->query("SELECT id,serial,deposit_override FROM batteries WHERE status='available' ORDER BY id")->fetchAll();
  App::view('rent',['prices'=>$prices,'batteries'=>$batteries,'checkoutEnabled'=>App::env('PUBLIC_RENTALS_ENABLED')==='1' && (new \App\Services\PaymentVerification)->ready() && (new \App\Services\HeyChargeOpenApi)->configured()]);
 }
 public function create():void {
  if(App::env('PUBLIC_RENTALS_ENABLED')!=='1'||!(new \App\Services\PaymentVerification)->ready()||!(new \App\Services\HeyChargeOpenApi)->configured()){
   http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Les locations seront disponibles prochainement.';return;
  }
  try {App::redirect((new \App\Services\RentalCheckoutService)->begin($_POST));}
  catch(\InvalidArgumentException $e){http_response_code(422);echo 'Informations de location invalides';}
  catch(\Throwable $e){error_log($e);http_response_code(503);echo 'Location momentanément indisponible';}
 }
 public function status():void {
  $reference=$_GET['reference']??'';
  $r=(new RentalRepository)->find($reference);
  if(!$r){http_response_code(404);return;}
  header('Content-Type: application/json');
  echo json_encode(['reference'=>$r['reference'],'status'=>$r['status']]);
 }
}
