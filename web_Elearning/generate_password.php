<?php
$password = "admin123"; // Mật khẩu bạn muốn sử dụng
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo $hashed_password;
?>