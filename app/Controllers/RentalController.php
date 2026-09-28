<?php
namespace App\Controllers;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalController {
 public function index():void {
  $prices=App::db()->query('SELECT * FROM pricing WHERE id=1')->fetch();
  $batteries=App::db()->query("SELECT id,serial,deposit_override FROM batteries WHERE status='available' ORDER BY id")->fetchAll();
  App::view('rent',['prices'=>$prices,'batteries'=>$batteries]);
 }
 public function create():void {
  // Re-enable only after verified payment, station release and deposit settlement are implemented.
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Les locations seront disponibles prochainement.';
 }
 public function status():void {
  $reference=$_GET['reference']??'';
  $r=(new RentalRepository)->find($reference);
  if(!$r){http_response_code(404);return;}
  header('Content-Type: application/json');
  echo json_encode(['reference'=>$r['reference'],'status'=>$r['status']]);
 }
}
