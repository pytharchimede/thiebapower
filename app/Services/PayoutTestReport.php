<?php

namespace App\Services;



final class PayoutTestReport
{
    public static function build(array $operation, array $requests, array $events): string
    {
        $ref = (string) $operation["reference"];
        $report = "THIEBAPOWER — RAPPORT TEST PAYOUT\n";
        $report .=
            "Référence : $ref\nEnvironnement : " .
            $operation["environment"] .
            "\nMontant : " .
            $operation["amount"] .
            " XOF\n";
        $report .=
            "Statut local : " .
            $operation["status"] .
            "\nSession : " .
            ($operation["provider_session_id"] ?? "absente") .
            "\n";
        $saved = PayoutResult::recordedInitiation($ref, $events);
        $recovered = PayoutResult::initiation($saved)["session"];
        if ($recovered !== "") {
            $report .= "Session retrouvée dans le journal : $recovered\n";
            if (empty($operation["provider_session_id"])) {
                $report .=
                    "Action requise : récupérer la session et vérifier cet essai ; aucun nouveau payout nécessaire.\n";
            }
            $url = PayoutResult::authorizationUrl($saved);
            if ($url !== "") {
                $report .= "URL d’authentification fournisseur : $url\n";
            }
        }
        $verified = false;
        foreach ($events as $event) {
            if ($event["reference"] !== $ref) {
                continue;
            }
            $data = json_decode((string) $event["response"], true);
            if (
                $event["source"] === "status" ||
                (is_array($data) && ($data["method"] ?? "") === "getTransStatus")
            ) {
                $verified = true;
            }
        }
        if (!$verified) {
            $report .=
                "Vérification fournisseur : aucun appel getTransStatus archivé pour cette référence ; versement non confirmé.\n";
        }
        $report .=
            "\nLOGIQUE CÔTÉ THIEBAPOWER\nPHP SoapClient, HTTPS, POST SOAP ; cache WSDL désactivé ; délai de connexion 10 secondes.\n";
        $report .=
            "Transaction unique via initTransact (pas de batch). Référence unique TBP-TEST-PAYOUT-..., montant fixe 200 XOF, téléphone normalisé +225 suivi de 10 chiffres.\n";
        $report .=
            "Authentification : hash_hmac('sha256', timestamp . merchantId, SECRET_KEY). Timestamp Unix en secondes ; validité documentaire 60 secondes ; IP sortante à autoriser chez Paiement Pro. Token et secret masqués.\n";
        $report .=
            "Une session reçue entraîne processing. INITIATED/SUCCEEDED/SUCCESS avec code 0 sans session entraîne initiated. FAILED entraîne failed ; réponse ambiguë ou exception entraîne unknown. Aucun renvoi automatique après une issue inconnue.\n";
        $report .=
            "Vérification via getTransStatus avec un nouveau timestamp/token et sessionid. SUCCESS n'entraîne succeeded qu'après concordance session, référence, montant, devise, canal et bénéficiaire. FAILED entraîne failed. Les callbacks restent non authentifiés et ne confirment pas seuls le versement.\n";
        $report .=
            "Un HTTP 200 ne suffit pas à confirmer le versement. Une clôture locale n'annule pas la transaction fournisseur.\n";
        foreach ($requests as $request) {
            if ($request["reference"] !== $ref) {
                continue;
            }
            $report .=
                "\nREQUÊTE INITIALE — " .
                $request["created_at"] .
                "\nWSDL : " .
                $request["endpoint"] .
                "\nMéthode SOAP : initTransact\nPayload (paramètres SOAP, présentation JSON) :\n" .
                self::pretty($request["parameters"]) .
                "\n";
        }
        $matching = array_filter(
            $events,
            static fn(array $event): bool => $event["reference"] === $ref,
        );
        usort(
            $matching,
            static fn(array $a, array $b): int => ((int) $a["id"]) <=> ((int) $b["id"]),
        );
        if (!$matching) {
            $report .= "\nAucune réponse archivée pour cet essai.\n";
        }
        foreach ($matching as $event) {
            $report .=
                "\nÉCHANGE " .
                $event["source"] .
                " — " .
                $event["created_at"] .
                "\n" .
                self::pretty($event["response"]) .
                "\n";
        }
        $report .=
            "\nLes données absentes des anciens journaux ne sont pas reconstituées. HTTP unavailable signifie que le code HTTP n'a pas été capturé.\n";
        return $report;
    }
    private static function pretty(string $json): string
    {
        return json_encode(
            json_decode($json, true),
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_INVALID_UTF8_SUBSTITUTE,
        ) ?:
            "[données indisponibles]";
    }
}
