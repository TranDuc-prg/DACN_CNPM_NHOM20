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
    <title>Chính Sách Vận Chuyển - Viet Duc Pharmacy</title>
    
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
            <i class="fas fa-shipping-fast me-2"></i> Chính Sách Vận Chuyển & Giao Hàng
        </h2>
        
        <p class="text-secondary lh-lg mb-4">
            <strong>VIET DUC PHARMACY</strong> cam kết mang đến dịch vụ vận chuyển nhanh chóng, an toàn và bảo mật tuyệt đối cho các sản phẩm dược phẩm, thực phẩm chức năng và thiết bị y tế đến tận tay khách hàng.
        </p>

        <hr class="my-4">

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-clock"></i></div>
            <div>
                <h5 class="text-dark">1. Thời gian giao hàng dự kiến</h5>
                <p class="text-secondary">Nội thành TP.HCM và Hà Nội giao nhanh trong vòng từ 2 - 4 giờ (có hỗ trợ hỏa tốc). Các tỉnh thành khác thời gian từ 2 - 4 ngày làm việc tùy khu vực.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-tags"></i></div>
            <div>
                <h5 class="text-dark">2. Biểu phí vận chuyển</h5>
                <p class="text-secondary">Miễn phí vận chuyển áp dụng theo các chương trình ưu đãi hiện hành của hệ thống hoặc đơn hàng đạt giá trị tối thiểu theo chính sách từng thời kỳ. Phí chuẩn được tính tự động khi thanh toán.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-box-open"></i></div>
            <div>
                <h5 class="text-dark">3. Quy định kiểm tra hàng</h5>
                <p class="text-secondary">Quý khách được quyền kiểm tra tình trạng bên ngoài gói hàng (số lượng, chủng loại, niêm phong) trước khi ký nhận thanh toán. Liên hệ ngay tổng đài <strong>1800 6789</strong> nếu có phát sinh vấn đề.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-shield-alt"></i></div>
            <div>
                <h5 class="text-dark">4. Tiêu chuẩn bảo quản thuốc</h5>
                <p class="text-secondary">Tất cả sản phẩm thuốc và dược mỹ phẩm đều được đóng gói theo tiêu chuẩn GPP, đảm bảo điều kiện nhiệt độ và ánh sáng tối ưu trong suốt quá trình vận chuyển.</p>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>