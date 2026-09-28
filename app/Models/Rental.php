<?php
namespace App\Models;
final class Rental {
 public static function due(int $deposit,int $hourlyPercent,int $secondsLate):int { if($secondsLate<=0)return 0; return min($deposit,(int)ceil($deposit*$hourlyPercent*ceil($secondsLate/3600)/100)); }
}
