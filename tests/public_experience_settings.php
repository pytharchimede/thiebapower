<?php
spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
use App\Services\PublicExperienceSettings as Settings;
$input=['support_enabled'=>'on','whatsapp_enabled'=>'on','whatsapp'=>'+2250700000000','phone'=>'+2250100000000','email'=>'support@example.test','chat'=>'https://example.test/chat','message'=>'Besoin d’aide','visible'=>['logo'=>'on']];
$s=Settings::validate($input);if(!$s['support_enabled']||!$s['whatsapp_enabled']||$s['chat_enabled']||!$s['visible']['logo']||$s['visible']['steps'])throw new RuntimeException('Settings toggles invalid');
foreach([['whatsapp'=>'javascript:alert(1)'],['email'=>'invalid'],['chat'=>'javascript:alert(1)'],['chat'=>'https://user:secret@example.test'],['phone_enabled'=>'on','phone'=>''],['message'=>str_repeat('x',501)]] as $invalid){try{Settings::validate(array_replace($input,$invalid));throw new RuntimeException('Invalid contact accepted');}catch(InvalidArgumentException $e){}}
if(Settings::defaults()['support_enabled'])throw new RuntimeException('Unconfigured support visible');
echo "Public settings: visibility, channel toggles, international numbers, email, safe chat URL and defaults OK\n";
