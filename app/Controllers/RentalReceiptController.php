<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\RentalReceipt;
use App\Services\BrandedReportPdf;
final class RentalReceiptController
{
    public function admin():void {Auth::requirePermission('rentals.view');Auth::requirePermission('reports.export');$this->render(false);}
    public function customer():void {$this->render(true);}
    private function render(bool $public):void
    {
        header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
        $db=App::db();$reference=(string)($_GET['reference']??'');
        $q=$db->prepare('SELECT r.*,b.serial battery_serial FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.reference=?');$q->execute([$reference]);$r=$q->fetch();
        $token=(string)($_GET['token']??'');
        if(!$r||$r['status']!=='returned'||($public&&(!preg_match('/^[a-f0-9]{32}$/D',$token)||empty($r['checkout_token'])||!hash_equals($r['checkout_token'],$token)))){http_response_code(404);echo 'Reçu indisponible';return;}
        $q=$db->prepare('SELECT * FROM deposit_settlements WHERE rental_id=?');$q->execute([$r['id']]);$settlement=$q->fetch()?:null;
        $q=$db->prepare("SELECT MIN(received_at) FROM payment_notifications WHERE rental_id=? AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.responsecode'))='0'");$q->execute([$r['id']]);$paidAt=$q->fetchColumn()?:null;
        $report=RentalReceipt::report($r,$settlement,$paidAt);
        // The QR points to the authenticated copy; no customer's access token is encoded or logged.
        $url=rtrim(App::env('APP_URL'),'/').'/admin/rentals/receipt?reference='.rawurlencode($reference);
        $pdf=(new BrandedReportPdf())->render('Reçu de location · retour confirmé',$report['headers'],$report['rows'],$report['cards'],$url,$reference.' · Horaires en UTC · Restitution selon statut indiqué');
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="recu-'.$reference.'.pdf"');echo $pdf;
    }
}
