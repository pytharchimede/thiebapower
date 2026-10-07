<?php
namespace App\Services;
use App\Core\App;
final class StationProfitability
{
 public static function metrics(int $revenue,int $deductions,int $costs,int $investment):array {
  $net=$revenue+$deductions-$costs;
  return ['net'=>$net,'remaining'=>max(0,$investment-$net),'recovered_percent'=>$investment>0?round(max(0,$net)*100/$investment,1):null];
 }
 public function report():array {
  // Only verified production payments and confirmed returns; callback retries cannot duplicate income.
  $rows=App::db()->query("SELECT s.imei,s.label,COALESCE(p.investment,0) investment,COALESCE(r.revenue,0) revenue,COALESCE(r.rentals,0) rentals,COALESCE(d.deductions,0) deductions,COALESCE(c.costs,0) costs FROM stations s LEFT JOIN station_profiles p ON p.station_imei=s.imei LEFT JOIN (SELECT r.station_code,SUM(r.rental_fee) revenue,COUNT(*) rentals FROM rentals r WHERE r.payment_environment='production' AND r.started_at IS NOT NULL AND EXISTS(SELECT 1 FROM payment_notifications n WHERE n.rental_id=r.id AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0') GROUP BY r.station_code) r ON r.station_code=s.imei LEFT JOIN (SELECT r.station_code,SUM(d.deduction) deductions FROM deposit_settlements d JOIN rentals r ON r.id=d.rental_id WHERE r.payment_environment='production' AND r.status='returned' AND d.status='refunded' GROUP BY r.station_code) d ON d.station_code=s.imei LEFT JOIN (SELECT station_imei,SUM(amount) costs FROM station_costs GROUP BY station_imei) c ON c.station_imei=s.imei ORDER BY s.label,s.imei")->fetchAll();
  foreach($rows as &$r)$r+=self::metrics((int)$r['revenue'],(int)$r['deductions'],(int)$r['costs'],(int)$r['investment']);unset($r);return $rows;
 }
}
