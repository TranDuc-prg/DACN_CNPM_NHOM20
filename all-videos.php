<?php 
require_once 'config/db.php'; 

function getProductImage($image_name) {
    $path = "images/" . $image_name;
    return (!empty($image_name) && file_exists($path)) ? $path : "https://via.placeholder.com/300x500?text=No+Video+Thumb";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tất cả Video Sức Khỏe</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="css/all.videos.css">
</head>
<body>

<div class="container py-5">
    <div class="d-flex align-items-center mb-5">
        <a href="goc-suc-khoe.php" class="btn btn-outline-secondary rounded-pill me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="fw-bold mb-0">Tất cả Video Nổi Bật</h2>
    </div>

    <div class="row">
        <?php 
        // Lấy tất cả bài viết có video
        $sql = "SELECT * FROM posts WHERE video_url != '' ORDER BY id DESC";
        $result = $conn->query($sql);
        
        if($result && $result->num_rows > 0):
            while($row = $result->fetch_assoc()):
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="video-grid-card">
                <a href="detail.php?id=<?= $row['id'] ?>">
                    <span class="badge-video"><i class="fas fa-play me-1"></i>VIDEO</span>
                    <img src="<?= getProductImage($row['image_url']) ?>" alt="Thumbnail">
                    <div class="video-overlay">
                        <p class="video-title text-truncate-2"><?= htmlspecialchars($row['title']) ?></p>
                    </div>
                </a>
            </div>
        </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">Chưa có video nào được cập nhật.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>