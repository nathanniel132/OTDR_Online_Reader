<?php

$b = file_get_contents('D:/Kerjaan/Hasil OTDR Kabel/Kabel CBD/KABEL-2/kabel2 cbd c1.sor');
$dpOff = 680 + 8 + 12;
$allPts = unpack('v*', substr($b, $dpOff, 16384 * 2));

// Let's print points every 100 points
for ($i = 1; $i <= 16384; $i += 200) {
    printf("index %5d: raw = %5d\n", $i, $allPts[$i]);
}
