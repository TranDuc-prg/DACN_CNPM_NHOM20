<?php
require_once 'config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Xóa file ảnh trong thư mục trước khi xóa DB (tùy chọn nhưng nên làm)
    $stmt = $conn->prepare("SELECT image_url FROM posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $img = $stmt->get_result()->fetch_assoc()['image_url'];
    if (file_exists("uploads/" . $img)) unlink("uploads/" . $img);

    // Xóa bản ghi
    $del = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
}
header("Location: admin.php");
?>