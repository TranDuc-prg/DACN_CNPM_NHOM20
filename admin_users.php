<?php
session_start();
require_once 'config/db.php';

// CSRF dùng chung với admin_chat_api.php cho chức năng nhân viên trả lời khách.
$chatCsrf = $_SESSION['chat_csrf'] ?? bin2hex(random_bytes(24));
$_SESSION['chat_csrf'] = $chatCsrf;

date_default_timezone_set('Asia/Ho_Chi_Minh');


$orderDetail = null;

if(isset($_GET['order_detail'])){
    $id = (int)$_GET['order_detail'];
    $orderDetail = $conn->query("SELECT * FROM orders WHERE id=$id")->fetch_assoc();
}
// 1. KIỂM TRA ĐĂNG NHẬP
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
    
$view = $_GET['view'] ?? 'dashboard';

/* =================================
    2. LOGIC XUẤT EXCEL (Tích hợp)
   ================================= */
if(isset($_GET['action']) && $_GET['action'] == 'export'){
    $type = $_GET['view'] ?? 'orders';
    $filename = "VietDuc_BaoCao_" . $type . "_" . date('d-m-Y') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM để hiển thị đúng tiếng Việt

    if($type == 'orders'){
        echo "Mã đơn\tKhách hàng\tSĐT\tTổng tiền\tTrạng thái\tNgày tạo\n";
        $res = $conn->query("SELECT * FROM orders ORDER BY id DESC");
        while($r = $res->fetch_assoc()) echo "#".$r['id']."\t".$r['fullname']."\t".$r['phone']."\t".$r['total_amount']."\t".$r['status']."\t".$r['created_at']."\n";
    } elseif($type == 'products'){
        echo "ID\tTên sản phẩm\tGiá\tKho\tDanh mục\tMô tả\n";
        $res = $conn->query("SELECT * FROM products ORDER BY id DESC");
        while($r = $res->fetch_assoc()) echo $r['id']."\t".$r['name']."\t".$r['price_new']."\t".$r['stock']."\t".$r['category']."\t".strip_tags($r['description'])."\n";
    } elseif($type == 'newsletter'){
        echo "STT\tEmail\tNgày đăng ký\n";
        $res = $conn->query("SELECT * FROM newsletter ORDER BY id DESC");
        $i=1; while($r = $res->fetch_assoc()) echo ($i++)."\t".$r['email']."\t".$r['created_at']."\n";
    }
    elseif($type == 'users'){
    echo "ID\tHọ tên\tEmail\tVai trò\tNgày tạo\n";
    $res = $conn->query("SELECT * FROM users ORDER BY id DESC");
    while($r = $res->fetch_assoc()){
        echo $r['id']."\t".$r['fullname']."\t".$r['email']."\t".$r['role']."\t".$r['created_at']."\n";
    }
}
    exit();
}

/* =================================
    3. LOGIC XỬ LÝ CRUD
   ================================= */
// HỦY DUYỆT TƯ VẤN
if(isset($_GET['cancel_consult'])){

$id = (int)$_GET['cancel_consult'];

$conn->query("
UPDATE consult_requests
SET status='Chưa liên hệ'
WHERE id=$id
");

header("Location:?view=consult&msg=cancelled");
exit();

}

   // DUYỆT ĐÃ LIÊN HỆ
if(isset($_GET['approve_consult'])){

$id = (int)$_GET['approve_consult'];

$conn->query("
UPDATE consult_requests
SET status='Đã liên hệ'
WHERE id=$id
");

header("Location:?view=consult&msg=approved");
exit();
}
   // XÓA YÊU CẦU TƯ VẤN
if(isset($_GET['delete_consult'])){
    $id = (int)$_GET['delete_consult'];

    $conn->query("DELETE FROM consult_requests WHERE id=$id");

   header("Location:?view=consult&msg=approved");
    exit();
}

// XÓA KHÁCH HÀNG
if(isset($_GET['delete_customer'])){
    $phone = mysqli_real_escape_string($conn,$_GET['delete_customer']);

    $conn->query("DELETE FROM orders WHERE phone='$phone'");

    header("Location:?view=customers");
    exit();
}

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

// XÓA ĐƠN HÀNG
if(isset($_GET['delete_order'])){
    $id = (int)$_GET['delete_order'];

    $conn->query("DELETE FROM orders WHERE id=$id");

    header("Location:?view=orders&status=deleted");
    exit();
}

// B. SẢN PHẨM
if(isset($_GET['delete_product'])){

$id = (int)$_GET['delete_product'];

/* xoá ảnh chính */
$img = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
if($img && !empty($img['image']) && file_exists("images/".$img['image'])){
unlink("images/".$img['image']);
}

/* xoá ảnh phụ */
$gal = $conn->query("SELECT image FROM product_images WHERE product_id=$id");

while($g = $gal->fetch_assoc()){
if(!empty($g['image']) && file_exists("images/".$g['image'])){
unlink("images/".$g['image']);
}
}

$conn->query("DELETE FROM product_images WHERE product_id=$id");
$conn->query("DELETE FROM products WHERE id=$id");

header("Location:?view=products&status=deleted");
exit();
}
// XÓA SẢN PHẨM
if(isset($_GET['delete_product'])){
    $id = (int)$_GET['delete_product'];

    $conn->query("DELETE FROM products WHERE id=$id");

    header("Location:?view=products");
    exit();
}
/* =================================
    F. QUẢN LÝ VOUCHER / COUPON
================================= */

if(isset($_POST['save_coupon'])){

    $cid = $_POST['coupon_id'] ?? '';

    $code = mysqli_real_escape_string($conn,$_POST['code'] ?? '');
    $discount = (int)($_POST['discount'] ?? 0);
    $expiry = trim($_POST['expiry_date'] ?? '');

    // Không cho lưu voucher có ngày hết hạn trong quá khứ.
    $expiryDate = DateTime::createFromFormat('!Y-m-d', $expiry);
    $validExpiry = $expiryDate && $expiryDate->format('Y-m-d') === $expiry;

    if(!$validExpiry || $expiry < date('Y-m-d')){
        header("Location:?view=newsletter&status=invalid_expiry");
        exit();
    }

    if($cid == ''){

        $saved = $conn->query("
        INSERT INTO coupons(code,discount_percent,expiry_date)
        VALUES('$code','$discount','$expiry')
        ");
        $successStatus = 'coupon_created';

    }else{

        $saved = $conn->query("
        UPDATE coupons
        SET code='$code',
            discount_percent='$discount',
            expiry_date='$expiry'
        WHERE id=".(int)$cid."
        ");
        $successStatus = 'coupon_updated';

    }

    if($saved){
        header("Location:?view=newsletter&status=".$successStatus);
    }else{
        header("Location:?view=newsletter&status=coupon_error");
    }
    exit();
}

// XÓA COUPON
if(isset($_GET['delete_coupon'])){
    $id = (int)$_GET['delete_coupon'];

    $conn->query("DELETE FROM coupons WHERE id=$id");

    header("Location:?view=newsletter&status=deleted");
    exit();
}
/* =================================
    D. QUẢN LÝ NGƯỜI DÙNG
   ================================= */

// XÓA USER
if (isset($_GET['delete_user'])) {
    $deleteUserId = (int)$_GET['delete_user'];
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);

    // Chặn ở phía máy chủ: không cho tài khoản đang đăng nhập tự xóa.
    if ($deleteUserId <= 0 || $currentUserId <= 0 || $deleteUserId === $currentUserId) {
        header("Location: ?view=users&status=cannot_delete_self");
        exit();
    }

    // Xóa bằng prepared statement.
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $deleteUserId);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        header("Location: ?view=users&status=" . ($deleted > 0 ? "deleted" : "not_found"));
    } else {
        header("Location: ?view=users&status=delete_error");
    }
    exit();
}

/* =================================
    E. QUẢN LÝ NHÀ SẢN XUẤT
================================= */

// XÓA
if(isset($_GET['delete_nhasx'])){
    $id = (int)$_GET['delete_nhasx'];
    $conn->query("DELETE FROM nhasx WHERE id=$id");
    header("Location:?view=nhasx");
    exit();
}

// THÊM / SỬA SẢN PHẨM
if(isset($_POST['save_product'])){

$id = $_POST['id'] ?? '';

$name = mysqli_real_escape_string($conn,$_POST['name']);
$price = $_POST['price_new'];
$stock = $_POST['stock'];
$category = $_POST['category'];
$subcategory = $_POST['subcategory'];
$desc = mysqli_real_escape_string($conn,$_POST['description']);

$image = "";

/* upload ảnh */
if(!empty($_FILES['images']['name'][0])){

$filename = time().'_'.$_FILES['images']['name'][0];

move_uploaded_file(
$_FILES['images']['tmp_name'][0],
"images/".$filename
);

$image = $filename;

}

/* thêm mới */
if($id == ""){

$conn->query("
INSERT INTO products(name,price_new,stock,category,subcategory,description,image)
VALUES('$name','$price','$stock','$category','$subcategory','$desc','$image')
");

}else{

$img_sql = $image ? ",image='$image'" : "";

$conn->query("
UPDATE products
SET name='$name',
price_new='$price',
stock='$stock',
category='$category',
subcategory='$subcategory',
description='$desc'
$img_sql
WHERE id=$id
");

}

header("Location:?view=products");
exit();

}
// THÊM / SỬA
if(isset($_POST['save_nhasx'])){

$id = $_POST['nhasx_id'] ?? '';

$name = mysqli_real_escape_string($conn,$_POST['name']);
$phone = mysqli_real_escape_string($conn,$_POST['phone']);
$email = mysqli_real_escape_string($conn,$_POST['email']);
$address = mysqli_real_escape_string($conn,$_POST['address']);
$website = mysqli_real_escape_string($conn,$_POST['website']);
$country = mysqli_real_escape_string($conn,$_POST['country']);

$logo = '';

if(!empty($_FILES['logo']['name'])){
    $logo = time().'_'.$_FILES['logo']['name'];
    move_uploaded_file($_FILES['logo']['tmp_name'], 'images/'.$logo);
}

if($id == ''){

$conn->query("
INSERT INTO nhasx(name,phone,email,address,website,logo,country)
VALUES('$name','$phone','$email','$address','$website','$logo','$country')
");

}else{

$logo_sql = $logo ? ", logo='$logo'" : "";

$conn->query("
UPDATE nhasx
SET name='$name',
phone='$phone',
email='$email',
address='$address',
website='$website',
country='$country'
$logo_sql
WHERE id=$id
");

}

header("Location:?view=nhasx");
exit();
}

$editNhasx =
(isset($_GET['edit_nhasx']))
? $conn->query("SELECT * FROM nhasx WHERE id=".(int)$_GET['edit_nhasx'])->fetch_assoc()
: null;
// THÊM / SỬA USER
if(isset($_POST['save_user'])){

    $uid = $_POST['user_id'] ?? '';
    $fullname = mysqli_real_escape_string($conn,$_POST['fullname']);
    $email = mysqli_real_escape_string($conn,$_POST['email']);
    $role = mysqli_real_escape_string($conn,$_POST['role']);
    $password = $_POST['password'];

    if($uid == ''){

        $hash = password_hash($password,PASSWORD_DEFAULT);

        $conn->query("
        INSERT INTO users(fullname,email,password,role)
        VALUES('$fullname','$email','$hash','$role')
        ");

    }else{

        if(!empty($password)){
            $hash = password_hash($password,PASSWORD_DEFAULT);

            $conn->query("
            UPDATE users
            SET fullname='$fullname',
                email='$email',
                password='$hash',
                role='$role'
            WHERE id=$uid
            ");

        }else{

            $conn->query("
            UPDATE users
            SET fullname='$fullname',
                email='$email',
                role='$role'
            WHERE id=$uid
            ");
        }
    }

    header("Location:?view=users&status=saved");
    exit();
}

$editUser = (isset($_GET['edit_user_id']))
? $conn->query("SELECT * FROM users WHERE id=".(int)$_GET['edit_user_id'])->fetch_assoc()
: null;
/* =================================
    4. TRUY VẤN THỐNG KÊ
   ================================= */
$revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status='Đã duyệt'")->fetch_assoc()['total'] ?? 0;
$newOrders = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status != 'Đã duyệt'")->fetch_assoc()['total'] ?? 0;
$totalOrders = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc()['total'] ?? 0;
$totalNews = $conn->query("SELECT COUNT(*) as total FROM newsletter")->fetch_assoc()['total'] ?? 0;

$chart_labels = [];
$chart_revenue = [];
$chart_orders = [];

for ($i = 6; $i >= 0; $i--) {

    $date_label = date('d/m', strtotime("-$i days"));
    $date_sql   = date('Y-m-d', strtotime("-$i days"));

    $chart_labels[] = $date_label;

    // doanh thu
    $sql1 = "
    SELECT SUM(total_amount) as total
    FROM orders
    WHERE status='Đã duyệt'
    AND DATE(approved_at)='$date_sql'
    ";

    $r1 = $conn->query($sql1)->fetch_assoc();
    $chart_revenue[] = (float)($r1['total'] ?? 0);

    // số đơn
    $sql2 = "
SELECT COUNT(*) as total
FROM orders
WHERE status='Đã duyệt'
AND DATE(approved_at)='$date_sql'
";

    $r2 = $conn->query($sql2)->fetch_assoc();
    $chart_orders[] = (int)($r2['total'] ?? 0);
}

/* =================================
   BÁO CÁO & THỐNG KÊ KINH DOANH
   Nguồn dữ liệu: orders, order_details,
   products, customers trong shop_db.
================================= */
$reportPeriod = $_GET['period'] ?? 'month';
$today = date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$reportStart = date('Y-m-01');
$reportEnd = $today;

$validMonth = DateTime::createFromFormat('!Y-m', $selectedMonth);
if (!$validMonth || $validMonth->format('Y-m') !== $selectedMonth) {
    $selectedMonth = date('Y-m');
}
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int)date('Y');
}

if ($reportPeriod === 'today') {
    $reportStart = $today;
    $reportEnd = $today;
} elseif ($reportPeriod === 'week') {
    $reportStart = date('Y-m-d', strtotime('-6 days'));
    $reportEnd = $today;
} elseif ($reportPeriod === 'quarter') {
    $quarter = (int)ceil((int)date('n') / 3);
    $selectedQuarter = (int)($_GET['quarter'] ?? $quarter);
    if ($selectedQuarter < 1 || $selectedQuarter > 4) $selectedQuarter = $quarter;
    $selectedYear = (int)($_GET['year'] ?? date('Y'));
    if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = (int)date('Y');
    $firstMonth = (($selectedQuarter - 1) * 3) + 1;
    $reportStart = sprintf('%04d-%02d-01', $selectedYear, $firstMonth);
    $reportEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $selectedYear, $firstMonth + 2)));
    if ($reportEnd > $today) $reportEnd = $today;
} elseif ($reportPeriod === 'year') {
    $reportStart = sprintf('%04d-01-01', $selectedYear);
    $reportEnd = sprintf('%04d-12-31', $selectedYear);
    if ($reportEnd > $today) $reportEnd = $today;
} elseif ($reportPeriod === 'custom') {
    $customStart = $_GET['start_date'] ?? date('Y-m-01');
    $customEnd = $_GET['end_date'] ?? $today;
    $startObj = DateTime::createFromFormat('!Y-m-d', $customStart);
    $endObj = DateTime::createFromFormat('!Y-m-d', $customEnd);
    if ($startObj && $endObj && $startObj->format('Y-m-d') === $customStart &&
        $endObj->format('Y-m-d') === $customEnd && $customStart <= $customEnd) {
        $reportStart = $customStart;
        $reportEnd = $customEnd;
    } else {
        $reportPeriod = 'month';
        $reportStart = $selectedMonth . '-01';
        $reportEnd = ($selectedMonth === date('Y-m')) ? $today : date('Y-m-t', strtotime($reportStart));
    }
} else {
    $reportPeriod = 'month';
    $reportStart = $selectedMonth . '-01';
    $reportEnd = ($selectedMonth === date('Y-m')) ? $today : date('Y-m-t', strtotime($reportStart));
}

/*
 * CSDL hiện tại không có completed_at. Vì vậy các báo cáo thời gian
 * được phân nhóm theo orders.created_at (ngày khách đặt đơn).
 * Doanh thu chỉ cộng đơn completed và dữ liệu cũ "Đã duyệt".
 * Không gọi đây là lợi nhuận vì products không có trường giá vốn.
 */
$completedStatusSql = "('completed', 'Đã duyệt')";
$reportRevenue = 0.0;
$reportOrders = 0;
$reportCompleted = 0;
$reportPending = 0;
$reportProcessing = 0;
$reportShipping = 0;
$reportCancelled = 0;
$reportOtherStatus = 0;
$reportAverage = 0.0;
$reportNewCustomers = 0;
$reportReturningCustomers = 0;

$summaryStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status IN ('completed','Đã duyệt')
            THEN total_amount ELSE 0 END), 0) AS revenue,
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status IN ('completed','Đã duyệt') THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN status IN ('pending','Chờ xử lý') THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) AS processing_orders,
        SUM(CASE WHEN status='shipping' THEN 1 ELSE 0 END) AS shipping_orders,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
");
if ($summaryStmt) {
    $summaryStmt->bind_param("ss", $reportStart, $reportEnd);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
    $reportRevenue = (float)($summary['revenue'] ?? 0);
    $reportOrders = (int)($summary['total_orders'] ?? 0);
    $reportCompleted = (int)($summary['completed_orders'] ?? 0);
    $reportPending = (int)($summary['pending_orders'] ?? 0);
    $reportProcessing = (int)($summary['processing_orders'] ?? 0);
    $reportShipping = (int)($summary['shipping_orders'] ?? 0);
    $reportCancelled = (int)($summary['cancelled_orders'] ?? 0);
    $reportOtherStatus = max(0, $reportOrders - $reportCompleted - $reportPending - $reportProcessing - $reportShipping - $reportCancelled);
    $summaryStmt->close();
}
$reportAverage = $reportCompleted > 0 ? $reportRevenue / $reportCompleted : 0;

/* So sánh với kỳ liền trước có cùng số ngày. */
$periodDays = max(1, (int)((strtotime($reportEnd) - strtotime($reportStart)) / 86400) + 1);
$previousEnd = date('Y-m-d', strtotime($reportStart . ' -1 day'));
$previousStart = date('Y-m-d', strtotime($previousEnd . ' -' . ($periodDays - 1) . ' days'));
$previousRevenue = 0.0;
$previousOrders = 0;
$previousStmt = $conn->prepare("
    SELECT COALESCE(SUM(CASE WHEN status IN ('completed','Đã duyệt')
        THEN total_amount ELSE 0 END),0) AS revenue, COUNT(*) AS total_orders
    FROM orders WHERE DATE(created_at) BETWEEN ? AND ?
");
if ($previousStmt) {
    $previousStmt->bind_param("ss", $previousStart, $previousEnd);
    $previousStmt->execute();
    $previousRow = $previousStmt->get_result()->fetch_assoc() ?: [];
    $previousRevenue = (float)($previousRow['revenue'] ?? 0);
    $previousOrders = (int)($previousRow['total_orders'] ?? 0);
    $previousStmt->close();
}
$revenueGrowth = $previousRevenue > 0 ? (($reportRevenue - $previousRevenue) / $previousRevenue) * 100 : null;
$orderGrowth = $previousOrders > 0 ? (($reportOrders - $previousOrders) / $previousOrders) * 100 : null;

/* Biểu đồ: theo tháng khi xem năm; theo ngày ở các kỳ còn lại. */
$reportLabels = [];
$reportRevenueData = [];
$reportOrderData = [];
$groupFormat = ($reportPeriod === 'year') ? '%Y-%m' : '%Y-%m-%d';
$chartStmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, ?) AS period_key,
           COALESCE(SUM(CASE WHEN status IN ('completed','Đã duyệt')
               THEN total_amount ELSE 0 END),0) AS revenue,
           COUNT(*) AS order_count
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, ?)
    ORDER BY period_key
");
$chartMap = [];
if ($chartStmt) {
    $chartStmt->bind_param("ssss", $groupFormat, $reportStart, $reportEnd, $groupFormat);
    $chartStmt->execute();
    $chartResult = $chartStmt->get_result();
    while ($row = $chartResult->fetch_assoc()) $chartMap[$row['period_key']] = $row;
    $chartStmt->close();
}
if ($reportPeriod === 'year') {
    for ($m = 1; $m <= 12; $m++) {
        $key = sprintf('%04d-%02d', $selectedYear, $m);
        $reportLabels[] = 'Tháng ' . $m;
        $reportRevenueData[] = (float)($chartMap[$key]['revenue'] ?? 0);
        $reportOrderData[] = (int)($chartMap[$key]['order_count'] ?? 0);
    }
} else {
    $cursor = new DateTime($reportStart);
    $lastDay = new DateTime($reportEnd);
    while ($cursor <= $lastDay) {
        $key = $cursor->format('Y-m-d');
        $reportLabels[] = $cursor->format('d/m');
        $reportRevenueData[] = (float)($chartMap[$key]['revenue'] ?? 0);
        $reportOrderData[] = (int)($chartMap[$key]['order_count'] ?? 0);
        $cursor->modify('+1 day');
    }
}

/* Top sản phẩm: dựa trên chi tiết đơn hàng thực tế, không dùng cột sold tổng lũy kế. */
$topProducts = [];
$productStmt = $conn->prepare("
    SELECT p.id, p.name,
           COALESCE(SUM(od.quantity),0) AS quantity_sold,
           COALESCE(SUM(od.quantity * od.price),0) AS product_revenue
    FROM order_details od
    INNER JOIN orders o ON o.id = od.order_id
    INNER JOIN products p ON p.id = od.product_id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
      AND o.status IN ('completed','Đã duyệt')
    GROUP BY p.id, p.name
    ORDER BY quantity_sold DESC, product_revenue DESC
    LIMIT 10
");
if ($productStmt) {
    $productStmt->bind_param("ss", $reportStart, $reportEnd);
    $productStmt->execute();
    $productResult = $productStmt->get_result();
    while ($row = $productResult->fetch_assoc()) $topProducts[] = $row;
    $productStmt->close();
}

/* Phân tích phương thức thanh toán. */
$paymentStats = [];
$paymentStmt = $conn->prepare("
    SELECT payment_method, COUNT(*) AS order_count,
           COALESCE(SUM(CASE WHEN status IN ('completed','Đã duyệt')
               THEN total_amount ELSE 0 END),0) AS revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY order_count DESC
");
if ($paymentStmt) {
    $paymentStmt->bind_param("ss", $reportStart, $reportEnd);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    while ($row = $paymentResult->fetch_assoc()) $paymentStats[] = $row;
    $paymentStmt->close();
}

/* Khách hàng: định danh theo số điện thoại trong orders. */
$topCustomers = [];
$customerStmt = $conn->prepare("
    SELECT phone, MAX(fullname) AS fullname,
           COUNT(*) AS order_count,
           COALESCE(SUM(CASE WHEN status IN ('completed','Đã duyệt')
               THEN total_amount ELSE 0 END),0) AS total_spent
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY phone
    ORDER BY total_spent DESC, order_count DESC
    LIMIT 10
");
if ($customerStmt) {
    $customerStmt->bind_param("ss", $reportStart, $reportEnd);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    while ($row = $customerResult->fetch_assoc()) $topCustomers[] = $row;
    $customerStmt->close();
}
$newCustomerStmt = $conn->prepare("SELECT COUNT(*) AS total FROM customers WHERE DATE(created_at) BETWEEN ? AND ?");
if ($newCustomerStmt) {
    $newCustomerStmt->bind_param("ss", $reportStart, $reportEnd);
    $newCustomerStmt->execute();
    $reportNewCustomers = (int)($newCustomerStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $newCustomerStmt->close();
}
$returningStmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM (
        SELECT phone FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
          AND status IN ('completed','Đã duyệt')
        GROUP BY phone HAVING COUNT(*) >= 2
    ) AS returning_customers
");
if ($returningStmt) {
    $returningStmt->bind_param("ss", $reportStart, $reportEnd);
    $returningStmt->execute();
    $reportReturningCustomers = (int)($returningStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $returningStmt->close();
}

/* Danh sách đơn trong kỳ, có phân trang 20 dòng. */
$reportPage = max(1, (int)($_GET['report_page'] ?? 1));
$reportPerPage = 20;
$reportOffset = ($reportPage - 1) * $reportPerPage;
$reportOrderRows = [];
$reportOrderCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
$reportOrderTotal = 0;
if ($reportOrderCountStmt) {
    $reportOrderCountStmt->bind_param("ss", $reportStart, $reportEnd);
    $reportOrderCountStmt->execute();
    $reportOrderTotal = (int)($reportOrderCountStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $reportOrderCountStmt->close();
}
$reportTotalPages = max(1, (int)ceil($reportOrderTotal / $reportPerPage));
if ($reportPage > $reportTotalPages) {
    $reportPage = $reportTotalPages;
    $reportOffset = ($reportPage - 1) * $reportPerPage;
}
$orderRowsStmt = $conn->prepare("
    SELECT id, fullname, phone, total_amount, payment_method, status, created_at
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
if ($orderRowsStmt) {
    $orderRowsStmt->bind_param("ssii", $reportStart, $reportEnd, $reportPerPage, $reportOffset);
    $orderRowsStmt->execute();
    $orderRowsResult = $orderRowsStmt->get_result();
    while ($row = $orderRowsResult->fetch_assoc()) $reportOrderRows[] = $row;
    $orderRowsStmt->close();
}

/* Xuất Excel dạng CSV UTF-8 BOM, giữ đúng bộ lọc hiện tại. */
if ($view === 'reports' && isset($_GET['export_report']) && $_GET['export_report'] === 'excel') {
    $filename = 'VietDuc_BaoCao_' . $reportStart . '_den_' . $reportEnd . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['BAO CAO THONG KE VIET DUC PHARMACY']);
    fputcsv($out, ['Tu ngay', $reportStart, 'Den ngay', $reportEnd]);
    fputcsv($out, ['Doanh thu', $reportRevenue, 'Tong don', $reportOrders, 'Don hoan thanh', $reportCompleted]);
    fputcsv($out, ['Don cho xu ly', $reportPending, 'Don dang xu ly', $reportProcessing, 'Don dang giao', $reportShipping, 'Don da huy', $reportCancelled]);
    fputcsv($out, []);
    fputcsv($out, ['TOP SAN PHAM', 'So luong ban', 'Doanh thu']);
    foreach ($topProducts as $item) fputcsv($out, [$item['name'], $item['quantity_sold'], $item['product_revenue']]);
    fputcsv($out, []);
    fputcsv($out, ['TOP KHACH HANG', 'So dien thoai', 'So don', 'Tong chi tieu']);
    foreach ($topCustomers as $item) fputcsv($out, [$item['fullname'], $item['phone'], $item['order_count'], $item['total_spent']]);
    fputcsv($out, []);
    fputcsv($out, ['CHI TIET DON HANG', 'Khach hang', 'So dien thoai', 'Ngay dat', 'Thanh toan', 'Trang thai', 'Tong tien']);
    $allExportStmt = $conn->prepare("
        SELECT id, fullname, phone, created_at, payment_method, status, total_amount
        FROM orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC
    ");
    if ($allExportStmt) {
        $allExportStmt->bind_param("ss", $reportStart, $reportEnd);
        $allExportStmt->execute();
        $allExportResult = $allExportStmt->get_result();
        while ($r = $allExportResult->fetch_assoc()) {
            fputcsv($out, [$r['id'], $r['fullname'], $r['phone'], $r['created_at'], $r['payment_method'], $r['status'], $r['total_amount']]);
        }
        $allExportStmt->close();
    }
    fclose($out);
    exit();
}

$sub_categories_json = json_encode([
    'thuc-pham-chuc-nang' => ['vitamin' => 'Vitamin', 'mien-dich' => 'Miễn dịch', 'tieu-hoa' => 'Tiêu hoá'],
    'duoc-my-pham' => ['cham-soc-da-mat' => 'Da mặt', 'kem-chong-nang' => 'Chống nắng'],
    'thuoc' => ['giam-dau-ha-sot' => 'Giảm đau', 'cam-cum' => 'Cảm cúm']
]);
$editProduct = (isset($_GET['edit_id'])) ? $conn->query("SELECT * FROM products WHERE id=".(int)$_GET['edit_id'])->fetch_assoc() : null;
$editCoupon = (isset($_GET['edit_coupon_id'])) ? $conn->query("SELECT * FROM coupons WHERE id=".(int)$_GET['edit_coupon_id'])->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viet Duc Admin | Quản Trị Hệ Thống</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="css/admin.user.css">
    <?php if ($view === "chat"): ?><style>
body{background:#f3f6fb;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.chat-shell{max-width:1400px;margin:24px auto;padding:0 14px}.chat-layout{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;height:calc(100vh - 150px);min-height:520px}.pane{background:#fff;border:1px solid #e4e9f2;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px #1831530b}.pane-head{padding:16px;border-bottom:1px solid #edf0f5}.conversation-list{height:calc(100% - 72px);overflow:auto}.conv-item{width:100%;text-align:left;background:#fff;border:0;border-bottom:1px solid #f0f2f7;padding:13px 15px}.conv-item.active,.conv-item:hover{background:#eef5ff}.conv-last{font-size:12px;color:#65748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.chat-messages{height:calc(100% - 140px);overflow:auto;padding:18px;background:#f8faff}.bubble{max-width:78%;margin:8px 0;padding:10px 13px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere}.bubble.visitor{background:#fff;border:1px solid #e1e7f0;margin-right:auto}.bubble.ai{background:#eef3fa;color:#344054;margin-right:auto}.bubble.staff{background:#d8f6e8;margin-left:auto}.bubble.system{background:#fff3cd;margin:8px auto;text-align:center;font-size:12px}.bubble small{display:block;font-size:10px;opacity:.65;margin-top:4px}.chat-compose{padding:12px;border-top:1px solid #edf0f5;display:flex;gap:8px}.mode-chip{font-size:12px}.conv-item .unread{font-size:11px}.empty{color:#728097;text-align:center;padding:60px 12px}@media(max-width:760px){.chat-layout{grid-template-columns:1fr;height:auto}.pane:first-child{height:260px}.pane:last-child{height:65vh}.conversation-list{height:190px}.chat-messages{height:calc(65vh - 140px)}}
.admin-chat-page{background:#fff;border:1px solid #e7ecf3;border-radius:16px;padding:18px;}
.admin-chat-page .chat-layout{height:calc(100vh - 260px);min-height:500px;}
</style><?php endif; ?>
</head>
<body>

<div class="sidebar">
    <div class="p-4 mb-3">
        <h4 class="text-info fw-bold mb-0">QUẢN LÝ</h4>
        <small class="text-muted">Pharmacy Admin</small>
        
    </div>
    <a href="?view=dashboard" class="<?= $view=='dashboard'?'active':'' ?>"><i class="fas fa-chart-pie me-2"></i> <span>Dashboard</span></a>
    <a href="?view=orders" class="<?= $view=='orders'?'active':'' ?>"><i class="fas fa-shopping-bag me-2"></i> <span>Đơn hàng</span></a>
    <a href="?view=reports" class="<?= $view=='reports'?'active':'' ?>"><i class="fas fa-chart-line me-2"></i> <span>Báo cáo & Thống kê</span></a>
    <a href="?view=products" class="<?= $view=='products'?'active':'' ?>"><i class="fas fa-boxes me-2"></i> <span>Sản phẩm</span></a>
    <a href="?view=nhasx" class="<?= $view=='nhasx'?'active':'' ?>"><i class="fas fa-industry me-2"></i><span>Nhà sản xuất</span>
</a>
    <a href="?view=customers" class="<?= $view=='customers'?'active':'' ?>"><i class="fas fa-users me-2"></i> <span>Khách hàng</span></a>
    <a href="?view=consult" class="<?= $view=='consult'?'active':'' ?>">
<i class="fas fa-phone me-2"></i> <span>Tư vấn khách</span>
</a>
   
   <a href="?view=users" class="<?= $view=='users'?'active':'' ?>">
<i class="fas fa-user-shield me-2"></i> <span>Người dùng</span>
</a>
    <a href="?view=newsletter" class="<?= $view=='newsletter'?'active':'' ?>"><i class="fas fa-envelope-open-text me-2"></i> <span>Khuyến mãi</span></a>
     <a href="?view=chat" class="<?= $view==='chat'?'active':'' ?>"><i class="fas fa-comments me-2"></i> <span>Chat khách hàng</span></a>
     <a href="admin.gocsk.php"><i class="fas fa-heartbeat me-2"></i> <span> Quản lý góc sức khỏe</span>
</a>
<a href="index.php" target="_blank"><i class="fas fa-globe me-2"></i> <span>Xem Website</span>
</a>
    <a href="logout.php" class="text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> <span>Đăng xuất</span></a>
</div>

<div class="main">
    <?php if ($view === 'chat'): ?>
        <div class="admin-chat-page">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h4 class="mb-1 fw-bold">Hộp thư tư vấn trực tuyến</h4>
                    <div class="small text-secondary">Quản lý hội thoại AI và tiếp nhận trả lời khách hàng trực tiếp</div>
                </div>
                <button class="btn btn-outline-primary btn-sm" id="refreshList">Làm mới hội thoại</button>
            </div>
            <div class="chat-layout">
                <section class="pane">
                    <div class="pane-head d-flex justify-content-between align-items-center">
                        <strong>Danh sách hội thoại</strong>
                    </div>
                    <div id="conversationList" class="conversation-list">
                        <div class="empty">Đang tải hội thoại…</div>
                    </div>
                </section>
                <section class="pane">
                    <div class="pane-head d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong id="activeTitle">Chọn hội thoại</strong>
                            <div id="activeMeta" class="small text-secondary">Tin nhắn mới sẽ xuất hiện tự động</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-sm btn-success" id="takeOver" disabled>Nhân viên tiếp nhận</button>
                            <button class="btn btn-sm btn-outline-primary" id="returnBot" disabled>Trả lại AI</button>
                            <button class="btn btn-sm btn-outline-danger" id="closeChat" disabled>Đóng</button>
                        </div>
                    </div>
                    <div id="messages" class="chat-messages">
                        <div class="empty">Chọn một hội thoại ở bên trái để xem tin nhắn.</div>
                    </div>
                    <form id="replyForm" class="chat-compose">
                        <input id="replyInput" class="form-control" maxlength="3000"
                               placeholder="Nhập phản hồi của nhân viên…" disabled>
                        <button class="btn btn-primary" id="sendReply" disabled>Gửi</button>
                    </form>
                </section>
            </div>
            <div id="statusMsg" class="small text-secondary mt-2">Cập nhật tự động mỗi vài giây.</div>
        </div>
    <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0 text-dark">Hệ thống Quản trị / <span class="text-primary text-capitalize"><?= $view ?></span></h3>
        <?php if($view != 'dashboard' && $view != 'customers' && $view != 'reports'): ?>
        <div>
            <a href="?action=export&view=<?= $view ?>" class="btn btn-success btn-sm btn-round shadow-sm"><i class="fas fa-file-excel me-2"></i>Excel</a>
            <button onclick="exportTableToPDF()" class="btn btn-danger btn-sm btn-round ms-2 shadow-sm"><i class="fas fa-file-pdf me-2"></i>PDF</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if($view=='dashboard'): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-3"><div class="card card-stat bg-primary">Doanh thu<h3 class="fw-bold mt-2"><?= number_format($revenue) ?>đ</h3></div></div>
            <div class="col-md-3"><div class="card card-stat bg-warning text-dark">Chưa duyệt<h3 class="fw-bold mt-2"><?= $newOrders ?></h3></div></div>
            <div class="col-md-3"><div class="card card-stat bg-success">Tổng đơn<h3 class="fw-bold mt-2"><?= $totalOrders ?></h3></div></div>
            <div class="col-md-3"><div class="card card-stat bg-info">Đăng ký mới<h3 class="fw-bold mt-2"><?= $totalNews ?></h3></div></div>
        </div>
        <div class="table-box">
            <h5 class="fw-bold mb-4">Phân tích doanh thu 7 ngày gần nhất</h5>
            <canvas id="chart" height="100"></canvas>

            <div class="table-responsive mt-4">
<table class="table table-bordered">
<thead class="table-light">
<tr>
<th>Ngày</th>
<th>Doanh thu</th>
<th>Số đơn</th>
</tr>
</thead>

<tbody>

<?php
for($i=0;$i<count($chart_labels);$i++){
?>

<tr>

<td><?= $chart_labels[$i] ?></td>

<td class="fw-bold text-primary">
<?= number_format($chart_revenue[$i]) ?> đ
</td>

<td class="fw-bold text-success">
<?= $chart_orders[$i] ?> đơn
</td>

</tr>

<?php } ?>

</tbody>
</table>
</div>
        </div>
    <?php endif; ?>


    <?php if ($view === 'reports'): ?>
    <style>
      .report-page{color:#172033}
      /* ĐỒNG BỘ CỠ CHỮ TOÀN BỘ KHU VỰC BÁO CÁO & THỐNG KÊ */
      .report-page,
      .report-page * {
        font-size: 20px !important;
      }
      .report-page canvas {
        font-size: 20px !important;
      }
      .report-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px}
      .report-heading p{color:#718096;margin:5px 0 0}
      .report-filter,.report-panel,.report-kpi{background:#fff;border:1px solid #e7ecf3;border-radius:14px;box-shadow:0 4px 18px rgba(15,35,70,.045)}
      .report-filter{padding:18px;margin-bottom:20px}
      .report-kpi{padding:18px;height:100%;position:relative;overflow:hidden}
      .report-kpi .kpi-label{font-size:11px;font-weight:750;letter-spacing:.5px;color:#718096;text-transform:uppercase}
      .report-kpi .kpi-value{font-size:clamp(20px,2vw,27px);font-weight:800;color:#172033;margin:9px 0 5px;line-height:1.25}
      .report-kpi .kpi-note{font-size:11px;color:#8994a5}
      .report-kpi .kpi-icon{position:absolute;right:16px;top:16px;width:38px;height:38px;border-radius:11px;display:grid;place-items:center;font-size:16px}
      .report-panel{padding:20px;margin-bottom:20px}
      .report-panel h5{font-weight:750;margin:0;font-size:16px}
      .report-subtitle{font-size:12px;color:#8994a5;margin-top:5px}
      .report-chart-wrap{height:320px;position:relative;margin-top:16px}
      .report-table th{font-size:11px;text-transform:uppercase;letter-spacing:.35px;color:#718096;background:#f7f9fc;white-space:nowrap}
      .report-table td{vertical-align:middle;font-size:13px}
      .report-pill{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
      .report-stat-line{display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid #eef1f5;font-size:13px}
      .report-stat-line:last-child{border-bottom:0}
      /* Customer insight section: prevent KPI cards from stretching into empty blocks */
      .report-customer-row{align-items:stretch}
      .report-customer-stack{display:flex;flex-direction:column;gap:14px;height:100%}
      .report-customer-kpi{height:auto!important;min-height:0;flex:1;padding:17px 18px;border-radius:14px}
      .report-customer-kpi .kpi-value{font-size:29px;margin:7px 0 3px}
      .customer-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:10px;border-top:1px solid #edf1f6;color:#8491a3;font-size:11px}
      .customer-card-tag{font-size:10px;font-weight:800;letter-spacing:.7px;padding:4px 8px;border-radius:20px;background:#f2f6fb;color:#63748b}
      .report-customer-new{background:linear-gradient(135deg,#fff 0%,#f8fffc 100%)}
      .report-customer-return{background:linear-gradient(135deg,#fff 0%,#fbf9ff 100%)}
      .report-top-customers{padding:18px 20px}
      .report-top-badge{font-size:10px;font-weight:800;letter-spacing:.5px;color:#a36a12;background:#fff5df;border:1px solid #f6e6bd;border-radius:20px;padding:6px 10px}
      .report-customer-rank{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px solid #eef1f5}
      .report-customer-rank:last-child{border-bottom:0}
      .customer-rank-left{display:flex;align-items:center;gap:10px;min-width:0}
      .customer-rank-number{width:23px;height:23px;flex:0 0 23px;border-radius:8px;display:grid;place-items:center;background:#f1f4f8;color:#64748b;font-size:11px;font-weight:800}
      .customer-rank-number.rank-1{background:#fff1cb;color:#b77908}
      .customer-rank-number.rank-2{background:#edf1f6;color:#65758b}
      .customer-rank-number.rank-3{background:#f8e9dc;color:#a75b2a}
      .customer-rank-avatar{width:34px;height:34px;flex:0 0 34px;border-radius:11px;display:grid;place-items:center;background:#edf4ff;color:#3476cf;font-weight:800}
      .customer-rank-info{display:flex;flex-direction:column;min-width:0}
      .customer-rank-info strong{font-size:12px;color:#25344a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .customer-rank-info small{font-size:10px;color:#8995a5;margin-top:3px}
      .customer-rank-total{font-size:12px;color:#1769d2;white-space:nowrap}
      .report-empty-state{display:flex;align-items:center;justify-content:center;gap:10px;min-height:125px;color:#8b98a9;font-size:13px}
      .report-empty-state i{font-size:22px;opacity:.55}
      /* Tăng khả năng đọc cho khu vực phân tích sản phẩm và thanh toán */
      .report-detail-panel{padding:23px}
      .report-detail-panel h5{font-size:19px;line-height:1.35;font-weight:800;color:#172b4d}
      .report-detail-panel .report-subtitle{font-size:13px;line-height:1.5;margin-top:7px}
      .report-detail-panel .report-table th{font-size:12px;letter-spacing:.45px;padding:12px 10px}
      .report-detail-panel .report-table td{font-size:14px;padding:11px 10px;line-height:1.5}
      .report-products-panel .report-table td:nth-child(2){min-width:260px;font-weight:600}
      .report-products-panel .report-table td:nth-child(1){font-size:14px;font-weight:700;color:#52627a}
      .report-payment-panel .report-chart-wrap{height:245px!important;margin-top:20px}
      .report-payment-panel .report-table td:first-child{font-weight:600}
      .report-payment-panel .report-table td:nth-child(2),
      .report-payment-panel .report-table td:nth-child(3){font-variant-numeric:tabular-nums}
      @media(max-width:768px){
        .report-detail-panel{padding:16px}
        .report-detail-panel h5{font-size:17px}
        .report-detail-panel .report-table th{font-size:11px}
        .report-detail-panel .report-table td{font-size:13px;padding:9px 8px}
        .report-payment-panel .report-chart-wrap{height:220px!important}
      }
      .report-section-title{font-size:16px;font-weight:750}
      .report-growth{font-size:11px;font-weight:700}
      .report-note{border:1px solid #f2dfb5;background:#fffaf0;color:#806020;border-radius:10px;padding:11px 13px;font-size:12px}
      .report-pagination .page-link{color:#315f9f}
      @media(max-width:768px){
        .report-panel{padding:14px}
        .report-chart-wrap{height:260px}
        .report-customer-stack{gap:10px}
        .report-customer-kpi{padding:14px}
        .report-top-customers{padding:15px}
        .customer-rank-total{font-size:11px}
      }
      @media print{
        .sidebar,.report-filter,.report-actions,.main>div:first-child,.pagination,.btn{display:none!important}
        .main{margin:0!important;padding:0!important;width:100%!important}
        .report-panel,.report-kpi{box-shadow:none;break-inside:avoid}
        body{background:#fff!important}
        .report-page{padding:0!important}
      }
    </style>

    <div class="report-page">
      <div class="report-heading">
        <div>
          <h3 class="fw-bold mb-0">Báo cáo &amp; Thống kê</h3>
          <p>Theo dõi doanh thu, đơn hàng, sản phẩm và khách hàng theo kỳ báo cáo</p>
        </div>
        <div class="d-flex gap-2 report-actions">
          <a class="btn btn-outline-success" href="?view=reports&amp;period=<?= urlencode($reportPeriod) ?>&amp;month=<?= urlencode($selectedMonth) ?>&amp;year=<?= (int)$selectedYear ?>&amp;quarter=<?= (int)($_GET['quarter'] ?? 1) ?>&amp;start_date=<?= urlencode($reportStart) ?>&amp;end_date=<?= urlencode($reportEnd) ?>&amp;export_report=excel">
            <i class="fas fa-file-excel me-2"></i>Xuất Excel
          </a>
          <button type="button" class="btn btn-outline-danger" onclick="window.print()">
            <i class="fas fa-file-pdf me-2"></i>In / Lưu PDF
          </button>
        </div>
      </div>

      <form method="GET" class="report-filter report-actions">
        <input type="hidden" name="view" value="reports">
        <div class="row g-3 align-items-end">
          <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold small">Kỳ báo cáo</label>
            <select name="period" id="report-period" class="form-select" onchange="toggleReportFields()">
              <option value="today" <?= $reportPeriod==='today'?'selected':'' ?>>Hôm nay</option>
              <option value="week" <?= $reportPeriod==='week'?'selected':'' ?>>7 ngày gần nhất</option>
              <option value="month" <?= $reportPeriod==='month'?'selected':'' ?>>Theo tháng</option>
              <option value="quarter" <?= $reportPeriod==='quarter'?'selected':'' ?>>Theo quý</option>
              <option value="year" <?= $reportPeriod==='year'?'selected':'' ?>>Theo năm</option>
              <option value="custom" <?= $reportPeriod==='custom'?'selected':'' ?>>Tùy chọn ngày</option>
            </select>
          </div>
          <div class="col-xl-2 col-md-4" id="report-month-field" style="<?= $reportPeriod==='month'?'':'display:none' ?>">
            <label class="form-label fw-semibold small">Tháng</label>
            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>">
          </div>
          <div class="col-xl-2 col-md-4" id="report-quarter-field" style="<?= $reportPeriod==='quarter'?'':'display:none' ?>">
            <label class="form-label fw-semibold small">Quý</label>
            <select name="quarter" class="form-select">
              <?php for($q=1;$q<=4;$q++): ?>
              <option value="<?= $q ?>" <?= (int)($_GET['quarter'] ?? ceil(date('n')/3))===$q?'selected':'' ?>>Quý <?= $q ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-xl-2 col-md-4" id="report-year-field" style="<?= in_array($reportPeriod,['year','quarter'],true)?'':'display:none' ?>">
            <label class="form-label fw-semibold small">Năm</label>
            <select name="year" class="form-select">
              <?php for($y=(int)date('Y');$y>=(int)date('Y')-5;$y--): ?>
              <option value="<?= $y ?>" <?= $selectedYear===$y?'selected':'' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-xl-2 col-md-4" id="report-start-field" style="<?= $reportPeriod==='custom'?'':'display:none' ?>">
            <label class="form-label fw-semibold small">Từ ngày</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? $reportStart) ?>">
          </div>
          <div class="col-xl-2 col-md-4" id="report-end-field" style="<?= $reportPeriod==='custom'?'':'display:none' ?>">
            <label class="form-label fw-semibold small">Đến ngày</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? $reportEnd) ?>">
          </div>
          <div class="col-xl-2 col-md-4">
            <button class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Áp dụng</button>
          </div>
        </div>
        <div class="small text-muted mt-3"><i class="far fa-calendar-alt me-1"></i>
          Kỳ báo cáo: <strong><?= date('d/m/Y',strtotime($reportStart)) ?></strong> – <strong><?= date('d/m/Y',strtotime($reportEnd)) ?></strong>
         
        </div>
      </form>

      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="report-kpi">
            <span class="kpi-icon" style="background:#eaf2ff;color:#2875df"><i class="fas fa-coins"></i></span>
            <div class="kpi-label">Doanh thu ghi nhận</div>
            <div class="kpi-value"><?= number_format($reportRevenue,0,',','.') ?>đ</div>
            <div class="kpi-note">Đơn hoàn thành / trạng thái cũ đã duyệt</div>
            <div class="report-growth mt-2 <?= $revenueGrowth===null?'text-muted':($revenueGrowth>=0?'text-success':'text-danger') ?>">
              <?= $revenueGrowth===null?'Chưa có dữ liệu kỳ trước':(($revenueGrowth>=0?'↑ ':'↓ ').number_format(abs($revenueGrowth),1).'% so với kỳ trước') ?>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="report-kpi">
            <span class="kpi-icon" style="background:#e8f7ef;color:#15945b"><i class="fas fa-receipt"></i></span>
            <div class="kpi-label">Tổng đơn hàng</div>
            <div class="kpi-value"><?= number_format($reportOrders) ?></div>
            <div class="kpi-note">Tất cả trạng thái trong kỳ</div>
            <div class="report-growth mt-2 <?= $orderGrowth===null?'text-muted':($orderGrowth>=0?'text-success':'text-danger') ?>">
              <?= $orderGrowth===null?'Chưa có dữ liệu kỳ trước':(($orderGrowth>=0?'↑ ':'↓ ').number_format(abs($orderGrowth),1).'% so với kỳ trước') ?>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="report-kpi">
            <span class="kpi-icon" style="background:#fff4e5;color:#d58a18"><i class="fas fa-chart-line"></i></span>
            <div class="kpi-label">Giá trị đơn hoàn thành TB</div>
            <div class="kpi-value"><?= number_format($reportAverage,0,',','.') ?>đ</div>
            <div class="kpi-note">Doanh thu / số đơn hoàn thành</div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="report-kpi">
            <span class="kpi-icon" style="background:#f2ecff;color:#805ad5"><i class="fas fa-user-check"></i></span>
            <div class="kpi-label">Đơn hoàn thành</div>
            <div class="kpi-value"><?= number_format($reportCompleted) ?></div>
            <div class="kpi-note">Tỷ lệ hoàn thành: <?= $reportOrders>0?number_format($reportCompleted/$reportOrders*100,1):'0.0' ?>%</div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-8">
          <div class="report-panel h-100">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div><h5>Xu hướng doanh thu và đơn hàng</h5><div class="report-subtitle">Doanh thu chỉ tính đơn hoàn thành; số đơn gồm mọi trạng thái</div></div>
              <span class="badge bg-light text-dark"><?= $reportPeriod==='year'?'Theo tháng':'Theo ngày' ?></span>
            </div>
            <div class="report-chart-wrap"><canvas id="reportRevenueChart"></canvas></div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="report-panel h-100">
            <h5>Cơ cấu trạng thái đơn hàng</h5>
            <div class="report-subtitle">Phân loại toàn bộ đơn phát sinh trong kỳ</div>
            <div class="report-chart-wrap" style="height:245px"><canvas id="reportStatusChart"></canvas></div>
            <div class="report-stat-line"><span>Hoàn thành</span><strong><?= $reportCompleted ?></strong></div>
            <div class="report-stat-line"><span>Chờ xử lý</span><strong><?= $reportPending ?></strong></div>
            <div class="report-stat-line"><span>Đang xử lý</span><strong><?= $reportProcessing ?></strong></div>
            <div class="report-stat-line"><span>Đang giao</span><strong><?= $reportShipping ?></strong></div>
            <div class="report-stat-line"><span>Đã hủy</span><strong><?= $reportCancelled ?></strong></div>
            <?php if($reportOtherStatus>0): ?><div class="report-stat-line"><span>Trạng thái khác</span><strong><?= $reportOtherStatus ?></strong></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-7">
          <div class="report-panel h-100 report-detail-panel report-products-panel">
            <h5>Top 10 sản phẩm bán chạy</h5>
            <div class="table-responsive mt-3">
              <table class="table table-hover report-table mb-0">
                <thead><tr><th>#</th><th>Sản phẩm</th><th class="text-end">SL bán</th><th class="text-end">Doanh thu</th></tr></thead>
                <tbody>
                <?php if(!$topProducts): ?><tr><td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu chi tiết sản phẩm trong kỳ.</td></tr>
                <?php else: foreach($topProducts as $i=>$product): ?>
                  <tr><td><?= $i+1 ?></td><td class="fw-semibold"><?= htmlspecialchars($product['name']) ?></td><td class="text-end"><?= number_format((int)$product['quantity_sold']) ?></td><td class="text-end fw-bold text-primary"><?= number_format((float)$product['product_revenue'],0,',','.') ?>đ</td></tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-xl-5">
          <div class="report-panel h-100 report-detail-panel report-payment-panel">
            <h5>Phương thức thanh toán</h5>
            <div class="report-subtitle">Số đơn và doanh thu theo phương thức thanh toán</div>
            <div class="report-chart-wrap" style="height:220px"><canvas id="reportPaymentChart"></canvas></div>
            <div class="table-responsive mt-2">
              <table class="table table-sm report-table mb-0">
                <thead><tr><th>Phương thức</th><th class="text-end">Đơn</th><th class="text-end">Doanh thu</th></tr></thead>
                <tbody>
                <?php if(!$paymentStats): ?><tr><td colspan="3" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                <?php else: foreach($paymentStats as $pay): ?>
                  <tr><td><?= htmlspecialchars($pay['payment_method'] ?: 'Khác') ?></td><td class="text-end"><?= (int)$pay['order_count'] ?></td><td class="text-end"><?= number_format((float)$pay['revenue'],0,',','.') ?>đ</td></tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4 report-customer-row">
        <div class="col-xl-4 col-lg-5">
          <div class="report-customer-stack">
            <div class="report-kpi report-customer-kpi report-customer-new">
              <div class="d-flex align-items-start justify-content-between">
                <div>
                  <div class="kpi-label">Khách hàng mới</div>
                  <div class="kpi-value"><?= number_format($reportNewCustomers) ?></div>
                  <div class="kpi-note">Đăng ký trong kỳ báo cáo</div>
                </div>
                <span class="kpi-icon" style="position:static;background:#eaf8f5;color:#159b83"><i class="fas fa-user-plus"></i></span>
              </div>
              <div class="customer-card-footer">
                <span><i class="fas fa-user-plus me-1"></i> Tệp khách mới</span>
                <span class="customer-card-tag">NEW</span>
              </div>
            </div>

            <div class="report-kpi report-customer-kpi report-customer-return">
              <div class="d-flex align-items-start justify-content-between">
                <div>
                  <div class="kpi-label">Khách hàng quay lại</div>
                  <div class="kpi-value"><?= number_format($reportReturningCustomers) ?></div>
                  <div class="kpi-note">Có từ 2 đơn hoàn thành trong kỳ</div>
                </div>
                <span class="kpi-icon" style="position:static;background:#f1edff;color:#805ad5"><i class="fas fa-users"></i></span>
              </div>
              <div class="customer-card-footer">
                <span><i class="fas fa-repeat me-1"></i> Khách mua lặp lại</span>
                <span class="customer-card-tag">LOYAL</span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-8 col-lg-7">
          <div class="report-panel report-top-customers h-100 mb-0">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
              <div>
                <h5>Top khách hàng theo chi tiêu</h5>
                <div class="report-subtitle">Xếp hạng theo tổng giá trị đơn hoàn thành trong kỳ</div>
              </div>
              <span class="report-top-badge"><i class="fas fa-crown me-1"></i> TOP 5</span>
            </div>
            <?php if(!$topCustomers): ?>
              <div class="report-empty-state"><i class="fas fa-user-friends"></i><span>Chưa có dữ liệu khách hàng trong kỳ này.</span></div>
            <?php else: foreach(array_slice($topCustomers,0,5) as $i=>$customer): ?>
              <div class="report-customer-rank">
                <div class="customer-rank-left">
                  <span class="customer-rank-number rank-<?= $i+1 ?>"><?= $i+1 ?></span>
                  <span class="customer-rank-avatar"><?= strtoupper(substr(trim($customer['fullname'] ?: 'K'),0,1)) ?></span>
                  <span class="customer-rank-info">
                    <strong><?= htmlspecialchars($customer['fullname'] ?: 'Khách hàng') ?></strong>
                    <small><?= htmlspecialchars($customer['phone']) ?> <span class="mx-1">·</span> <?= (int)$customer['order_count'] ?> đơn</small>
                  </span>
                </div>
                <strong class="customer-rank-total"><?= number_format((float)$customer['total_spent'],0,',','.') ?>đ</strong>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <div class="report-panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div><h5>Chi tiết đơn hàng</h5><div class="report-subtitle">Có <?= number_format($reportOrderTotal) ?> đơn trong kỳ · Hiển thị <?= $reportPerPage ?> đơn mỗi trang</div></div>
          <span class="badge bg-primary-subtle text-primary">Trang <?= $reportPage ?>/<?= $reportTotalPages ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover report-table mb-0">
            <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Số điện thoại</th><th>Ngày đặt</th><th>Thanh toán</th><th>Trạng thái</th><th class="text-end">Tổng tiền</th></tr></thead>
            <tbody>
            <?php if(!$reportOrderRows): ?><tr><td colspan="7" class="text-center text-muted py-4">Không có đơn hàng trong kỳ đã chọn.</td></tr>
            <?php else: foreach($reportOrderRows as $ro):
              $statusText = $ro['status']; $statusClass = 'bg-secondary-subtle text-secondary';
              if(in_array($statusText,['completed','Đã duyệt'],true)){ $statusText='Hoàn thành'; $statusClass='bg-success-subtle text-success'; }
              elseif(in_array($statusText,['pending','Chờ xử lý'],true)){ $statusText='Chờ xử lý'; $statusClass='bg-warning-subtle text-warning'; }
              elseif($statusText==='processing'){ $statusText='Đang xử lý'; $statusClass='bg-info-subtle text-info'; }
              elseif($statusText==='shipping'){ $statusText='Đang giao'; $statusClass='bg-primary-subtle text-primary'; }
              elseif($statusText==='cancelled'){ $statusText='Đã hủy'; $statusClass='bg-danger-subtle text-danger'; }
            ?>
              <tr>
                <td class="fw-bold">#<?= (int)$ro['id'] ?></td>
                <td><?= htmlspecialchars($ro['fullname']) ?></td>
                <td><?= htmlspecialchars($ro['phone']) ?></td>
                <td><?= date('d/m/Y H:i',strtotime($ro['created_at'])) ?></td>
                <td><?= htmlspecialchars($ro['payment_method']) ?></td>
                <td><span class="report-pill <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span></td>
                <td class="text-end fw-bold"><?= number_format((float)$ro['total_amount'],0,',','.') ?>đ</td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if($reportTotalPages>1): ?>
        <nav class="mt-3 report-actions"><ul class="pagination pagination-sm justify-content-end report-pagination mb-0">
          <?php for($pg=1;$pg<=$reportTotalPages;$pg++): if($pg===1 || $pg===$reportTotalPages || abs($pg-$reportPage)<=2): ?>
          <li class="page-item <?= $pg===$reportPage?'active':'' ?>"><a class="page-link" href="?view=reports&amp;period=<?= urlencode($reportPeriod) ?>&amp;month=<?= urlencode($selectedMonth) ?>&amp;year=<?= $selectedYear ?>&amp;quarter=<?= (int)($_GET['quarter'] ?? 1) ?>&amp;start_date=<?= urlencode($reportStart) ?>&amp;end_date=<?= urlencode($reportEnd) ?>&amp;report_page=<?= $pg ?>"><?= $pg ?></a></li>
          <?php elseif(abs($pg-$reportPage)===3): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endfor; ?>
        </ul></nav>
        <?php endif; ?>
      </div>

      
    </div>

    <script>
    function toggleReportFields(){
      const p=document.getElementById('report-period').value;
      document.getElementById('report-month-field').style.display=p==='month'?'':'none';
      document.getElementById('report-quarter-field').style.display=p==='quarter'?'':'none';
      document.getElementById('report-year-field').style.display=(p==='year'||p==='quarter')?'':'none';
      ['report-start-field','report-end-field'].forEach(id=>{
        document.getElementById(id).style.display=p==='custom'?'':'none';
      });
    }
    document.addEventListener('DOMContentLoaded',function(){
      const labels=<?= json_encode($reportLabels,JSON_UNESCAPED_UNICODE) ?>;
      const revenues=<?= json_encode($reportRevenueData) ?>;
      const orderCounts=<?= json_encode($reportOrderData) ?>;
      const paymentLabels=<?= json_encode(array_map(function($x){return $x['payment_method'] ?: 'Khác';},$paymentStats),JSON_UNESCAPED_UNICODE) ?>;
      const paymentOrders=<?= json_encode(array_map(function($x){return (int)$x['order_count'];},$paymentStats)) ?>;
      if(window.Chart){
        // Đồng bộ chữ trong biểu đồ với cỡ chữ 20px của toàn bộ báo cáo.
        Chart.defaults.font.size = 20;
        const revenueCanvas=document.getElementById('reportRevenueChart');
        if(revenueCanvas){
          new Chart(revenueCanvas,{
            data:{labels:labels,datasets:[
              {type:'bar',label:'Doanh thu (VNĐ)',data:revenues,backgroundColor:'rgba(47,116,224,.18)',borderColor:'#2f74e0',borderWidth:1.5,borderRadius:5,yAxisID:'y'},
              {type:'line',label:'Số đơn',data:orderCounts,borderColor:'#16a36a',backgroundColor:'#16a36a',tension:.3,pointRadius:2.5,borderWidth:2,yAxisID:'y1'}
            ]},
            options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
              plugins:{legend:{position:'bottom'}},
              scales:{y:{beginAtZero:true,position:'left',ticks:{callback:v=>new Intl.NumberFormat('vi-VN',{notation:'compact'}).format(v)}},
                y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{precision:0}}}}
          });
        }
        const statusCanvas=document.getElementById('reportStatusChart');
        if(statusCanvas){
          new Chart(statusCanvas,{type:'doughnut',
            data:{labels:['Hoàn thành','Chờ xử lý','Đang xử lý','Đang giao','Đã hủy','Khác'],
              datasets:[{data:[<?= $reportCompleted ?>,<?= $reportPending ?>,<?= $reportProcessing ?>,<?= $reportShipping ?>,<?= $reportCancelled ?>,<?= $reportOtherStatus ?>],
                backgroundColor:['#16a36a','#f4b740','#42a5f5','#647eea','#e05252','#9aa6b2'],borderWidth:0}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom'}}}
          });
        }
        const paymentCanvas=document.getElementById('reportPaymentChart');
        if(paymentCanvas){
          new Chart(paymentCanvas,{type:'bar',
            data:{labels:paymentLabels,datasets:[{label:'Số đơn',data:paymentOrders,backgroundColor:'rgba(105,85,190,.72)',borderRadius:5}]},
            options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
          });
        }
      }
    });
    </script>
    <?php endif; ?>

    <?php if($view=='orders'): ?>
        <?php if (($_GET['status'] ?? '') === 'updated'): ?>
            <div class="alert alert-success">Cập nhật trạng thái đơn hàng thành công!</div>
        <?php elseif (($_GET['status'] ?? '') === 'invalid_transition'): ?>
            <div class="alert alert-warning">Không thể chuyển đơn hàng sang trạng thái này!</div>
        <?php elseif (($_GET['status'] ?? '') === 'not_found'): ?>
            <div class="alert alert-danger">Không tìm thấy đơn hàng!</div>
        <?php endif; ?>

        <div class="table-box">

        <?php if($orderDetail): ?>

<div class="card mb-4 shadow">
<div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">

<span>Chi tiết đơn hàng #<?= $orderDetail['id'] ?></span>

<a href="?view=orders" class="btn btn-sm btn-light">
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
                <table class="table table-hover align-middle" id="main-table">
                    <thead class="table-light">
                        <tr><th>Mã đơn</th><th>Khách hàng</th><th>SĐT</th><th>Thanh toán</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
                    </thead>
                    <tbody>
                        <?php $orders = $conn->query("SELECT * FROM orders ORDER BY id DESC");
                        while($o = $orders->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold">#<?= $o['id'] ?></td>
                            <td>
<a href="?view=orders&order_detail=<?= $o['id'] ?>" 
class="fw-bold text-primary text-decoration-none">
<?= $o['fullname'] ?>
</a>
</td>
                            <td><?= $o['phone'] ?></td>
                            <td class="text-primary fw-bold"><?= number_format($o['total_amount']) ?>đ</td>
                            <?php
$orderStatus = $o['status'];
if ($orderStatus === 'Đã duyệt') $orderStatus = 'processing';
if ($orderStatus === 'Chờ xử lý') $orderStatus = 'pending';
$statusLabels = [
    'pending' => ['Chờ xác nhận', 'bg-warning text-dark'],
    'processing' => ['Đang chuẩn bị', 'bg-info text-dark'],
    'shipping' => ['Đang giao hàng', 'bg-primary'],
    'completed' => ['Hoàn thành', 'bg-success'],
    'cancelled' => ['Đã hủy', 'bg-danger']
];
$statusInfo = $statusLabels[$orderStatus] ?? [$o['status'], 'bg-secondary'];
?>
<td><span class="badge <?= $statusInfo[1] ?>"><?= htmlspecialchars($statusInfo[0]) ?></span></td>
<td class="text-end">
    <div class="d-flex justify-content-end flex-wrap gap-1">
        <?php
        $nextActions = [
            'pending' => ['processing' => 'Xác nhận đơn', 'cancelled' => 'Hủy đơn'],
            'processing' => ['shipping' => 'Bắt đầu giao', 'cancelled' => 'Hủy đơn'],
            'shipping' => ['completed' => 'Hoàn thành']
        ];
        foreach (($nextActions[$orderStatus] ?? []) as $nextStatus => $buttonLabel):
        ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn <?= htmlspecialchars($buttonLabel, ENT_QUOTES) ?>?')">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="new_status" value="<?= htmlspecialchars($nextStatus) ?>">
                <button type="submit" name="update_order_status" value="1" class="btn btn-sm <?= $nextStatus === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= htmlspecialchars($buttonLabel) ?></button>
            </form>
        <?php endforeach; ?>
        <a href="?view=orders&delete_order=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa hẳn đơn hàng này?')"><i class="fas fa-trash"></i></a>
    </div>
</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

   <?php if($view=='products'): ?>
    <div class="mb-3 text-end">

<a href="themsp.php" class="btn btn-success btn-round shadow-sm">
<i class="fas fa-plus me-2"></i> Thêm sản phẩm
</a>

</div>
           <div class="col-md-12">
                <div class="table-box">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="main-table">
                            <thead><tr><th>Ảnh</th><th>Tên sản phẩm</th><th>Giá</th><th>Kho</th><th>Thao tác</th></tr></thead>
                            <tbody>
                                <?php $prods = $conn->query("SELECT * FROM products ORDER BY id DESC");
                                while($p = $prods->fetch_assoc()): ?>
                                <tr>
<td>
<img 
src="images/<?= !empty($p['image']) ? htmlspecialchars($p['image']) : 'no-image.png' ?>" 
width="45" 
height="45" 
style="object-fit:cover;border-radius:8px;">
</td>                                    <td class="fw-bold"><?= $p['name'] ?></td>
                                    <td><?= number_format($p['price_new']) ?>đ</td>
                                    <td><?= $p['stock'] ?></td>
                                    <td>
<td>

<a href="themsp.php?action=edit&id=<?= $p['id'] ?>" 
class="btn btn-sm btn-outline-primary border-0">
<i class="fas fa-edit"></i>
</a>

<a href="?view=products&delete_product=<?= $p['id'] ?>"
class="btn btn-sm btn-outline-danger border-0"
onclick="return confirm('Xóa sản phẩm này?')">
<i class="fas fa-trash"></i>
</a>

</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if($view=='consult'): ?>
<?php if(isset($_GET['msg']) && $_GET['msg']=='approved'): ?>

<div class="alert alert-success">
Đã đánh dấu khách đã liên hệ
</div>

<?php endif; ?>

<?php if(isset($_GET['msg']) && $_GET['msg']=='cancelled'): ?>

<div class="alert alert-warning">
Đã hủy trạng thái liên hệ
</div>

<?php endif; ?>

<div class="table-box">

<h5 class="fw-bold mb-4">Danh sách khách cần tư vấn</h5>

<table class="table table-hover align-middle">

<thead class="table-light">
<tr>
<th>ID</th>
<th>Số điện thoại</th>
<th>Ngày gửi</th>
<th>Trạng thái</th>
<th>Thao tác</th>
</tr>
</thead>

<tbody>

<?php
$list = $conn->query("SELECT * FROM consult_requests ORDER BY id ASC");

while($c = $list->fetch_assoc()):
?>

<tr>

<td><?= $c['id'] ?></td>

<td class="fw-bold text-primary">
<?= $c['phone'] ?>
</td>

<td>
<?= date('d/m/Y H:i',strtotime($c['created_at'])) ?>
</td>
<td>

<?php
$status = $c['status'];

if($status == "Đã liên hệ"){
$badge = "bg-success";
}else{
$badge = "bg-warning";
$status = "Chưa liên hệ";
}
?>

<span class="badge <?= $badge ?>">
<?= $status ?>
</span>

</td>
<td>

<!-- GỌI KHÁCH -->
<a href="tel:<?= $c['phone'] ?>"
class="btn btn-sm btn-primary">
<i class="fas fa-phone"></i>
</a>

<!-- CHAT ZALO -->
<a href="https://zalo.me/<?= $c['phone'] ?>"
target="_blank"
class="btn btn-sm btn-info">
<i class="fas fa-comment"></i>
</a>

<?php if($c['status'] != 'Đã liên hệ'): ?>

<a href="?view=consult&approve_consult=<?= $c['id'] ?>"
class="btn btn-sm btn-success">
Duyệt
</a>

<?php else: ?>

<a href="?view=consult&cancel_consult=<?= $c['id'] ?>"
class="btn btn-sm btn-warning">
Hủy
</a>

<?php endif; ?>

<a href="?view=consult&delete_consult=<?= $c['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Xóa yêu cầu này?')">
<i class="fas fa-trash"></i>
</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php endif; ?>

    <?php if($view=='customers'): ?>
        
<div class="table-box">
    <h5 class="fw-bold mb-4">Khách hàng chi tiêu nhiều nhất</h5>

    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
<th>Rank</th>
<th>Họ và tên</th>
<th>Số điện thoại</th>
<th>Số đơn</th>
<th>Tổng chi tiêu</th>
<th>Xóa</th>
</tr>
        </thead>

        <tbody>

     <?php
$cust = $conn->query("
SELECT 
fullname,
phone,
COUNT(id) AS orders,
SUM(total_amount) AS total
FROM orders
GROUP BY phone, fullname
ORDER BY total DESC
");

while($c = $cust->fetch_assoc()):

$rank = "🥉 Đồng";

if($c['total'] >= 10000000){
    $rank = "💎 Kim cương";
}
elseif($c['total'] >= 5000000){
    $rank = "🥇 Vàng";
}
elseif($c['total'] >= 2000000){
    $rank = "🥈 Bạc";
}
?>

<tr>
<td><?= $rank ?></td>
<td><?= $c['fullname'] ?></td>
<td><?= $c['phone'] ?></td>
<td><?= $c['orders'] ?> đơn</td>
<td><?= number_format($c['total']) ?>đ</td>
<td>
<a href="?view=customers&delete_customer=<?= $c['phone'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Xóa toàn bộ đơn của khách này?')">
<i class="fas fa-trash"></i>
</a>
</td>
</tr>

<?php endwhile; ?>
        </tbody>
    </table>

</div>
<?php endif; ?>

   <?php if($view=='users'): ?>

<?php if (($_GET['status'] ?? '') === 'cannot_delete_self'): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    Bạn không thể xóa tài khoản đang đăng nhập.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
</div>
<?php elseif (($_GET['status'] ?? '') === 'deleted'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    Đã xóa tài khoản thành công.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
</div>
<?php elseif (($_GET['status'] ?? '') === 'not_found'): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    Không tìm thấy tài khoản cần xóa.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
</div>
<?php elseif (($_GET['status'] ?? '') === 'delete_error'): ?>
<div class="alert alert-danger" role="alert">
    Không thể xóa tài khoản. Vui lòng thử lại.
</div>
<?php endif; ?>

<div class="row">

<div class="col-md-4">

<div class="table-box">

<h5 class="fw-bold mb-3">
<?= $editUser ? 'Cập nhật' : 'Thêm' ?> Tài khoản
</h5>

<form method="POST">

<input type="hidden" name="user_id" value="<?= $editUser['id'] ?? '' ?>">

<input
name="fullname"
class="form-control mb-2"
placeholder="Họ tên"
value="<?= htmlspecialchars($editUser['fullname'] ?? '') ?>"
required>

<input
name="email"
type="email"
class="form-control mb-2"
placeholder="Email"
value="<?= htmlspecialchars($editUser['email'] ?? '') ?>"
required>

<input
name="password"
type="password"
class="form-control mb-2"
placeholder="Mật khẩu (để trống nếu không đổi)">

<select name="role" class="form-select mb-3">

<option value="admin"
<?= ($editUser['role']??'')=='admin'?'selected':'' ?>>
Quản trị
</option>

<option value="staff"
<?= ($editUser['role']??'')=='staff'?'selected':'' ?>>
Nhân viên
</option>

<option value="customer"
<?= ($editUser['role']??'')=='customer'?'selected':'' ?>>
Khách hàng
</option>

</select>

<button name="save_user" class="btn btn-primary w-100 btn-round">
Lưu tài khoản
</button>

</form>

</div>

</div>


<div class="col-md-8">

<div class="table-box">

<h5 class="fw-bold mb-3">Danh sách tài khoản</h5>

<table class="table table-hover align-middle" id="main-table">

<thead class="table-light">
<tr>
<th>ID</th>
<th>Họ tên</th>
<th>Email</th>
<th>Vai trò</th>
<th class="text-end">Thao tác</th>
</tr>
</thead>

<tbody>

<?php
$users=$conn->query("SELECT * FROM users ORDER BY id DESC");
while($u=$users->fetch_assoc()):
?>

<tr>

<td><?= $u['id'] ?></td>

<td><?= htmlspecialchars($u['fullname']) ?></td>

<td><?= htmlspecialchars($u['email']) ?></td>

<td>

<?php if($u['role']=='admin'): ?>
<span class="badge bg-danger">Quản trị</span>

<?php elseif($u['role']=='staff'): ?>
<span class="badge bg-primary">Nhân viên</span>

<?php else: ?>
<span class="badge bg-success">Khách hàng</span>
<?php endif; ?>

</td>

<td class="text-end">

<td>

<td class="text-end">

<a href="?view=users&edit_user_id=<?= $u['id'] ?>"
class="btn btn-sm btn-outline-primary border-0">
<i class="fas fa-edit"></i>
</a>

<?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
<a href="?view=users&delete_user=<?= (int)$u['id'] ?>"
class="btn btn-sm btn-outline-danger border-0"
onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này?')"
title="Xóa tài khoản">
<i class="fas fa-trash"></i>
</a>
<?php else: ?>
<button type="button"
class="btn btn-sm btn-outline-secondary border-0"
disabled
title="Không thể xóa tài khoản đang đăng nhập"
aria-label="Không thể xóa tài khoản đang đăng nhập">
<i class="fas fa-lock"></i>
</button>
<?php endif; ?>

</td>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<?php endif; ?>
<?php if($view=='nhasx'): ?>

<div class="row">

<div class="col-md-4">

<div class="table-box">

<h5 class="fw-bold mb-3">
<?= $editNhasx ? 'Cập nhật' : 'Thêm' ?> Nhà sản xuất
</h5>

<form method="POST" enctype="multipart/form-data">

<input type="hidden"
name="nhasx_id"
value="<?= $editNhasx['id'] ?? '' ?>">

<input
name="name"
class="form-control mb-2"
placeholder="Tên nhà sản xuất"
value="<?= $editNhasx['name'] ?? '' ?>"
required>

<input
name="phone"
class="form-control mb-2"
placeholder="Số điện thoại"
value="<?= $editNhasx['phone'] ?? '' ?>">

<input
name="email"
class="form-control mb-2"
placeholder="Email"
value="<?= $editNhasx['email'] ?? '' ?>">

<input
name="address"
class="form-control mb-2"
placeholder="Địa chỉ"
value="<?= $editNhasx['address'] ?? '' ?>">

<input
name="website"
class="form-control mb-2"
placeholder="Website"
value="<?= $editNhasx['website'] ?? '' ?>">

<input
name="country"
class="form-control mb-2"
placeholder="Quốc gia"
value="<?= $editNhasx['country'] ?? '' ?>">

<input
type="file"
name="logo"
class="form-control mb-3">

<button
name="save_nhasx"
class="btn btn-primary w-100 btn-round">

<?= $editNhasx ? 'Cập nhật' : 'Thêm' ?>

</button>

</form>



</form>

</div>
</div>


<div class="col-md-8">

<div class="table-box">

<h5 class="fw-bold mb-3">Danh sách nhà sản xuất</h5>

<table class="table table-hover align-middle">

<thead class="table-light">
<tr>
<th>ID</th>
<th>Logo</th>
<th>Tên</th>
<th>SĐT</th>
<th>Email</th>
<th>Website</th>
<th>Quốc gia</th>
<th class="text-end">Thao tác</th>
</tr>
</thead>

<tbody>

<?php
$list=$conn->query("SELECT * FROM nhasx ORDER BY id ASC");
while($m=$list->fetch_assoc()):
?>

<tr>

<td><?= $m['id'] ?></td>

<td>
<?php if($m['logo']): ?>
<img src="images/<?= $m['logo'] ?>" width="40">
<?php endif; ?>
</td>

<td><?= $m['name'] ?></td>
<td><?= $m['phone'] ?></td>
<td><?= $m['email'] ?></td>

<td>
<a href="<?= $m['website'] ?>" target="_blank">
<?= $m['website'] ?>
</a>
</td>

<td><?= $m['country'] ?></td>

<td class="text-end">

<a
href="?view=nhasx&edit_nhasx=<?= $m['id'] ?>"
class="btn btn-sm btn-outline-primary border-0">
<i class="fas fa-edit"></i>
</a>

<a
href="?view=nhasx&delete_nhasx=<?= $m['id'] ?>"
class="btn btn-sm btn-outline-danger border-0"
onclick="return confirm('Xóa nhà sản xuất này?')">
<i class="fas fa-trash"></i>
</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

</div>

<?php endif; ?>

    <?php if($view=='newsletter'): ?>
        <div class="row">
            <div class="col-md-5">
                <div class="table-box">
                    <h5 class="fw-bold mb-3">Tạo Voucher</h5>
                    <?php if(($_GET['status'] ?? '') === 'invalid_expiry'): ?>
                        <script>alert('Ngày hết hạn không hợp lệ! Vui lòng chọn hôm nay hoặc một ngày trong tương lai.');</script>
                    <?php elseif(($_GET['status'] ?? '') === 'coupon_created'): ?>
                        <script>alert('Đã tạo voucher thành công!');</script>
                    <?php elseif(($_GET['status'] ?? '') === 'coupon_updated'): ?>
                        <script>alert('Đã cập nhật voucher thành công!');</script>
                    <?php elseif(($_GET['status'] ?? '') === 'coupon_error'): ?>
                        <script>alert('Lưu voucher thất bại! Vui lòng kiểm tra dữ liệu và thử lại.');</script>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="coupon_id" value="<?= $editCoupon['id'] ?? '' ?>">
                        <input name="code" class="form-control mb-2" placeholder="Mã giảm giá (VD: GIAM20)" value="<?= $editCoupon['code'] ?? '' ?>" required>
                        <input name="discount" type="number" class="form-control mb-2" placeholder="Phần trăm giảm (%)" value="<?= $editCoupon['discount_percent'] ?? '' ?>">
                        <input name="expiry_date" type="date" min="<?= date('Y-m-d') ?>" class="form-control mb-3" value="<?= htmlspecialchars($editCoupon['expiry_date'] ?? '') ?>" required>
                        <button name="save_coupon" class="btn btn-primary w-100 btn-round">PHÁT HÀNH MÃ</button>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="table-box">
                    <h5 class="fw-bold mb-3">Danh sách Email Newsletter</h5>
                    
                    <table class="table table-sm" id="main-table">
                        <thead class="table-light"><tr><th>Email</th><th>Ngày đăng ký</th></tr></thead>
                        <tbody>
                            <?php $news = $conn->query("SELECT * FROM newsletter ORDER BY id DESC");
                            while($n = $news->fetch_assoc()): ?>
                            <tr><td><?= $n['email'] ?></td><td><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td></tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <h5 class="fw-bold mt-4 mb-3">Danh sách Voucher đã phát hành</h5>

<table class="table table-hover align-middle">
<thead class="table-light">
<tr>
<th>Mã</th>
<th>Giảm (%)</th>
<th>Hạn dùng</th>
<th>Sửa</th>
</tr>
</thead>

<tbody>

<?php
$coupons = $conn->query("SELECT * FROM coupons ORDER BY id DESC");

while($c = $coupons->fetch_assoc()):
?>

<tr>

<td class="fw-bold text-primary">
<?= $c['code'] ?>
</td>

<td>
<?= $c['discount_percent'] ?>%
</td>

<td>
<?= date('d/m/Y',strtotime($c['expiry_date'])) ?>
</td>

<td>

<a href="?view=newsletter&edit_coupon_id=<?= $c['id'] ?>"
class="btn btn-sm btn-outline-primary border-0">
<i class="fas fa-edit"></i>
</a>

<a href="?view=newsletter&delete_coupon=<?= $c['id'] ?>"
class="btn btn-sm btn-outline-danger border-0"
onclick="return confirm('Xóa mã này?')">
<i class="fas fa-trash"></i>
</a>

<td>



</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
    // 1. Logic danh mục sản phẩm con động
    const subData = <?= $sub_categories_json ?>;
    const currentSub = "<?= $editProduct['subcategory'] ?? '' ?>";
    function loadSub() {
        const mainCat = document.getElementById('main-cat').value;
        const subCatSelect = document.getElementById('sub-cat');
        subCatSelect.innerHTML = '<option value="">-- Chọn loại phụ --</option>';
        if(subData[mainCat]) {
            Object.keys(subData[mainCat]).forEach(key => {
                const opt = document.createElement('option');
                opt.value = key; opt.text = subData[mainCat][key];
                if(key === currentSub) opt.selected = true;
                subCatSelect.appendChild(opt);
            });
        }
    }
    window.onload = loadSub;

    // 2. Xuất dữ liệu ra PDF (Sử dụng thư viện jsPDF)
    function exportTableToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFont("helvetica", "normal");
        doc.text("VIET DUC PHARMACY - BAO CAO <?= strtoupper($view) ?>", 14, 15);
        doc.autoTable({ 
            html: '#main-table', 
            startY: 20,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [15, 23, 42] }
        });
        doc.save('Bao_cao_<?= $view ?>_' + new Date().toLocaleDateString() + '.pdf');
    }

   // 3. Khởi tạo Biểu đồ Doanh thu
const canvas = document.getElementById("chart");

if (canvas) {

let chart;
const ctx = canvas.getContext("2d");

function loadChart() {

fetch("get_chart_data.php?t=" + Date.now())
.then(res => res.json())
.then(res => {

if (!chart) {

chart = new Chart(ctx, {
type: 'line',

data: {
labels: res.labels,
datasets: [
{
label: 'Doanh thu',
data: res.data,
borderColor: '#2563eb',
backgroundColor: 'rgba(37,99,235,0.2)',
borderWidth: 3,
tension: 0.4,
fill: true
},
{
label: 'Số đơn',
data: res.orders,
borderColor: '#16a34a',
backgroundColor: 'rgba(22,163,74,0.2)',
borderWidth: 3,
tension: 0.4,
fill: true
}
]
},

options: {
responsive: true,
plugins: {
legend: { display: true }
},
scales: {
y: {
beginAtZero: true
}
}
}

});

} else {

chart.data.labels = res.labels;
chart.data.datasets[0].data = res.data;
chart.data.datasets[1].data = res.orders;

chart.update();

}

});

}

loadChart();

/* tự cập nhật mỗi 3 giây */
setInterval(loadChart, 3000);

}
</script>

<?php if ($view === 'chat'): ?>
<script>
const csrf = <?= json_encode($chatCsrf) ?>;
const listEl=document.getElementById('conversationList'), msgsEl=document.getElementById('messages');
let activeId=0, lastId=0, activeMode='bot', listCache=[], listBusy=false, messagesBusy=false;
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function api(action,params={},method='GET'){let url='admin_chat_api.php?action='+encodeURIComponent(action);if(method==='GET')url+='&'+new URLSearchParams(params);let opt={method,headers:{'Content-Type':'application/json'}};if(method!=='GET')opt.body=JSON.stringify({...params,csrf});const r=await fetch(url,opt);const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Lỗi kết nối');return d;}
function modeText(m){return m==='human'?'Nhân viên đang trả lời':m==='closed'?'Đã đóng':'AI đang trả lời';}
async function loadList(){if(listBusy||document.hidden)return;listBusy=true;try{const d=await api('list');listCache=d.conversations;listEl.innerHTML='';if(!listCache.length){listEl.innerHTML='<div class="empty">Chưa có hội thoại nào. Khi khách nhắn tin, hội thoại sẽ hiện ở đây.</div>';return;}for(const c of listCache){const b=document.createElement('button');b.className='conv-item'+(Number(c.id)===activeId?' active':'');b.innerHTML='<div class="d-flex justify-content-between gap-2"><strong>Hội thoại #'+esc(c.id)+'</strong><span class="badge '+(c.status==='human'?'text-bg-success':c.status==='closed'?'text-bg-secondary':'text-bg-primary')+'">'+esc(c.status==='human'?'Nhân viên':c.status==='closed'?'Đã đóng':'AI')+'</span></div><div class="conv-last">'+esc(c.last_message||'Chưa có tin nhắn')+'</div><div class="d-flex justify-content-between mt-1"><small class="text-secondary">'+esc(c.last_message_at||'')+'</small>'+(Number(c.unread)>0?'<span class="badge rounded-pill text-bg-danger unread">'+esc(c.unread)+' mới</span>':'')+'</div>';b.onclick=()=>selectConversation(Number(c.id));listEl.appendChild(b);}}catch(e){document.getElementById('statusMsg').textContent=e.message;}finally{listBusy=false;}}
function addMessage(m){if(Number(m.id)<=lastId)return;const b=document.createElement('div');b.className='bubble '+(m.sender==='visitor'?'visitor':m.sender==='staff'?'staff':m.sender==='system'?'system':'ai');b.innerHTML=esc(m.message)+'<small>'+esc(m.sender==='visitor'?'Khách hàng':m.sender==='staff'?'Nhân viên':m.sender==='system'?'Hệ thống':'AI')+' · '+esc(m.created_at||'')+'</small>';msgsEl.appendChild(b);lastId=Math.max(lastId,Number(m.id));msgsEl.scrollTop=msgsEl.scrollHeight;}
async function selectConversation(id){activeId=id;lastId=0;msgsEl.innerHTML='';document.getElementById('activeTitle').textContent='Hội thoại #'+id;['takeOver','returnBot','closeChat','replyInput','sendReply'].forEach(x=>document.getElementById(x).disabled=false);await pollMessages(true);await loadList();}
async function pollMessages(initial=false){if(!activeId||messagesBusy||document.hidden)return;messagesBusy=true;try{const d=await api('messages',{conversation_id:activeId,after:initial?0:lastId});if(initial){msgsEl.innerHTML='';lastId=0;}for(const m of d.messages)addMessage(m);activeMode=d.mode;document.getElementById('activeMeta').textContent=modeText(activeMode);document.getElementById('takeOver').disabled=activeMode==='human';document.getElementById('returnBot').disabled=activeMode==='bot';}catch(e){document.getElementById('statusMsg').textContent=e.message;}finally{messagesBusy=false;}}
document.getElementById('replyForm').addEventListener('submit',async e=>{e.preventDefault();if(!activeId)return;const input=document.getElementById('replyInput'),message=input.value.trim();if(!message)return;document.getElementById('sendReply').disabled=true;try{await api('reply',{conversation_id:activeId,message},'POST');input.value='';await pollMessages();await loadList();}catch(e){alert(e.message)}finally{document.getElementById('sendReply').disabled=false;input.focus();}});
async function changeMode(mode){if(!activeId)return;try{await api('mode',{conversation_id:activeId,mode},'POST');await pollMessages();await loadList();}catch(e){alert(e.message)}}
document.getElementById('takeOver').onclick=()=>changeMode('human');document.getElementById('returnBot').onclick=()=>changeMode('bot');document.getElementById('closeChat').onclick=()=>changeMode('closed');document.getElementById('refreshList').onclick=loadList;
loadList();setInterval(loadList,8000);setInterval(()=>pollMessages(false),3500);
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>



<?php
// Dùng lại widget chatbot AI hiện có của website, giữ nguyên API và cấu hình.
if (is_file(__DIR__ . '/chatbot_widget.php')) {
    include_once __DIR__ . '/chatbot_widget.php';
}
?>

</body>
</html>