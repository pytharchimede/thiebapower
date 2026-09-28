<?php
namespace App\Controllers;

use App\Core\App;
use App\Services\Auth;

final class AuditController
{
    public function index(): void
    {
        Auth::requirePermission('audit.view');
        $db = App::db();
        $actions = $db->query('SELECT a.*,u.username FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 150')->fetchAll();
        $visits = $db->query('SELECT r.*,u.username FROM request_events r LEFT JOIN users u ON u.id=r.actor_id ORDER BY r.id DESC LIMIT 150')->fetchAll();
        $attempts = $db->query('SELECT username,ip_address,successful,occurred_at FROM auth_attempts ORDER BY id DESC LIMIT 50')->fetchAll();
        App::view('audit', compact('actions', 'visits', 'attempts'));
    }
}
