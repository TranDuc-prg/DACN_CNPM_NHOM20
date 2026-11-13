<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/db.php";

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$error = '';
$success = false;

if (empty($_SESSION['csrf_reset'])) {
    $_SESSION['csrf_reset'] = bin2hex(random_bytes(32));
}

function findValidReset(mysqli $conn, string $tokenHash): ?array {
    $stmt = $conn->prepare(
        "SELECT pr.id AS reset_id, pr.user_id
         FROM password_resets pr
         WHERE pr.token_hash = ? AND pr.expires_at > NOW()
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

$resetRow = $token !== '' ? findValidReset($conn, $tokenHash) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_reset'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif (!$resetRow) {
        $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (strlen($password) < 8) {
            $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $userId = (int)$resetRow['user_id'];
                $update->bind_param("si", $hash, $userId);
                $update->execute();
                $update->close();

                $delete = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $delete->bind_param("i", $userId);
                $delete->execute();
                $delete->close();

                $conn->commit();
                $success = true;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Password reset update error: ' . $e->getMessage());
                $error = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
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
    <title>Đặt lại mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h3 class="mb-3">Đặt lại mật khẩu</h3>
        <?php if ($success): ?>
            <div class="alert alert-success">Đặt lại mật khẩu thành công. Bạn có thể đăng nhập bằng mật khẩu mới.</div>
            <a class="btn btn-primary w-100" href="login.php">Đăng nhập</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if ($resetRow): ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_reset'], ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-label">Mật khẩu mới</label>
                <input class="form-control mb-3" type="password" name="password" minlength="8" required autocomplete="new-password">
                <label class="form-label">Xác nhận mật khẩu mới</label>
                <input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
                <button class="btn btn-primary w-100" type="submit">CẬP NHẬT MẬT KHẨU</button>
            </form>
            <?php elseif (!$error): ?>
                <div class="alert alert-danger">Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>
</div>
</body>
</html>
