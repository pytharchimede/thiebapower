<?php
declare(strict_types=1);
spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
use App\Services\{SmsTemplates as Templates,SmsProcessService as Process,PhoneOtpService as Otp};
function same($a,$b):void{if($a!==$b)throw new RuntimeException('Unexpected result');}
function rejects(callable $f):void{try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected validation error');}
foreach(Templates::definitions() as $key=>$d){$text=Templates::render($key,$d[1],$d[2]);same(false,str_contains($text,'{{'));if(Templates::segments($text)<1)throw new RuntimeException('Segments missing');}
rejects(fn()=>Templates::render('rental_started','Bonjour {{unknown}}',[]));
rejects(fn()=>Templates::render('rental_started','Bonjour {{customer_name}}',[]));
rejects(fn()=>Templates::render('rental_started','Bonjour {{customer_name}}',['customer_name'=>"bad\nname"]));
rejects(fn()=>Templates::validate('phone_otp','Votre code est absent'));
rejects(fn()=>Templates::validate('reminder_5min','Retour avant {{due_time}}'));
rejects(fn()=>Templates::validate('rental_started','{{customer_name}'));
same(1,Templates::segments(str_repeat('A',160)));same(2,Templates::segments(str_repeat('A',161)));
same(1,Templates::segments(str_repeat('^',80)));same(2,Templates::segments(str_repeat('^',81)));
same(1,Templates::segments(str_repeat('ê',70)));same(2,Templates::segments(str_repeat('ê',71)));same(2,Templates::segments(str_repeat('😀',36)));
$now=(new DateTimeImmutable('2026-10-04 12:00:00',new DateTimeZone('UTC')))->getTimestamp();
foreach([300=>true,299=>true,241=>true,240=>false,301=>false,0=>false,-1=>false] as $left=>$expected){$r=['status'=>'active','due_at'=>gmdate('Y-m-d H:i:s',$now+$left)];same($expected,Process::reminderEligible($r,$now));$r['status']='returned';same(false,Process::reminderEligible($r,$now));}
putenv('ORANGE_SMS_ENABLED=0');same(null,Process::activeSettings());
putenv('PHONE_OTP_KEY='.base64_encode(random_bytes(32)));
for($i=0;$i<50;$i++)if(!preg_match('/^\d{6}$/D',Otp::generateCode()))throw new RuntimeException('Invalid OTP');
$hash=Otp::digest('id','+2250700000000','verify_phone','123456');same(64,strlen($hash));
same(true,hash_equals($hash,Otp::digest('id','+2250700000000','verify_phone','123456')));
foreach([['other','+2250700000000','verify_phone','123456'],['id','+2250100000000','verify_phone','123456'],['id','+2250700000000','reset_password','123456'],['id','+2250700000000','verify_phone','000000']] as $v)same(false,hash_equals($hash,Otp::digest(...$v)));
echo "SMS templates: variables, message bounds, segment estimates, reminder window, global stop and purpose-bound OTP hashing OK\n";
