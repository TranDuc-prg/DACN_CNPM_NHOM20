<?php 
require_once 'config/db.php'; 

// 1. Lấy ID từ URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: admin.gocsk.php'); 
    exit();
}

// 2. Truy vấn lấy dữ liệu cũ (bao gồm cả video_url)
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: admin.gocsk.php');
    exit();
}

// 3. Xử lý khi nhấn nút "Lưu cập nhật"
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $content = $_POST['content']; 
    $video_url = $_POST['video_url']; // Lấy giá trị video mới
    $image = $post['image_url'];  // Mặc định giữ tên ảnh cũ

    // Kiểm tra nếu có chọn file ảnh mới
    if (!empty($_FILES['image']['name'])) {
        $image = time() . "_" . $_FILES['image']['name'];
        
        if (!is_dir('images')) { mkdir('images', 0777, true); } 
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], "images/" . $image)) {
            // Xóa ảnh cũ nếu tồn tại và không phải ảnh mặc định
            if ($post['image_url'] && file_exists("images/" . $post['image_url'])) {
                unlink("images/" . $post['image_url']);
            }
        }
    }

    // Cập nhật vào Database (Thêm video_url vào câu lệnh UPDATE)
    $update = $conn->prepare("UPDATE posts SET title=?, category=?, content=?, image_url=?, video_url=? WHERE id=?");
    $update->bind_param("sssssi", $title, $category, $content, $image, $video_url, $id);
    
    if ($update->execute()) {
        header("Location: admin.gocsk.php?msg=updated"); 
        exit();
    } else {
        $error = "Có lỗi xảy ra: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa bài viết - Góc Sức Khỏe</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; }
        .note-editor { background: white !important; border-radius: 8px; }
        .current-img { width: 120px; height: 160px; object-fit: cover; border-radius: 8px; border: 2px solid #27ae60; }
        .form-label { font-weight: 600; color: #2c3e50; }
        .video-edit-box { 
            background: #fff3cd; 
            border: 1px dashed #ffc107; 
            border-radius: 10px; 
            padding: 15px; 
        }
    </style>
</head>
<body class="p-md-5 p-3">
    <div class="container" style="max-width: 950px;">
        <div class="card shadow border-0 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-success fw-bold mb-0"><i class="fas fa-edit me-2"></i>Chỉnh sửa nội dung</h3>
                <a href="admin.gocsk.php" class="btn btn-outline-secondary btn-sm rounded-pill">Quay lại</a>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label">Tiêu đề bài viết</label>
                    <input type="text" name="title" class="form-control form-control-lg" value="<?= htmlspecialchars($post['title']) ?>" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Chuyên mục</label>
                        <select name="category" class="form-select">
                            <option value="Dược phẩm" <?= $post['category'] == 'Dược phẩm' ? 'selected' : '' ?>>💊 Dược phẩm</option>
                            <option value="Mẹo sức khỏe" <?= $post['category'] == 'Mẹo sức khỏe' ? 'selected' : '' ?>>💡 Mẹo sức khỏe</option>
                            <option value="Sống khỏe" <?= $post['category'] == 'Sống khỏe' ? 'selected' : '' ?>>🥗 Sống khỏe</option>
                            <option value="Thực phẩm chức năng" <?= $post['category'] == 'Thực phẩm chức năng' ? 'selected' : '' ?>>🌿 Thực phẩm chức năng</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Ảnh đại diện (Giữ nguyên hoặc thay mới)</label>
                        <div class="d-flex align-items-center gap-3 border p-2 rounded bg-white">
                            <img src="images/<?= $post['image_url'] ?>" 
                                 class="current-img" 
                                 onerror="this.src='https://via.placeholder.com/120x160?text=No+Image'">
                            <div class="flex-grow-1">
                                <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                                <small class="text-muted mt-1 d-block italic">* Để trống nếu không muốn đổi ảnh.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 video-edit-box">
                    <label class="form-label text-dark"><i class="fab fa-youtube text-danger me-2"></i>Link Video YouTube / TikTok (Nếu có)</label>
                    <input type="url" name="video_url" class="form-control" 
                           value="<?= htmlspecialchars($post['video_url'] ?? '') ?>" 
                           placeholder="https://www.youtube.com/watch?v=...">
                    <small class="form-text text-muted mt-1">Xóa link nếu muốn gỡ bài viết khỏi mục "Video ngắn nổi bật".</small>
                </div>

                <div class="mb-4">
                    <label class="form-label">Nội dung chi tiết</label>
                    <textarea name="content" id="summernote" class="form-control"><?= $post['content'] ?></textarea>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="admin.gocsk.php" class="btn btn-light px-4 py-2 text-muted border rounded-pill">Hủy bỏ</a>
                    <button type="submit" class="btn btn-success px-5 py-2 fw-bold shadow-sm rounded-pill">LƯU THAY ĐỔI</button>
                </div>
            </form>
        </div>
    </div>

    <script>
      $(document).ready(function() {
          $('#summernote').summernote({
            placeholder: 'Viết nội dung y khoa tại đây...',
            tabsize: 2,
            height: 400,
            lang: 'vi-VN',
            toolbar: [
              ['style', ['style']],
              ['font', ['bold', 'underline', 'clear']],
              ['color', ['color']],
              ['para', ['ul', 'ol', 'paragraph']],
              ['table', ['table']],
              ['insert', ['link', 'picture', 'video']], 
              ['view', ['fullscreen', 'codeview']]
            ]
          });
      });
    </script>
</body>
</html>