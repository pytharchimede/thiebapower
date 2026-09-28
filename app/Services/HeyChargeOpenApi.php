<?php
namespace App\Services;
use App\Core\App;
final class HeyChargeOpenApi {
 /** Option 3: provider-hosted station server. The command paths and signatures need the official API contract. */
 public function configured():bool {return App::env('HEYCHARGE_API_BASE')!=='' && App::env('HEYCHARGE_API_KEY')!=='';}
 public function release(string $stationSerial,string $batterySerial,string $orderReference):void {
  throw new \RuntimeException('Contrat Open API HeyCharge requis avant libération');
 }
 public function verifyReturn(array $event):bool {return false;}
}
