<?php

$count = 100000000;
$path = __DIR__ . '/numbers.bin';

$fp = fopen($path, 'wb');
if ($fp === false) {
    fwrite(STDERR, "Failed to open $path for writing\n");
    exit(1);
}

echo "Generating $count random numbers into $path..\n";
$start = microtime(true);

$chunkSize = 100000;
$buffer = '';
for ($i = 0; $i < $count; $i++) {
    $buffer .= pack('V', rand(0, 10000000));

    if (($i + 1) % $chunkSize === 0) {
        fwrite($fp, $buffer);
        $buffer = '';
    }
}
if ($buffer !== '') {
    fwrite($fp, $buffer);
}

fclose($fp);

$elapsed = microtime(true) - $start;
printf("Done. Wrote %d numbers (%d bytes) in %.2fs\n", $count, $count * 4, $elapsed);
