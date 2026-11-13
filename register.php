<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();
require_once "config/db.php";

if(isset($_POST['register']))
{
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if($fullname != "" && $email != "" && $password != "")
    {
        /* kiểm tra email tồn tại */

        $check = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $check->bind_param("s",$email);
        $check->execute();
        $result = $check->get_result();

        if($result->num_rows > 0)
        {
            $err = "Email đã tồn tại!";
        }
        else
        {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("
            INSERT INTO users(fullname,email,password,role)
            VALUES(?,?,?,'Khách hàng')
            ");

            $stmt->bind_param("sss",$fullname,$email,$hash);

            if($stmt->execute())
            {
                // Lưu tài khoản vào cơ sở dữ liệu thành công, chuyển sang trang đăng nhập
                $stmt->close();
                $check->close();
                header("Location: login.php");
                exit;
            }
            else
            {
                $err = "Đăng ký thất bại!";
            }

            $stmt->close();
        }

        $check->close();
    }
    else
    {
        $err = "Vui lòng nhập đầy đủ thông tin!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Đăng ký tài khoản</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/register.css">


</head>

<body>

<div class="register-box">

<div class="logo" style = "font-size : 30px">
Viet Duc Pharmacy</div>

<?php if(isset($err)): ?>

<div class="alert alert-danger text-center p-2">
<?= htmlspecialchars($err) ?>
</div>

<?php endif; ?>

<?php if(isset($success)): ?>

<div class="alert alert-success text-center p-2">
<?= htmlspecialchars($success) ?>
</div>

<?php endif; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label fw-bold">Họ và tên</label>

<input
type="text"
name="fullname"
class="form-control"
placeholder="Nguyễn Văn A"
required>

</div>

<div class="mb-3">

<label class="form-label fw-bold">Email</label>

<input
type="email"
name="email"
class="form-control"
placeholder="example@email.com"
required>

</div>

<div class="mb-3">

<label class="form-label fw-bold">Mật khẩu</label>

<input
type="password"
name="password"
class="form-control"
placeholder="••••••••"
required>

</div>

<button
type="submit"
name="register"
class="btn btn-primary w-100">

ĐĂNG KÝ

</button>

<div class="text-center mt-3">

Đã có tài khoản?

<a href="login.php" class="fw-bold text-decoration-none">
Đăng nhập
</a>

</div>

</form>

</div>

</body>
</html>