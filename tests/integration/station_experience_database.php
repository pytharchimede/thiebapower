<?php
if(getenv('TEST_EXPERIENCE_DATABASE')!=='1'){echo "Experience DB integration skipped (set TEST_EXPERIENCE_DATABASE=1 on an empty test database).\n";exit(0);}
$dsn=getenv('DB_DSN')?:'';
if(!preg_match('/dbname=thiebapower_test_[a-z0-9_]+(?:;|$)/D',$dsn))throw new RuntimeException('Use a dedicated thiebapower_test_* database.');
$db=new PDO($dsn,getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(!preg_match('/^thiebapower_test_[a-z0-9_]+$/D',(string)$db->query('SELECT DATABASE()')->fetchColumn()))throw new RuntimeException('Selected database is not a dedicated test database.');
if((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('Test database must be empty; no existing table is deleted.');
$root=dirname(__DIR__,2);$db->exec(file_get_contents($root.'/database/schema.sql'));
$files=glob($root.'/database/migrations/*.sql');
$order=['20260928_automatic_deposit_refunds.sql','20260928_v1_accounts_audit.sql','20260928_rental_checkout_lifecycle.sql','20260929_heycharge_terminals.sql','20260929_charging_batteries.sql','20260929_manual_battery_release.sql','20260929_manual_battery_reinsertion.sql','20260929_finance_cash.sql','20260929_rental_operations.sql','20260929_payment_reservation_timeout.sql','20260929_station_label_settings.sql','20260929_station_label_logo_settings.sql','20261003_checkout_notifications.sql','20261003_finance_billing.sql','20261003_refund_payment_channel.sql','20261003_deposit_wallet.sql','20261003_deposit_wallet_fee_defaults.sql','20261004_station_experience.sql','20261004_public_promotions.sql','20261004_public_support.sql','20261004_training_roadmap.sql','20261005_configurable_grace.sql','20261005_deposit_promotions.sql','20261005_promotion_archive.sql','20261005_promotion_retry_limits.sql','20261005_station_pricing.sql'];
foreach($order as $file){try{$db->exec(file_get_contents($root.'/database/migrations/'.$file));}catch(Throwable $e){throw new RuntimeException($file.': '.$e->getMessage());}}
$db->exec(file_get_contents($root.'/database/migrations/20261004_station_experience.sql')); // idempotence
$db->exec(file_get_contents($root.'/database/migrations/20261004_public_promotions.sql'));
$db->exec("INSERT INTO stations(imei,label,status,enabled,last_seen_at) VALUES('TEST01','Café test','online',1,UTC_TIMESTAMP()),('DISABLED','Privée','online',0,UTC_TIMESTAMP())");
$db->exec("INSERT INTO batteries(id,serial,status,station_imei,slot_id,battery_capacity) VALUES(1,'BANK01','available','TEST01','1',100),(2,'BANK02','available','TEST01','2',95),(3,'BANK03','rented','TEST01','3',90)");
$db->exec("INSERT INTO station_profiles(station_imei,address,latitude,longitude,manager_name,manager_phone,investment) VALUES('TEST01','Koumassi',5.3,-4.0,'SECRET MANAGER','0700000000',100000)");
$hash=password_hash('local-test-password',PASSWORD_DEFAULT);$q=$db->prepare("INSERT INTO users(username,display_name,password_hash,role) VALUES('owner','Test Owner',?,'owner'),('auditor','Auditeur',?,'auditor')");$q->execute([$hash,$hash]);
$db->exec("DELETE FROM role_permissions WHERE role='auditor'");$db->exec("INSERT IGNORE INTO role_permissions(role,permission) VALUES('auditor','stations.view'),('auditor','rentals.view'),('auditor','dashboard.view')");
foreach(['returned','release_failed','active'] as $i=>$state){$token=str_pad((string)($i+1),32,'a');$ref='TBP-'.str_pad((string)($i+1),16,'0');$q=$db->prepare("INSERT INTO rentals(reference,battery_id,customer_name,customer_email,customer_phone,rental_fee,deposit,late_percent,duration_minutes,status,station_code,payment_environment,checkout_token,started_at,due_at,returned_at,billing_rule) VALUES(?,?,'Client Test','','0700000001',1000,5000,10,60,?,'TEST01','production',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?,'prorata_grace5')");$q->execute([$ref,$i+1,$state,$token,$state==='returned'?gmdate('Y-m-d H:i:s',time()-1800):null]);$id=$db->lastInsertId();if($state==='release_failed')$db->exec("UPDATE rentals SET started_at=NULL,due_at=NULL WHERE id=$id");$db->prepare("INSERT INTO payment_notifications(rental_id,payload,received_at) VALUES(?,'{\"responsecode\":\"0\"}',UTC_TIMESTAMP()),(?,'{\"responsecode\":\"0\"}',UTC_TIMESTAMP())")->execute([$id,$id]);if($state==='returned')$db->exec("INSERT INTO deposit_settlements(rental_id,deduction,refund_amount,status,confirmed_at) VALUES($id,500,4500,'refunded',UTC_TIMESTAMP())");}
$db->exec("INSERT INTO station_costs(request_token,station_imei,category,amount,description,occurred_on,created_by) VALUES('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','TEST01','maintenance',100,'Test',CURRENT_DATE(),1)");
$db->exec("INSERT INTO promotions(code,kind,discount_amount,max_uses,starts_at,ends_at,enabled) VALUES('TEST200','campaign',200,2,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),1)");
echo "Fresh and repeated migrations OK\n";

spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__,2).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\StationProfitability;use App\Services\PromotionService;use App\Core\App;
$assert=function($condition,$label){if(!$condition)throw new RuntimeException($label);};
$db=App::db();$report=(new StationProfitability)->report();$row=array_values(array_filter($report,fn($r)=>$r['imei']==='TEST01'))[0];$assert((int)$row['revenue']===2000,'Payment retry deduplication and no-release exclusion');$assert((int)$row['deductions']===500&&$row['net']===2400,'Deposit excluded; cost and deductions included');
$offer=(new PromotionService)->assess('TEST200','0700000001',1000);$assert($offer['deposit']===800,'Promo preview');
$db->beginTransaction();$offer=(new PromotionService)->assess('TEST200','0700000001',1000,'',true);(new PromotionService)->record(1,$offer,'0700000001');$db->commit();
$reject=false;try{(new PromotionService)->assess('TEST200','+2250700000001',1000);}catch(InvalidArgumentException){$reject=true;}$assert($reject,'Same phone cannot reuse code');
$before=$db->query('SELECT id,status,started_at,returned_at,rental_fee,deposit FROM rentals ORDER BY id')->fetchAll();\App\Services\SystemNotifications::refresh();$after=$db->query('SELECT id,status,started_at,returned_at,rental_fee,deposit FROM rentals ORDER BY id')->fetchAll();$assert($before===$after,'Alerts do not mutate rentals');
echo "MariaDB integration: profitability, promotion locks, phone reuse, alert refresh OK\n";

$db->exec("UPDATE pricing SET deposit_enabled=1 WHERE id=1");
$db->exec("UPDATE promotions SET is_public=1 WHERE code='TEST200'");
$db->exec("INSERT INTO promotions(code,kind,discount_amount,max_uses,starts_at,ends_at,enabled,is_public) VALUES('PRIVATE200','campaign',200,20,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),1,0),('LOYAL200','loyalty',200,20,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),1,1),('EXPIRED200','campaign',200,20,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),1,1)");
ob_start();(new \App\Controllers\PromotionController)->offers();$html=ob_get_clean();
$assert(str_contains($html,'TEST200')&&!str_contains($html,'PRIVATE200')&&!str_contains($html,'LOYAL200')&&!str_contains($html,'EXPIRED200'),'Public promotion visibility and validity filter');
$assert(str_contains($html,'wa.me')&&str_contains($html,'promo-copy'),'Public share actions');
echo "Public promotions: additive repeated migration, private/loyalty/expired exclusion and share actions OK\n";

$db->exec(file_get_contents($root.'/database/migrations/20261004_public_support.sql'));
\App\Services\PublicExperienceSettings::save(\App\Services\PublicExperienceSettings::defaults());
$assert((int)$db->query("SELECT COUNT(*) FROM public_experience_settings WHERE id=1")->fetchColumn()===1,"Support settings save and repeated migration");

$db->exec(file_get_contents($root.'/database/migrations/20261005_configurable_grace.sql'));
$db->exec("UPDATE pricing SET grace_minutes=10 WHERE id=1");
$assert((int)$db->query("SELECT grace_minutes FROM rentals LIMIT 1")->fetchColumn()===5,"Existing rental grace is frozen");
$db->exec("UPDATE pricing SET grace_minutes=5 WHERE id=1");
echo "Grace migration: idempotent and existing rentals retain five minutes OK\n";

// Abandoned attempts keep their history but release coupon quota without a cron.
$db->exec(file_get_contents($root.'/database/migrations/20261005_promotion_retry_limits.sql'));
$db->exec(file_get_contents($root.'/database/migrations/20261005_promotion_retry_limits.sql'));
$db->exec("INSERT INTO promotions(code,kind,discount_amount,max_uses,max_uses_per_phone,starts_at,ends_at,enabled,is_public) VALUES('RETRY200','campaign',200,2,1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),1,1)");
$retryId=(int)$db->lastInsertId();$service=new PromotionService;
$newAttempt=function(string $status,string $expires)use($db):int{
 $q=$db->prepare("INSERT INTO rentals(reference,battery_id,customer_name,customer_email,customer_phone,rental_fee,deposit,late_percent,duration_minutes,status,station_code,payment_environment,checkout_token,reservation_expires_at) SELECT ?,battery_id,customer_name,customer_email,customer_phone,rental_fee,deposit,late_percent,duration_minutes,?,station_code,payment_environment,?,? FROM rentals WHERE id=1");
 $q->execute(['TBP-'.strtoupper(bin2hex(random_bytes(8))),$status,bin2hex(random_bytes(16)),$expires]);return (int)$db->lastInsertId();
};
$future=gmdate('Y-m-d H:i:s',time()+120);$past=gmdate('Y-m-d H:i:s',time()-1);$phone='0700000099';
$offer=$service->assess('RETRY200',$phone,1000);$attempt=$newAttempt('pending_payment',$future);$service->record($attempt,$offer,$phone);
$rejected=false;try{$service->assess('RETRY200',$phone,1000);}catch(InvalidArgumentException $e){$rejected=str_contains($e->getMessage(),'Ce numéro');}$assert($rejected,'Pending reservation holds phone quota for two minutes');
$db->prepare('UPDATE rentals SET reservation_expires_at=? WHERE id=?')->execute([$past,$attempt]);
$offer=$service->assess('RETRY200','+2250700000099',1000);$assert($offer['deposit']===800,'Expired unpaid attempt releases equivalent phone without cron');
$attempt2=$newAttempt('payment_failed',$future);$service->record($attempt2,$offer,$phone);
$rejected=false;try{$service->assess('RETRY200',$phone,1000);}catch(InvalidArgumentException){$rejected=true;}$assert($rejected,'Failed payment keeps reservation until its deadline');
$db->prepare('UPDATE rentals SET reservation_expires_at=? WHERE id=?')->execute([$past,$attempt2]);$offer=$service->assess('RETRY200',$phone,1000);
$attempt3=$newAttempt('returned',$past);$service->record($attempt3,$offer,$phone);
$db->prepare("INSERT INTO payment_notifications(rental_id,payload,received_at) VALUES(?,'{\"responsecode\":\"0\"}',UTC_TIMESTAMP()),(?,'{\"responsecode\":\"0\"}',UTC_TIMESTAMP())")->execute([$attempt3,$attempt3]);
$rejected=false;try{$service->assess('RETRY200',$phone,1000);}catch(InvalidArgumentException $e){$rejected=str_contains($e->getMessage(),'Ce numéro');}$assert($rejected,'Paid attempt consumes phone quota once despite duplicate notifications');
$db->prepare('UPDATE promotions SET max_uses_per_phone=2 WHERE id=?')->execute([$retryId]);
$offer=$service->assess('RETRY200',$phone,1000);$attempt4=$newAttempt('pending_payment',$future);$service->record($attempt4,$offer,$phone);
$rejected=false;try{$service->assess('RETRY200','0700000088',1000);}catch(InvalidArgumentException $e){$rejected=str_contains($e->getMessage(),'Réessayez dans');}$assert($rejected,'Global quota counts payments plus live reservations');
$db->prepare("UPDATE rentals SET status='payment_timeout',reservation_expires_at=? WHERE id=?")->execute([$past,$attempt4]);
$assert($service->assess('RETRY200',$phone,1000)['deposit']===800,'Per-phone limit two permits another attempt after timeout');
$assert((int)$db->query("SELECT COUNT(*) FROM rental_promotion_redemptions WHERE promotion_id=$retryId")->fetchColumn()===4,'Expired attempts retained in audit history');
ob_start();(new \App\Controllers\PromotionController)->offers();$html=ob_get_clean();$assert(str_contains($html,'RETRY200'),'Public offer is available again after unpaid expiry');
echo "Promotion retry: repeatable migration, expiry without cron, failed payment window, duplicate callback, adjustable phone limit, global quota and retained history OK\n";

// Global/default/selected scopes and existing rental snapshots are independent.
use App\Services\StationPricing;
$db->exec(file_get_contents($root.'/database/migrations/20261005_station_pricing.sql'));
$db->exec(file_get_contents($root.'/database/migrations/20261005_station_pricing.sql'));
$global=StationPricing::resolve('TEST01');$assert(!$global['is_station_price'],'Existing station inherits general price after migration');
$snapshots=$db->query('SELECT id,rental_fee,deposit,duration_minutes,grace_minutes FROM rentals ORDER BY id')->fetchAll();
$custom=StationPricing::validate(['rental_fee'=>250,'default_deposit'=>1200,'duration_minutes'=>30,'late_percent'=>10,'deposit_enabled'=>1,'grace_minutes'=>2]);
StationPricing::apply($custom,'selected',['TEST01']);$assert((int)StationPricing::resolve('TEST01')['rental_fee']===250,'Selected station receives override');$assert((int)StationPricing::resolve('DISABLED')['rental_fee']===(int)$global['rental_fee'],'Other station retains general price');
$general=$custom;$general['rental_fee']=400;StationPricing::apply($general,'default',[]);$assert((int)StationPricing::resolve('TEST01')['rental_fee']===250&&(int)StationPricing::resolve('DISABLED')['rental_fee']===400,'Changing general price preserves overrides');
$reject=false;try{StationPricing::apply($custom,'selected',['TEST01','UNKNOWN']);}catch(InvalidArgumentException){$reject=true;}$assert($reject&&(int)StationPricing::resolve('TEST01')['rental_fee']===250,'Unknown selection rolls back entire update');
StationPricing::apply($custom,'selected',['TEST01','DISABLED']);$assert((int)StationPricing::resolve('DISABLED')['rental_fee']===250,'Selection applies to several stations');
StationPricing::apply([],'inherit',['TEST01']);$assert(!StationPricing::resolve('TEST01')['is_station_price']&&(int)StationPricing::resolve('TEST01')['rental_fee']===400,'Restore inheritance follows general price');
StationPricing::apply($general,'all',[]);$assert((int)$db->query('SELECT COUNT(*) FROM station_pricing')->fetchColumn()===0,'All-stations scope removes prior overrides');
$assert($snapshots===$db->query('SELECT id,rental_fee,deposit,duration_minutes,grace_minutes FROM rentals ORDER BY id')->fetchAll(),'Changing prices never changes existing rental snapshots');
echo "Station pricing database: repeated migration, global inheritance, selected stations, default preservation, atomic invalid selection, all stations and frozen rentals OK\n";
