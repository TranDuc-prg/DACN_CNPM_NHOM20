<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json');

require_once 'config/db.php';

$labels = [];
$revenue = [];
$orders = [];


$sql = "
SELECT 
DATE(approved_at) AS day,
SUM(total_amount) AS total
FROM orders
WHERE status='Đã duyệt'
AND approved_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY DATE(approved_at)
ORDER BY day
";

$result = $conn->query($sql);

if(!$result){
    echo json_encode(["error"=>$conn->error]);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $revenue[$row['day']] = (float)$row['total'];
}


/* ===== LẤY SỐ ĐƠN ===== */

$sql2 = "
SELECT 
DATE(created_at) AS day,
COUNT(*) AS total
FROM orders
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY DATE(created_at)
";

$res2 = $conn->query($sql2);

while ($row = $res2->fetch_assoc()) {
    $orders[$row['day']] = (int)$row['total'];
}


/* ===== TẠO DATA 7 NGÀY ===== */

$dataRevenue = [];
$dataOrders = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date('Y-m-d', strtotime("-$i days"));

    $labels[] = date('d/m', strtotime($date));

    $dataRevenue[] = $revenue[$date] ?? 0;
    $dataOrders[] = $orders[$date] ?? 0;
}


/* ===== TRẢ JSON ===== */

echo json_encode([
    "labels"=>$labels,
    "data"=>$dataRevenue,
    "orders"=>$dataOrders
], JSON_UNESCAPED_UNICODE);