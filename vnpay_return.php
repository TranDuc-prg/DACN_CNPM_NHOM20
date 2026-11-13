<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "config/db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');


function shopColumnExists(mysqli $conn, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $st = $conn->prepare($sql);
    if (!$st) return false;
    $st->bind_param("ss", $table, $column);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int)($row['n'] ?? 0) > 0;
}

function shopCurrentUserId(): ?int {
    foreach (['user_id', 'id_user', 'id'] as $key) {
        if (isset($_SESSION[$key]) && is_numeric($_SESSION[$key]) && (int)$_SESSION[$key] > 0) {
            return (int)$_SESSION[$key];
        }
    }
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        foreach (['id', 'user_id'] as $key) {
            if (!empty($_SESSION['user'][$key]) && is_numeric($_SESSION['user'][$key])) {
                return (int)$_SESSION['user'][$key];
            }
        }
    }
    return null;
}

function shopSaveOrder(mysqli $conn, array $info): int {
    $hasUserId = shopColumnExists($conn, 'orders', 'user_id');
    $userId = array_key_exists('user_id', $info)
        ? (($info['user_id'] === null || $info['user_id'] === '') ? null : (int)$info['user_id'])
        : shopCurrentUserId();
    if ($hasUserId) {
        $stmt = $conn->prepare("INSERT INTO orders
            (user_id, fullname, phone, address, products, total_amount, payment_method, status, created_at, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        if (!$stmt) throw new Exception($conn->error);
        $status = $info['status'] ?? 'pending';
        $approvedAt = $status === 'Đã duyệt' ? date('Y-m-d H:i:s') : null;
        $stmt->bind_param("issssdsss", $userId, $info['fullname'], $info['phone'], $info['address'],
            $info['products'], $info['total'], $info['method'], $status, $approvedAt);
    } else {
        $stmt = $conn->prepare("INSERT INTO orders
            (fullname, phone, address, products, total_amount, payment_method, status, created_at, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        if (!$stmt) throw new Exception($conn->error);
        $status = $info['status'] ?? 'pending';
        $approvedAt = $status === 'Đã duyệt' ? date('Y-m-d H:i:s') : null;
        $stmt->bind_param("ssssdsss", $info['fullname'], $info['phone'], $info['address'],
            $info['products'], $info['total'], $info['method'], $status, $approvedAt);
    }
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception($error);
    }
    $orderId = (int)$conn->insert_id;
    $stmt->close();

    if (!empty($info['items'])) {
        $detail = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        if (!$detail) throw new Exception($conn->error);
        foreach ($info['items'] as $item) {
            $pid = (int)$item['id'];
            $qty = (int)$item['qty'];
            $price = (float)$item['price_new'];
            $detail->bind_param("iiid", $orderId, $pid, $qty, $price);
            if (!$detail->execute()) {
                $error = $detail->error;
                $detail->close();
                throw new Exception($error);
            }
        }
        $detail->close();
    }
    return $orderId;
}

$vnp_HashSecret = "ECGVTGMNQJYBVTGMMPHVGLLVKNTMAJQO";
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = [];
foreach ($_GET as $key => $value) {
    if (!in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) $inputData[$key] = $value;
}
ksort($inputData);
$hashParts = [];
foreach ($inputData as $key => $value) $hashParts[] = urlencode($key).'='.urlencode((string)$value);
$secureHash = hash_hmac('sha512', implode('&', $hashParts), $vnp_HashSecret);

$order_status = false;
$error_message = '';
$inserted_id = null;
$order_info = $_SESSION['vnpay_order'] ?? null;

if ($vnp_SecureHash === '' || !hash_equals($secureHash, $vnp_SecureHash)) {
    $error_message = 'Lỗi bảo mật: Chữ ký VNPay không hợp lệ.';
} elseif (($_GET['vnp_ResponseCode'] ?? '') !== '00' || ($_GET['vnp_TransactionStatus'] ?? '00') !== '00') {
    $error_message = 'Giao dịch không thành công. Mã lỗi: '.htmlspecialchars($_GET['vnp_ResponseCode'] ?? 'Không rõ');
} elseif (!$order_info) {
    $error_message = 'Không tìm thấy thông tin đơn hàng trong phiên làm việc. Vui lòng liên hệ quản trị viên.';
} elseif ((string)($_GET['vnp_TxnRef'] ?? '') !== (string)($order_info['txn_ref'] ?? '')) {
    $error_message = 'Mã giao dịch không khớp với đơn hàng đang chờ thanh toán.';
} elseif ((int)($_GET['vnp_Amount'] ?? 0) !== (int)round(((float)$order_info['final_total']) * 100)) {
    $error_message = 'Số tiền VNPay trả về không khớp với số tiền đơn hàng.';
} else {
    try {
        $conn->begin_transaction();
        // Tránh tạo trùng đơn nếu VNPay gọi lại URL kết quả.
        $savedOrderId = (int)($_SESSION['vnpay_saved_order_id'] ?? 0);
        if ($savedOrderId > 0) {
            $inserted_id = $savedOrderId;
        } else {
            $inserted_id = shopSaveOrder($conn, [
                'fullname'=>$order_info['fullname'],
                'phone'=>$order_info['phone'],
                'address'=>$order_info['address'],
                'products'=>$order_info['products'],
                'total'=>(float)$order_info['final_total'],
                'method'=>'VNPAY',
                'status'=>'Đã duyệt',
                'items'=>$order_info['items'] ?? [],
                'user_id'=>$order_info['user_id'] ?? null
            ]);
        }
        $conn->commit();
        // Chỉ ghi nhận ID sau khi transaction đã commit thành công.
        $_SESSION['vnpay_saved_order_id'] = $inserted_id;
        $order_status = true;
        $_SESSION['customer_phone'] = $order_info['phone'];
        $_SESSION['customer_fullname'] = $order_info['fullname'];
        unset($_SESSION['cart'], $_SESSION['discount_percent'], $_SESSION['applied_coupon'], $_SESSION['temp_user'], $_SESSION['vnpay_order']);
    } catch (Throwable $e) {
        $conn->rollback();
        $error_message = 'Đã thanh toán nhưng chưa thể lưu đơn hàng: '.$e->getMessage().'. Vui lòng liên hệ quản trị viên, không thanh toán lại.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kết quả thanh toán VNPay - Viet Duc Pharma</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container" style="max-width:600px">
<div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
<?php if ($order_status): ?>
<i class="fas fa-check-circle text-success mb-4" style="font-size:5rem"></i>
<h2 class="fw-bold text-success mb-3">Thanh toán thành công!</h2>
<p class="text-muted">Cảm ơn bạn đã mua hàng tại <b>Viet Duc Pharmacy</b>.</p>
<div class="bg-light p-3 rounded-3 text-start my-4">
<p><strong>Mã giao dịch VNPay:</strong> <?= htmlspecialchars($_GET['vnp_TransactionNo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
<p><strong>Mã đơn hàng:</strong> #<?= htmlspecialchars($_GET['vnp_TxnRef'] ?? '', ENT_QUOTES, 'UTF-8') ?> (Mã CSDL: #<?= (int)$inserted_id ?>)</p>
<p class="mb-0"><strong>Số tiền:</strong> <span class="text-danger fw-bold"><?= number_format(((int)($_GET['vnp_Amount'] ?? 0))/100, 0, ',', '.') ?> đ</span></p>
</div>
<a href="index.php" class="btn btn-primary rounded-pill">VỀ TRANG CHỦ</a>
<a href="invoice.php?id=<?= (int)$inserted_id ?>" class="btn btn-success rounded-pill" target="_blank">Xuất hóa đơn</a>
<a href="track-order.php" class="btn btn-outline-primary rounded-pill mt-2">Đơn mua của tôi</a>
<?php else: ?>
<i class="fas fa-times-circle text-danger mb-4" style="font-size:5rem"></i>
<h2 class="fw-bold text-danger mb-3">Thanh toán chưa hoàn tất!</h2>
<p class="text-muted"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></p>
<a href="cart.php" class="btn btn-dark rounded-pill">QUAY LẠI GIỎ HÀNG</a>
<?php endif; ?>
</div></div></body></html>
