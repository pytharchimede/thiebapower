<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\PublicExperienceSettings;
final class PublicExperienceSettingsController {
 public function index():void {Auth::requirePermission('system.manage');$settings=PublicExperienceSettings::all();$error='';App::view('public_settings',compact('settings','error'));}
 public function save():void {Auth::requirePermission('system.manage',true);try{$settings=PublicExperienceSettings::validate($_POST);PublicExperienceSettings::save($settings);Audit::event('public.settings.updated','settings','public');App::redirect('/admin/public-settings?saved=1');}catch(\InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();$settings=PublicExperienceSettings::all();App::view('public_settings',compact('settings','error'));}}
}
