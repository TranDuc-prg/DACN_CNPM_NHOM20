<?php 
require_once 'config/db.php'; 

// Xử lý tìm kiếm trong danh mục video
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = " WHERE video_url != '' ";
if (!empty($search)) {
    $where_clause .= " AND (title LIKE '%$search%' OR category LIKE '%$search%')";
}

// Lấy danh sách video
$sql = "SELECT * FROM posts $where_clause ORDER BY id DESC";
$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Video Shorts | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/manage_videos.css?v=<?= time() ?>">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 d-none d-md-block sidebar p-4">
            <h4 class="text-center mb-4 fw-bold text-success">QUẢN LÝ GÓC SỨC KHỎE</h4>
            <ul class="nav flex-column mt-4">
                <li class="nav-item"><a href="admin.gocsk.php" class="nav-link"><i class="fas fa-chart-line me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="manage_videos.php" class="nav-link active"><i class="fas fa-video me-2"></i> Quản lý Video</a></li>
                <li class="nav-item"><a href="add_post.php" class="nav-link"><i class="fas fa-plus-circle me-2"></i> Thêm mới</a></li>
                <li class="nav-item mt-5"><a href="index.php" class="nav-link"><i class="fas fa-external-link-alt me-2"></i> Xem Web</a></li>
            </ul>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold"><i class="fas fa-bolt text-warning me-2"></i>Quản lý Video Shorts</h3>
                    <p class="text-muted small">Hiển thị danh sách các bài viết có đính kèm video ngắn.</p>
                </div>
                <div class="d-flex gap-2">
                    <form class="position-relative">
                        <input type="text" name="search" class="form-control rounded-pill px-4" placeholder="Tìm video..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn position-absolute end-0 top-0 text-muted"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>

            <div class="row g-4">
                <?php if($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="video-manage-card">
                            <div class="video-thumbnail-wrapper">
                                <span class="video-badge"><i class="fas fa-play me-1"></i> VIDEO</span>
                                <img src="images/<?= $row['image_url'] ?>" onerror="this.src='https://via.placeholder.com/300x500?text=No+Thumb'">
                                <div class="play-overlay shadow">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                            <div class="video-info">
                                <div class="text-primary small fw-bold mb-1"><?= $row['category'] ?></div>
                                <div class="video-title"><?= htmlspecialchars($row['title']) ?></div>
                                <div class="text-muted small mt-2">
                                    <i class="far fa-calendar-alt me-1"></i> <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                                </div>
                            </div>
                            <div class="action-btns">
                                <a href="edit_post.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning flex-fill rounded-pill">
                                    <i class="fas fa-edit me-1"></i> Sửa
                                </a>
                                <a href="<?= $row['video_url'] ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-fill rounded-pill">
                                    <i class="fas fa-eye me-1"></i> Xem
                                </a>
                                <a href="delete_post.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('Xóa video này?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <div class="display-1 text-muted opacity-25 mb-3"><i class="fas fa-video-slash"></i></div>
                        <h4>Chưa có video nào được đăng</h4>
                        <a href="add_post.php" class="btn btn-success mt-3 rounded-pill px-4">Đăng video ngay</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>