<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;

/** Read-only presentation pages. Existing POST handlers keep all business operations. */
final class AdminPresentationController
{
    public function pricing(): void
    {
        Auth::requirePermission("pricing.manage");
        $csrf = $_SESSION["csrf"];
        $prices = App::db()->query("SELECT * FROM pricing WHERE id=1")->fetch();
        App::view("pricing", compact("prices", "csrf"));
    }
    public function batteries(): void
    {
        Auth::requirePermission("fleet.manage");
        $csrf = $_SESSION["csrf"];
        $batteries = App::db()
            ->query("SELECT * FROM batteries ORDER BY id")
            ->fetchAll();
        App::view("batteries", compact("batteries", "csrf"));
    }
    public function batteryDetail(): void
    {
        Auth::requirePermission("fleet.manage");
        $id = filter_var($_GET["id"] ?? "", FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(404);
            echo "Batterie introuvable";
            return;
        }
        $q = App::db()->prepare("SELECT * FROM batteries WHERE id=?");
        $q->execute([$id]);
        $battery = $q->fetch();
        if (!$battery) {
            http_response_code(404);
            echo "Batterie introuvable";
            return;
        }
        $title = "Batterie " . $battery["serial"];
        $back = "/admin/batteries";
        $fields = [
            "Numéro de série" => $battery["serial"],
            "État" => $battery["status"],
            "Station" => $battery["station_imei"] ?? "—",
            "Emplacement" => $battery["slot_id"] ?? "—",
            "Charge" => isset($battery["battery_capacity"])
                ? $battery["battery_capacity"] . " %"
                : "—",
            "Caution spécifique" => isset($battery["deposit_override"])
                ? number_format(
                        (int) $battery["deposit_override"],
                        0,
                        ",",
                        " ",
                    ) . " FCFA"
                : "Tarif par défaut",
        ];
        $stationLink = !empty($battery["station_imei"])
            ? "/admin/stations/detail?imei=" .
                rawurlencode($battery["station_imei"])
            : "";
        $q = App::db()->prepare(
            "SELECT reference,status,started_at,due_at,returned_at FROM rentals WHERE battery_id=? ORDER BY (status='active') DESC,id DESC LIMIT 1",
        );
        $q->execute([$id]);
        $usage = $q->fetch() ?: null;
        App::view(
            "record_detail",
            compact("title", "back", "fields", "stationLink", "usage"),
        );
    }
    public function rentalDetail(): void
    {
        Auth::requirePermission("rentals.manage");
        $reference = (string) ($_GET["reference"] ?? "");
        if (strlen($reference) > 120 || $reference === "") {
            http_response_code(404);
            echo "Location introuvable";
            return;
        }
        $q = App::db()->prepare(
            "SELECT r.*,b.serial battery_serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.reference=?",
        );
        $q->execute([$reference]);
        $r = $q->fetch();
        if (!$r) {
            http_response_code(404);
            echo "Location introuvable";
            return;
        }
        $title = "Location " . $r["reference"];
        $back = "/admin/rentals";
        $fields = [
            "Référence" => $r["reference"],
            "Client" => $r["customer_name"],
            "Téléphone" => $r["customer_phone"],
            "État" => $r["status"],
            "Batterie" => $r["battery_serial"],
            "Station" => $r["station_code"] ?? "—",
            "Tarif" =>
                number_format((int) $r["rental_fee"], 0, ",", " ") . " FCFA",
            "Caution" =>
                number_format((int) $r["deposit"], 0, ",", " ") . " FCFA",
            "Création" => $r["created_at"],
            "Début" => $r["started_at"] ?? "—",
            "Échéance" => $r["due_at"] ?? "—",
            "Retour" => $r["returned_at"] ?? "—",
        ];
        $stationLink = !empty($r["station_code"])
            ? "/admin/stations/detail?imei=" . rawurlencode($r["station_code"])
            : "";
        $usage = $r;
        App::view(
            "record_detail",
            compact("title", "back", "fields", "stationLink", "usage"),
        );
    }
}
