<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Endpoint này trả về thông tin cá nhân trong đơn hàng, chỉ nhân viên/quản trị được truy cập.
if (empty($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'staff'], true)) {
    http_response_code(401);
    echo json_encode(['new' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/config/db.php';
$sql = "SELECT id, fullname, phone FROM orders ORDER BY id DESC LIMIT 1";
$res = $conn->query($sql);
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    echo json_encode(['new' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode([
    'new' => true,
    'id' => (int)$row['id'],
    'name' => $row['fullname'],
    'phone' => $row['phone']
], JSON_UNESCAPED_UNICODE);
