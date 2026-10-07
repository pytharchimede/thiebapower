<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Core\App;use App\Services\XPayeWalletClient;use App\Services\DepositWallet;
$option=$argv[1]??'';$client=new XPayeWalletClient(null,'auth');$failed=false;
try {
    if($option===''){$client->authenticate();}
    elseif(preg_match('/^--send-test=([1-9][0-9]*)$/D',$option,$m)){
        $q=App::db()->prepare('SELECT purpose,status,amount FROM deposit_wallet_transfers WHERE id=?');$q->execute([(int)$m[1]]);$row=$q->fetch();
        if(!$row || $row['purpose']!=='test' || $row['status']!=='pending')throw new LogicException('Seul un essai existant jamais envoyé est autorisé. Aucune demande incertaine ou caution ne peut être réémise ici.');
        $client=new XPayeWalletClient(null,'wallet-transfer-'.$m[1]);
        echo 'Essai réel #'.$m[1].' : '.$row['amount']." FCFA du solde encaissement vers payout\n";
        DepositWallet::send((int)$m[1],$client);
    }else throw new LogicException('Usage : php bin/xpaye_diagnostic.php [--send-test=ID_ESSAI_PENDING]');
}catch(Throwable $e){$failed=true;fwrite(STDERR,(string)$client->redact($e->getMessage())."\n");}
echo json_encode($client->diagnostics(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
exit($failed?1:0);
