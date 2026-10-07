<?php
spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
use App\Services\OrangeSmsDelivery as D;
function check($v){if(!$v)throw new RuntimeException('Assertion failed');}
putenv('ORANGE_SMS_CALLBACK_TOKEN='.str_repeat('a',64));putenv('ORANGE_SMS_CALLBACK_IPS=192.0.2.10, 192.0.2.11');
check(D::authorized(str_repeat('a',64),'192.0.2.10'));check(!D::authorized('bad','192.0.2.10'));check(!D::authorized(str_repeat('a',64),'192.0.2.12'));
putenv('ORANGE_SMS_CALLBACK_IPS=');check(!D::authorized(str_repeat('a',64),'192.0.2.10'));
foreach(D::STATUSES as $status){$v=D::parse(json_encode(['deliveryInfoNotification'=>['callbackData'=>'22cf84fc-5965-4dec-945a-aa8fe8dd6007','deliveryInfo'=>['address'=>'tel:+2250700000000','deliveryStatus'=>$status]]]));check($v['status']===$status);check($v['recipient_masked']==='+225******0000');}
foreach(['{}','invalid',str_repeat('x',16385),json_encode(['deliveryInfoNotification'=>['callbackData'=>'abc','deliveryInfo'=>['address'=>'tel:++2250700000000','deliveryStatus'=>'DeliveredToTerminal']]])] as $raw){$failed=false;try{D::parse($raw);}catch(InvalidArgumentException|JsonException $e){$failed=true;}check($failed);}
echo "Orange SMS callbacks: validation, IP allowlist, token and masked recipients OK\n";
