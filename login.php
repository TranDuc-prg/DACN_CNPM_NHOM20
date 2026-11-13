<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/config/db.php";

if (isset($_SESSION['user_id'])) {
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role === 'admin') {
        header("Location: admin_users.php");
    } elseif ($role === 'staff') {
        header("Location: staff_dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_login'], $csrf)) {
        $err = "Phiên làm việc không hợp lệ. Vui lòng tải lại trang.";
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $err = "Vui lòng nhập đầy đủ thông tin!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = "Email không đúng định dạng!";
        } else {
            $stmt = $conn->prepare(
                "SELECT id, fullname, password, role FROM users WHERE email = ? LIMIT 1"
            );

            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, (string)$user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['fullname'] = $user['fullname'];
                        $_SESSION['customer_name'] = $user['fullname'];
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $user['role'];
                        unset($_SESSION['customer_phone'], $_SESSION['customer_fullname']);

                        $role = strtolower(trim((string)$user['role']));
                        if ($role === 'admin') {
                            header("Location: admin_users.php");
                        } elseif ($role === 'staff') {
                            header("Location: staff_dashboard.php");
                        } else {
                            header("Location: index.php");
                        }
                        $stmt->close();
                        exit;
                    }
                    $err = "Email hoặc mật khẩu không chính xác!";
                } else {
                    // Thông báo chung, tránh tiết lộ email có tồn tại hay không.
                    $err = "Email hoặc mật khẩu không chính xác!";
                }
                $stmt->close();
            } else {
                $err = "Không thể xử lý yêu cầu. Vui lòng thử lại sau.";
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
    <title>Đăng nhập hệ thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="login-box">
    <div class="logo">Viet Duc Pharmacy</div>

    <?php if (!empty($err)): ?>
        <div class="alert alert-danger text-center p-2">
            <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_login'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
            <label class="form-label fw-bold">Email</label>
            <input type="email" name="email" class="form-control"
                   placeholder="example@email.com" autocomplete="username" required>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Mật khẩu</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <div class="text-end mb-3">
            <a href="forgot_password.php" class="text-decoration-none">Quên mật khẩu?</a>
        </div>

        <button type="submit" name="login" value="1" class="btn btn-primary w-100">
            ĐĂNG NHẬP
        </button>

        <div class="text-center mt-3">
            Chưa có tài khoản?
            <a href="register.php" class="fw-bold text-decoration-none">Đăng ký ngay</a>
        </div>
    </form>
</div>
</body>
</html>
