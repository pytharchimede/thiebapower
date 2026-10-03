<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\StatisticsDashboard;
final class StatisticsController
{
 public function index():void {Auth::requirePermission('dashboard.view');App::view('statistics');}
 public function snapshot():void {
  $user=Auth::requirePermission('dashboard.view');$permissions=StatisticsDashboard::permissions($user);session_write_close();
  header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
  $days=filter_var($_GET['days']??7,FILTER_VALIDATE_INT);
  if(!in_array($days,[1,7,30,90],true)){http_response_code(422);echo json_encode(['error'=>'Période invalide']);return;}
  try {echo json_encode((new StatisticsDashboard())->snapshot($days,$permissions),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);}
  catch(\Throwable $e){error_log('Statistics dashboard: '.$e->getMessage());http_response_code(503);echo json_encode(['error'=>'Statistiques temporairement indisponibles. Les dernières données restent affichées.']);}
 }
}
