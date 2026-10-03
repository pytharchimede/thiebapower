<?php
namespace App\Controllers;
use App\Core\App;
use App\Services\Auth;
use App\Services\BrandedReportPdf;
use App\Services\Audit;
final class ReportController
{
    public function qr():void
    {
        Auth::requirePermission('reports.export');header('Content-Type: image/svg+xml');header('Cache-Control: no-store');
        echo \App\Services\StationQr::svg(rtrim(App::env('APP_URL'),'/').'/admin');
    }
    public function pdf():void
    {
        Auth::requirePermission('reports.export',true);
        if(strlen((string)($_POST['report']??''))>2000000){http_response_code(413);return;}
        $data=json_decode((string)($_POST['report']??''),true);
        if(!is_array($data)||!is_array($data['headers']??null)||!is_array($data['rows']??null)||count($data['headers'])>12||count($data['rows'])>1000){http_response_code(422);return;}
        $clean=static function($value):string {if(!is_string($value)||strlen($value)>2000)throw new \InvalidArgumentException();return $value;};
        try {
            $title=$clean($data['title']??'Export');$headers=array_map($clean,$data['headers']);foreach($headers as $h){if(strlen($h)>120)throw new \InvalidArgumentException();}if(strlen($title)>150)throw new \InvalidArgumentException();$rows=[];
            foreach($data['rows'] as $row){if(!is_array($row)||count($row)!==count($headers))throw new \InvalidArgumentException();$rows[]=array_map($clean,$row);}
            $cards=[['label'=>'Lignes exportées','value'=>(string)count($rows)],['label'=>'Périmètre','value'=>'Sélection affichée'],['label'=>'Édité le','value'=>gmdate('d/m/Y')]];
            $cards=[['label'=>'Lignes exportées','value'=>(string)count($rows)]];
            $moneyColumns=[];
            foreach($headers as $i=>$header){
                $values=array_map(static fn($r)=>$r[$i],$rows);
                if($values && count(array_filter($values,static fn($v)=>preg_match('/^\s*[0-9][0-9 \x{00a0}\x{202f}]* FCFA(?:\s|$)/u',$v)))===count($values)){
                    $total=0;foreach($values as $value){preg_match('/^\s*([0-9][0-9 \x{00a0}\x{202f}]*) FCFA/u',$value,$m);$total+=(int)preg_replace('/[^0-9]/','',$m[1]);}
                    $cards[]=['label'=>'Total · '.$header,'value'=>number_format($total,0,',',' ').' FCFA'];
                    if(count($cards)===4)break;
                }
            }
            if(count($cards)<2)$cards[]=['label'=>'Périmètre','value'=>'Sélection affichée'];
            $bytes=(new BrandedReportPdf())->render($title,$headers,$rows,$cards,rtrim(App::env('APP_URL'),'/').'/admin');
        } catch(\InvalidArgumentException $e){http_response_code(422);echo 'Rapport invalide';return;}
        Audit::event('report.exported','report',null,['rows'=>count($rows)]);
        header('Content-Type: application/pdf');header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="thiebapower-'.gmdate('Y-m-d').'.pdf"');echo $bytes;
    }
}
