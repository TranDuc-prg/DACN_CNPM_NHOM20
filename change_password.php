<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/db.php";

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['csrf_change'])) {
    $_SESSION['csrf_change'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_change'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'Vui lòng nhập đầy đủ thông tin.';
        } elseif (strlen($new) < 8) {
            $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif ($new !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } elseif ($current === $new) {
            $error = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        } else {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Không thể xử lý yêu cầu. Vui lòng thử lại.';
            } else {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$row || !password_verify($current, (string)$row['password'])) {
                    $error = 'Mật khẩu hiện tại không chính xác.';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($update) {
                        $update->bind_param("si", $newHash, $userId);
                        if ($update->execute()) {
                            $success = 'Đổi mật khẩu thành công.';
                        } else {
                            $error = 'Đổi mật khẩu thất bại. Vui lòng thử lại.';
                        }
                        $update->close();
                    } else {
                        $error = 'Không thể cập nhật mật khẩu.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đổi mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h3 class="mb-3">Đổi mật khẩu</h3>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_change'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="form-label">Mật khẩu hiện tại</label>
            <input class="form-control mb-3" type="password" name="current_password" required autocomplete="current-password">
            <label class="form-label">Mật khẩu mới</label>
            <input class="form-control mb-3" type="password" name="new_password" minlength="8" required autocomplete="new-password">
            <label class="form-label">Xác nhận mật khẩu mới</label>
            <input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
            <button class="btn btn-primary w-100" type="submit">ĐỔI MẬT KHẨU</button>
        </form>
        <div class="text-center mt-3"><a href="index.php">Quay lại trang chủ</a></div>
    </div></div>
</div>
</body>
</html>
