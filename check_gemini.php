<?php
header('Content-Type: text/plain; charset=utf-8');

$key = getenv('GEMINI_API_KEY');

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "GEMINI_API_KEY: "
    . (($key !== false && trim($key) !== '') ? 'DA NHAN' : 'CHUA NHAN')
    . PHP_EOL;
echo "cURL: "
    . (function_exists('curl_init') ? 'DA BAT' : 'CHUA BAT')
    . PHP_EOL;