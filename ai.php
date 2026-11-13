
<?php
$key = getenv('GEMINI_API_KEY');

if (!$key && function_exists('apache_getenv')) {
    $key = apache_getenv('GEMINI_API_KEY');
}

if (!$key && isset($_SERVER['GEMINI_API_KEY'])) {
    $key = $_SERVER['GEMINI_API_KEY'];
}

$model = getenv('GEMINI_MODEL');

if (!$model && function_exists('apache_getenv')) {
    $model = apache_getenv('GEMINI_MODEL');
}

if (!$model && isset($_SERVER['GEMINI_MODEL'])) {
    $model = $_SERVER['GEMINI_MODEL'];
}

define('GEMINI_API_KEY', trim((string)($key ?: '')));
define(
    'GEMINI_MODEL',
    trim((string)($model ?: 'gemini-3-flash-preview'))
);
define(
    'GEMINI_API_BASE',
    'https://generativelanguage.googleapis.com/v1beta/models/'
);
