<?php
namespace App\Services;
use App\Core\App;
/** Durable event store. Future delivery channels can consume the same event keys. */
final class SystemNotifications
{
    public static function rentalAlert(array $r, int $now): ?array
    {
        if (
            $r["status"] === "active" &&
            !empty($r["due_at"]) &&
            strtotime($r["due_at"] . " UTC") <= $now
        ) {
            return ["rental.overdue", "warning", "Durée incluse dépassée"];
        }
        return match ($r["status"]) {
            "release_failed" => [
                "rental.release_failed",
                "warning",
                "Sortie de batterie à vérifier",
            ],
            "payment_review" => [
                "rental.payment_review",
                "warning",
                "Paiement à rapprocher",
            ],
            default => null,
        };
    }
    public static function publish(
        string $key,
        string $type,
        string $permission,
        string $title,
        string $message,
        string $link,
    ): void {
        $db = App::db();
        $q = $db->prepare(
            "SELECT id,active FROM system_notifications WHERE event_key=?",
        );
        $q->execute([$key]);
        $old = $q->fetch();
        $db->prepare(
            "INSERT INTO system_notifications(event_key,type,permission,title,message,link) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),message=VALUES(message),link=VALUES(link),active=1,resolved_at=NULL",
        )->execute([$key, $type, $permission, $title, $message, $link]);
        if ($old && (int) $old["active"] === 0) {
            $db->prepare(
                "DELETE FROM system_notification_reads WHERE notification_id=?",
            )->execute([$old["id"]]);
        }
    }
    public static function refresh(): void
    {
        $db = App::db();
        if (
            (int) $db
                ->query("SELECT GET_LOCK('tbp-system-alerts',0)")
                ->fetchColumn() !== 1
        ) {
            return;
        }
        try {
            $last = $db
                ->query(
                    "SELECT last_run_at FROM service_heartbeats WHERE name='system_alerts'",
                )
                ->fetchColumn();
            if ($last && strtotime($last . " UTC") > time() - 30) {
                return;
            }
            $keys = [];
            $rows = $db
                ->query(
                    "SELECT reference,status,due_at FROM rentals WHERE status IN ('active','release_failed','payment_review')",
                )
                ->fetchAll();
            foreach ($rows as $r) {
                $a = self::rentalAlert($r, time());
                if (!$a) {
                    continue;
                }
                [$type, $level, $title] = $a;
                $key = $type . ":" . $r["reference"];
                $keys[] = $key;
                self::publish(
                    $key,
                    $type,
                    "rentals.manage",
                    $title,
                    $r["reference"],
                    "/admin/rentals/detail?reference=" .
                        rawurlencode($r["reference"]),
                );
            }
            $rows = $db
                ->query(
                    "SELECT s.id,r.reference FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id WHERE s.status='failed'",
                )
                ->fetchAll();
            foreach ($rows as $r) {
                $key = "refund.failed:" . $r["id"];
                $keys[] = $key;
                self::publish(
                    $key,
                    "refund.failed",
                    "payout.view",
                    "Remboursement à vérifier",
                    $r["reference"],
                    "/admin/payout",
                );
            }
            $worker = $db
                ->query(
                    "SELECT last_run_at FROM service_heartbeats WHERE name='heycharge'",
                )
                ->fetchColumn();
            if (
                IntegrationSettings::all()["heycharge"] === "normal" &&
                (int) $db
                    ->query("SELECT COUNT(*) FROM stations WHERE enabled=1")
                    ->fetchColumn() > 0 &&
                (!$worker || strtotime($worker . " UTC") < time() - 240)
            ) {
                $keys[] = "worker.heycharge";
                self::publish(
                    "worker.heycharge",
                    "worker.heycharge",
                    "fleet.manage",
                    "Synchronisation à vérifier",
                    "Le worker HeyCharge ne donne plus de signe récent.",
                    "/admin/stations",
                );
            }
            $sql =
                "UPDATE system_notifications SET active=0,resolved_at=UTC_TIMESTAMP() WHERE active=1 AND type IN ('rental.overdue','rental.release_failed','rental.payment_review','refund.failed','worker.heycharge')";
            if ($keys) {
                $sql .=
                    " AND event_key NOT IN (" .
                    implode(",", array_fill(0, count($keys), "?")) .
                    ")";
            }
            $db->prepare($sql)->execute($keys);
            $db->exec(
                "INSERT INTO service_heartbeats(name,last_run_at) VALUES('system_alerts',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_run_at=VALUES(last_run_at)",
            );
        } finally {
            $db->query("SELECT RELEASE_LOCK('tbp-system-alerts')");
        }
    }
    public static function feed(array $user): array
    {
        $permissions = [];
        foreach (
            ["rentals.manage", "payout.view", "fleet.manage"]
            as $permission
        ) {
            if (Auth::can($permission, $user)) {
                $permissions[] = $permission;
            }
        }
        if (!$permissions) {
            return ["items" => [], "unread" => 0];
        }
        $where =
            "n.active=1 AND n.permission IN (" .
            implode(",", array_fill(0, count($permissions), "?")) .
            ")";
        $q = App::db()->prepare(
            "SELECT n.id,n.title,n.message,n.link,n.created_at,(r.notification_id IS NULL) unread FROM system_notifications n LEFT JOIN system_notification_reads r ON r.notification_id=n.id AND r.user_id=? WHERE " .
                $where .
                " ORDER BY n.created_at DESC,n.id DESC LIMIT 50",
        );
        $q->execute(array_merge([$user["id"]], $permissions));
        $items = $q->fetchAll();
        $q = App::db()->prepare(
            "SELECT COUNT(*) FROM system_notifications n LEFT JOIN system_notification_reads r ON r.notification_id=n.id AND r.user_id=? WHERE " .
                $where .
                " AND r.notification_id IS NULL",
        );
        $q->execute(array_merge([$user["id"]], $permissions));
        return ["items" => $items, "unread" => (int) $q->fetchColumn()];
    }
}
