<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Ưu tiên tài khoản đã đăng nhập; khách vãng lai dùng số điện thoại trong session.
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$customerPhone = trim((string)($_SESSION['customer_phone'] ?? ''));

$statusMap = [
    'pending' => ['text' => 'Chờ xác nhận', 'icon' => 'bi-clock-history', 'color' => 'warning'],
    'processing' => ['text' => 'Đang chuẩn bị', 'icon' => 'bi-box-seam', 'color' => 'info'],
    'shipping' => ['text' => 'Đang giao', 'icon' => 'bi-truck', 'color' => 'primary'],
    'completed' => ['text' => 'Hoàn thành', 'icon' => 'bi-check-circle-fill', 'color' => 'success'],
    'cancelled' => ['text' => 'Đã hủy', 'icon' => 'bi-x-circle-fill', 'color' => 'danger'],
    // Tương thích dữ liệu cũ trong database.
    'Đã duyệt' => ['text' => 'Đã duyệt', 'icon' => 'bi-check-circle-fill', 'color' => 'success']
];

$tabs = [
    'all' => 'Tất cả',
    'pending' => 'Chờ xác nhận',
    'processing' => 'Đang chuẩn bị',
    'shipping' => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$orders = [];
$hasAccountLink = false;

// Kiểm tra cột user_id đã được thêm vào orders chưa.
$columnCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'user_id'");
$hasAccountLink = ($columnCheck && $columnCheck->num_rows > 0);

// Hủy đơn: chỉ cho phép hủy đơn thuộc tài khoản hiện tại hoặc số điện thoại guest.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $success = false;

    if ($orderId > 0 && $userId > 0 && $hasAccountLink) {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $orderId, $userId);
            $stmt->execute();
            $success = $stmt->affected_rows > 0;
            $stmt->close();
        }
    } elseif ($orderId > 0 && $customerPhone !== '') {
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE id = ? AND phone = ? AND status = 'pending'
        ");
        if ($stmt) {
            $stmt->bind_param("is", $orderId, $customerPhone);
            $stmt->execute();
            $success = $stmt->affected_rows > 0;
            $stmt->close();
        }
    }

    header("Location: track-order.php?tab=all&message=" . ($success ? 'cancel_success' : 'cancel_failed'));
    exit;
}

// Lấy đơn hàng theo user_id khi đã đăng nhập; không dựa vào session phone dễ mất khi đăng xuất.
if ($userId > 0 && $hasAccountLink) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }
} elseif ($customerPhone !== '') {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE phone = ? ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param("s", $customerPhone);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }
}

$counts = array_fill_keys(array_keys($tabs), 0);
$counts['all'] = count($orders);
foreach ($orders as $order) {
    $status = (string)$order['status'];
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

$activeTab = $_GET['tab'] ?? 'all';
if (!isset($tabs[$activeTab])) {
    $activeTab = 'all';
}
$filteredOrders = array_filter($orders, function ($order) use ($activeTab) {
    return $activeTab === 'all' || $order['status'] === $activeTab;
});

// Trạng thái không nằm trong các tab chuẩn vẫn hiển thị trong Tất cả.
$tabIcons = [
    'all' => 'bi-grid-fill',
    'pending' => 'bi-clock-history',
    'processing' => 'bi-box-seam',
    'shipping' => 'bi-truck',
    'completed' => 'bi-check-circle-fill',
    'cancelled' => 'bi-x-circle-fill'
];
?>


<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Đơn mua của tôi - Viet Duc Pharmacy</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
body {
    background: #f5f5f5;
    font-family: Arial, sans-serif;
}

.page {
    max-width: 1150px;
    margin: auto;
}

.page-title {
    font-size: 25px;
    font-weight: 700;
}

.order-tabs {
    display: flex;
    overflow-x: auto;
    background: #fff;
    border-radius: 12px;
    margin: 22px 0;
    white-space: nowrap;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
    scrollbar-width: none;
}

.order-tabs::-webkit-scrollbar {
    display: none;
}

.order-tabs a {
    flex: 1;
    min-width: 145px;
    padding: 17px 10px;
    text-align: center;
    text-decoration: none;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all .25s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    font-size: 14px;
}

.order-tabs a i {
    font-size: 22px;
    transition: transform .25s ease;
}

.order-tabs a:hover {
    background: #f8faff;
    color: #0d6efd;
}

.order-tabs a:hover i {
    transform: translateY(-3px);
}

.order-tabs a.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background: #f4f8ff;
    font-weight: 700;
}

.tab-count {
    font-size: 12px;
    color: #888;
    font-weight: 500;
}

.order-tabs a.active .tab-count {
    color: #0d6efd;
}
.order-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 14px;
}

.order-id {
    font-weight: 700;
}

.order-info {
    padding: 15px 0;
    color: #555;
}

.order-info p {
    margin-bottom: 9px;
}

.order-footer {
    border-top: 1px solid #eee;
    padding-top: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.total-price {
    color: #ee4d2d;
    font-size: 21px;
    font-weight: 700;
}

.empty-box {
    background: white;
    padding: 65px 20px;
    border-radius: 10px;
    text-align: center;
}

.empty-box i {
    font-size: 55px;
    color: #bbb;
}

.status-warning { color: #e5a000; }
.status-info { color: #0dcaf0; }
.status-primary { color: #0d6efd; }
.status-success { color: #198754; }
.status-danger { color: #dc3545; }

@media(max-width: 600px) {
    .order-card { padding: 14px; }
    .order-header { align-items: flex-start; }
}
</style>
</head>

<body>

<div class="container py-4 page">

    <div class="d-flex justify-content-between align-items-center">

        <h3 class="page-title">
            <i class="bi bi-bag-check text-primary"></i>
            Đơn mua của tôi
        </h3>

        <a href="index.php" class="btn btn-outline-primary">
            <i class="bi bi-house"></i>
            Tiếp tục mua hàng
        </a>

    </div>

    <?php if (isset($_GET['message'])): ?>

        <?php if ($_GET['message'] === 'cancel_success'): ?>
            <div class="alert alert-success mt-3">
                <i class="bi bi-check-circle"></i>
                Hủy đơn hàng thành công!
            </div>
        <?php elseif ($_GET['message'] === 'cancel_failed'): ?>
            <div class="alert alert-warning mt-3">
                Không thể hủy đơn hàng. Đơn có thể đã được xử lý.
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php if (empty($orders)): ?>

        <div class="empty-box mt-4">
            <i class="bi bi-receipt"></i>
            <h5 class="mt-3">Chưa có thông tin đơn hàng</h5>
            <p class="text-muted">
                Hãy đặt hàng để hệ thống lưu thông tin và hiển thị đơn mua của bạn.
            </p>
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-cart"></i>
                Mua sắm ngay
            </a>
        </div>

    <?php else: ?>

        <!-- CÁC TAB ĐƠN HÀNG -->


        <div class="order-tabs">

            <?php foreach ($tabs as $key => $label): ?>

                <a href="?tab=<?= e($key) ?>"
                class="<?= $activeTab === $key ? 'active' : '' ?>">

                    <i class="bi <?= $tabIcons[$key] ?>"></i>

                    <span><?= e($label) ?></span>

                    <span class="tab-count">
                        (<?= (int)$counts[$key] ?>)
                    </span>

                </a>

            <?php endforeach; ?>

        </div>

        <!-- DANH SÁCH ĐƠN -->
        <?php if (empty($filteredOrders)): ?>

            <div class="empty-box">
                <i class="bi bi-receipt"></i>
                <h5 class="mt-3">Chưa có đơn hàng</h5>
                <p class="text-muted">
                    Bạn chưa có đơn hàng trong mục này.
                </p>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-cart"></i>
                    Mua sắm ngay
                </a>
            </div>

        <?php else: ?>

            <?php foreach ($filteredOrders as $order): ?>

                <?php
                $status = $statusMap[$order['status']] ?? [
                    'text' => $order['status'],
                    'icon' => 'bi-question-circle',
                    'color' => 'secondary'
                ];
                ?>

                <div class="order-card">

                    <div class="order-header">

                        <div class="order-id">
                            <i class="bi bi-receipt"></i>
                            Đơn hàng #<?= (int)$order['id'] ?>
                        </div>

                        <div class="status-<?= e($status['color']) ?> fw-bold">
                            <i class="bi <?= e($status['icon']) ?>"></i>
                            <?= e($status['text']) ?>
                        </div>

                    </div>

                    <div class="order-info">

                        <p>
                            <i class="bi bi-person"></i>
                            <strong>Người nhận:</strong>
                            <?= e($order['fullname']) ?>
                        </p>

                        <p>
                            <i class="bi bi-telephone"></i>
                            <strong>Số điện thoại:</strong>
                            <?= e($order['phone']) ?>
                        </p>

                        <p>
                            <i class="bi bi-geo-alt"></i>
                            <strong>Địa chỉ:</strong>
                            <?= e($order['address']) ?>
                        </p>

                        <p>
                            <i class="bi bi-calendar3"></i>
                            <strong>Ngày đặt:</strong>
                            <?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?>
                        </p>

                        <p>
                            <i class="bi bi-credit-card"></i>
                            <strong>Thanh toán:</strong>
                            <?= e($order['payment_method']) ?>
                        </p>

                        <?php if (!empty($order['products'])): ?>
                            <p>
                                <i class="bi bi-box"></i>
                                <strong>Sản phẩm:</strong>
                                <?= e($order['products']) ?>
                            </p>
                        <?php endif; ?>

                    </div>

                    <div class="order-footer">

                        <div>
                            <span class="text-muted">Thành tiền:</span>
                            <div class="total-price">
                                <?= number_format((float)$order['total_amount'], 0, ',', '.') ?>đ
                            </div>
                        </div>

                        <?php if ($order['status'] === 'pending'): ?>

                            <form method="POST"
                                  onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?')">

                                <input type="hidden"
                                       name="order_id"
                                       value="<?= (int)$order['id'] ?>">

                                <button type="submit"
                                        name="cancel_order"
                                        value="1"
                                        class="btn btn-outline-danger">

                                    <i class="bi bi-x-circle"></i>
                                    Hủy đơn hàng

                                </button>

                            </form>

                        <?php elseif (in_array($order['status'], ['completed', 'Đã duyệt'], true)): ?>

                            <span class="text-success fw-bold">
                                <i class="bi bi-check-circle-fill"></i>
                                Đã hoàn thành
                            </span>

                        <?php elseif ($order['status'] === 'cancelled'): ?>

                            <span class="text-danger fw-bold">
                                <i class="bi bi-x-circle-fill"></i>
                                Đơn đã hủy
                            </span>

                        <?php else: ?>

                            <span class="text-primary fw-bold">
                                <i class="bi bi-truck"></i>
                                Đơn đang được xử lý
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>

</body>
</html>
