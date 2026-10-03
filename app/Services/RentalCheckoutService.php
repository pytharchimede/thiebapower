<?php
namespace App\Services;
use App\Core\App;
use App\Repositories\RentalRepository;
final class RentalCheckoutService
{
    public function begin(array $input): string
    {
        return CheckoutDiagnostics::measure(
            "checkout_request",
            fn() => $this->beginGuarded($input),
        );
    }
    private function beginGuarded(array $input): string
    {
        $token = (string) ($input["checkout_token"] ?? "");
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new \InvalidArgumentException("Formulaire à recharger");
        }
        $db = App::db();
        $lock = "tbp-checkout-" . $token;
        $q = $db->prepare("SELECT GET_LOCK(?,0)");
        $q->execute([$lock]);
        if ((int) $q->fetchColumn() !== 1) {
            throw new \RuntimeException("Demande déjà en cours");
        }
        try {
            $q = $db->prepare(
                "SELECT reference,status,battery_id,station_code,customer_name,customer_phone,checkout_payment_url,reservation_expires_at FROM rentals WHERE checkout_token=?",
            );
            $q->execute([$token]);
            $existing = $q->fetch();
            if ($existing) {
                if (
                    (int) $existing["battery_id"] !==
                        (int) ($input["battery_id"] ?? 0) ||
                    $existing["station_code"] !==
                        trim((string) ($input["station_code"] ?? "")) ||
                    $existing["customer_name"] !==
                        trim((string) ($input["name"] ?? "")) ||
                    $existing["customer_phone"] !==
                        preg_replace(
                            "/\s+/",
                            "",
                            (string) ($input["phone"] ?? ""),
                        )
                ) {
                    throw new \InvalidArgumentException(
                        "Formulaire incohérent",
                    );
                }
                if (
                    $existing["status"] === "pending_payment" &&
                    $existing["checkout_payment_url"] &&
                    strtotime($existing["reservation_expires_at"] . " UTC") >
                        time()
                ) {
                    return $existing["checkout_payment_url"];
                }
                throw new \RuntimeException(
                    "Demande déjà enregistrée ; aucune réémission",
                );
            }
            return CheckoutDiagnostics::measure(
                "checkout",
                fn() => $this->beginOnce($input),
            );
        } finally {
            $q = $db->prepare("SELECT RELEASE_LOCK(?)");
            $q->execute([$lock]);
        }
    }
    private function beginOnce(array $input): string
    {
        $name = trim((string) ($input["name"] ?? ""));
        $email = "";
        $phone = preg_replace("/\s+/", "", (string) ($input["phone"] ?? ""));
        $station = trim((string) ($input["station_code"] ?? ""));
        $channel = (string) ($input["payout_channel"] ?? "");
        $depositEnabled =
            (int) App::db()
                ->query("SELECT deposit_enabled FROM pricing WHERE id=1")
                ->fetchColumn() === 1;
        $batteryId = filter_var(
            $input["battery_id"] ?? null,
            FILTER_VALIDATE_INT,
        );
        if (
            $name === "" ||
            strlen($name) > 160 ||
            !preg_match('/^\+?[0-9]{10,16}$/', $phone) ||
            $station === "" ||
            strlen($station) > 120 ||
            !$batteryId ||
            ($depositEnabled &&
                !in_array(
                    $channel,
                    ["WAVECI", "MOMOCI", "OMCIV", "FLOOZ"],
                    true,
                ))
        ) {
            throw new \InvalidArgumentException(
                "Informations de location invalides",
            );
        }
        // Validate the required provider contact before reserving a battery.
        PaiementProService::customerEmail(["customer_email" => ""]);
        $db = App::db();
        $physical = IntegrationSettings::all()["heycharge"] === "normal";
        if ($physical) {
            $q = $db->prepare(
                "SELECT serial,slot_id,station_imei FROM batteries WHERE id=?",
            );
            $q->execute([$batteryId]);
            $candidate = $q->fetch();
            if (
                !$candidate ||
                $candidate["station_imei"] !== $station ||
                !$candidate["slot_id"]
            ) {
                throw new \RuntimeException("Station ou batterie indisponible");
            }
            $remote = CheckoutDiagnostics::measure(
                "heycharge_inventory",
                fn() => (new HeyChargeOpenApi())->station($station),
            );
            if (
                ($remote["imei"] ?? "") !== $station ||
                !StationFleetService::availableAt(
                    $remote,
                    $candidate["serial"],
                    $candidate["slot_id"],
                )
            ) {
                throw new \RuntimeException(
                    "Batterie indisponible sur la station",
                );
            }
        }
        $db->beginTransaction();
        try {
            $q = $db->prepare(
                "SELECT * FROM batteries WHERE id=? AND status='available' FOR UPDATE",
            );
            $q->execute([$batteryId]);
            $battery = $q->fetch();
            if (!$battery) {
                throw new \RuntimeException("Batterie indisponible");
            }
            if ($physical) {
                $q = $db->prepare("SELECT enabled FROM stations WHERE imei=?");
                $q->execute([$station]);
                if (
                    (int) $q->fetchColumn() !== 1 ||
                    $battery["station_imei"] !== $station ||
                    !$battery["slot_id"] ||
                    $battery["slot_id"] !== $candidate["slot_id"] ||
                    $battery["serial"] !== $candidate["serial"]
                ) {
                    throw new \RuntimeException(
                        "Station ou batterie indisponible",
                    );
                }
            }
            $price = $db->query("SELECT * FROM pricing WHERE id=1")->fetch();
            $reference = "TBP-" . strtoupper(bin2hex(random_bytes(8)));
            $db->prepare(
                "INSERT INTO rentals(checkout_token,reference,battery_id,station_code,payment_environment,customer_name,customer_email,customer_phone,payout_channel,rental_fee,deposit,late_percent,duration_minutes,billing_rule,status,reservation_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'prorata_grace5','pending_payment',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))",
            )->execute([
                $input["checkout_token"],
                $reference,
                $batteryId,
                $station,
                IntegrationSettings::all()["paiementpro"],
                $name,
                $email,
                $phone,
                $channel,
                (int) $price["rental_fee"],
                $depositEnabled
                    ? (int) ($battery["deposit_override"] ??
                        $price["default_deposit"])
                    : 0,
                (int) $price["late_percent"],
                (int) $price["duration_minutes"],
            ]);
            $db->prepare(
                "UPDATE batteries SET status='reserved' WHERE id=?",
            )->execute([$batteryId]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        $session = null;
        try {
            $r = (new RentalRepository())->find($reference);
            $session = CheckoutDiagnostics::measure(
                "paiementpro_session",
                fn() => (new PaiementProService())->initiateSession($r),
            );
            $db->prepare(
                "UPDATE rentals SET payment_session_id=?,checkout_payment_url=? WHERE reference=?",
            )->execute([$session["id"], $session["url"], $reference]);
            return $session["url"];
        } catch (\Throwable $e) {
            if ($session !== null) {
                error_log(
                    "Payment session created but could not be persisted for " .
                        $reference .
                        ": " .
                        $e->getMessage(),
                );
                throw $e;
            }
            $db->beginTransaction();
            try {
                $q = $db->prepare(
                    "SELECT * FROM rentals WHERE reference=? FOR UPDATE",
                );
                $q->execute([$reference]);
                $r = $q->fetch();
                if ($r && $r["status"] === "pending_payment") {
                    $db->prepare(
                        "UPDATE rentals SET status='payment_failed' WHERE id=?",
                    )->execute([$r["id"]]);
                }
                $db->commit();
            } catch (\Throwable $inner) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log($inner);
            }
            throw $e;
        }
    }
}
