<?php
spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
use App\Services\ClientTraining as Training;
$guide=Training::guide();if(count($guide['modules'])!==20)throw new RuntimeException('Incomplete training guide');
foreach($guide['modules'] as $m)if(count($m)!==5||count($m[2])<3||!str_starts_with($m[4],'/'))throw new RuntimeException('Module incomplete');
$input=['title'=>'Une évolution','description'=>'Besoin','benefit'=>'Bénéfice','scope'=>'Périmètre','status'=>'proposed','priority'=>'high','budget'=>'','timeframe'=>''];
$data=Training::validate($input);if($data['budget']!==null||$data['status']!=='proposed')throw new RuntimeException('Proposal validation invalid');
foreach([['budget'=>'-1'],['budget'=>'1.5'],['title'=>''],['status'=>'automatic-deploy'],['scope'=>str_repeat('x',2001)]] as $bad){try{Training::validate(array_replace($input,$bad));throw new RuntimeException('Invalid proposal accepted');}catch(InvalidArgumentException $e){}}
if(!str_starts_with(Training::guidePdf(),'%PDF-1.4')||!str_starts_with(Training::proposalsPdf([]),'%PDF-1.4'))throw new RuntimeException('PDF export invalid');
echo "Training: 20 complete modules, proposal validation, nullable estimate and both PDF exports OK\n";
