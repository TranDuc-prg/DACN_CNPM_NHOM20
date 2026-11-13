<?php
require_once 'config/db.php';

$order_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
$stmt->bind_param("i",$order_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if(!$order){
    die("Không tìm thấy đơn hàng");
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Hóa đơn</title>

<style>

body{
font-family:Arial;
padding:40px;
}

h1{
text-align:center;
}

table{
width:100%;
border-collapse:collapse;
margin-top:20px;
}

table,th,td{
border:1px solid #ccc;
padding:10px;
}

.total{
font-size:20px;
font-weight:bold;
color:red;
}

</style>

</head>

<body>

<h1>VIET DUC PHARMACY</h1>
<h2 style="text-align:center">HÓA ĐƠN BÁN HÀNG</h2>

<p>Mã đơn: <b>#<?= $order['id'] ?></b></p>
<p>Khách hàng: <?= $order['fullname'] ?></p>
<p>SĐT: <?= $order['phone'] ?></p>
<p>Địa chỉ: <?= $order['address'] ?></p>
<p>Phương thức thanh toán: <?= $order['payment_method'] ?></p>

<hr>

<p class="total">
Tổng tiền: <?= number_format($order['total_amount']) ?> đ
</p>

<br>

<button onclick="window.print()">🖨 In hóa đơn</button>

</body>
</html>