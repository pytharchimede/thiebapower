<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $c):void{if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
use App\Core\App;
use App\Services\{HeyChargeOpenApi,StationFleetService};
$imei=$argv[1]??'';
if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei)){fwrite(STDERR,"Usage: php bin/station_check.php IMEI [--sync]\n");exit(1);}
function failure(Throwable $e): array {
 if($e instanceof PDOException)return ['type'=>'database','sqlstate'=>(string)$e->getCode(),'driver_code'=>$e->errorInfo[1]??null];
 $message=$e->getMessage();
 if(preg_match('/HeyCharge HTTP (\d{3})/',$message,$m))return ['type'=>'heycharge_http','http_status'=>(int)$m[1]];
 if(str_contains($message,'Configuration HeyCharge'))return ['type'=>'heycharge_configuration'];
 if(str_contains($message,'indisponible'))return ['type'=>'heycharge_connection'];
 return ['type'=>str_contains($message,'incohérente')||str_contains($message,'invalide')?'invalid_response':'internal','exception'=>get_class($e)];
}
$report=['imei'=>$imei,'api_configured'=>(new HeyChargeOpenApi)->configured()];$failed=false;
try{$db=App::db();$q=$db->prepare('SELECT imei,enabled,status,last_seen_at FROM stations WHERE imei=?');$q->execute([$imei]);$report['station']=$q->fetch()?:null;
foreach(['manual_release_commands','batteries','stations'] as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);$report['tables'][$table]=(bool)$q->fetchColumn();}
}catch(Throwable $e){$report['database_error']=failure($e);$failed=true;}
try{$remote=(new HeyChargeOpenApi)->station($imei);$report['api']=['returned_imei'=>$remote['imei']??null,'response_fields'=>array_keys($remote),'battery_count'=>is_array($remote['batteries']??null)?count($remote['batteries']):null];$report['api']['coherent']=($remote['imei']??null)===$imei&&is_array($remote['batteries']??null);if(!$report['api']['coherent'])$failed=true;}
catch(Throwable $e){$report['api_error']=failure($e);$failed=true;}
if(in_array('--sync',$argv,true)){
 try{(new StationFleetService)->sync($imei);$report['sync']='ok';}
 catch(Throwable $e){$report['sync_error']=failure($e);$failed=true;}
}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($failed?1:0);
