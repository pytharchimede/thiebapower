<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\FinanceWithdrawalService;
final class FinanceWithdrawalController
{
    public function send(): void
    {
        $user=Auth::requirePermission('finance.withdraw',true);
        try {(new FinanceWithdrawalService())->send($_POST,(int)$user['id']);}
        catch (\InvalidArgumentException|\LogicException $e) {http_response_code(422);echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');return;}
        catch (\Throwable $e) {error_log($e);http_response_code(503);echo 'Retrait non finalisé. Consultez le journal avant toute nouvelle tentative.';return;}
        App::redirect('/admin/finance');
    }
    public function verify(): void
    {
        Auth::requirePermission('finance.withdraw',true);
        try {(new FinanceWithdrawalService())->verify((string)($_POST['reference']??''));}
        catch (\Throwable $e) {error_log($e);http_response_code(503);echo 'Vérification indisponible ou session absente. Conservez la référence et contactez Paiement Pro ; ne réémettez pas le retrait.';return;}
        App::redirect('/admin/finance');
    }
}
