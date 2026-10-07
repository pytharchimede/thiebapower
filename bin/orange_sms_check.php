<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void {if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\{OrangeSmsSettings,OrangeSmsClient};
try {
    $settings=OrangeSmsSettings::all();
    echo json_encode(OrangeSmsSettings::authenticationInfo($settings),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    $client=new OrangeSmsClient($settings);
    echo json_encode($client->authenticate(),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    if(in_array('--balance',$argv,true))echo json_encode($client->inspect('balance'),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
} catch (Throwable $e) {fwrite(STDERR,$e instanceof PDOException?'Base SMS indisponible. Appliquez la migration.\n':$e->getMessage()."\n");exit(1);}
