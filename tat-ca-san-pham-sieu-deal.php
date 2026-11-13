<?php
// 1. Kết nối Database
require_once 'config/db.php';

// 2. Lấy TẤT CẢ sản phẩm Siêu Deal
$sql_all_deal = "SELECT * FROM products WHERE is_super_deal = 1 ORDER BY id DESC";
$result_all_deal = $conn->query($sql_all_deal);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siêu Deal Ngọt Ngào - Viet Duc Pharmacy</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sweet-pink: #ff85a2;
            --hot-pink: #f72585;
            --soft-pink: #fde2e4;
            --dark-pink: #a4133c;
            --bg-page: #fff5f6;
        }

        body {
            background-color: var(--bg-page);
            font-family: 'Inter', sans-serif;
            color: #444;
        }

        /* Header Màu Hồng lung linh */
        .page-header {
            background: linear-gradient(135deg, #f72585 0%, #ff85a2 100%);
            padding: 50px 0;
            text-align: center;
            color: white;
            margin-bottom: 40px;
            border-bottom: 5px solid #ffb3c1;
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.2);
        }

        .breadcrumb-item a { color: rgba(255,255,255,0.8); }
        .breadcrumb-item.active { color: #fff; }

        /* Card Sản phẩm */
        .card-product {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid var(--soft-pink);
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .card-product:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(247, 37, 133, 0.15) !important;
            border-color: var(--sweet-pink);
        }

        /* Badge Giảm giá */
        .discount-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--hot-pink);
            color: white;
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 800;
            z-index: 2;
            box-shadow: 0 4px 8px rgba(247, 37, 133, 0.3);
        }

        /* Hình ảnh */
        .img-wrapper {
            background: #fff;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border-radius: 12px;
        }

        .img-wrapper img {
            max-width: 95%;
            max-height: 180px;
            object-fit: contain;
            transition: 0.5s ease;
        }

        .card-product:hover .img-wrapper img {
            transform: scale(1.1);
        }

        /* Tên sản phẩm */
        .card-product h6 {
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.4;
            height: 2.8rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 10px;
            color: #333;
        }

        /* Giá cả */
        .price-new {
            color: var(--hot-pink);
            font-weight: 800;
            font-size: 1.3rem;
        }

        .price-old {
            text-decoration: line-through;
            color: #adb5bd;
            font-size: 0.9rem;
            margin-left: 5px;
        }

        /* Thanh Tiến độ Đã bán */
        .flash-sale-progress {
            height: 18px !important;
            background-color: var(--soft-pink) !important;
            border-radius: 20px !important;
            position: relative;
            margin-top: 15px;
            overflow: hidden;
            border: 1px solid #ffccd5;
        }

        .flash-sale-progress .progress-bar {
            background: linear-gradient(90deg, #ff85a2, #f72585);
        }

        .flash-sale-text {
            position: absolute;
            width: 100%;
            left: 0;
            top: 0;
            font-size: 10px;
            font-weight: 800;
            color: #fff;
            line-height: 18px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        /* Nút mua hàng */
        .btn-buy {
            background: linear-gradient(90deg, #f72585, #ff4d6d);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 50px;
            font-weight: 700;
            transition: 0.3s;
            margin-top: 15px;
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.2);
        }

        .btn-buy:hover {
            background: var(--dark-pink);
            color: #fff;
            transform: scale(1.03);
            box-shadow: 0 6px 15px rgba(247, 37, 133, 0.4);
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Trang chủ</a></li>
                <li class="breadcrumb-item active" aria-current="page">Siêu Deal Ngọt Ngào</li>
            </ol>
        </nav>
        <h1 class="fw-800"><i class="fa-solid fa-heart me-2"></i> SIÊU DEAL NGỌT NGÀO <i class="fa-solid fa-heart ms-2"></i></h1>
        <p class="opacity-90">Tổng cộng <?= $result_all_deal->num_rows ?> ưu đãi cực lớn đang chờ bạn</p>
    </div>
</div>

<div class="container pb-5">
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
        <?php if($result_all_deal->num_rows > 0): ?>
            <?php while($row = $result_all_deal->fetch_assoc()): 
                // Tính % giảm giá
                $percent = 0;
                if($row['price_old'] > $row['price_new']) {
                    $percent = round((($row['price_old'] - $row['price_new']) / $row['price_old']) * 100);
                }
                // Giả lập số lượng đã bán (hoặc lấy từ DB nếu có)
                $sold_percent = rand(30, 90);
            ?>
            <div class="col">
                <div class="card-product shadow-sm">
                    <?php if($percent > 0): ?>
                        <div class="discount-badge">-<?= $percent ?>%</div>
                    <?php endif; ?>

                    <div class="img-wrapper">
                       <img src="images/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                    </div>

                    <h6 class="text-center"><?= htmlspecialchars($row['name']) ?></h6>

                    <div class="mt-auto text-center">
                        <div class="d-flex justify-content-center align-items-baseline">
                            <span class="price-new"><?= number_format($row['price_new']) ?>đ</span>
                            <?php if($row['price_old'] > $row['price_new']): ?>
                                <span class="price-old"><?= number_format($row['price_old']) ?>đ</span>
                            <?php endif; ?>
                        </div>

                        <div class="progress flash-sale-progress">
                            <div class="progress-bar" style="width: <?= $sold_percent ?>%"></div>
                            <div class="flash-sale-text">Đã bán <?= $sold_percent ?>%</div>
                        </div>

                        <a href="chitietsp.php?id=<?= $row['id'] ?>" class="btn btn-buy w-100">
    <i class="fa-solid fa-eye me-2"></i>CHI TIẾT 
</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="bg-white p-5 rounded-4 shadow-sm border border-pink">
                    <i class="fa-solid fa-face-sad-tear text-pink mb-3" style="font-size: 50px; color: var(--hot-pink)"></i>
                    <p class="text-muted fs-5">Chương trình Siêu Deal đã kết thúc. Hẹn bạn lần sau nhé!</p>
                    <a href="index.php" class="btn btn-buy px-5">QUAY LẠI TRANG CHỦ</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>