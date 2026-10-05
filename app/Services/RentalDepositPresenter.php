<?php
namespace App\Services;
final class RentalDepositPresenter
{
    public static function snapshot(array $r):array
    {
        return ['grace'=>max(0,(int)($r['grace_minutes']??5)),'paid'=>!empty($r['deposit_payment_verified_at']),'deposit'=>max(0,(int)$r['deposit']),'active'=>$r['status']==='active','due'=>!empty($r['due_at'])?strtotime($r['due_at'].' UTC'):0,'end'=>!empty($r['returned_at'])?strtotime($r['returned_at'].' UTC'):0,'rule'=>$r['billing_rule']??'legacy_hourly','fee'=>max(0,(int)$r['rental_fee']),'duration'=>max(1,(int)$r['duration_minutes']),'latePercent'=>max(0,(int)$r['late_percent'])];
    }
}
