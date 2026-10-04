<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $c):void{if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
try {echo json_encode(\App\Services\SmsProcessService::run(),JSON_UNESCAPED_UNICODE).PHP_EOL;}catch(Throwable $e){fwrite(STDERR,"Traitement SMS indisponible : verifier la migration et la configuration.\n");exit(1);}
