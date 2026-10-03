<?php
namespace App\Services;
use App\Core\App;
/** XPaye's supplied request contract. A request acknowledgement is not credit confirmation. */
final class XPayeWalletClient
{
    private $transport;
    private array $events=[];
    private array $secrets=[];
    public function __construct(?callable $transport=null,private ?string $traceKey=null) {$this->transport=$transport;}
    public function diagnostics():array {return ['at'=>gmdate('c'),'events'=>$this->events];}
    public function redact(mixed $value,?string $key=null):mixed
    {
        if($key!==null && preg_match('/token|password|secret|authorization|api.?key|login/i',$key))return '[masqué]';
        if(is_array($value)){foreach($value as $k=>&$v)$v=$this->redact($v,(string)$k);unset($v);return $value;}
        if(is_string($value)){
            $value=str_replace(array_filter(array_merge($this->secrets,[App::env('XPAYE_LOGIN'),App::env('XPAYE_PASSWORD')])),'[masqué]',$value);
            return preg_replace('/Bearer\s+[^\s"<>]+|[A-Za-z0-9_-]{12,}\.[A-Za-z0-9_-]{12,}\.[A-Za-z0-9_-]+/i','[masqué]',$value);
        }
        return $value;
    }
    private function trace(array $event):void
    {
        $this->events[]=$this->redact($event);
        if($this->traceKey!==null && preg_match('/^[a-z0-9-]{1,80}$/D',$this->traceKey)){
            try{SystemStorage::write('xpaye-'.$this->traceKey.'.json',$this->diagnostics());}catch(\Throwable $e){error_log('Stockage diagnostic XPaye indisponible');}
        }
    }
    public static function configured():bool {return App::env('XPAYE_LOGIN')!=='' && App::env('XPAYE_PASSWORD')!=='';}
    public function authenticate():string
    {
        if(!self::configured())throw new \LogicException('Identifiants XPaye absents dans la configuration serveur.');
        $r=$this->post('/auth/token',['login'=>App::env('XPAYE_LOGIN'),'password'=>App::env('XPAYE_PASSWORD')]);
        $data=json_decode($r['body'],true);
        $token=is_array($data)?($data['token']??$data['access_token']??$data['data']['token']??$data['data']['access_token']??null):null;
        if($r['http']<200 || $r['http']>=300 || !is_string($token) || strlen($token)<1 || strlen($token)>8192 || preg_match('/[\r\n]/',$token)) {
            throw new \RuntimeException('Authentification XPaye non confirmée : vérifier les identifiants et le format de réponse token.');
        }
        $this->secrets[]=$token;
        return $token;
    }
    public function request(int $amount,string $token):array
    {
        if($amount<1 || $amount>100000000)throw new \InvalidArgumentException('Montant de transfert hors limites.');
        $reply=$this->post('/wallet/request',['montant'=>$amount],$token);
        // Store only neutral provider fields. Never store tokens, credentials, raw errors or arbitrary payloads.
        $data=json_decode($reply['body'],true);$summary=[];
        if(is_array($data))foreach(['status','code','reference','transaction_id','montant'] as $field){
            if(isset($data[$field]) && (is_string($data[$field]) || is_numeric($data[$field])))$summary[$field]=substr(str_replace(array_filter([$token,App::env('XPAYE_LOGIN'),App::env('XPAYE_PASSWORD')]),'[masqué]',(string)$data[$field]),0,120);
        }
        return ['http'=>$reply['http'],'summary'=>$summary];
    }
    private function post(string $path,array $payload,?string $token=null):array
    {
        if($token!==null)$this->secrets[]=$token;
        $started=microtime(true);
        $event=['method'=>'POST','endpoint'=>'https://api.xpaye.africa'.$path,'payload'=>$payload,'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json']];
        if($token!==null)$event['headers']['Authorization']='Bearer [masqué]';
        try {
            if($this->transport){$reply=($this->transport)($path,$payload,$token);}
            else {
                if(!function_exists('curl_init'))throw new \RuntimeException('Extension PHP cURL requise.');
                $ch=curl_init($event['endpoint']);
                $headers=['Content-Type: application/json','Accept: application/json'];if($token!==null)$headers[]='Authorization: Bearer '.$token;
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
                $body=curl_exec($ch);$info=curl_getinfo($ch);$errno=curl_errno($ch);$error=curl_error($ch);curl_close($ch);
                $reply=['http'=>(int)($info['http_code']??0),'body'=>$body];
                $event+=['curl_errno'=>$errno,'curl_error'=>$error,'remote_ip'=>$info['primary_ip']??null,'content_type'=>$info['content_type']??null,'redirect_url'=>$info['redirect_url']??null,'dns_seconds'=>$info['namelookup_time']??null,'connect_seconds'=>$info['connect_time']??null,'total_seconds'=>$info['total_time']??null];
            }
            $event['http_status']=$reply['http'];
            if($reply['body']===false)throw new \RuntimeException('Connexion XPaye non confirmée (cURL '.($event['curl_errno']??0).') : '.($event['curl_error']??'réponse indisponible').'. Vérifiez le mouvement avant toute nouvelle demande.');
            $data=json_decode($reply['body'],true);
            // Auth tokens may be embedded in a message as well as a token field.
            if($path==='/auth/token' && is_array($data)){
                $found=$data['token']??$data['access_token']??$data['data']['token']??$data['data']['access_token']??null;
                if(is_string($found)&&$found!=='')$this->secrets[]=$found;
            }
            $event['response']=is_array($data)?$data:($path==='/auth/token'?'[Réponse non JSON d’authentification masquée]':substr($reply['body'],0,16000));
            if(strlen($reply['body'])>1048576){$event['response']='[Réponse dépassant la limite de taille]';throw new \RuntimeException('Réponse XPaye trop volumineuse ; vérifier le mouvement avant toute nouvelle demande.');}
            $event['elapsed_ms']=(int)round((microtime(true)-$started)*1000);$this->trace($event);
            return $reply;
        }catch(\Throwable $e){
            $event['error']=$e->getMessage();$event['elapsed_ms']=(int)round((microtime(true)-$started)*1000);$this->trace($event);
            throw new \RuntimeException((string)$this->redact($e->getMessage()),0,$e);
        }
    }
}
