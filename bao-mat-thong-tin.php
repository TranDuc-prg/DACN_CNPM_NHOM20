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
    <title>Bảo Mật Thông Tin - Viet Duc Pharmacy</title>
    
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
            <i class="fas fa-user-shield me-2"></i> Chính Sách Bảo Mật Thông Tin
        </h2>
        
        <p class="text-secondary lh-lg mb-4">
            <strong>VIET DUC PHARMACY</strong> hiểu rằng quyền riêng tư và bảo mật thông tin cá nhân là vô cùng quan trọng đối với khách hàng. Chúng tôi cam kết bảo vệ tuyệt đối mọi dữ liệu mà quý khách hàng đã tin tưởng cung cấp khi sử dụng dịch vụ của hệ thống.
        </p>

        <hr class="my-4">

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-database"></i></div>
            <div>
                <h5 class="text-dark">1. Mục đích thu thập thông tin</h5>
                <p class="text-secondary">Chúng tôi thu thập thông tin cá nhân (họ tên, số điện thoại, địa chỉ giao hàng, email) nhằm phục vụ cho việc xử lý đơn hàng, chăm sóc khách hàng, tư vấn dược phẩm và nâng cao chất lượng dịch vụ.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-lock"></i></div>
            <div>
                <h5 class="text-dark">2. Phạm vi sử dụng và bảo mật</h5>
                <p class="text-secondary">Thông tin của quý khách được bảo mật nghiêm ngặt bằng các giải pháp công nghệ hiện đại. Chúng tôi cam kết không mua bán, trao đổi hoặc tiết lộ thông tin cá nhân của khách hàng cho bên thứ ba trái với chính sách này.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-share-alt"></i></div>
            <div>
                <h5 class="text-dark">3. Chia sẻ thông tin trong trường hợp cần thiết</h5>
                <p class="text-secondary">Thông tin chỉ được chia sẻ cho các đơn vị vận chuyển đối tác nhằm mục đích giao hàng đến đúng địa chỉ của quý khách, hoặc khi có yêu cầu hợp pháp từ các cơ quan nhà nước có thẩm quyền theo quy định pháp luật.</p>
            </div>
        </div>

        <div class="policy-step">
            <div class="step-icon"><i class="fas fa-user-cog"></i></div>
            <div>
                <h5 class="text-dark">4. Quyền lợi và chỉnh sửa thông tin</h5>
                <p class="text-secondary">Quý khách hàng có toàn quyền yêu cầu kiểm tra, cập nhật, chỉnh sửa hoặc hủy bỏ thông tin cá nhân của mình bằng cách liên hệ trực tiếp với tổng đài chăm sóc khách hàng <strong>1800 6789</strong>.</p>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>