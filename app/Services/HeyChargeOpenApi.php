<?php
namespace App\Services;
use App\Core\App;
final class HeyChargeOpenApi {
 public function mode():string {return IntegrationSettings::all()['heycharge'];}
 public function configured():bool {return App::env('HEYCHARGE_API_BASE')!=='' && App::env('HEYCHARGE_API_KEY')!=='';}
 /** Simulation explicitly never sends a physical command or activates a rental. */
 public function previewRelease(string $stationSerial,string $batterySerial,string $orderReference):array {
  if($this->mode()!=='simulation')throw new \LogicException('Simulation désactivée');
  return ['simulated'=>true,'station'=>$stationSerial,'battery'=>$batterySerial,'reference'=>$orderReference,'physical_release'=>false];
 }
 public function release(string $stationSerial,string $batterySerial,string $orderReference):void {
  throw new \RuntimeException('Commande Open API HeyCharge non documentée ; libération désactivée');
 }
 public function verifyReturn(array $event):bool {return false;}
}
