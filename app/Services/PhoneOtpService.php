<?php
namespace App\Services;
use App\Core\App;
final class PhoneOtpService
{
 public static function generateCode(): string {return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);}
 public static function digest(string $challenge,string $phone,string $purpose,string $code): string
 {
  $key=App::env('PHONE_OTP_KEY',App::env('ORANGE_SMS_ENCRYPTION_KEY'));
  $decoded=base64_decode($key,true);if($decoded===false||strlen($decoded)!==32)throw new \RuntimeException('Configurer PHONE_OTP_KEY avec une cle de 32 octets en base64.');
  return hash_hmac('sha256',$challenge.'|'.$phone.'|'.$purpose.'|'.$code,$decoded);
 }
 public function issue(string $phone,string $purpose='verify_phone',int $minutes=5): array
 {
  $phone=OrangeSmsClient::phone($phone);
  if(!preg_match('/^[a-z_]{1,40}$/D',$purpose)||$minutes<1||$minutes>10)throw new \InvalidArgumentException('Objet ou validite OTP invalide.');
  $db=App::db();$lock='otp_'.substr(hash('sha256',$phone.':'.$purpose),0,50);
  $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new \RuntimeException('Demande OTP deja en cours.');
  try{
   $q=$db->prepare('SELECT COUNT(*) FROM phone_otp_challenges WHERE phone=? AND purpose=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 SECOND)');$q->execute([$phone,$purpose]);if((int)$q->fetchColumn()>0)throw new \InvalidArgumentException('Attendez 60 secondes avant un nouveau code.');
   $code=self::generateCode();$id=bin2hex(random_bytes(16));$hash=self::digest($id,$phone,$purpose,$code);
   $db->beginTransaction();
   $db->prepare('UPDATE phone_otp_challenges SET used_at=UTC_TIMESTAMP() WHERE phone=? AND purpose=? AND used_at IS NULL')->execute([$phone,$purpose]);
   $db->prepare('INSERT INTO phone_otp_challenges(challenge_id,phone,purpose,code_hash,expires_at) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))')->execute([$id,$phone,$purpose,$hash,$minutes]);$db->commit();
   // Only trusted server code receives the plain OTP for dispatch; never return it in a public API.
   return ['challenge_id'=>$id,'code'=>$code,'expires_in'=>$minutes*60];
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
 }
 public function verify(string $challenge,string $phone,string $purpose,string $code): bool
 {
  $phone=OrangeSmsClient::phone($phone);$db=App::db();$db->beginTransaction();
  try{
   $q=$db->prepare('SELECT *,expires_at<=UTC_TIMESTAMP() AS expired FROM phone_otp_challenges WHERE challenge_id=? FOR UPDATE');$q->execute([$challenge]);$r=$q->fetch();
   if(!$r||$r['phone']!==$phone||$r['purpose']!==$purpose||$r['used_at']!==null||(int)$r['expired']===1||(int)$r['attempts']>=5){$db->commit();return false;}
   $ok=preg_match('/^[0-9]{6}$/D',$code)===1&&hash_equals($r['code_hash'],self::digest($challenge,$phone,$purpose,$code));
   $db->prepare('UPDATE phone_otp_challenges SET attempts=attempts+1,used_at=IF(?=1 OR attempts>=5,UTC_TIMESTAMP(),used_at) WHERE challenge_id=?')->execute([$ok?1:0,$challenge]);$db->commit();return $ok;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
