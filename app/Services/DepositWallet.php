<?php
namespace App\Services;
use App\Core\App;
final class DepositWallet
{
    public const DEFAULT_FEE_BASIS_POINTS = 200; // 2%, not 0.2%; internal wallet transfer is free.
    /** Parse the percentage typed in the interface without floating point rounding. */
    public static function percentageBasisPoints(mixed $value):int
    {
        if(!is_string($value) && !is_int($value))throw new \InvalidArgumentException('Taux invalide. Saisissez un pourcentage entre 0 et 100, avec deux décimales maximum.');
        $value=str_replace(',', '.',trim((string)$value));
        if(!preg_match('/^(0|[1-9][0-9]{0,2})(?:\.([0-9]{1,2}))?$/D',$value,$parts))throw new \InvalidArgumentException('Taux invalide. Saisissez un pourcentage entre 0 et 100, avec deux décimales maximum.');
        $bps=(int)$parts[1]*100+(int)str_pad($parts[2]??'',2,'0');
        if($bps>10000)throw new \InvalidArgumentException('Le taux ne doit pas dépasser 100 %.');
        return $bps;
    }
    public static function settings():array {return App::db()->query('SELECT * FROM deposit_wallet_settings WHERE id=1')->fetch();}
    /** Fees are paid by the company, never subtracted from the customer's refund. */
    public static function fee(int $amount,string $channel,array $rules):int
    {
        $rule=$rules[$channel]??null;
        if(!is_array($rule) || !isset($rule['fixed'],$rule['basis_points']) || !is_int($rule['fixed']) || !is_int($rule['basis_points']) || $rule['fixed']<0 || $rule['fixed']>100000 || $rule['basis_points']<0 || $rule['basis_points']>10000)throw new \LogicException('Frais de remboursement non renseignés pour '.$channel.'.');
        if($amount<=0)return 0;
        return $rule['fixed']+intdiv($amount*$rule['basis_points']+9999,10000);
    }
    public static function remaining(array $rental,?int $now=null):array
    {
        if(empty($rental['deposit_payment_verified_at']) || (int)$rental['deposit']<=0)return ['paid'=>false];
        $end=!empty($rental['returned_at'])?strtotime($rental['returned_at'].' UTC'):($now??time());
        $late=!empty($rental['due_at'])?max(0,$end-strtotime($rental['due_at'].' UTC')):0;
        return ['paid'=>true,'deposit'=>(int)$rental['deposit']]+RentalBilling::calculate($rental,$late);
    }
    /** Enqueue only a success already verified by PaymentVerification, never a browser redirect. */
    public static function verifiedPayment(int $rentalId):void
    {
        $db=App::db();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM rentals WHERE id=? FOR UPDATE');$q->execute([$rentalId]);$r=$q->fetch();
            if(!$r || $r['payment_environment']!=='production' || (int)$r['deposit']<=0){$db->commit();return;}
            $db->prepare('UPDATE rentals SET deposit_payment_verified_at=COALESCE(deposit_payment_verified_at,UTC_TIMESTAMP()) WHERE id=?')->execute([$rentalId]);
            $q=$db->prepare('SELECT id FROM deposit_wallet_transfers WHERE rental_id=?');$q->execute([$rentalId]);
            if($q->fetchColumn()){$db->commit();return;}
            $settings=self::settings();
            // Missing fees produce a visible queue item; they never silently assume zero.
            $fee=0;$state='needs_fees';
            try {$fee=self::fee((int)$r['deposit'],(string)$r['payout_channel'],json_decode($settings['fee_rules'],true)??[]);$state='pending';}catch(\LogicException $e){}
            $db->prepare('INSERT INTO deposit_wallet_transfers(rental_id,request_key,purpose,deposit_amount,fee_reserve,amount,status) VALUES(?,?,?,?,?,?,?)')->execute([$rentalId,'rental-'.$rentalId,'deposit',$r['deposit'],$fee,(int)$r['deposit']+$fee,$state]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function createTest(string $key,int $amount,int $actor):int
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$key) || $amount<1 || $amount>100000)throw new \InvalidArgumentException('Essai invalide (1 à 100 000 FCFA).');
        $db=App::db();$q=$db->prepare('INSERT INTO deposit_wallet_transfers(request_key,purpose,amount,created_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$q->execute(['test-'.$key,'test',$amount,$actor]);
        $q=$db->prepare('SELECT id,amount,created_by FROM deposit_wallet_transfers WHERE request_key=?');$q->execute(['test-'.$key]);$r=$q->fetch();
        if((int)$r['amount']!==$amount || (int)$r['created_by']!==$actor)throw new \LogicException('Essai déjà enregistré avec d’autres paramètres.');
        return (int)$r['id'];
    }
    public static function send(int $id,?XPayeWalletClient $client=null):void
    {
        $check=App::db()->prepare('SELECT status FROM deposit_wallet_transfers WHERE id=?');$check->execute([$id]);if($check->fetchColumn()!=='pending')return;
        $client??=new XPayeWalletClient();
        // Auth has no monetary side effect: failure leaves the request pending.
        $token=$client->authenticate();$db=App::db();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM deposit_wallet_transfers WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
            if(!$r || $r['status']!=='pending'){$db->commit();return;}
            if($r['purpose']==='deposit'){
                $settings=self::settings();
                if((int)$settings['enabled']!==1)throw new \LogicException('Les transferts de cautions sont désactivés.');
                $q=$db->prepare('SELECT payout_channel FROM rentals WHERE id=?');$q->execute([$r['rental_id']]);$channel=$q->fetchColumn();
                $fee=self::fee((int)$r['deposit_amount'],(string)$channel,json_decode($settings['fee_rules'],true)??[]);
                $r['amount']=(int)$r['deposit_amount']+$fee;
                $db->prepare("UPDATE deposit_wallet_transfers SET fee_reserve=?,amount=? WHERE id=? AND status='pending'")->execute([$fee,$r['amount'],$id]);
            }
            // Commit before the financial request; concurrent workers and retries cannot resend.
            $db->prepare("UPDATE deposit_wallet_transfers SET status='unknown',sent_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'")->execute([$id]);$db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        try {
            $reply=$client->request((int)$r['amount'],$token);
            $status=$reply['http']>=200 && $reply['http']<300?'submitted':'unknown';
            $db->prepare('UPDATE deposit_wallet_transfers SET status=?,http_status=?,response_summary=? WHERE id=? AND status=?')->execute([$status,$reply['http'],json_encode($reply['summary']),$id,'unknown']);
            Audit::event('wallet.request','wallet_transfer',(string)$id,['amount'=>$r['amount'],'status'=>$status]);
        }catch(\Throwable $e){SystemReports::record('wallet.request',$e,['transfer_id'=>$id],'wallet.request:'.$id);throw $e;}
    }
    /** Until a provider confirmation contract is supplied, credit requires an auditable reconciliation. */
    public static function confirm(int $id,string $proof,int $actor):void
    {
        $proof=trim($proof);if(strlen($proof)<5 || strlen($proof)>240)throw new \InvalidArgumentException('Indiquez la référence du crédit vérifié dans le solde payout.');
        $q=App::db()->prepare("UPDATE deposit_wallet_transfers SET status='confirmed',confirmation_proof=?,confirmed_by=?,confirmed_at=UTC_TIMESTAMP() WHERE id=? AND status IN ('submitted','unknown')");$q->execute([$proof,$actor,$id]);
        if($q->rowCount()!==1)throw new \LogicException('Transfert non éligible au rapprochement.');
        Audit::event('wallet.credit_confirmed','wallet_transfer',(string)$id,['proof'=>$proof,'actor'=>$actor]);
    }
    public static function recoverVerifiedPayments():void
    {
        // Repair a callback interrupted between the durable verified notification and wallet enqueue.
        // Never sweep historical rentals when deploying this feature.
        $db=App::db();
        $rows=$db->query("SELECT DISTINCT r.id FROM rentals r JOIN payment_notifications n ON n.rental_id=r.id JOIN deposit_wallet_settings cfg ON cfg.id=1 LEFT JOIN deposit_wallet_transfers w ON w.rental_id=r.id WHERE r.payment_environment='production' AND r.deposit>0 AND w.id IS NULL AND n.received_at>=cfg.created_at AND JSON_VALID(n.payload) AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0' ORDER BY r.id LIMIT 100")->fetchAll();
        foreach($rows as $row){
            $q=$db->prepare("SELECT payload FROM payment_notifications WHERE rental_id=? AND JSON_VALID(payload) AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.responsecode'))='0' ORDER BY id DESC LIMIT 1");$q->execute([$row['id']]);
            RentalPaymentChannel::record((int)$row['id'],json_decode($q->fetchColumn(),true)??[]);
            self::verifiedPayment((int)$row['id']);
        }
    }
    public static function funded(array $rental):bool
    {
        if($rental['payment_environment']!=='production')return false;
        $channel=App::db()->prepare('SELECT payment_channel,payout_channel,payment_channel_source FROM rentals WHERE id=?');$channel->execute([$rental['rental_id']]);$paid=$channel->fetch();
        if(!$paid || empty($paid['payment_channel_source']) || $paid['payment_channel']!==$paid['payout_channel'] || $paid['payout_channel']!==$rental['payout_channel'])return false;
        $q=App::db()->prepare("SELECT fee_reserve FROM deposit_wallet_transfers WHERE rental_id=? AND purpose='deposit' AND status='confirmed' AND deposit_amount>=?");$q->execute([$rental['rental_id'],$rental['refund_amount']]);$reserve=$q->fetchColumn();
        if($reserve===false)return false;
        try {$fee=self::fee((int)$rental['refund_amount'],$rental['payout_channel'],json_decode(self::settings()['fee_rules'],true)??[]);}catch(\LogicException $e){return false;}
        return $fee<=(int)$reserve;
    }
    /** Recalculate only requests never attempted; sent amounts remain immutable. */
    public static function prepareMissingFees():void
    {
        $db=App::db();$rules=json_decode(self::settings()['fee_rules'],true)??[];
        $rows=$db->query("SELECT w.id,w.deposit_amount,r.payout_channel FROM deposit_wallet_transfers w JOIN rentals r ON r.id=w.rental_id WHERE w.status IN ('needs_fees','pending') AND w.sent_at IS NULL ORDER BY w.id LIMIT 100")->fetchAll();
        foreach($rows as $r){try{$fee=self::fee((int)$r['deposit_amount'],(string)$r['payout_channel'],$rules);}catch(\LogicException $e){continue;}
            $db->prepare("UPDATE deposit_wallet_transfers SET fee_reserve=?,amount=deposit_amount+?,status='pending' WHERE id=? AND status IN ('needs_fees','pending') AND sent_at IS NULL")->execute([$fee,$fee,$r['id']]);}
    }
}
