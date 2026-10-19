<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\AdminNavigation;

final class ModuleController
{
    public function index(): void
    {
        Auth::start();
        $key=basename((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH));
        $all=AdminNavigation::groups();
        if(!isset($all[$key])){http_response_code(404);return;}
        $groups=AdminNavigation::visible();
        if(!isset($groups[$key])){
            Auth::requirePermission($all[$key]['items'][0][2]);
            http_response_code(403);return;
        }
        Auth::requirePermission($groups[$key]['items'][0][2]);
        header('Cache-Control: no-store');
        $module=$groups[$key];$metrics=[];$db=App::db();
        if($key==='locations'&&Auth::can('rentals.view')){
            $q=$db->query("SELECT COUNT(*) total,COALESCE(SUM(status='active'),0) active,COALESCE(SUM(status='pending_payment'),0) pending FROM rentals")->fetch();
            $metrics=[['Locations en cours',(int)$q['active'],'/admin/rentals'],['Paiements en attente',(int)$q['pending'],'/admin/rentals/watch'],['Locations enregistrées',(int)$q['total'],'/admin/rentals']];
        }
        if($key==='equipements'){
            if(Auth::can('stations.view'))$metrics[]=['Stations enregistrées',(int)$db->query('SELECT COUNT(*) FROM stations')->fetchColumn(),'/admin/stations'];
            if(Auth::can('batteries.view')){
                $q=$db->query("SELECT COUNT(*) total,COALESCE(SUM(status='available'),0) available,COALESCE(SUM(status='maintenance'),0) maintenance FROM batteries")->fetch();
                $metrics[]=['Batteries disponibles',(int)$q['available'],'/admin/batteries'];
                $metrics[]=['Batteries en maintenance',(int)$q['maintenance'],'/admin/batteries'];
                $metrics[]=['Batteries enregistrées',(int)$q['total'],'/admin/batteries'];
            }
        }
        if($key==='offres'&&Auth::can('pricing.view')){
            $q=$db->query('SELECT rental_fee,duration_minutes FROM pricing WHERE id=1')->fetch();
            if($q)$metrics=[['Tarif par défaut',number_format((int)$q['rental_fee'],0,',',' ').' FCFA','/admin/pricing'],['Durée incluse',(int)$q['duration_minutes'].' min','/admin/pricing']];
        }
        if($key==='communication'&&Auth::can('sms.view')){
            if(Auth::can('sms.manage'))$metrics[]=['Modèles de SMS actifs',(int)$db->query('SELECT COUNT(*) FROM sms_templates WHERE enabled=1')->fetchColumn(),'/admin/sms/templates'];
            $metrics[]=['SMS dans la file d’attente',(int)$db->query("SELECT COUNT(*) FROM sms_outbox WHERE state='queued'")->fetchColumn(),'/admin/sms'];
        }
        if(!$metrics)$metrics[]=['Rubriques accessibles',count($module['items']),$module['items'][0][1]];
        App::view('module',compact('module','metrics','key'));
    }
}
