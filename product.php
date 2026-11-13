<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/* ===============================
   1. LẤY THAM SỐ & LOGIC LỌC
================================*/
$category = $_GET['cat'] ?? 'all';
$subcategory = $_GET['sub'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$search = trim($_GET['search'] ?? '');

// Thiết lập sắp xếp
$order_by = " ORDER BY id DESC";
if($sort == 'price_asc') $order_by = " ORDER BY price_new ASC";
if($sort == 'price_desc') $order_by = " ORDER BY price_new DESC";

/* ===============================
   2. CẤU HÌNH DANH MỤC
================================*/
$cat_names = [
    'all' => ['name' => 'Tất cả sản phẩm', 'icon' => 'fa-border-all'],
    'thuc-pham-chuc-nang' => ['name' => 'Thực phẩm chức năng', 'icon' => 'fa-leaf'],
    'duoc-my-pham' => ['name' => 'Dược mỹ phẩm', 'icon' => 'fa-magic'],
    'thuoc' => ['name' => 'Thuốc', 'icon' => 'fa-pills'],
    'thiet-bi-y-te' => ['name' => 'Thiết bị y tế', 'icon' => 'fa-stethoscope']
];

$sub_categories = [
    'thuc-pham-chuc-nang' => [
        'vitamin' => 'Vitamin & Khoáng chất',
        'mien-dich' => 'Miễn dịch - Đề kháng',
        'than-kinh-nao' => 'Thần kinh não',
        'tieu-hoa' => 'Hỗ trợ tiêu hoá'
    ],
     'duoc-my-pham' => [
        'cham-soc-da-mat' => 'Chăm sóc da mặt',
        'kem-chong-nang' => 'Kem chống nắng',
        'cham-soc-co-the' => 'Chăm sóc cơ thể',
        'tri-mun' => 'Trị mụn',
        'duong-trang-da' => 'Dưỡng trắng da',
        'chong-lao-hoa' => 'Chống lão hóa'
    ],

    'thuoc' => [
    'giam-dau-ha-sot' => 'Giảm đau - Hạ sốt',
    'cam-cum' => 'Cảm cúm',
    'thuoc-ho' => 'Thuốc ho',
    'di-ung' => 'Dị ứng',
    'da-day' => 'Dạ dày',
    'thuoc-bo' => 'Thuốc bổ'
],

    
     'thiet-bi-y-te' => [
        'may-do-huyet-ap' => 'Máy đo huyết áp',
        'nhiet-ke' => 'Nhiệt kế',
        'may-do-duong-huyet' => 'Máy đo đường huyết',
        'may-xong-mui-hong' => 'Máy xông mũi họng',
        'may-tao-oxy' => 'Máy tạo oxy',
        'can-suc-khoe' => 'Cân sức khỏe',
        'dung-cu-so-cuu' => 'Dụng cụ sơ cứu',
        'khau-trang-y-te' => 'Khẩu trang y tế'
    ]
];
//TRUY VẤN DATABASE (PREPARED STATEMENT)


$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($category !== 'all') { 
    $where_clauses[] = "category = ?"; 
    $params[] = $category; 
    $types .= "s"; 
}
if ($subcategory !== '') { 
    $where_clauses[] = "subcategory = ?"; 
    $params[] = $subcategory; 
    $types .= "s"; 
}
if ($search !== '') { 
    $where_clauses[] = "name LIKE ?"; 
    $params[] = "%$search%"; 
    $types .= "s"; 
}

$sql = "SELECT * FROM products WHERE " . implode(" AND ", $where_clauses) . $order_by;
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm | Viet Duc Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="css/product.css?v=<?= filemtime('css/product.css') ?>">

</head>
<body>

<div class="promo-marquee">
    <div class="marquee-content">
        <span><i class="fas fa-truck-fast"></i> Miễn phí vận chuyển đơn từ 500k</span>
        <span><i class="fas fa-certificate"></i> Cam kết 100% thuốc chính hãng</span>
        <span><i class="fas fa-user-doctor"></i> Dược sĩ tư vấn chuyên môn 24/7</span>
        <span><i class="fas fa-gift"></i> Voucher 50k cho khách hàng mới</span>
        <span><i class="fas fa-truck-fast"></i> Miễn phí vận chuyển đơn từ 500k</span>
        <span><i class="fas fa-certificate"></i> Cam kết 100% thuốc chính hãng</span>
    </div>
</div>

<nav class="navbar-custom shadow-sm sticky-top mb-4">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn-home"><i class="fas fa-home me-2"></i>Trang chủ</a>
            <a class="navbar-brand text-white fw-800 fs-3 d-none d-md-block" href="index.php">
                <span style="color: var(--primary)">Viet Duc</span> Pharmacy 
            </a>
        </div>
        <div class="search-bar d-none d-lg-flex align-items-center w-50">
            <i class="fas fa-search text-white opacity-50 pe-2"></i>
            <form action="product.php" method="GET" class="w-100">
                <input type="text" name="search" placeholder="Bạn đang tìm sản phẩm gì?" value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>
        <div class="d-flex align-items-center gap-4">
            <a href="cart.php" class="text-white position-relative p-2" style="background: rgba(255,255,255,0.1); border-radius: 12px;">
                <i class="fas fa-shopping-basket fs-5"></i>
                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $cart_count ?>
                </span>
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-lg-5">
    <div class="row g-4">
        <div class="col-lg-3 col-xl-2">
            <div class="sidebar-card">
                <h6 class="text-uppercase fw-800 mb-4 small text-muted">Danh mục</h6>
                <nav class="nav flex-column">
                    <?php foreach($cat_names as $key => $cat): ?>
                        <a href="product.php?cat=<?= $key ?>" class="nav-link-custom <?= ($category == $key) ? 'active' : '' ?>">
                            <i class="fas <?= $cat['icon'] ?> me-3"></i> <?= $cat['name'] ?>
                        </a>
                        
                        <?php if(isset($sub_categories[$key]) && $category == $key): ?>
                            <div class="submenu">
                                <?php foreach($sub_categories[$key] as $sub_key => $sub_name): ?>
                                    <a href="product.php?cat=<?= $key ?>&sub=<?= $sub_key ?>" 
                                       class="sub-link <?= ($subcategory == $sub_key) ? 'active' : '' ?>">
                                        <?= $sub_name ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <div class="col-lg-9 col-xl-10">
            <div id="heroCarousel" class="carousel slide border-radius-24 overflow-hidden shadow-sm mb-5" data-bs-ride="carousel" style="border-radius: 24px;">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="https://img.freepik.com/free-vector/medical-science-banner-with-blue-hexagonal-shapes_1017-26838.jpg" class="d-block w-100" style="height:350px; object-fit:cover;">
                        <div class="carousel-caption d-none d-md-block text-start" style="bottom: 20%; left: 8%;">
                            <span class="badge bg-primary mb-2 p-2 px-3">GIẢM ĐẾN 30%</span>
                            <h2 class="fw-800 display-6 text-dark">Thực phẩm chức năng<br>Chính hãng 100%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-800 mb-0"><?= $cat_names[$category]['name'] ?></h2>
                    <?php if($subcategory): ?>
                        <span class="text-muted">Phân loại: <?= $sub_categories[$category][$subcategory] ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <select class="form-select rounded-pill border-0 shadow-sm" style="width: 200px;" onchange="location = this.value;">
                        <option value="product.php?cat=<?= $category ?>&sort=newest" <?= $sort=='newest'?'selected':'' ?>>Mới nhất</option>
                        <option value="product.php?cat=<?= $category ?>&sort=price_asc" <?= $sort=='price_asc'?'selected':'' ?>>Giá tăng dần</option>
                        <option value="product.php?cat=<?= $category ?>&sort=price_desc" <?= $sort=='price_desc'?'selected':'' ?>>Giá giảm dần</option>
                    </select>
                    
                </div>
            </div>

            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 row-cols-xxl-5 g-4">
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="product-grid-card">
                            <div class="img-wrap">
                                <img src="images/<?= $row['image'] ?>" alt="<?= $row['name'] ?>">
                                <div class="img-overlay">
                                   <a href="chitietsp.php?id=<?= $row['id'] ?>" class="btn-detail-view">Xem chi tiết</a>
                                </div>
                            </div>
                            <a href="chitietsp.php?id=<?= $row['id'] ?>" class="text-decoration-none">
                                <h2 class="product-title"><?= $row['name'] ?></h2>

<?php if($row['stock'] <= 0): ?>
<span class="badge bg-danger">Hết hàng</span>

<?php elseif($row['stock'] < 5): ?>
<span class="badge bg-warning text-dark">Còn <?= $row['stock'] ?> sp</span>

<?php else: ?>
<span class="badge bg-success">Còn hàng</span>
<?php endif; ?>
                            </a>
                            <div class="price-tag">

<?php if($row['price_old'] > $row['price_new']): 
$discount = round((($row['price_old'] - $row['price_new']) / $row['price_old']) * 100);
?>
<span class="badge bg-danger mb-1">-<?= $discount ?>%</span>
<?php endif; ?>

<div>
<span class="price-new"><?= number_format($row['price_new'],0,',','.') ?>đ</span>

<?php if($row['price_old'] > $row['price_new']): ?>
<span class="text-muted text-decoration-line-through small">
<?= number_format($row['price_old'],0,',','.') ?>đ
</span>
<?php endif; ?>
</div>

<button 
onclick="addToCart(<?= $row['id'] ?>)" 
class="btn-add-cart"
<?= $row['stock'] <= 0 ? 'disabled style="opacity:0.4;cursor:not-allowed"' : '' ?>
>
<i class="fas fa-plus"></i>
</button>

</div>
                            <button 
onclick="buyNow(<?= $row['id'] ?>)" 
class="btn-buy-now shadow-sm"
<?= $row['stock'] <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>
>
<?= $row['stock'] <= 0 ? 'HẾT HÀNG' : 'MUA NGAY' ?>
</button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="opacity-25 mb-3">
                        <p class="text-muted">Không tìm thấy sản phẩm nào trong mục này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="toast-container">
    <div id="cartToast" class="toast align-items-center text-white bg-dark border-0 rounded-pill px-3 shadow-lg" role="alert">
        <div class="d-flex align-items-center"><div class="toast-body fw-bold">🛒 Đã thêm vào giỏ thành công!</div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function addToCart(id) {
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'add_to_cart_id=' + id
        })
        .then(response => response.text())
        .then(totalCount => {
            document.getElementById('cart-badge').innerText = totalCount;
            let toast = new bootstrap.Toast(document.getElementById('cartToast'));
            toast.show();
        });
    }

    function buyNow(id) {
        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'add_to_cart_id=' + id
        }).then(() => { window.location.href = 'cart.php'; });
    }
</script>
<script>
document.querySelectorAll('.float-btn').forEach(btn=>{
btn.addEventListener('mouseover',()=>{
btn.style.animation="shake 0.5s";
});
btn.addEventListener('mouseout',()=>{
btn.style.animation="shake 3s infinite";
});
});
</script>

<!-- FLOATING CHAT BUTTONS -->
<div class="floating-contact">

<!-- ZALO -->
<a href="https://zalo.me/0366388104" target="_blank" class="float-btn zalo-btn">
<img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg" width="26">
</a>

<!-- MESSENGER -->
<a href="https://m.me/uctran.147643" target="_blank" class="float-btn messenger-btn">
<i class="fab fa-facebook-messenger"></i>
</a>

<!-- CALL -->
<a href="tel:0366388104" class="float-btn call-btn">
<i class="fas fa-phone"></i>
</a>

</div>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>