<?php
namespace App\Services;

final class PayoutCallbackAssessment {
 public static function assess(array $payload,array $expected):array {
  $reference=(string)($payload['referenceNo']??$payload['referenceNumber']??$payload['reference']??'');
  $payee=(string)($payload['payeeNo']??'');
  try {$payee=$payee===''?'':PaiementProPayoutService::normalizePhone($payee);}catch(\Throwable){$payee='';}
  return [
   'authenticated'=>'false',
   'matchedReference'=>(string)($reference!==''&&hash_equals((string)$expected['reference'],$reference)?'true':'false'),
   'matchedAmount'=>(string)(isset($payload['amount'])&&(int)$payload['amount']===(int)$expected['amount']?'true':'false'),
   'matchedMerchant'=>(string)(isset($payload['merchantId'])&&hash_equals((string)$expected['merchantId'],(string)$payload['merchantId'])?'true':'false'),
   'matchedBeneficiary'=>(string)($payee!==''&&hash_equals((string)$expected['payeeNo'],$payee)&&isset($payload['channel'])&&hash_equals((string)$expected['channel'],(string)$payload['channel'])?'true':'false'),
  ];
 }
}
