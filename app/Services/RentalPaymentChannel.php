<?php
namespace App\Services;
use App\Core\App;
final class RentalPaymentChannel
{
    public const CHANNELS=['WAVECI'=>'Wave','OMCIV'=>'Orange Money','MOMOCI'=>'MTN MoMo','FLOOZ'=>'Moov Money'];
    private static function configured(string $column):array
    {
        try{$raw=App::db()->query('SELECT '.$column.' FROM deposit_wallet_settings WHERE id=1')->fetchColumn();$items=json_decode((string)$raw,true);}
        catch(\Throwable $e){return array_keys(self::CHANNELS);}
        if(!is_array($items))return array_keys(self::CHANNELS);
        return array_values(array_filter(array_unique(array_map([self::class,'normalize'],$items))));
    }
    public static function paymentChannels():array{return self::configured('payment_channels');}
    public static function payoutChannels():array{return self::configured('payout_channels');}
    public static function paymentEnabled(mixed $channel):bool{$channel=self::normalize($channel);return $channel!==null&&in_array($channel,self::paymentChannels(),true);}
    public static function payoutEnabled(mixed $channel):bool{$channel=self::normalize($channel);return $channel!==null&&in_array($channel,self::payoutChannels(),true);}
    public static function normalize(mixed $channel):?string
    {
        if(!is_string($channel))return null;
        return match(strtoupper(trim($channel))){'WAVECI'=>'WAVECI','MOMOCI'=>'MOMOCI','OMCIV','OMCIV2'=>'OMCIV','FLOOZ'=>'FLOOZ',default=>null};
    }
    /** Only call after PaymentVerification authenticated a successful notification. */
    public static function resolve(array $rental,array $notification):array
    {
        if(array_key_exists('channel',$notification))return ['channel'=>self::normalize($notification['channel']),'source'=>'provider_notification'];
        // Documented notifications can omit channel. Only a session explicitly routed
        // to one payment channel qualifies; never use the old independent refund preference.
        return ['channel'=>self::normalize($rental['payment_channel']??null),'source'=>'requested_session'];
    }
    public static function record(int $id,array $notification):void
    {
        $db=App::db();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM rentals WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
            if(!$r){$db->commit();return;}
            $q=$db->prepare('SELECT status FROM deposit_settlements WHERE rental_id=?');$q->execute([$id]);$settled=$q->fetchColumn();
            // Never change a beneficiary channel after a payout has been attempted.
            if($settled!==false && $settled!=='pending'){$db->commit();return;}
            $resolved=self::resolve($r,$notification);
            if(!empty($r['payment_channel_source'])){
                // Duplicate callbacks cannot reroute a recorded refund destination.
                if($resolved['channel']!==null && $resolved['channel']!==$r['payment_channel'])throw new \LogicException('Canal de paiement contradictoire : rapprochement requis.');
                $db->commit();return;
            }
            $db->prepare('UPDATE rentals SET payment_channel=?,payout_channel=?,payment_channel_source=? WHERE id=?')->execute([$resolved['channel'],$resolved['channel'],$resolved['channel']===null?null:$resolved['source'],$id]);
            $db->commit();
            Audit::event('payment.refund_channel','rental',(string)$id,$resolved);
            if($resolved['channel']===null)SystemReports::record('payment.refund_channel',new \RuntimeException('Canal de paiement absent ou non remboursable ; caution à rapprocher.'),['rental_id'=>$id],'payment.refund_channel:'.$id);
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
