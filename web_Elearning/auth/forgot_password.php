<?php
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Khởi tạo kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Khởi tạo biến
$step = $_GET['step'] ?? 'request'; // request, verify, reset
$error = '';
$email = $_POST['email'] ?? '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        // Bước 1: Nhập email và gửi mã xác nhận
        $email = $_POST['email'] ?? '';

        // Kiểm tra email tồn tại
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Xóa các mã cũ của email này
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Tạo mã 6 số
            $token = sprintf("%06d", mt_rand(0, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

            // Lưu mã vào bảng password_resets
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires_at]);

            // Gửi email với mã xác nhận
            $mail = new PHPMailer(true);
            try {
                // Cấu hình SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Thay bằng SMTP server của bạn
                $mail->SMTPAuth = true;
                $mail->Username = 'your-email@gmail.com'; // Thay bằng email của bạn
                $mail->Password = 'your-app-password'; // Thay bằng mật khẩu ứng dụng
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Người gửi và người nhận
                $mail->setFrom('your-email@gmail.com', 'EPU System');
                $mail->addAddress($email);

                // Nội dung email
                $mail->isHTML(true);
                $mail->Subject = 'Khôi phục mật khẩu';
                $mail->Body = "Mã xác nhận của bạn là: <b>$token</b>. Mã này có hiệu lực trong 2 phút.";
                $mail->AltBody = "Mã xác nhận của bạn là: $token. Mã này có hiệu lực trong 2 phút.";

                $mail->send();
                $success = "Mã xác nhận đã được gửi đến email của bạn.";
                $step = 'verify';
            } catch (Exception $e) {
                $error = "Không thể gửi email. Vui lòng thử lại sau.";
            }
        } else {
            $error = "Email không tồn tại.";
        }
    } elseif ($step === 'verify') {
        // Bước 2: Xác nhận mã
        $email = $_POST['email'] ?? '';
        $token = $_POST['token'] ?? '';

        // Xóa các mã đã hết hạn
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at <= NOW()");
        $stmt->execute();

        // Kiểm tra mã xác nhận
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()");
        $stmt->execute([$email, $token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $step = 'reset';
        } else {
            $error = "Mã xác nhận không hợp lệ hoặc đã hết hạn.";
        }
    } elseif ($step === 'reset') {
        // Bước 3: Đặt lại mật khẩu
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Kiểm tra mật khẩu
        if (strlen($password) < 6) {
            $error = "Mật khẩu phải có ít nhất 6 ký tự.";
        } else {
            // Cập nhật mật khẩu
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            // Xóa các mã xác nhận cũ
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $success = "Mật khẩu đã được thay đổi thành công.";
            $step = 'request';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="epu Logo">
            <h2>Khôi phục mật khẩu</h2>
        </div>
        <div class="login-form">
            <h3><?php echo $step === 'request' ? 'Nhập email' : ($step === 'verify' ? 'Nhập mã xác nhận' : 'Đặt lại mật khẩu'); ?></h3>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="step" value="request">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Gửi mã xác nhận</button>
                    <div class="forgot-password">
                        <a href="<?php echo BASE_URL; ?>auth/login.php">Quay lại đăng nhập</a>
                    </div>
                </form>
            <?php elseif ($step === 'verify'): ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="step" value="verify">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="form-group">
                        <label for="token">Mã xác nhận</label>
                        <input type="text" class="form-control" id="token" name="token" placeholder="Nhập mã 6 số" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Xác nhận</button>
                    <div class="forgot-password">
                        <a href="<?php echo BASE_URL; ?>auth/login.php">Quay lại đăng nhập</a>
                    </div>
                </form>
            <?php elseif ($step === 'reset'): ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="step" value="reset">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <div class="form-group">
                        <label for="password">Mật khẩu mới</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-eye" id="togglePassword"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Đặt lại mật khẩu</button>
                    <div class="forgot-password">
                        <a href="<?php echo BASE_URL; ?>auth/login.php">Quay lại đăng nhập</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#togglePassword").click(function() {
                const passwordField = $("#password");
                const type = passwordField.attr("type") === "password" ? "text" : "password";
                passwordField.attr("type", type);
                $(this).toggleClass("fa-eye fa-eye-slash");
            });
        });
    </script>
</body>
</html>