<?php
require dirname(__DIR__).'/app/Services/DepositWallet.php';
require dirname(__DIR__).'/app/Services/WithdrawalQuote.php';
use App\Services\WithdrawalQuote;
use App\Services\DepositWallet;
$rules=['WAVECI'=>['fixed'=>0,'basis_points'=>200],'OMCIV'=>['fixed'=>10,'basis_points'=>200]];
foreach([[5008,'WAVECI',4909],[928,'WAVECI',909],[200,'WAVECI',196],[0,'WAVECI',0],[100,'OMCIV',88],[5008,'OMCIV',4900],[5,'OMCIV',0]] as [$balance,$channel,$expected]){
 $q=WithdrawalQuote::calculate($balance,$channel,$rules);
 if($q['maximum']!==$expected)throw new RuntimeException('Incorrect maximum for '.$balance);
}
foreach([0,1,199,200,250,10000] as $bps){
 foreach([0,10,100] as $fixed){
  $rule=['WAVECI'=>['fixed'=>$fixed,'basis_points'=>$bps]];
  foreach([0,1,10,100,999,5008,999999999] as $balance){
   $q=WithdrawalQuote::calculate($balance,'WAVECI',$rule);$max=$q['maximum'];
   if($max>0&&$max+DepositWallet::fee($max,'WAVECI',$rule)>$balance)throw new RuntimeException('Maximum would overdraw wallet');
   if($max<999999999&&$max+1+DepositWallet::fee($max+1,'WAVECI',$rule)<=$balance)throw new RuntimeException('Maximum leaves an available whole FCFA');
  }
 }
}
if(WithdrawalQuote::preview(null,$rules)['channels']!==[])throw new RuntimeException('Unavailable balance must disable quotes');
if(WithdrawalQuote::preview(5008,[])['channels']!==[])throw new RuntimeException('Missing fees must disable quotes');
echo "Withdrawal maximum: fee rounding, fixed fees, zero balance, unavailable data and exact maximum boundaries OK\n";
