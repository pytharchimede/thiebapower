<?php
namespace App\Services;

final class WithdrawalQuote
{
    public static function rules(): array
    {
        $settings=DepositWallet::settings();
        $rules=json_decode((string)($settings['fee_rules']??'{}'),true);
        return is_array($rules)?$rules:[];
    }
    public static function calculate(int $balance,string $channel,array $rules,int $amount=0): array
    {
        // Same configured payout fee schedule; integer arithmetic rounds each fee upward.
        DepositWallet::fee(1,$channel,$rules);
        $low=0;$high=min(999999999,max(0,$balance));
        while($low<$high){
            $mid=intdiv($low+$high+1,2);
            if($mid+DepositWallet::fee($mid,$channel,$rules)<=$balance)$low=$mid;else $high=$mid-1;
        }
        $fee=$amount>0?DepositWallet::fee($amount,$channel,$rules):0;
        return ['balance'=>$balance,'maximum'=>$low,'amount'=>$amount,'fee'=>$fee,'total'=>$amount+$fee,'remaining'=>$balance-$amount-$fee,'fixed'=>$rules[$channel]['fixed'],'basis_points'=>$rules[$channel]['basis_points']];
    }
    public static function preview(?int $balance,array $rules): array
    {
        $channels=[];
        foreach(['WAVECI','OMCIV','MOMOCI','FLOOZ'] as $channel){
            try {if($balance!==null)$channels[$channel]=self::calculate($balance,$channel,$rules);}
            catch(\LogicException){}
        }
        return ['balance'=>$balance,'channels'=>$channels];
    }
}
