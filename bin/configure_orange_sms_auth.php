<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void {if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\OrangeSmsSettings;
try {
    $header=trim(stream_get_contents(STDIN));
    [$id,$secret]=OrangeSmsSettings::basicCredentials($header);
    $path=realpath(dirname(__DIR__).'/.env');
    if ($path===false || !is_writable($path)) throw new RuntimeException('Le fichier .env doit exister et être accessible en écriture.');
    $content=file_get_contents($path);
    if ($content===false) throw new RuntimeException('Lecture du .env impossible.');
    foreach (['ORANGE_SMS_CREDENTIALS_SOURCE'=>'env','ORANGE_SMS_AUTHORIZATION_HEADER'=>'Basic '.base64_encode($id.':'.$secret),'ORANGE_SMS_CLIENT_ID'=>$id,'ORANGE_SMS_CLIENT_SECRET'=>$secret] as $key=>$value) {
        $content=preg_replace('/^[ \t]*'.preg_quote($key,'/').'[ \t]*=.*(?:\r?\n|$)/m','',$content);
        $content=rtrim($content)."\n".$key.'='.$value."\n";
    }
    $temp=tempnam(dirname($path),'.orange-sms-env-');
    if ($temp===false) throw new RuntimeException('Création du fichier temporaire impossible.');
    try {
        if (!chmod($temp,0600) || file_put_contents($temp,$content,LOCK_EX)===false || !rename($temp,$path)) throw new RuntimeException('Écriture du .env impossible.');
    } finally {if(is_file($temp))unlink($temp);}
    echo "Authentification Orange configurée : source env, en-tête Basic. Aucun secret affiché.\n";
} catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
