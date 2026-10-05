<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\PromotionService;
final class PromotionController
{
 public function index():void {Auth::requirePermission('pricing.view');$archivedView=($_GET['archived']??'')==='1';$promotions=App::db()->query('SELECT p.*,(SELECT COUNT(*) FROM rental_promotion_redemptions x WHERE x.promotion_id=p.id) recorded_uses,(SELECT COUNT(*) FROM rental_promotion_redemptions x JOIN rentals r ON r.id=x.rental_id WHERE x.promotion_id=p.id AND '.PromotionService::usageCondition().') uses FROM promotions p WHERE p.archived_at IS '.($archivedView?'NOT NULL':'NULL').' ORDER BY p.id DESC')->fetchAll();$enabled=PromotionService::enabled();App::view('promotions',compact('promotions','enabled','archivedView'));}
 public function create():void {
  Auth::requirePermission('pricing.manage',true);$code=strtoupper(trim((string)($_POST['code']??'')));$kind=(string)($_POST['kind']??'');$discount=filter_var($_POST['discount']??null,FILTER_VALIDATE_INT);$uses=filter_var($_POST['max_uses']??null,FILTER_VALIDATE_INT);$min=filter_var($_POST['minimum_completed']??0,FILTER_VALIDATE_INT);$perPhone=filter_var($_POST['max_uses_per_phone']??1,FILTER_VALIDATE_INT);
  $start=(string)($_POST['start']??'');$end=(string)($_POST['end']??'');$a=\DateTimeImmutable::createFromFormat('!Y-m-d',$start);$b=\DateTimeImmutable::createFromFormat('!Y-m-d',$end);
  if(!preg_match('/^[A-Z0-9_-]{3,40}$/D',$code)||!in_array($kind,['campaign','loyalty'],true)||$discount===false||$discount<1||$discount>1000000||$uses===false||$uses<1||$uses>100000||$perPhone===false||$perPhone<1||$perPhone>100000||$min===false||$min<0||$min>10000||!$a||!$b||$a->format('Y-m-d')!==$start||$b->format('Y-m-d')!==$end||$b<$a){http_response_code(422);echo 'Offre invalide';return;}
  $db=App::db();$q=$db->prepare('SELECT 1 FROM promotions WHERE code=?');$q->execute([$code]);if($q->fetchColumn()){http_response_code(409);echo 'Ce code existe déjà.';return;}
  $db->prepare('INSERT INTO promotions(code,kind,discount_amount,minimum_completed,max_uses,max_uses_per_phone,starts_at,ends_at,enabled,is_public,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$code,$kind,$discount,$min,$uses,$perPhone,$start.' 00:00:00',$b->modify('+1 day')->format('Y-m-d').' 00:00:00',isset($_POST['enabled'])?1:0,$kind==='campaign'&&isset($_POST['is_public'])?1:0,Auth::id()]);Audit::event('promotion.created','promotion',$code);App::redirect('/admin/promotions');
 }
 public function limits():void {
  Auth::requirePermission('pricing.manage',true);$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$total=filter_var($_POST['max_uses']??null,FILTER_VALIDATE_INT);$phone=filter_var($_POST['max_uses_per_phone']??null,FILTER_VALIDATE_INT);
  if(!$id||$total===false||$total<1||$total>100000||$phone===false||$phone<1||$phone>100000){http_response_code(422);echo 'Limites invalides.';return;}
  $db=App::db();$db->beginTransaction();try{
   $q=$db->prepare('SELECT id FROM promotions WHERE id=? AND archived_at IS NULL FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn()){$db->rollBack();http_response_code(409);echo 'Code indisponible.';return;}
   $db->prepare('UPDATE promotions SET max_uses=?,max_uses_per_phone=? WHERE id=?')->execute([$total,$phone,$id]);$db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  Audit::event('promotion.limits','promotion',(string)$id,['max_uses'=>$total,'max_uses_per_phone'=>$phone]);App::redirect('/admin/promotions');
 }
 public function toggle():void {Auth::requirePermission('pricing.manage',true);$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$enabled=($_POST['enabled']??'')==='1'?1:0;App::db()->prepare('UPDATE promotions SET enabled=? WHERE id=? AND archived_at IS NULL')->execute([$enabled,$id]);Audit::event('promotion.toggled','promotion',(string)$id,['enabled'=>$enabled]);App::redirect('/admin/promotions');}
 public function visibility():void {
  Auth::requirePermission('pricing.manage',true);$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$public=($_POST['is_public']??'')==='1'?1:0;
  App::db()->prepare("UPDATE promotions SET is_public=? WHERE id=? AND kind='campaign' AND archived_at IS NULL")->execute([$public,$id]);Audit::event('promotion.visibility','promotion',(string)$id,['is_public'=>$public]);App::redirect('/admin/promotions');
 }
 public function remove():void {
  Auth::requirePermission('pricing.manage',true);
  $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);$action=$_POST['action']??'';if(!$id||!in_array($action,['archive','delete'],true)){http_response_code(422);echo 'Action invalide.';return;}$archive=$action==='archive';$db=App::db();$db->beginTransaction();
  try {
   $q=$db->prepare('SELECT enabled,archived_at FROM promotions WHERE id=? FOR UPDATE');$q->execute([$id]);$p=$q->fetch();
   $q=$db->prepare('SELECT COUNT(*) FROM rental_promotion_redemptions WHERE promotion_id=?');$q->execute([$id]);$uses=(int)$q->fetchColumn();
   if(!$p||($archive&&($uses===0||(int)$p['enabled']===1))||(!$archive&&$uses>0)){
    $db->rollBack();http_response_code(409);echo 'Désactivez un code utilisé avant de l’archiver. Seuls les codes jamais utilisés peuvent être supprimés.';return;
   }
   if($archive)$db->prepare('UPDATE promotions SET archived_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
   else $db->prepare('DELETE FROM promotions WHERE id=?')->execute([$id]);
   $db->commit();Audit::event($archive?'promotion.archived':'promotion.deleted','promotion',(string)$id);App::redirect('/admin/promotions');
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 public function offers():void {
  header('Cache-Control: no-store');$promotions=[];
  if(PromotionService::enabled()&&\App\Services\PublicExperienceSettings::all()['visible']['offers'])$promotions=App::db()->query("SELECT p.code,p.discount_amount,p.ends_at,p.max_uses_per_phone FROM promotions p WHERE p.archived_at IS NULL AND p.is_public=1 AND p.kind='campaign' AND p.enabled=1 AND p.starts_at<=UTC_TIMESTAMP() AND p.ends_at>UTC_TIMESTAMP() AND (SELECT deposit_enabled FROM pricing WHERE id=1)=1 AND (SELECT COUNT(*) FROM rental_promotion_redemptions x JOIN rentals r ON r.id=x.rental_id WHERE x.promotion_id=p.id AND ".PromotionService::usageCondition().")<p.max_uses ORDER BY p.ends_at")->fetchAll();
  App::view('public_offers',compact('promotions'));
 }
 public function preview():void {
  header('Content-Type: application/json');header('Cache-Control: no-store');
  if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){http_response_code(415);echo '{}';return;}
  $input=json_decode(file_get_contents('php://input',false,null,0,4000),true);if(!is_array($input)){http_response_code(422);echo '{}';return;}
  try{$db=App::db();$price=$db->query('SELECT rental_fee,default_deposit,deposit_enabled FROM pricing WHERE id=1')->fetch();$q=$db->prepare("SELECT deposit_override FROM batteries WHERE id=? AND station_imei=? AND status='available'");$q->execute([(int)($input['battery_id']??0),(string)($input['station_code']??'')]);$battery=$q->fetch();if(!$battery)throw new \InvalidArgumentException('Sélectionnez une batterie disponible.');$deposit=(int)$price['deposit_enabled']===1?(int)($battery['deposit_override']??$price['default_deposit']):0;$offer=(new PromotionService)->assess((string)($input['code']??''),(string)($input['phone']??''),$deposit,(string)($input['previous_token']??''));echo json_encode(['fee'=>(int)$price['rental_fee'],'deposit'=>$offer['deposit'],'discount'=>$offer['discount'],'code'=>$offer['code']]);}
  catch(\InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
  catch(\Throwable $e){error_log('Promotion preview: '.$e->getMessage());http_response_code(503);echo json_encode(['error'=>'La vérification est momentanément indisponible. Réessayez.']);}
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
   $q=$db->prepare("SELECT code,discount_amount,ends_at FROM promotions WHERE referrer_rental_id=? AND kind='referral' AND archived_at IS NULL AND enabled=1 AND ends_at>UTC_TIMESTAMP()");$q->execute([$id]);$offer=$q->fetch();
   if(!$offer){$fee=(int)$db->query('SELECT IF(deposit_enabled=1,default_deposit,0) FROM pricing WHERE id=1')->fetchColumn();$discount=(int)App::env('REFERRAL_DISCOUNT_XOF','100');if($discount<1||$fee<1){$db->rollBack();http_response_code(409);echo '{"error":"Offre de parrainage indisponible au tarif actuel."}';return;}
    $code='AMI-'.strtoupper(bin2hex(random_bytes(5)));$db->prepare("INSERT INTO promotions(code,kind,discount_amount,max_uses,starts_at,ends_at,enabled,referrer_rental_id) VALUES(?,'referral',?,10,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),1,?)")->execute([$code,$discount,$id]);$offer=['code'=>$code,'discount_amount'=>$discount];}
   $db->commit();echo json_encode(['code'=>$offer['code'],'discount'=>(int)$offer['discount_amount']]);
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
