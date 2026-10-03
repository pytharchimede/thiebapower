<?php
namespace App\Services;
use App\Core\App;
final class StatisticsDashboard
{
 public static function permissions(array $user):array {
  return ['rentals'=>Auth::can('rentals.view',$user),'finance'=>Auth::can('finance.view',$user),'stations'=>Auth::can('stations.view',$user),'batteries'=>Auth::can('batteries.view',$user),'system'=>Auth::can('system.manage',$user)];
 }
 public function snapshot(int $days,array $permissions):array {
  if(!in_array($days,[1,7,30,90],true))throw new \InvalidArgumentException('Période invalide');
  $key='statistics-'.$days.'-'.implode('',array_map(static fn($v)=>$v?'1':'0',$permissions)).'.json';
  $cached=SystemStorage::read($key);
  if($cached && time()-(int)$cached['at']<5)return $cached;
  $today=new \DateTimeImmutable('today',new \DateTimeZone('UTC'));$start=$today->modify('-'.($days-1).' days')->format('Y-m-d');$from=$start.' 00:00:00';$until=$today->modify('+1 day')->format('Y-m-d').' 00:00:00';
  $data=['at'=>time(),'from'=>$start,'to'=>gmdate('Y-m-d'),'permissions'=>$permissions,'rentals'=>null,'finance'=>null,'fleet'=>null,'system'=>null];$db=App::db();$db->exec("SET time_zone='+00:00'");
  if($permissions['rentals']){
   $q=$db->query("SELECT COUNT(*) active,COALESCE(SUM(due_at<UTC_TIMESTAMP()),0) overdue,COALESCE(SUM(due_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)),0) beyondGrace FROM rentals WHERE status='active'");$totals=$q->fetch();
   $q=$db->prepare('SELECT status,COUNT(*) quantity FROM rentals WHERE created_at>=? AND created_at<? GROUP BY status');$q->execute([$from,$until]);$states=$q->fetchAll();
   $q=$db->prepare("SELECT day,SUM(requests) requests,SUM(starts) starts,SUM(returned) returned FROM (
    SELECT DATE(created_at) day,COUNT(*) requests,0 starts,0 returned FROM rentals WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at)
    UNION ALL SELECT DATE(started_at),0,COUNT(*),0 FROM rentals WHERE started_at>=? AND started_at<? GROUP BY DATE(started_at)
    UNION ALL SELECT DATE(returned_at),0,0,COUNT(*) FROM rentals WHERE returned_at>=? AND returned_at<? GROUP BY DATE(returned_at)
   ) events GROUP BY day ORDER BY day");$q->execute([$from,$until,$from,$until,$from,$until]);$daily=$q->fetchAll();
   $rows=$db->query("SELECT r.reference,r.customer_name,r.status,r.started_at,r.due_at,r.returned_at,r.deposit,r.deposit_payment_verified_at,r.billing_rule,r.rental_fee,r.duration_minutes,r.late_percent,b.serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.status='active' ORDER BY r.due_at LIMIT 100")->fetchAll();
   $q=$db->prepare("SELECT reference,customer_name,returned_at FROM rentals WHERE status='returned' AND returned_at>=? AND returned_at<? ORDER BY returned_at DESC LIMIT 12");$q->execute([$from,$until]);
   foreach($rows as &$row){if($permissions['finance'])$row['caution']=DepositWallet::remaining($row);unset($row['deposit'],$row['deposit_payment_verified_at'],$row['billing_rule'],$row['rental_fee'],$row['duration_minutes'],$row['late_percent']);}unset($row);
   $data['rentals']=['totals'=>$totals,'states'=>$states,'daily'=>$daily,'active'=>$rows,'recentReturns'=>$q->fetchAll(),'limited'=>(int)$totals['active']>100];
  }
  if($permissions['finance']){
   $queue=$db->query("SELECT s.status,COUNT(*) quantity,COALESCE(SUM(s.refund_amount),0) amount FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE r.payment_environment='production' AND s.status<>'refunded' GROUP BY s.status")->fetchAll();
   $q=$db->prepare("SELECT DATE(s.confirmed_at) day,COUNT(*) quantity,SUM(s.refund_amount) amount FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE r.payment_environment='production' AND s.status='refunded' AND s.refund_amount>0 AND s.confirmed_at>=? AND s.confirmed_at<? GROUP BY DATE(s.confirmed_at) ORDER BY day");$q->execute([$from,$until]);$daily=$q->fetchAll();
   $q=$db->prepare("SELECT r.reference,s.status,s.refund_amount,s.confirmed_at FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE r.payment_environment='production' AND (s.status<>'refunded' OR (s.confirmed_at>=? AND s.confirmed_at<?)) ORDER BY (s.status<>'refunded') DESC,s.id DESC LIMIT 12");$q->execute([$from,$until]);
   $data['finance']=['queue'=>$queue,'daily'=>$daily,'recent'=>$q->fetchAll()];
  }
  if($permissions['stations']||$permissions['batteries']){
   $data['fleet']=['batteries'=>$permissions['batteries']?$db->query('SELECT status,COUNT(*) quantity FROM batteries GROUP BY status')->fetchAll():[],'stations'=>$permissions['stations']?$db->query("SELECT imei,label,status,last_seen_at FROM stations ORDER BY imei LIMIT 100")->fetchAll():[]];
  }
  if($permissions['system']){
   $reports=SystemReports::listing();$period=array_values(array_filter($reports,static fn($r)=>strtotime($r['created_at'])>=strtotime($from.' UTC') && strtotime($r['created_at'])<strtotime($until.' UTC')));$daily=[];
   foreach($period as $r){$day=gmdate('Y-m-d',strtotime($r['created_at']));$daily[$day]=($daily[$day]??0)+1;}
   $data['system']=['pending'=>count(array_filter($reports,static fn($r)=>empty($r['acknowledged']))),'daily'=>$daily,'recent'=>array_slice($period,0,12),'retainedOnly'=>true];
  }
  try{SystemStorage::write($key,$data);}catch(\Throwable $e){error_log('Statistics cache unavailable');}
  return $data;
 }
}
