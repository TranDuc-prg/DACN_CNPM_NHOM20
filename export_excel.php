<?php
require_once 'config/db.php';

$view = $_GET['view'] ?? 'products';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=".$view.".xls");

echo "<table border='1'>";

if($view == "orders"){

echo "<tr>
<th>ID</th>
<th>Khách</th>
<th>SĐT</th>
<th>Tổng tiền</th>
<th>Trạng thái</th>
</tr>";

$res = $conn->query("SELECT * FROM orders");

while($row = $res->fetch_assoc()){

echo "<tr>
<td>{$row['id']}</td>
<td>{$row['fullname']}</td>
<td>{$row['phone']}</td>
<td>{$row['total_amount']}</td>
<td>{$row['status']}</td>
</tr>";

}

}

elseif($view == "customers"){

echo "<tr>
<th>Tên</th>
<th>SĐT</th>
<th>Địa chỉ</th>
<th>Số đơn</th>
<th>Tổng chi</th>
</tr>";

$res = $conn->query("
SELECT fullname,phone,address,
COUNT(*) as total_orders,
SUM(total_amount) as total_spent
FROM orders
GROUP BY phone
");

while($row = $res->fetch_assoc()){

echo "<tr>
<td>{$row['fullname']}</td>
<td>{$row['phone']}</td>
<td>{$row['address']}</td>
<td>{$row['total_orders']}</td>
<td>{$row['total_spent']}</td>
</tr>";

}

}

elseif($view == "newsletter"){

echo "<tr>
<th>Email</th>
<th>Ngày tạo</th>
</tr>";

$res = $conn->query("SELECT * FROM newsletter");

while($row = $res->fetch_assoc()){

echo "<tr>
<td>{$row['email']}</td>
<td>{$row['created_at']}</td>
</tr>";

}

}

else{

echo "<tr>
<th>ID</th>
<th>Tên thuốc</th>
<th>Danh mục</th>
<th>Giá</th>
</tr>";

$res = $conn->query("SELECT * FROM products");

while($row = $res->fetch_assoc()){

echo "<tr>
<td>{$row['id']}</td>
<td>{$row['name']}</td>
<td>{$row['category']}</td>
<td>{$row['price_new']}</td>
</tr>";

}

}

echo "</table>";