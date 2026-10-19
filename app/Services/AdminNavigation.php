<?php
namespace App\Services;

/** Shared presentation map. Existing endpoint permissions remain authoritative. */
final class AdminNavigation
{
    public static function groups(): array
    {
        return [
            'locations'=>['label'=>'Locations et clients','icon'=>'fa-arrow-right-arrow-left','description'=>'Suivre les locations, repérer les incidents et aider un client.','items'=>[
                ['Locations','/admin/rentals','rentals.view','rentals','Suivre une location et consulter son reçu.'],
                ['À surveiller','/admin/rentals/watch','rentals.view','watch','Repérer les locations qui demandent votre attention.'],
                ['Assistance clients','/admin/support','rentals.view','support','Retrouver et traiter les demandes des clients.'],
            ]],
            'finances'=>['label'=>'Finances','icon'=>'fa-wallet','description'=>'Consulter les soldes, retirer de l’argent et suivre la caisse.','items'=>[
                ['Tableau de bord financier','/admin/finance','finance.view','finance','Voir les soldes et le résumé de la période.'],
                ['Retirer via Paiement Pro','/admin/finance/withdrawals','finance.view','withdrawals','Envoyer un retrait et suivre son résultat.'],
                ['Point financier','/admin/finance/summary','finance.view','summary','Analyser les entrées et sorties sur une période.'],
                ['Points financiers quotidiens','/admin/finance/daily','finance.view','daily','Comparer les mouvements jour par jour.'],
                ['Encaissements Paiement Pro','/admin/finance/receipts','finance.view','receipts','Retrouver les paiements clients confirmés.'],
                ['Reversements confirmés','/admin/finance/refunds','finance.view','refunds','Consulter les cautions effectivement remboursées.'],
                ['Journal de caisse','/admin/finance/cash','finance.view','cash','Suivre les entrées et sorties d’espèces.'],
                ['Cautions et remboursements','/admin/deposit-wallet','finance.view','wallet','Suivre les cautions et les remboursements à traiter.'],
                ['Rentabilité des stations','/admin/stations/profitability','finance.view','profitability','Comparer les revenus et les coûts des stations.'],
                ['Outils API Paiement Pro','/admin/payout','payout.view','payout','Outils techniques et essais financiers.'],
            ]],
            'equipements'=>['label'=>'Équipements','icon'=>'fa-tower-broadcast','description'=>'Retrouver les stations, les batteries et les étiquettes QR.','items'=>[
                ['Terminaux / stations','/admin/stations','stations.view','stations','Consulter les stations et leur état de connexion.'],
                ['Batteries','/admin/batteries','batteries.view','fleet','Retrouver une batterie et consulter son état.'],
                ['Étiquettes QR','/admin/stations/labels','labels.view','labels','Préparer les étiquettes à installer sur les stations.'],
            ]],
            'offres'=>['label'=>'Tarifs et offres','icon'=>'fa-tags','description'=>'Gérer les prix de location, les cautions et les promotions.','items'=>[
                ['Tarification','/admin/pricing','pricing.view','pricing','Consulter et régler les tarifs.'],
                ['Offres et fidélité','/admin/promotions','pricing.view','promotions','Gérer les promotions et les avantages clients.'],
            ]],
            'communication'=>['label'=>'Communication','icon'=>'fa-comment-sms','description'=>'Suivre les SMS et gérer les messages adressés aux clients.','items'=>[
                ['SMS Orange CI','/admin/sms','sms.view','sms','Consulter les envois et le service SMS.'],
                ['Modèles de SMS','/admin/sms/templates','sms.manage','sms-templates','Consulter les messages automatiques.'],
            ]],
            'administration'=>['label'=>'Administration','icon'=>'fa-user-shield','description'=>'Gérer les accès, les réglages et le suivi technique.','items'=>[
                ['Statistiques en direct','/admin/statistics','dashboard.view','statistics','Suivre les indicateurs de l’activité.'],
                ['Comptes et droits','/admin/users','users.manage','users','Gérer les utilisateurs et leurs autorisations.'],
                ['Journal et visites','/admin/audit','audit.view','audit','Consulter la trace des actions.'],
                ['Système','/admin/system','system.manage','system','Contrôler le fonctionnement des services.'],
                ['Interface publique et support','/admin/public-settings','system.manage','public-settings','Configurer l’expérience des clients.'],
                ['Formation et évolutions','/admin/training','dashboard.view','training','Retrouver les guides d’utilisation.'],
            ]],
        ];
    }

    public static function visible(?array $user=null): array
    {
        $groups=[];
        foreach(self::groups() as $key=>$group){
            $group['items']=array_values(array_filter($group['items'],static function(array $item)use($user):bool{
                return Auth::can($item[2],$user) && ($item[3]!=='profitability'||Auth::can('stations.view',$user));
            }));
            if(!$group['items'])continue;
            $group['href']=$key==='finances'?'/admin/finance':'/admin/modules/'.$key;
            // The API tools can be visible without permission to open Finance itself.
            if($key==='finances'&&!Auth::can('finance.view',$user))$group['href']='/admin/modules/finances';
            $groups[$key]=$group;
        }
        return $groups;
    }

    public static function active(string $href,string $path): bool
    {
        if($href===$path)return true;
        if($href==='/admin/rentals')return str_starts_with($path,'/admin/rentals/detail');
        if($href==='/admin/stations')return in_array($path,['/admin/stations/detail','/admin/stations/profile','/admin/stations/places'],true);
        if($href==='/admin/batteries')return str_starts_with($path,'/admin/batteries/');
        return false;
    }
}
