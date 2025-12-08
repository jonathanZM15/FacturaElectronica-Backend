<?php
// Test double decode
$raw = '"[3]"';
echo 'Raw: ' . $raw . PHP_EOL;
$decoded = json_decode($raw, true);
echo 'First decode: ' . var_export($decoded, true) . PHP_EOL;
if (is_string($decoded)) {
    $decoded = json_decode($decoded, true);
    echo 'Second decode: ' . var_export($decoded, true) . PHP_EOL;
}
echo 'Is array: ' . (is_array($decoded) ? 'YES' : 'NO') . PHP_EOL;
echo 'empty: ' . (empty($decoded) ? 'YES' : 'NO') . PHP_EOL;
