<?php
namespace App\Services;

/** Self-contained QR Code version 5, error correction L, byte mode. */
final class StationQr {
 private const SIZE=37;
 public static function svg(string $url):string {
  if(strlen($url)>106||!preg_match('~^https://[A-Za-z0-9./?=_%-]+$~D',$url))throw new \InvalidArgumentException('URL trop longue pour le QR de station');
  $bits='0100'.str_pad(decbin(strlen($url)),8,'0',STR_PAD_LEFT);
  foreach(unpack('C*',$url) as $byte)$bits.=str_pad(decbin($byte),8,'0',STR_PAD_LEFT);
  $bits.=str_repeat('0',min(4,864-strlen($bits)));
  $bits.=str_repeat('0',(8-strlen($bits)%8)%8);
  $data=[];
  foreach(str_split($bits,8) as $byte)$data[]=bindec($byte);
  for($pad=0;count($data)<108;$pad++)$data[]=$pad%2===0?0xec:0x11;
  $div=array_fill(0,26,0);$div[25]=1;$root=1;
  for($i=0;$i<26;$i++){
   for($j=0;$j<26;$j++){
    $div[$j]=self::multiply($div[$j],$root);
    if($j<25)$div[$j]^=$div[$j+1];
   }
   $root=self::multiply($root,2);
  }
  $ecc=array_fill(0,26,0);
  foreach($data as $byte){
   $factor=$byte^array_shift($ecc);$ecc[]=0;
   foreach($div as $j=>$coefficient)$ecc[$j]^=self::multiply($coefficient,$factor);
  }
  $stream='';foreach(array_merge($data,$ecc) as $byte)$stream.=str_pad(decbin($byte),8,'0',STR_PAD_LEFT);
  $m=array_fill(0,self::SIZE,array_fill(0,self::SIZE,null));
  foreach([[3,3],[self::SIZE-4,3],[3,self::SIZE-4]] as [$cx,$cy]){
   for($dy=-4;$dy<=4;$dy++)for($dx=-4;$dx<=4;$dx++){
    $x=$cx+$dx;$y=$cy+$dy;if($x<0||$y<0||$x>=self::SIZE||$y>=self::SIZE)continue;
    $d=max(abs($dx),abs($dy));$m[$y][$x]=($d!==2&&$d!==4)?1:0;
   }
  }
  for($i=8;$i<self::SIZE-8;$i++){
   if($m[6][$i]===null)$m[6][$i]=$i%2===0?1:0;
   if($m[$i][6]===null)$m[$i][6]=$i%2===0?1:0;
  }
  for($dy=-2;$dy<=2;$dy++)for($dx=-2;$dx<=2;$dx++)$m[30+$dy][30+$dx]=max(abs($dy),abs($dx))!==1?1:0;
  // Reserve both format information tracks before placing payload modules.
  for($i=0;$i<=8;$i++){$m[8][$i]=0;$m[$i][8]=0;}
  for($i=0;$i<8;$i++){$m[8][self::SIZE-1-$i]=0;$m[self::SIZE-1-$i][8]=0;}
  $m[self::SIZE-8][8]=1;
  $position=0;
  for($right=self::SIZE-1;$right>=1;$right-=2){
   if($right===6)$right=5;
   for($vertical=0;$vertical<self::SIZE;$vertical++){
    $y=((($right+1)&2)===0)?self::SIZE-1-$vertical:$vertical;
    for($j=0;$j<2;$j++){
     $x=$right-$j;if($m[$y][$x]!==null)continue;
     $bit=$position<strlen($stream)&&$stream[$position]==='1';$position++;
     $m[$y][$x]=($bit xor (($x+$y)%2===0))?1:0;
    }
   }
  }
  // L / mask 0 => BCH-protected format bits 0x77c4.
  $format=0x77c4;
  $set=static function(int $x,int $y,int $i)use(&$m,$format):void{$m[$y][$x]=($format>>$i)&1;};
  for($i=0;$i<=5;$i++)$set(8,$i,$i);
  $set(8,7,6);$set(8,8,7);$set(7,8,8);
  $m[6][8]=1;$m[8][6]=1;
  for($i=9;$i<15;$i++)$set(14-$i,8,$i);
  for($i=0;$i<8;$i++)$set(self::SIZE-1-$i,8,$i);
  for($i=8;$i<15;$i++)$set(8,self::SIZE-15+$i,$i);
  $m[self::SIZE-8][8]=1;
  $path='';foreach($m as $y=>$row)foreach($row as $x=>$value)if($value)$path.='M'.($x+4).' '.($y+4).'h1v1h-1z';
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 45 45" role="img" aria-label="QR code de la station"><path fill="#fff" d="M0 0h45v45H0z"/><path fill="#103d46" d="'.$path.'"/></svg>';
 }
 private static function multiply(int $x,int $y):int {
  $result=0;for($i=7;$i>=0;$i--){$result=($result<<1)^(($result>>7)*0x11d);$result^=(($y>>$i)&1)*$x;}return $result;
 }
}
