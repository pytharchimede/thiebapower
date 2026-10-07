<?php
namespace App\Repositories;
use App\Core\App;
final class RentalRepository {
 public function find(string $reference):?array {
  $s=App::db()->prepare('SELECT * FROM rentals WHERE reference = ?');
  $s->execute([$reference]);
  return $s->fetch()?:null;
 }
 public function create(array $data):void {
  $s=App::db()->prepare("INSERT INTO rentals(reference,battery_id,customer_name,customer_email,customer_phone,rental_fee,deposit,late_percent,duration_minutes,grace_minutes,billing_rule,status) VALUES(?,?,?,?,?,?,?,?,?,(SELECT grace_minutes FROM pricing WHERE id=1),'prorata_grace5','pending_payment')");
  $s->execute($data);
  \App\Services\SmsProcessService::queue('payment_pending',(int)App::db()->lastInsertId());
 }
}
