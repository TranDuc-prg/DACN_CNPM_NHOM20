<?php
session_start();
require_once 'config/db.php';

/* LƯU TẠM THÔNG TIN KHÁCH HÀNG */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['temp_user'] = [
        'fullname' => $_POST['fullname'] ?? ($_SESSION['temp_user']['fullname'] ?? ''),
        'phone' => $_POST['phone'] ?? ($_SESSION['temp_user']['phone'] ?? ''),
        'address' => $_POST['address'] ?? ($_SESSION['temp_user']['address'] ?? ''),
        'payment_method' => $_POST['payment_method'] ?? ($_SESSION['temp_user']['payment_method'] ?? 'COD')
    ];
}

// LẤY DANH SÁCH KHUYẾN MÃI
$coupons = [];
$res_coupon = $conn->query("SELECT code, discount_percent, expiry_date 
                            FROM coupons 
                            WHERE expiry_date >= CURDATE()
                            ORDER BY discount_percent DESC");

if ($res_coupon) {
    while ($row = $res_coupon->fetch_assoc()) {
        $coupons[] = $row;
    }
}

// 1. CẬP NHẬT SỐ LƯỢNG SẢN PHẨM TRONG GIỎ (Tăng/Giảm/Xóa)
if (isset($_POST['update_qty'])) {
    $id = (int)$_POST['product_id'];
    $action = $_POST['action']; // 'plus' hoặc 'minus'
    
    if (isset($_SESSION['cart'][$id])) {
        if ($action === 'plus') {
            $_SESSION['cart'][$id]++;
        } elseif ($action === 'minus') {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) unset($_SESSION['cart'][$id]);
        }
    }
}

// 2. XỬ LÝ AJAX THÊM NHANH
if (isset($_POST['add_to_cart_id'])) {
    $id = (int)$_POST['add_to_cart_id'];
    if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
    $_SESSION['cart'][$id] = isset($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id] + 1 : 1;
    echo array_sum($_SESSION['cart']);
    exit;
}

// 3. CHUẨN BỊ DỮ LIỆU GIỎ HÀNG
$cart_items = [];
$total_bill = 0;
if (!empty($_SESSION['cart'])) {
    $cart_ids = array_filter(array_keys($_SESSION['cart']));
    if (!empty($cart_ids)) {
        $ids_str = implode(',', array_map('intval', $cart_ids));
        $res = $conn->query("SELECT id, name, price_new, image FROM products WHERE id IN ($ids_str)");
        while ($r = $res->fetch_assoc()) {
            $qty = $_SESSION['cart'][$r['id']];
            $r['qty'] = $qty;
            $r['subtotal'] = $r['price_new'] * $qty;
            $total_bill += $r['subtotal'];
            $cart_items[] = $r;
        }
    }
}

// BỎ ÁP DỤNG MÃ GIẢM GIÁ
if (isset($_POST['remove_coupon'])) {
    unset($_SESSION['discount_percent']);
    unset($_SESSION['applied_coupon']);
    $coupon_code = "";
}

// 4. XỬ LÝ MÃ GIẢM GIÁ
$discount = 0;
$coupon_code = $_POST['coupon_code'] ?? ($_SESSION['applied_coupon'] ?? '');
if (isset($_POST['apply_coupon']) && !empty($coupon_code)) {
    $stmt_cp = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND expiry_date >= CURDATE() LIMIT 1");
    $stmt_cp->bind_param("s", $coupon_code);
    $stmt_cp->execute();
    $check = $stmt_cp->get_result();

    if ($check && $check->num_rows > 0) {
        $coupon = $check->fetch_assoc();
        $_SESSION['discount_percent'] = $coupon['discount_percent'];
        $_SESSION['applied_coupon'] = $coupon_code;
    } else {
        $coupon_error = "Mã không hợp lệ hoặc đã hết hạn";
        unset($_SESSION['discount_percent'], $_SESSION['applied_coupon']);
    }
}
if (isset($_SESSION['discount_percent'])) {
    $discount = ($total_bill * $_SESSION['discount_percent']) / 100;
}
$after_discount = $total_bill - $discount;

/* THUẾ VAT 10% */
$vat = $after_discount * 0.10;

/* TỔNG THANH TOÁN */
$final_total = $after_discount + $vat;

$products = "";
foreach($cart_items as $item){
    $products .= $item['name']." x".$item['qty'].", ";
}

// HÀM KIỂM TRA CỘT user_id (tương thích cả database đã/nếu chưa thêm cột)
function hasTableColumn(mysqli $conn, string $table, string $column): bool {
    $tableSafe = str_replace('`', '', $table);
    $columnSafe = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    return $result && $result->num_rows > 0;
}

// Lưu các dòng sản phẩm vào order_details.
// Mỗi dòng giỏ hàng phải có id sản phẩm, số lượng và giá bán tại thời điểm đặt.
function saveOrderDetails(mysqli $conn, int $orderId, array $items): void {
    $detailStmt = $conn->prepare(
        "INSERT INTO order_details (order_id, product_id, quantity, price)
         VALUES (?, ?, ?, ?)"
    );

    if (!$detailStmt) {
        throw new Exception("Không thể chuẩn bị lưu chi tiết đơn hàng: " . $conn->error);
    }

    foreach ($items as $item) {
        $productId = (int)($item['id'] ?? 0);
        $quantity  = (int)($item['qty'] ?? 0);
        $price     = (float)($item['price_new'] ?? 0);

        if ($productId <= 0 || $quantity <= 0) {
            throw new Exception("Sản phẩm trong giỏ hàng không hợp lệ.");
        }

        $detailStmt->bind_param("iiid", $orderId, $productId, $quantity, $price);

        if (!$detailStmt->execute()) {
            throw new Exception("Không thể lưu sản phẩm ID {$productId}: " . $detailStmt->error);
        }
    }

    $detailStmt->close();
}

// 5. XỬ LÝ ĐẶT HÀNG & THANH TOÁN VNPAY
$success_data = null;
$checkout_error = '';

if (isset($_POST['checkout_action']) && !empty($cart_items)) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $method   = $_POST['payment_method'] ?? 'COD';
    $currentUserId = null;
    foreach (['user_id', 'id_user', 'id'] as $sessionKey) {
        if (isset($_SESSION[$sessionKey]) && is_numeric($_SESSION[$sessionKey]) && (int)$_SESSION[$sessionKey] > 0) {
            $currentUserId = (int)$_SESSION[$sessionKey];
            break;
        }
    }
    if ($currentUserId === null && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        foreach (['id', 'user_id'] as $sessionKey) {
            if (!empty($_SESSION['user'][$sessionKey]) && is_numeric($_SESSION['user'][$sessionKey])) {
                $currentUserId = (int)$_SESSION['user'][$sessionKey];
                break;
            }
        }
    }

    if ($fullname === '' || $phone === '' || $address === '') {
        $checkout_error = "Vui lòng nhập đầy đủ họ tên, số điện thoại và địa chỉ nhận hàng.";
    } elseif ($method === 'VNPAY') {
        // Lưu đầy đủ từng sản phẩm vào session để vnpay_return.php ghi vào order_details
        $_SESSION['vnpay_order'] = [
            'fullname' => $fullname,
            'phone' => $phone,
            'address' => $address,
            'products' => $products,
            'final_total' => (float)$final_total,
            'user_id' => $currentUserId,
            'items' => array_map(static function ($item) {
                return [
                    'id' => (int)$item['id'],
                    'qty' => (int)$item['qty'],
                    'price_new' => (float)$item['price_new']
                ];
            }, $cart_items)
        ];

        // Cấu hình VNPay Sandbox hiện tại của dự án
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $vnp_TmnCode = "UVH9T83L";
        $vnp_HashSecret = "ECGVTGMNQJYBVTGMMPHVGLLVKNTMAJQO";
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = "http://localhost/VIET_DUC_PHARMACY_500_VISITS_OPTIMIZED/VIET_DUC_PHARMACY_CHAT_TRUC_TIEP/BANTHUOC_CHATBOT_AI/BANTHUOC_chot/vnpay_return.php";

        $vnp_TxnRef = time();
        // Lưu mã tham chiếu để đối chiếu chính xác khi VNPay trả về.
        $_SESSION['vnpay_order']['txn_ref'] = (string)$vnp_TxnRef;
        // Xóa mã đơn VNPay cũ để tránh dùng nhầm khi khách tạo giao dịch mới.
        unset($_SESSION['vnpay_saved_order_id']);
        $vnp_Amount = (int)round($final_total * 100);
        $vnp_OrderInfo = 'Thanh toan don hang Viet Duc Pharmacy - Ma GD: ' . $vnp_TxnRef;

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            "vnp_Locale" => "vn",
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => "billpayment",
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef
        ];

        ksort($inputData);
        $query = [];
        $hashParts = [];
        foreach ($inputData as $key => $value) {
            $query[] = urlencode($key) . "=" . urlencode((string)$value);
            $hashParts[] = urlencode($key) . "=" . urlencode((string)$value);
        }

        $vnp_Url .= "?" . implode("&", $query);
        $vnpSecureHash = hash_hmac('sha512', implode("&", $hashParts), $vnp_HashSecret);
        $vnp_Url .= '&vnp_SecureHash=' . $vnpSecureHash;

        header('Location: ' . $vnp_Url);
        exit;
    } else {
        // ĐẶT HÀNG COD / BANK / MOMO: lưu orders và order_details trong cùng transaction
        try {
            $conn->begin_transaction();

            $hasUserId = hasTableColumn($conn, 'orders', 'user_id');

            if ($hasUserId && $currentUserId !== null) {
                $stmt = $conn->prepare(
                    "INSERT INTO orders
                        (user_id, fullname, phone, address, products, total_amount, payment_method)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) {
                    throw new Exception("Không thể chuẩn bị tạo đơn hàng: " . $conn->error);
                }
                $stmt->bind_param(
                    "issssds",
                    $currentUserId,
                    $fullname,
                    $phone,
                    $address,
                    $products,
                    $final_total,
                    $method
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO orders
                        (fullname, phone, address, products, total_amount, payment_method)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                if (!$stmt) {
                    throw new Exception("Không thể chuẩn bị tạo đơn hàng: " . $conn->error);
                }
                $stmt->bind_param(
                    "ssssds",
                    $fullname,
                    $phone,
                    $address,
                    $products,
                    $final_total,
                    $method
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Không thể tạo đơn hàng: " . $stmt->error);
            }

            $orderId = (int)$conn->insert_id;
            $stmt->close();

            // Đây là phần bị thiếu trong file cũ: ghi từng sản phẩm vào order_details
            saveOrderDetails($conn, $orderId, $cart_items);

            $conn->commit();

            $_SESSION['customer_phone'] = $phone;
            $_SESSION['customer_fullname'] = $fullname;

            $success_data = [
                'id' => $orderId,
                'name' => $fullname,
                'total' => $final_total,
                'method' => $method
            ];

            unset(
                $_SESSION['cart'],
                $_SESSION['discount_percent'],
                $_SESSION['applied_coupon'],
                $_SESSION['temp_user']
            );
        } catch (Throwable $e) {
            $conn->rollback();
            $checkout_error = "Đặt hàng chưa thành công: " . $e->getMessage()
                . " Giỏ hàng của bạn vẫn được giữ nguyên, vui lòng thử lại.";
        }
    }
}
$old = $_SESSION['temp_user'] ?? [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Viet Duc Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/cart.css">
</head>
<body>

<header class="top-bar bg-primary text-white py-3">
<div class="container d-flex align-items-center justify-content-between">
<a href="index.php" class="text-white fw-bold fs-4 text-decoration-none">
<i class="fas fa-plus-square me-2"></i>VIET DUC PHARMACY
</a>
<div class="d-flex align-items-center gap-3">
<a href="track-order.php" class="text-white text-decoration-none fw-bold small"><i class="fas fa-search-location me-1"></i>Tra cứu đơn</a>
<a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-home me-1"></i> Trang chủ</a>
<a href="product.php" class="btn btn-outline-light btn-sm"><i class="fas fa-pills me-1"></i> Sản phẩm</a>
<a href="cart.php" class="btn btn-warning btn-sm position-relative">
<i class="fas fa-shopping-cart"></i> Giỏ hàng
<span class="position-absolute top-0 start-100 translate-middle badge bg-danger">
<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>
</span>
</a>
</div>
</div>
</header>

<div class="container my-5 pt-4">
    <?php if ($success_data): ?>
        <div class="text-center bg-white p-5 rounded-4 shadow-sm mx-auto" style="max-width: 600px;">
            <i class="fas fa-check-circle text-success mb-4" style="font-size: 5rem;"></i>
            <h2 class="fw-bold">Đặt hàng thành công!</h2>
            <p>Mã đơn: <b>#<?= $success_data['id'] ?></b></p>
            <h4 class="text-danger fw-bold my-4">TỔNG: <?= number_format($success_data['total']) ?> đ</h4>
            <a href="index.php" class="btn btn-primary px-5 py-2 rounded-pill">VỀ TRANG CHỦ</a>
            <a href="invoice.php?id=<?= $success_data['id'] ?>" class="btn btn-success px-5 py-2 rounded-pill ms-2" target="_blank">
                <i class="fas fa-file-invoice"></i> Xuất hóa đơn
            </a>
        </div>
    <?php else: ?>
        <?php if (!empty($checkout_error)): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($checkout_error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="POST" id="checkoutForm">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card-custom mb-4">
                        <h5 class="section-title">1. Thông tin người nhận</h5>
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($old['fullname'] ?? '') ?>" placeholder="Họ tên" required></div>
                            <div class="col-md-6"><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($old['phone'] ?? '') ?>" placeholder="Số điện thoại" required></div>
                            <div class="col-12"><textarea name="address" class="form-control" rows="3" placeholder="Địa chỉ chi tiết" required><?= htmlspecialchars($old['address'] ?? '') ?></textarea></div>
                        </div>
                    </div>

                    <div class="card-custom">
                        <h5 class="section-title">2. Phương thức thanh toán</h5>
                        <div class="payment-methods-list">

                            <label class="payment-item active" onclick="togglePayment('COD', this)">
                                <img src="https://cdn-icons-png.flaticon.com/512/6491/6491490.png" width="30" class="me-3">
                                <span class="flex-grow-1">Thanh toán khi nhận hàng (COD)</span>
                                <input type="radio" name="payment_method" value="COD" <?= (($old['payment_method'] ?? 'COD') == 'COD') ? 'checked' : '' ?> class="d-none">
                            </label>

                            <!-- TÍCH HỢP THANH TOÁN VNPAY -->
                            <label class="payment-item" onclick="togglePayment('VNPAY', this)">
                                <img src="https://tse4.mm.bing.net/th/id/OIP.zsHE4ELaw12_LsdfAuSZMQHaHa?r=0&pid=Api&h=220&P=0" width="30" class="me-3">
                                <span class="flex-grow-1">Cổng thanh toán trực tuyến VNPAY</span>
                                <input type="radio" name="payment_method" value="VNPAY" class="d-none">
                            </label>

                            <label class="payment-item" onclick="togglePayment('BANK', this)">
                                <img src="https://cdn-icons-png.flaticon.com/512/2830/2830284.png" width="30" class="me-3">
                                <span class="flex-grow-1">Chuyển khoản ngân hàng (Thủ công)</span>
                                <input type="radio" name="payment_method" value="BANK" class="d-none">
                            </label>

                            <label class="payment-item" onclick="togglePayment('MOMO', this)">
                                <img src="https://th.bing.com/th?q=V%c3%ad+Momo+Logo.png&w=120&h=120&c=1&rs=1&qlt=70&o=7&cb=1&dpr=1.4&pid=InlineBlock&rm=3&mkt=en-WW&cc=VN&setlang=vi&adlt=moderate&t=1&mw=247" width="30" class="me-3">
                                <span class="flex-grow-1">Ví MoMo</span>
                                <input type="radio" name="payment_method" value="MOMO" class="d-none">
                            </label>

                        </div>
                        
                        <div id="qr-verification-area" style="display:none;">
                            <h6 class="fw-bold text-primary">QUÉT MÃ THANH TOÁN</h6>
                            <img src="" id="qr-preview-img" class="qr-img-preview">
                            <div class="form-check mt-2 d-flex justify-content-center">
                                <input class="form-check-input me-2" type="checkbox" id="checkConfirmed">
                                <label class="form-check-label small fw-bold text-danger" for="checkConfirmed">Tôi cam kết đã chuyển khoản đúng số tiền</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card-custom sticky-top" style="top: 20px;">
                        <h5 class="fw-bold mb-4">Đơn hàng của bạn</h5>
                        <div class="order-summary mb-4">
                            <?php if(empty($cart_items)): ?>
                                <p class="text-center text-muted">Giỏ hàng trống</p>
                            <?php else: foreach($cart_items as $item): ?>
                                <div class="cart-item-sm">
                                    <img src="images/<?= htmlspecialchars($item['image']) ?>" alt="">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-truncate small" style="max-width: 150px;"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="text-primary fw-bold mb-1 small"><?= number_format($item['price_new']) ?>đ</div>
                                        <div class="qty-control">
                                            <button type="submit" name="update_qty" value="1" class="btn-qty" onclick="setUpdate('<?= $item['id'] ?>', 'minus')"><i class="fas fa-minus"></i></button>
                                            <span class="px-2 small fw-bold"><?= $item['qty'] ?></span>
                                            <button type="submit" name="update_qty" value="1" class="btn-qty" onclick="setUpdate('<?= $item['id'] ?>', 'plus')"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <div class="fw-bold text-end" style="min-width: 80px;"><?= number_format($item['subtotal']) ?>đ</div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>

                        <input type="hidden" name="product_id" id="update_p_id">
                        <input type="hidden" name="action" id="update_action">

                        <div class="mb-3">
                            <div class="input-group input-group-sm mt-2">
                                <input type="text" name="coupon_code" class="form-control" value="<?= htmlspecialchars($coupon_code) ?>" placeholder="Mã giảm giá">
                                <button type="submit" name="apply_coupon" class="btn btn-dark">Áp dụng</button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Tạm tính:</span>
                            <span><?= number_format($total_bill) ?>đ</span>
                        </div>

                        <?php if($discount > 0): ?>
                        <div class="d-flex justify-content-between text-success mb-2">
                            <span>Giảm giá:</span>
                            <b>-<?= number_format($discount) ?>đ</b>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Thuế VAT (10%):</span>
                            <span><?= number_format($vat) ?>đ</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span class="h5 fw-bold">TỔNG CỘNG:</span>
                            <span class="h4 text-danger fw-bold"><?= number_format($final_total) ?>đ</span>
                        </div>

                        <button type="submit" name="checkout_action" class="btn btn-primary w-100 py-3 fw-bold shadow" <?= empty($cart_items) ? 'disabled' : '' ?>>XÁC NHẬN ĐẶT HÀNG</button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
.coupon-floating {
    position: fixed;
    right: 20px;
    top: 190px;
    width: 255px;
    max-height: calc(100vh - 220px);
    overflow-y: auto;
    background: #fff;
    padding: 14px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    z-index: 999;
}
.coupon-box {
    border: 1px dashed #ddd;
    padding: 9px;
    border-radius: 8px;
    background: #fff7f7;
    margin-bottom: 8px;
    overflow-wrap: anywhere;
}
@media (max-width: 1400px) {
    .coupon-floating {
        position: static;
        width: 100%;
        max-width: 100%;
        max-height: none;
        margin: 0 auto 24px;
    }
}
</style>

<script>
function togglePayment(method, element) {
    document.querySelectorAll('.payment-item').forEach(item => item.classList.remove('active'));
    element.classList.add('active');
    element.querySelector('input').checked = true;

    const qrArea = document.getElementById('qr-verification-area');
    const qrImg = document.getElementById('qr-preview-img');
    const checkConfirm = document.getElementById('checkConfirmed');
    const qrTitle = qrArea.querySelector('h6');

    if(method === "COD" || method === "VNPAY"){
        qrArea.style.display = "none";
        checkConfirm.required = false;
    } else {
        qrArea.style.display = "block";
        checkConfirm.required = true;

        // CẬP NHẬT THÔNG TIN TÀI KHOẢN BIDV CỦA BẠN Ở ĐÂY
        const BANK_ID = "BIDV";
        const ACCOUNT_NO = "2601643412";
        const ACCOUNT_NAME = "TRAN VIET DUC";
        const AMOUNT = "<?= $final_total ?>";
        const DESC = "DH_" + Date.now();
        let path = "";

        switch(method){
            case "BANK":
            case "CARD":
                // Tạo mã QR VietQR tự động điền số tiền và nội dung chuyển khoản
                path = `https://img.vietqr.io/image/${BANK_ID}-${ACCOUNT_NO}-compact.png?amount=${AMOUNT}&addInfo=${DESC}&accountName=${ACCOUNT_NAME}`;
                qrTitle.innerHTML = "🏦 Chuyển khoản BIDV (Quét mã QR tự động)";
                break;
            case "MOMO":
                path = "images/momo.qr.png";
                qrTitle.innerHTML = "📱 Ví điện tử MoMo";
                break;
        }
        qrImg.src = path;
    }
}

function setUpdate(productId, action) {
    document.getElementById('update_p_id').value = productId;
    document.getElementById('update_action').value = action;
    document.getElementById('checkoutForm').submit();
}

function selectCoupon(code){
    const form = document.getElementById("checkoutForm");
    const input = document.querySelector('input[name="coupon_code"]');
    input.value = code;

    const apply = document.createElement("input");
    apply.type = "hidden";
    apply.name = "apply_coupon";
    apply.value = "1";

    form.appendChild(apply);
    form.submit();
}

function removeCoupon(){
    const form = document.getElementById("checkoutForm");
    const input = document.createElement("input");
    input.type = "hidden";
    input.name = "remove_coupon";
    input.value = "1";

    form.appendChild(input);
    form.submit();
}
</script>

<div class="coupon-floating">
    <h6 class="fw-bold mb-2">🎁 Khuyến mãi đang có</h6>
    <?php if(!empty($coupons)): ?>
    <div class="coupon-list">
        <?php foreach($coupons as $cp): ?>
        <div class="coupon-box d-flex justify-content-between align-items-center mb-2">
            <div>
                <b class="text-danger"><?= $cp['discount_percent'] ?>%</b> - Mã: <b><?= $cp['code'] ?></b><br>
                <small>HSD: <?= $cp['expiry_date'] ?></small>
            </div>
            <div class="d-flex gap-1">
                <?php if(isset($_SESSION['applied_coupon']) && $_SESSION['applied_coupon'] == $cp['code']): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" style="font-size:12px;padding:3px 8px;" onclick="removeCoupon()">Bỏ</button>
                <?php else: ?>
                    <button type="button" class="btn btn-sm btn-success" style="font-size:12px;padding:3px 8px;" onclick="selectCoupon('<?= $cp['code'] ?>')">Chọn</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-muted small">Không có khuyến mãi</div>
    <?php endif; ?>
</div>

<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>