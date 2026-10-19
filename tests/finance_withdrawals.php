<?php
namespace App\Core {
 final class App {
  public static object $database;
  public static function db():object{return self::$database;}
  public static function env(string $key,string $default=''):string{return ['APP_URL'=>'https://thiebapower.com','PAIEMENTPRO_MERCHANT_ID'=>'fixture','PAIEMENTPRO_SECRET_KEY'=>'test-secret'][$key]??$default;}
 }
}
namespace App\Services {
 final class IntegrationSettings{public static function all():array{return ['paiementpro'=>'production'];}}
 final class Audit {public static function event(...$args):void{}}
 final class PayoutApiAudit {public static function request(...$args):void{}public static function record(...$args):void{}}
}
namespace {
 spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
 final class Statement {
  private array $params=[];
  private int $affected=0;
  public function rowCount():int{return $this->affected;}
  public function __construct(private Database $db,private string $sql){}
  public function execute(array $p):void {
   $this->params=$p;
   if(str_starts_with($this->sql,'INSERT INTO finance_withdrawals'))$this->db->ledger=['reference'=>$p[0],'request_token'=>$p[1],'amount'=>$p[2],'recipient_phone'=>$p[3],'recipient_channel'=>$p[4],'status'=>'unknown','provider_session_id'=>null];
   if(str_contains($this->sql,"SET status='released_unknown'")){
    if($this->db->overdue && in_array($this->db->ledger['status'],['unknown','initiated','processing'],true) && $this->db->ledger['reference']===$p[1]){$this->db->ledger['status']='released_unknown';$this->affected=1;}
    return;
   }
   if(str_starts_with($this->sql,'UPDATE finance_withdrawals SET status=')){$this->db->ledger['status']=$p[0];$this->db->ledger['provider_session_id']=$p[1];}
  }
  public function fetchColumn():mixed {
   if(str_contains($this->sql,'GET_LOCK')||str_contains($this->sql,'RELEASE_LOCK'))return 1;
   if(str_contains($this->sql,'COUNT(*)'))return $this->db->ledger&&in_array($this->db->ledger['status'],['unknown','processing','initiated'],true)?1:0;
   if(str_contains($this->sql,'request_token='))return ($this->db->ledger['request_token']??'')===($this->params[0]??'')?$this->db->ledger['reference']:false;
   return false;
  }
  public function fetch():mixed{if(str_contains($this->sql,'deposit_wallet_settings'))return ['fee_rules'=>json_encode(['WAVECI'=>['fixed'=>0,'basis_points'=>200]])];return $this->db->ledger;}
 }
 final class Database {public ?array $ledger=null;public bool $overdue=false;public function prepare(string $s):Statement{return new Statement($this,$s);}public function query(string $s):Statement{return new Statement($this,$s);}}
 \App\Core\App::$database=new Database();$calls=0;
 $soap=new class {public int $calls=0;public int $balance=1000;public function getBalance($p){return (object)['status'=>'success','balance'=>$this->balance,'currency'=>'XOF'];}public function initTransact($p){$this->calls++;throw new \RuntimeException('Timeout after sending');}};
 $provider=new \App\Services\PaiementProPayoutService(static fn()=>$soap,static fn()=>1791044082,static function(...$args){});
 $service=new \App\Services\FinanceWithdrawalService($provider);
 $input=['amount'=>'200','request_token'=>str_repeat('a',32),'recipient_name'=>'Client','reason'=>'Retrait','phone'=>'0700000000','channel'=>'WAVECI','confirm'=>'1'];
 $soap->balance=200;$rejected=false;
 try{$service->send($input,1);}catch(\LogicException){$rejected=true;}
 if(!$rejected||$soap->calls!==0||\App\Core\App::$database->ledger!==null)throw new \RuntimeException('Fees must prevent an overdraw before recording or sending');
 $soap->balance=1000;
 $ref=$service->send($input,1);
 if($soap->calls!==1||\App\Core\App::$database->ledger['status']!=='unknown')throw new \RuntimeException('Unknown outcome must remain pending');
 if($service->send($input,1)!==$ref||$soap->calls!==1)throw new \RuntimeException('Duplicate request emitted twice');
 foreach([['request_token'=>str_repeat('b',32)],['confirm'=>'0'],['amount'=>'0'],['phone'=>'bad'],['channel'=>'invalid']] as $override){
  $rejected=false;try{$service->send(array_replace($input,$override),1);}catch(\LogicException $e){$rejected=true;}
  if(!$rejected||$soap->calls!==1)throw new \RuntimeException('Uncertain/invalid withdrawal was sent');
 }
 foreach([['Bénéficiaire sans versement',false],['',true],['Bénéficiaire sans versement',true]] as [$reason,$confirm]) {
  $rejected=false;try{$service->unblock($ref,$reason,$confirm,1);}catch(\LogicException $e){$rejected=true;}
  if(!$rejected||\App\Core\App::$database->ledger['status']!=='unknown')throw new \RuntimeException('Premature or unconfirmed unlock');
 }
 \App\Core\App::$database->overdue=true;
 $service->unblock($ref,'Bénéficiaire contacté : aucun versement reçu',true,1);
 if(\App\Core\App::$database->ledger['status']!=='released_unknown'||$soap->calls!==1)throw new \RuntimeException('Unlock must not send or assert failure');
 if($service->send($input,1)!==$ref||$soap->calls!==1)throw new \RuntimeException('Unlock must preserve idempotency');
 $service->send(array_replace($input,['request_token'=>str_repeat('c',32)]),1);
 if($soap->calls!==2)throw new \RuntimeException('Explicit unlock must allow a new request');
 echo "Withdrawal validation, idempotency and unknown-outcome blocking OK (mock SOAP, no real payment)\n";
}
