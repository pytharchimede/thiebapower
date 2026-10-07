<?php
declare(strict_types=1);

namespace App\Services {
    function curl_init($url){return (object)['url'=>$url];}
    function curl_setopt_array($ch,$options){
        $GLOBALS['routed_payload']=json_decode($options[CURLOPT_POSTFIELDS],true);
        return true;
    }
    function curl_exec($ch){
        $GLOBALS['routed_calls']++;
        return '{"success":true,"url":"https://www.paiementpro.net/payment?sessionid=SESSION_TEST"}';
    }
    function curl_getinfo($ch,$option){return 200;}
    function curl_close($ch):void{}
}

namespace {
    spl_autoload_register(static function($class){
        if(str_starts_with($class,'App\\')){
            require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
        }
    });

    putenv('APP_URL=https://thiebapower.example');
    putenv('PAIEMENTPRO_MERCHANT_ID=TEST-MERCHANT');

    $GLOBALS['routed_calls']=0;

    /*
     * Le test doit utiliser les canaux réellement activés.
     * Il vérifie le routage PaiementPro sans imposer WAVECI/OMCIV
     * à une installation dont la configuration administrateur peut varier.
     */
    $active=\App\Services\RentalPaymentChannel::paymentChannels();

    if($active===[]){
        throw new \RuntimeException(
            'Impossible de tester le routage : aucun canal d’encaissement actif.'
        );
    }

    $first=$active[0];

    $r=[
        'reference'=>'TBP-ROUTING-TEST',
        'payment_environment'=>'production',
        'customer_name'=>'Test client',
        'customer_phone'=>'0700000000',
        'customer_email'=>'client@example.test',
        'rental_fee'=>600,
        'deposit'=>5000,
        'payment_channel'=>$first,
        'payout_channel'=>'MOMOCI',
    ];

    (new \App\Services\PaiementProService())->initiateTest($r);

    if(
        $GLOBALS['routed_payload']['channel']!==$first
        || $GLOBALS['routed_payload']['amount']!==5600
    ){
        throw new \RuntimeException(
            'Payment not bound to the chosen active payment channel'
        );
    }

    /*
     * Même comportement lorsque la caution est désactivée.
     */
    $r['deposit']=0;
    $r['rental_fee']=600;

    (new \App\Services\PaiementProService())->initiateTest($r);

    if(
        $GLOBALS['routed_payload']['channel']!==$first
        || $GLOBALS['routed_payload']['amount']!==600
    ){
        throw new \RuntimeException(
            'Active CI channel missing when deposit is disabled'
        );
    }

    /*
     * Aucun canal absent/inconnu ne doit atteindre PaiementPro.
     */
    unset($r['payment_channel']);

    try{
        (new \App\Services\PaiementProService())->initiateTest($r);
        throw new \RuntimeException(
            'Missing payment channel reached provider'
        );
    }catch(\LogicException $e){
        // attendu
    }

    $r['payment_channel']='INVALID-CI-CHANNEL';

    try{
        (new \App\Services\PaiementProService())->initiateTest($r);
        throw new \RuntimeException(
            'Unsupported payment channel reached provider'
        );
    }catch(\LogicException $e){
        // attendu
    }

    if($GLOBALS['routed_calls']!==2){
        throw new \RuntimeException(
            'Unsupported payment reached provider'
        );
    }

    echo "Rental payment routing OK: active CI channel enforced with or without deposit; invalid/absent channels blocked; mocked HTTP, no payment\n";
}
