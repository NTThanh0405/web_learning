<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nếu đã đăng nhập, chuyển hướng
if (isLoggedIn()) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

// Khởi tạo biến để lưu giá trị form
$errors = [];
$success = '';
$username = '';
$full_name = '';
$email = '';
$password = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "Vui lòng điền đầy đủ thông tin.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if ($role !== 'teacher' && $role !== 'student') {
        $errors[] = "Vai trò không hợp lệ.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Mật khẩu phải dài ít nhất 6 ký tự.";
    }

    if (strlen($username) < 3) {
        $errors[] = "Tên người dùng phải dài ít nhất 3 ký tự.";
    }

    // Kiểm tra username và email đã tồn tại
    $conn = getDBConnection();
    
    // Kiểm tra username
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Tên người dùng đã tồn tại.";
    }

    // Kiểm tra email
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email đã tồn tại.";
    }

    // Nếu không có lỗi, thêm người dùng vào cơ sở dữ liệu
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role) VALUES (:username, :full_name, :email, :password, :role)");
        $stmt->execute([
            'username' => $username,
            'full_name' => $full_name,
            'email' => $email,
            'password' => $hashed_password,
            'role' => $role
        ]);

        // Lấy thông tin người dùng vừa thêm (không cần đăng nhập tự động)
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $success = "Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.";
            // Xóa dữ liệu form để tránh nhập lại
            $username = '';
            $full_name = '';
            $email = '';
            $password = '';
            $role = '';
        } else {
            $errors[] = "Đã xảy ra lỗi khi tạo tài khoản.";
        }
    }

    // Đóng kết nối
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/register.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="EPU Logo">
            <h2>EPU</h2>
        </div>
        <div class="login-form">
            <h3>Đăng ký</h3>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-primary btn-sm mt-2">Đăng nhập ngay</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Tên người dùng:</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group">
                    <label for="full_name">Họ và tên:</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" name="password" id="password" class="form-control" value="<?php echo htmlspecialchars($password); ?>" required>
                </div>
                <div class="form-group">
                    <label for="role">Vai trò:</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="">-- Chọn vai trò --</option>
                        <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Giáo viên</option>
                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Đăng ký</button>
            </form>
            <div class="forgot-password">
                <a href="<?php echo BASE_URL; ?>auth/login.php">Đăng nhập</a>
            </div>
        </div>
    </div>
</body>
</html>