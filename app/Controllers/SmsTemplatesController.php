<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\{Auth,Audit,SmsTemplates};
final class SmsTemplatesController
{
 public function index(): void {Auth::requirePermission('sms.manage');header('Cache-Control: no-store');$templates=SmsTemplates::all();$message=$_SESSION['sms_template_message']??'';unset($_SESSION['sms_template_message']);App::view('sms_templates',compact('templates','message'));}
 public function save(): void {Auth::requirePermission('sms.manage',true);try{SmsTemplates::save((string)($_POST['key']??''),trim((string)($_POST['content']??'')),isset($_POST['enabled']));Audit::event('sms.template.updated','sms_template',(string)$_POST['key']);$_SESSION['sms_template_message']='Modele enregistre.';}catch(\InvalidArgumentException $e){$_SESSION['sms_template_message']=$e->getMessage();}App::redirect('/admin/sms/templates');}
}
