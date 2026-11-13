<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/chat_helpers.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
try {
    $conv = chat_get_conversation($conn);
    $cid = (int)$conv['id'];
    // Giai phong khoa session som de cac request cung phien khong phai cho nhau.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if ($action === 'init') {
        $stmt = $conn->prepare('SELECT id,sender,message,created_at FROM chat_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 80');
        $stmt->bind_param('i',$cid); $stmt->execute(); $res=$stmt->get_result(); $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r;
        $stmt->close(); $rows=array_reverse($rows);
        chat_json(['ok'=>true,'conversation_id'=>$cid,'mode'=>$conv['status'],'messages'=>$rows,'last_id'=>$rows ? (int)end($rows)['id'] : 0]);
    }
    if ($action === 'send') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chat_json(['ok'=>false,'error'=>'Phương thức không hợp lệ.'],405);
        $body=json_decode(file_get_contents('php://input'),true) ?: $_POST;
        $message=trim((string)($body['message'] ?? ''));
        if ($message==='' || chat_text_length($message)>1500) chat_json(['ok'=>false,'error'=>'Tin nhắn phải có từ 1 đến 1.500 ký tự.'],400);
        $countStmt=$conn->prepare('SELECT COUNT(*) n FROM chat_messages WHERE conversation_id=?'); $countStmt->bind_param('i',$cid); $countStmt->execute(); $count=(int)$countStmt->get_result()->fetch_assoc()['n']; $countStmt->close();
        if ($conv['status']==='closed') {
            $stmt=$conn->prepare("UPDATE chat_conversations SET status='bot', assigned_to=NULL WHERE id=?"); $stmt->bind_param('i',$cid); $stmt->execute(); $stmt->close(); $conv['status']='bot';
        }
        $id=chat_insert_message($conn,$cid,'visitor',$message,null,0);
        // Gui email moi khi khach gui tin nhan, de nhan vien khong bo lo
        // tin nhan trong ca hoi thoai dang o che do AI.
        $sent = chat_notify_manager($conn, $cid, $message);
        error_log("Chat email: conversation #{$cid}; send result=" . ($sent ? 'true' : 'false'));
        chat_json(['ok'=>true,'message_id'=>$id,'mode'=>$conv['status']]);
    }
    if ($action === 'ai_reply') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') chat_json(['ok'=>false,'error'=>'Phương thức không hợp lệ.'],405);
        $body=json_decode(file_get_contents('php://input'),true) ?: $_POST;
        $message=trim((string)($body['message'] ?? ''));
        if ($message==='') chat_json(['ok'=>false,'error'=>'Tin nhắn AI trống.'],400);
        $stmt=$conn->prepare('SELECT status FROM chat_conversations WHERE id=?'); $stmt->bind_param('i',$cid); $stmt->execute(); $current=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$current || $current['status']!=='bot') chat_json(['ok'=>true,'accepted'=>false,'mode'=>$current['status'] ?? 'closed']);
        $id=chat_insert_message($conn,$cid,'ai',$message,null,1);
        chat_json(['ok'=>true,'accepted'=>true,'message_id'=>$id,'mode'=>'bot']);
    }
    if ($action === 'poll') {
        $after=max(0,(int)($_GET['after'] ?? 0));
        $stmt=$conn->prepare('SELECT id,sender,message,created_at FROM chat_messages WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 100');
        $stmt->bind_param('ii',$cid,$after); $stmt->execute(); $res=$stmt->get_result(); $rows=[];
        while($r=$res->fetch_assoc()) $rows[]=$r; $stmt->close();
        $stmt=$conn->prepare('SELECT status FROM chat_conversations WHERE id=?'); $stmt->bind_param('i',$cid); $stmt->execute(); $mode=$stmt->get_result()->fetch_assoc()['status'] ?? 'bot'; $stmt->close();
        chat_json(['ok'=>true,'mode'=>$mode,'messages'=>$rows]);
    }
    chat_json(['ok'=>false,'error'=>'Hành động không hợp lệ.'],400);
} catch (Throwable $e) {
    error_log('Chat live API: '.$e->getMessage());
    chat_json(['ok'=>false,'error'=>'Chức năng chat trực tiếp chưa sẵn sàng. Hãy nhập file sql/chat_upgrade.sql vào shop_db.'],503);
}
