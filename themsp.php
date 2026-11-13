<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/* ===== XÓA ẢNH PHỤ ===== */
if(isset($_GET['delete_img']) && isset($_GET['pid'])){

    $img_id = (int)$_GET['delete_img'];
    $pid = (int)$_GET['pid'];

    $img = $conn->query("SELECT image FROM product_images WHERE id=$img_id")->fetch_assoc();

    if($img && !empty($img['image'])){
        $file = "images/".$img['image'];

        if(file_exists($file)){
            unlink($file);
        }

        $conn->query("DELETE FROM product_images WHERE id=$img_id");
    }

    header("Location: themsp.php?edit=".$pid);
    exit;
}
/* ========= 1. XOÁ SẢN PHẨM & ẢNH LIÊN QUAN ========= */
if (isset($_GET['action']) && $_GET['action'] == 'delete') {

    $id = (int)$_GET['id'];
    
    // Xoá ảnh chính
    $img = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();
    if ($img && !empty($img['image']) && file_exists('images/'.$img['image'])) unlink('images/'.$img['image']);
    
    // Xoá toàn bộ ảnh phụ trong thư mục
    $gal = $conn->query("SELECT image FROM product_images WHERE product_id=$id");
    while($g = $gal->fetch_assoc()) {
        if(!empty($g['image']) && file_exists('images/'.$g['image'])) unlink('images/'.$g['image']);
    }
    
    $conn->query("DELETE FROM product_images WHERE product_id=$id");
    $conn->query("DELETE FROM products WHERE id=$id");
    header("Location: themsp.php"); exit;
}

/* ========= 2. LẤY DỮ LIỆU SỬA ========= */
$edit = null; $gallery = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit') {

    $id = (int)$_GET['id'];
    $edit = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
    $g = $conn->query("SELECT * FROM product_images WHERE product_id=$id");
    while($row = $g->fetch_assoc()){ $gallery[] = $row; }
}

/* ========= 3. LƯU DỮ LIỆU (THÊM/SỬA) ========= */
if (isset($_POST['save'])) {
    $id = $_POST['id'] ?? '';
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price_new = (float)$_POST['price_new'];
    $price_old = (float)$_POST['price_old'];
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $subcategory = mysqli_real_escape_string($conn, $_POST['subcategory']);
    $description = $_POST['description'];
$ingredients = $_POST['ingredients'];
    $stock = (int)$_POST['stock'];
$is_super_deal = isset($_POST['is_super_deal']) ? 1 : 0;
$is_featured   = isset($_POST['is_featured']) ? 1 : 0;
    // Xử lý ảnh chính (File đầu tiên)
    $main_image = "";

if (!empty($_FILES['images']['name'][0])) {

    // Nếu đang sửa thì xóa ảnh cũ
    if(!empty($id)){
        $old = $conn->query("SELECT image FROM products WHERE id=$id")->fetch_assoc();

        if($old && !empty($old['image'])){
            $old_file = "images/".$old['image'];
            if(file_exists($old_file)){
                unlink($old_file);
            }
        }
    }

    $ext = pathinfo($_FILES['images']['name'][0], PATHINFO_EXTENSION);
    $main_image = time().'_main_'.uniqid().'.'.$ext;

    move_uploaded_file($_FILES['images']['tmp_name'][0], 'images/'.$main_image);
}

    if ($id == '') {
        $sql = "INSERT INTO products 
(name, image, price_new, price_old, category, subcategory, description, ingredients, stock, is_super_deal, is_featured)
VALUES 
('$name','$main_image',$price_new,$price_old,'$category','$subcategory','$description','$ingredients',$stock,$is_super_deal,$is_featured)";
        $conn->query($sql);
        $product_id = $conn->insert_id;
    } else {
        $img_sql = $main_image ? ", image='$main_image'" : "";
        $sql = "UPDATE products SET 
name='$name',
price_new=$price_new,
price_old=$price_old,
category='$category',
subcategory='$subcategory',
description='$description',
ingredients='$ingredients',
is_super_deal=$is_super_deal,
stock=$stock,
is_featured=$is_featured
$img_sql
WHERE id=$id";
        $conn->query($sql);
        $product_id = $id;
    }

    // XỬ LÝ ẢNH PHỤ
    if (isset($_FILES['images']['name'])) {
        foreach ($_FILES['images']['name'] as $key => $val) {
            if ($key == 0 || empty($val)) continue; 
            
            $ext = pathinfo($val, PATHINFO_EXTENSION);
            $img_name = time().'_gal_'.$key.'_'.uniqid().'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$key], 'images/'.$img_name)) {
                $conn->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, '$img_name')");
            }
        }
    }
    header("Location: themsp.php"); exit;
}

$result = $conn->query("SELECT * FROM products ORDER BY id DESC");

// Cấu hình Subcategories để Javascript sử dụng
$sub_categories_json = json_encode([
    'thuc-pham-chuc-nang' => [
        'vitamin' => 'Vitamin',
        'mien-dich' => 'Miễn dịch - Đề kháng',
        'than-kinh-nao' => 'Thần kinh não',
        'tieu-hoa' => 'Tiêu hoá'
    ],
    'duoc-my-pham' => [
        'cham-soc-da-mat' => 'Chăm sóc da mặt',
        'kem-chong-nang' => 'Kem chống nắng',
        'cham-soc-co-the' => 'Chăm sóc cơ thể',
        'tri-mun' => 'Trị mụn',
        'duong-trang-da' => 'Dưỡng trắng da',
        'chong-lao-hoa' => 'Chống lão hóa'
    ],
     'thiet-bi-y-te' => [
        'may-do-huyet-ap' => 'Máy đo huyết áp',
        'nhiet-ke' => 'Nhiệt kế',
        'may-do-duong-huyet' => 'Máy đo đường huyết',
        'may-xong-mui-hong' => 'Máy xông mũi họng',
        'may-tao-oxy' => 'Máy tạo oxy',
        'can-suc-khoe' => 'Cân sức khỏe',
        'dung-cu-so-cuu' => 'Dụng cụ sơ cứu',
        'khau-trang-y-te' => 'Khẩu trang y tế'
    
    ],
    'thuoc' => [
    'giam-dau-ha-sot' => 'Giảm đau - Hạ sốt',
    'cam-cum' => 'Cảm cúm',
    'thuoc-ho' => 'Thuốc ho',
    'di-ung' => 'Dị ứng',
    'da-day' => 'Dạ dày',
    'thuoc-bo' => 'Thuốc bổ'
],


]);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viet Duc Admin | Quản lý sản phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
    <script>
     CKEDITOR.config.versionCheck = false;
      </script>
    <style>
        :root { --primary-blue: #1250dc; }
        body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; padding: 30px; }
        .admin-card { background: #fff; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none; padding: 25px; }
        .form-label { font-weight: 600; color: #475569; }
        .gallery-preview { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .gallery-preview img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
        .table img { border-radius: 8px; }
        .btn-save { background: var(--primary-blue); color: white; font-weight: 700; border-radius: 10px; padding: 12px; transition: 0.3s; }
        .btn-save:hover { background: #0a3bb1; transform: translateY(-2px); color: white; }

        #scrollDownBtn{
    position: fixed;
    bottom: 25px;
    left: 25px;
    width: 45px;
    height: 45px;
    border: none;
    border-radius: 50%;
    background: #1250dc;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    cursor: pointer;
    z-index: 999;
}

#scrollDownBtn:hover{
    background:#0a3bb1;
}
    </style>
</head>
<body>
<div class="d-flex justify-content-between align-items-center mb-3">

<?php
$back_link = "index.php";

if(isset($_SESSION['role'])){
    if($_SESSION['role'] == 'admin'){
        $back_link = "admin_users.php";
    }elseif($_SESSION['role'] == 'staff'){
        $back_link = "staff_dashboard.php";
    }
}
?>

<a href="<?= $back_link ?>" class="btn btn-outline-secondary">
<i class="fas fa-home me-1"></i> Quay lại
</a>


</div>
<div class="container">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-card sticky-top" style="top: 30px;">
                <h4 class="fw-bold mb-4 text-primary">
                    <i class="fas <?= $edit ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $edit ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?>
                </h4>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên sản phẩm</label>
                        <input name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Danh mục chính</label>
                            <select name="category" id="main-cat" class="form-select" onchange="loadSub()" required>
                                <option value="all">-- Chọn danh mục --</option>
                                <option value="thuc-pham-chuc-nang" <?= (isset($edit['category']) && $edit['category']=='thuc-pham-chuc-nang')?'selected':'' ?>>Thực phẩm chức năng</option>
                                <option value="duoc-my-pham" <?= (isset($edit['category']) && $edit['category']=='duoc-my-pham')?'selected':'' ?>>Dược mỹ phẩm</option>
                                <option value="thuoc" <?= (isset($edit['category']) && $edit['category']=='thuoc')?'selected':'' ?>>Thuốc kê đơn</option>
                                <option value="thiet-bi-y-te" <?= (isset($edit['category']) && $edit['category']=='thiet-bi-y-te')?'selected':'' ?>>Thiết bị y tế</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Danh mục con</label>
                            <select name="subcategory" id="sub-cat" class="form-select">
                                <option value="">-- Chọn danh mục con --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Giá mới (đ)</label>
        <input name="price_new" type="number" class="form-control" value="<?= $edit['price_new'] ?? '' ?>" required>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Giá cũ (đ)</label>
        <input name="price_old" type="number" class="form-control" value="<?= $edit['price_old'] ?? '' ?>">
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Số lượng kho</label>
        <input name="stock" type="number" class="form-control" value="<?= $edit['stock'] ?? 0 ?>">
    </div>
</div>
                    <div class="row">
    <div class="col-md-6 mb-3 form-check">
        <input class="form-check-input" type="checkbox" name="is_super_deal" value="1"
        <?= (isset($edit['is_super_deal']) && $edit['is_super_deal']==1)?'checked':'' ?>>
        <label class="form-check-label fw-bold text-danger">
            🧧 Săn Deal
        </label>
    </div>

    <div class="col-md-6 mb-3 form-check">
        <input class="form-check-input" type="checkbox" name="is_featured" value="1"
        <?= (isset($edit['is_featured']) && $edit['is_featured']==1)?'checked':'' ?>>
        <label class="form-check-label fw-bold text-primary">
            ⭐ Sản phẩm nổi bật
        </label>
    </div>
</div>

                    <div class="mb-3">
                        <label class="form-label">Hình ảnh (Đầu tiên là ảnh chính)</label>
                        <input type="file" name="images[]" class="form-control" multiple>
                        <?php if(!empty($gallery)): ?>
                            <div class="gallery-preview">
                                <?php foreach($gallery as $g): ?>
<div style="position:relative; display:inline-block;">
    <img src="images/<?= $g['image'] ?>" width="70">

    <a href="?delete_img=<?= $g['id'] ?>&pid=<?= $edit['id'] ?>"
       onclick="return confirm('Xoá ảnh này?')"
       style="position:absolute;top:-5px;right:-5px;
       background:red;color:white;border-radius:50%;
       width:18px;height:18px;font-size:12px;text-align:center;">
       ×
    </a>
</div>
<?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Thành phần</label>
                        <textarea name="ingredients" id="ingredients" class="form-control" rows="2"><?= $edit['ingredients'] ?? '' ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mô tả chi tiết</label>
                       <textarea name="description" id="description" class="form-control" rows="4"><?= $edit['description'] ?? '' ?></textarea>
                    </div>

                    <button name="save" class="btn btn-save w-100 mt-2">LƯU SẢN PHẨM</button>
                    <?php if($edit): ?>
                        <a href="themsp.php" class="btn btn-light w-100 mt-2">Hủy chế độ sửa</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="admin-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Danh sách sản phẩm</h5>
                    <span class="badge bg-primary rounded-pill"><?= $result->num_rows ?> sản phẩm</span>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
    <th>Ảnh</th>
    <th>Thông tin sản phẩm</th>
    <th class="text-center">Kho</th>
    <th class="text-end">Giá</th>
    <th class="text-center">Thao tác</th>
</tr>
                        </thead>
 <tbody>
<?php while($row = $result->fetch_assoc()): ?>
<tr>

<td>
<img src="images/<?= !empty($row['image']) ? htmlspecialchars($row['image']) : 'no-image.png' ?>" 
width="55" height="55" class="object-fit-cover">
</td>

<td>
<div class="fw-bold text-dark"><?= $row['name'] ?></div>
<small class="text-muted text-uppercase" style="font-size:10px;">
<?= $row['category'] ?>
</small>
</td>

<td class="text-center">
<?php if($row['stock'] == 0){ ?>
<span class="badge bg-danger">Hết</span>
<?php } elseif($row['stock'] < 5){ ?>
<span class="badge bg-warning"><?= $row['stock'] ?></span>
<?php } else { ?>
<span class="badge bg-success"><?= $row['stock'] ?></span>
<?php } ?>
</td>

<td class="text-end fw-bold text-primary">
<?= number_format($row['price_new'],0,',','.') ?>đ
</td>

<td class="text-center">
<div class="btn-group btn-group-sm">
<a href="themsp.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-outline-primary">
<i class="fas fa-edit"></i>
</a>

<a href="themsp.php?action=delete&id=<?= $row['id'] ?>" 
class="btn btn-outline-danger"
onclick="return confirm('Xoá sản phẩm này?')">
<i class="fas fa-trash"></i>
</a>
</div>
</td>

</tr>
<?php endwhile; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const subData = <?= $sub_categories_json ?>;
    const currentSub = "<?= $edit['subcategory'] ?? '' ?>";

    function loadSub() {
        const mainCat = document.getElementById('main-cat').value;
        const subCatSelect = document.getElementById('sub-cat');
        
        subCatSelect.innerHTML = '<option value="">-- Chọn danh mục con --</option>';
        
        if(subData[mainCat]) {
            Object.keys(subData[mainCat]).forEach(key => {
                const option = document.createElement('option');
                option.value = key;
                option.text = subData[mainCat][key];
                if(key === currentSub) option.selected = true;
                subCatSelect.appendChild(option);
            });
        }
    }

    // Tự động load subcategory khi trang được tải (dành cho chế độ sửa)
    window.onload = loadSub;
</script>

<script>
CKEDITOR.replace('description');
CKEDITOR.replace('ingredients');
</script>
<button id="scrollDownBtn">
<i class="fas fa-arrow-down"></i>
</button>

<script>
document.getElementById("scrollDownBtn").onclick = function(){
    window.scrollTo({
        top: document.body.scrollHeight,
        behavior: "smooth"
    });
};
</script>
</button>
</body>
</html>