<?php
require_once __DIR__ . '/config/config.php'; 
require_once __DIR__ . '/includes/auth_functions.php'; // Thêm file chứa hàm kiểm tra đăng nhập

// Kiểm tra trạng thái đăng nhập
$user = getCurrentUser(); // Hàm này trả về thông tin người dùng hiện tại hoặc false nếu chưa đăng nhập

if ($user && is_array($user)) {
    // Nếu đã đăng nhập, kiểm tra vai trò và chuyển hướng tương ứng
    if ($user['role'] === 'teacher') {
        header("Location: " . BASE_URL . "teacher/index.php");
    } elseif ($user['role'] === 'student') {
        header("Location: " . BASE_URL . "student/index.php");
    } else {
        // Nếu vai trò không hợp lệ, chuyển về trang đăng nhập
        header("Location: " . BASE_URL . "auth/login.php");
    }
} else {
    // Nếu chưa đăng nhập, chuyển hướng đến trang đăng nhập
    header("Location: " . BASE_URL . "auth/login.php");
}

exit();
?>