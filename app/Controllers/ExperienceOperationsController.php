<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\RentalWatch;
final class ExperienceOperationsController
{
 public function watch():void {
  Auth::requirePermission('rentals.view');$finance=Auth::can('finance.view');
  $refundSelect=$finance?'d.status refund_status,':'NULL refund_status,';
  $refundCondition=$finance?"OR d.status IN ('failed','unknown') ":'';
  $q=App::db()->query("SELECT r.*,b.serial battery_serial,s.label station_label,".$refundSelect."COALESCE(t.open_requests,0) open_requests FROM rentals r JOIN batteries b ON b.id=r.battery_id LEFT JOIN stations s ON s.imei=r.station_code LEFT JOIN deposit_settlements d ON d.rental_id=r.id LEFT JOIN (SELECT rental_id,COUNT(*) open_requests FROM rental_support_requests WHERE status='open' GROUP BY rental_id) t ON t.rental_id=r.id WHERE r.status IN ('release_failed','payment_review') OR (r.status='releasing' AND r.release_command_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE)) OR (r.status='pending_payment' AND r.reservation_expires_at<=UTC_TIMESTAMP()) OR (r.status='active' AND r.due_at<=UTC_TIMESTAMP()) ".$refundCondition."OR t.open_requests>0 ORDER BY (r.status IN ('release_failed','payment_review')) DESC,r.created_at DESC LIMIT 200");
  $rows=$q->fetchAll();foreach($rows as &$row)$row['reasons']=RentalWatch::reasons($row,time());unset($row);
  App::view('rental_watch',compact('rows','finance'));
 }
 public function support():void {
  Auth::requirePermission('rentals.view');
  $requests=App::db()->query("SELECT t.*,r.reference,r.customer_name,r.customer_phone FROM rental_support_requests t JOIN rentals r ON r.id=t.rental_id ORDER BY (t.status='open') DESC,t.id DESC LIMIT 200")->fetchAll();App::view('rental_support',compact('requests'));
 }
 public function resolve():void {
  Auth::requirePermission('rentals.manage',true);$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);$text=trim((string)($_POST['resolution']??''));
  if(!$id||strlen($text)<5||strlen($text)>2000){http_response_code(422);echo 'Renseignez la résolution de la demande.';return;}
  $q=App::db()->prepare("UPDATE rental_support_requests SET status='resolved',resolution=?,resolved_by=?,resolved_at=UTC_TIMESTAMP() WHERE id=? AND status='open'");$q->execute([$text,Auth::id(),$id]);
  if($q->rowCount())Audit::event('support.resolved','support',(string)$id);
  App::redirect('/admin/support');
 }
}
