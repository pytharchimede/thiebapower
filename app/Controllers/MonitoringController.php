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
            $enabled = \App\Services\SystemStorage::countersEnabled();
            $rentals = [];
            $tracked = [];
            $snapshot = ["at" => time(), "rows" => []];
            if ($canRentals && $enabled && ($_GET["counters"] ?? "1") !== "0") {
                $references = array_values(
                    array_unique(
                        array_slice(
                            array_filter(
                                explode(
                                    ",",
                                    (string) ($_GET["references"] ?? ""),
                                ),
                                fn($ref) => preg_match(
                                    '/^TBP-[A-Za-z0-9_-]{1,100}$/D',
                                    $ref,
                                ),
                            ),
                            0,
                            100,
                        ),
                    ),
                );
                $snapshot = \App\Services\SystemStorage::rentalSnapshot(
                    $references,
                    function () use ($references) {
                        $sql =
                            "SELECT r.reference,r.status,r.customer_name,r.started_at,r.due_at,r.returned_at,b.serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.status='active'";
                        if ($references) {
                            $sql .=
                                " OR r.reference IN (" .
                                implode(
                                    ",",
                                    array_fill(0, count($references), "?"),
                                ) .
                                ")";
                        }
                        $sql .=
                            " ORDER BY (r.status='active') DESC,r.started_at DESC LIMIT 500";
                        $q = App::db()->prepare($sql);
                        $q->execute($references);
                        return $q->fetchAll();
                    },
                );
                foreach ($snapshot["rows"] as $row) {
                    foreach (["started", "due", "returned"] as $field) {
                        $row[$field . "_unix"] = !empty($row[$field . "_at"])
                            ? strtotime($row[$field . "_at"] . " UTC")
                            : null;
                    }
                    if ($row["status"] === "active") {
                        $rentals[] = $row;
                    }
                    if (in_array($row["reference"], $references, true)) {
                        $tracked[] = $row;
                    }
                }
            }
            if (Auth::can("system.manage", $user)) {
                $incidents = \App\Services\SystemReports::pendingFeed();
                $feed["items"] = array_merge(
                    $incidents["items"],
                    $feed["items"],
                );
                $feed["unread"] += $incidents["unread"];
            }
            echo json_encode(
                [
                    "serverTime" => time(),
                    "countersEnabled" => $enabled,
                    "snapshotAt" => $snapshot["at"],
                    "snapshotUnavailable" => !empty($snapshot["unavailable"]),
                    "limited" => count($snapshot["rows"]) >= 500,
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
        if (str_starts_with((string) ($_POST["id"] ?? ""), "incident-")) {
            if (!Auth::can("system.manage", $user)) {
                http_response_code(403);
                return;
            }
            \App\Services\SystemReports::acknowledge(substr($_POST["id"], 9));
            header("Content-Type: application/json");
            echo '{"ok":true}';
            return;
        }
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
