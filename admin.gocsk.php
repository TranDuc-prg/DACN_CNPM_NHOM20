<?php
require_once 'config/db.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Xử lý tìm kiếm
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = "";
if (!empty($search)) {
    $where_clause = " WHERE title LIKE '%$search%' OR category LIKE '%$search%'";
}

// Thống kê nhanh (cập nhật theo kết quả tìm kiếm nếu cần, hoặc giữ nguyên tổng)
$total_posts = $conn->query("SELECT id FROM posts")->num_rows;
$total_tips = $conn->query("SELECT id FROM posts WHERE category = 'Mẹo sức khỏe'")->num_rows;
$total_meds = $conn->query("SELECT id FROM posts WHERE category = 'Dược phẩm'")->num_rows;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản lý Góc Sức Khỏe</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="css/admin.gocsk.css?v=2">


</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 d-none d-md-block sidebar p-4">
            <h4 class="text-center mb-4 fw-bold"><i class="fas fa-clinic-medical"></i> QUẢN LÝ GÓC SỨC KHỎE</h4>
            <hr class="text-secondary">
            <ul class="nav flex-column mt-4">
                <li class="nav-item"><a href="index.php" class="nav-link"><i class="fas fa-external-link-alt me-2"></i> Xem Website</a></li>
                <li class="nav-item"><a href="admin.gocsk.php" class="nav-link active"><i class="fas fa-th-large me-2"></i> Dashboard</a></li>
                <li class="nav-item"><a href="add_post.php" class="nav-link"><i class="fas fa-pen-nib me-2"></i> Viết bài mới</a></li>
                <li class="nav-item">
    <a href="manage_videos.php" class="nav-link"><i class="fas fa-video me-2"></i> Quản lý Video</a>
</li>
                <li class="nav-item mt-4"><a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-2"></i> Đăng xuất</a></li>
            </ul>
        </div>

        <div class="col-md-10 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-0">Quản lý Góc Sức Khỏe</h3>
                    <p class="text-muted small">Chào mừng bạn trở lại trang quản trị nội dung.</p>
                </div>
                <a href="add_post.php" class="btn btn-success px-4 rounded-pill shadow-sm py-2">
                    <i class="fas fa-plus me-2"></i> Tạo bài viết mới
                </a>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card bg-white p-3 border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small fw-bold">TỔNG BÀI VIẾT</p>
                                <h2 class="mb-0 fw-bold"><?= $total_posts ?></h2>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                                <i class="fas fa-newspaper fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card bg-white p-3 border-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small fw-bold">MẸO SỨC KHỎE</p>
                                <h2 class="mb-0 fw-bold"><?= $total_tips ?></h2>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                                <i class="fas fa-lightbulb fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card bg-white p-3 border-warning">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1 small fw-bold">DƯỢC PHẨM</p>
                                <h2 class="mb-0 fw-bold"><?= $total_meds ?></h2>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                                <i class="fas fa-capsules fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container shadow-sm">
                <div class="row mb-4 align-items-center">
                    <div class="col-md-6">
                        <h5 class="fw-bold mb-0">Tất cả bài đăng</h5>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" action="admin.gocsk.php" class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="form-control" placeholder="Tìm tiêu đề hoặc chuyên mục..." value="<?= htmlspecialchars($search) ?>">
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr class="text-muted small">
                                <th class="ps-3">ẢNH</th>
                                <th>NỘI DUNG BÀI VIẾT</th>
                                <th>CHUYÊN MỤC</th>
                                <th>THỜI GIAN</th>
                                <th class="text-end pe-3">HÀNH ĐỘNG</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM posts $where_clause ORDER BY id DESC LIMIT 15";
                            $res = $conn->query($sql);
                            if($res->num_rows > 0):
                                while($row = $res->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="ps-3">
<img src="images/<?= $row['image_url'] ?>" class="post-img-preview" onerror="this.src='https://via.placeholder.com/65x48?text=No+Img'">                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($row['title']) ?></div>
                                    <span class="text-muted" style="font-size: 0.75rem;">Mã bài viết: #<?= $row['id'] ?></span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill badge-category"><?= $row['category'] ?></span>
                                </td>
                                <td>
                                    <div class="small"><i class="far fa-calendar-alt me-1"></i> <?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i> <?= date('H:i', strtotime($row['created_at'])) ?></div>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group">
                                        <a href="edit_post.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3 me-2">
                                            <i class="fas fa-edit me-1"></i> Sửa
                                        </a>
                                        <a href="delete_post.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Bạn có chắc chắn muốn xóa bài viết này không?')">
                                            <i class="fas fa-trash me-1"></i> Xóa
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            else:
                            ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Không tìm thấy bài viết nào phù hợp.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <nav class="mt-4">
                    <ul class="pagination pagination-sm justify-content-end mb-0">
                        <li class="page-item disabled"><a class="page-item page-link" href="#">Trước</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">Tiếp</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>