<?php
namespace App\Services;
final class HeyChargeService {
 public function release(array $rental):void {
  // À relier au mode Open API HeyCharge après obtention des endpoints,
  // de l'authentification et du schéma d'événements propres au terminal.
  throw new \RuntimeException('Libération HeyCharge non configurée');
 }
}
