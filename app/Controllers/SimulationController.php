<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\IntegrationSettings;
use App\Services\RentalLifecycleService;
use App\Services\AutomaticDepositRefundService;
use App\Services\Auth;
use App\Services\Audit;
final class SimulationController {
 private function guard():void {
  Auth::requirePermission('rentals.manage',true);
  if(App::env('SIMULATED_RENTALS_ENABLED')!=='1'||IntegrationSettings::all()['heycharge']!=='simulation'){http_response_code(403);exit('Simulation désactivée');}
 }
 private function selection():array {
  $reference=(string)($_POST['reference']??'');
  if(!preg_match('/^TBP-[A-F0-9]{16}$/D',$reference)||!hash_equals($reference,(string)($_POST['confirm_reference']??''))){http_response_code(422);exit('Confirmez la référence de la location');}
  $s=App::db()->prepare('SELECT * FROM rentals WHERE reference=?');$s->execute([$reference]);$r=$s->fetch();
  if(!$r){http_response_code(404);exit('Location introuvable');}
  return $r;
 }
 public function paid():void {
  $this->guard();$r=$this->selection();
  if(App::env('PUBLIC_RENTALS_ENABLED')!=='1'||$r['status']!=='pending_payment'||!$r['payment_session_id']||!$r['station_code']||($_POST['confirm_paid']??'')!=='1'){http_response_code(409);exit('Paiement ou location non éligible');}
  $proof=trim((string)($_POST['provider_proof']??''));
  if(strlen($proof)<6||strlen($proof)>120){http_response_code(422);exit('Renseignez la référence de transaction vérifiée auprès de Paiement Pro');}
  $db=App::db();$db->beginTransaction();
  try {
   $s=$db->prepare('SELECT * FROM rentals WHERE id=? FOR UPDATE');$s->execute([$r['id']]);$locked=$s->fetch();
   if($locked['status']!=='pending_payment'||$locked['payment_session_id']!==$r['payment_session_id'])throw new \LogicException('État de la location modifié');
   $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
   $due=$now->modify('+'.(int)$locked['duration_minutes'].' minutes');
   $db->prepare("UPDATE rentals SET status='active',started_at=?,due_at=? WHERE id=?")->execute([$now->format('Y-m-d H:i:s'),$due->format('Y-m-d H:i:s'),$r['id']]);
   $battery=$db->prepare("UPDATE batteries SET status='rented' WHERE id=? AND status='reserved'");$battery->execute([$r['battery_id']]);
   if($battery->rowCount()!==1)throw new \LogicException('Batterie non réservée');
   $db->prepare("INSERT INTO rental_simulation_events(rental_id,event_type,provider_proof,created_at) VALUES(?,'release',?,UTC_TIMESTAMP())")->execute([$r['id'],$proof]);
   $db->commit();
   Audit::event('rental.simulated_release','rental',$r['reference'],['provider_proof'=>$proof]);
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e);http_response_code(409);exit('Confirmation impossible');}
  App::redirect('/admin#activity');
 }
 public function returned():void {
  $this->guard();$r=$this->selection();
  if($r['status']!=='active'){http_response_code(409);exit('Location non active');}
  try {
   $settlement=(new RentalLifecycleService)->confirmedReturn((int)$r['id'],new \DateTimeImmutable('now',new \DateTimeZone('UTC')));
   App::db()->prepare("INSERT IGNORE INTO rental_simulation_events(rental_id,event_type,created_at) VALUES(?,'return',UTC_TIMESTAMP())")->execute([$r['id']]);
   Audit::event('rental.simulated_return','rental',$r['reference'],['settlement_id'=>$settlement]);
  }catch(\Throwable $e){error_log($e);http_response_code(503);exit('Retour à vérifier avant toute répétition');}
  App::redirect('/admin#activity');
 }
 public function reconcile():void {
  $this->guard();$r=$this->selection();
  $s=App::db()->prepare('SELECT id,status FROM deposit_settlements WHERE rental_id=?');$s->execute([$r['id']]);$settlement=$s->fetch();
  if(!$settlement||$settlement['status']!=='processing'){http_response_code(409);exit('Aucun reversement en cours à vérifier');}
  try {(new AutomaticDepositRefundService)->reconcile((int)$settlement['id']);}
  catch(\Throwable $e){error_log($e);http_response_code(503);exit('Réponse Paiement Pro indisponible ; ne relancez pas le reversement');}
  App::redirect('/admin#simulation');
 }
}
