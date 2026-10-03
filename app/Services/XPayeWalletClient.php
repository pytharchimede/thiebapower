<?php
namespace App\Services;
use App\Core\App;
/** XPaye's supplied request contract. A request acknowledgement is not credit confirmation. */
final class XPayeWalletClient
{
    private $transport;
    public function __construct(?callable $transport=null) {$this->transport=$transport;}
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
        if($this->transport)return ($this->transport)($path,$payload,$token);
        if(!function_exists('curl_init'))throw new \RuntimeException('Extension PHP cURL requise.');
        $ch=curl_init('https://api.xpaye.africa'.$path);
        $headers=['Content-Type: application/json','Accept: application/json'];if($token!==null)$headers[]='Authorization: Bearer '.$token;
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($body===false || strlen($body)>1048576)throw new \RuntimeException('Réponse XPaye indisponible ; vérifier le mouvement avant toute nouvelle demande.');
        return ['http'=>$status,'body'=>$body];
    }
}
