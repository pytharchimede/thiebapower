<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void {
 if(!str_starts_with($class,'App\\'))return;
 $file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
 if(is_file($file))require $file;
});
use App\Core\App;
use App\Services\AutomaticDepositRefundService;
if(App::env('AUTOMATIC_REFUNDS_ENABLED')!=='1'){fwrite(STDERR,"Reversements automatiques désactivés\n");exit(0);}
$db=App::db();$service=new AutomaticDepositRefundService();
$rows=$db->query("SELECT id,status FROM deposit_settlements WHERE status IN ('pending','processing') ORDER BY id LIMIT 25")->fetchAll();
foreach($rows as $row){
 try {
  if($row['status']==='pending')$service->dispatch((int)$row['id']);
  else $service->reconcile((int)$row['id']);
 }catch(Throwable $e){error_log('Refund worker settlement '.$row['id'].': '.$e->getMessage());}
}
