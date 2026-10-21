<?php

require __DIR__.'/../vendor/autoload.php';

use App\Services\SorParserService;

$service = new SorParserService;
$res = $service->parse(__DIR__.'/../Doc/kabel1 cbd c1.sor');

echo 'Success: '.($res['success'] ? 'true' : 'false')."\n";
echo 'Supplier: '.$res['summary']['supplier'].' '.$res['summary']['model']."\n";
echo 'Total Distance: '.$res['summary']['totalDistanceKm']." km\n";
echo 'Wavelength: '.$res['summary']['wavelength']." nm\n";
echo 'Pulse Width: '.$res['summary']['pulseWidth']." ns\n";
echo 'IOR: '.$res['summary']['ior']."\n";
echo 'Events count: '.count($res['events'])."\n";
print_r($res['events']);
foreach ($res['events'] as $e) {
    echo "  Event #{$e['eventNumber']} at {$e['distanceKm']} km: {$e['type']}, loss={$e['eventLossDb']} dB, refl={$e['reflectanceDb']} dB\n";
}
echo 'Data Points count: '.count($res['dataPoints']['points'])."\n";
echo "First point: x={$res['dataPoints']['points'][0]['x']} km, y={$res['dataPoints']['points'][0]['y']} dB\n";
echo "Mid point: x={$res['dataPoints']['points'][2385]['x']} km, y={$res['dataPoints']['points'][2385]['y']} dB\n";
echo "Last point: x={$res['dataPoints']['points'][16383]['x']} km, y={$res['dataPoints']['points'][16383]['y']} dB\n";
