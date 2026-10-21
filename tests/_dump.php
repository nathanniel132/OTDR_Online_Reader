<?php

$b = file_get_contents(__DIR__.'/../Doc/kabel1 cbd c1.sor');
$start = (int) ($argv[1] ?? 0);
$end = (int) ($argv[2] ?? 683);
for ($i = $start; $i < $end; $i += 16) {
    $c = substr($b, $i, 16);
    $h = strtoupper(bin2hex($c));
    $h = trim(chunk_split($h, 2, ' '));
    $a = preg_replace('/[^\x20-\x7e]/', '.', $c);
    printf("%6d: %-48s |%s|\n", $i, $h, $a);
}
