<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\PromotionService;
final class PromotionController
{
 public function index():void {Auth::requirePermission('pricing.view');$promotions=App::db()->query('SELECT p.*,COUNT(x.rental_id) uses FROM promotions p LEFT JOIN rental_promotion_redemptions x ON x.promotion_id=p.id GROUP BY p.id ORDER BY p.id DESC')->fetchAll();$enabled=PromotionService::enabled();App::view('promotions',compact('promotions','enabled'));}
 public function create():void {
  Auth::requirePermission('pricing.manage',true);$code=strtoupper(trim((string)($_POST['code']??'')));$kind=(string)($_POST['kind']??'');$discount=filter_var($_POST['discount']??null,FILTER_VALIDATE_INT);$uses=filter_var($_POST['max_uses']??null,FILTER_VALIDATE_INT);$min=filter_var($_POST['minimum_completed']??0,FILTER_VALIDATE_INT);
  $start=(string)($_POST['start']??'');$end=(string)($_POST['end']??'');$a=\DateTimeImmutable::createFromFormat('!Y-m-d',$start);$b=\DateTimeImmutable::createFromFormat('!Y-m-d',$end);
  if(!preg_match('/^[A-Z0-9_-]{3,40}$/D',$code)||!in_array($kind,['campaign','loyalty'],true)||$discount===false||$discount<1||$discount>1000000||$uses===false||$uses<1||$uses>100000||$min===false||$min<0||$min>10000||!$a||!$b||$a->format('Y-m-d')!==$start||$b->format('Y-m-d')!==$end||$b<$a){http_response_code(422);echo 'Offre invalide';return;}
  $db=App::db();$q=$db->prepare('SELECT 1 FROM promotions WHERE code=?');$q->execute([$code]);if($q->fetchColumn()){http_response_code(409);echo 'Ce code existe déjà.';return;}
  $db->prepare('INSERT INTO promotions(code,kind,discount_amount,minimum_completed,max_uses,starts_at,ends_at,enabled,created_by) VALUES(?,?,?,?,?,?,?,1,?)')->execute([$code,$kind,$discount,$min,$uses,$start.' 00:00:00',$b->modify('+1 day')->format('Y-m-d').' 00:00:00',Auth::id()]);Audit::event('promotion.created','promotion',$code);App::redirect('/admin/promotions');
 }
 public function toggle():void {Auth::requirePermission('pricing.manage',true);$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$enabled=($_POST['enabled']??'')==='1'?1:0;App::db()->prepare('UPDATE promotions SET enabled=? WHERE id=?')->execute([$enabled,$id]);Audit::event('promotion.toggled','promotion',(string)$id,['enabled'=>$enabled]);App::redirect('/admin/promotions');}
 public function visibility():void {
  Auth::requirePermission('pricing.manage',true);$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$public=($_POST['is_public']??'')==='1'?1:0;
  App::db()->prepare("UPDATE promotions SET is_public=? WHERE id=? AND kind='campaign'")->execute([$public,$id]);Audit::event('promotion.visibility','promotion',(string)$id,['is_public'=>$public]);App::redirect('/admin/promotions');
 }
 public function offers():void {
  header('Cache-Control: no-store');$promotions=[];
  if(PromotionService::enabled())$promotions=App::db()->query("SELECT p.code,p.discount_amount,p.ends_at FROM promotions p WHERE p.is_public=1 AND p.kind='campaign' AND p.enabled=1 AND p.starts_at<=UTC_TIMESTAMP() AND p.ends_at>UTC_TIMESTAMP() AND p.discount_amount<(SELECT rental_fee FROM pricing WHERE id=1) AND (SELECT COUNT(*) FROM rental_promotion_redemptions x WHERE x.promotion_id=p.id)<p.max_uses ORDER BY p.ends_at")->fetchAll();
  App::view('public_offers',compact('promotions'));
 }
 public function preview():void {
  header('Content-Type: application/json');header('Cache-Control: no-store');
  if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){http_response_code(415);echo '{}';return;}
  $input=json_decode(file_get_contents('php://input',false,null,0,4000),true);if(!is_array($input)){http_response_code(422);echo '{}';return;}
  try{$fee=(int)App::db()->query('SELECT rental_fee FROM pricing WHERE id=1')->fetchColumn();$offer=(new PromotionService)->assess((string)($input['code']??''),(string)($input['phone']??''),$fee,(string)($input['previous_token']??''));echo json_encode(['fee'=>$offer['fee'],'discount'=>$offer['discount'],'code'=>$offer['code']]);}
  catch(\InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
 }
 public function referral():void {
  header('Content-Type: application/json');header('Cache-Control: no-store');
  if(!PromotionService::enabled()){http_response_code(404);echo '{}';return;}
  if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){http_response_code(415);echo '{}';return;}
  $input=json_decode(file_get_contents('php://input',false,null,0,4000),true);$token=is_array($input)?($input['token']??''):'';
  if(!is_string($token)||!preg_match('/^[a-f0-9]{32}$/D',$token)){http_response_code(422);echo '{}';return;}
  $db=App::db();$db->beginTransaction();try{
   $q=$db->prepare("SELECT id FROM rentals WHERE checkout_token=? AND status='returned' AND payment_environment='production' AND EXISTS(SELECT 1 FROM payment_notifications n WHERE n.rental_id=rentals.id AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0') FOR UPDATE");$q->execute([$token]);$id=$q->fetchColumn();
   if(!$id){$db->rollBack();http_response_code(404);echo '{"error":"Terminez une location pour inviter vos proches."}';return;}
   $q=$db->prepare("SELECT code,discount_amount,ends_at FROM promotions WHERE referrer_rental_id=? AND kind='referral' AND enabled=1 AND ends_at>UTC_TIMESTAMP()");$q->execute([$id]);$offer=$q->fetch();
   if(!$offer){$fee=(int)$db->query('SELECT rental_fee FROM pricing WHERE id=1')->fetchColumn();$discount=(int)App::env('REFERRAL_DISCOUNT_XOF','100');if($discount<1||$discount>=$fee){$db->rollBack();http_response_code(409);echo '{"error":"Offre de parrainage indisponible au tarif actuel."}';return;}
    $code='AMI-'.strtoupper(bin2hex(random_bytes(5)));$db->prepare("INSERT INTO promotions(code,kind,discount_amount,max_uses,starts_at,ends_at,enabled,referrer_rental_id) VALUES(?,'referral',?,10,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),1,?)")->execute([$code,$discount,$id]);$offer=['code'=>$code,'discount_amount'=>$discount];}
   $db->commit();echo json_encode(['code'=>$offer['code'],'discount'=>(int)$offer['discount_amount']]);
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
