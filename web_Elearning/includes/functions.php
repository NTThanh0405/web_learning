<?php
require_once __DIR__ . '/auth_functions.php';

function login($email, $password) {
    global $conn;
    
    // Sử dụng email thay vì username để đồng bộ với auth/login.php
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        // Cập nhật thời gian hoạt động cuối
        $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Chuyển hướng ngay sau khi đăng nhập thành công
        if ($user['role'] === 'admin') {
            header("Location: " . BASE_URL . "admin/index.php");
        } elseif ($user['role'] === 'teacher') {
            header("Location: " . BASE_URL . "teacher/index.php");
        } elseif ($user['role'] === 'student') {
            header("Location: " . BASE_URL . "student/index.php");
        }
        exit();
    }
    return false;
}

function register($username, $email, $password, $full_name, $role) {
    global $conn;
    
    // Chỉ cho phép đăng ký vai trò teacher hoặc student
    if ($role !== 'teacher' && $role !== 'student') {
        return false; // Không cho phép đăng ký admin
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
}
?>