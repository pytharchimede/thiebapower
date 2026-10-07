<?php
namespace App\Services;
use App\Core\App;
final class SmsTemplates
{
 public static function definitions(): array
 {
  return [
   'phone_otp'=>['Verification du telephone','Thiebapower : votre code est {{otp_code}}. Valable {{otp_minutes}} min. Ne le partagez jamais.',['otp_code'=>'123456','otp_minutes'=>'5']],
   'payment_pending'=>['Paiement en attente','Bonjour {{customer_name}}, finalisez le paiement de {{amount}} FCFA pour votre location {{reference}}.',['customer_name'=>'Ulrich','amount'=>'100','reference'=>'TBP-EXEMPLE']],
   'payment_confirmed'=>['Paiement confirme','Bonjour {{customer_name}}, paiement de {{amount}} FCFA confirme pour {{reference}}. La sortie de votre batterie est en cours.',['customer_name'=>'Ulrich','amount'=>'100','reference'=>'TBP-EXEMPLE']],
   'payment_failed'=>['Paiement refuse','Bonjour {{customer_name}}, le paiement de {{reference}} a echoue. Aucune batterie ne sera liberee. Contactez le support si vous avez ete debite.',['customer_name'=>'Ulrich','reference'=>'TBP-EXEMPLE']],
   'release_failed'=>['Sortie de batterie a verifier','Bonjour {{customer_name}}, la sortie de batterie pour {{reference}} est a verifier. Contactez le personnel avant de refaire un paiement.',['customer_name'=>'Ulrich','reference'=>'TBP-EXEMPLE']],
   'rental_started'=>['Location commencee','Bonjour {{customer_name}}, votre location {{reference}} a commence. Duree incluse : {{duration_minutes}} min. Retour avant {{due_time}}.',['customer_name'=>'Ulrich','reference'=>'TBP-EXEMPLE','duration_minutes'=>'60','due_time'=>'14:30']],
   'reminder_5min'=>['Rappel cinq minutes avant echeance','Bonjour {{customer_name}}, il vous reste {{remaining_minutes}} min pour redeposer votre batterie Thiebapower avant {{due_time}} et eviter le depassement.',['customer_name'=>'Ulrich','remaining_minutes'=>'5','due_time'=>'14:30']],
   'rental_overdue'=>['Duree incluse depassee','Bonjour {{customer_name}}, la duree incluse de {{reference}} est depassee. Merci de redeposer la batterie. Les conditions de facturation de votre location restent applicables.',['customer_name'=>'Ulrich','reference'=>'TBP-EXEMPLE']],
   'rental_returned'=>['Retour confirme','Merci {{customer_name}}, le retour de votre batterie est confirme pour {{reference}}. Retenue calculee : {{late_charge}} FCFA.',['customer_name'=>'Ulrich','reference'=>'TBP-EXEMPLE','late_charge'=>'0']],
   'refund_pending'=>['Restitution de caution en attente','Bonjour {{customer_name}}, restitution de {{refund_amount}} FCFA en attente pour {{reference}}. Elle sera confirmee apres validation du paiement.',['customer_name'=>'Ulrich','refund_amount'=>'200','reference'=>'TBP-EXEMPLE']],
   'refund_confirmed'=>['Restitution de caution confirmee','Bonjour {{customer_name}}, la restitution de {{refund_amount}} FCFA est confirmee pour {{reference}}. Merci de votre confiance.',['customer_name'=>'Ulrich','refund_amount'=>'200','reference'=>'TBP-EXEMPLE']],
  ];
 }
 public static function all(): array
 {
  $items=[];foreach(self::definitions() as $key=>$d)$items[$key]=['key'=>$key,'label'=>$d[0],'content'=>$d[1],'variables'=>$d[2],'enabled'=>false];
  try{$rows=App::db()->query('SELECT template_key,content,enabled FROM sms_templates')->fetchAll();}
  catch(\PDOException $e){if($e->getCode()==='42S02')return $items;throw $e;}
  foreach($rows as $row)if(isset($items[$row['template_key']])){$items[$row['template_key']]['content']=$row['content'];$items[$row['template_key']]['enabled']=(bool)$row['enabled'];}
  return $items;
 }
 public static function validate(string $key,string $content): void
 {
  $defs=self::definitions();if(!isset($defs[$key]))throw new \InvalidArgumentException('Modele SMS inconnu.');
  if(trim($content)===''||!preg_match('//u',$content)||self::length($content)>480)throw new \InvalidArgumentException('Modele vide, invalide ou trop long (480 caracteres maximum).');
  preg_match_all('/\{\{([a-z_]+)\}\}/',$content,$m);
  foreach($m[1] as $v)if(!array_key_exists($v,$defs[$key][2]))throw new \InvalidArgumentException('Variable non autorisee : '.$v);
  $plain=preg_replace('/\{\{[a-z_]+\}\}/','',$content);
  if(str_contains($plain,'{{')||str_contains($plain,'}}'))throw new \InvalidArgumentException('Syntaxe attendue : {{nom_variable}}.');
  if($key==='phone_otp'&&!in_array('otp_code',$m[1],true))throw new \InvalidArgumentException('Le modele OTP doit contenir {{otp_code}}.');
  if($key==='reminder_5min'&&(!in_array('customer_name',$m[1],true)||!in_array('remaining_minutes',$m[1],true)))throw new \InvalidArgumentException('Le rappel doit contenir le nom client et les minutes restantes.');
 }
 public static function length(string $text): int {preg_match_all('/./us',$text,$m);return count($m[0]);}
 public static function render(string $key,string $content,array $variables): string
 {
  self::validate($key,$content);
  $text=preg_replace_callback('/\{\{([a-z_]+)\}\}/',static function($m)use($variables){if(!array_key_exists($m[1],$variables)||!is_scalar($variables[$m[1]]))throw new \InvalidArgumentException('Renseignez la variable '.$m[1]);$v=trim((string)$variables[$m[1]]);if($v===''||str_contains($v,'{{')||preg_match('/[\x00-\x1f\x7f]/',$v)||self::length($v)>160)throw new \InvalidArgumentException('Valeur invalide pour '.$m[1]);return $v;},$content);
  if(self::length($text)>480)throw new \InvalidArgumentException('Le SMS genere depasse 480 caracteres. Raccourcissez le modele ou ses variables.');
  return $text;
 }
 public static function fromInput(string $key,string $json,bool $sending=false): array
 {
  $items=self::all();if(!isset($items[$key]))throw new \InvalidArgumentException('Modele inconnu.');
  if($sending&&!$items[$key]['enabled'])throw new \InvalidArgumentException('Ce modele est desactive. Activez-le avant de l’utiliser pour un envoi.');
  try{$v=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new \InvalidArgumentException('Variables : objet JSON invalide.');}
  if(!is_array($v))throw new \InvalidArgumentException('Variables : objet JSON attendu.');
  $text=self::render($key,$items[$key]['content'],$v);
  return ['message'=>$text,'characters'=>self::length($text),'segments'=>self::segments($text)];
 }
 public static function send(string $key,string $phone,array $variables): array
 {
  if(!OrangeSmsSettings::serverEnabled())throw new \InvalidArgumentException('Envois SMS desactives dans le .env.');
  $t=self::all()[$key]??null;if(!$t||!$t['enabled'])throw new \InvalidArgumentException('Modele SMS desactive ou inconnu.');
  $text=self::render($key,$t['content'],$variables);
  return (new OrangeSmsClient(OrangeSmsSettings::all()))->send($phone,$text);
 }
 public static function segments(string $text): int
 {
  // GSM basic and extension alphabet: extension characters consume two septets.
  $basic="@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
  $extended="^{}\\[~]|€\f";$units=0;
  preg_match_all('/./us',$text,$m);
  foreach($m[0] as $c){if(str_contains($basic,$c))$units++;elseif(str_contains($extended,$c))$units+=2;else{$units=intdiv(strlen(iconv('UTF-8','UTF-16BE',$text)),2);return $units<=70?1:(int)ceil($units/67);}}
  return $units<=160?1:(int)ceil($units/153);
 }
 public static function save(string $key,string $content,bool $enabled): void
 {
  self::validate($key,$content);
  App::db()->prepare('INSERT INTO sms_templates(template_key,content,enabled,enabled_at) VALUES(?,?,?,IF(?=1,UTC_TIMESTAMP(),NULL)) ON DUPLICATE KEY UPDATE enabled_at=IF(enabled=0 AND VALUES(enabled)=1,UTC_TIMESTAMP(),enabled_at),content=VALUES(content),enabled=VALUES(enabled)')->execute([$key,$content,$enabled?1:0,$enabled?1:0]);
 }
}
