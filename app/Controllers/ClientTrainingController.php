<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\ClientTraining;
final class ClientTrainingController {
 public function index():void {Auth::requirePermission('dashboard.view');$guide=ClientTraining::guide();$proposals=App::db()->query('SELECT * FROM development_proposals ORDER BY updated_at DESC,id DESC LIMIT 200')->fetchAll();$error='';App::view('client_training',compact('guide','proposals','error'));}
 public function save():void {Auth::requirePermission('system.manage',true);try{$data=ClientTraining::validate($_POST);}catch(\InvalidArgumentException $e){http_response_code(422);$guide=ClientTraining::guide();$proposals=App::db()->query('SELECT * FROM development_proposals ORDER BY updated_at DESC,id DESC LIMIT 200')->fetchAll();$error=$e->getMessage();App::view('client_training',compact('guide','proposals','error'));return;}
  $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0){http_response_code(422);echo 'Proposition invalide';return;}$values=array_values($data);$db=App::db();if($id){$q=$db->prepare('SELECT id FROM development_proposals WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn()){http_response_code(404);echo 'Proposition introuvable';return;}$db->prepare('UPDATE development_proposals SET title=?,description=?,benefit=?,scope=?,timeframe=?,status=?,priority=?,budget=? WHERE id=?')->execute([...$values,$id]);}else{$db->prepare('INSERT INTO development_proposals(title,description,benefit,scope,timeframe,status,priority,budget,created_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([...$values,Auth::id()]);$id=(int)$db->lastInsertId();}Audit::event('proposal.saved','proposal',(string)$id);App::redirect('/admin/training?saved=1#evolutions');
 }
 public function export():void {Auth::requirePermission('dashboard.view');$kind=(string)($_GET['kind']??'guide');if(!in_array($kind,['guide','proposals'],true)){http_response_code(422);echo 'Export invalide';return;}
  if($kind==='guide')$bytes=ClientTraining::guidePdf();else{$id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0){http_response_code(422);echo 'Proposition invalide';return;}$q=App::db()->prepare('SELECT * FROM development_proposals'.($id?' WHERE id=?':'').' ORDER BY updated_at DESC,id DESC LIMIT 200');$q->execute($id?[$id]:[]);$rows=$q->fetchAll();if($id&&!$rows){http_response_code(404);echo 'Proposition introuvable';return;}$bytes=ClientTraining::proposalsPdf($rows);}
  Audit::event('training.exported','training',$kind);header('Content-Type: application/pdf');header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="thiebapower-'.$kind.'-'.gmdate('Y-m-d').'.pdf"');echo $bytes;
 }
}
