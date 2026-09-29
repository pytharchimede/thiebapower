<?php
namespace App\Services;

/** Vector PDF, one landscape A4 page per station. Coordinates are millimetres from the top left. */
final class StationLabelPdf {
 private array $commands=[];
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
  $this->commands=[];
  // Full-bleed A4 background; the label content keeps its specified 70 mm
  // side margins, 50 mm top margin and 8 mm internal padding.
  $this->rect(0,0,self::PAGE_W,self::PAGE_H,'#092e37');
  $this->rect(0,0,self::PAGE_W,8,'#0c6471');
  $this->rect(0,202,self::PAGE_W,8,'#0c6471');
  $this->rect(67,47,163,156,'#237581');
  $this->rect(70,50,157,150,'#103d46');
  $this->rect(70,50,157,8,'#0c6471');
  $this->text(77,56,'THIEBA',14,'#ffffff',true);
  $this->text(96,56,'POWER',14,'#ffba5e',true);
  $this->text(77,72,'BATTERIES EXTERNES EN LIBRE SERVICE',7,'#b8ded4',true);
  $this->text(77,84,'Louez une batterie',18,'#ffffff',true);
  $this->text(77,92,'externe.',18,'#ffba5e',true);
  $this->text(77,103,'Scannez le QR code avec votre téléphone.',8,'#e5f3f1');
  $this->text(77,109,'Choisissez, payez, puis récupérez la batterie.',8,'#e5f3f1');
  $this->rect(176,66,44,54,'#ffffff');
  $this->qr($station['url'],180,68,36);
  $this->text(179,117,'SCANNEZ POUR LOUER',8,'#103d46',true);
  $this->rect(77,127,143,.4,'#629da1');
  foreach ([['01','SCANNEZ',77],['02','CHOISISSEZ',113],['03','PAYEZ',149],['04','RÉCUPÉREZ',184]] as [$number,$title,$x]) {
   $this->rect($x,134,9,9,'#ffba5e');$this->text($x+1.4,140.3,$number,9,'#103d46',true);
   $this->text($x,150,$title,7,'#ffffff',true);
  }
  $this->text(77,163,'PAIEMENT MOBILE',7,'#b8ded4',true);
  $this->text(77,170,'Wave    Orange Money    MTN MoMo    Moov Money',8,'#ffffff',true);
  $label=trim((string)($station['label']??''));
  $this->text(77,181,self::shorten($label!==''?$label:'Station Thiebapower',42),9,'#ffba5e',true);
  $this->text(77,188,'Station '.(string)$station['imei'],7,'#d1e7e6');
  $this->text(77,194,self::shorten($station['url'],92),6,'#d1e7e6');
  return implode("\n",$this->commands)."\n";
 }
 private function qr(string $url,float $x,float $y,float $size):void {
  $svg=StationQr::svg($url);
  preg_match_all('/M(\d+) (\d+)h1v1h-1z/',$svg,$cells,PREG_SET_ORDER);
  $unit=$size/45;
  foreach ($cells as $cell) $this->rect($x+(int)$cell[1]*$unit,$y+(int)$cell[2]*$unit,$unit+.008,$unit+.008,'#103d46');
 }
 public function render(array $stations):string {
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
