<?php
namespace App\Services;
final class PaymentVerification {
 public function ready():bool {return false;}
 public function verified(array $payload,array $rental):bool {
  // Le PDF fourni ne précise ni l'algorithme du hashcode ni une API de vérification.
  // Aucune notification ne peut autoriser la libération sans procédure documentée et testée.
  return false;
 }
}
