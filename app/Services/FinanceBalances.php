<?php
namespace App\Services;
use App\Core\App;

final class FinanceBalances
{
    public function snapshot(): array
    {
        $wallet=null;$error=null;
        if (IntegrationSettings::all()['paiementpro']==='production') {
            $key='finance_wallet_'.hash('sha256',App::env('PAIEMENTPRO_MERCHANT_ID'));
            $cached=$_SESSION[$key]??null;
            if (is_array($cached) && ($cached['expires']??0)>time()) {
                $wallet=$cached['wallet'];$error=$cached['error'];
            } else {
                try {$wallet=(new PaiementProPayoutService())->balance();}
                catch (\Throwable $e) {
                    $error='Consultation Paiement Pro indisponible. Réessayez après une minute.';
                    error_log('Finance balance lookup failed: '.get_class($e));
                }
                $_SESSION[$key]=['expires'=>time()+60,'wallet'=>$wallet,'error'=>$error];
            }
        } else {$error='Soldes réels consultables en mode production uniquement.';}
        $rows=[
            ['Réception','Solde disponible',null,'Non fourni par l’API réception disponible'],
            ['Réception','Paiement SMS',null,'Non fourni par l’API réception disponible'],
            ['Réception','Total des reversements',null,'Non fourni par l’API réception disponible'],
            ['Payout','Solde du wallet BE-CASH',$wallet['balance']??null,$wallet?'API Paiement Pro · '.$wallet['checked_at'].' UTC':($error??'Indisponible')],
            ['Payout','Total des rechargements',null,'Non fourni par getBalance'],
            ['Payout','Total des envois',null,'Non fourni par getBalance'],
            ['Payout','Total des frais de transaction',null,'Non fourni par getBalance'],
        ];
        // Deduplicate successful notifications before computing gross local receipts.
        $q=App::db()->query("SELECT DATE(p.paid_at) day,SUM(p.amount) amount FROM (
          SELECT r.id,(r.rental_fee+r.deposit) amount,MIN(n.received_at) paid_at
          FROM rentals r JOIN payment_notifications n ON n.rental_id=r.id
          WHERE r.payment_environment='production' AND JSON_UNQUOTE(JSON_EXTRACT(n.payload,'$.responsecode'))='0'
          GROUP BY r.id,r.rental_fee,r.deposit
        ) p WHERE p.paid_at>=UTC_DATE()-INTERVAL 1 DAY AND p.paid_at<UTC_DATE()+INTERVAL 1 DAY GROUP BY DATE(p.paid_at)");
        $days=[];foreach($q->fetchAll() as $row){$days[$row['day']]=(int)$row['amount'];}
        $localSource='Thiebapower · encaissements bruts confirmés, cautions incluses · '.gmdate('Y-m-d H:i:s').' UTC';
        $rows[]=['Activité locale','Encaissements aujourd’hui',$days[gmdate('Y-m-d')]??0,$localSource];
        $rows[]=['Activité locale','Encaissements hier',$days[gmdate('Y-m-d',strtotime('-1 day'))]??0,$localSource];
        return ['rows'=>$rows,'checked_at'=>$wallet['checked_at']??null,'error'=>$error];
    }
}
