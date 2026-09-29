<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\RentalLifecycleService;
final class RentalOperationsController {
 public function index():void {
  Auth::requirePermission('rentals.manage');
  $status=(string)($_GET['status']??'');
  $allowed=['pending_payment','releasing','active','returned','payment_failed','release_failed','payment_review'];
  $db=App::db();
  if(in_array($status,$allowed,true)){$q=$db->prepare('SELECT r.*,b.serial battery_serial,s.status refund_status FROM rentals r JOIN batteries b ON b.id=r.battery_id LEFT JOIN deposit_settlements s ON s.rental_id=r.id WHERE r.status=? ORDER BY r.id DESC LIMIT 100');$q->execute([$status]);}
  else {$status='';$q=$db->query('SELECT r.*,b.serial battery_serial,s.status refund_status FROM rentals r JOIN batteries b ON b.id=r.battery_id LEFT JOIN deposit_settlements s ON s.rental_id=r.id ORDER BY r.id DESC LIMIT 100');}
  $rentals=$q->fetchAll();
  $counts=$db->query('SELECT status,COUNT(*) quantity FROM rentals GROUP BY status')->fetchAll();
  App::view('rental_operations',compact('rentals','counts','status'));
 }
 public function cancelPending():void {
  Auth::requirePermission('rentals.manage',true);
  $reference=(string)($_POST['reference']??'');$confirmation=(string)($_POST['confirm_reference']??'');$proof=trim((string)($_POST['provider_proof']??''));
  if(!preg_match('/^TBP-[A-F0-9]{16}$/D',$reference)||!hash_equals($reference,$confirmation)||strlen($proof)<6||strlen($proof)>120){http_response_code(422);exit('Référence ou preuve fournisseur invalide');}
  $q=App::db()->prepare('SELECT status,created_at FROM rentals WHERE reference=?');$q->execute([$reference]);$r=$q->fetch();
  if(!$r||$r['status']!=='pending_payment'||strtotime($r['created_at'].' UTC')>time()-900){http_response_code(409);exit('Attendre la fin de la session de paiement');}
  if(!(new RentalLifecycleService)->failedPayment($reference)){http_response_code(409);exit('État de la location modifié');}
  Audit::event('rental.pending_cancelled','rental',$reference,['provider_proof'=>$proof]);
  App::redirect('/admin/rentals?status=payment_failed');
 }
}
