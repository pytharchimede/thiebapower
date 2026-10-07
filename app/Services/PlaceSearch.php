<?php
namespace App\Services;
use App\Core\App;
final class PlaceSearch
{
 public static function normalize(array $geo):array {
  $results=[];
  foreach(array_slice($geo['features']??[],0,6) as $item){
   $p=$item['properties']??[];$c=$item['geometry']['coordinates']??[];
   if(count($c)!==2||!is_numeric($c[0])||!is_numeric($c[1])||!is_finite((float)$c[0])||!is_finite((float)$c[1])||abs((float)$c[0])>180||abs((float)$c[1])>90)continue;
   $parts=array_filter([$p['name']??'',trim(($p['housenumber']??'').' '.($p['street']??'')),$p['district']??'',$p['city']??'',$p['state']??'',$p['country']??'']);
   $results[]=['label'=>implode(', ',array_unique($parts)),'latitude'=>(float)$c[1],'longitude'=>(float)$c[0]];
  }
  return $results;
 }
 public function search(string $query):array {
  if(strlen($query)<3||strlen($query)>160)throw new \InvalidArgumentException('Recherche invalide');
  $endpoint=rtrim(App::env('PHOTON_API_URL','https://photon.komoot.io'),'/');
  if(!str_starts_with($endpoint,'https://'))throw new \RuntimeException('Recherche géographique non configurée');
  $key='places-'.hash('sha256',$endpoint.'|'.$query).'.json';$cache=SystemStorage::read($key);
  if($cache&&time()-(int)($cache['at']??0)<86400)return $cache['results'];
  $handle=curl_init($endpoint.'/api/?'.http_build_query(['q'=>$query,'limit'=>6,'lang'=>'fr','lat'=>5.35,'lon'=>-4.02,'countrycode'=>'CI']));
  curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>7,CURLOPT_USERAGENT=>'Thiebapower/1.0 station-place-search',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
  $raw=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);
  if($raw===false||$status!==200||strlen($raw)>262144)throw new \RuntimeException('Recherche temporairement indisponible');
  $decoded=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$results=self::normalize($decoded);
  try{SystemStorage::write($key,['at'=>time(),'results'=>$results]);}catch(\Throwable $e){error_log('Place cache unavailable');}
  return $results;
 }
}
