<?php 
session_start();
require_once 'config/db.php'; 

if(isset($_POST['consult_submit'])){

    $phone = $conn->real_escape_string($_POST['phone']);

    if(!empty($phone)){

        $sql = "INSERT INTO consult_requests(phone) VALUES('$phone')";
        $conn->query($sql);

        echo "<script>alert('Đã gửi yêu cầu tư vấn thành công!');</script>";

    }

}
/**
 * Hàm lấy đường dẫn ảnh bài viết
 */
function getProductImage($image_name) {
    $path = "images/" . $image_name;
    return (!empty($image_name) && file_exists($path)) ? $path : "https://via.placeholder.com/400x250?text=Health+Care";
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Góc Sức Khỏe - Tin tức & Video nổi bật</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="css/gocsk.css?v=<?= filemtime('css/gocsk.css') ?>">
  
  
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-success fs-3" href="index.php">
            <i class="fas fa-heartbeat me-2"></i>VIET DUC PHARMACY
        </a>
        <div class="ms-auto d-flex align-items-center">

<a href="index.php" class="nav-link me-3">Trang chủ</a>

<a href="all-posts.php" class="nav-link active fw-bold text-success me-3">Sức khỏe</a>

<a href="#" onclick="checkAdmin()" class="btn btn-light rounded-circle shadow-sm text-danger">
<i class="fas fa-user-shield"></i>
</a>

</div>
</nav>

<header class="health-banner text-center">
    <div class="container">
        <span class="badge bg-success mb-3 px-3 py-2 rounded-pill">KIẾN THỨC CHUYÊN GIA</span>
        <h1 class="display-4 fw-bold mb-3">Góc Sức Khỏe & Đời Sống</h1>
        <p class="lead opacity-90">Đồng hành cùng cộng đồng vì một thế hệ sống khỏe và hạnh phúc</p>
    </div>
</header>

<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-end mb-4 border-start border-success border-5 ps-3">
                <div>
                    <h3 class="fw-bold mb-0">Cập nhật mới nhất</h3>
                    <p class="text-muted small mb-0">Tin tức y khoa chính thống mỗi ngày</p>
                </div>
                <a href="all-posts.php" class="text-success text-decoration-none fw-bold">
                    Tất cả <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php
                $sql = "SELECT * FROM posts ORDER BY id DESC LIMIT 6";
                $result = $conn->query($sql);
                if($result && $result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                ?>
                <div class="col-md-6">
                    <div class="card card-post">
                        <div class="post-img-wrapper">
                            <span class="category-badge shadow-sm"><?= htmlspecialchars($row['category']) ?></span>
                            <img src="<?= getProductImage($row['image_url']) ?>" alt="Post image">
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3 text-muted small">
                                <i class="far fa-calendar-alt me-2"></i> <?= date('d/m/Y', strtotime($row['created_at'])) ?>
                                <span class="mx-2">•</span>
                                <i class="far fa-clock me-2"></i> 2 phút đọc
                            </div>
                            <h5 class="post-title mb-3"><?= htmlspecialchars($row['title']) ?></h5>
                            <a href="detail.php?id=<?= $row['id'] ?>" class="btn btn-link text-success p-0 fw-bold text-decoration-none">
                                Đọc tiếp <i class="fas fa-chevron-right ms-1 small"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sidebar-box shadow-sm border-0">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-user-md text-success fs-4"></i>
                    </div>
                    <h5 class="fw-bold mb-0">Dược sĩ tư vấn</h5>
                </div>
                <p class="small text-muted mb-4">Bạn đang gặp vấn đề sức khỏe? Hãy để chuyên gia của chúng tôi hỗ trợ ngay.</p>
               <form class="consult-form" method="POST">
    
    <input type="text"
           name="phone"
           class="form-control mb-3"
           placeholder="Số điện thoại của bạn..."
           required>

    <button type="submit"
        name="consult_submit"
        class="btn btn-success w-100 btn-consult shadow-sm">
    NHẬN TƯ VẤN MIỄN PHÍ
</button>

</form>

<div class="sidebar-box shadow-sm mt-4">

<h6 class="fw-bold mb-3 text-success">
<i class="fas fa-user-md me-2"></i>Dược sĩ đang trực
</h6>

<!-- Dược sĩ 1 -->
<div class="d-flex align-items-center mb-3 border-bottom pb-3">

<img src="images/dat.jpg"
     class="rounded-circle me-3 shadow-sm"
     style="width:60px;height:60px;object-fit:cover;"
     alt="Dược sĩ: Phạm Bảo Sơn">

<div class="flex-grow-1">

<div class="fw-bold text-dark">
<i class="fas fa-user-md text-success me-1"></i>
Dược sĩ: Phạm Bảo Sơn
</div>

<a href="tel:0987654321"
   class="text-success small text-decoration-none">

<i class="fas fa-phone-alt me-1"></i>
0987 654 321

</a>

</div>

<a href="tel:0987654321"
   class="btn btn-success btn-sm rounded-pill px-3">

<i class="fas fa-phone me-1"></i> Gọi

</a>

</div>

<!-- Dược sĩ 2 -->
<div class="d-flex align-items-center mb-3 border-bottom pb-3">

<img src="images/duc.jpg"
     class="rounded-circle me-3 shadow-sm"
     style="width:60px;height:60px;object-fit:cover;"
     alt="Dược sĩ: Trần Việt Đức">

<div class="flex-grow-1">

<div class="fw-bold text-dark">
<i class="fas fa-user-md text-success me-1"></i>
Dược sĩ: Trần Việt Đức
</div>

<a href="tel:0366388104"
   class="text-success small text-decoration-none">

<i class="fas fa-phone-alt me-1"></i>
0366388104

</a>

</div>

<a href="tel:0366388104"
   class="btn btn-success btn-sm rounded-pill px-3">

<i class="fas fa-phone me-1"></i> Gọi

</a>

</div>
<!-- Dược sĩ 3 -->
<div class="d-flex align-items-center mb-3 border-bottom pb-3">

<img src="images/khanh.jpg"
     class="rounded-circle me-3 shadow-sm"
     style="width:60px;height:60px;object-fit:cover;"
     alt="Dược sĩ: Nguyễn Thị Khanh">

<div class="flex-grow-1">

<div class="fw-bold text-dark">
<i class="fas fa-user-md text-success me-1"></i>
Dược sĩ: Nguyễn Thị Khanh
</div>

<a href="tel:0969917694"
   class="text-success small text-decoration-none">

<i class="fas fa-phone-alt me-1"></i>
0969917694

</a>

</div>

<a href="tel:0969917694"
   class="btn btn-success btn-sm rounded-pill px-3">

<i class="fas fa-phone me-1"></i> Gọi

</a>

</div>
<!-- Dược sĩ 4 -->

<div class="d-flex align-items-center mb-3 border-bottom pb-3">

<img src="images/oanh.jpg"
     class="rounded-circle me-3 shadow-sm"
     style="width:60px;height:60px;object-fit:cover;"
     alt="Dược sĩ: Nguyễn Thị Kim Oanh">

<div class="flex-grow-1">

<div class="fw-bold text-dark">
<i class="fas fa-user-md text-success me-1"></i>
Dược sĩ: Nguyễn Thị Kim Oanh
</div>

<a href="tel:0987 679034
"
   class="text-success small text-decoration-none">

<i class="fas fa-phone-alt me-1"></i>
0987 679034

</a>

</div>

<a href="tel:0987 679034"
   class="btn btn-success btn-sm rounded-pill px-3">

<i class="fas fa-phone me-1"></i> Gọi

</a>

</div>
            </div>

            <div class="sidebar-box text-center border-0 bg-danger bg-opacity-10">
                <p class="mb-1 fw-bold text-danger">TỔNG ĐÀI HỖ TRỢ 24/7</p>
                <h2 class="text-danger fw-bold mb-0">1900.8888</h2>
                <p class="text-muted small mt-2 mb-0">Liên hệ ngay nếu bạn cần giúp đỡ khẩn cấp</p>
            </div>
            
            <a href="javascript:void(0)" class="d-block mt-4">
                <img src="https://tse3.mm.bing.net/th/id/OIP.PgK9g3dlbZhlAjOBxbxTWwHaDX?pid=Api&P=0&h=220"
                     class="w-100 rounded-4 shadow-sm border">
            </a>
        </div>
    </div>
</div>

<section class="video-shorts-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h3 class="fw-bold mb-0"><i class="fas fa-bolt text-warning me-2"></i>Video Short khỏe</h3>
                <p class="mb-0 opacity-75">Kiến thức y khoa nhanh trong 60 giây</p>
            </div>
            <a href="all-videos.php" class="btn btn-outline-light rounded-pill px-4">Xem tất cả</a>
        </div>
        
        <div class="video-shorts-wrapper">
            <?php 
            $v_result = $conn->query("SELECT * FROM posts WHERE video_url != '' ORDER BY id DESC LIMIT 10");
            if($v_result && $v_result->num_rows > 0):
                while($v_row = $v_result->fetch_assoc()):
            ?>
            <div class="video-short-card shadow-lg" onclick="location.href='detail.php?id=<?= $v_row['id'] ?>'">
                <div class="play-btn-center shadow"><i class="fas fa-play"></i></div>
                <img src="<?= getProductImage($v_row['image_url']) ?>" alt="Video">
                <div class="video-overlay">
                    <p class="video-title text-white fw-bold"><?= htmlspecialchars($v_row['title']) ?></p>
                </div>
            </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</section>

<footer class="bg-white py-5 border-top">
    <div class="container text-center">
        <h4 class="fw-bold text-success mb-3">VIET DUC PHARMACY</h4>
        <p class="text-muted small mb-0">© 2026 Chuyên gia chăm sóc sức khỏe của mọi nhà.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function checkAdmin(){

    var pass = prompt("Nhập mật khẩu quản lý:");

    if(pass === "admin123"){
        window.location.href = "admin.gocsk.php";
    }else if(pass !== null){
        alert("Sai mật khẩu!");
    }

}

setInterval(function(){

let btns = document.querySelectorAll('.contact-btn');

btns.forEach(btn=>{
btn.style.transform="translateX(-5px)";
setTimeout(()=>{btn.style.transform="translateX(0)";},200);
});

},6000);
</script>
<div class="contact-floating">

<a href="tel:0366388104" class="contact-btn phone">
<i class="fas fa-phone"></i>
<span>Gọi</span>
</a>

<a href="https://zalo.me/0366388104" target="_blank" class="contact-btn zalo">
<img src="https://upload.wikimedia.org/wikipedia/commons/9/91/Icon_of_Zalo.svg">
<span>Zalo</span>
</a>

<a href="https://m.me/uctran.147643" target="_blank" class="contact-btn messenger">
<i class="fab fa-facebook-messenger"></i>
<span>Messenger</span>
</a>

</div>
<?php include_once __DIR__ . '/chatbot_widget.php'; ?>
</body>
</html>