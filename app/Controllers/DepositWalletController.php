<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\DepositWallet;
use App\Services\XPayeWalletClient;
use App\Services\Audit;
final class DepositWalletController
{
    public function index():void
    {
        Auth::requirePermission('finance.view');header('Cache-Control: no-store');
        $settings=DepositWallet::settings();
        $rows=App::db()->query("SELECT w.*,r.reference,r.customer_name,r.customer_phone,r.payout_channel,r.deposit,r.deposit_payment_verified_at,r.status rental_status,r.due_at,r.returned_at,r.rental_fee,r.duration_minutes,r.billing_rule,r.late_percent,b.serial,s.refund_amount,s.status refund_status FROM deposit_wallet_transfers w LEFT JOIN rentals r ON r.id=w.rental_id LEFT JOIN batteries b ON b.id=r.battery_id LEFT JOIN deposit_settlements s ON s.rental_id=r.id ORDER BY w.id DESC LIMIT 100")->fetchAll();
        $totals=App::db()->query("SELECT status,SUM(amount) amount,COUNT(*) count FROM deposit_wallet_transfers GROUP BY status")->fetchAll();
        $flash=$_SESSION['wallet_flash']??null;unset($_SESSION['wallet_flash']);
        App::view('deposit_wallet',compact('settings','rows','totals','flash'));
    }
    public function snapshot():void
    {
        Auth::requirePermission('finance.view');session_write_close();
        header('Content-Type: application/json');header('Cache-Control: no-store');
        try {
            $rows=App::db()->query("SELECT r.* FROM rentals r JOIN deposit_wallet_transfers w ON w.rental_id=r.id ORDER BY w.id DESC LIMIT 100")->fetchAll();
            echo json_encode(['rows'=>array_map(static fn($r)=>['id'=>(int)$r['id'],'caution'=>DepositWallet::remaining($r)],$rows)],JSON_THROW_ON_ERROR);
        }catch(\Throwable $e){http_response_code(503);echo json_encode(['error'=>'Suivi temporairement indisponible']);}
    }
    public function action():void
    {
        $user=Auth::requirePermission('finance.withdraw',true);
        try {
            $action=(string)($_POST['action']??'');
            if($action==='settings'){
                Auth::requirePermission('pricing.manage');
                $rules=[];
                foreach(['WAVECI','MOMOCI','OMCIV','FLOOZ'] as $channel){
                    if(($_POST['configured'][$channel]??'')!=='1')continue;
                    $fixed=filter_var($_POST['fixed'][$channel]??null,FILTER_VALIDATE_INT);$bps=DepositWallet::percentageBasisPoints($_POST['percent'][$channel]??null);
                    if($fixed===false)throw new \InvalidArgumentException('Frais invalides.');
                    $rules[$channel]=['fixed'=>$fixed,'basis_points'=>$bps];DepositWallet::fee(100,$channel,$rules);
                }
                $enabled=($_POST['enabled']??'')==='1';
                if($enabled && (!XPayeWalletClient::configured() || count($rules)!==4))throw new \LogicException('Renseignez les identifiants serveur et confirmez les frais des quatre canaux avant activation.');
                App::db()->prepare('UPDATE deposit_wallet_settings SET enabled=?,fee_rules=?,updated_at=UTC_TIMESTAMP() WHERE id=1')->execute([(int)$enabled,json_encode($rules)]);
                Audit::event('wallet.settings','wallet','1',['enabled'=>$enabled,'fees'=>$rules]);DepositWallet::prepareMissingFees();
                $message='Paramètres enregistrés. Les cautions se règlent dans Tarification ; les reversements automatiques doivent aussi être activés sur le serveur.';
            }elseif($action==='auth'){
                (new XPayeWalletClient(null,'auth'))->authenticate();$message='Authentification XPaye réussie. Aucun transfert effectué.';
            }elseif($action==='test'){
                if(($_POST['confirm_real']??'')!=='1')throw new \LogicException('Confirmez le transfert réel entre vos soldes.');
                $amount=filter_var($_POST['amount']??null,FILTER_VALIDATE_INT);
                if($amount===false)throw new \InvalidArgumentException('Montant invalide.');
                $id=DepositWallet::createTest((string)($_POST['request_key']??''),$amount,(int)$user['id']);DepositWallet::send($id);
                $message='Essai enregistré. Consultez son état et vérifiez le crédit dans votre compte XPaye. Une réponse HTTP ne confirme pas le crédit.';
            }elseif($action==='send'){
                DepositWallet::send((int)($_POST['id']??0));$message='Transfert traité. Consultez son état avant toute autre opération.';
            }elseif($action==='confirm'){
                if(($_POST['credit_checked']??'')!=='1')throw new \LogicException('Vérifiez d’abord le crédit effectif du montant exact dans XPaye.');
                DepositWallet::confirm((int)($_POST['id']??0),(string)($_POST['proof']??''),(int)$user['id']);$message='Crédit rapproché. Les cautions éligibles seront remboursées par le worker après retour physique confirmé.';
            }else throw new \InvalidArgumentException('Action inconnue.');
            $_SESSION['wallet_flash']=['ok'=>true,'message'=>$message];
        }catch(\Throwable $e){
            error_log('Deposit wallet action: '.get_class($e));
            $_SESSION['wallet_flash']=['ok'=>false,'message'=>$e instanceof \LogicException || $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Opération non confirmée. Consultez le journal et le compte XPaye avant toute nouvelle demande.'];
        }
        App::redirect('/admin/deposit-wallet');
    }
}
