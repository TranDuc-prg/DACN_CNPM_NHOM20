<?php 
require_once 'config/db.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $category = $_POST['category'];
    $content = $_POST['content'];
    $video_url = $_POST['video_url']; // Lấy link video
    $created_at = date('Y-m-d H:i:s');

    $image_url = "";
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "images/"; 
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $new_filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = $new_filename; 
        }
    }

    // Lưu vào Database (Đảm bảo bảng posts đã có cột video_url)
    $stmt = $conn->prepare("INSERT INTO posts (title, category, content, image_url, video_url, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $title, $category, $content, $image_url, $video_url, $created_at);

    if ($stmt->execute()) {
        header("Location: admin.gocsk.php?msg=success");
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
    <title>Thêm bài viết mới & Video - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <style>
        body { background-color: #f4f7f6; }
        .editor-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #34495e; }
        .video-box { 
            background: #fff3cd; 
            border: 1px dashed #ffc107; 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 25px;
        }
    </style>
</head>
<body class="p-4">

<div class="container" style="max-width: 1000px;">
    <div class="editor-container">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h3 class="text-success fw-bold mb-0">🚀 Xuất bản nội dung mới</h3>
            <a href="admin.gocsk.php" class="btn btn-outline-secondary rounded-pill">Quay lại</a>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="form-label">Tiêu đề bài viết / Video ngắn</label>
                <input type="text" name="title" class="form-control form-control-lg" placeholder="Nhập tiêu đề thu hút..." required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label class="form-label">Chuyên mục</label>
                    <select name="category" class="form-select">
                        <option value="Dược phẩm">💊 Dược phẩm</option>
                        <option value="Mẹo sức khỏe">💡 Mẹo sức khỏe</option>
                        <option value="Sống khỏe">🥗 Sống khỏe</option>
                        <option value="Thực phẩm chức năng">🌿 Thực phẩm chức năng</option>
                    </select>
                </div>
                <div class="col-md-6 mb-4">
                    <label class="form-label">Ảnh đại diện (Nên dùng ảnh dọc cho video ngắn)</label>
                    <input type="file" name="image" class="form-control" accept="image/*" required>
                </div>
            </div>

            <div class="video-box">
                <label class="form-label text-dark">
                    <i class="fab fa-youtube text-danger me-2"></i>Link Video YouTube / TikTok
                </label>
                <input type="url" name="video_url" class="form-control border-warning" 
                       placeholder="Ví dụ: https://www.youtube.com/watch?v=...">
                <div class="form-text mt-2 italic text-muted">
                    * Nếu dán link vào đây, bài viết sẽ tự động hiển thị trong mục <b>Video ngắn nổi bật</b>.
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Nội dung chi tiết bài viết</label>
                <textarea name="content" id="summernote"></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light px-4 rounded-pill">Xóa hết</button>
                <button type="submit" class="btn btn-success px-5 fw-bold rounded-pill">ĐĂNG BÀI NGAY</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#summernote').summernote({
            placeholder: 'Viết nội dung kiến thức y khoa chuyên sâu tại đây...',
            height: 400,
            lang: 'vi-VN',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    });
</script>

</body>
</html>
