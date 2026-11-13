<?php
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xử lý thêm sản phẩm vào giỏ hàng qua AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart_id'])) {
    $product_id = (int)$_POST['add_to_cart_id'];
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += 1;
    } else {
        $_SESSION['cart'][$product_id] = 1;
    }

    $total_count = array_sum($_SESSION['cart']);
    echo json_encode(['status' => 'success', 'cart_count' => $total_count]);
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

$subcategory = isset($_GET['sub']) ? $conn->real_escape_string($_GET['sub']) : '';

$sql = "SELECT * FROM products WHERE category='duoc-my-pham'";
if ($subcategory != '') {
    $sql .= " AND subcategory='$subcategory'";
}
$sql .= " ORDER BY id DESC";
$result = $conn->query($sql);

$subcats = [
    "cham-soc-da-mat" => "Chăm sóc da mặt",
    "kem-chong-nang" => "Kem chống nắng",
    "cham-soc-co-the" => "Chăm sóc cơ thể",
    "tri-mun" => "Trị mụn",
    "duong-trang-da" => "Dưỡng trắng da",
    "chong-lao-hoa" => "Chống lão hóa"
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dược Mỹ Phẩm Cao Cấp | Pharma Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/duocmypham.css?v=<?= filemtime('css/duocmypham.css') ?>">
</head>
<body>

<div class="hero-banner text-center">
    <div class="container">
        <h1>Glow & Beauty</h1>
        <p>Hệ thống dược mỹ phẩm chính hãng nâng niu làn da Việt</p>
    </div>
</div>

<div class="filter-wrapper sticky-top-menu">
    <div class="filter-container">
        <ul class="nav nav-pills">
            <li class="nav-item"><a href="index.php" class="nav-link"><i class="fa-solid fa-house"></i></a></li>
            <li class="nav-item"><a href="?" class="nav-link <?= $subcategory == '' ? 'active' : '' ?>">Tất cả</a></li>
            <?php foreach($subcats as $key => $value): ?>
                <li class="nav-item">
                    <a href="?sub=<?= $key ?>" class="nav-link <?= $subcategory == $key ? 'active' : '' ?>">
                        <?= $value ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>


<div class="container">
    <div class="row g-5">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                    <div class="product-card text-center">
                        <img src="images/<?= $row['image'] ?>" class="product-img" alt="<?= $row['name'] ?>">
                        <div class="flex-grow-1 d-flex flex-column">
                            <h6 class="product-name"><?= $row['name'] ?></h6>
                            <p class="price-new mt-auto"><?= number_format($row['price_new'],0,',','.') ?>đ</p>
                            <a href="chitietsp.php?id=<?= $row['id'] ?>" class="btn btn-action btn-detail">CHI TIẾT</a>
                            <button onclick="buyNow(<?= $row['id'] ?>)" class="btn btn-action btn-buy">MUA NGAY</button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <p>Hiện tại chưa có sản phẩm nào trong mục này.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="newsletter-custom row align-items-center">
        <div class="col-lg-7 mb-4 mb-lg-0">
            <h2>Đăng ký nhận tin khuyến mãi</h2>
            <p>Nhận ngay voucher <b style="color:#ffeb3b">50.000đ</b> và cẩm nang sức khỏe hàng tuần.</p>
        </div>
        <div class="col-lg-5">
            <div class="input-group-custom">
                <input type="email" placeholder="Email của bạn...">
                <button class="btn btn-subscribe">GỬI</button>
            </div>
        </div>
    </div>
</div>

<footer class="footer-new">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-5">
                <h3 style="color: var(--blue-main); font-weight: 800; font-size: 32px;" class="mb-4">
                    <i class="fa-solid fa-square-plus me-2"></i> VIET DUC PHARMACY
                </h3>
                <p>Hệ thống nhà thuốc chuẩn GPP hàng đầu. Chúng tôi cung cấp giải pháp chăm sóc da thông minh và dịch vụ tư vấn tận tâm 24/7.</p>
                <div class="social-links mt-4">
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-6 mb-4">
                <h6>Khám phá</h6>
                <ul class="list-unstyled">
                    <li><a href="#">Sản phẩm mới</a></li>
                    <li><a href="#">Siêu Deal Đón Tết</a></li>
                    <li><a href="#">Góc sức khỏe</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-6 mb-4">
                <h6>Hỗ trợ</h6>
                <ul class="list-unstyled">
                    <li><a href="#">Chính sách vận chuyển</a></li>
                    <li><a href="#">Chính sách đổi trả</a></li>
                    <li><a href="#">Bảo mật thông tin</a></li>
                </ul>
            </div>

            <div class="col-lg-3 mb-4">
                <h6>Liên hệ</h6>
                <a href="tel:18006789" class="hotline-footer d-block mb-3">1800 6789</a>
                <p><i class="fa-solid fa-location-dot me-2"></i> Quận 1, TP.HCM</p>
                <p><i class="fa-solid fa-envelope me-2"></i> hotro@pharmatech.vn</p>
            </div>
        </div>

        <div class="bottom-bar d-flex flex-wrap justify-content-between align-items-center">
            <p class="mb-0">© 2026 <strong>Pharma Tech Solution</strong>. Bản quyền thuộc về Viet Duc Pharmacy.</p>
            <div class="payment-icons">
                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa">
                <img src="https://vnpay.vn/wp-content/uploads/2020/07/Logo-VNPAYQR-update.png" alt="VNPAY">
                <img src="https://dangkywebsitevoibocongthuong.com/wp-content/uploads/2018/06/logo-da-dang-ky-bo-cong-thuong.png" alt="Bộ Công Thương">
            </div>
        </div>
    </div>
</footer>

<script>
function buyNow(productId){
    fetch("duocmypham.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "add_to_cart_id=" + productId
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert("Đã thêm vào giỏ hàng");
            let badge = document.querySelector(".cart-badge");
            if(badge){
                badge.innerText = data.cart_count;
            }
        }
    })
    .catch(err => console.error("Lỗi:", err));
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<a href="cart.php" class="cart-float" style="left: 20px; right: auto;">
    <i class="fas fa-shopping-basket"></i>
    <span class="cart-badge">
        <?= $cart_count ?>
    </span>
</a>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>