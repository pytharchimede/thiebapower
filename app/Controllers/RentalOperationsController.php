<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\Audit;
use App\Services\RentalLifecycleService;
final class RentalOperationsController
{
    public function index(): void
    {
        Auth::requirePermission("rentals.view");
        $status = (string) ($_GET["status"] ?? "");
        $allowed = [
            "pending_payment",
            "releasing",
            "active",
            "returned",
            "payment_failed",
            "payment_timeout",
            "release_failed",
            "payment_review",
        ];
        $db = App::db();
        $select =
            "SELECT r.*,b.serial battery_serial,s.status refund_status FROM rentals r JOIN batteries b ON b.id=r.battery_id LEFT JOIN deposit_settlements s ON s.rental_id=r.id";
        $order =
            " ORDER BY CASE WHEN r.status='active' THEN 0 WHEN r.status IN ('release_failed','payment_review') THEN 1 WHEN r.status IN ('releasing','pending_payment') THEN 2 ELSE 3 END,CASE WHEN r.status='active' AND r.due_at<=UTC_TIMESTAMP() THEN 0 ELSE 1 END,CASE WHEN r.status='active' AND r.due_at<=UTC_TIMESTAMP() THEN r.due_at END ASC,COALESCE(r.returned_at,r.started_at,r.created_at) DESC,r.id DESC LIMIT 100";
        if (in_array($status, $allowed, true)) {
            $q = $db->prepare($select . " WHERE r.status=?" . $order);
            $q->execute([$status]);
        } else {
            $status = "";
            $q = $db->query($select . $order);
        }
        $rentals = $q->fetchAll();
        $counts = $db
            ->query(
                "SELECT status,COUNT(*) quantity FROM rentals GROUP BY status",
            )
            ->fetchAll();
        App::view("rental_operations", compact("rentals", "counts", "status"));
    }
    public function cancelPending(): void
    {
        Auth::requirePermission("rentals.cancel", true);
        $reference = (string) ($_POST["reference"] ?? "");
        $confirmation = (string) ($_POST["confirm_reference"] ?? "");
        $proof = trim((string) ($_POST["provider_proof"] ?? ""));
        if (
            !preg_match('/^TBP-[A-F0-9]{16}$/D', $reference) ||
            !hash_equals($reference, $confirmation) ||
            strlen($proof) < 6 ||
            strlen($proof) > 120
        ) {
            http_response_code(422);
            exit("Référence ou preuve fournisseur invalide");
        }
        $q = App::db()->prepare(
            "SELECT status,reservation_expires_at, reservation_expires_at<=UTC_TIMESTAMP() AS expired FROM rentals WHERE reference=?",
        );
        $q->execute([$reference]);
        $r = $q->fetch();
        if (
            !$r ||
            $r["status"] !== "pending_payment" ||
            (int) $r["expired"] !== 1
        ) {
            http_response_code(409);
            exit("Attendre la fin de la session de paiement");
        }
        if (!(new RentalLifecycleService())->failedPayment($reference)) {
            http_response_code(409);
            exit("État de la location modifié");
        }
        Audit::event("rental.pending_cancelled", "rental", $reference, [
            "provider_proof" => $proof,
        ]);
        App::redirect("/admin/rentals?status=payment_failed");
    }
}
