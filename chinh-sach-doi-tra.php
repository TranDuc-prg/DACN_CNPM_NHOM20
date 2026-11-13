<?php
session_start();
require_once 'config/db.php';

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính Sách Đổi Trả - Viet Duc Pharmacy</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Thiết lập font Times New Roman và cỡ chữ lớn toàn trang */
        body {
            font-family: 'Times New Roman', Times, serif !important;
            font-size: 20px !important;
            line-height: 1.6;
            background-color: #F8FAFC;
            color: #1E293B;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Times New Roman', Times, serif !important;
            font-weight: bold !important;
        }

        h2 { font-size: 32px !important; }
        h5 { font-size: 24px !important; }

        .container-full { width: 100%; padding: 0 5%; }
        
        .top-bar { 
            background: linear-gradient(90deg, #0838a1, #1250dc); 
            padding: 15px 0; 
            border-bottom: 3px solid #ffb800; 
        }
        
        .policy-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid #edf2f7;
            margin-top: 40px;
            margin-bottom: 60px;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            color: #1250dc;
            font-family: 'Times New Roman', Times, serif !important;
            font-weight: bold;
            font-size: 20px;
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 6px 18px rgba(18, 80, 220, 0.15);
            border: 2px solid #1250dc;
            transition: 0.3s;
        }
        .btn-home:hover {
            background: #1250dc;
            color: #ffffff;
            transform: translateY(-2px);
        }

        .policy-step {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            align-items: flex-start;
        }
        .step-icon {
            width: 60px;
            height: 60px;
            background: #E3F2FD;
            color: #1250dc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }

        p, ul li {
            font-size: 20px !important;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="top-bar">
    <div class="container-full d-flex align-items-center justify-content-between">
        <a href="index.php" class="text-white fw-bold fs-3 text-decoration-none d-flex align-items-center" style="font-size: 28px !important;">
            <i class="fas fa-plus-square me-2"></i>VIET DUC <span class="fw-light ms-1" style="color: #A5F3FC;">PHARMACY</span>
        </a>
        <div class="d-flex align-items-center gap-4">
            <a href="cart.php" class="position-relative text-white text-decoration-none">
                <i class="fas fa-shopping-basket fs-4"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $cart_count ?></span>
            </a>
            <a href="login.php" class="btn btn-outline-light btn-sm fw-bold px-4 rounded-pill" style="font-size: 18px !important; font-family: 'Times New Roman', Times, serif;">ĐĂNG NHẬP</a>
        </div>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="container-full">
    <div class="mt-4">
        <a href="index.php" class="btn-home">
            <i class="fas fa-home me-2"></i> Trang chủ
        </a>
    </div>

    <div class="policy-card">
        <h2 class="text-primary mb-4">
            <i class="fas fa-undo-alt me-2"></i> Chính Sách Đổi Trả Sản Phẩm
        </h2>
        
        <p class="text-secondary lh-lg mb-4">
            <strong>VIET DUC PHARMACY</strong> luôn đặt uy tín và sức khỏe của khách hàng lên hàng đầu. Chúng tôi hỗ trợ chính sách đổi trả minh bạch và linh hoạt nhằm đảm bảo quyền lợi tối đa cho quý khách khi mua sắm tại hệ thống.
        </p>

        <hr class="my-4">

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-calendar-check"></i></div>
            <div>
                <h5 class="text-dark">1. Thời gian áp dụng đổi trả</h5>
                <p class="text-secondary">Quý khách có thể yêu cầu đổi trả sản phẩm trong vòng <strong>30 ngày</strong> kể từ ngày mua hàng (hoặc ngày nhận hàng thành công qua đơn vị vận chuyển).</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <h5 class="text-dark">2. Điều kiện được đổi trả</h5>
                <p class="text-secondary">Sản phẩm còn nguyên tem mác, bao bì gốc, chưa có dấu hiệu bóc mở hay sử dụng sai hướng dẫn. Hóa đơn mua hàng hợp lệ kèm theo (nếu có). Áp dụng đổi trả miễn phí đối với lỗi từ nhà sản xuất hoặc sai sót về chủng loại.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-ban"></i></div>
            <div>
                <h5 class="text-dark">3. Trường hợp không hỗ trợ đổi trả</h5>
                <p class="text-secondary">Không áp dụng đổi trả đối với các sản phẩm thuốc kê đơn đã qua sử dụng, sản phẩm giảm giá/khuyến mại đặc biệt (trừ trường hợp lỗi kỹ thuật từ nhà sản xuất), hoặc sản phẩm bị hư hỏng do lỗi bảo quản từ phía khách hàng.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-headset"></i></div>
            <div>
                <h5 class="text-dark">4. Quy trình & Hỗ trợ</h5>
                <p class="text-secondary">Để được hướng dẫn chi tiết thủ tục đổi trả, quý khách vui lòng mang sản phẩm trực tiếp đến hệ thống nhà thuốc hoặc liên hệ tổng đài chăm sóc khách hàng <strong>1800 6789</strong> để được hỗ trợ nhanh chóng.</p>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>