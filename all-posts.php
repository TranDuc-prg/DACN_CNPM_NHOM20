<?php 
require_once 'config/db.php'; 

/**
 * Hàm lấy đường dẫn ảnh bài viết
 */
function getProductImage($image_name) {
    $path = "images/" . $image_name;
    return (!empty($image_name) && file_exists($path)) ? $path : "https://via.placeholder.com/400x250?text=Health+Care";
}

// 1. Cấu hình phân trang
$limit = 12; // 12 bài mỗi trang (3 cột x 4 hàng)
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;

// 2. Lọc theo chuyên mục nếu có
$cat_filter = isset($_GET['category']) ? $_GET['category'] : '';
$where_sql = "";
if ($cat_filter) {
    $where_sql = "WHERE category = '" . $conn->real_escape_string($cat_filter) . "'";
}

// 3. Tính tổng số bài để phân trang
$total_res = $conn->query("SELECT COUNT(id) AS total FROM posts $where_sql");
$total_posts = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $limit);

// 4. Lấy danh sách bài viết
$sql = "SELECT * FROM posts $where_sql ORDER BY id DESC LIMIT $start, $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tất cả bài viết y khoa | Viet Duc Pharmacy – Hệ Thống Nhà Thuốc Hàng Đầu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.post.css">
   
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-success" href="index.php"><i class="fas fa-plus-square me-2"></i>Viet Duc Pharmacy</a>
        <a href="goc-suc-khoe.php" class="btn btn-light rounded-pill px-4 border">
            <i class="fas fa-arrow-left me-2"></i> Quay lại
        </a>
    </div>
</nav>

<div class="page-header text-center">
    <div class="container">
        <h2 class="fw-bold mb-3">Tất cả bài viết sức khỏe</h2>
        <p class="text-muted mb-4">Tìm hiểu kiến thức y khoa chuyên sâu từ các dược sĩ giàu kinh nghiệm</p>
        
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="all-posts.php" class="filter-link <?= $cat_filter == '' ? 'active' : '' ?>">Tất cả</a>
            <a href="all-posts.php?category=Dược phẩm" class="filter-link <?= $cat_filter == 'Dược phẩm' ? 'active' : '' ?>">💊 Dược phẩm</a>
            <a href="all-posts.php?category=Mẹo sức khỏe" class="filter-link <?= $cat_filter == 'Mẹo sức khỏe' ? 'active' : '' ?>">💡 Mẹo sức khỏe</a>
            <a href="all-posts.php?category=Sống khỏe" class="filter-link <?= $cat_filter == 'Sống khỏe' ? 'active' : '' ?>">🥗 Sống khỏe</a>
            <a href="all-posts.php?category=Thực phẩm chức năng" class="filter-link <?= $cat_filter == 'Thực phẩm chức năng' ? 'active' : '' ?>">🌿 TPCN</a>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card card-post">
                    <div class="post-img-wrapper">
                        <span class="category-badge"><?= htmlspecialchars($row['category']) ?></span>
                        <img src="<?= getProductImage($row['image_url']) ?>" alt="Post image">
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3 text-muted small">
                            <span class="me-3"><i class="far fa-calendar-alt me-1"></i> <?= date('d/m/Y', strtotime($row['created_at'])) ?></span>
                            <span><i class="far fa-clock me-1"></i> 3 phút đọc</span>
                        </div>
                        <h5 class="post-title"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="text-secondary small mb-4">
                            <?= mb_substr(strip_tags($row['content']), 0, 100) ?>...
                        </p>
                        <div class="d-grid">
                            <a href="detail.php?id=<?= $row['id'] ?>" class="btn btn-outline-success btn-read-more">
                                Đọc bài viết <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <img src="https://cdn-icons-png.flaticon.com/512/6134/6134065.png" width="100" class="mb-3 opacity-25">
                <p class="text-muted">Không tìm thấy bài viết nào trong chuyên mục này.</p>
                <a href="all-posts.php" class="btn btn-success rounded-pill px-4">Xem tất cả bài viết</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if($total_pages > 1): ?>
    <nav class="mt-5">
        <ul class="pagination justify-content-center">
            <?php if($page > 1): ?>
                <li class="page-item"><a class="page-link" href="all-posts.php?page=<?= $page-1 ?><?= $cat_filter ? '&category='.$cat_filter : '' ?>"><i class="fas fa-chevron-left"></i></a></li>
            <?php endif; ?>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link shadow-sm" href="all-posts.php?page=<?= $i ?><?= $cat_filter ? '&category='.$cat_filter : '' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="all-posts.php?page=<?= $page+1 ?><?= $cat_filter ? '&category='.$cat_filter : '' ?>"><i class="fas fa-chevron-right"></i></a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<footer class="bg-dark text-white py-5 mt-5">
    <div class="container text-center">
        <h4 class="fw-bold text-success mb-3"><i class="fas fa-plus-square me-2"></i>Viet Duc Pharmacy – Hệ Thống Nhà Thuốc Hàng Đầu</h4>
        <p class="text-secondary small mb-0">© 2026 Viet Duc Pharmacy – Hệ Thống Nhà Thuốc Hàng Đầu - Chuyên gia chăm sóc sức khỏe cộng đồng.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>