<?php
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Khởi tạo kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Khởi tạo biến $error và $email để kiểm soát giá trị
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Sử dụng email thay vì username để đăng nhập
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            if (isset($_POST['remember'])) {
                setcookie('user_id', $user['id'], time() + (86400 * 30), "/");
                setcookie('role', $user['role'], time() + (86400 * 30), "/");
            }

            // Chuyển hướng dựa trên vai trò
            if ($user['role'] === 'admin') {
                header("Location: " . BASE_URL . "admin/index.php");
            } elseif ($user['role'] === 'teacher') {
                header("Location: " . BASE_URL . "teacher/index.php");
            } elseif ($user['role'] === 'student') {
                header("Location: " . BASE_URL . "student/index.php");
            }
            exit();
        } else {
            $error = "Mật khẩu không đúng.";
        }
    } else {
        $error = "Email không tồn tại.";
    }
    
    // Nếu có lỗi, chuyển hướng về trang đăng nhập với thông báo lỗi qua GET
    if (!empty($error)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error) . "&email=" . urlencode($email));
        exit();
    }
}

// Lấy thông báo lỗi và email từ URL (nếu có)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $error = $_GET['error'] ?? '';
    $email = $_GET['email'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
    <!-- Thêm meta để ngăn browser cache form submission -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="epu Logo">
            <h2>Chào mừng bạn quay lại!</h2>
        </div>

        <!-- Form đăng nhập -->
        <div class="login-form">
            <h3>ĐĂNG NHẬP</h3>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <!-- Thêm input ẩn để ngăn gửi lại form -->
                <input type="hidden" name="form_token" value="<?php echo md5(uniqid(mt_rand(), true)); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="admin, teacher, student" required>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="input-group-append">
                            <span class="input-group-text">
                                <i class="fas fa-eye" id="togglePassword"></i>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Nhớ tài khoản</label>
                </div>
                <button type="submit" class="btn btn-primary">Đăng nhập</button>
                <div class="forgot-password">
                    <a href="<?php echo BASE_URL; ?>auth/forgot_password.php">Quên mật khẩu?</a>
                    <span> | </span>
                    <a href="<?php echo BASE_URL; ?>auth/register.php">Đăng ký tài khoản</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
    <script>
        // Hiển thị/Ẩn mật khẩu
        $(document).ready(function() {
            $("#togglePassword").click(function() {
                const passwordField = $("#password");
                const type = passwordField.attr("type") === "password" ? "text" : "password";
                passwordField.attr("type", type);
                $(this).toggleClass("fa-eye fa-eye-slash");
            });
            
            // Xóa tham số GET khỏi URL sau khi trang đã load
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>
</body>
</html>