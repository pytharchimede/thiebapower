<?php
namespace App\Services;
final class RentalWatch
{
 public static function reasons(array $r,int $now):array {
  $reasons=[];
  if($r['status']==='release_failed')$reasons[]='Paiement reçu : sortie de batterie à vérifier';
  if($r['status']==='payment_review')$reasons[]='Paiement tardif à rapprocher';
  if($r['status']==='releasing'&&!empty($r['release_command_at'])&&strtotime($r['release_command_at'].' UTC')<=$now-120)$reasons[]='Commande de sortie sans confirmation depuis 2 minutes';
  if($r['status']==='pending_payment'&&!empty($r['reservation_expires_at'])&&strtotime($r['reservation_expires_at'].' UTC')<=$now)$reasons[]='Réservation expirée encore en attente';
  if($r['status']==='active'&&!empty($r['due_at'])&&strtotime($r['due_at'].' UTC')<=$now)$reasons[]='Durée incluse dépassée';
  if(in_array($r['refund_status']??'',['failed','unknown'],true))$reasons[]='Remboursement à vérifier';
  if((int)($r['open_requests']??0)>0)$reasons[]='Demande d’assistance ouverte';
  return $reasons;
 }
}
