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
 public static function discountedDeposit(int $deposit,int $discount):int {
  if($deposit<1||$discount<1)throw new \InvalidArgumentException('Le code promo nécessite une caution active.');return max(0,$deposit-$discount);
 }
 /** Successful payments consume the code; unpaid reservations hold it only for two minutes. */
 public static function usageCondition():string {
  return "(EXISTS(SELECT 1 FROM payment_notifications n WHERE n.rental_id=r.id AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0') OR (r.status IN ('pending_payment','payment_failed') AND r.reservation_expires_at>UTC_TIMESTAMP()))";
 }
 public function assess(string $code,string $phone,int $fee,string $previousToken='',bool $lock=false):array {
  if(!self::enabled())throw new \InvalidArgumentException('Les offres promotionnelles ne sont pas activées.');
  $code=strtoupper(trim($code));if(!preg_match('/^[A-Z0-9_-]{3,40}$/D',$code))throw new \InvalidArgumentException('Code invalide.');
  $db=App::db();$q=$db->prepare("SELECT * FROM promotions WHERE code=? AND archived_at IS NULL AND enabled=1 AND starts_at<=UTC_TIMESTAMP() AND ends_at>UTC_TIMESTAMP()".($lock?' FOR UPDATE':''));$q->execute([$code]);$promotion=$q->fetch();
  if(!$promotion)throw new \InvalidArgumentException('Code indisponible ou expiré.');
  // Locking reads see the latest committed reservations after waiting for the promotion lock.
  $q=$db->prepare('SELECT x.phone_key FROM rental_promotion_redemptions x JOIN rentals r ON r.id=x.rental_id WHERE x.promotion_id=? AND '.self::usageCondition().($lock?' FOR UPDATE':''));$q->execute([$promotion['id']]);$keys=$q->fetchAll(\PDO::FETCH_COLUMN);$usage=['total'=>count($keys),'used'=>count(array_filter($keys,static fn($key)=>$key===self::phoneKey($phone)))];
  if((int)$usage['used']>=(int)$promotion['max_uses_per_phone'])throw new \InvalidArgumentException('Ce numéro a atteint sa limite de '.$promotion['max_uses_per_phone'].' utilisation(s) pour ce code. Un paiement en attente réserve une utilisation pendant deux minutes.');
  if((int)$usage['total']>=(int)$promotion['max_uses'])throw new \InvalidArgumentException('Ce code a atteint sa limite globale. Un paiement en attente réserve une utilisation pendant deux minutes.');
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
  return ['id'=>(int)$promotion['id'],'code'=>$code,'discount'=>min($fee,(int)$promotion['discount_amount']),'deposit'=>self::discountedDeposit($fee,(int)$promotion['discount_amount']),'original_deposit'=>$fee];
 }
 public function record(int $rentalId,array $offer,string $phone):void {
  App::db()->prepare('INSERT INTO rental_promotion_redemptions(rental_id,promotion_id,phone_key,original_fee,discount_amount,discount_target,original_deposit) VALUES(?,?,?,?,?,"deposit",?)')->execute([$rentalId,$offer['id'],self::phoneKey($phone),$offer['original_fee']??0,$offer['discount'],$offer['original_deposit']]);
 }
}
