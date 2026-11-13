<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
}

echo "Xin chào: ".$_SESSION['fullname']."<br>";
echo "Quyền: ".$_SESSION['role']."<br><br>";

if($_SESSION['role'] == "Quản lý"){
    echo "Trang quản trị hệ thống";
}

elseif($_SESSION['role'] == "Nhân viên"){
    echo "Trang nhân viên";
}

else{
    echo "Trang khách hàng";
}
?>