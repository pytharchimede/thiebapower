<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\SystemStorage;
use App\Services\SystemReports;
final class SystemController
{
    public function index(): void
    {
        Auth::requirePermission("system.manage");
        $reports = SystemReports::listing();
        $filter = trim((string) ($_GET["search"] ?? ""));
        if ($filter !== "") {
            $reports = array_values(
                array_filter(
                    $reports,
                    fn($r) => stripos(json_encode($r), $filter) !== false,
                ),
            );
        }
        $enabled = SystemStorage::countersEnabled();
        try {
            $storageReady = is_writable(SystemStorage::ensure());
        } catch (\Throwable $e) {
            $storageReady = false;
        }
        App::view(
            "system",
            compact("reports", "enabled", "filter", "storageReady"),
        );
    }
    public function settings(): void
    {
        Auth::requirePermission("system.manage", true);
        try {
            SystemStorage::setCounters(
                ($_POST["counters_enabled"] ?? "") === "1",
            );
            App::redirect("/admin/system?saved=1");
        } catch (\Throwable $e) {
            http_response_code(503);
            echo "Réglage non enregistré : vérifiez les droits du stockage système.";
        }
    }
    public function acknowledge(): void
    {
        Auth::requirePermission("system.manage", true);
        SystemReports::acknowledge((string) ($_POST["id"] ?? ""));
        App::redirect("/admin/system");
    }
}
