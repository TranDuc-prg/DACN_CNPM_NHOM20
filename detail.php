<?php 
require_once 'config/db.php'; 

// 1. Lấy ID từ URL an toàn
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. Truy vấn chi tiết bài viết
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

// 3. Nếu không tìm thấy bài viết, quay về trang chủ
if (!$post) {
    header("Location: index.php");
    exit();
}

// 4. Hàm xử lý Link Video YouTube (Chuyển link thường sang link nhúng Embed)
function getYoutubeEmbedUrl($url) {
    $youtube_id = '';
    // Regex hỗ trợ cả link thường, link shorts và link rút gọn youtu.be
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/|youtube\.com/shorts/)([^"&?/ ]{11})%i', $url, $match)) {
        $youtube_id = $match[1];
    }
    return $youtube_id ? "https://www.youtube.com/embed/" . $youtube_id : null;
}

$embed_url = getYoutubeEmbedUrl($post['video_url']);

// 5. Lấy các bài viết liên quan (cùng chuyên mục)
$related_stmt = $conn->prepare("SELECT * FROM posts WHERE category = ? AND id != ? ORDER BY id DESC LIMIT 3");
$related_stmt->bind_param("si", $post['category'], $id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> | Viet Duc Pharmacy – Hệ Thống Nhà Thuốc Hàng Đầu</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
     <link rel="stylesheet" href="css/detail.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-success" href="index.php"><i class="fas fa-plus-square me-2"></i>VIET DUC PHARMACY</a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-light rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Quay lại</a>
        </div>
    </div>
</nav>

<header class="post-header text-center">
    <div class="container">
        <span class="category-badge"><?= htmlspecialchars($post['category']) ?></span>
        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
        <div class="text-muted mt-3 small">
            <i class="far fa-calendar-alt me-2"></i><?= date('d/m/Y', strtotime($post['created_at'])) ?>
            <span class="mx-2">|</span>
            <i class="far fa-eye me-2"></i>Kiến thức y khoa
        </div>
    </div>
</header>

<main class="container my-5">
    <div class="row">
        <div class="col-lg-8">
            <?php if ($embed_url): ?>
                <div class="video-wrapper">
                    <iframe src="<?= $embed_url ?>" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                </div>
            <?php else: ?>
                <img src="images/<?= $post['image_url'] ?>" class="main-img" onerror="this.src='https://via.placeholder.com/800x450'">
            <?php endif; ?>

            <article class="content-area">
                <?= $post['content'] ?>
            </article>

            <div class="mt-5 p-4 bg-light rounded-4 d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="fas fa-share-alt me-2 text-success"></i>Chia sẻ bài viết:</span>
                <div class="d-flex gap-2">
                    <a 
href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://WebThuocVietDucPharmacy.com/detail.php?id='.$post['id']) ?>" 
target="_blank"
class="btn btn-primary rounded-pill btn-sm px-3">
<i class="fab fa-facebook-f me-1"></i> Facebook
</a>
                    <a href="#" class="btn btn-dark rounded-pill btn-sm px-3">Copy Link</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-5 mt-lg-0">
            <div class="sidebar-consult shadow-sm text-center">
                <img src="https://cdn-icons-png.flaticon.com/512/3304/3304567.png" width="70" class="mb-3">
                <h5 class="fw-bold">Bạn cần hỗ trợ?</h5>
                <p class="text-muted small px-2">Kết nối trực tiếp với dược sĩ chuyên môn để giải đáp thắc mắc về sức khỏe.</p>
                <div class="d-grid gap-2 mt-4">
                    <a href="https://zalo.me/0366388104" class="btn btn-success py-2 fw-bold rounded-pill">ZALO DƯỢC SĨ</a>
                    <a href="tel:1900xxxx" class="btn btn-outline-dark py-2 fw-bold rounded-pill">1900.XXXX</a>
                </div>
            </div>
        </div>
    </div>
</main>

<section class="related-section">
    <div class="container">
        <h4 class="fw-bold mb-4">Bài viết liên quan</h4>
        <div class="row">
            <?php if($related_result->num_rows > 0): ?>
                <?php while($related = $related_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 related-card shadow-sm">
                        <img src="images/<?= $related['image_url'] ?>" class="card-img-top" style="height: 180px; object-fit: cover;" onerror="this.src='https://via.placeholder.com/400x250'">
                        <div class="card-body">
                            <h6 class="fw-bold"><a href="detail.php?id=<?= $related['id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($related['title']) ?></a></h6>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted italic">Đang cập nhật...</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="bg-dark text-white py-4 text-center">
    <p class="mb-0 small">© 2026 Viet Duc Pharmacy - Góc Sức Khỏe</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>