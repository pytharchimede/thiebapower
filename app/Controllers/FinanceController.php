<?php
namespace App\Controllers;

use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\FinanceReport;

final class FinanceController {
 public function index():void {
  Auth::requirePermission('finance.view');
  $today=gmdate('Y-m-d');$start=(string)($_GET['from']??gmdate('Y-m-01'));$end=(string)($_GET['to']??$today);
  $first=\DateTimeImmutable::createFromFormat('!Y-m-d',$start,new \DateTimeZone('UTC'));
  $last=\DateTimeImmutable::createFromFormat('!Y-m-d',$end,new \DateTimeZone('UTC'));
  if(!$first||!$last||$first->format('Y-m-d')!==$start||$last->format('Y-m-d')!==$end||$first>$last||$first->diff($last)->days>366){http_response_code(422);echo 'Période invalide (maximum 366 jours)';return;}
  $report=(new FinanceReport)->forPeriod($start,$end);
  App::view('finance',compact('report','start','end'));
 }
 public function addCash():void {
  $user=Auth::requirePermission('finance.manage',true);
  $direction=(string)($_POST['direction']??'');$amount=(string)($_POST['amount']??'');
  $category=trim((string)($_POST['category']??''));$reference=trim((string)($_POST['reference']??''));$note=trim((string)($_POST['note']??''));
  if(!in_array($direction,['in','out'],true)||!preg_match('/^[1-9][0-9]{0,8}$/D',$amount)||strlen($category)>80||$category===''||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,99}$/D',$reference)||strlen($note)>500){http_response_code(422);echo 'Écriture invalide';return;}
  $occurred=trim((string)($_POST['occurred_at']??''));$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$occurred,new \DateTimeZone('UTC'));
  if(!$date||$date->format('Y-m-d')!==$occurred||$date>new \DateTimeImmutable('today',new \DateTimeZone('UTC'))){http_response_code(422);echo 'Date invalide';return;}
  try {App::db()->prepare('INSERT INTO cash_entries(direction,amount,category,reference,note,occurred_at,created_by) VALUES(?,?,?,?,?,?,?)')
   ->execute([$direction,(int)$amount,$category,$reference,$note,$occurred.' 12:00:00',$user['id']]);}
  catch(\PDOException $e){if(($e->errorInfo[1]??0)===1062){http_response_code(409);echo 'Référence de pièce déjà enregistrée';return;}throw $e;}
  Audit::event('finance.cash_entry','cash',$reference,['direction'=>$direction,'amount'=>(int)$amount]);
  App::redirect('/admin/finance?from='.rawurlencode(substr($occurred,0,7).'-01').'&to='.rawurlencode($occurred));
 }
}
