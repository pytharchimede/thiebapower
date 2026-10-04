<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\{Auth,Audit,OrangeSmsSettings,OrangeSmsClient};
final class OrangeSmsController
{
    public function index(): void
    {
        Auth::requirePermission('sms.view');
        header('Cache-Control: no-store');
        $settings=OrangeSmsSettings::all(); $error=''; $history=[]; $installed=true;
        try { $history=App::db()->query('SELECT * FROM orange_sms_logs ORDER BY id DESC LIMIT 50')->fetchAll(); }
        catch (\PDOException $e) { if ($e->getCode()!=='42S02') throw $e; $installed=false; }
        $result=$_SESSION['orange_sms_result']??null; unset($_SESSION['orange_sms_result']);
        $secretConfigured=$settings['secret_cipher']!=='' || App::env('ORANGE_SMS_CLIENT_SECRET')!=='';
        unset($settings['secret_cipher']);
        App::view('orange_sms',compact('settings','error','history','installed','result','secretConfigured'));
    }
    public function save(): void
    {
        Auth::requirePermission('sms.manage',true);
        try {
            OrangeSmsSettings::save(OrangeSmsSettings::validate($_POST,OrangeSmsSettings::all()));
            Audit::event('sms.settings.updated','integration','orange');
            $_SESSION['orange_sms_result']=['notice'=>'Paramètres enregistrés.'];
        } catch (\InvalidArgumentException $e) { $_SESSION['orange_sms_result']=['error'=>$e->getMessage()]; }
        App::redirect('/admin/sms#sms-result');
    }
    public function test(): void
    {
        Auth::requirePermission('sms.test',true);
        $op=(string)($_POST['operation']??'');
        if (!in_array($op,['auth','balance','usage','purchases','send'],true)) { http_response_code(422); echo 'Opération invalide'; return; }
        $id=null;
        try {
            $s=OrangeSmsSettings::all(); $phone=null;
            if ($op==='send') {
                $phone=OrangeSmsClient::phone((string)($_POST['recipient']??''));
                OrangeSmsClient::payload($s,$phone,(string)($_POST['message']??''));
                if (!$s['enabled']) throw new \InvalidArgumentException('Activez l’intégration avant le test d’envoi.');
                if ($s['mode']==='production' && !isset($_POST['confirm_real'])) throw new \InvalidArgumentException('Confirmez l’envoi réel facturé avant le test.');
                $nonce=(string)($_POST['nonce']??'');
                if ($nonce==='' || !hash_equals((string)($_SESSION['orange_sms_nonce']??''),$nonce)) throw new \InvalidArgumentException('Test déjà soumis ou formulaire expiré. Rechargez la page.');
                unset($_SESSION['orange_sms_nonce']);
            }
            // Lock the shared settings row while reserving a test slot, not during network I/O.
            $db=App::db(); $db->beginTransaction();
            try {
                $db->query('SELECT id FROM orange_sms_settings WHERE id=1 FOR UPDATE')->fetch();
                $remaining=(int)$db->query("SELECT COALESCE(MAX(30-TIMESTAMPDIFF(SECOND,created_at,CURRENT_TIMESTAMP)),0) FROM orange_sms_logs WHERE created_at>DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 30 SECOND)")->fetchColumn();
                if ($remaining>0) throw new \InvalidArgumentException('Attendez encore '.$remaining.' seconde(s) avant une nouvelle opération Orange.');
                $db->prepare('INSERT INTO orange_sms_logs(user_id,operation,mode,recipient_masked) VALUES(?,?,?,?)')->execute([Auth::id(),$op,$s['mode'],$phone===null?null:'+225******'.substr($phone,-4)]);
                $id=(int)$db->lastInsertId(); $db->commit();
            } catch (\Throwable $e) { $db->rollBack(); throw $e; }
            $client=new OrangeSmsClient($s);
            $result=match($op) { 'auth'=>$client->authenticate(), 'send'=>$client->send($phone,(string)$_POST['message']), default=>$client->inspect($op) };
            $db->prepare('UPDATE orange_sms_logs SET state=?,http_status=?,resource_id=? WHERE id=?')->execute([$result['state'],$result['http_status'],$result['resource_id']??null,$id]);
            Audit::event('sms.'.$op,'orange_sms',(string)$id,['state'=>$result['state'],'http_status'=>$result['http_status']]);
            $_SESSION['orange_sms_result']=$result+['operation'=>$op];
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($id!==null) App::db()->prepare('UPDATE orange_sms_logs SET state=? WHERE id=?')->execute([$op==='send'?'unknown':'failed',$id]);
            $_SESSION['orange_sms_result']=['error'=>$e instanceof \PDOException?'Stockage SMS indisponible. Appliquez la migration et vérifiez la base de données.':$e->getMessage()];
        }
        $this->respond();
    }
    private function respond(): void
    {
        if (str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            $result=$_SESSION['orange_sms_result']??['error'=>'Aucun résultat disponible.'];
            unset($_SESSION['orange_sms_result']);
            $_SESSION['orange_sms_nonce']=bin2hex(random_bytes(24));
            echo json_encode($result+['next_nonce'=>$_SESSION['orange_sms_nonce']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            return;
        }
        App::redirect('/admin/sms#sms-result');
    }
}
