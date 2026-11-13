<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Tạo CSRF sau khi session đã được khởi tạo để API nhận đúng token.
$chatCsrf = $_SESSION['chat_csrf'] ?? bin2hex(random_bytes(24));
$_SESSION['chat_csrf'] = $chatCsrf;
$orderDetail = null;

if(isset($_GET['order_detail'])){
$id = (int)$_GET['order_detail'];

$res = $conn->query("SELECT * FROM orders WHERE id=$id");

if($res && $res->num_rows > 0){
$orderDetail = $res->fetch_assoc();
}

}

/* ===============================
   1. XỬ LÝ LOGIC (ACTIONS)
================================*/
$alert_msg = "";


// CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG THEO QUY TRÌNH 5 TRẠNG THÁI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $id = (int)($_POST['order_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowedTransitions = [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipping', 'cancelled'],
        'shipping'   => ['completed'],
    ];

    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentOrder = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($currentOrder) {
        $currentStatus = $currentOrder['status'];

        // Chuẩn hóa trạng thái cũ được tạo bởi phiên bản trước.
        if ($currentStatus === 'Đã duyệt') {
            $currentStatus = 'processing';
        } elseif ($currentStatus === 'Chờ xử lý') {
            $currentStatus = 'pending';
        }

        if (isset($allowedTransitions[$currentStatus]) &&
            in_array($newStatus, $allowedTransitions[$currentStatus], true)) {

            if ($newStatus === 'processing') {
                $stmt = $conn->prepare(
                    "UPDATE orders SET status = ?, approved_at = NOW() WHERE id = ?"
                );
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            }

            $stmt->bind_param("si", $newStatus, $id);
            $stmt->execute();
            $stmt->close();
            $statusMessage = 'updated';
        } else {
            $statusMessage = 'invalid_transition';
        }
    } else {
        $statusMessage = 'not_found';
    }

    $redirectPage = basename($_SERVER['PHP_SELF']);
    if ($redirectPage === 'admin_users.php') {
        header("Location: ?view=orders&status=" . $statusMessage);
    } else {
        header("Location: staff_dashboard.php?view=orders&status=" . $statusMessage);
    }
    exit();
}




// C. Xử lý Xóa sản phẩm
if (isset($_GET['delete_product'])) {
    $id = (int)$_GET['delete_product'];

    $stmt = $conn->prepare("DELETE FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=products&status=deleted");
    exit;
}
// E. Xử lý Xóa đơn hàng
if (isset($_GET['delete_order'])) {
    $id = (int)$_GET['delete_order'];

    $stmt = $conn->prepare("DELETE FROM orders WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=orders&status=deleted");
    exit;
}

// F. Xử lý Xóa khách hàng
if (isset($_GET['delete_customer'])) {

    $phone = $_GET['delete_customer'];

    $stmt = $conn->prepare("DELETE FROM orders WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=customers&status=deleted");
    exit;
}

// DUYỆT TƯ VẤN
if(isset($_GET['approve_consult'])){

$id = (int)$_GET['approve_consult'];

$conn->query("
UPDATE consult_requests
SET status='Đã liên hệ'
WHERE id=$id
");

header("Location: staff_dashboard.php?view=consult&status=approved_consult");
exit;

}


// HỦY DUYỆT
if(isset($_GET['cancel_consult'])){

$id = (int)$_GET['cancel_consult'];

$conn->query("
UPDATE consult_requests
SET status='Chưa liên hệ'
WHERE id=$id
");

header("Location: staff_dashboard.php?view=consult&status=cancel_consult");
exit;

}
// G. Xử lý Xóa email nhận tin
if (isset($_GET['delete_newsletter'])) {

    $id = (int)$_GET['delete_newsletter'];

    $stmt = $conn->prepare("DELETE FROM newsletter WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=newsletter&status=deleted");
    exit;
}

// H. Thêm email nhận tin
if (isset($_POST['add_email'])) {

    $email = trim($_POST['email']);

    $stmt = $conn->prepare("INSERT INTO newsletter(email, created_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=newsletter&status=added");
    exit;
}

// I. Sửa email nhận tin
if (isset($_POST['update_email'])) {

    $id = (int)$_POST['id'];
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("UPDATE newsletter SET email=? WHERE id=?");
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();

    header("Location: staff_dashboard.php?view=newsletter&status=updated");
    exit;
}
// D. Xử lý Gửi Voucher
if (isset($_GET['send_voucher'])) {
    header("Location: staff_dashboard.php?view=newsletter&status=sent_success");
    exit();
}

// Thiết lập nội dung thông báo
if (isset($_GET['status'])) {
    $st = $_GET['status'];
    if ($st == 'added') $alert_msg = "Email được thêm thành công!";
    if ($st == 'sent_success') $alert_msg = "Mã Voucher đã được gửi tới khách hàng!";
    if ($st == 'approved') $alert_msg = "Đơn hàng đã được duyệt thành công!";
    if ($st == 'updated') $alert_msg = "Cập nhật trạng thái đơn hàng thành công!";
    if ($st == 'invalid_transition') $alert_msg = "Không thể chuyển đơn hàng sang trạng thái này!";
    if ($st == 'not_found') $alert_msg = "Không tìm thấy đơn hàng!";
    if ($st == 'deleted') $alert_msg = "Dữ liệu đã được xóa khỏi hệ thống!";
    if ($st == 'approved_consult') $alert_msg = "Đã đánh dấu khách đã liên hệ!";
if ($st == 'cancel_consult') $alert_msg = "Đã hủy trạng thái liên hệ!";
}

/* ===============================
   2. TRUY VẤN DỮ LIỆU
================================*/
$view = $_GET['view'] ?? 'products';
$search = trim($_GET['search'] ?? '');
$searchParam = "%$search%";

// Thống kê nhanh
$totalP = ($res = $conn->query("SELECT COUNT(*) as total FROM products")) ? $res->fetch_assoc()['total'] : 0;
$totalO = ($res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status != 'Đã duyệt'")) ? $res->fetch_assoc()['total'] : 0;
$totalC = ($res = $conn->query("SELECT COUNT(DISTINCT phone) as total FROM orders")) ? $res->fetch_assoc()['total'] : 0;
$totalN = ($res = $conn->query("SELECT COUNT(*) as total FROM newsletter")) ? $res->fetch_assoc()['total'] : 0;

/// Câu lệnh SQL theo từng View
if ($view == 'orders') {

    $sql = "SELECT * FROM orders 
            WHERE fullname LIKE ? OR phone LIKE ? 
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchParam, $searchParam);

} 
elseif ($view == 'customers') {

    $sql = "SELECT 
            fullname,
            phone,
            address,
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount),0) as total_spent,
            MAX(created_at) as last_order
            FROM orders
            WHERE fullname LIKE ? OR phone LIKE ?
            GROUP BY fullname, phone, address
            ORDER BY total_spent DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchParam, $searchParam);

}

elseif ($view == 'consult') {

$sql = "SELECT * FROM consult_requests ORDER BY id DESC";
$stmt = $conn->prepare($sql);

}
elseif ($view == 'newsletter') {

    // TRUY VẤN BẢNG NEWSLETTER
    $sql = "SELECT * FROM newsletter ORDER BY id DESC";
    $stmt = $conn->prepare($sql);

}
else {

    $sql = "SELECT * FROM products 
            WHERE name LIKE ? OR category LIKE ? 
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchParam, $searchParam);


}
$stmt->execute();
$data = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Viet Duc | Quản trị hệ thống</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/staff_dashboard.css?v=<?php echo time(); ?>">
    <?php if ($view === "chat"): ?><style>
body{background:#f3f6fb;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.chat-shell{max-width:1400px;margin:24px auto;padding:0 14px}.chat-layout{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;height:calc(100vh - 150px);min-height:520px}.pane{background:#fff;border:1px solid #e4e9f2;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px #1831530b}.pane-head{padding:16px;border-bottom:1px solid #edf0f5}.conversation-list{height:calc(100% - 72px);overflow:auto}.conv-item{width:100%;text-align:left;background:#fff;border:0;border-bottom:1px solid #f0f2f7;padding:13px 15px}.conv-item.active,.conv-item:hover{background:#eef5ff}.conv-last{font-size:12px;color:#65748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.chat-messages{height:calc(100% - 140px);overflow:auto;padding:18px;background:#f8faff}.bubble{max-width:78%;margin:8px 0;padding:10px 13px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere}.bubble.visitor{background:#fff;border:1px solid #e1e7f0;margin-right:auto}.bubble.ai{background:#eef3fa;color:#344054;margin-right:auto}.bubble.staff{background:#d8f6e8;margin-left:auto}.bubble.system{background:#fff3cd;margin:8px auto;text-align:center;font-size:12px}.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px}.chat-compose{padding:12px;border-top:1px solid #edf0f5;display:flex;gap:8px}.mode-chip{font-size:12px}.conv-item .unread{font-size:11px}.empty{color:#728097;text-align:center;padding:60px 12px}@media(max-width:760px){.chat-layout{grid-template-columns:1fr;height:auto}.pane:first-child{height:260px}.pane:last-child{height:65vh}.conversation-list{height:190px}.chat-messages{height:calc(65vh - 140px)}}
</style><?php endif; ?>
</head>
<body>

<div class="sidebar shadow p-3">
    <h3 class="fw-bold text-center py-4 text-white">NHÂN VIÊN</h3>
    <div class="nav flex-column mt-2">
        <a href="?view=products" class="nav-link <?= $view=='products'?'active':'' ?>"><i class="fas fa-pills me-2"></i> Sản phẩm</a>
        <a href="?view=orders" class="nav-link <?= $view=='orders'?'active':'' ?>"><i class="fas fa-shopping-cart me-2"></i> Đơn hàng 
            <?php if($totalO > 0): ?><span class="badge bg-danger float-end"><?= $totalO ?></span><?php endif; ?>
        </a>
        <a href="?view=customers" class="nav-link <?= $view=='customers'?'active':'' ?>"><i class="fas fa-users me-2"></i> Khách hàng</a>
        <a href="?view=newsletter" class="nav-link <?= $view=='newsletter'?'active':'' ?>"><i class="fas fa-envelope me-2"></i> Nhận tin</a>
        <a href="?view=consult" class="nav-link <?= $view=='consult'?'active':'' ?>">
<i class="fas fa-phone me-2"></i> Tư vấn khách
</a>
        
        <a href="admin.gocsk.php" class="nav-link"><i class="fas fa-heartbeat me-2 text-info"></i> Góc sức khỏe</a>
        <a href="?view=chat" class="nav-link <?= $view==='chat'?'active':'' ?>"><i class="fas fa-comments me-2 text-warning"></i> Chat khách hàng</a>
        
        <a href="index.php" target="_blank" class="nav-link">
    <i class="fas fa-globe me-2 text-success"></i> Xem website
</a>
        <hr class="mx-3 opacity-25">
        <a href="logout.php" class="nav-link text-danger mt-auto"><i class="fas fa-sign-out-alt me-2"></i> Đăng xuất</a>
    </div>
</div>

<div class="main-content">
    
    <?php if ($alert_msg): ?>
        <div id="msgAlert" class="alert alert-success alert-fixed border-0 d-flex align-items-center rounded-3 px-4 py-3" role="alert">
            <i class="fas fa-check-circle me-3 fa-lg"></i>
            <div><?= $alert_msg ?></div>
        </div>
        <script>setTimeout(() => { document.getElementById('msgAlert').style.display='none'; }, 3000);</script>
    <?php endif; ?>

   <div class="d-flex justify-content-between align-items-center mb-5">
<div class="d-flex align-items-center gap-3">

<h2 class="fw-bold mb-0">Quản lý hệ thống</h2>

<a href="export_excel.php?view=<?= $view ?>" 
class="btn btn-success rounded-pill px-4">
<i class="fas fa-file-excel me-2"></i>
Xuất Excel
</a>

<a href="export_pdf.php?view=<?= $view ?>" 
class="btn btn-danger rounded-pill px-4">
<i class="fas fa-file-pdf me-2"></i>
Xuất PDF
</a>
            <?php if($view == 'newsletter'): ?>
<button class="btn btn-primary rounded-pill px-4"
data-bs-toggle="modal"
data-bs-target="#addEmailModal">
<i class="fas fa-plus me-2"></i>Thêm Email
</button>
<?php endif; ?>
            <?php if($view == 'products'): ?>
                <a href="themsp.php" class="btn btn-primary rounded-pill px-4">
    <i class="fas fa-plus me-2"></i>Thêm sản phẩm
</a>
            <?php endif; ?>
        </div>
        <form action="" method="GET" class="d-flex gap-2">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="text" name="search" class="form-control rounded-pill px-4" placeholder="Tìm kiếm nhanh..." value="<?= $search ?>">
        </form>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-3"><div class="stat-card">Sản phẩm<h4 class="fw-bold text-primary mb-0 mt-2"><?= $totalP ?></h4></div></div>
        <div class="col-md-3"><div class="stat-card">Đơn mới<h4 class="fw-bold text-warning mb-0 mt-2"><?= $totalO ?></h4></div></div>
        <div class="col-md-3"><div class="stat-card">Khách hàng<h4 class="fw-bold text-success mb-0 mt-2"><?= $totalC ?></h4></div></div>
        <div class="col-md-3"><div class="stat-card">Nhận KM<h4 class="fw-bold text-info mb-0 mt-2"><?= $totalN ?></h4></div></div>
    </div>

    <div class="bg-white p-4 rounded-4 shadow-sm border-0">
        <?php if ($view === 'chat'): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h3 class="mb-1">Hộp thư tư vấn trực tuyến</h3>
                <div class="small text-secondary">Tiếp nhận và trả lời tin nhắn của khách hàng</div></div>
                <button class="btn btn-outline-primary btn-sm" id="refreshList">Làm mới hội thoại</button>
            </div>
            <div class="chat-layout">
                <section class="pane">
                    <div class="pane-head"><strong>Hội thoại</strong></div>
                    <div id="conversationList" class="conversation-list"><div class="empty">Đang tải hội thoại…</div></div>
                </section>
                <section class="pane">
                    <div class="pane-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div><strong id="activeTitle">Chọn hội thoại</strong>
                        <div id="activeMeta" class="small text-secondary">Tin nhắn mới sẽ xuất hiện tự động</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-success" id="takeOver" disabled>Nhân viên tiếp nhận</button>
                            <button class="btn btn-sm btn-outline-primary" id="returnBot" disabled>Trả lại AI</button>
                            <button class="btn btn-sm btn-outline-danger" id="closeChat" disabled>Đóng</button>
                        </div>
                    </div>
                    <div id="messages" class="chat-messages"><div class="empty">Chọn một hội thoại ở bên trái để xem tin nhắn.</div></div>
                    <form id="replyForm" class="chat-compose">
                        <input id="replyInput" class="form-control" maxlength="3000" placeholder="Nhập phản hồi của nhân viên…" disabled>
                        <button class="btn btn-primary" id="sendReply" disabled>Gửi</button>
                    </form>
                </section>
            </div>
            <div id="statusMsg" class="small text-secondary mt-2">Cập nhật tự động mỗi vài giây.</div>
        <?php else: ?>
        <?php if($view == 'orders' && $orderDetail): ?>

<div class="card mb-4 shadow" id="orderDetailBox">

<div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">

<span>Chi tiết đơn hàng #<?= $orderDetail['id'] ?></span>

<a href="staff_dashboard.php?view=orders"
class="btn btn-sm btn-light">
<i class="fas fa-times"></i> Đóng
</a>

</div>

<div class="card-body">

<p><b>Khách hàng:</b> <?= $orderDetail['fullname'] ?></p>

<p><b>SĐT:</b> <?= $orderDetail['phone'] ?></p>

<p><b>Địa chỉ:</b> <?= $orderDetail['address'] ?></p>

<p><b>Sản phẩm:</b></p>

<div class="bg-light p-3 rounded">
<?= nl2br($orderDetail['products']) ?>
</div>

<p class="mt-3">
<b>Tổng tiền:</b>
<span class="text-danger fw-bold">
<?= number_format($orderDetail['total_amount']) ?>đ
</span>
</p>

<p>
<b>Phương thức:</b>
<?= $orderDetail['payment_method'] ?>
</p>

<p>
<b>Ngày tạo:</b>
<?= date('d/m/Y H:i',strtotime($orderDetail['created_at'])) ?>
</p>

</div>
</div>

<?php endif; ?>
<div class="table-responsive">
<table class="table table-hover align-middle">

<thead class="table-light">

<?php if($view == 'customers'): ?>
<tr>
<th>Rank</th>
<th>Tên khách</th>
<th>SĐT</th>
<th>Địa chỉ</th>
<th>Số đơn</th>
<th>Tổng chi</th>
<th>Đơn cuối</th>
<th>Xóa</th>
</tr>


<?php elseif($view == 'orders'): ?>
<tr><th>Mã</th><th>Khách hàng</th><th>SĐT</th><th>Trạng thái</th><th>Thao tác</th></tr>


<?php elseif($view == 'consult'): ?>
<tr>
<th>ID</th>
<th>Số điện thoại</th>
<th>Trạng thái</th>
<th>Ngày gửi</th>
<th>Thao tác</th>
</tr>
<?php elseif($view == 'newsletter'): ?>
<tr>
<th>Email khách hàng</th>
<th>Phân loại</th>
<th>Trạng thái</th>
<th>Gửi KM</th>
<th>Sửa</th>
<th>Ngày tạo</th>
<th>Xóa</th>
</tr>
<?php else: ?>
<tr><th>Ảnh</th><th>Tên thuốc</th><th>Danh mục</th><th>Giá bán</th><th>Thao tác</th></tr>
<?php endif; ?>

</thead>

<tbody>

<?php while($row = $data->fetch_assoc()): ?>

<?php if($view == 'customers'): ?>

<?php
$spent = $row['total_spent'] ?? 0;

$rank = '<span class="badge bg-secondary">🥉 Đồng</span>';

if($spent >= 10000000){
$rank = '<span class="badge bg-dark">💎 Kim cương</span>';
}
elseif($spent >= 5000000){
$rank = '<span class="badge bg-warning text-dark">🥇 Vàng</span>';
}
elseif($spent >= 2000000){
$rank = '<span class="badge bg-info text-dark">🥈 Bạc</span>';
}
?>

<tr>

<td><?= $rank ?></td>

<td><strong><?= htmlspecialchars($row['fullname']) ?></strong></td>

<td><?= $row['phone'] ?></td>

<td><?= $row['address'] ?></td>

<td>
<span class="badge bg-secondary">
<?= $row['total_orders'] ?> đơn
</span>
</td>

<td class="text-success fw-bold">
<?= number_format($spent) ?>đ
</td>

<td class="small text-muted">
<?= $row['last_order'] ? date('d/m/Y', strtotime($row['last_order'])) : '-' ?>
</td>
<td>

<a href="?view=customers&delete_customer=<?= $row['phone'] ?>"
class="btn btn-sm btn-outline-danger"
onclick="return confirm('Xóa toàn bộ đơn của khách này?')">

<i class="fas fa-trash"></i>

</a>

</td>
</tr>

<?php elseif($view == 'orders'): ?>

<tr>

<td class="fw-bold">#<?= $row['id'] ?></td>

<td>

<a href="staff_dashboard.php?view=orders&order_detail=<?= $row['id'] ?>"
class="text-primary fw-bold text-decoration-none">

<?= htmlspecialchars($row['fullname']) ?>

</a>

</td>

<td><?= $row['phone'] ?></td>

<td>
<?php

$orderStatus = $row['status'];
if ($orderStatus === 'Đã duyệt') $orderStatus = 'processing';
if ($orderStatus === 'Chờ xử lý') $orderStatus = 'pending';
$statusLabels = [
    'pending' => ['Chờ xác nhận', 'bg-warning text-dark'],
    'processing' => ['Đang chuẩn bị', 'bg-info text-dark'],
    'shipping' => ['Đang giao hàng', 'bg-primary'],
    'completed' => ['Hoàn thành', 'bg-success'],
    'cancelled' => ['Đã hủy', 'bg-danger']
];
$statusInfo = $statusLabels[$orderStatus] ?? [$row['status'], 'bg-secondary'];
?>
<span class="badge <?= $statusInfo[1] ?>"><?= htmlspecialchars($statusInfo[0]) ?></span>
</td>
<td>
<div class="d-flex flex-wrap gap-1">
    <?php
    $nextActions = [
        'pending' => ['processing' => 'Xác nhận đơn', 'cancelled' => 'Hủy đơn'],
        'processing' => ['shipping' => 'Bắt đầu giao', 'cancelled' => 'Hủy đơn'],
        'shipping' => ['completed' => 'Hoàn thành']
    ];
    foreach (($nextActions[$orderStatus] ?? []) as $nextStatus => $buttonLabel):
    ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn <?= htmlspecialchars($buttonLabel, ENT_QUOTES) ?>?')">
            <input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="new_status" value="<?= htmlspecialchars($nextStatus) ?>">
            <button type="submit" name="update_order_status" value="1" class="btn btn-sm <?= $nextStatus === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= htmlspecialchars($buttonLabel) ?></button>
        </form>
    <?php endforeach; ?>
    <a href="?view=orders&delete_order=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa hẳn đơn hàng này?')"><i class="fas fa-trash"></i></a>
</div>
</td>

</tr>
<?php elseif($view == 'consult'): ?>

<tr>

<td>#<?= $row['id'] ?></td>

<td class="fw-bold">
<?= $row['phone'] ?>
</td>

<td>

<?php
$status = $row['status'] ?? 'Chưa liên hệ';

$badge = "bg-warning";
$text = "Chưa liên hệ";

if($status == "Đã liên hệ"){
$badge = "bg-success";
$text = "Đã liên hệ";
}
?>

<span class="badge <?= $badge ?>">
<?= $text ?>
</span>

</td>

<td class="small text-muted">
<?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
</td>

<td>

<!-- Gọi -->
<a href="tel:<?= $row['phone'] ?>"
class="btn btn-sm btn-primary">
<i class="fas fa-phone"></i>
</a>

<!-- Zalo -->
<a href="https://zalo.me/<?= $row['phone'] ?>"
target="_blank"
class="btn btn-sm btn-info">
<i class="fas fa-comment"></i>
</a>

<?php if($status != "Đã liên hệ"): ?>

<!-- Duyệt -->
<a href="?view=consult&approve_consult=<?= $row['id'] ?>"
class="btn btn-sm btn-success">
<i class="fas fa-check"></i>
</a>

<?php else: ?>

<!-- Hủy duyệt -->
<a href="?view=consult&cancel_consult=<?= $row['id'] ?>"
class="btn btn-sm btn-warning">
<i class="fas fa-undo"></i>
</a>

<?php endif; ?>

</td>

</tr>

<?php elseif($view == 'newsletter'): ?>

<tr>

<td><strong><?= htmlspecialchars($row['email'] ?? '') ?></strong></td>

<td>
<span class="badge bg-info-subtle text-info">Ưu đãi</span>
</td>

<td class="text-success small">
<i class="fas fa-check me-1"></i>Hoạt động
</td>

<td>
<a href="?view=newsletter&send_voucher=1" 
class="btn btn-sm btn-primary rounded-pill px-3">
Gửi
</a>
</td>

<td>
<button class="btn btn-sm btn-outline-primary"
data-bs-toggle="modal"
data-bs-target="#editEmail<?= $row['id'] ?>">
<i class="fas fa-edit"></i>
</button>
</td>

<td class="small text-muted">
<?= date('d/m/Y', strtotime($row['created_at'] ?? 'now')) ?>
</td>

<td>
<a href="?view=newsletter&delete_newsletter=<?= $row['id'] ?>"
class="btn btn-sm btn-outline-danger"
onclick="return confirm('Xóa email này?')">
<i class="fas fa-trash"></i>
</a>
</td>

</tr>

<?php else: ?>

<td>
<img src="images/<?= $row['image'] ?: 'default.png' ?>" 
width="40" height="40" 
class="rounded shadow-sm" 
style="object-fit: cover;">
</td>

<td><strong><?= $row['name'] ?></strong></td>

<td class="text-muted"><?= $row['category'] ?></td>

<td class="text-primary fw-bold">
<?= number_format($row['price_new']) ?>đ
</td>

<td>
<a href="themsp.php?action=edit&id=<?= $row['id'] ?>" 
class="btn btn-sm btn-outline-primary border-0">
<i class="fas fa-edit"></i>
</a>

<a href="?delete_product=<?= $row['id'] ?>" 
class="btn btn-sm btn-outline-danger border-0"
onclick="return confirm('Xóa thuốc này?')">
<i class="fas fa-trash"></i>
</a>
</td>

</tr>

<?php endif; ?>

<?php endwhile; ?>

</tbody>
</table>
</div>
        <?php endif; ?>
</div>
</div>

<div class="modal fade" id="addProdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold">Thêm dược phẩm mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="form-label small fw-bold">Tên thuốc</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Danh mục</label>
                        <select name="category" class="form-select">
                            <option>Thuốc kê đơn</option><option>Thực phẩm chức năng</option><option>Dụng cụ y tế</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6"><label class="form-label small fw-bold">Giá gốc</label><input type="number" name="price_old" class="form-control"></div>
                        <div class="col-6"><label class="form-label small fw-bold">Giá bán</label><input type="number" name="price_new" class="form-control" required></div>
                    </div>
                    <div class="mb-0"><label class="form-label small fw-bold">Ảnh đại diện</label><input type="file" name="image" class="form-control" accept="image/*" required></div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="add_product" class="btn btn-primary rounded-pill px-4 shadow">Lưu vào kho</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if($view == 'newsletter'): ?>
<?php 
$data->data_seek(0); // quay lại đầu dữ liệu
while($row = $data->fetch_assoc()): 
?>

<div class="modal fade" id="editEmail<?= $row['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content rounded-4 shadow">

<form method="POST">

<div class="modal-header border-0">
<h5 class="fw-bold">Sửa Email</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" name="id" value="<?= $row['id'] ?>">

<label class="form-label small fw-bold">Email khách hàng</label>

<input type="email"
name="email"
class="form-control"
value="<?= htmlspecialchars($row['email']) ?>"
required>

</div>

<div class="modal-footer border-0">

<button type="button"
class="btn btn-light rounded-pill px-4"
data-bs-dismiss="modal">
Hủy
</button>

<button type="submit"
name="update_email"
class="btn btn-primary rounded-pill px-4">
Lưu thay đổi
</button>

</div>

</form>

</div>
</div>
</div>

<?php endwhile; ?>
<?php endif; ?>

<?php if($view == 'newsletter'): ?>

<div class="modal fade" id="addEmailModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content rounded-4 shadow">

<form method="POST">

<div class="modal-header border-0">
<h5 class="fw-bold">Thêm Email nhận tin</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<label class="form-label fw-bold small">Email khách hàng</label>

<input type="email"
name="email"
class="form-control"
placeholder="example@gmail.com"
required>

</div>

<div class="modal-footer border-0">

<button type="button"
class="btn btn-light rounded-pill px-4"
data-bs-dismiss="modal">
Hủy
</button>

<button type="submit"
name="add_email"
class="btn btn-primary rounded-pill px-4">
Thêm email
</button>

</div>

</form>

</div>
</div>
</div>

<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<audio id="bellSound" preload="auto">
<source src="sounds/bell.mp3" type="audio/mpeg">
</audio>
<script>

window.onload = function(){

// xóa backdrop bootstrap
document.querySelectorAll('.modal-backdrop').forEach(function(el){
el.remove();
});

// reset body
document.body.classList.remove('modal-open');
document.body.style.overflow = "auto";

}

</script>



<script>



let lastOrder = parseInt(localStorage.getItem("lastOrder")) || 0;

let bell = document.getElementById("bellSound");

let popup, info, viewBtn, closeBtn;

window.addEventListener("DOMContentLoaded", function(){

popup = document.getElementById("orderPopup");
info = document.getElementById("orderInfo");
viewBtn = document.getElementById("viewOrder");
closeBtn = document.getElementById("closePopup");

});

// mở khóa audio
document.addEventListener("click", function(){

bell.play().then(()=>{
bell.pause();
bell.currentTime = 0;
}).catch(()=>{});

},{once:true});


function checkNewOrder(){

fetch("check_new_orders.php?t="+Date.now())

.then(res => res.json())

.then(data => {

if(data.new && data.id > lastOrder){

lastOrder = data.id;
localStorage.setItem("lastOrder", data.id);

// phát chuông
bell.currentTime = 0;
bell.play().catch(()=>{});

// hiển thị popup
if(popup && info){

popup.style.display = "flex";

info.innerHTML =
"Khách: <b>"+data.name+"</b><br>"+
"SĐT: "+data.phone;

}


// nút OK
if(viewBtn){
viewBtn.onclick = function(){

window.location.href =
"staff_dashboard.php?view=orders&highlight="+data.id;

};
}

if(closeBtn){
closeBtn.onclick = function(){

if(confirm("Hủy đơn hàng này?")){

if(closeBtn){
closeBtn.onclick = function(){

if(confirm("Hủy đơn hàng này?")){

popup.style.display = "none";

window.location.href =
"staff_dashboard.php?delete_order="+data.id+"&view=orders";

}

};
}

}

};
}

}

})

.catch(err => console.log("Lỗi check đơn:",err));

}

setInterval(checkNewOrder,3000);
</script>

<div id="orderPopup" class="order-popup">

<div class="popup-box">

<h4>🔔 ĐƠN HÀNG MỚI</h4>

<p id="orderInfo"></p>

<div class="popup-btns">

<button id="viewOrder" class="btn btn-success">
OK
</button>

<button id="closePopup" class="btn btn-secondary">
Hủy
</button>

</div>

</div>

</div>

<?php if ($view === 'chat'): ?>
<script>
const csrf = <?= json_encode($chatCsrf) ?>;
const listEl=document.getElementById('conversationList'), msgsEl=document.getElementById('messages');
let activeId=0, lastId=0, activeMode='bot', listBusy=false, messagesBusy=false;
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function api(action,params={},method='GET'){
    let url='admin_chat_api.php?action='+encodeURIComponent(action);
    if(method==='GET')url+='&'+new URLSearchParams(params);
    const opt={method,headers:{'Content-Type':'application/json'}};
    if(method!=='GET')opt.body=JSON.stringify({...params,csrf});
    const r=await fetch(url,opt), d=await r.json();
    if(!r.ok||!d.ok)throw new Error(d.error||'Lỗi kết nối');
    return d;
}
function modeText(m){return m==='human'?'Nhân viên đang trả lời':m==='closed'?'Đã đóng':'AI đang trả lời';}
async function loadList(){
    if(listBusy||document.hidden)return; listBusy=true;
    try{
        const d=await api('list'); listEl.innerHTML='';
        if(!d.conversations.length){listEl.innerHTML='<div class="empty">Chưa có hội thoại nào. Khi khách nhắn tin, hội thoại sẽ hiện ở đây.</div>';return;}
        for(const c of d.conversations){
            const b=document.createElement('button'); b.className='conv-item'+(Number(c.id)===activeId?' active':'');
            b.innerHTML='<div class="d-flex justify-content-between gap-2"><strong>Hội thoại #'+esc(c.id)+'</strong><span class="badge '+(c.status==='human'?'text-bg-success':c.status==='closed'?'text-bg-secondary':'text-bg-primary')+'">'+esc(c.status==='human'?'Nhân viên':c.status==='closed'?'Đã đóng':'AI')+'</span></div><div class="conv-last">'+esc(c.last_message||'Chưa có tin nhắn')+'</div><div class="d-flex justify-content-between mt-1"><small class="text-secondary">'+esc(c.last_message_at||'')+'</small>'+(Number(c.unread)>0?'<span class="badge rounded-pill text-bg-danger unread">'+esc(c.unread)+' mới</span>':'')+'</div>';
            b.onclick=()=>selectConversation(Number(c.id)); listEl.appendChild(b);
        }
    }catch(e){document.getElementById('statusMsg').textContent=e.message;}finally{listBusy=false;}
}
function addMessage(m){
    if(Number(m.id)<=lastId)return;
    const b=document.createElement('div'); b.className='bubble '+(m.sender==='visitor'?'visitor':m.sender==='staff'?'staff':m.sender==='system'?'system':'ai');
    b.innerHTML=esc(m.message)+'<small>'+esc(m.sender==='visitor'?'Khách hàng':m.sender==='staff'?'Nhân viên':m.sender==='system'?'Hệ thống':'AI')+' · '+esc(m.created_at||'')+'</small>';
    msgsEl.appendChild(b); lastId=Math.max(lastId,Number(m.id)); msgsEl.scrollTop=msgsEl.scrollHeight;
}
async function selectConversation(id){
    activeId=id;lastId=0;msgsEl.innerHTML='';document.getElementById('activeTitle').textContent='Hội thoại #'+id;
    ['takeOver','returnBot','closeChat','replyInput','sendReply'].forEach(x=>document.getElementById(x).disabled=false);
    await pollMessages(true);await loadList();
}
async function pollMessages(initial=false){
    if(!activeId||messagesBusy||document.hidden)return;messagesBusy=true;
    try{
        const d=await api('messages',{conversation_id:activeId,after:initial?0:lastId});
        if(initial){msgsEl.innerHTML='';lastId=0;}d.messages.forEach(addMessage);activeMode=d.mode;
        document.getElementById('activeMeta').textContent=modeText(activeMode);
        document.getElementById('takeOver').disabled=activeMode==='human';
        document.getElementById('returnBot').disabled=activeMode==='bot';
    }catch(e){document.getElementById('statusMsg').textContent=e.message;}finally{messagesBusy=false;}
}
document.getElementById('replyForm').addEventListener('submit',async e=>{
    e.preventDefault();if(!activeId)return;const input=document.getElementById('replyInput'),message=input.value.trim();if(!message)return;
    document.getElementById('sendReply').disabled=true;
    try{await api('reply',{conversation_id:activeId,message},'POST');input.value='';await pollMessages();await loadList();}
    catch(e){alert(e.message);}finally{document.getElementById('sendReply').disabled=false;input.focus();}
});
async function changeMode(mode){if(!activeId)return;try{await api('mode',{conversation_id:activeId,mode},'POST');await pollMessages();await loadList();}catch(e){alert(e.message);}}
document.getElementById('takeOver').onclick=()=>changeMode('human');
document.getElementById('returnBot').onclick=()=>changeMode('bot');
document.getElementById('closeChat').onclick=()=>changeMode('closed');
document.getElementById('refreshList').onclick=loadList;
loadList();setInterval(loadList,8000);setInterval(()=>pollMessages(false),3500);
</script>
<?php endif; ?>

</body>
</html>