<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\StationProfitability;
final class StationProfitabilityController
{
 public function index():void {
  Auth::requirePermission('finance.view');Auth::requirePermission('stations.view');$rows=(new StationProfitability)->report();
  $costs=App::db()->query('SELECT c.*,s.label FROM station_costs c JOIN stations s ON s.imei=c.station_imei ORDER BY c.occurred_on DESC,c.id DESC LIMIT 100')->fetchAll();App::view('station_profitability',compact('rows','costs'));
 }
 public function investment():void {
  Auth::requirePermission('finance.manage',true);Auth::requirePermission('stations.view');
  $amount=filter_var($_POST['investment']??null,FILTER_VALIDATE_INT);$imei=(string)($_POST['imei']??'');
  if($amount===false||$amount<0||$amount>1000000000||!$this->exists($imei)){http_response_code(422);return;}
  App::db()->prepare('INSERT INTO station_profiles(station_imei,investment) VALUES(?,?) ON DUPLICATE KEY UPDATE investment=VALUES(investment)')->execute([$imei,$amount]);Audit::event('station.investment_saved','station',$imei,['amount'=>$amount]);App::redirect('/admin/stations/profitability');
 }
 public function cost():void {
  Auth::requirePermission('finance.manage',true);Auth::requirePermission('stations.view');
  $token=(string)($_POST['request_token']??'');$amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);$imei=(string)($_POST['imei']??'');$category=(string)($_POST['category']??'');$text=trim((string)($_POST['description']??''));$date=(string)($_POST['occurred_on']??'');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));
  if(!preg_match('/^[a-f0-9]{32}$/D',$token)||$amount===false||$amount<1||$amount>1000000000||!in_array($category,['payment_fee','maintenance','venue','other'],true)||$text===''||strlen($text)>250||!$parsed||$parsed->format('Y-m-d')!==$date||!$this->exists($imei)){http_response_code(422);echo 'Coût invalide';return;}
  App::db()->prepare('INSERT INTO station_costs(request_token,station_imei,category,amount,description,occurred_on,created_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id')->execute([$token,$imei,$category,$amount,$text,$date,Auth::id()]);Audit::event('station.cost_recorded','station',$imei,['amount'=>$amount,'category'=>$category]);App::redirect('/admin/stations/profitability');
 }
 private function exists(string $imei):bool {$q=App::db()->prepare('SELECT 1 FROM stations WHERE imei=?');$q->execute([$imei]);return (bool)$q->fetchColumn();}
}
