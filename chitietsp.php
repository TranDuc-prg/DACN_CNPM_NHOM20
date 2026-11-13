<?php
session_start();
require_once 'config/db.php';


/* ===== SẢN PHẨM CHO MEGA MENU ===== */

$mega_products = [];

$sqlMega = "SELECT id,name,image,price_new 
            FROM products 
            ORDER BY id DESC 
            LIMIT 6";

$resultMega = $conn->query($sqlMega);

if($resultMega){
    while($row = $resultMega->fetch_assoc()){
        $mega_products[] = $row;
    }
}
// 1. Kiểm tra ID sản phẩm
$cat = $_GET['cat'] ?? '';
$sub = $_GET['sub'] ?? '';



$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

/* ========= 2. XỬ LÝ GỬI ĐÁNH GIÁ & PHẢN HỒI ========= */
if (isset($_POST['submit_review'])) {
    $name = mysqli_real_escape_string($conn, $_POST['customer_name'] ?: 'Khách hàng');
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    
    if (!empty($comment)) {
        $sql = "INSERT INTO reviews 
        (product_id, customer_name, rating, comment, parent_id) 
        VALUES 
        ('$id','$name','$rating','$comment','$parent_id')";
        if ($conn->query($sql)) {
            header("Location: chitietsp.php?id=$id#sec-review");
            exit;
        }
    }
}

/* ========= 3. TRUY VẤN DỮ LIỆU ========= */
$product = null;

if($id){
    $product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
}

if (!$product) {
    echo "Sản phẩm không tồn tại!";
    exit;
}

$all_images = [];

if($product){
    $all_images[] = $product['image'];

    $imgs = $conn->query("SELECT * FROM product_images WHERE product_id=$id");

    if($imgs){
        while ($row = $imgs->fetch_assoc()) {
            $all_images[] = $row['image'];
        }
    }
}


/* ========= 4. LẤY SẢN PHẨM LIÊN QUAN ========= */
$relatedProducts = [];

$stmt_related = $conn->prepare("
    SELECT id, name, image, price_new 
    FROM products 
    WHERE id != ?
    ORDER BY id DESC
    LIMIT 4
");

$stmt_related->bind_param("i", $id);
$stmt_related->execute();
$result_related = $stmt_related->get_result();

if ($result_related->num_rows > 0) {
    while ($row = $result_related->fetch_assoc()) {
        $relatedProducts[] = $row;
    }
}
// Lấy đánh giá gốc
$reviews_main = $conn->query("SELECT * FROM reviews WHERE product_id=$id AND parent_id = 0 ORDER BY created_at DESC");

// Tính tổng số đánh giá + trung bình sao (tối ưu 1 query)
$avg_rating = 0;
$total_reviews = 0;

$result = $conn->query("SELECT COUNT(*) as total, AVG(rating) as average 
                        FROM reviews 
                        WHERE product_id=$id AND parent_id = 0");

/* ========= XOÁ BÌNH LUẬN ========= */
if(isset($_GET['delete_review'])){

    $rid = (int)$_GET['delete_review'];

    // Xóa cả reply của bình luận đó
    $conn->query("DELETE FROM reviews WHERE id=$rid OR parent_id=$rid");

    header("Location: chitietsp.php?id=".$id."#sec-review");
    exit;
}
// ===== THỐNG KÊ 5→1 SAO =====
$rating_stats = array_fill(1, 5, 0);

$stats = $conn->query("
    SELECT rating, COUNT(*) as total 
    FROM reviews 
    WHERE product_id=$id AND parent_id=0 
    GROUP BY rating
");

if ($stats) {
    while ($row = $stats->fetch_assoc()) {
        $rating_stats[$row['rating']] = $row['total'];
    }
}

if ($result) {
    $row = $result->fetch_assoc();
    $total_reviews = (int)$row['total'];
    $avg_rating = $row['average'] ? round($row['average'], 1) : 0;
}


// CẤU TRÚC MENU CÓ DANH MỤC CON
$nav_items = [
    [
        'name' => 'Thực phẩm chức năng', 
        'link' => 'product.php?cat=thuc-pham-chuc-nang', 
        'icon' => 'fa-mortar-pestle',
        'subs' => [
    [
        'title' => 'Vitamin & Khoáng chất', 
        'sub'   => 'vitamin', 
        'desc'  => '', 
        'icon'  => 'fa-capsules' // Biểu tượng viên thuốc nhộng
    ],
    [
        'title' => 'Hỗ trợ miễn dịch', 
        'sub'   => 'mien-dich', 
        'desc'  => '', 
        'icon'  => 'fa-shield-virus' // Biểu tượng khiên bảo vệ chống virus
    ],
    [
        'title' => 'Hỗ trợ tiêu hóa', 
        'sub'   => 'tieu-hoa', 
        'desc'  => '', 
        'icon'  => 'fa-apple-alt' // Biểu tượng dạ dày
    ],
    [
        'title' => 'Thần kinh não', 
        'sub'   => 'than-kinh-nao', 
        'desc'  => '', 
        'icon'  => 'fa-brain' // Biểu tượng bộ não
    ]
]
    ],
[
    'name' => 'Dược mỹ phẩm',
    'link' => 'product.php?cat=duoc-my-pham',
    'icon' => 'fa-sparkles',
    'subs' => [

        [
            'title' => 'Chăm sóc da mặt',
            'sub'   => 'cham-soc-da-mat',
            'icon'  => 'fa-face-smile'
        ],

        [
            'title' => 'Kem chống nắng',
            'sub'   => 'kem-chong-nang',
            'icon'  => 'fa-sun'
        ],

        [
            'title' => 'Chăm sóc cơ thể',
            'sub'   => 'cham-soc-co-the',
            'icon'  => 'fa-spa'
        ],

        [
            'title' => 'Trị mụn',
            'sub'   => 'tri-mun',
            'icon'  => 'fa-bacteria'
        ],

        [
            'title' => 'Dưỡng trắng da',
            'sub'   => 'duong-trang-da',
            'icon'  => 'fa-star'
        ],

        [
            'title' => 'Chống lão hóa',
            'sub'   => 'chong-lao-hoa',
            'icon'  => 'fa-clock'
        ]

    ]
],
[
    'name' => 'Thuốc',
    'link' => 'product.php?cat=thuoc',
    'icon' => 'fa-pills',
    'subs' => [

        [
            'title' => 'Giảm đau - Hạ sốt',
            'sub'   => 'giam-dau-ha-sot',
            'icon'  => 'fa-tablets'
        ],

        [
            'title' => 'Thuốc ho',
            'sub'   => 'thuoc-ho',
            'icon'  => 'fa-lungs'
        ],

        [
            'title' => 'Dị ứng',
            'sub'   => 'di-ung',
            'icon'  => 'fa-allergies'
        ],

        [
            'title' => 'Dạ dày',
            'sub'   => 'da-day',
            'icon'  => 'fa-stomach'
        ],

        [
            'title' => 'Cảm cúm',
            'sub'   => 'cam-cum',
            'icon'  => 'fa-head-side-cough'
        ],

        [
            'title' => 'Thuốc bổ',
            'sub'   => 'thuoc-bo',
            'icon'  => 'fa-capsules'
        ]

    ]
],
    [
    'name' => 'Thiết bị y tế',
    'link' => 'product.php?cat=thiet-bi-y-te',
    'icon' => 'fa-stethoscope',
    'subs' => [

        [
            'title' => 'Máy đo huyết áp',
            'sub'   => 'may-do-huyet-ap',
            'icon'  => 'fa-heartbeat'
        ],

        [
            'title' => 'Nhiệt kế',
            'sub'   => 'nhiet-ke',
            'icon'  => 'fa-thermometer-half'
        ],

        [
            'title' => 'Máy đo đường huyết',
            'sub'   => 'may-do-duong-huyet',
            'icon'  => 'fa-tint'
        ],

        [
            'title' => 'Máy xông mũi họng',
            'sub'   => 'may-xong-mui-hong',
            'icon'  => 'fa-wind'
        ],

        [
            'title' => 'Máy tạo oxy',
            'sub'   => 'may-tao-oxy',
            'icon'  => 'fa-lungs'
        ],

        [
            'title' => 'Cân sức khỏe',
            'sub'   => 'can-suc-khoe',
            'icon'  => 'fa-weight'
        ],

        [
            'title' => 'Dụng cụ sơ cứu',
            'sub'   => 'dung-cu-so-cuu',
            'icon'  => 'fa-first-aid'
        ],

        [
            'title' => 'Khẩu trang y tế',
            'sub'   => 'khau-trang-y-te',
            'icon'  => 'fa-head-side-mask'
        ]

    ]
],
    ['name' => 'Tất cả sản phẩm', 'link' => 'product.php?cat=all', 'icon' => 'fa-th-large']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Viet Duc Pharmacy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/chitietsp.css?v=<?= filemtime('css/chitietsp.css') ?>">
</head>

<body>

<header class="top-bar">
    <div class="container-full d-flex align-items-center justify-content-between">
        <a href="index.php" class="text-white fw-bold fs-3 text-decoration-none d-flex align-items-center">
            <i class="fas fa-plus-square me-2 text-medical"></i>VIET DUC <span class="fw-light ms-1" style="color: #A5F3FC;">PHARMACY</span>
        </a>
        <form action="product.php" method="GET" 
      class="search-box d-none d-lg-flex flex-grow-1 mx-5 align-items-center">

    <i class="fas fa-search text-muted me-3"></i>

    <input type="text" 
           name="keyword"
           class="form-control border-0 shadow-none p-0"
           placeholder="Tìm tên thuốc, thực phẩm chức năng..."
           value="<?= isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : '' ?>">

</form>
        <div class="d-flex align-items-center gap-4">
            <a href="cart.php" class="position-relative text-white text-decoration-none">
                <i class="fas fa-shopping-basket fs-4"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $cart_count ?></span>
            </a>
            <a href="login.php" class="btn btn-outline-light btn-sm fw-bold px-4 rounded-pill">ĐĂNG NHẬP</a>
        </div>
    </div>
</header>

<nav class="nav-main d-none d-lg-block">
    <div class="container-full">
        <ul class="nav justify-content-center">

<?php 
if (!function_exists('getSmartSubSlug')) {

    function getSmartSubSlug($text) {

        $text_lower = mb_strtolower($text,'UTF-8');

        if (stripos($text_lower,'miễn dịch') !== false) return "mien-dich";

        if (stripos($text_lower,'tiêu hóa') !== false || stripos($text_lower,'tiêu hoá') !== false) 
            return "tieu-hoa";

        if (stripos($text_lower,'thần kinh') !== false) 
            return "than-kinh-nao";

        if (stripos($text_lower,'vitamin') !== false) 
            return "vitamin";

        if (stripos($text_lower,'da mặt') !== false) 
            return "cham-soc-da-mat";

        if (stripos($text_lower,'chống nắng') !== false) 
            return "kem-chong-nang";

        if (stripos($text_lower,'làm đẹp') !== false) 
            return "lam-dep";


        if (stripos($text_lower,'chăm sóc cơ thể') !== false) 
    return "cham-soc-co-the";

if (stripos($text_lower,'trị mụn') !== false) 
    return "tri-mun";

if (stripos($text_lower,'dưỡng trắng') !== false) 
    return "duong-trang-da";

if (stripos($text_lower,'chống lão hóa') !== false) 
    return "chong-lao-hoa";

        // fallback: chuyển text thành slug
        $slug = iconv('UTF-8','ASCII//TRANSLIT',$text_lower);
        $slug = preg_replace('/[^a-z0-9\s-]/','',$slug);
        $slug = preg_replace('/[\s-]+/','-',$slug);
        $slug = trim($slug,'-');

        return $slug;
    }

}


foreach($nav_items as $item): 
?>

<?php if(!empty($item['subs'])): ?>

<li class="nav-item has-mega">
    <a class="nav-link" href="#">
        <i class="fas <?= $item['icon'] ?? 'fa-circle' ?> me-2"></i>
        <?= htmlspecialchars($item['name']) ?>
    </a>

    <div class="mega-menu">
        <div class="container py-3">
            <div class="row">

<?php
// Chia danh mục làm 2 cột chính (Mỗi cột chiếm 4/12 Grid)
$chunks = array_chunk($item['subs'], ceil(count($item['subs'])/2));

foreach($chunks as $index => $column):
?>
                <div class="col-lg-4 <?= $index > 0 ? 'border-start' : '' ?> ps-4">
                    <h6 class="fw-bold text-success mb-3">
                        <i class="fas fa-th-large me-2"></i> Nhóm <?= $index + 1 ?>
                    </h6>

                    <div class="sub-category-list mb-3" style="position: relative; z-index: 10;">
                        <?php foreach ($column as $sub): 
                            $title = $sub['title'] ?? '';
                           $cat_slug = str_replace("product.php?cat=", "", $item['link']);
                        ?>
<div class="mb-2">
    <a href="#"
       class="text-decoration-none d-block mega-sub-link filter-product"
       data-column-id="<?= $item['name'] . '-' . $index ?>"
       data-cat="<?= $cat_slug ?>"
       data-sub="<?= $sub['sub'] ?>"
       >
       
        <h6 class="fw-bold text-dark mb-1" style="font-size: 14px;">
            <i class="fas <?= $sub['icon'] ?? 'fa-chevron-right' ?> me-2 text-primary" style="font-size: 11px;"></i>
            <?= htmlspecialchars($title) ?>
        </h6>

    </a>
</div>
<?php endforeach; ?>
</div>

                    <hr>

                    <div id="mega-product-list-<?= $item['name'] . '-' . $index ?>" class="dynamic-product-area mt-2">
                        <p class="text-muted small italic">Click danh mục để xem sản phẩm</p>
                    </div>
                </div>
<?php endforeach; ?>

                <div class="col-lg-4 border-start ps-4">
                    <h6 class="fw-bold text-danger mb-3">
                        <i class="fas fa-fire me-2"></i> Sản phẩm bán chạy
                    </h6>
                    <div class="mega-products-list">
                        <?php if(!empty($mega_products)): ?>
                            <?php foreach(array_slice($mega_products,0,4) as $p): ?>
                            <a href="chitietsp.php?id=<?= $p['id'] ?>" 
                               class="product-item d-flex align-items-center text-decoration-none text-dark mb-2 p-2 rounded hover-bg-light border" style="background: #f9f9f9;">
 <img src="images/<?= htmlspecialchars($p['image']) ?>" 

style="width:45px;height:45px;object-fit:contain;margin-right:10px;background:#fff;border-radius:4px;"
onerror="this.src='/images/no-image.png'">
                                <div class="product-info">
                                    <div class="text-truncate fw-bold" style="max-width:160px;font-size:13px">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </div>
                                    <div class="text-primary small fw-bold">
                                        <?= number_format($p['price_new'] ?? 0) ?>đ
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</li>

<?php else: ?>
<li class="nav-item">
    <a class="nav-link" href="<?= htmlspecialchars($item['link']) ?>">
        <i class="fas <?= $item['icon'] ?? 'fa-circle' ?> me-2"></i>
        <?= htmlspecialchars($item['name']) ?>
    </a>
</li>
<?php endif; ?>

<?php endforeach; ?>

        </ul>
    </div>
</nav>
<main class="container-full mt-4">
    <div class="container mt-4">
    
</div>

    <!-- Nút quay lại -->
    <div class="mb-4">
    <a href="index.php" class="btn-home">
<i class="fas fa-home me-2"></i> Trang chủ 
   </a>
</div>

    <div class="product-card position-relative">
        <div class="row g-5">
            <div class="col-lg-5">

                <div class="img-main-wrapper p-4 mb-3 position-relative">
        <!-- Nút trái -->
        <button class="img-nav prev-btn">&#10094;</button>

        <!-- Ảnh chính -->
    <img src="images/<?= $all_images[0] ?>" 
id="mainImg"
onerror="this.src='/images/no-image.png'">
        <!-- Nút phải -->
        <button class="img-nav next-btn">&#10095;</button>

    </div>

    <!-- Thumbnail -->
    <div class="d-flex gap-2 justify-content-center">
        <?php foreach($all_images as $index => $img): ?>
            <img src="images/<?= $img ?>"

class="border rounded-2 p-1 bg-white thumb-img"
                 style="width:70px; height:70px; cursor:pointer; object-fit: cover;"
                 data-index="<?= $index ?>">
        <?php endforeach; ?>
    </div>

</div>
            <div class="col-lg-7">
                <div class="mb-2">
                    <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="fas fa-shield-alt me-1"></i> Chính hãng 100%</span>
                </div>
                <h1 class="fw-800 mb-3" style="color: var(--dark-blue); font-size: 2rem;"><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="d-flex align-items-center gap-3 mb-4">
    <div class="text-warning d-flex align-items-center">
        <?php
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= floor($avg_rating)) {
                echo '<i class="fas fa-star"></i>'; // sao đầy
            } elseif ($i - $avg_rating < 1) {
                echo '<i class="fas fa-star-half-alt"></i>'; // sao nửa
            } else {
                echo '<i class="far fa-star"></i>'; // sao rỗng
            }
        }
        ?>
        <span class="text-dark fw-bold ms-2"><?= $avg_rating ?>/5</span>
        <span class="text-muted small ms-2">(<?= $total_reviews ?> đánh giá)</span>
    </div>

    <span class="text-muted">|</span>

    <?php
$stock = (int)$product['stock'];
?>

<?php if($stock <= 0): ?>
<span class="text-danger small fw-bold">
    <i class="fas fa-times-circle me-1"></i>
    Tình trạng: Hết hàng
</span>

<?php elseif($stock < 5): ?>
<span class="text-warning small fw-bold">
    <i class="fas fa-exclamation-circle me-1"></i>
    Tình trạng: Còn <?= $stock ?> sản phẩm
</span>

<?php else: ?>
<span class="text-success small fw-bold">
    <i class="fas fa-check-circle me-1"></i>
    Tình trạng: Còn hàng
</span>
<?php endif; ?>
</div>

                <div class="price-container p-4 mb-4">
                    <div class="d-flex align-items-baseline gap-3">
                        <span class="price-new"><?= number_format($product['price_new']) ?><small style="font-size: 1.2rem; font-weight: 600;">đ</small></span>
                        <?php if($product['price_old'] > $product['price_new']): ?>
                            <span class="text-muted text-decoration-line-through fs-5"><?= number_format($product['price_old']) ?>đ</span>
                            <span class="badge bg-danger rounded-pill"> -<?= round((($product['price_old']-$product['price_new'])/$product['price_old'])*100) ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>

               

                <div class="d-grid d-md-flex gap-3 mt-4">
                    <button 
class="btn btn-add-cart text-white px-5 py-3 flex-grow-1 btn-add-cart-ajax"
data-id="<?= $product['id'] ?>"
<?= $stock <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>
>
<i class="fas fa-cart-plus me-2"></i>
<?= $stock <= 0 ? 'HẾT HÀNG' : 'THÊM VÀO GIỎ HÀNG' ?>
</button>
                    <button class="btn btn-outline-primary px-4 rounded-3"><i class="far fa-heart"></i></button>
                

</div>
<div class="product-policy mt-3">
    <div class="policy-item">
        <i class="fas fa-undo-alt"></i>
        <div>
            <strong>Đổi trả trong 30 ngày</strong>
            <p>kể từ ngày mua hàng</p>
        </div>
    </div>

    <div class="policy-item">
        <i class="fas fa-box-open"></i>
        <div>
            <strong>Miễn phí 100%</strong>
            <p>đổi thuốc</p>
        </div>
    </div>

    <div class="policy-item">
        <i class="fas fa-truck"></i>
        <div>
            <strong>Miễn phí vận chuyển</strong>
            <p>theo chính sách giao hàng</p>
        </div>
    </div>
                </div>
            </div>
        </div>
        


        <div class="tabs-container" id="tabBar">
            <a href="#sec-desc" class="tab-link active">MÔ TẢ</a>
            <?php if(!empty($product['ingredients'])): ?><a href="#sec-ingre" class="tab-link">THÀNH PHẦN</a><?php endif; ?>
            <?php if(!empty($product['uses'])): ?><a href="#sec-uses" class="tab-link">CÔNG DỤNG</a><?php endif; ?>
            <a href="#sec-review" class="tab-link">ĐÁNH GIÁ</a>
        </div>

        <div class="content-body">
            <div id="sec-desc" class="section-item">
                <h3>Mô tả sản phẩm</h3>
                <div class="lh-lg text-secondary"><?= $product['description'] ?></div>
            </div>

            <?php if(!empty($product['ingredients'])): ?>
            <div id="sec-ingre" class="section-item">
                <h3>Thành phần</h3>
                <div class="lh-lg text-secondary p-3 bg-light rounded-3"><?= nl2br($product['ingredients']) ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($product['uses'])): ?>
            <div id="sec-uses" class="section-item">
                <h3>Công dụng & Cách dùng</h3>
                <div class="lh-lg text-secondary"><?= nl2br($product['uses']) ?></div>
            </div>
            <?php endif; ?>
            <section class="related-products mt-5">
    <div class="container">

      <section class="related-products mt-5">
    <div class="container">

        <div class="section-header d-flex justify-content-between align-items-center mb-4">
            <h3 class="section-title fw-bold">Sản phẩm liên quan</h3>
            <a href="product.php" class="view-all text-decoration-none">
                Xem tất cả →
            </a>
        </div>

        <div class="row g-4">

            <?php if (!empty($relatedProducts)): ?>
                <?php foreach($relatedProducts as $item): ?>
                <div class="col-6 col-md-3">
                    <div class="product-card h-100 shadow-sm">

                        <a href="chitietsp.php?id=<?= $item['id'] ?>">
                            <div class="product-image">
                               <img src="images/<?= $item['image'] ?>"
alt="<?= htmlspecialchars($item['name']) ?>"
class="img-fluid">
                            </div>
                        </a>
<div class="product-info text-center p-3">

    <h6 class="product-name mb-2">
        <?= htmlspecialchars($item['name']) ?>
    </h6>

    <div class="product-price text-danger fw-bold mb-3">
        <?= number_format($item['price_new']) ?>đ
    </div>

    <!-- NÚT XEM CHI TIẾT -->
    <a href="chitietsp.php?id=<?= $item['id'] ?>" 
       class="btn btn-outline-primary btn-sm w-100 mb-2">
        Xem chi tiết
    </a>

    <!-- NÚT THÊM GIỎ HÀNG (AJAX giống trên) -->
    <button class="btn btn-add-cart text-white w-100 btn-add-cart-ajax"
            data-id="<?= $item['id'] ?>">
        <i class="fas fa-cart-plus me-2"></i> THÊM VÀO GIỎ HÀNG
    </button>

</div>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="col-12 text-center">
                    <p class="text-muted">Chưa có sản phẩm liên quan.</p>
                </div>
            <?php endif; ?>

        </div>

    </div>
</section>

</div>
            <div id="sec-review" class="section-item border-0">
                <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3">
                    <div>
                        <h3 class="mb-0">Khách hàng nói gì?</h3>
                        <small class="text-muted">Chia sẻ trải nghiệm của bạn về sản phẩm</small>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="collapse" data-bs-target="#formReview">
                        <i class="fas fa-pen me-2"></i>Viết đánh giá
                    </button>
                </div>

                <div class="collapse mb-5" id="formReview">
                    <form method="POST" class="bg-white border p-4 rounded-4 shadow-sm">
                        <input type="hidden" name="parent_id" id="parent_id_input" value="0">
                        <h5 class="fw-bold mb-3" id="reply-title">Đánh giá sản phẩm</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold">Họ và tên *</label>
                                <input type="text" name="customer_name" class="form-control" placeholder="Nhập họ tên" required>
                            </div>
                            <div class="col-md-6" id="rating_container">
                                <label class="small fw-bold">Xếp hạng</label>
                                <select name="rating" class="form-select">
                                    <option value="5">⭐⭐⭐⭐⭐ Rất tốt</option>
                                    <option value="4">⭐⭐⭐⭐ Tốt</option>
                                    <option value="3">⭐⭐⭐ Bình thường</option>
                                    <option value="2">⭐⭐ Kém</option>
                                    <option value="1">⭐ Rất kém</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="small fw-bold">Nội dung đánh giá *</label>
                                <textarea name="comment" class="form-control" rows="4" placeholder="Bạn cảm thấy sản phẩm này thế nào?" required></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" name="submit_review" class="btn btn-primary px-5 fw-bold rounded-pill">GỬI ĐÁNH GIÁ</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php if($total_reviews > 0): ?>
<div class="bg-white p-4 rounded-4 shadow-sm mb-4">
    <div class="row align-items-center">

        <!-- Trung bình -->
        <div class="col-md-4 text-center border-end">
            <h2 class="fw-bold mb-1" style="font-size: 2.5rem; color: var(--primary-blue);">
                <?= $avg_rating ?>
            </h2>
            <div class="text-warning mb-2">
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= floor($avg_rating)) {
                        echo '<i class="fas fa-star"></i>';
                    } elseif ($i - $avg_rating < 1) {
                        echo '<i class="fas fa-star-half-alt"></i>';
                    } else {
                        echo '<i class="far fa-star"></i>';
                    }
                }
                ?>
            </div>
            <small class="text-muted"><?= $total_reviews ?> đánh giá</small>
        </div>

        <!-- Thống kê -->
        <div class="col-md-8">
            <?php for ($star = 5; $star >= 1; $star--): 
                $percent = $total_reviews > 0 
                    ? round(($rating_stats[$star] / $total_reviews) * 100) 
                    : 0;
            ?>
            <div class="d-flex align-items-center mb-2">
                <div style="width:60px"><?= $star ?> ⭐</div>

                <div class="progress flex-grow-1 mx-3" style="height:8px;">
                    <div class="progress-bar bg-warning" 
                         style="width: <?= $percent ?>%"></div>
                </div>

                <div style="width:40px" class="text-end small">
                    <?= $rating_stats[$star] ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>

    </div>
</div>
<?php endif; ?>

                <div class="review-list">
                    <?php if($total_reviews == 0): ?>
                        <div class="text-center py-5">
                            <i class="far fa-comments fs-1 text-muted mb-3"></i>
                            <p class="text-muted">Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
                        </div>
                    <?php else: ?>
                        <?php while($rev = $reviews_main->fetch_assoc()): ?>
                            <div class="review-item">
                                <div class="d-flex gap-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:45px; height:45px; flex-shrink:0;">
                                        <?= strtoupper(substr($rev['customer_name'],0,1)) ?>
                                    </div>
                                    <div class="w-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($rev['customer_name']) ?></h6>
                                            <div class="text-warning small"><?= str_repeat('<i class="fas fa-star"></i>', $rev['rating']) ?></div>
                                        </div>
                                        <p class="text-secondary my-2 small lh-base"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                                        <div class="d-flex align-items-center gap-3">
                                            <small class="text-muted" style="font-size: 12px;"><i class="far fa-clock me-1"></i><?= date('d/m/Y', strtotime($rev['created_at'])) ?></small>
                                            <span class="btn-reply fw-bold small text-primary"
style="cursor:pointer"
onclick="replyTo('<?= $rev['id'] ?>', '<?= htmlspecialchars($rev['customer_name']) ?>')">
Trả lời
</span>

<a href="chitietsp.php?id=<?= $id ?>&delete_review=<?= $rev['id'] ?>#sec-review"
class="text-danger small ms-2"
onclick="return confirm('Bạn chắc muốn xoá bình luận này?')">
<i class="fas fa-trash"></i> Xóa
</a>
                                        </div>

                                        <?php 
                                        $pid = $rev['id'];
                                        $replies = $conn->query("SELECT * FROM reviews WHERE parent_id = $pid ORDER BY created_at ASC");
                                        while($rep = $replies->fetch_assoc()): ?>
                                            <div class="reply-box">
                                                <div class="d-flex gap-2 align-items-center mb-1">
                                                    <span class="badge-qtv">QTV</span>
                                                    <h6 class="fw-bold mb-0 small"><?= htmlspecialchars($rep['customer_name']) ?></h6>
                                                </div>
                                                <p class="small mb-1 text-secondary"><?= nl2br(htmlspecialchars($rep['comment'])) ?></p>
                                                <small class="text-muted" style="font-size: 11px;"><?= date('d/m/Y H:i', strtotime($rep['created_at'])) ?></small>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="main-footer">
    <div class="container-full">
        <div class="footer-newsletter-custom shadow">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <h2 class="fw-bold mb-2">Đăng ký nhận tin khuyến mãi</h2>
                    <p class="mb-0 text-white-50 fs-5">
                        Nhận ngay voucher <b class="text-warning">50.000đ</b> và cẩm nang sức khỏe hàng tuần.
                    </p>
                </div>
                <form id="newsletterForm" class="input-group">
    <input type="email" 
           name="email"
           class="form-control newsletter-input"
           placeholder="Địa chỉ email của bạn..."
           required>

    <button class="btn newsletter-btn" type="submit">
        ĐĂNG KÝ
    </button>
</form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 pe-lg-5">
                <a href="index.php" class="text-primary fw-800 fs-3 text-decoration-none d-block mb-3">
                    <i class="fas fa-plus-square me-2"></i>VIET DUC <span class="fw-light text-dark">PHARMACY</span>
                </a>
                <p class="text-secondary lh-lg mb-4">
                    Hệ thống nhà thuốc chuẩn GPP hàng đầu. Chúng tôi cung cấp giải pháp quản lý dược phẩm thông minh và dịch vụ tư vấn tận tâm 24/7.
                </p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-zalo">Z</i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="footer-title">Khám phá</h6>
                <a href="#" class="footer-link">Sản phẩm mới</a>
                <a href="#" class="footer-link">Siêu Deal Đón Tết</a>
                <a href="#" class="footer-link">Góc sức khỏe</a>
                <a href="#" class="footer-link">Hệ thống cửa hàng</a>
            </div>

            <div class="col-6 col-lg-3">
                <h6 class="footer-title">Hỗ trợ khách hàng</h6>
                <a href="#" class="footer-link">Chính sách vận chuyển</a>
                <a href="#" class="footer-link">Chính sách đổi trả</a>
                <a href="#" class="footer-link">Bảo mật thông tin</a>
                <a href="#" class="footer-link">Câu hỏi thường gặp</a>
            </div>

            <div class="col-lg-3">
                <h6 class="footer-title">Liên hệ</h6>
                <div class="footer-contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <span class="d-block small text-muted">Tổng đài miễn cước</span>
                        <strong class="text-primary fs-5">1800 6789</strong>
                    </div>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span class="small">123 Đường ABC, Quận 1, TP.HCM</span>
                </div>
                <div class="footer-contact-item">
                    <i class="fas fa-envelope"></i>
                    <span class="small">hotro@vietducpharma.vn</span>
                </div>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center">
            <div class="small text-muted mb-3 mb-md-0">
                © 2026 <b>Viet Duc Pharmacy</b>. Bản quyền thuộc về Công ty Dược phẩm Việt Đức.
            </div>
            <div class="payment-methods">
                <img src="https://upload.wikimedia.org/wikipedia/vi/f/fe/MoMo_Logo.png" alt="MoMo" height="30" class="me-3 grayscale">
                <img src="https://vnpay.vn/sweb/face/resources/images/logo.png" alt="VNPay" height="20" class="me-3 grayscale">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Visa_Inc._logo.svg/2560px-Visa_Inc._logo.svg.png" alt="Visa" height="15" class="grayscale">
            </div>
        </div>
    </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Trả lời bình luận
    function replyTo(id, name) {
        document.getElementById('parent_id_input').value = id;
        document.getElementById('reply-title').innerHTML = `<i class="fas fa-reply me-2"></i>Trả lời: <span class="text-primary">${name}</span>`;
        document.getElementById('rating_container').style.display = "none"; 
        
        const formCollapse = document.getElementById('formReview');
        if(!formCollapse.classList.contains('show')) {
            new bootstrap.Collapse(formCollapse).show();
        }
        formCollapse.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // AJAX Thêm giỏ hàng (áp dụng cho TẤT CẢ nút)
document.querySelectorAll('.btn-add-cart-ajax').forEach(function(btn){

    btn.addEventListener('click', function(){

        let originalContent = btn.innerHTML;

        fetch('cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'add_to_cart_id=' + btn.dataset.id
        })
        .then(res => res.text())
        .then(data => {

            btn.innerHTML = "<i class='fas fa-check me-2'></i> ĐÃ THÊM GIỎ HÀNG";
            btn.style.background = "#2d3436";

            setTimeout(() => { 
                btn.innerHTML = originalContent; 
                btn.style.background = "";
            }, 2000);

        });

    });

});

    // Tab Scrolling
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            const target = document.querySelector(this.getAttribute('href'));
            window.scrollTo({ top: target.offsetTop - 140, behavior: 'smooth' });
        });
    });
</script>
<script>
    let images = <?php echo json_encode($all_images); ?>;
    let currentIndex = 0;
    let mainImg = document.getElementById("mainImg");

    function showImage(index) {
        currentIndex = index;
        mainImg.src = "images/" + images[currentIndex];
    }

    // Next
    document.querySelector(".next-btn").addEventListener("click", function(){
        currentIndex = (currentIndex + 1) % images.length;
        showImage(currentIndex);
    });

    // Prev (đã sửa thêm dấu trừ để lùi ảnh chính xác)
    document.querySelector(".prev-btn").addEventListener("click", function(){
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        showImage(currentIndex);
    });

    // Click thumbnail
    document.querySelectorAll(".thumb-img").forEach(function(thumb){
        thumb.addEventListener("click", function(){
            showImage(parseInt(this.dataset.index));
        });
    });

    // Tự động chạy 5 giây
    setInterval(function(){
        currentIndex = (currentIndex + 1) % images.length;
        showImage(currentIndex);
    },2000);
</script>



<script>

document.addEventListener("DOMContentLoaded", function () {

    const buttons = document.querySelectorAll(".filter-product");

    buttons.forEach(function(btn){

        btn.addEventListener("click", function(e){

            e.preventDefault();

            let cat = this.dataset.cat;
            let sub = this.dataset.sub;
            let column = this.dataset.columnId;

            let target = document.getElementById("mega-product-list-" + column);

            if(!target) return;

            target.innerHTML =
                '<div class="text-center text-muted py-2">' +
                '<i class="fas fa-spinner fa-spin"></i> Đang tải...' +
                '</div>';

            fetch("load_products.php?cat=" + cat + "&sub=" + sub)

            .then(function(res){
                return res.text();
            })

            .then(function(data){
                target.innerHTML = data;
            })

            .catch(function(){
                target.innerHTML =
                '<p class="text-danger small">Không tải được sản phẩm</p>';
            });

        });

    });

});



document.addEventListener("DOMContentLoaded", function(){

const form = document.getElementById("newsletterForm");

if(form){

form.addEventListener("submit", function(e){

    e.preventDefault();

    const emailInput = form.querySelector("input[name='email']");
    const btn = form.querySelector("button");

    const email = emailInput.value;

    if(!email) return;

    btn.disabled = true;
    btn.innerHTML = "<i class='fas fa-spinner fa-spin'></i>";

    fetch("subscribe.php",{
        method:"POST",
        headers:{
            "Content-Type":"application/x-www-form-urlencoded"
        },
        body:"email=" + encodeURIComponent(email)
    })
    .then(res => res.text())
    .then(data => {

        const result = data.trim();

        if(result === "success"){
            alert("🎉 Đăng ký nhận khuyến mãi thành công!");
            emailInput.value = "";
        }
        else if(result === "exists"){
            alert("📧 Email này đã đăng ký rồi.");
        }
        else if(result === "invalid"){
            alert("❌ Email không hợp lệ.");
        }
        else{
            alert("❌ Lỗi hệ thống.");
        }

    })
    .catch(()=>{
        alert("❌ Không thể kết nối server.");
    })
    .finally(()=>{
        btn.disabled = false;
        btn.innerHTML = "ĐĂNG KÝ";
    });

});

}

});

function resetReviewForm(){
    document.getElementById('parent_id_input').value = 0;
    document.getElementById('reply-title').innerHTML = "Đánh giá sản phẩm";
    document.getElementById('rating_container').style.display = "block";
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
</div>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>