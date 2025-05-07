<?php
require_once __DIR__ . '/../config/database.php';

// Khởi tạo kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Xóa các mã đã hết hạn
$stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at <= NOW()");
$stmt->execute();

echo "Đã xóa các mã xác nhận hết hạn.\n";
?>