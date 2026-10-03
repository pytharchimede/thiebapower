<?php
declare(strict_types=1);
namespace App\Core {final class App {public static \PDO $db;public static function db():\PDO{return self::$db;}public static function env(string $key,string $default=''):string{return getenv($key)!==false?getenv($key):$default;}}}
namespace App\Services {final class Audit {public static function event(...$args):void{}}final class SystemReports {public static function record(...$args):void{}}}
namespace {
 spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
 use App\Core\App;use App\Services\DepositWallet;use App\Services\XPayeWalletClient;
 function same($expected,$actual):void{if($expected!==$actual)throw new \RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));}
 // SQLite exercises durable state and rollback. Adapt only MySQL locking/function syntax;
 // concurrent row locking is additionally verified by inspection of the FOR UPDATE + committed state protocol.
 final class TestDB extends \PDO {
  public function prepare(string $query,array $options=[]):\PDOStatement|false {
   $query=str_replace(' FOR UPDATE','',$query);
   $query=str_replace('ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)','ON CONFLICT(request_key) DO UPDATE SET id=id',$query);
   return parent::prepare($query,$options);
  }
 }
 $db=new TestDB('sqlite::memory:');$db->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);$db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC);$db->sqliteCreateFunction('UTC_TIMESTAMP',fn()=>gmdate('Y-m-d H:i:s'));App::$db=$db;
 $db->exec("CREATE TABLE rentals(id INTEGER PRIMARY KEY,reference TEXT,payment_environment TEXT,deposit INTEGER,payout_channel TEXT,deposit_payment_verified_at TEXT);
 CREATE TABLE deposit_wallet_settings(id INTEGER PRIMARY KEY,enabled INTEGER,fee_rules TEXT);
 CREATE TABLE deposit_wallet_transfers(id INTEGER PRIMARY KEY AUTOINCREMENT,rental_id INTEGER UNIQUE,request_key TEXT UNIQUE,purpose TEXT,deposit_amount INTEGER DEFAULT 0,fee_reserve INTEGER DEFAULT 0,amount INTEGER,status TEXT DEFAULT 'pending',http_status INTEGER,response_summary TEXT,confirmation_proof TEXT,created_by INTEGER,confirmed_by INTEGER,sent_at TEXT,confirmed_at TEXT);
 INSERT INTO deposit_wallet_settings VALUES(1,1,'{}');
 INSERT INTO rentals VALUES(1,'TBP-TEST','production',5000,'WAVECI',NULL),(2,'TBP-SANDBOX','sandbox',5000,'WAVECI',NULL),(3,'TBP-ZERO','production',0,'WAVECI',NULL);");
 same(200,DepositWallet::percentageBasisPoints('2'));same(250,DepositWallet::percentageBasisPoints('2,5'));same(1,DepositWallet::percentageBasisPoints('0.01'));same(10000,DepositWallet::percentageBasisPoints('100'));same(20,DepositWallet::percentageBasisPoints('0.2'));
 foreach(['1.002','-1','100.01','2e0',[],null] as $bad){try{DepositWallet::percentageBasisPoints($bad);throw new \RuntimeException('Invalid percentage accepted');}catch(\InvalidArgumentException $e){}}
 $default=['WAVECI'=>['fixed'=>0,'basis_points'=>DepositWallet::DEFAULT_FEE_BASIS_POINTS]];
 same(100,DepositWallet::fee(5000,'WAVECI',$default));same(96,DepositWallet::fee(4800,'WAVECI',$default));same(3,DepositWallet::fee(101,'WAVECI',$default));
 // The new migration fills missing channels, preserves explicit custom fees and is replay-safe.
 $migration=file_get_contents(dirname(__DIR__).'/database/migrations/20261003_deposit_wallet_fee_defaults.sql');
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode(['WAVECI'=>['fixed'=>7,'basis_points'=>150]])]);
 $db->exec($migration);$after=json_decode($db->query('SELECT fee_rules FROM deposit_wallet_settings')->fetchColumn(),true);
 same(['fixed'=>7,'basis_points'=>150],$after['WAVECI']);same(['fixed'=>0,'basis_points'=>200],$after['MOMOCI']);same(4,count($after));
 $db->exec($migration);same($after,json_decode($db->query('SELECT fee_rules FROM deposit_wallet_settings')->fetchColumn(),true));same(1,(int)$db->query('SELECT enabled FROM deposit_wallet_settings')->fetchColumn());
 $db->exec("UPDATE deposit_wallet_settings SET fee_rules='{}'");
 $rules=['WAVECI'=>['fixed'=>100,'basis_points'=>100]];
 same(150,DepositWallet::fee(5000,'WAVECI',$rules));same(0,DepositWallet::fee(0,'WAVECI',$rules));same(101,DepositWallet::fee(1,'WAVECI',$rules));
 foreach([[],['WAVECI'=>['fixed'=>-1,'basis_points'=>0]],['WAVECI'=>['fixed'=>0,'basis_points'=>10001]]] as $bad){try{DepositWallet::fee(5000,'WAVECI',$bad);throw new \RuntimeException('Invalid fee accepted');}catch(\LogicException $e){}}
 DepositWallet::verifiedPayment(2);DepositWallet::verifiedPayment(3);same(0,(int)$db->query('SELECT COUNT(*) FROM deposit_wallet_transfers')->fetchColumn());
 DepositWallet::verifiedPayment(1);DepositWallet::verifiedPayment(1);same(1,(int)$db->query('SELECT COUNT(*) FROM deposit_wallet_transfers')->fetchColumn());same('needs_fees',$db->query('SELECT status FROM deposit_wallet_transfers')->fetchColumn());
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode($rules)]);DepositWallet::prepareMissingFees();same(5150,(int)$db->query('SELECT amount FROM deposit_wallet_transfers')->fetchColumn());
 // Unsent requests follow a rate change; a later change must not rewrite sent transfers.
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode($default)]);DepositWallet::prepareMissingFees();same(5100,(int)$db->query('SELECT amount FROM deposit_wallet_transfers WHERE id=1')->fetchColumn());
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode($rules)]);DepositWallet::prepareMissingFees();same(5150,(int)$db->query('SELECT amount FROM deposit_wallet_transfers WHERE id=1')->fetchColumn());
 putenv('XPAYE_LOGIN=private@example.test');putenv('XPAYE_PASSWORD=private-secret');$walletCalls=0;
 $client=new XPayeWalletClient(function($path,$payload,$token)use(&$walletCalls,$db){
  if($path==='/auth/token'){same(['login'=>'private@example.test','password'=>'private-secret'],$payload);return ['http'=>200,'body'=>'{"token":"private-token"}'];}
  same('/wallet/request',$path);same(['montant'=>5150],$payload);same('private-token',$token);same(false,$db->inTransaction());same('unknown',$db->query('SELECT status FROM deposit_wallet_transfers WHERE id=1')->fetchColumn());$walletCalls++;
  return ['http'=>200,'body'=>'{"status":"accepted","reference":"private-token","access_token":"should-not-persist","password":"private-secret"}'];
 });
 DepositWallet::send(1,$client);DepositWallet::send(1,$client);same(1,$walletCalls);same('submitted',$db->query('SELECT status FROM deposit_wallet_transfers')->fetchColumn());
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode($default)]);DepositWallet::prepareMissingFees();same(5150,(int)$db->query('SELECT amount FROM deposit_wallet_transfers WHERE id=1')->fetchColumn());
 $saved=$db->query('SELECT response_summary FROM deposit_wallet_transfers')->fetchColumn();if(str_contains($saved,'private-')||str_contains($saved,'should-not-persist'))throw new \RuntimeException('Secret persisted');
 $r=['payment_environment'=>'production','rental_id'=>1,'refund_amount'=>4800,'payout_channel'=>'WAVECI'];same(false,DepositWallet::funded($r));
 DepositWallet::confirm(1,'XPAYE-CREDIT-001',9);same(true,DepositWallet::funded($r));same(false,DepositWallet::funded(array_replace($r,['payment_environment'=>'sandbox'])));
 $db->prepare('UPDATE deposit_wallet_settings SET fee_rules=?')->execute([json_encode(['WAVECI'=>['fixed'=>200,'basis_points'=>100]])]);same(false,DepositWallet::funded($r));
 $id=DepositWallet::createTest(str_repeat('a',32),100,9);same($id,DepositWallet::createTest(str_repeat('a',32),100,9));
 try{DepositWallet::createTest(str_repeat('a',32),200,9);throw new \RuntimeException('Changed test accepted');}catch(\LogicException $e){}
 $timeoutCalls=0;$timeout=new XPayeWalletClient(function($path)use(&$timeoutCalls){if($path==='/auth/token')return ['http'=>200,'body'=>'{"data":{"access_token":"safe-token"}}'];$timeoutCalls++;throw new \RuntimeException('timeout');});
 try{DepositWallet::send($id,$timeout);}catch(\RuntimeException $e){}DepositWallet::send($id,$timeout);same(1,$timeoutCalls);same('unknown',$db->query('SELECT status FROM deposit_wallet_transfers WHERE id='.$id)->fetchColumn());
 // An unrelated test credit cannot fund a rental.
 DepositWallet::confirm($id,'XPAYE-TEST-ONLY',9);same(false,DepositWallet::funded(array_replace($r,['rental_id'=>100])));
 $rental=['deposit_payment_verified_at'=>'2026-10-03 10:00:00','deposit'=>5000,'billing_rule'=>'prorata_grace5','rental_fee'=>600,'duration_minutes'=>60,'late_percent'=>20,'due_at'=>'2026-10-03 12:00:00','returned_at'=>null];
 $due=strtotime($rental['due_at'].' UTC');same(5000,DepositWallet::remaining($rental,$due+300)['refund']);same(4999,DepositWallet::remaining($rental,$due+301)['refund']);same(0,DepositWallet::remaining($rental,$due+40000)['refund']);
 $rental['returned_at']='2026-10-03 12:06:00';same(4990,DepositWallet::remaining($rental,$due+40000)['refund']);$rental['deposit_payment_verified_at']=null;same(['paid'=>false],DepositWallet::remaining($rental));
 $db->exec("ALTER TABLE rentals ADD COLUMN checkout_token TEXT; ALTER TABLE rentals ADD COLUMN due_at TEXT; ALTER TABLE rentals ADD COLUMN returned_at TEXT; ALTER TABLE rentals ADD COLUMN rental_fee INTEGER; ALTER TABLE rentals ADD COLUMN duration_minutes INTEGER; ALTER TABLE rentals ADD COLUMN billing_rule TEXT; ALTER TABLE rentals ADD COLUMN late_percent INTEGER; ALTER TABLE rentals ADD COLUMN status TEXT;");
 $db->exec("UPDATE rentals SET checkout_token='private-checkout',due_at='2099-10-03 12:00:00',rental_fee=600,duration_minutes=60,billing_rule='prorata_grace5',late_percent=20,status='active' WHERE id=1");
 $controller=new \App\Controllers\RentalController();
 foreach(['','wrong','private-checkout'] as $token){$_GET=['reference'=>'TBP-TEST','token'=>$token];ob_start();$controller->status();$data=json_decode(ob_get_clean(),true);same($token==='private-checkout',isset($data['caution']));if($token==='private-checkout')same(5000,$data['caution']['refund']);}
 echo "Deposit wallet OK: verified payments, fee reserve, durable duplicate/timeout protection, credit gate, secret redaction, frozen return and grace boundaries; no real API calls\n";
}
