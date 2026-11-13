<?php
// Tắt hiển thị lỗi trực tiếp để không làm hỏng kết quả trả về của AJAX
error_reporting(0);

// 1. Kết nối Database
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Kiểm tra định dạng email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "invalid";
        exit;
    }

    // 2. Tự động tạo bảng nếu chưa có (để tránh lỗi "Table not found")
    $create_table = "CREATE TABLE IF NOT EXISTS newsletter (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_table);

    // 3. Kiểm tra email đã tồn tại chưa
    $check = $conn->prepare("SELECT id FROM newsletter WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "exists";
    } else {
        // 4. Thêm email mới
        $stmt = $conn->prepare("INSERT INTO newsletter (email) VALUES (?)");
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "error";
        }
    }
} else {
    echo "no_data";
}
?>