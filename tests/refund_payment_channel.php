<?php
declare(strict_types=1);
spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\RentalPaymentChannel;
function same($expected,$actual):void{if($expected!==$actual)throw new RuntimeException('Channel mismatch: '.var_export($actual,true));}
foreach(['WAVECI','MOMOCI','OMCIV','FLOOZ'] as $channel){same($channel,RentalPaymentChannel::resolve(['payment_channel'=>$channel],[])['channel']);same($channel,RentalPaymentChannel::resolve(['payment_channel'=>'WAVECI'],['channel'=>$channel])['channel']);}
same('OMCIV',RentalPaymentChannel::resolve([],['channel'=>'OMCIV2'])['channel']);
same(null,RentalPaymentChannel::resolve(['payout_channel'=>'WAVECI'],[])['channel']);
same(null,RentalPaymentChannel::resolve(['payment_channel'=>'WAVECI'],['channel'=>'CARD'])['channel']);
same(null,RentalPaymentChannel::resolve(['payment_channel'=>'WAVECI'],['channel'=>[]])['channel']);
$form=file_get_contents(dirname(__DIR__).'/views/rent.php');if(str_contains($form,"paymentField='payout_channel'")||!str_contains($form,"paymentField='payment_channel'"))throw new RuntimeException('Independent refund choice still exposed');
$service=file_get_contents(dirname(__DIR__).'/app/Services/RentalPaymentChannel.php');if(!str_contains($service,'paymentChannels()')||!str_contains($service,'payoutChannels()'))throw new RuntimeException('Configurable channel policy missing');
echo "Refund channel OK: payment routing, provider priority, Orange alias, unknown historical channels and unsupported methods; no API calls\n";
