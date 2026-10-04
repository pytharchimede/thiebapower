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
            throw new CheckoutConflict("Une demande est déjà en cours. Patientez quelques secondes, puis consultez votre suivi de paiement.");
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
                return '/payment/return?reference='.rawurlencode($existing['reference']);
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
        $channel = (string) ($input["payment_channel"] ?? "");
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
                throw new CheckoutConflict("Cette batterie n’est plus disponible sur ce terminal. Actualisez la liste et choisissez une batterie disponible.");
            }
            $remote = CheckoutDiagnostics::measure(
                "heycharge_inventory",
                fn() => (new HeyChargeOpenApi())->station($station),
            );
            if (($remote["imei"] ?? "") !== $station || !isset($remote['batteries']) || !is_array($remote['batteries'])) {
                throw new \RuntimeException('Réponse station incohérente');
            }
            if (
                !StationFleetService::availableAt(
                    $remote,
                    $candidate["serial"],
                    $candidate["slot_id"],
                )
            ) {
                throw new CheckoutConflict(
                    "La batterie choisie n’est plus louable à cet emplacement. Actualisez la liste et choisissez une autre batterie.",
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
                throw new CheckoutConflict("Cette batterie vient d’être réservée ou n’est plus disponible. Actualisez la liste.");
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
                    throw new CheckoutConflict(
                        "Le terminal ou la batterie n’est plus disponible. Actualisez la liste avant de choisir à nouveau.",
                    );
                }
            }
            $price = $db->query("SELECT * FROM pricing WHERE id=1")->fetch();
            $offer=null;$effectiveFee=(int)$price['rental_fee'];
            if(PromotionService::enabled()&&trim((string)($input['promotion_code']??''))!==''){
                try{$offer=(new PromotionService)->assess((string)$input['promotion_code'],$phone,$effectiveFee,(string)($input['previous_token']??''),true);$effectiveFee=$offer['fee'];}catch(\InvalidArgumentException $e){throw new CheckoutConflict($e->getMessage());}
            }
            $reference = "TBP-" . strtoupper(bin2hex(random_bytes(8)));
            $db->prepare(
                "INSERT INTO rentals(checkout_token,reference,battery_id,station_code,payment_environment,customer_name,customer_email,customer_phone,payment_channel,payout_channel,rental_fee,deposit,late_percent,duration_minutes,billing_rule,status,reservation_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'prorata_grace5','pending_payment',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))",
            )->execute([
                $input["checkout_token"],
                $reference,
                $batteryId,
                $station,
                IntegrationSettings::all()["paiementpro"],
                $name,
                $email,
                $phone,
                $depositEnabled ? $channel : null,
                null, // Filled automatically after a verified payment; no separate refund choice.
                $effectiveFee,
                $depositEnabled
                    ? (int) ($battery["deposit_override"] ??
                        $price["default_deposit"])
                    : 0,
                (int) $price["late_percent"],
                (int) $price["duration_minutes"],
            ]);
            if($offer!==null)(new PromotionService)->record((int)$db->lastInsertId(),$offer,$phone);
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
