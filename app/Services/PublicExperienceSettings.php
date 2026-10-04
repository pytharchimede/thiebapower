<?php
namespace App\Services;
use App\Core\App;
final class PublicExperienceSettings
{
 public const ELEMENTS=[
  'logo'=>['Logo','La marque dans l’en-tête','.kiosk-header .kiosk-logo'],
  'slogan'=>['Slogan','La signature sous le logo','.public-slogan'],
  'locations'=>['Mes locations','Le raccourci dans l’en-tête','.kiosk-header a[href="/my-rentals"]'],
  'stations'=>['Trouver une station','Le raccourci dans l’en-tête','.kiosk-header a[href="/stations/map"]'],
  'intro'=>['Présentation','Les textes de présentation de l’accueil','.kiosk-qr-copy>h1,.kiosk-qr-copy>p,.kiosk-qr-copy>.kiosk-tag'],
  'steps'=>['Cycle de location','Les quatre étapes sur l’accueil','.public-cycle'],
  'illustration'=>['Illustration batterie','Le visuel dans la page de location','.bank-illustration'],
  'price_teaser'=>['Tarif de présentation','Le prix dans le panneau latéral','.side-footer'],
  'payment_logos'=>['Logos des paiements','Les petits logos des moyens de paiement','.payment-logos'],
  'offers'=>['Offres publiques','Le lien et les coupons publics','a[href="/offers"],.public-offers .promo-grid'],
  'manager_name'=>['Nom du gérant','Dans les fiches et sur la carte','.finder-manager'],
  'manager_phone'=>['Téléphone du gérant','Dans les fiches et sur la carte','.finder-phone'],
  'hours'=>['Horaires des stations','Dans les fiches et sur la carte','.finder-hours'],
  'stock'=>['Stock disponible','Dans les fiches et sur la carte','.finder-stock'],
  'summary'=>['Résumé des locations','Les compteurs dans Mes locations','.customer-summary'],
  'incidents'=>['Signalement des incidents','Le formulaire lié à une location','.customer-incident'],
  'footer'=>['Pied de page de marque','Le texte au bas de l’accueil et de la location','.kiosk-footer'],
 ];
 public static function defaults():array {return ['support_enabled'=>false,'whatsapp_enabled'=>false,'whatsapp'=>'','phone_enabled'=>false,'phone'=>'','email_enabled'=>false,'email'=>'','chat_enabled'=>false,'chat'=>'','message'=>'Bonjour, j’ai besoin d’aide pour ma location Thiebapower.','visible'=>array_fill_keys(array_keys(self::ELEMENTS),true)];}
 public static function all():array {
  static $settings;if($settings!==null)return $settings;$defaults=self::defaults();
  try{$raw=App::db()->query('SELECT configuration FROM public_experience_settings WHERE id=1')->fetchColumn();}catch(\PDOException $e){if($e->getCode()!=='42S02')throw $e;return $settings=$defaults;}
  $data=json_decode($raw?:'{}',true);if(!is_array($data))$data=[];$settings=array_replace($defaults,$data);$settings['visible']=array_replace($defaults['visible'],is_array($data['visible']??null)?$data['visible']:[]);return $settings;
 }
 public static function validate(array $input):array {
  $s=self::defaults();foreach(['support','whatsapp','phone','email','chat'] as $k)$s[$k.'_enabled']=isset($input[$k.'_enabled']);
  foreach(['whatsapp','phone'] as $k){$v=trim((string)($input[$k]??''));if($v!==''&&!preg_match('/^\+[1-9][0-9]{7,14}$/D',$v))throw new \InvalidArgumentException('Utilisez le format international pour les numéros : +225 suivi du numéro.');$s[$k]=$v;}
  $s['email']=trim((string)($input['email']??''));if($s['email']!==''&&(!filter_var($s['email'],FILTER_VALIDATE_EMAIL)||strlen($s['email'])>190))throw new \InvalidArgumentException('Adresse email invalide.');
  $s['chat']=trim((string)($input['chat']??''));if($s['chat']!==''&&(!filter_var($s['chat'],FILTER_VALIDATE_URL)||parse_url($s['chat'],PHP_URL_SCHEME)!=='https'||parse_url($s['chat'],PHP_URL_USER)!==null||strlen($s['chat'])>500))throw new \InvalidArgumentException('Le chat doit utiliser une URL HTTPS sans identifiants.');
  $s['message']=trim((string)($input['message']??''));if(strlen($s['message'])>500)throw new \InvalidArgumentException('Le message WhatsApp est limité à 500 caractères.');
  foreach(['whatsapp','phone','email','chat'] as $k)if($s[$k.'_enabled']&&$s[$k]==='')throw new \InvalidArgumentException('Renseignez chaque canal activé.');
  foreach(self::ELEMENTS as $k=>$v)$s['visible'][$k]=isset($input['visible'][$k]);return $s;
 }
 public static function save(array $s):void {App::db()->prepare('INSERT INTO public_experience_settings(id,configuration) VALUES(1,?) ON DUPLICATE KEY UPDATE configuration=VALUES(configuration)')->execute([json_encode($s,JSON_THROW_ON_ERROR)]);}
}
