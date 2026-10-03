<?php
namespace App\Services;
use App\Models\Rental;
final class DepositSettlementService {
 /** Settlement is computed only from verified station return timestamps. */
 public function calculate(array $rental, \DateTimeImmutable $returnedAt):array {
  if (empty($rental['due_at'])) throw new \LogicException('Date limite absente');
  $due = new \DateTimeImmutable($rental['due_at'], new \DateTimeZone('UTC'));
  $late = max(0, $returnedAt->getTimestamp() - $due->getTimestamp());
  return RentalBilling::calculate($rental, $late);
 }
}
