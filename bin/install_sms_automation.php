<?php
// Run as the hosting account, never as root. Does not send SMS.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Core/App.php';
$root=realpath(dirname(__DIR__));$env=$root.'/.env';
try{
 if(!is_file($env)||!is_writable($env))throw new RuntimeException('.env absent ou non modifiable.');
 $token=\App\Core\App::env('ORANGE_SMS_CALLBACK_TOKEN');
 if(strlen($token)<32){$token=bin2hex(random_bytes(32));$content=file_get_contents($env);$content=preg_replace('/^ORANGE_SMS_CALLBACK_TOKEN=.*\R?/m','',$content);$content=rtrim($content)."\nORANGE_SMS_CALLBACK_TOKEN=".$token."\n";if(file_put_contents($env,$content,LOCK_EX)===false)throw new RuntimeException('Écriture .env impossible.');chmod($env,0600);}
 if(\App\Core\App::env('ORANGE_SMS_CALLBACK_IPS')==='')echo "À renseigner : ORANGE_SMS_CALLBACK_IPS avec les IP fournies par Orange.\n";
 $base=rtrim(\App\Core\App::env('APP_URL','https://thiebapower.com'),'/');
 echo "URL privée à transmettre uniquement au support Orange :\n".$base.'/api/orange/sms/delivery?token='.$token."\n";
 if(!function_exists('exec'))throw new RuntimeException('exec indisponible : installation cron impossible via PHP.');
 $out=[];$code=0;exec('crontab -l 2>/dev/null',$out,$code);if($code>1)throw new RuntimeException('Lecture crontab impossible.');
 $marker='# thiebapower-sms-worker';$lines=array_values(array_filter($out,fn($line)=>!str_contains($line,$marker)&&!str_contains($line,$root.'/bin/sms_worker.php')));
 if(str_contains($root,'%')||str_contains(PHP_BINARY,'%'))throw new RuntimeException('Chemin cron invalide.');
 if(!is_dir($root.'/storage'))mkdir($root.'/storage',0700,true);
 $lines[]='* * * * * '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/bin/sms_worker.php').' >> '.escapeshellarg($root.'/storage/sms-worker.log').' 2>&1 '.$marker;
 $tmp=tempnam(sys_get_temp_dir(),'sms-cron-');chmod($tmp,0600);
 try{file_put_contents($tmp,implode("\n",$lines)."\n");exec('crontab '.escapeshellarg($tmp).' 2>&1',$result,$code);if($code!==0)throw new RuntimeException('Installation crontab refusée.');}finally{unlink($tmp);}
 echo "Cron SMS installé chaque minute sans doublon, avec ".PHP_BINARY.".\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
