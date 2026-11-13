<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['csrf_forgot'])) {
    $_SESSION['csrf_forgot'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_forgot'], $postedCsrf)) {
        $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
        } else {
            // Thong bao chung, khong tiet lo email co ton tai hay khong.
            $message = 'Nếu email tồn tại trong hệ thống, hướng dẫn đặt lại mật khẩu sẽ được gửi đến email đó. Vui lòng kiểm tra cả hộp thư Spam.';

            try {
                $stmt = $conn->prepare(
                    'SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1'
                );

                if (!$stmt) {
                    throw new RuntimeException('Không thể chuẩn bị truy vấn tài khoản: ' . $conn->error);
                }

                $stmt->bind_param('s', $email);

                if (!$stmt->execute()) {
                    throw new RuntimeException('Không thể truy vấn tài khoản: ' . $stmt->error);
                }

                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user) {
                    // Tao token ngau nhien; chi luu hash vao database.
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 1800);
                    $userId = (int)$user['id'];

                    // Tao token moi va xoa token cu cua tai khoan.
                    $conn->begin_transaction();

                    try {
                        $delete = $conn->prepare(
                            'DELETE FROM password_resets WHERE user_id = ?'
                        );

                        if (!$delete) {
                            throw new RuntimeException('Không thể chuẩn bị xóa token cũ: ' . $conn->error);
                        }

                        $delete->bind_param('i', $userId);

                        if (!$delete->execute()) {
                            throw new RuntimeException('Không thể xóa token cũ: ' . $delete->error);
                        }
                        $delete->close();

                        $insert = $conn->prepare(
                            'INSERT INTO password_resets (user_id, token_hash, expires_at)
                             VALUES (?, ?, ?)'
                        );

                        if (!$insert) {
                            throw new RuntimeException('Không thể chuẩn bị lưu token: ' . $conn->error);
                        }

                        $insert->bind_param('iss', $userId, $tokenHash, $expiresAt);

                        if (!$insert->execute()) {
                            throw new RuntimeException('Không thể lưu token: ' . $insert->error);
                        }
                        $insert->close();

                        $conn->commit();
                    } catch (Throwable $e) {
                        $conn->rollback();
                        throw $e;
                    }

                    try {
                        // Su dung cau hinh SMTP da co trong du an.
                        $mailConfigPath = __DIR__ . '/config/chat_config.php';
                        $autoloadPath = __DIR__ . '/vendor/autoload.php';

                        if (!is_file($mailConfigPath)) {
                            throw new RuntimeException('Không tìm thấy config/chat_config.php');
                        }

                        if (!is_file($autoloadPath)) {
                            throw new RuntimeException('Không tìm thấy vendor/autoload.php. Hãy cài PHPMailer bằng Composer.');
                        }

                        $mailConfig = require $mailConfigPath;

                        $requiredKeys = [
                            'mail_enabled',
                            'smtp_host',
                            'smtp_port',
                            'smtp_username',
                            'smtp_password',
                        ];

                        foreach ($requiredKeys as $key) {
                            if (!array_key_exists($key, $mailConfig) || $mailConfig[$key] === '' || $mailConfig[$key] === null) {
                                throw new RuntimeException('Thiếu cấu hình SMTP: ' . $key);
                            }
                        }

                        if ($mailConfig['mail_enabled'] !== true) {
                            throw new RuntimeException('Chức năng gửi email đang bị tắt trong chat_config.php');
                        }

                        require_once $autoloadPath;

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = (string)$mailConfig['smtp_host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = (string)$mailConfig['smtp_username'];
                        $mail->Password = (string)$mailConfig['smtp_password'];
                        $mail->Port = (int)$mailConfig['smtp_port'];
                        $mail->CharSet = 'UTF-8';
                        $mail->Timeout = 20;

                        // Cau hinh Gmail: port 465 dung SMTPS, port 587 dung STARTTLS.
                        if ((int)$mailConfig['smtp_port'] === 465) {
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        } else {
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        }

                        $fromName = (string)($mailConfig['mail_from_name'] ?? 'VIET DUC PHARMACY');

                        $mail->setFrom(
                            (string)$mailConfig['smtp_username'],
                            $fromName
                        );

                        // Gui den email cua tai khoan dang yeu cau khoi phuc.
                        $mail->addAddress(
                            (string)$user['email'],
                            (string)$user['fullname']
                        );

                        // Tao URL dua tren thu muc hien tai cua forgot_password.php.
                        $https = (
                            !empty($_SERVER['HTTPS']) &&
                            strtolower((string)$_SERVER['HTTPS']) !== 'off'
                        );
                        $scheme = $https ? 'https' : 'http';
                        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
                        $scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
                        $scriptDirectory = rtrim($scriptDirectory, '/');

                        $baseUrl = $scheme . '://' . $host . $scriptDirectory;
                        $resetUrl = $baseUrl . '/reset_password.php?token=' . urlencode($token);

                        $safeName = htmlspecialchars(
                            (string)$user['fullname'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        $safeUrl = htmlspecialchars(
                            $resetUrl,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        $mail->isHTML(true);
                        $mail->Subject = 'Dat lai mat khau - VIET DUC PHARMACY';
                        $mail->Body = '
                            <div style="font-family:Arial,sans-serif;line-height:1.6">
                                <h2>VIET DUC PHARMACY</h2>
                                <p>Xin chào ' . $safeName . ',</p>
                                <p>Bạn đã yêu cầu đặt lại mật khẩu tài khoản.</p>
                                <p>
                                    <a href="' . $safeUrl . '"
                                       style="display:inline-block;background:#0d6efd;color:#fff;
                                       padding:12px 20px;text-decoration:none;border-radius:5px">
                                        ĐẶT LẠI MẬT KHẨU
                                    </a>
                                </p>
                                <p>Liên kết có hiệu lực trong 30 phút và chỉ sử dụng được một lần.</p>
                                <p>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email.</p>
                            </div>
                        ';
                        $mail->AltBody = 'Lien ket dat lai mat khau (hieu luc 30 phut): ' . $resetUrl;

                        $mail->send();
                        error_log('Password reset email sent successfully for user_id=' . $userId);
                    } catch (Throwable $mailError) {
                        // Neu gui email that bai, xoa token vua tao de tranh token khong gui duoc.
                        $cleanup = $conn->prepare(
                            'DELETE FROM password_resets WHERE user_id = ? AND token_hash = ?'
                        );

                        if ($cleanup) {
                            $cleanup->bind_param('is', $userId, $tokenHash);
                            $cleanup->execute();
                            $cleanup->close();
                        }

                        error_log('Password reset email error: ' . $mailError->getMessage());
                    }
                }
            } catch (Throwable $e) {
                error_log('Forgot password process error: ' . $e->getMessage());
                // Khong hien thi chi tiet loi database/SMTP cho nguoi dung.
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
    <title>Quên mật khẩu - VIET DUC PHARMACY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:520px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h3 class="mb-3">Quên mật khẩu</h3>
            <p class="text-muted">Nhập email đã đăng ký để nhận liên kết đặt lại mật khẩu.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_forgot'], ENT_QUOTES, 'UTF-8') ?>">

                <label class="form-label" for="email">Email</label>
                <input id="email" class="form-control mb-3" type="email"
                       name="email" required autocomplete="email"
                       placeholder="example@email.com">

                <button class="btn btn-primary w-100" type="submit">
                    GỬI LIÊN KẾT ĐẶT LẠI
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="login.php">Quay lại đăng nhập</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
