<?php
namespace App\Controllers;
use App\Services\OrangeSmsDelivery;
final class OrangeSmsCallbackController
{
 public function receive(): void
 {
  header('Content-Type: application/json');header('Cache-Control: no-store');
  // Never trust client-supplied forwarding headers for the provider IP.
  if(!OrangeSmsDelivery::authorized((string)($_GET['token']??''),(string)($_SERVER['REMOTE_ADDR']??''))){http_response_code(403);echo '{"error":"Forbidden"}';return;}
  try{$raw=file_get_contents('php://input',false,null,0,16385);OrangeSmsDelivery::record(OrangeSmsDelivery::parse($raw));echo '{"received":true}';}
  catch(\InvalidArgumentException|\JsonException $e){http_response_code(400);echo '{"error":"Invalid notification"}';}
  catch(\Throwable $e){error_log('Orange SMS receipt storage unavailable');http_response_code(503);echo '{"error":"Storage unavailable"}';}
 }
}
