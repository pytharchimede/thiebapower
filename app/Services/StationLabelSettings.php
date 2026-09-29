<?php
namespace App\Services;
use App\Core\App;
final class StationLabelSettings {
 public const DEFAULTS=['left'=>50.0,'right'=>50.0,'top'=>70.0,'bottom'=>70.0];
 public static function validate(array $values):array {
  $result=[];
  foreach(self::DEFAULTS as $key=>$default){
   $value=$values[$key]??$default;
   if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0)throw new \InvalidArgumentException('Marges invalides');
   $result[$key]=(float)$value;
  }
  if(297-$result['left']-$result['right']<150||210-$result['top']-$result['bottom']<60)throw new \InvalidArgumentException('Conservez une zone de contenu d’au moins 15 × 6 cm.');
  return $result;
 }
 public static function load():array {
  $row=App::db()->query('SELECT margin_left,margin_right,margin_top,margin_bottom FROM station_label_settings WHERE id=1')->fetch();
  if(!$row)return self::DEFAULTS;
  return self::validate(['left'=>$row['margin_left'],'right'=>$row['margin_right'],'top'=>$row['margin_top'],'bottom'=>$row['margin_bottom']]);
 }
 public static function save(array $values):void {
  $v=self::validate($values);
  App::db()->prepare('UPDATE station_label_settings SET margin_left=?,margin_right=?,margin_top=?,margin_bottom=? WHERE id=1')->execute([$v['left'],$v['right'],$v['top'],$v['bottom']]);
 }
 public static function fromCentimetres(array $input):array {
  $v=[];foreach(self::DEFAULTS as $key=>$default){if(!isset($input[$key])||!is_numeric($input[$key]))throw new \InvalidArgumentException('Renseignez les quatre marges en cm.');$v[$key]=(float)$input[$key]*10;}
  return self::validate($v);
 }
}
