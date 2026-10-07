<?php
namespace App\Services;
final class RentalReceipt
{
    public static function report(array $r,?array $settlement,?string $paidAt):array
    {
        $money=static fn($n)=>number_format((int)$n,0,',',' ').' FCFA';
        $date=static fn($s)=>$s?:'Non confirmé';
        $duration=empty($r['started_at'])||empty($r['returned_at'])?0:max(0,strtotime($r['returned_at'].' UTC')-strtotime($r['started_at'].' UTC'));
        $late=empty($r['due_at'])?0:max(0,strtotime($r['returned_at'].' UTC')-strtotime($r['due_at'].' UTC'));
        $bill=RentalBilling::calculate($r,$late);
        $deduction=(int)($settlement['deduction']??$r['late_charge']??0);
        $refund=(int)($settlement['refund_amount']??max(0,(int)$r['deposit']-$deduction));
        $rule=($r['billing_rule']??'legacy_hourly')===RentalBilling::RULE
            ? (int)($r['grace_minutes']??5).' minutes gratuites après le délai, puis prorata du tarif payé. Secondes facturables : '.$bill['billable_seconds'].'. Arrondi au FCFA supérieur ; retenue plafonnée à la caution.'
            : 'Règle initiale : '.$r['late_percent'].' % de la caution par heure de retard entamée, plafonnée à la caution.';
        $status=['pending'=>'À rembourser','processing'=>'En traitement','refunded'=>'Restitution clôturée','unknown'=>'À vérifier','failed'=>'Échec à traiter'];
        $rows=[
            ['Contrat','Référence / client',$r['reference']."\n".$r['customer_name']."\n".$r['customer_phone']],
            ['Matériel','Station de départ / batterie',($r['station_code']??'—')."\n".$r['battery_serial']],
            ['Création','Location créée (UTC)',$date($r['created_at'])],
            ['Paiement','Succès vérifié (UTC)',$date($paidAt)."\n".$money((int)$r['rental_fee']+(int)$r['deposit']).' (location + caution)'],
            ['Sortie','Commande / sortie physique (UTC)',$date($r['release_command_at']??null)."\n".$date($r['started_at']??null)],
            ['Durée incluse','Tarif / échéance',$money($r['rental_fee']).' pour '.$r['duration_minutes']." minutes\n".$date($r['due_at']??null).' UTC'],
            ['Retour','Retour physique confirmé (UTC)',$date($r['returned_at'])."\nStation / emplacement : ".($r['returned_station']??'Non enregistré').' / '.($r['returned_slot']??'—')."\nDurée utilisée : ".sprintf('%02d:%02d:%02d',intdiv($duration,3600),intdiv($duration%3600,60),$duration%60)],
            ['Calcul','Conditions de facturation',$rule."\nMontant calculé : ".$money($bill['calculated_charge'])."\nRetenue effective : ".$money($deduction)],
            ['Restitution','Montant / état',$money($refund)."\n".($status[$settlement['status']??'']??'Non enregistré')."\nConfirmation UTC : ".$date($settlement['confirmed_at']??null)],
        ];
        return ['headers'=>['Étape','Détail','Valeur'],'rows'=>$rows,'cards'=>[
            ['label'=>'Tarif de location','value'=>$money($r['rental_fee'])],['label'=>'Caution versée','value'=>$money($r['deposit'])],['label'=>'Retenue','value'=>$money($deduction)],['label'=>'Caution à restituer','value'=>$money($refund)]
        ]];
    }
}
