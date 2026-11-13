<?php
session_start();
require_once 'config/db.php';

$cat = $_GET['cat'] ?? '';
$sub = $_GET['sub'] ?? '';

// ================= QUERY =================

$sql = "SELECT id, name, image, price_new 
        FROM products 
        WHERE 1=1";

$params = [];
$types = "";

if($cat != ""){
    $sql .= " AND category = ?";
    $params[] = $cat;
    $types .= "s";
}

if($sub != ""){
    $sql .= " AND subcategory LIKE ?";
    $params[] = "%$sub%";
    $types .= "s";
}

$sql .= " ORDER BY id DESC LIMIT 6";

$stmt = $conn->prepare($sql);

if(!$stmt){
    die("SQL Error: ".$conn->error);
}

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<link rel="stylesheet" href="css/load.products.css">

<?php

// ================= HIỂN THỊ =================

if($result && $result->num_rows > 0){

echo '<div class="mega-product-grid">';

while($row = $result->fetch_assoc()){

$id = (int)$row['id'];

$name = htmlspecialchars($row['name']);

$img = !empty($row['image']) 
        ? htmlspecialchars($row['image']) 
        : "no-image.png";

$price = number_format($row['price_new'] ?? 0);

echo "

<a href='chitietsp.php?id=$id' class='product-card-mini'>

<div class='img-box'>

<img src='images/$img' alt='$name'>

</div>

<div class='p-name'>$name</div>

<div class='p-price'>{$price}đ</div>

</a>

";

}

echo "</div>";

}else{

echo "

<div style='padding:20px;text-align:center;color:#888;font-size:14px'>

Chưa có sản phẩm

</div>

";

}

$stmt->close();
$conn->close();
?>