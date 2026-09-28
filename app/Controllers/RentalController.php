<?php
namespace App\Controllers;
use App\Core\App;use App\Repositories\RentalRepository;use App\Services\PaiementProService;
final class RentalController {
 public function index():void {$prices=App::db()->query('SELECT * FROM pricing WHERE id=1')->fetch();$batteries=App::db()->query("SELECT id,serial,deposit_override FROM batteries WHERE status='available' ORDER BY id")->fetchAll();App::view('rent',['prices'=>$prices,'batteries'=>$batteries]);}
 public function create():void {
  if(!filter_var($_POST['email']??'',FILTER_VALIDATE_EMAIL)||!preg_match('/^[+0-9 ]{8,20}$/',$_POST['phone']??'')||trim($_POST['name']??'')===''){http_response_code(422);echo 'Coordonnées invalides';return;}
  $db=App::db();$db->beginTransaction();try {
   $s=$db->prepare("SELECT * FROM batteries WHERE id=? AND status='available' FOR UPDATE");$s->execute([(int)($_POST['battery_id']??0)]);$battery=$s->fetch();if(!$battery)throw new \RuntimeException('Batterie indisponible');
   $price=$db->query('SELECT * FROM pricing WHERE id=1')->fetch();$reference='TBP-'.strtoupper(bin2hex(random_bytes(8)));
   $data=[$reference,$battery['id'],trim($_POST['name']),$_POST['email'],$_POST['phone'],(int)$price['rental_fee'],(int)($battery['deposit_override']??$price['default_deposit']),(int)$price['late_percent'],(int)$price['duration_minutes']];
   (new RentalRepository)->create($data);$db->prepare("UPDATE batteries SET status='reserved' WHERE id=?")->execute([$battery['id']]);$db->commit();
   $r=(new RentalRepository)->find($reference);$url=(new PaiementProService)->initiate($r);App::redirect($url);
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();error_log($e);http_response_code(503);echo 'Location indisponible. Réessayez ou contactez le support.';}
 }
 public function status():void {$reference=$_GET['reference']??'';$r=(new RentalRepository)->find($reference);if(!$r){http_response_code(404);return;}header('Content-Type: application/json');echo json_encode(['reference'=>$r['reference'],'status'=>$r['status']]);}
}
