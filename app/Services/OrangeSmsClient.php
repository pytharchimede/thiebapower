<?php
namespace App\Services;
final class OrangeSmsClient
{
    public const BASE='https://api.orange.com';
    private ?string $token=null;
    private float $expires=0;
    public function __construct(private array $settings, private ?\Closure $transport=null) {}
    public static function phone(string $number): string
    {
        $n=preg_replace('/[\s().-]+/','',trim($number));
        if (str_starts_with($n,'00')) $n='+'.substr($n,2);
        if (preg_match('/^\d{10}$/D',$n)) $n='+225'.$n;
        elseif (preg_match('/^225\d{10}$/D',$n)) $n='+'.$n;
        if (!preg_match('/^\+225\d{10}$/D',$n)) throw new \InvalidArgumentException('Numéro ivoirien attendu : 10 chiffres, ou +225 suivi de 10 chiffres.');
        return $n;
    }
    public static function payload(array $s,string $phone,string $message): array
    {
        OrangeSmsSettings::requireSender($s);
        $phone=self::phone($phone);
        if (trim($message)==='' || !preg_match('//u',$message) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$message)) throw new \InvalidArgumentException('Message vide ou invalide.');
        preg_match_all('/./us',$message,$characters);
        if (count($characters[0])>160) throw new \InvalidArgumentException('Le test est limité à 160 caractères. Les caractères Unicode peuvent consommer plusieurs unités SMS.');
        $body=['address'=>'tel:'.$phone,'senderAddress'=>$s['sender_address'],'outboundSMSTextMessage'=>['message'=>$message]];
        if (($s['sender_mode']??'default')==='custom') {
            if (!$s['sender_approved']) throw new \InvalidArgumentException('Nom expéditeur non approuvé.');
            $body['senderName']=$s['sender_name'];
        }
        return ['outboundSMSMessageRequest'=>$body];
    }
    private function request(string $method,string $path,array $headers,?string $body=null): array
    {
        if ($this->transport) return ($this->transport)($method,self::BASE.$path,$headers,$body);
        if (!extension_loaded('curl')) throw new \RuntimeException('Extension PHP cURL requise.');
        $c=curl_init(self::BASE.$path);
        curl_setopt_array($c,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if ($body!==null) curl_setopt($c,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($c); $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
        if ($raw===false || $status===0) throw new \RuntimeException('Connexion Orange interrompue. Pour un envoi, le résultat peut être inconnu : vérifier avant de recommencer.');
        $data=json_decode($raw,true);
        return ['http_status'=>$status,'data'=>is_array($data)?$data:[]];
    }
    public function authenticate(): array
    {
        [$id,$secret]=OrangeSmsSettings::credentials($this->settings);
        $r=$this->request('POST','/oauth/v3/token',['Authorization: Basic '.base64_encode($id.':'.$secret),'Content-Type: application/x-www-form-urlencoded','Accept: application/json'],'grant_type=client_credentials');
        if ($r['http_status']!==200 || empty($r['data']['access_token'])) throw new \RuntimeException('Authentification Orange refusée (HTTP '.$r['http_status'].'). Source : '.OrangeSmsSettings::authenticationInfo($this->settings)['credentials_source'].'. Vérifier les identifiants de cette source.');
        $this->token=(string)$r['data']['access_token'];
        $this->expires=microtime(true)+max(0,(int)($r['data']['expires_in']??3600)-60);
        return OrangeSmsSettings::authenticationInfo($this->settings)+['http_status'=>200,'state'=>'authenticated','expires_in'=>(int)($r['data']['expires_in']??3600)];
    }
    private function authorized(string $method,string $path,?array $body=null): array
    {
        if ($this->token===null || microtime(true)>=$this->expires) $this->authenticate();
        return $this->request($method,$path,['Authorization: Bearer '.$this->token,'Content-Type: application/json'],$body===null?null:json_encode($body,JSON_THROW_ON_ERROR));
    }
    public function inspect(string $operation): array
    {
        $paths=['balance'=>'contracts','usage'=>'statistics','purchases'=>'purchaseorders'];
        if (!isset($paths[$operation])) throw new \InvalidArgumentException('Opération invalide.');
        $r=$this->authorized('GET','/sms/admin/v1/'.$paths[$operation]);
        if ($r['http_status']!==200) throw new \RuntimeException('Consultation Orange refusée (HTTP '.$r['http_status'].').');
        return $r+['state'=>'checked'];
    }
    public function send(string $phone,string $message): array
    {
        if (!OrangeSmsSettings::serverEnabled()) throw new \InvalidArgumentException('Envois SMS bloqués par ORANGE_SMS_ENABLED dans le .env.');
        $body=self::payload($this->settings,$phone,$message);
        if (!$this->settings['enabled']) throw new \InvalidArgumentException('Activez l’intégration SMS pour envoyer.');
        if ($this->settings['mode']==='simulation') return ['http_status'=>null,'state'=>'simulated','resource_id'=>null];
        if ($this->settings['mode']!=='production') throw new \InvalidArgumentException('Mode invalide.');
        // Never retry a POST automatically: a timeout or server error can conceal an accepted SMS.
        $r=$this->authorized('POST','/smsmessaging/v1/outbound/'.rawurlencode($this->settings['sender_address']).'/requests',$body);
        $state=$r['http_status']===201?'accepted':($r['http_status']>=500?'unknown':'rejected');
        $url=$r['data']['outboundSMSMessageRequest']['resourceURL']??$r['data']['resourceURL']??'';
        $code=$r['data']['requestError']['serviceException']['messageId']??$r['data']['requestError']['policyException']['messageId']??$r['data']['code']??null;
        $code=is_scalar($code) && preg_match('/^[A-Za-z0-9_.-]{1,80}$/D',(string)$code)?(string)$code:null;
        return ['provider_code'=>$code,'http_status'=>$r['http_status'],'state'=>$state,'resource_id'=>$url!==''?substr(basename((string)parse_url($url,PHP_URL_PATH)),0,190):null];
    }
}
