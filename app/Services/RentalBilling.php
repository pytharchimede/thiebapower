<?php
namespace App\Services;
use App\Models\Rental;
final class RentalBilling
{
    public const RULE = 'prorata_grace5';
    /** First five late minutes are free; charge elapsed time, round once to the next FCFA. */
    public static function calculate(array $rental, int $lateSeconds): array
    {
        $lateSeconds = max(0, $lateSeconds);
        $deposit = max(0, (int)$rental['deposit']);
        if (($rental['billing_rule'] ?? 'legacy_hourly') !== self::RULE) {
            $charge = Rental::due($deposit, (int)$rental['late_percent'], $lateSeconds);
            return ['late_seconds'=>$lateSeconds,'billable_seconds'=>$lateSeconds,'calculated_charge'=>$charge,'deduction'=>$charge,'refund'=>$deposit-$charge];
        }
        $seconds = max(0, $lateSeconds - 300);
        $duration = max(1, (int)$rental['duration_minutes']) * 60;
        $numerator = max(0, (int)$rental['rental_fee']) * $seconds;
        $charge = intdiv($numerator, $duration) + ($numerator % $duration > 0 ? 1 : 0);
        $deduction = min($deposit, $charge);
        return ['late_seconds'=>$lateSeconds,'billable_seconds'=>$seconds,'calculated_charge'=>$charge,'deduction'=>$deduction,'refund'=>$deposit-$deduction];
    }
}
