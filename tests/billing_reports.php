<?php
declare(strict_types=1);
spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\RentalBilling;
use App\Services\BrandedReportPdf;
use App\Services\RentalReceipt;
$r=['billing_rule'=>RentalBilling::RULE,'deposit'=>200,'rental_fee'=>100,'duration_minutes'=>15,'late_percent'=>10];
foreach([[0,0],[299,0],[300,0],[301,1],[360,7],[900,67],[1200,100],[2100,200],[3600,200]] as [$seconds,$expected]){
 $b=RentalBilling::calculate($r,$seconds);if($b['deduction']!==$expected||$b['refund']!==200-$expected)throw new RuntimeException('Grace/prorata boundary: '.$seconds);
}
if(RentalBilling::calculate(array_replace($r,['deposit'=>0]),900)['deduction']!==0)throw new RuntimeException('No deposit deduction');
if(RentalBilling::calculate(array_replace($r,['billing_rule'=>'legacy_hourly']),1)['deduction']!==20)throw new RuntimeException('Legacy contract changed');
$pdf=(new BrandedReportPdf())->render('Locations et historique',['Référence','Client','Montant','État'],array_fill(0,120,['TBP-0123456789ABCDEF',"Nom long avec retour\nDeuxième ligne",'100 FCFA','En cours']),[['label'=>'Locations','value'=>'120']], 'https://thiebapower.com/admin');
if(substr_count($pdf,'/Type /Page ')<2||!str_contains($pdf,'THIEBA')||!str_contains($pdf,'Page 1/')||substr_count($pdf,' re f')<300)throw new RuntimeException('Report pagination/QR');
$r+=['reference'=>'TBP-0123456789ABCDEF','customer_name'=>'Client test','customer_phone'=>'+2250700000000','station_code'=>'DCHEY02603000938','battery_serial'=>'DCHA63000390','created_at'=>'2026-10-03 10:00:00','started_at'=>'2026-10-03 10:01:00','due_at'=>'2026-10-03 10:16:00','returned_at'=>'2026-10-03 10:31:00','late_charge'=>67];
$receipt=RentalReceipt::report($r,['deduction'=>67,'refund_amount'=>133,'status'=>'pending'],'2026-10-03 10:00:30');
if($receipt['cards'][3]['value']!=='133 FCFA'||!str_contains($receipt['rows'][8][2],'À rembourser'))throw new RuntimeException('Pending refund is not a completed refund');
echo "Billing boundaries, legacy preservation, report pagination and receipt status OK\n";
