
<?php
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config/ai.php';

$key = getenv('GEMINI_API_KEY');

if (!$key && function_exists('apache_getenv')) {
    $key = apache_getenv('GEMINI_API_KEY');
}

if (!$key && isset($_SERVER['GEMINI_API_KEY'])) {
    $key = $_SERVER['GEMINI_API_KEY'];
}

if (!$key && defined('GEMINI_API_KEY')) {
    $key = GEMINI_API_KEY;
}

$model = getenv('GEMINI_MODEL');
if (!$model && defined('GEMINI_MODEL')) {
    $model = GEMINI_MODEL;
}
$model = $model ?: 'gemini-3-flash-preview';

if (trim((string)$key) === '') {
    exit(
        "LOI: Khong doc duoc API key.\n" .
        "Hay kiem tra config/ai.php va httpd.conf.\n"
    );
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
     . $model . ':generateContent?key=' . urlencode(trim($key));

$body = [
    'contents' => [[
        'parts' => [[
            'text' => 'Reply briefly in Vietnamese: Xin chao'
        ]]
    ]]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 45
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "MODEL: $model\n";
echo "HTTP STATUS: $status\n";

if ($response === false) {
    exit("CURL ERROR: $error\n");
}

$data = json_decode($response, true);

if ($status >= 200 && $status < 300) {
    echo "GEMINI: KET NOI THANH CONG\n";
    echo "TRA LOI: ";
    echo $data['candidates'][0]['content']['parts'][0]['text']
        ?? 'Khong co noi dung tra loi';
} else {
    echo "GEMINI: LOI\n";
    echo "CHI TIET: "
        . ($data['error']['message'] ?? 'Khong co chi tiet loi');
}
