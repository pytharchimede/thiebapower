<?php
namespace App\Core {final class App {public static $db;public static function db(){return self::$db;}}}
namespace App\Services {
 final class SystemStorage {public static array $cache=[];public static function read($key){return self::$cache[$key]??null;}public static function write($key,$data){self::$cache[$key]=$data;}}
 final class SystemReports {public static int $reads=0;public static function listing(){self::$reads++;return [['created_at'=>gmdate('c'),'acknowledged'=>false,'stage'=>'API','message'=>'test','id'=>'abc']];}}
}
namespace {
 require dirname(__DIR__).'/app/Services/StatisticsDashboard.php';
 final class Query {public function __construct(private string $sql){}public function execute($params=[]):void{}public function fetch():array{return ['active'=>2,'overdue'=>1,'beyondGrace'=>1];}public function fetchAll():array{return [];}}
 final class DB {public array $sql=[];public function exec($sql):void{}public function query($sql){$this->sql[]=$sql;return new Query($sql);}public function prepare($sql){$this->sql[]=$sql;return new Query($sql);}}
 $db=new DB();\App\Core\App::$db=$db;$service=new \App\Services\StatisticsDashboard();
 $none=['rentals'=>false,'finance'=>false,'stations'=>false,'batteries'=>false,'system'=>false];
 $d=$service->snapshot(7,$none);
 if($db->sql||\App\Services\SystemReports::$reads||$d['rentals']!==null||$d['finance']!==null)throw new \RuntimeException('Restricted data accessed');
 $d=$service->snapshot(7,array_replace($none,['rentals'=>true]));
 if($d['rentals']['totals']['active']!==2||$d['finance']!==null||\App\Services\SystemReports::$reads)throw new \RuntimeException('Rental access leaked finance/system');
 $queries=count($db->sql);$service->snapshot(7,array_replace($none,['rentals'=>true]));
 if(count($db->sql)!==$queries)throw new \RuntimeException('Shared cache ignored');
 $db->sql=[];$d=$service->snapshot(7,array_replace($none,['batteries'=>true]));
 if(count($db->sql)!==1||str_contains($db->sql[0],'stations')||$d['fleet']['stations']!==[])throw new \RuntimeException('Battery-only permission leaked stations');
 $d=$service->snapshot(1,array_replace($none,['system'=>true]));
 if($d['system']['pending']!==1||$d['finance']!==null||$d['rentals']!==null)throw new \RuntimeException('Incident data');
 try{$service->snapshot(999,$none);throw new \RuntimeException('Invalid period accepted');}catch(\InvalidArgumentException $e){}
 echo "Statistics permissions, period validation and cache OK\n";
}
