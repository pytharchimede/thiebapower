<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\SystemNotifications;
final class MonitoringController
{
    public function snapshot(): void
    {
        $user = Auth::requirePermission("dashboard.view");
        $canRentals = Auth::can("rentals.manage", $user);
        session_write_close();
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store");
        try {
            SystemNotifications::refresh();
            $feed = SystemNotifications::feed($user);
            $rentals = [];
            $tracked = [];
            if ($canRentals) {
                $rentals = App::db()
                    ->query(
                        "SELECT r.reference,r.customer_name,r.started_at,r.due_at,b.serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.status='active' ORDER BY r.started_at",
                    )
                    ->fetchAll();
                foreach ($rentals as &$r) {
                    $r["started_unix"] = $r["started_at"]
                        ? strtotime($r["started_at"] . " UTC")
                        : null;
                    $r["due_unix"] = $r["due_at"]
                        ? strtotime($r["due_at"] . " UTC")
                        : null;
                }
                unset($r);
                $references = array_slice(
                    array_filter(
                        explode(",", (string) ($_GET["references"] ?? "")),
                        fn($ref) => preg_match(
                            '/^TBP-[A-Za-z0-9_-]{1,100}$/D',
                            $ref,
                        ),
                    ),
                    0,
                    100,
                );
                if ($references) {
                    $q = App::db()->prepare(
                        "SELECT reference,status,started_at,due_at,returned_at FROM rentals WHERE reference IN (" .
                            implode(
                                ",",
                                array_fill(0, count($references), "?"),
                            ) .
                            ")",
                    );
                    $q->execute($references);
                    $tracked = $q->fetchAll();
                    foreach ($tracked as &$row) {
                        foreach (["started", "due", "returned"] as $field) {
                            $row[$field . "_unix"] = !empty(
                                $row[$field . "_at"]
                            )
                                ? strtotime($row[$field . "_at"] . " UTC")
                                : null;
                        }
                    }
                    unset($row);
                }
            }
            echo json_encode(
                [
                    "serverTime" => time(),
                    "notifications" => $feed,
                    "activeRentals" => $rentals,
                    "trackedRentals" => $tracked,
                ],
                JSON_THROW_ON_ERROR,
            );
        } catch (\Throwable $e) {
            error_log("TBP monitoring: " . $e->getMessage());
            http_response_code(503);
            echo json_encode([
                "error" =>
                    "Suivi temporairement indisponible. Vérifiez que la migration a été appliquée.",
            ]);
        }
    }
    public function markRead(): void
    {
        $user = Auth::requirePermission("dashboard.view", true);
        $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(422);
            return;
        }
        $q = App::db()->prepare(
            "SELECT permission FROM system_notifications WHERE id=?",
        );
        $q->execute([$id]);
        $permission = $q->fetchColumn();
        if (!$permission || !Auth::can($permission, $user)) {
            http_response_code(403);
            return;
        }
        App::db()
            ->prepare(
                "INSERT IGNORE INTO system_notification_reads(notification_id,user_id) VALUES(?,?)",
            )
            ->execute([$id, $user["id"]]);
        header("Content-Type: application/json");
        echo '{"ok":true}';
    }
}
