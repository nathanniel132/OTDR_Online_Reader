<?php

$b = file_get_contents('D:/Kerjaan/Hasil OTDR Kabel/Kabel CBD/KABEL-2/kabel2 cbd c1.sor');

// Let's dump all block headers and sizes
$offset = 0;
// Read Map
$null = strpos($b, "\0", $offset);
$mapName = substr($b, $offset, $null - $offset);
$offset = $null + 1;
$mapHdr = unpack('vversion/Vsize/vnumBlocks', substr($b, $offset, 8));
$offset += 8;

echo "Map version: {$mapHdr['version']}, size: {$mapHdr['size']}, numBlocks: {$mapHdr['numBlocks']}\n";
$blocks = [];
$curOff = $mapHdr['size'];
for ($i = 0; $i < $mapHdr['numBlocks'] - 1; $i++) {
    $null = strpos($b, "\0", $offset);
    $bId = substr($b, $offset, $null - $offset);
    $offset = $null + 1;
    $bHdr = unpack('vversion/Vsize', substr($b, $offset, 6));
    $offset += 6;
    $blocks[$bId] = [
        'name' => $bId,
        'version' => $bHdr['version'],
        'size' => $bHdr['size'],
        'offset' => $curOff,
    ];
    $curOff += $bHdr['size'];
}
print_r($blocks);

// Now dump raw content of FxdParams
$fxdOff = $blocks['FxdParams']['offset'];
$fxdSize = $blocks['FxdParams']['size'];
echo "\n=== FxdParams hex ($fxdOff, $fxdSize) ===\n";
for ($i = $fxdOff; $i < $fxdOff + $fxdSize; $i += 16) {
    $c = substr($b, $i, min(16, $fxdOff + $fxdSize - $i));
    printf("%6d: %-48s |%s|\n", $i, trim(chunk_split(strtoupper(bin2hex($c)), 2, ' ')), preg_replace('/[^\x20-\x7e]/', '.', $c));
}
