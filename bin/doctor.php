<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void {
 if(!str_starts_with($class,'App\\'))return;
 $file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
 if(is_file($file))require $file;
});
use App\Core\App;
$checks=[];
foreach(['pdo_mysql','curl','soap'] as $extension)$checks['Extension '.$extension]=extension_loaded($extension);
foreach(['APP_URL','HEYCHARGE_API_KEY','PAIEMENTPRO_MERCHANT_ID','PAYMENT_CALLBACK_SECRET'] as $name)
 $checks[$name]=$name==='PAYMENT_CALLBACK_SECRET'?strlen(App::env($name))>=32:App::env($name)!=='';
try {
 $db=App::db();$checks['Connexion MariaDB']=true;
 foreach(['stations','heycharge_events','service_heartbeats'] as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);$checks['Table '.$table]=(bool)$q->fetchColumn();}
 foreach(['deposit_enabled'=>'pricing','last_station_check_at'=>'rentals','release_command_at'=>'rentals','station_imei'=>'batteries'] as $column=>$table){$q=$db->query('SHOW COLUMNS FROM '.$table.' LIKE '.$db->quote($column));$checks[$table.'.'.$column]=(bool)$q->fetch();}
 if($checks['Table service_heartbeats']){
  $last=$db->query("SELECT last_run_at FROM service_heartbeats WHERE name='heycharge'")->fetchColumn();
  $checks['Cron HeyCharge récent']=$last && strtotime($last.' UTC')>=time()-240;
 }
}catch(\Throwable $e){$checks['Connexion MariaDB']=false;fwrite(STDERR,'Base indisponible: '.get_class($e).PHP_EOL);}
foreach($checks as $label=>$pass)echo ($pass?'OK ':'À CONFIGURER ').$label.PHP_EOL;
exit(in_array(false,$checks,true)?1:0);
