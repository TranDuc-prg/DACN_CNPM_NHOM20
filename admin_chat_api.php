<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/chat_helpers.php';
chat_require_staff();

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'list') {
        $sql = "SELECT c.id, c.status, c.visitor_name, c.last_message_at, c.created_at, c.assigned_to,
              (SELECT m.message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) last_message,
              (SELECT COUNT(*) FROM chat_messages u WHERE u.conversation_id = c.id AND u.sender = 'visitor' AND u.read_by_staff = 0) unread
              FROM chat_conversations c ORDER BY c.last_message_at DESC LIMIT 100";
        $res = $conn->query($sql); 
        $rows = []; 
        while ($r = $res->fetch_assoc()) {
            // Fallback display if visitor_name is empty/null
            if (empty($r['visitor_name'])) {
                $r['visitor_name'] = 'Khách vãng lai #' . $r['id'];
            }
            $rows[] = $r;
        }
        chat_json(['ok' => true, 'conversations' => $rows]);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sentCsrf = (string)($body['csrf'] ?? '');
        $sessionCsrf = (string)($_SESSION['chat_csrf'] ?? '');
        if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $sentCsrf)) {
            chat_json(['ok' => false, 'error' => 'Phiên làm việc hết hạn. Hãy tải lại trang chat.'], 403);
        }
    }

    $cid = max(0, (int)($body['conversation_id'] ?? $_GET['conversation_id'] ?? 0));
    if (!$cid) {
        chat_json(['ok' => false, 'error' => 'Thiếu mã hội thoại.'], 400);
    }

    // NEW ACTION: Update visitor name explicitly if requested
    if ($action === 'update_name') {
        $visitorName = trim((string)($body['visitor_name'] ?? ''));
        if ($visitorName !== '') {
            $stmt = $conn->prepare('UPDATE chat_conversations SET visitor_name = ? WHERE id = ?');
            $stmt->bind_param('si', $visitorName, $cid);
            $stmt->execute();
            $stmt->close();
            chat_json(['ok' => true, 'visitor_name' => $visitorName]);
        }
        chat_json(['ok' => false, 'error' => 'Tên không hợp lệ.'], 400);
    }

    if ($action === 'messages') {
        $after = max(0, (int)($_GET['after'] ?? 0));
        $stmt = $conn->prepare('SELECT id, sender, staff_id, message, created_at FROM chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 200');
        $stmt->bind_param('ii', $cid, $after); 
        $stmt->execute(); 
        $res = $stmt->get_result(); 
        $rows = []; 
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r; 
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE chat_messages SET read_by_staff = 1 WHERE conversation_id = ? AND sender = 'visitor'"); 
        $stmt->bind_param('i', $cid); 
        $stmt->execute(); 
        $stmt->close();

        $stmt = $conn->prepare('SELECT status, visitor_name FROM chat_conversations WHERE id = ?'); 
        $stmt->bind_param('i', $cid); 
        $stmt->execute(); 
        $convData = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        $mode = $convData['status'] ?? 'closed';
        $visitorName = $convData['visitor_name'] ?? '';

        chat_json(['ok' => true, 'messages' => $rows, 'mode' => $mode, 'visitor_name' => $visitorName]);
    }

    if ($action === 'reply') {
        $message = trim((string)($body['message'] ?? ''));
        if ($message === '' || chat_text_length($message) > 3000) {
            chat_json(['ok' => false, 'error' => 'Tin nhắn phải có từ 1 đến 3.000 ký tự.'], 400);
        }
        $stmt = $conn->prepare("UPDATE chat_conversations SET status = 'human', assigned_to = ? WHERE id = ?"); 
        $staffId = (int)$_SESSION['user_id']; 
        $stmt->bind_param('ii', $staffId, $cid); 
        $stmt->execute(); 
        $stmt->close();

        $id = chat_insert_message($conn, $cid, 'staff', $message, $staffId, 1);
        chat_json(['ok' => true, 'message_id' => $id, 'mode' => 'human']);
    }

    if ($action === 'mode') {
        $mode = (string)($body['mode'] ?? '');
        if (!in_array($mode, ['bot', 'human', 'closed'], true)) {
            chat_json(['ok' => false, 'error' => 'Trạng thái không hợp lệ.'], 400);
        }
        $staffId = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare('UPDATE chat_conversations SET status = ?, assigned_to = ? WHERE id = ?');
        $assigned = ($mode === 'human') ? $staffId : null; 
        $stmt->bind_param('sii', $mode, $assigned, $cid); 
        $stmt->execute(); 
        $stmt->close();
        chat_json(['ok' => true, 'mode' => $mode]);
    }

    chat_json(['ok' => false, 'error' => 'Hành động không hợp lệ.'], 400);

} catch (Throwable $e) {
    error_log('Admin chat API: ' . $e->getMessage());
    chat_json(['ok' => false, 'error' => 'Không xử lý được yêu cầu chat. Hãy kiểm tra SQL nâng cấp và log PHP.'], 500);
}