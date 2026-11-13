<?php
// BẮT BUỘC đặt lên đầu file
session_start();

// Khách được xem sản phẩm; chỉ người đăng nhập mới được mua hàng
$is_logged_in = !empty($_SESSION['fullname']);
$login_url = 'login.php?redirect=' . rawurlencode($_SERVER['REQUEST_URI'] ?? 'index.php');

// 1. Kết nối Database
require_once 'config/db.php';
$cat = isset($_GET['cat']) ? $conn->real_escape_string($_GET['cat']) : '';
$sub = isset($_GET['sub']) ? $conn->real_escape_string($_GET['sub']) : '';

$where = "1";

if($cat && $cat != 'all'){
    $where .= " AND category='$cat'";
}

if($sub){
    $where .= " AND subcategory='$sub'";
}

$sql_products = "SELECT * FROM products
                 WHERE $where
                 ORDER BY id DESC
                 LIMIT 20";
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database!");
}

// 2. LẤY SỐ LƯỢNG GIỎ HÀNG
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;


// ============================
// 3. LẤY SẢN PHẨM SIÊU DEAL
// ============================

$sql_deal = "SELECT * FROM products 
             WHERE is_super_deal = 1 
             ORDER BY id DESC 
             LIMIT 5";

$result_deal = $conn->query($sql_deal);

if(!$result_deal){
    $result_deal = null;
}


// ============================
// 4. LẤY BEST SELLER
// ============================

$sql_best = "SELECT * FROM products 
             WHERE is_featured = 1 
             ORDER BY id DESC 
             LIMIT 5";

$result_best = $conn->query($sql_best);

// ============================
// 4.1 SẢN PHẨM CHO MEGA MENU
// ============================

$sql_mega = "SELECT id,name,image,price_new 
             FROM products
             ORDER BY RAND()
             LIMIT 4";

$result_mega = $conn->query($sql_mega);

if(!$result_mega){
    $result_mega = false;
}

// ============================
// 5. MENU DANH MỤC
// ============================

$nav_items = [

[
'name'=>'Thực phẩm chức năng',
'link'=>'product.php?cat=thuc-pham-chuc-nang',
'icon'=>'fa-mortar-pestle',

'subs'=>[
['title'=>'Vitamin & Khoáng chất','sub'=>'vitamin','icon'=>'fa-capsules'],
['title'=>'Hỗ trợ miễn dịch','sub'=>'mien-dich','icon'=>'fa-shield-virus'],
['title'=>'Hỗ trợ tiêu hóa','sub'=>'tieu-hoa','icon'=>'fa-apple-alt'],
['title'=>'Thần kinh não','sub'=>'than-kinh-nao','icon'=>'fa-brain']
]
],

[
'name'=>'Dược mỹ phẩm',
'link'=>'product.php?cat=duoc-my-pham',
'icon'=>'fa-sparkles',

'subs'=>[
['title'=>'Chăm sóc da mặt','sub'=>'cham-soc-da-mat','icon'=>'fa-face-smile'],
['title'=>'Kem chống nắng','sub'=>'kem-chong-nang','icon'=>'fa-sun'],
['title'=>'Chăm sóc cơ thể','sub'=>'cham-soc-co-the','icon'=>'fa-spa'],
['title'=>'Trị mụn','sub'=>'tri-mun','icon'=>'fa-bacteria'],
['title'=>'Dưỡng trắng da','sub'=>'duong-trang-da','icon'=>'fa-star'],
['title'=>'Chống lão hóa','sub'=>'chong-lao-hoa','icon'=>'fa-clock']
]
],

[
'name'=>'Thuốc',
'link'=>'product.php?cat=thuoc',
'icon'=>'fa-pills',

'subs'=>[
['title'=>'Giảm đau - Hạ sốt','sub'=>'giam-dau-ha-sot','icon'=>'fa-tablets'],
['title'=>'Thuốc ho','sub'=>'thuoc-ho','icon'=>'fa-lungs'],
['title'=>'Dị ứng','sub'=>'di-ung','icon'=>'fa-allergies'],
['title'=>'Dạ dày','sub'=>'da-day','icon'=>'fa-stomach'],
['title'=>'Cảm cúm','sub'=>'cam-cum','icon'=>'fa-head-side-cough'],
['title'=>'Thuốc bổ','sub'=>'thuoc-bo','icon'=>'fa-capsules']
]
],

[
'name'=>'Thiết bị y tế',
'link'=>'product.php?cat=thiet-bi-y-te',
'icon'=>'fa-stethoscope',

'subs'=>[
['title'=>'Máy đo huyết áp','sub'=>'may-do-huyet-ap','icon'=>'fa-heartbeat'],
['title'=>'Nhiệt kế','sub'=>'nhiet-ke','icon'=>'fa-thermometer-half'],
['title'=>'Máy đo đường huyết','sub'=>'may-do-duong-huyet','icon'=>'fa-tint'],
['title'=>'Máy xông mũi họng','sub'=>'may-xong-mui-hong','icon'=>'fa-wind'],
['title'=>'Máy tạo oxy','sub'=>'may-tao-oxy','icon'=>'fa-lungs'],
['title'=>'Cân sức khỏe','sub'=>'can-suc-khoe','icon'=>'fa-weight'],
['title'=>'Dụng cụ sơ cứu','sub'=>'dung-cu-so-cuu','icon'=>'fa-first-aid'],
['title'=>'Khẩu trang y tế','sub'=>'khau-trang-y-te','icon'=>'fa-head-side-mask']
]
],

['name'=>'Tất cả sản phẩm','link'=>'product.php?cat=all','icon'=>'fa-th-large'],
['name'=>'Góc sức khỏe','link'=>'goc-suc-khoe.php','icon'=>'fa-heart-pulse']

];



// ============================
// 6. VIDEO YOUTUBE
// ============================

$videos = [
    ['id' => 'xl1gwzUbrLc', 'title' => 'Chăm sóc sức khỏe mùa đông đúng cách'],
    ['id' => 'iL0wdc42yVQ', 'title' => 'Cách phòng cúm cho trẻ tại nhà'],
    ['id' => 'tvHEfX0rU2c', 'title' => '8 cách sống khỏe mỗi ngày'],
    ['id' => 'HC8WihIcKg0', 'title' => 'Lưu ý khi dùng thuốc cảm cúm'],
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viet Duc Pharmacy – Hệ Thống Nhà Thuốc Hàng Đầu</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/index.css?v=<?= filemtime('css/index.css') ?>">
    
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="top-bar sticky-top">
<div class="container-full d-flex align-items-center justify-content-between">

<!-- LOGO -->
<a href="index.php" class="logo text-white fw-bold text-decoration-none">
    <i class="fas fa-clinic-medical me-2"></i>
    VIET DUC <span class="fw-light">PHARMACY</span>
</a>

<!-- SEARCH -->
<form action="product.php" method="GET" class="search-box d-none d-lg-flex flex-grow-1 mx-5 align-items-center">
    <i class="fas fa-search text-muted me-3"></i>
    <input type="text"
           name="search"
           class="form-control border-0 shadow-none p-0"
           placeholder="Tìm kiếm thuốc, thực phẩm chức năng...">
</form>

<!-- RIGHT -->
<div class="d-flex align-items-center gap-4 text-white">

<a href="track-order.php"
class="text-white text-decoration-none fw-bold small">
<i class="fas fa-search-location me-1"></i>
Tra cứu đơn
</a>

<!-- CART -->
<a href="cart.php"
   class="cart-btn position-relative text-white">

<i class="fas fa-shopping-cart fs-5"></i>

<span id="cart-badge"
      class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
      <?= $cart_count ?>
</span>
</a>


</a>

<?php if(isset($_SESSION['fullname'])): ?>

<span class="fw-bold small">
<i class="fas fa-user-circle me-1"></i>
<?= $_SESSION['fullname'] ?>
</span>

<a href="logout.php"
   class="btn btn-sm btn-light fw-bold">
Đăng xuất
</a>

<?php else: ?>

<a href="login.php"
   class="btn btn-warning fw-bold px-3">
<i class="fas fa-user me-1"></i> Đăng nhập
</a>

<?php endif; ?>

</div>

</div>
</header>


<!-- ================= MENU ================= -->
<ul class="nav-menu">


<?php foreach($nav_items as $item): ?>

<?php if(!empty($item['subs'])): ?>

<li class="mega-item has-mega">

<a href="javascript:void(0)">
<i class="fas <?= $item['icon'] ?>"></i>
<?= $item['name'] ?>
</a>

<div class="mega-menu">

<?php
$subs = $item['subs'];
$total = count($subs);
$half = ceil($total / 2);
$cat_slug = str_replace('product.php?cat=','',$item['link']);
?>

<!-- CỘT 1 -->
<div class="mega-col">

<h4>Nhóm 1</h4>

<?php for($i=0; $i<$half; $i++): ?>
<?php $sub = $subs[$i]; ?>

<a href="product.php?cat=<?= $cat_slug ?>&sub=<?= $sub['sub'] ?>"
class="menu-load"
data-cat="<?= $cat_slug ?>"
data-sub="<?= $sub['sub'] ?>">
<i class="fas <?= $sub['icon'] ?>"></i>
<?= $sub['title'] ?>
</a>

<?php endfor; ?>

</div>

<!-- CỘT 2 -->
<div class="mega-col">

<h4>Nhóm 2</h4>

<?php for($i=$half; $i<$total; $i++): ?>
<?php $sub = $subs[$i]; ?>

<a href="product.php?cat=<?= $cat_slug ?>&sub=<?= $sub['sub'] ?>"
class="menu-load"
data-cat="<?= $cat_slug ?>"
data-sub="<?= $sub['sub'] ?>">

<i class="fas <?= $sub['icon'] ?>"></i>
<?= $sub['title'] ?>

</a>

<?php endfor; ?>

</div>

<!-- CỘT SẢN PHẨM -->
<div class="mega-col mega-products">

<h4>Sản phẩm</h4>

<div class="mega-products-list">

<div style="padding:10px;font-size:13px;color:#777">
Chọn danh mục để xem sản phẩm
</div>

</div>

</div>

</div>
</li>

<?php else: ?>

<li>
<a href="<?= $item['link'] ?>">
<i class="fas <?= $item['icon'] ?>"></i>
<?= $item['name'] ?>
</a>
</li>

<?php endif; ?>

<?php endforeach; ?>

</ul>
<main class="container-full mt-4">
<div class="row g-3 mb-5 align-items-stretch">


<div class="col-lg-7">
    <a href="thucphamchucnang.php">
        <img src="https://production-cdn.pharmacity.io/digital/795x0/plain/e-com/images/banners/20260304070334-0-780x324.png?versionId=gpApOrkreCTTzininssI5CWmqG9nw3Pz"
             class="w-100 h-100 rounded-4 shadow-sm banner-left banner-hover">
    </a>
</div>




<div class="col-lg-5 d-none d-lg-flex flex-column gap-3">

<a href="duocmypham.php" target="_blank">
<img src="https://production-cdn.pharmacity.io/digital/795x0/plain/e-com/images/banners/20260305092653-0-Desktop.png?versionId=EDQHqecKRzdoCBfaA0V44JRxb9m9Kmls"
class="w-100 rounded-4 shadow-sm banner-right">
</a>

<img src="https://tse3.mm.bing.net/th/id/OIP.PgK9g3dlbZhlAjOBxbxTWwHaDX?pid=Api&P=0&h=220"
     class="w-100 rounded-4 shadow-sm banner-right" style="cursor: pointer;">
</div>

</div>
</main>

  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
<h2 class="text-danger mb-0">🧧 SIÊU DEAL</h2>

<div id="countdownDeal" class="deal-timer">
⏰ 02:14:35
</div>
</div>
        <a href="tat-ca-san-pham-sieu-deal.php"
           class="fw-bold text-decoration-none text-primary">
            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4">
        <?php if($result_deal && $result_deal->num_rows > 0): ?>
            <?php while($row = $result_deal->fetch_assoc()): ?>
                <div class="col">
                    <div class="card shadow-sm h-100 text-center p-3">

                        <div class="product-img">
                            <img src="images/<?= htmlspecialchars($row['image']) ?>"
                                 alt="<?= htmlspecialchars($row['name']) ?>">
                        </div>

                        <h6 class="product-title">
                            <?= htmlspecialchars($row['name']) ?>
                        </h6>

                        <div class="mb-2">
                            <span class="text-danger fw-bold">
                                <?= number_format($row['price_new']) ?>đ
                            </span>

                            <?php if($row['price_old'] > $row['price_new']): ?>
                                <small class="text-muted text-decoration-line-through ms-2">
                                    <?= number_format($row['price_old']) ?>đ
                                </small>
                            <?php endif; ?>
                        </div>

                        <a href="chitietsp.php?id=<?= $row['id'] ?>"
                           class="btn btn-outline-danger w-100 rounded-pill fw-bold mb-2">
                           <i class="fas fa-eye me-1"></i> XEM CHI TIẾT
                        </a>

                        <button class="btn btn-danger w-100 rounded-pill fw-bold btn-add-cart"
                                data-id="<?= $row['id'] ?>"
                                data-requires-login="<?= $is_logged_in ? '0' : '1' ?>"
                                data-login-url="<?= htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-cart-plus me-1"></i> THÊM VÀO GIỎ
                        </button>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center">Không có sản phẩm.</p>
        <?php endif; ?>
    </div>
</section>



<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
<h2 class="text-dark mb-0">🔥 BEST SELLER</h2>

<div id="countdownBest" class="deal-timer-dark">
⏰ 02:14:35
</div>
</div>
        <a href="tat-ca-best-seller.php"
           class="fw-bold text-decoration-none text-dark">
            Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4">
        <?php if($result_best && $result_best->num_rows > 0): ?>
            <?php while($item = $result_best->fetch_assoc()): ?>
                <div class="col">
                    <div class="card shadow-sm h-100 text-center p-3">

                        <div class="product-img">
                            <img src="images/<?= htmlspecialchars($item['image']) ?>"
                                 alt="<?= htmlspecialchars($item['name']) ?>">
                        </div>

                        <h6 class="product-title">
                            <?= htmlspecialchars($item['name']) ?>
                        </h6>

                        <div class="mb-2">
                            <span class="text-dark fw-bold">
                                <?= number_format($item['price_new']) ?>đ
                            </span>
                        </div>

                        <a href="chitietsp.php?id=<?= $item['id'] ?>"
                           class="btn btn-outline-dark w-100 rounded-pill fw-bold mb-2">
                           <i class="fas fa-eye me-1"></i> XEM CHI TIẾT
                        </a>

                        <button class="btn btn-dark w-100 rounded-pill fw-bold btn-add-cart"
                                data-id="<?= $item['id'] ?>"
                                data-requires-login="<?= $is_logged_in ? '0' : '1' ?>"
                                data-login-url="<?= htmlspecialchars($login_url, ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-cart-plus me-1"></i> THÊM VÀO GIỎ
                        </button>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center">Không có sản phẩm.</p>
        <?php endif; ?>
    </div>
</section>



<!-- ================= AJAX ADD TO CART ================= -->
<script>
document.querySelectorAll('.btn-add-cart').forEach(button => {
    button.addEventListener('click', function() {

        // Chặn khách chưa đăng nhập trước khi gửi yêu cầu thêm giỏ hàng
        if (this.dataset.requiresLogin === '1') {
            const loginModalEl = document.getElementById('loginRequiredModal');
            const loginNowBtn = document.getElementById('loginRequiredGoLogin');

            if (loginNowBtn) {
                loginNowBtn.href = this.dataset.loginUrl || 'login.php';
            }

            if (loginModalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(loginModalEl).show();
            } else {
                window.location.href = this.dataset.loginUrl || 'login.php';
            }
            return;
        }

        let productId = this.dataset.id;

        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'add_to_cart_id=' + productId
        })
        .then(response => response.text())
        .then(data => {

            // Cập nhật số lượng trên icon giỏ hàng
            let cartCount = document.getElementById('cart-badge');
            if(cartCount){
                cartCount.innerText = data;
            }

            // Đổi trạng thái nút
            this.innerHTML = '<i class="fas fa-check"></i> ĐÃ THÊM';
            this.classList.remove('btn-danger','btn-dark');
            this.classList.add('btn-success');

            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-cart-plus me-1"></i> THÊM VÀO GIỎ';
                this.classList.remove('btn-success');
                if(this.classList.contains('btn-danger') === false &&
                   this.classList.contains('btn-dark') === false){
                    this.classList.add('btn-primary');
                }
            }, 1500);
        });
    });
});
</script>
    <section class="py-5 mt-4">
        <div class="section-header">
            <h2 class="section-title text-primary">📹 VIDEO NGẮN NỔI BẬT (VIDEO)</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($videos as $video): ?>
            <div class="col-6 col-md-3">
                <div class="video-card" data-bs-toggle="modal" data-bs-target="#videoModal" data-video="https://www.youtube.com/embed/<?= $video['id'] ?>">
                    <img src="https://img.youtube.com/vi/<?= $video['id'] ?>/hqdefault.jpg" class="w-100">
                    <div class="video-overlay"><i class="fas fa-play-circle fs-1"></i></div>
                    <div class="video-title"><?= $video['title'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<div class="modal fade" id="videoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-body p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal"></button>
                <div class="ratio ratio-16x9">
                    <iframe id="videoFrame" src="" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="main-footer">
    <div class="container-balanced">
        <div class="footer-newsletter">
            <div class="row align-items-center">
                <div class="col-xl-7 col-lg-6 mb-4 mb-lg-0 text-center text-lg-start">
                    <h2 class="fw-bold mb-2" style="font-size: 2.3rem;">Đăng ký nhận tin khuyến mãi</h2>
                    <p class="mb-0 fs-5 opacity-90">Nhận ngay voucher <strong class="text-warning">50.000đ</strong> và cẩm nang sức khỏe hàng tuần.</p>
                </div>
                <div class="col-xl-5 col-lg-6">
                    <form class="input-group">
    <input type="email" name="email" class="form-control newsletter-input" placeholder="Địa chỉ email của bạn..." required>
    <button type="submit" class="btn newsletter-btn shadow-sm">ĐĂNG KÝ</button>
</form>
                </div>
            </div>
        </div>

        <div class="row g-5 pb-5">
            <div class="col-xl-4 col-lg-12">
                <div class="brand-logo fw-bold text-primary mb-4" style="font-size: 2.2rem;">
                    <i class="fas fa-clinic-medical"></i> PHARMA TECH
                </div>
                <p class="text-muted mb-4 pe-xl-5" style="font-size: 1.1rem; line-height: 1.8;">
                    Hệ thống nhà thuốc chuẩn GPP hàng đầu. Chúng tôi cung cấp giải pháp quản lý dược phẩm thông minh và dịch vụ tư vấn tận tâm 24/7.
                </p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-zalo"></i></a>
                </div>
            </div>

            <div class="col-xl-2 col-lg-4 col-6">
                <h6 class="footer-title">Khám phá</h6>
                <a href="tat-ca-best-seller.php" class="footer-link">Sản phẩm mới</a>
                <a href="tat-ca-san-pham-sieu-deal.php" class="footer-link">Siêu Deal</a>
                <a href="goc-suc-khoe.php" class="footer-link">Góc sức khỏe</a>
            </div>

            <div class="col-xl-3 col-lg-4 col-6">
                <h6 class="footer-title">Hỗ trợ khách</h6>
                <a href="chinh-sach-van-chuyen.php" class="footer-link">Chính sách vận chuyển</a>
                <a href="chinh-sach-doi-tra.php" class="footer-link">Chính sách đổi trả</a>
                <a href="bao-mat-thong-tin.php" class="footer-link">Bảo mật thông tin</a>
            </div>

            <div class="col-xl-3 col-lg-4">
                <h6 class="footer-title">Liên hệ</h6>
                <p class="mb-3"><i class="fas fa-phone-alt text-primary me-2"></i> <span class="fw-800 text-primary fs-4">0366388104</span></p>
                <p class="mb-3 small text-muted"><i class="fas fa-map-marker-alt me-2"></i> Đại học công nghệ Đông Á</p>
                <p class="small text-muted"><i class="fas fa-envelope me-2"></i> 20220111@eaut.edu.vn</p>
            </div>
        </div>
        
        <hr class="opacity-10">
        
        <div class="row py-4 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-muted small mb-0"> <strong>© 2026 Bản quyền thuộc về Viet Duc Pharmacy.</strong></p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="trust-badges">
    <img src="https://tse3.mm.bing.net/th/id/OIP.uSuym7UI4QCWQc8XOPkN6AHaHa?r=0&pid=Api&h=220&P=0" alt="Visa">
    
    <img src="https://tse2.mm.bing.net/th/id/OIP.9xWEj6GeKnhoOnmPlZ9d7gHaD6?rs=1&pid=ImgDetMain&o=7&rm=3" alt="VNPay">
    
    <img src="https://tse4.mm.bing.net/th/id/OIP.GzrJbUHnR3OjPyZWptEytQHaCz?r=0&pid=Api&h=220&P=0" alt="BCT">
</div>
            </div>
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ================= COUNTDOWN =================
function startCountdown() {
    let h = 2, m = 15, s = 40;
    const el = document.getElementById('countdown');

    if(!el) return;

    setInterval(() => {
        if(s > 0) s--;
        else {
            s = 59;
            if(m > 0) m--;
            else {
                m = 59;
                if(h > 0) h--;
            }
        }

        el.innerText = `Kết thúc sau: ${h<10?'0'+h:h}:${m<10?'0'+m:m}:${s<10?'0'+s:s}`;
    }, 1000);
}
startCountdown();


// ================= VIDEO MODAL =================
const vModal = document.getElementById('videoModal');
const vFrame = document.getElementById('videoFrame');

if(vModal && vFrame){
    vModal.addEventListener('show.bs.modal', function(e){
        vFrame.src = e.relatedTarget.dataset.video + "?autoplay=1";
    });

    vModal.addEventListener('hidden.bs.modal', function(){
        vFrame.src = "";
    });
}


// ================= NEWSLETTER AJAX =================
const newsletterForm = document.querySelector('.footer-newsletter form');

if(newsletterForm){
newsletterForm.addEventListener('submit', function(e){

    e.preventDefault();

    const emailInput = this.querySelector('input[name="email"]');
    const btn = this.querySelector('button');
    const email = emailInput.value;

    if(!email) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('subscribe.php', {
        method: 'POST',
        headers:{
            'Content-Type':'application/x-www-form-urlencoded'
        },
        body:'email=' + encodeURIComponent(email)
    })
    .then(res => res.text())
    .then(data => {

        const result = data.trim();

        if(result === 'success'){
            alert('🎉 Chúc mừng! Bạn đã đăng ký nhận tin thành công.');
            emailInput.value = '';
        }
        else if(result === 'exists'){
            alert('📧 Email này đã có trong hệ thống rồi!');
        }
        else if(result === 'invalid'){
            alert('❌ Email không hợp lệ.');
        }
        else{
            alert('❌ Lỗi hệ thống: ' + result);
        }

    })
    .catch(err=>{
        console.error(err);
        alert('❌ Không thể kết nối máy chủ.');
    })
    .finally(()=>{
        btn.disabled = false;
        btn.innerHTML = 'ĐĂNG KÝ';
    });

});
}


// ================= LOAD MENU PRODUCTS =================
document.querySelectorAll('.menu-load').forEach(btn => {

btn.addEventListener('mouseenter', function(){

let cat = this.dataset.cat;
let sub = this.dataset.sub;

fetch(`load_menu_products.php?cat=${cat}&sub=${sub}`)
.then(res => res.text())
.then(data => {

let box = this.closest('.mega-menu')
              .querySelector('.mega-products-list');

if(box){
box.innerHTML = data;
}

})
.catch(()=>{
console.log("Không load được sản phẩm");
});

});

});

function startCountdown(id){

let h = 2, m = 14, s = 35;

const el = document.getElementById(id);
if(!el) return;

setInterval(()=>{

if(s>0){
s--;
}
else{
s=59;

if(m>0){
m--;
}
else{
m=59;
if(h>0) h--;
}
}

el.innerText =
"⏰ "+(h<10?"0"+h:h)+":"+
(m<10?"0"+m:m)+":"+
(s<10?"0"+s:s);

},1000);

}

startCountdown("countdownDeal");
startCountdown("countdownBest");
</script>

<div class="contact-floating">

<a href="tel:0387353201" class="contact-btn phone">
<i class="fas fa-phone"></i>
<span>Gọi</span>
</a>

<a href="https://zalo.me/" target="_blank" class="contact-btn zalo">
<img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg">
<span>Zalo</span>
</a>

<a href="https://m.me/uctran.147643"
target="_blank"
class="contact-btn messenger">

<i class="fab fa-facebook-messenger"></i>
<span>Messenger</span>

</a>

</div>

<!-- MODAL YÊU CẦU ĐĂNG NHẬP -->
<div class="modal fade" id="loginRequiredModal" tabindex="-1"
     aria-labelledby="loginRequiredTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 22px;">
            <div class="text-center px-4 pt-4 pb-3"
                 style="background: linear-gradient(135deg, #fff7e6, #ffffff);">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                     style="width:76px;height:76px;border-radius:50%;background:#fff0c2;color:#d99100;font-size:32px;">
                    <i class="fas fa-lock"></i>
                </div>
                <h4 class="fw-bold mb-2" id="loginRequiredTitle" style="color:#183b70;">
                    Đăng nhập để tiếp tục
                </h4>
                <p class="text-muted mb-0">
                    Bạn có thể thoải mái xem sản phẩm. Để thêm vào giỏ hàng và đặt mua,
                    vui lòng đăng nhập tài khoản.
                </p>
            </div>
            <div class="modal-body px-4 pt-3 pb-4">
                <a id="loginRequiredGoLogin" href="login.php"
                   class="btn btn-primary w-100 fw-bold py-3 mb-2"
                   style="border-radius:12px;background:#123b78;border-color:#123b78;">
                    <i class="fas fa-sign-in-alt me-2"></i> ĐĂNG NHẬP NGAY
                </a>
                <button type="button" class="btn btn-light w-100 fw-semibold py-2"
                        data-bs-dismiss="modal" style="border-radius:12px;">
                    Tiếp tục xem sản phẩm
                </button>
                <div class="text-center mt-3 small text-muted">
                    <i class="fas fa-shield-alt me-1 text-success"></i>
                    Thông tin của bạn được bảo vệ an toàn
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>