<?php
namespace App\Services;
use App\Core\App;

/** Production withdrawals have a separate ledger; an uncertain result is never reissued. */
final class FinanceWithdrawalService
{
    private PaiementProPayoutService $provider;
    public function __construct(?PaiementProPayoutService $provider=null){$this->provider=$provider??new PaiementProPayoutService();}
    public function send(array $data, int $actor): string
    {
        if (IntegrationSettings::all()['paiementpro'] !== 'production') {
            throw new \LogicException('Le retrait nécessite le mode production de Paiement Pro.');
        }
        $amount = filter_var($data['amount'] ?? '', FILTER_VALIDATE_INT);
        $token = (string)($data['request_token'] ?? '');
        $name = trim((string)($data['recipient_name'] ?? ''));
        $reason = trim((string)($data['reason'] ?? ''));
        if (!$amount || $amount < 1 || $amount > 999999999 || !preg_match('/^[a-f0-9]{32}$/D', $token) || !$name || strlen($name)>120 || !$reason || strlen($reason)>250 || ($data['confirm'] ?? '')!=='1') {
            throw new \InvalidArgumentException('Montant, bénéficiaire, motif et confirmation requis.');
        }
        $phone = PaiementProPayoutService::normalizePhone((string)($data['phone'] ?? ''));
        $channel = (string)($data['channel'] ?? '');
        $ref = 'TBP-WD-'.strtoupper(bin2hex(random_bytes(12)));
        $provider = $this->provider;
        $request = $provider->prepare($ref, $amount, $channel, $phone, $name, 'production');
        $db = App::db();
        // Connection-scoped lock serializes independent submissions; provider calls occur after commit.
        if ((int)$db->query("SELECT GET_LOCK('tbp_finance_withdrawal',0)")->fetchColumn() !== 1) {
            throw new \LogicException('Un retrait est déjà en préparation.');
        }
        try {
            $q=$db->prepare('SELECT reference FROM finance_withdrawals WHERE request_token=?');$q->execute([$token]);
            if ($existing=$q->fetchColumn()) return $existing;
            if ((int)$db->query("SELECT COUNT(*) FROM finance_withdrawals WHERE status IN ('unknown','initiated','processing')")->fetchColumn()>0) {
                throw new \LogicException('Un retrait attend sa vérification. Ne pas le réémettre.');
            }
            try {
                $wallet=$provider->balance(); // Always fresh: never trust browser values or cached balances.
                $quote=WithdrawalQuote::calculate($wallet['balance'],$channel,WithdrawalQuote::rules(),$amount);
            } catch (\Throwable $e) {
                throw new \LogicException('Impossible de vérifier le solde ou les frais. Aucun retrait envoyé. Actualisez la page et vérifiez le barème payout.',0,$e);
            }
            if($amount>$quote['maximum']) {
                throw new \LogicException('Montant trop élevé. Maximum retirable, frais inclus : '.number_format($quote['maximum'],0,',',' ').' FCFA. Aucun retrait envoyé.');
            }
            $db->prepare('INSERT INTO finance_withdrawals(reference,request_token,amount,recipient_phone,recipient_channel,recipient_name,reason,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$ref,$token,$amount,$phone,$channel,$name,$reason,$actor]);
        } finally {
            $db->query("SELECT RELEASE_LOCK('tbp_finance_withdrawal')");
        }
        Audit::event('finance.withdrawal_requested','withdrawal',$ref,['amount'=>$amount,'channel'=>$channel,'fee_estimate'=>$quote['fee'],'total_estimate'=>$quote['total'],'balance_before'=>$quote['balance']]);
        PayoutApiAudit::request($ref,$request);
        try {
            $reply=$provider->initiate($request);
            PayoutApiAudit::record($ref,'init',$reply);
            $outcome=PayoutResult::initiation($reply);
            $state=in_array($outcome['state'],['processing','initiated','failed'],true)?$outcome['state']:'unknown';
            $db->prepare('UPDATE finance_withdrawals SET status=?,provider_session_id=?,provider_message=? WHERE reference=?')->execute([$state,$outcome['session']??null,substr((string)($reply->description??'Versement à vérifier'),0,250),$ref]);
        } catch (\Throwable $e) {
            PayoutApiAudit::record($ref,'error',['description'=>$e->getMessage()]);
            error_log('Withdrawal outcome unknown '.$ref.': '.$e->getMessage());
        }
        return $ref;
    }

    /** Release only the local submission lock; this does not cancel the provider transfer. */
    public function unblock(string $reference, string $reason, bool $confirmed, int $actor): void
    {
        $reason=trim($reason);
        if (!$confirmed || strlen($reason)<5 || strlen($reason)>160) {
            throw new \InvalidArgumentException('Confirmation et motif de 5 à 160 caractères requis.');
        }
        $db=App::db();
        $q=$db->prepare("UPDATE finance_withdrawals SET status='released_unknown', provider_message=? WHERE reference=? AND status IN ('unknown','initiated','processing') AND created_at<=UTC_TIMESTAMP()-INTERVAL 5 MINUTE");
        $q->execute(['Débloqué par #'.$actor.' : '.$reason,$reference]);
        if ($q->rowCount()!==1) {
            throw new \LogicException('Déblocage impossible : attendre 5 minutes ou actualiser le statut.');
        }
        Audit::event('finance.withdrawal_unblocked','withdrawal',$reference,['actor'=>$actor,'reason'=>$reason,'provider_outcome'=>'unknown']);
    }

    public function verify(string $reference): void
    {
        $db=App::db();$q=$db->prepare('SELECT * FROM finance_withdrawals WHERE reference=?');$q->execute([$reference]);$op=$q->fetch();
        if (!$op || !in_array($op['status'],['unknown','initiated','processing','released_unknown'],true)) throw new \LogicException('Retrait non éligible.');
        $session=$op['provider_session_id'];
        if (!$session) {
            $events=$db->prepare("SELECT * FROM payout_api_events WHERE reference=? AND source='init' ORDER BY id");$events->execute([$reference]);
            $outcome=PayoutResult::initiation(PayoutResult::recordedInitiation($reference,$events->fetchAll()));
            $session=$outcome['session']??null;
        }
        if (!$session) throw new \LogicException('Session absente : demander une vérification à Paiement Pro avec la référence. Le retrait reste bloqué.');
        $reply=$this->provider->status($session,'production',$reference);
        PayoutApiAudit::record($reference,'status',$reply);
        $state=PayoutResult::finalStatus($reply,['sessionid'=>$session,'referenceNo'=>$reference,'amount'=>$op['amount'],'currency'=>'XOF','channel'=>$op['recipient_channel'],'payeeNo'=>$op['recipient_phone']]);
        if ($state==='mismatch') throw new \LogicException('Réponse fournisseur incohérente : vérification manuelle requise.');
        if (!in_array($state,['succeeded','failed'],true)) return;
        $db->prepare("UPDATE finance_withdrawals SET status=?,provider_session_id=?,confirmed_at=UTC_TIMESTAMP() WHERE reference=? AND status IN ('unknown','initiated','processing','released_unknown')")->execute([$state,$session,$reference]);
        Audit::event('finance.withdrawal_verified','withdrawal',$reference,['status'=>$state]);
    }
}
