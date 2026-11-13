
<?php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'shop_db_test';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu.');
}

$conn->set_charset('utf8mb4');

?>
