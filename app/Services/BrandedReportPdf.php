<?php
namespace App\Services;

/** A4 landscape vector report, wrapped rows, repeated headers, local QR and wordmark. */
final class BrandedReportPdf
{
    private array $pages=[];
    private string $stream='';
    private float $y=0;
    private array $headers=[];
    private array $widths=[];
    private string $title='';
    private string $qr='';
    private string $subtitle='';
    private const W=841.89;
    private const H=595.28;
    private function color(string $hex): string {return implode(' ',array_map(static fn($s)=>number_format(hexdec($s)/255,4,'.',''),str_split($hex,2)));}
    private function rect(float $x,float $y,float $w,float $h,string $color):void {$this->stream.=$this->color($color)." rg $x ".(self::H-$y-$h)." $w $h re f\n";}
    private function text(float $x,float $y,string $text,float $size=9,string $color='103d46',bool $bold=false):void {
        $text=iconv('UTF-8','Windows-1252//TRANSLIT',$text)?:'';
        $text=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$text);
        $this->stream.=$this->color($color).' rg BT /'.($bold?'F2':'F1')." $size Tf $x ".(self::H-$y)." Td ($text) Tj ET\n";
    }
    private function lines(string $text,float $width,float $size=8):array {
        $limit=max(4,(int)floor(($width-14)/($size*.56)));
        $parts=preg_split('/\R/u',$text)?:[''];$lines=[];
        foreach($parts as $part){$words=preg_split('/\s+/u',trim($part))?:[];$line='';foreach($words as $word){preg_match_all('/.{1,'.$limit.'}/us',$word,$chunks);foreach($chunks[0] as $chunk){$length=preg_match_all('/./us',$line.' '.$chunk);if($line!==''&&$length>$limit){$lines[]=$line;$line=$chunk;}else{$line=trim($line.' '.$chunk);}}} $lines[]=$line;}
        return $lines?:[''];
    }
    private function newPage():void {
        if($this->stream!=='')$this->pages[]=$this->stream;
        $this->stream='';$this->rect(0,0,self::W,78,'103d46');
        // Existing Thiebapower wordmark with an energy mark in its brand colours.
        $this->rect(28,19,27,33,'ffba5e');$this->stream.="0.063 0.239 0.275 rg 44 573.28 m 35 557.28 l 41 557.28 l 37 545.28 l 49 563.28 l 43 563.28 l h f\n";
        $this->text(67,36,'THIEBA',22,'ffffff',true);$this->text(166,36,'POWER',22,'ffba5e',true);
        $this->text(67,55,'VOTRE ÉNERGIE, PARTOUT',8,'b8ded4');
        $this->rect(748,9,62,62,'ffffff');
        preg_match_all('/M(\d+) (\d+)h1v1h-1z/',$this->qr,$cells,PREG_SET_ORDER);
        foreach($cells as $cell)$this->rect(751+(int)$cell[1]*1.25,12+(int)$cell[2]*1.25,1.27,1.27,'103d46');
        $this->text(28,102,$this->title,strlen($this->title)>85?11:17,'103d46',true);
        $this->text(28,120,$this->subtitle,8,'527078');$this->y=136;
    }
    private function tableHeader():void {
        $x=28;$height=0;$lines=[];foreach($this->headers as $i=>$header){$lines[$i]=$this->lines($header,$this->widths[$i],8);$height=max($height,count($lines[$i])*11+15);}
        foreach($lines as $i=>$wrapped){$this->rect($x,$this->y,$this->widths[$i],$height,'0c6471');foreach($wrapped as $j=>$line)$this->text($x+7,$this->y+14+$j*11,$line,8,'ffffff',true);$x+=$this->widths[$i];}
        $this->y+=$height;
    }
    public function render(string $title,array $headers,array $rows,array $cards,string $qrUrl,string $subtitle=''):string {
        if(!$headers||count($headers)>12||count($rows)>1000)throw new \InvalidArgumentException('Dimensions de rapport invalides');
        $this->pages=[];$this->stream='';$this->title=$title;$this->headers=$headers;$this->subtitle=$subtitle?:gmdate('d/m/Y H:i').' UTC · '.count($rows).' lignes';$this->qr=StationQr::svg($qrUrl);
        $weights=[];foreach($headers as $i=>$header){$weight=strlen($header);foreach($rows as $row)$weight=max($weight,min(52,strlen((string)($row[$i]??''))));$weights[]=max(14,$weight);}
        $sum=array_sum($weights);$this->widths=array_map(static fn($n)=>785.89*$n/$sum,$weights);
        if($headers===['Module','Objectif et procédure','Mise en pratique']||$headers===['Proposition','Besoin, bénéfice et périmètre','Suivi et estimation'])$this->widths=[150,420,215.89];
        if($headers===['Étape','Détail','Valeur'])$this->widths=[85,170,530.89];
        $this->newPage();
        $cards=array_slice($cards,0,4);$cw=($cards?785.89/count($cards):0);
        foreach($cards as $i=>$card){$x=28+$i*$cw;$this->rect($x,$this->y,$cw-8,54,'edf5f3');$this->text($x+10,$this->y+18,(string)$card['label'],8,'527078');$this->text($x+10,$this->y+39,(string)$card['value'],14,'103d46',true);}
        if($cards)$this->y+=69;
        $this->tableHeader();
        $compact=$headers===['Étape','Détail','Valeur'];$lineHeight=$compact?10:11;$padding=$compact?8:12;
        foreach($rows as $index=>$row){
            $wrapped=[];$max=1;foreach($headers as $i=>$header){$wrapped[$i]=$this->lines((string)($row[$i]??''),$this->widths[$i]);$max=max($max,count($wrapped[$i]));}
            $fullHeight=$max*$lineHeight+$padding;
            if($fullHeight<350 && $this->y+$fullHeight>self::H-49){$this->newPage();$this->tableHeader();}
            // A long row continues on another page with column headings repeated.
            for($offset=0;$offset<$max;){
                $available=(int)floor((self::H-49-$this->y-$padding)/$lineHeight);
                if($available<1){$this->newPage();$this->tableHeader();continue;}
                $take=min($max-$offset,$available);$height=$take*$lineHeight+$padding;$x=28;
                foreach($wrapped as $i=>$lines){$this->rect($x,$this->y,$this->widths[$i]-.5,$height,$index%2===0?'f1f6f5':'ffffff');foreach(array_slice($lines,$offset,$take) as $j=>$line)$this->text($x+7,$this->y+11+$j*$lineHeight,$line,8);$x+=$this->widths[$i];}
                $this->y+=$height;$offset+=$take;
            }
        }
        $this->pages[]=$this->stream;
        $objects=['','<< /Type /Catalog /Pages 2 0 R >>','','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];
        foreach($this->pages as $i=>$page){
            $this->stream='';$this->rect(28,self::H-35,785.89,.7,'b8ded4');$this->text(28,self::H-19,'THIEBAPOWER · '.gmdate('d/m/Y H:i').' UTC · Document confidentiel',8,'527078');$this->text(740,self::H-19,'Page '.($i+1).'/'.count($this->pages),8,'527078');$page.=$this->stream;
            $id=count($objects);$kids[]=$id;
            $objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($id+1).' 0 R >>';
            $objects[]='<< /Length '.strlen($page).' >>' ."\nstream\n".$page."endstream";
        }
        $objects[2]='<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ',array_map(static fn($id)=>$id.' 0 R',$kids)).'] >>';
        $pdf="%PDF-1.4\n";$offsets=[0];for($i=1;$i<count($objects);$i++){$offsets[]=strlen($pdf);$pdf.="$i 0 obj\n".$objects[$i]."\nendobj\n";}
        $xref=strlen($pdf);$pdf.='xref' ."\n0 ".count($objects)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";
        return $pdf.'trailer' ."\n<< /Size ".count($objects).' /Root 1 0 R >>' ."\nstartxref\n$xref\n%%EOF";
    }
}
