<?php
namespace App\Services;
final class StationProfile
{
 public static function validate(array $input):array {
  $data=[];
  foreach(['label'=>160,'address'=>500,'venue_type'=>80,'opening_hours'=>250,'manager_name'=>160,'manager_phone'=>30,'manager_email'=>190,'manager_notes'=>2000] as $key=>$max){
   if(isset($input[$key])&&!is_scalar($input[$key]))throw new \InvalidArgumentException('Champ invalide');
   $v=trim((string)($input[$key]??''));
   if(strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v))throw new \InvalidArgumentException('Champ trop long ou invalide');
   $data[$key]=$v;
  }
  if($data['label']==='')throw new \InvalidArgumentException('Donnez un nom à cette station.');
  if($data['manager_email']!==''&&!filter_var($data['manager_email'],FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Adresse email du gérant invalide.');
  if($data['manager_phone']!==''&&!preg_match('/^\+?[0-9 ()-]{8,30}$/D',$data['manager_phone']))throw new \InvalidArgumentException('Téléphone du gérant invalide.');
  $lat=$input['latitude']??'';$lon=$input['longitude']??'';
  if(($lat==='')!==($lon===''))throw new \InvalidArgumentException('Position incomplète.');
  $data['latitude']=$data['longitude']=null;
  if($lat!==''){
   if(!is_scalar($lat)||!is_scalar($lon)||!is_numeric($lat)||!is_numeric($lon)||!is_finite((float)$lat)||!is_finite((float)$lon)||abs((float)$lat)>90||abs((float)$lon)>180)throw new \InvalidArgumentException('Position invalide.');
   $data['latitude']=(float)$lat;$data['longitude']=(float)$lon;
  }
  return $data;
 }
 public static function publicFields(array $row):array {
  $enabled=(int)($row['enabled']??1)===1;
  $fresh=!empty($row['last_seen_at'])&&strtotime($row['last_seen_at'].' UTC')>=time()-600&&$row['status']==='online';
  return ['imei'=>$row['imei'],'label'=>$row['label']?:$row['imei'],'address'=>$row['address']??'',
   'latitude'=>isset($row['latitude'])?(float)$row['latitude']:null,'longitude'=>isset($row['longitude'])?(float)$row['longitude']:null,
   'opening_hours'=>$row['opening_hours']??'','venue_type'=>$row['venue_type']??'',
   'enabled'=>$enabled,'manager_name'=>$row['manager_name']??'','manager_phone'=>$row['manager_phone']??'',
   'available'=>$enabled&&$fresh?(int)($row['available_count']??0):0,'fresh'=>$fresh];
 }
}
