<?php
/** Helpers dùng chung cho chat trực tiếp. */
function chat_text_length($value) {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}
function chat_text_substr($value, $start, $length) {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}
function chat_json($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function chat_require_staff() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin','staff'], true)) {
        chat_json(['ok'=>false,'error'=>'Bạn cần đăng nhập bằng tài khoản quản trị hoặc nhân viên.'], 401);
    }
}
function chat_get_conversation(mysqli $conn) {
    if (empty($_SESSION['vd_chat_token'])) $_SESSION['vd_chat_token'] = bin2hex(random_bytes(32));
    $token = $_SESSION['vd_chat_token'];
    $stmt = $conn->prepare('SELECT id, status FROM chat_conversations WHERE visitor_token=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Không thể truy vấn chat_conversations. Hãy chạy file SQL nâng cấp chat.');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row;
    $stmt = $conn->prepare("INSERT INTO chat_conversations (visitor_token,status) VALUES (?,'bot')");
    if (!$stmt) throw new RuntimeException('Không thể tạo hội thoại. Hãy chạy file SQL nâng cấp chat.');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return ['id'=>$id, 'status'=>'bot'];
}
function chat_notify_manager(mysqli $conn, int $conversationId, string $message) {
    $configFile = __DIR__ . '/chat_config.php';
    if (!is_file($configFile)) return false;
    $cfg = require $configFile;
    if (!is_array($cfg) || empty($cfg['mail_enabled'])) return false;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) { error_log('Chat email: chưa cài PHPMailer. Chạy composer require phpmailer/phpmailer trong thư mục BANTHUOC.'); return false; }
    require_once $autoload;
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return false;
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string)($cfg['smtp_host'] ?? 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = (string)($cfg['smtp_username'] ?? '');
        $mail->Password = (string)($cfg['smtp_password'] ?? '');
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($cfg['smtp_port'] ?? 587);
        $mail->setFrom($mail->Username, (string)($cfg['mail_from_name'] ?? 'VIET DUC PHARMACY - Chat'));
        $mail->addAddress((string)($cfg['mail_to'] ?? ''));
        $mail->Subject = 'Tin nhắn mới trên chatbot Viet Duc Pharmacy (#' . $conversationId . ')';
        $mail->Body = "Có tin nhắn mới trên website Viet Duc Pharmacy.\n\nMã hội thoại: #{$conversationId}\nNội dung: " . chat_text_substr($message, 0, 500) . "\n\nMở trang quản trị chat sau khi đăng nhập để phản hồi.";
        $mail->AltBody = $mail->Body;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Chat email error: ' . $e->getMessage());
        return false;
    }
}
function chat_insert_message(mysqli $conn, int $conversationId, string $sender, string $message, ?int $staffId = null, int $read = 0) {
    $stmt = $conn->prepare('INSERT INTO chat_messages (conversation_id,sender,staff_id,message,read_by_staff) VALUES (?,?,?,?,?)');
    if (!$stmt) throw new RuntimeException('Không thể lưu tin nhắn. Hãy kiểm tra bảng chat_messages.');
    $stmt->bind_param('isisi', $conversationId, $sender, $staffId, $message, $read);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    $stmt = $conn->prepare('UPDATE chat_conversations SET last_message_at=CURRENT_TIMESTAMP WHERE id=?');
    $stmt->bind_param('i', $conversationId);
    $stmt->execute();
    $stmt->close();
    return $id;
}
