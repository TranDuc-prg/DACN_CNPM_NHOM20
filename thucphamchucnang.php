<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// XỬ LÝ THÊM VÀO GIỎ HÀNG
if(isset($_POST['add_to_cart_id'])){
    $id = (int)$_POST['add_to_cart_id'];

    if(!isset($_SESSION['cart'])){
        $_SESSION['cart'] = [];
    }

    if(isset($_SESSION['cart'][$id])){
        $_SESSION['cart'][$id] += 1;
    } else {
        $_SESSION['cart'][$id] = 1;
    }

    echo "success";
    exit();
}
// 1. CẤU HÌNH
$category = 'thuc-pham-chuc-nang';
$subcategory = $_GET['sub'] ?? '';
$search = trim($_GET['search'] ?? '');

$sub_cats = [
    'vitamin' => 'Vitamin & Khoáng chất',
    'mien-dich' => 'Miễn dịch - Đề kháng',
    'than-kinh-nao' => 'Thần kinh não',
    'tieu-hoa' => 'Hỗ trợ tiêu hoá'
];

$section_banners = [
    'vitamin' => 'https://prod-cdn.pharmacity.io/e-com/images/landing-pages/20260305093135-0-1376x300-5.png?versionId=FhSNUb7oPGh8hPgTGW4VCT0SkHEAN_n0',
    'mien-dich' => 'https://prod-cdn.pharmacity.io/e-com/images/landing-pages/20260305094622-0-1376x300.png',
    'than-kinh-nao' => 'https://prod-cdn.pharmacity.io/e-com/images/landing-pages/20260305094428-0-1376x300-3.png',
    'tieu-hoa' => 'https://prod-cdn.pharmacity.io/e-com/images/landing-pages/20260305094833-0-1376x300-2.png'
];

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viet Duc Pharmacy | Chữ To Rõ Nét</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/thucphamchucnang.css?v=<?= time() ?>">
  
</head>
<body>

<header class="tet-header text-center">
    <div class="container">
        <h1 class="script-font">Giáp Tết chăm tròn</h1>
        <h2 style="font-size: 38px; font-weight: 800;" class="text-warning mb-4">THỰC PHẨM CHỨC NĂNG</h2>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <form action="" method="GET">
                    <div class="input-group shadow-lg rounded-pill overflow-hidden">
                        <input type="text" name="search" class="form-control border-0" placeholder="Bạn tìm thuốc gì?" value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-dark btn-search px-5">TÌM KIẾM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

<div class="nav-scroller">
    <div class="container d-flex overflow-auto">
        <a href="index.php" class="filter-pill">Trang chủ</a>
        <?php foreach($sub_cats as $key => $name): ?>
            <a href="#section-<?= $key ?>" class="filter-pill"><?= $name ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="container mt-5">
    <div class="mb-5 shadow-lg" style="border-radius: 40px; overflow: hidden;">
        <img src="https://production-cdn.pharmacity.io/digital/795x0/plain/e-com/images/banners/20260304070334-0-780x324.png?versionId=gpApOrkreCTTzininssI5CWmqG9nw3Pz" class="w-100 img-fluid">
    </div>

    <?php foreach($sub_cats as $key => $name): 
        $sql_sec = "SELECT * FROM products WHERE category = ? AND subcategory = ? ";
        if($search) $sql_sec .= "AND name LIKE ? ";
        $sql_sec .= "ORDER BY id DESC LIMIT 8"; 
        
        $stmt_sec = $conn->prepare($sql_sec);
        if($search) {
            $s_term = "%$search%";
            $stmt_sec->bind_param("sss", $category, $key, $s_term);
        } else {
            $stmt_sec->bind_param("ss", $category, $key);
        }
        $stmt_sec->execute();
        $res_sec = $stmt_sec->get_result();
    ?>
        <div id="section-<?= $key ?>" class="mt-5 pt-4">
            <img src="<?= $section_banners[$key] ?>" class="w-100 rounded-5 mb-4 shadow-sm border">
            
            <div class="d-flex justify-content-between align-items-end mb-4 mt-2">
                <h3 class="section-title mb-0"><?= $name ?></h3>
                <a href="product.php?cat=<?= $category ?>&sub=<?= $key ?>" class="text-danger fw-bold fs-3 text-decoration-none">
                    Xem tất cả <i class="fas fa-chevron-right ms-2"></i>
                </a>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-5">
                <?php while($row = $res_sec->fetch_assoc()): ?>
                <div class="col">
                    <div class="product-card">
                        <a href="chitietsp.php?id=<?= $row['id'] ?>" class="text-decoration-none">
                            <div class="img-box">
                                <img src="images/<?= $row['image'] ?>" alt="<?= $row['name'] ?>" loading="lazy">
                            </div>
                        </a>
                        
                        <a href="chitietsp.php?id=<?= $row['id'] ?>" class="text-decoration-none">
                            <div class="product-title"><?= $row['name'] ?></div>
                        </a>
                       <div class="text-center mb-3">
    <a href="chitietsp.php?id=<?= $row['id'] ?>" class="text-decoration-none fw-bold" style="color: #d63031; font-size: 22px;">
        Chi tiết sản phẩm <i class="fas fa-circle-chevron-right ms-1" style="font-size: 22px; vertical-align: middle;"></i>
    </a>
</div>

                        <div class="price-new"><?= number_format($row['price_new'], 0, ',', '.') ?>đ</div>
                        <button onclick="addToCart(<?= $row['id'] ?>)" class="btn-add">
                            MUA NGAY
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<a href="cart.php" class="cart-float" style="left: 20px; right: auto;">
    <i class="fas fa-shopping-basket" style="font-size: 40px;"></i>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark fs-5">
        <?= $cart_count ?>
    </span>
</a>

<script>
    function addToCart(id) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'add_to_cart_id=' + id
    })
    .then(res => res.text())
    .then(() => {
        alert("Đã thêm vào giỏ hàng");
        location.reload();
    });
}
</script>

<section class="newsletter-section mt-5">
<div class="container">
<div class="newsletter-box d-flex flex-wrap align-items-center justify-content-between">

<div class="newsletter-text">
<h2 class="fw-bold mb-1">Đăng ký nhận tin khuyến mãi</h2>
<p class="mb-0 fs-5">
Nhận ngay voucher 
<span class="text-warning fw-bold">50.000đ</span>
</p>
</div>

<div class="newsletter-form mt-3 mt-lg-0">

<form id="newsletterForm" class="d-flex">

<input 
type="email" 
name="email"
class="form-control"
placeholder="Email của bạn..."
required
style="width:300px;height:60px;border-radius:15px 0 0 15px;"
>

<button 
class="btn btn-warning fw-bold px-4"
type="submit"
style="height:60px;border-radius:0 15px 15px 0;"
>
ĐĂNG KÝ
</button>

</form>

</div>

</div>
</div>
</section>

<footer class="main-footer py-5 mt-5 bg-white border-top">

<div class="container">

<div class="row g-4">

<div class="col-lg-4">

<h2 class="text-primary fw-bold mb-3">
<i class="fas fa-clinic-medical me-2"></i>
VIET DUC PHARMACY
</h2>

<p class="text-muted">
Hệ thống nhà thuốc chuẩn GPP hàng đầu Việt Nam. 
Cam kết cung cấp dược phẩm chính hãng và tư vấn sức khỏe 24/7.
</p>

<div class="d-flex gap-3 mt-3">

<a href="#" class="social-icon">
<i class="fab fa-facebook-f"></i>
</a>

<a href="#" class="social-icon">
<i class="fab fa-youtube"></i>
</a>

<a href="#" class="social-icon">
<i class="fab fa-zalo"></i>
</a>

</div>

</div>

<div class="col-6 col-lg-2">

<h5 class="fw-bold mb-3">KHÁM PHÁ</h5>

<ul class="list-unstyled footer-links">

<li><a href="#">Sản phẩm mới</a></li>
<li><a href="#">Khuyến mãi</a></li>
<li><a href="#">Góc sức khỏe</a></li>

</ul>

</div>

<div class="col-6 col-lg-3">

<h5 class="fw-bold mb-3">HỖ TRỢ</h5>

<ul class="list-unstyled footer-links">

<li><a href="#">Chính sách vận chuyển</a></li>
<li><a href="#">Đổi trả sản phẩm</a></li>
<li><a href="#">Bảo mật thông tin</a></li>

</ul>

</div>

<div class="col-lg-3">

<h5 class="fw-bold mb-3">LIÊN HỆ</h5>

<p class="fw-bold text-primary fs-4 mb-1">
1800 6789
</p>

<p class="text-muted">
Tổng đài miễn cước
</p>

<p class="text-muted">
<i class="fas fa-map-marker-alt me-2"></i>
123 Đường ABC, TP.HCM
</p>

<p class="text-muted">
<i class="fas fa-envelope me-2"></i>
support@vietducpharmacy.vn
</p>

</div>

</div>

<hr class="my-4">

<div class="text-center text-muted small">

© 2026 Viet Duc Pharmacy. All Rights Reserved.

</div>

</div>

</footer>

<script>

const form = document.getElementById("newsletterForm");

if(form){

form.addEventListener("submit", function(e){

e.preventDefault();

const email = form.querySelector("input[name='email']").value;
const btn = form.querySelector("button");

btn.disabled = true;
btn.innerHTML = "Đang gửi...";

fetch("subscribe.php",{
method:"POST",
headers:{
"Content-Type":"application/x-www-form-urlencoded"
},
body:"email="+encodeURIComponent(email)
})

.then(res=>res.text())
.then(data=>{

if(data.trim()=="success"){
alert("🎉 Đăng ký nhận khuyến mãi thành công!");
form.reset();
}
else if(data.trim()=="exists"){
alert("Email này đã đăng ký rồi.");
}
else{
alert("Có lỗi xảy ra.");
}

})

.catch(()=>{
alert("Không thể kết nối server.");
})

.finally(()=>{
btn.disabled=false;
btn.innerHTML="ĐĂNG KÝ";
});

});

}

</script>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>