<?php
namespace App\Services;
use App\Core\App;
/** Opt-in only. Discounts are captured once in the existing checkout transaction. */
final class PromotionService
{
 public static function enabled():bool {return App::env('PROMOTIONS_ENABLED')==='1';}
 public static function discountedFee(int $fee,int $discount):int {
  if($discount<1||$discount>=$fee)throw new \InvalidArgumentException('Ce code ne s’applique pas au tarif actuel.');return $fee-$discount;
 }
 public static function phoneKey(string $phone):string {return hash('sha256',substr(preg_replace('/[^0-9]/','',$phone),-10));}
 public function assess(string $code,string $phone,int $fee,string $previousToken='',bool $lock=false):array {
  if(!self::enabled())throw new \InvalidArgumentException('Les offres promotionnelles ne sont pas activées.');
  $code=strtoupper(trim($code));if(!preg_match('/^[A-Z0-9_-]{3,40}$/D',$code))throw new \InvalidArgumentException('Code invalide.');
  $db=App::db();$q=$db->prepare("SELECT * FROM promotions WHERE code=? AND enabled=1 AND starts_at<=UTC_TIMESTAMP() AND ends_at>UTC_TIMESTAMP()".($lock?' FOR UPDATE':''));$q->execute([$code]);$promotion=$q->fetch();
  if(!$promotion)throw new \InvalidArgumentException('Code indisponible ou expiré.');
  $q=$db->prepare('SELECT COUNT(*) total,COALESCE(SUM(phone_key=?),0) used FROM rental_promotion_redemptions WHERE promotion_id=?');$q->execute([self::phoneKey($phone),$promotion['id']]);$usage=$q->fetch();
  if((int)$usage['total']>=(int)$promotion['max_uses']||(int)$usage['used']>0)throw new \InvalidArgumentException('Ce code a atteint sa limite d’utilisation.');
  if($promotion['referrer_rental_id']){
   $q=$db->prepare('SELECT customer_phone FROM rentals WHERE id=?');$q->execute([$promotion['referrer_rental_id']]);$referrer=$q->fetchColumn();
   if($referrer&&self::phoneKey($referrer)===self::phoneKey($phone))throw new \InvalidArgumentException('Le parrainage est destiné à une autre personne.');
  }
  if($promotion['kind']==='loyalty'){
   if(!preg_match('/^[a-f0-9]{32}$/D',$previousToken))throw new \InvalidArgumentException('Retrouvez une location précédente sur cet appareil pour utiliser cette offre.');
   $q=$db->prepare("SELECT customer_phone FROM rentals WHERE checkout_token=? AND status='returned' AND payment_environment='production' AND EXISTS(SELECT 1 FROM payment_notifications n WHERE n.rental_id=rentals.id AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0')");$q->execute([$previousToken]);$verifiedPhone=$q->fetchColumn();
   if(!$verifiedPhone||self::phoneKey($verifiedPhone)!==self::phoneKey($phone))throw new \InvalidArgumentException('Cette offre est réservée aux clients réguliers.');
   $q=$db->prepare("SELECT COUNT(*) FROM rentals r WHERE RIGHT(REPLACE(r.customer_phone,' ',''),10)=? AND r.status='returned' AND r.payment_environment='production' AND EXISTS(SELECT 1 FROM payment_notifications n WHERE n.rental_id=r.id AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0')");$q->execute([substr(preg_replace('/[^0-9]/','',$phone),-10)]);
   if((int)$q->fetchColumn()<(int)$promotion['minimum_completed'])throw new \InvalidArgumentException('Nombre de locations terminées insuffisant pour cette offre.');
  }
  return ['id'=>(int)$promotion['id'],'code'=>$code,'discount'=>(int)$promotion['discount_amount'],'fee'=>self::discountedFee($fee,(int)$promotion['discount_amount']),'original_fee'=>$fee];
 }
 public function record(int $rentalId,array $offer,string $phone):void {
  App::db()->prepare('INSERT INTO rental_promotion_redemptions(rental_id,promotion_id,phone_key,original_fee,discount_amount) VALUES(?,?,?,?,?)')->execute([$rentalId,$offer['id'],self::phoneKey($phone),$offer['original_fee'],$offer['discount']]);
 }
}
