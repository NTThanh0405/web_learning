<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Chỉ cho phép giáo viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();
$conn = getDBConnection();
$errors = [];
$success = false;

// Lấy tổng số khóa học của giáo viên
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as course_count FROM courses WHERE teacher_id = :teacher_id");
    $stmt->execute(['teacher_id' => $user['id']]);
    $courseCount = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];
} catch (PDOException $e) {
    $courseCount = 0;
}

// Xử lý thay đổi avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['error'] === UPLOAD_ERR_OK) {
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Chỉ chấp nhận file ảnh JPG, PNG hoặc GIF.";
        } elseif ($file['size'] > $maxSize) {
            $errors[] = "Kích thước file ảnh không được vượt quá 5MB.";
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $avatarName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $uploadPath = __DIR__ . '/../assets/uploads/profile_pictures/' . $avatarName;

            // Kiểm tra và xóa avatar cũ nếu tồn tại
            if (!empty($user['avatar'])) {
                $oldAvatarPath = __DIR__ . '/../' . $user['avatar'];
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath); // Xóa file ảnh cũ
                }
            }

            // Tải file ảnh mới lên
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Cập nhật avatar trong database
                try {
                    $stmt = $conn->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
                    $stmt->execute(['avatar' => 'assets/uploads/profile_pictures/' . $avatarName, 'id' => $user['id']]);
                    $user['avatar'] = 'assets/uploads/profile_pictures/' . $avatarName; // Cập nhật avatar trong session
                    $success = "Avatar đã được cập nhật thành công!";
                } catch (PDOException $e) {
                    $errors[] = "Lỗi khi cập nhật avatar: " . $e->getMessage();
                }
            } else {
                $errors[] = "Không thể tải file ảnh lên.";
            }
        }
    } else {
        $errors[] = "Có lỗi xảy ra khi tải ảnh lên.";
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errors[] = "Tất cả các trường đều bắt buộc.";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "Mật khẩu mới và xác nhận mật khẩu không khớp.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $storedPassword = $stmt->fetchColumn();

        if (password_verify($currentPassword, $storedPassword)) {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute(['password' => $newPasswordHash, 'id' => $user['id']]);
                $success = "Mật khẩu đã được đổi thành công!";
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi cập nhật mật khẩu: " . $e->getMessage();
            }
        } else {
            $errors[] = "Mật khẩu hiện tại không đúng.";
        }
    }
}

$conn = null;
?>

<div class="container mt-5">
    <h2>Thông Tin Tài Khoản</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body text-center">
            <h5 class="card-title">Thông tin giáo viên</h5>
            <img src="<?php echo ($user['avatar'] ?? false) ? BASE_URL . $user['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                 alt="Avatar" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
            <form method="POST" enctype="multipart/form-data" class="mb-3">
                <div class="form-group">
                    <input type="file" name="avatar" id="avatar" class="form-control-file" accept="image/*">
                    <small class="form-text text-muted">Chấp nhận JPG, PNG, GIF. Tối đa 5MB.</small>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Cập nhật avatar</button>
            </form>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Chưa cập nhật'); ?></p>
            <p><strong>Tên:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Chưa cập nhật'); ?></p>
            <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
            <p><strong>Tổng số khóa học đã tạo:</strong> <?php echo $courseCount; ?></p>
        </div>
    </div>

    <h3>Đổi Mật Khẩu</h3>
    <button type="button" class="btn btn-primary mb-3" onclick="document.getElementById('passwordForm').style.display='block';">Đổi mật khẩu</button>
    <div id="passwordForm" style="display: none;">
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <div class="form-group">
                <label for="current_password">Mật khẩu hiện tại:</label>
                <input type="password" name="current_password" id="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="new_password">Mật khẩu mới:</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Xác nhận đổi mật khẩu</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('passwordForm').style.display='none';">Hủy</button>
        </form>
    </div>
</div>