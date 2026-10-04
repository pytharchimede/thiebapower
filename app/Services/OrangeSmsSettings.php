<?php
namespace App\Services;
use App\Core\App;
final class OrangeSmsSettings
{
    public static function defaults(): array
    {
        return ['enabled'=>false, 'mode'=>'simulation', 'client_id'=>'', 'secret_cipher'=>'',
            'sender_address'=>'tel:+2250000', 'sender_name'=>'', 'sender_approved'=>false];
    }
    public static function all(): array
    {
        try { $raw=App::db()->query('SELECT configuration FROM orange_sms_settings WHERE id=1')->fetchColumn(); }
        catch (\PDOException $e) { if ($e->getCode()==='42S02') return self::defaults(); throw $e; }
        return array_replace(self::defaults(), json_decode($raw?:'{}',true,512,JSON_THROW_ON_ERROR));
    }
    private static function key(): string
    {
        $key=base64_decode(App::env('ORANGE_SMS_ENCRYPTION_KEY'),true);
        if ($key===false || strlen($key)!==32) throw new \InvalidArgumentException('Configurer ORANGE_SMS_ENCRYPTION_KEY (32 octets en base64) dans le fichier .env avant de saisir un secret.');
        return $key;
    }
    public static function encrypt(string $value): string
    {
        $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if ($cipher===false) throw new \RuntimeException('Chiffrement indisponible.');
        return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt(string $value): string
    {
        $raw=base64_decode($value,true);
        if ($raw===false || strlen($raw)<29) throw new \RuntimeException('Secret SMS invalide.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if ($plain===false) throw new \RuntimeException('Impossible de déchiffrer le secret SMS. Vérifier la clé serveur.');
        return $plain;
    }
    public static function credentials(array $s): array
    {
        $id=$s['client_id']?:App::env('ORANGE_SMS_CLIENT_ID');
        $secret=$s['secret_cipher']!==''?self::decrypt($s['secret_cipher']):App::env('ORANGE_SMS_CLIENT_SECRET');
        if ($id==='' || $secret==='') throw new \InvalidArgumentException('Renseignez le Client ID et le Client Secret Orange.');
        return [$id,$secret];
    }
    public static function validate(array $input,array $old): array
    {
        $s=self::defaults(); $s['secret_cipher']=$old['secret_cipher'];
        $s['enabled']=isset($input['enabled']); $s['mode']=(string)($input['mode']??'simulation');
        if (!in_array($s['mode'],['simulation','production'],true)) throw new \InvalidArgumentException('Mode SMS invalide.');
        $s['client_id']=trim((string)($input['client_id']??''));
        if (strlen($s['client_id'])>190 || preg_match('/[\x00-\x1f:]/',$s['client_id'])) throw new \InvalidArgumentException('Client ID invalide.');
        $secret=(string)($input['client_secret']??'');
        if (strlen($secret)>4096) throw new \InvalidArgumentException('Secret trop long.');
        if ($secret!=='') $s['secret_cipher']=self::encrypt($secret);
        elseif ($s['client_id']!==$old['client_id'] && $old['secret_cipher']!=='') throw new \InvalidArgumentException('Renseignez le nouveau secret lorsque vous changez le Client ID.');
        $s['sender_address']=trim((string)($input['sender_address']??''));
        if ($s['sender_address']!=='tel:+2250000') throw new \InvalidArgumentException('Pour Orange CI, utilisez tel:+2250000 comme adresse technique.');
        $s['sender_name']=trim((string)($input['sender_name']??''));
        if ($s['sender_name']!=='' && !preg_match('/^[A-Za-z0-9]{1,11}$/D',$s['sender_name'])) throw new \InvalidArgumentException('Nom expéditeur : 1 à 11 caractères alphanumériques.');
        $s['sender_approved']=isset($input['sender_approved']);
        if ($s['sender_name']!=='' && !$s['sender_approved']) throw new \InvalidArgumentException('Confirmez l’approbation Orange du nom expéditeur ou laissez-le vide.');
        self::requireSender($s);
        if ($s['enabled'] && $s['mode']==='production') self::credentials($s);
        return $s;
    }
    public static function requireSender(array $s): void
    {
        if ($s['mode']==='production' && ($s['sender_name']==='' || !$s['sender_approved'])) {
            throw new \InvalidArgumentException('Orange CI exige un nom expéditeur approuvé et autorisé. Renseignez ce nom et confirmez son approbation avant de passer en production.');
        }
    }
    public static function save(array $s): void
    {
        App::db()->prepare('INSERT INTO orange_sms_settings(id,configuration) VALUES(1,?) ON DUPLICATE KEY UPDATE configuration=VALUES(configuration)')->execute([json_encode($s,JSON_THROW_ON_ERROR)]);
    }
}
