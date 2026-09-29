<?php
namespace App\Services;

/** Vector PDF, one landscape A4 page per station. Coordinates are millimetres from the top left. */
final class StationLabelPdf {
 private array $commands=[];
 private array $margins=StationLabelSettings::DEFAULTS;
 private const PAGE_W=297;
 private const PAGE_H=210;
 private static function pt(float $mm):float {return $mm*72/25.4;}
 private static function n(float $number):string {return number_format($number,2,'.','');}
 private function rect(float $x,float $y,float $w,float $h,string $rgb):void {
  $this->commands[]=self::color($rgb).' '.self::n(self::pt($x)).' '.self::n(self::pt(self::PAGE_H-$y-$h)).' '.self::n(self::pt($w)).' '.self::n(self::pt($h)).' re f';
 }
 private static function color(string $hex):string {
  $c=array_map(static fn($v)=>hexdec($v)/255,str_split(ltrim($hex,'#'),2));
  return implode(' ',array_map([self::class,'n'],$c)).' rg';
 }
 private function text(float $x,float $y,string $value,float $size=10,string $rgb='#ffffff',bool $bold=false):void {
  $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value);
  $escaped=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$encoded===false?'':$encoded);
  $this->commands[]=self::color($rgb).' BT /'.($bold?'F2':'F1').' '.self::n($size).' Tf '.self::n(self::pt($x)).' '.self::n(self::pt(self::PAGE_H-$y)).' Td ('.$escaped.') Tj ET';
 }
 private static function shorten(string $value,int $length):string {
  preg_match('/^.{0,'.$length.'}/us',$value,$match);
  return $match[0]??'';
 }
 private function page(array $station):string {
  $width=297-$this->margins['left']-$this->margins['right'];
  $height=210-$this->margins['top']-$this->margins['bottom'];
  $scale=min($width/197,$height/70);
  $x=$this->margins['left']+($width-197*$scale)/2;
  $y=$this->margins['top']+($height-70*$scale)/2;
  $tx=self::pt($x-50*$scale);
  $ty=self::pt(210-$y-140*$scale);
  $this->commands=['q '.number_format($scale,6,'.','').' 0 0 '.number_format($scale,6,'.','').' '.self::n($tx).' '.self::n($ty).' cm'];
  // A4 landscape: 50 mm on the left and right, 70 mm above and below.
  // Artwork is exactly 197 x 70 mm; the content has 6 mm of internal space.
  $this->rect(50,70,197,70,'#103d46');
  $this->rect(50,70,197,5,'#0c6471');
  $this->text(57,80,'THIEBA',14,'#ffffff',true);
  $this->text(76,80,'POWER',14,'#ffba5e',true);
  $this->text(57,88,'BATTERIES EXTERNES EN LIBRE SERVICE',7,'#b8ded4',true);
  $this->text(57,99,'Louez une batterie externe.',20,'#ffffff',true);
  $this->text(57,107,'Scannez le QR code, payez et récupérez votre batterie.',8,'#e5f3f1');
  $this->rect(57,113,136,.35,'#629da1');
  foreach ([['01','SCANNEZ',57],['02','CHOISISSEZ',91],['03','PAYEZ',125],['04','RÉCUPÉREZ',159]] as [$number,$title,$x]) {
   $this->rect($x,118,8,8,'#ffba5e');
   $this->text($x+1.2,123.8,$number,8,'#103d46',true);
   $this->text($x+9.5,123.5,$title,6.8,'#ffffff',true);
  }
  $this->text(57,133,'Wave  ·  Orange Money  ·  MTN MoMo  ·  Moov Money',8,'#e5f3f1');
  $this->rect(201,76,40,58,'#ffffff');
  $this->qr($station['url'],204,77,34);
  $this->text(204,117,'SCANNEZ POUR LOUER',7,'#103d46',true);
  $label=trim((string)($station['label']??''));
  $this->text(204,125,self::shorten($label!==''?$label:'Station Thiebapower',22),7,'#103d46',true);
  $this->text(204,131,self::shorten((string)$station['imei'],25),6,'#315b61');
  $this->commands[]='Q';
  return implode("\n",$this->commands)."\n";
 }
 private function qr(string $url,float $x,float $y,float $size):void {
  $svg=StationQr::svg($url);
  preg_match_all('/M(\d+) (\d+)h1v1h-1z/',$svg,$cells,PREG_SET_ORDER);
  $unit=$size/45;
  foreach ($cells as $cell) $this->rect($x+(int)$cell[1]*$unit,$y+(int)$cell[2]*$unit,$unit+.008,$unit+.008,'#103d46');
 }
 public function render(array $stations,array $margins=StationLabelSettings::DEFAULTS):string {
  $this->margins=StationLabelSettings::validate($margins);
  if(!$stations)throw new \InvalidArgumentException('Aucune station à imprimer');
  $objects=[];$add=static function(string $body)use(&$objects):int {$objects[]=$body;return count($objects);};
  $catalog=$add('');$pages=$add('');
  $font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
  $bold=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
  $kids=[];
  foreach ($stations as $station) {
   $content=$this->page($station);$stream=$add('<< /Length '.strlen($content).' >>' . "\nstream\n".$content.'endstream');
   $kids[]=$add('<< /Type /Page /Parent '.$pages.' 0 R /MediaBox [0 0 '.self::n(self::pt(self::PAGE_W)).' '.self::n(self::pt(self::PAGE_H)).'] /Resources << /Font << /F1 '.$font.' 0 R /F2 '.$bold.' 0 R >> >> /Contents '.$stream.' 0 R >>');
  }
  $objects[$pages-1]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$kids)).'] >>';
  $objects[$catalog-1]='<< /Type /Catalog /Pages '.$pages.' 0 R >>';
  $pdf="%PDF-1.4\n";$offsets=[0];
  foreach ($objects as $i=>$body) {$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$body."\nendobj\n";}
  $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
  foreach (array_slice($offsets,1) as $offset)$pdf.=sprintf('%010d 00000 n ', $offset)."\n";
  return $pdf.'trailer << /Size '.(count($objects)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
 }
}
