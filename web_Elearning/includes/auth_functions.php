<?php
// Chỉ gọi session_start() một lần
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) || (isset($_COOKIE['user_id']) && isset($_COOKIE['role']));
}

function getUserRole() {
    if (isset($_SESSION['role'])) {
        return $_SESSION['role'];
    } elseif (isset($_COOKIE['role'])) {
        return $_COOKIE['role'];
    }
    return null;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }
}

function redirectIfNotRole($role) {
    if (getUserRole() !== $role) {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

function getCurrentUser() {
    // Khởi tạo kết nối cơ sở dữ liệu trong hàm
    $conn = getDBConnection();
    if (isLoggedIn()) {
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_COOKIE['user_id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        // Đóng kết nối
        $conn = null;
        return $user;
    }
    return null;
}

// Tự động chuyển hướng nếu có cookie hợp lệ
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    $user = getCurrentUser();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') {
            header("Location: " . BASE_URL . "admin/index.php");
        } elseif ($user['role'] === 'teacher') {
            header("Location: " . BASE_URL . "teacher/index.php");
        } elseif ($user['role'] === 'student') {
            header("Location: " . BASE_URL . "student/index.php");
        }
        exit();
    } else {
        // Xóa cookie nếu không hợp lệ
        setcookie('user_id', '', time() - 3600, "/");
        setcookie('role', '', time() - 3600, "/");
    }
}