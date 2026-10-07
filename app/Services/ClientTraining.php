<?php
namespace App\Services;
use App\Core\App;
final class ClientTraining {
 public const STATUSES=['proposed'=>'Proposée','review'=>'À étudier','approved'=>'Validée','planned'=>'Planifiée','progress'=>'En cours','delivered'=>'Livrée','declined'=>'Non retenue'];
 public const PRIORITIES=['low'=>'Basse','normal'=>'Normale','high'=>'Haute'];
 public static function guide():array {return require dirname(__DIR__,2).'/resources/training/client.php';}
 public static function suggestions():array {return [
 ['Chat intégré avec suivi','Centraliser les échanges entre le client et l’équipe, au-delà du lien externe actuel.','Messages liés à une location, identification du client, boîte opérateur, disponibilités et notifications.'],
 ['Alertes de stock et de connexion','Intervenir avant qu’un client ne se déplace vers une station indisponible.','Seuils par station, destinataires, fréquence et journal des alertes.'],
 ['Maintenance préventive des batteries','Mieux suivre la qualité du parc et les remplacements.','Historique des usages, anomalies, contrôles périodiques et état de maintenance.'],
 ['Fidélité avec compte client','Retrouver ses avantages et locations sur plusieurs appareils.','Compte client, récupération sécurisée des accès, règles de fidélité et consentement.'],
 ['Rapport périodique au propriétaire','Réduire le temps consacré au suivi de l’activité.','Synthèse datée des revenus, retards, coûts et incidents, avec destinataires validés.']
 ];}
 public static function validate(array $input):array {
  $r=[];foreach(['title'=>160,'description'=>6000,'benefit'=>1000,'scope'=>2000,'timeframe'=>160] as $key=>$max){$r[$key]=trim((string)($input[$key]??''));if(mb_strlen($r[$key])>$max||($key!=='timeframe'&&$r[$key]===''))throw new \InvalidArgumentException('Complétez le titre, le besoin, le bénéfice et le périmètre dans les limites indiquées.');}
  $r['status']=(string)($input['status']??'proposed');$r['priority']=(string)($input['priority']??'normal');if(!isset(self::STATUSES[$r['status']],self::PRIORITIES[$r['priority']]))throw new \InvalidArgumentException('Statut ou priorité invalide.');
  $budget=trim((string)($input['budget']??''));$r['budget']=$budget===''?null:filter_var($budget,FILTER_VALIDATE_INT);if($r['budget']!==null&&($r['budget']===false||$r['budget']<0||$r['budget']>1000000000))throw new \InvalidArgumentException('Budget indicatif invalide.');return $r;
 }
 public static function guidePdf():string {$g=self::guide();$rows=[['Avant de commencer',$g['intro'],'Public : propriétaire, gérant et opérateur.']];foreach($g['modules'] as $i=>$m)$rows[]=[($i+1).'. '.$m[0],$m[1]."\n\n".implode("\n\n",$m[2]),'Exercice : '.$m[3]."\n\nAccès : ".$m[4]];return (new BrandedReportPdf)->render('Formation client - Guide d’exploitation',['Module','Objectif et procédure','Mise en pratique'],$rows,[['label'=>'Version','value'=>$g['version']],['label'=>'Modules','value'=>count($g['modules'])],['label'=>'Durée suggérée','value'=>'2 heures']],rtrim(App::env('APP_URL','https://thiebapower.com'),'/').'/admin/training',$g['version'].' | Exercices en environnement de test ; opérations réelles réservées au personnel autorisé.');}
 public static function proposalsPdf(array $proposals):string {$rows=[];foreach($proposals as $p)$rows[]=['EVO-'.str_pad((string)$p['id'],4,'0',STR_PAD_LEFT).' - '.$p['title'],'Besoin : '.$p['description']."\n\nBénéfice : ".$p['benefit']."\n\nPérimètre : ".$p['scope'],self::STATUSES[$p['status']]."\nPriorité : ".self::PRIORITIES[$p['priority']]."\nBudget indicatif : ".($p['budget']===null?'À définir':number_format((int)$p['budget'],0,',',' ').' FCFA')."\nDélai indicatif : ".($p['timeframe']?:'À définir')."\nActualisée le : ".$p['updated_at']];return (new BrandedReportPdf)->render('Propositions d’évolution - Thiebapower',['Proposition','Besoin, bénéfice et périmètre','Suivi et estimation'],$rows,[['label'=>'Propositions','value'=>count($rows)],['label'=>'Document','value'=>'Pour discussion']],rtrim(App::env('APP_URL','https://thiebapower.com'),'/').'/admin/training','Estimations indicatives, sans engagement contractuel. Le statut ne déclenche aucune installation ni facturation.');}
}
