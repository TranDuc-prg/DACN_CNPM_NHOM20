<?php
$start = microtime(true);

// Điền thông tin giống config/db.php của bạn
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'shop_db';

$conn = new mysqli($host, $user, $pass, $db);

$elapsed = (microtime(true) - $start) * 1000;

if ($conn->connect_error) {
    echo "Lỗi kết nối: " . htmlspecialchars($conn->connect_error);
} else {
    echo "Kết nối trực tiếp thành công<br>";
}

echo "Thời gian: " . round($elapsed, 2) . " ms";