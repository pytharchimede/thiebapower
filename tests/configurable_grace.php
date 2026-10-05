<?php
spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
$r=['billing_rule'=>'prorata_grace5','deposit'=>200,'rental_fee'=>600,'duration_minutes'=>60];
foreach([[5,300,0],[5,301,1],[0,60,10],[10,600,0],[10,660,10]] as [$g,$late,$expected]){$r['grace_minutes']=$g;$b=\App\Services\RentalBilling::calculate($r,$late);if($b['calculated_charge']!==$expected)throw new RuntimeException('Grace boundary failed');}
unset($r['grace_minutes']);if(\App\Services\RentalBilling::calculate($r,300)['deduction']!==0)throw new RuntimeException('Legacy default changed');
echo "Grace: zero, five and ten minute periods, exact boundary, rounding and existing default OK\n";
