<?php
// 1. Kết nối Database
require_once 'config/db.php';

// 2. Lấy TẤT CẢ sản phẩm Best Seller (is_featured = 1)
$sql_all_best = "SELECT * FROM products WHERE is_featured = 1 ORDER BY id DESC";
$result_all_best = $conn->query($sql_all_best);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản Phẩm Bán Chạy - Viet Duc Pharmacy</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sweet-pink: #ff85a2;
            --hot-pink: #f72585;
            --soft-pink: #fde2e4;
            --bg-page: #fff5f6;
        }

        body {
            background-color: var(--bg-page);
            font-family: 'Inter', sans-serif;
            color: #444;
        }

        .best-seller-header {
            background: linear-gradient(135deg, #f72585 0%, #ff85a2 100%);
            padding: 50px 0;
            text-align: center;
            color: white;
            margin-bottom: 40px;
            border-bottom: 5px solid #ffb3c1;
            box-shadow: 0 4px 15px rgba(247, 37, 133, 0.2);
        }

        .breadcrumb-item a { color: rgba(255,255,255,0.8); text-decoration: none; }
        .breadcrumb-item.active { color: #fff; }

        .container-full { padding: 0 5%; }

        .card-product {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid var(--soft-pink);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .card-product:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(247, 37, 133, 0.15) !important;
            border-color: var(--sweet-pink);
        }

        .img-wrapper {
            background: #fff;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
        }

        .img-wrapper img {
            max-width: 95%;
            max-height: 180px;
            object-fit: contain;
            transition: 0.5s ease;
        }

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

        .price-new {
            color: var(--hot-pink);
            font-weight: 800;
            font-size: 1.3rem;
        }

        /* Nút Chi tiết chính */
        .btn-detail {
            background: linear-gradient(90deg, #f72585, #ff4d6d);
            color: white !important;
            border: none;
            padding: 12px;
            border-radius: 50px;
            font-weight: 700;
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.2);
            text-align: center;
            text-decoration: none;
        }

        .btn-detail:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 15px rgba(247, 37, 133, 0.4);
            filter: brightness(1.1);
        }

        .text-pink { color: var(--hot-pink); }
    </style>
</head>
<body>

<div class="best-seller-header">
    <div class="container-full">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active" aria-current="page">Best Seller</li>
            </ol>
        </nav>
        <h1 class="display-5 fw-800"><i class="fa-solid fa-fire-flame-curved me-2"></i> TOP SẢN PHẨM BÁN CHẠY</h1>
        <p class="lead opacity-90">Những sản phẩm được hàng ngàn khách hàng tin dùng tại Viet Duc Pharmacy</p>
    </div>
</div>

<div class="container-full pb-5">
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
        <?php if($result_all_best->num_rows > 0): ?>
            <?php while($item = $result_all_best->fetch_assoc()): ?>
            <div class="col">
                <div class="card-product shadow-sm">
                    <div class="img-wrapper">
                        <img src="images/<?= $item['image'] ?>" alt="<?= $item['name'] ?>">
                    </div>

                    <h6 class="text-center"><?= $item['name'] ?></h6>

                    <div class="price-new text-center mt-auto mb-3">
                        <?= number_format($item['price_new']) ?>đ
                    </div>

                    <div class="d-grid">
                        <a href="chitietsp.php?id=<?= $item['id'] ?>" class="btn btn-detail">
                            <i class="fas fa-eye me-1"></i> Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="bg-white p-5 rounded-4 shadow-sm border border-pink" style="border: 1px solid var(--soft-pink)">
                    <i class="fas fa-box-open fs-1 mb-3" style="color: var(--sweet-pink)"></i>
                    <p class="text-muted fs-5">Danh sách đang được cập nhật, vui lòng quay lại sau.</p>
                    <a href="index.php" class="btn btn-detail px-5 mt-3">QUAY LẠI TRANG CHỦ</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>