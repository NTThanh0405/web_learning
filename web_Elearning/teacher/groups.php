<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';
require_once dirname(__DIR__) . '/includes/notifications.php';

// Chỉ cho phép giáo viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();
$conn = getDBConnection();
$action = $_GET['action'] ?? '';
$errors = [];
$success = false;

// Xử lý các hành động
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Handle group thumbnail upload
        $thumbnail = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['thumbnail'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($file['error'] === UPLOAD_ERR_OK) {
                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = "Chỉ chấp nhận file ảnh JPG, PNG hoặc GIF.";
                } elseif ($file['size'] > $maxSize) {
                    $errors[] = "Kích thước file ảnh không được vượt quá 5MB.";
                } else {
                    $uploadDir = __DIR__ . '/../assets/uploads/groups/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $thumbnail = 'group_' . time() . '.' . $ext;
                    $uploadPath = $uploadDir . $thumbnail;

                    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $errors[] = "Không thể tải file ảnh lên. Kiểm tra quyền thư mục hoặc đường dẫn.";
                    } else {
                        $thumbnail = "assets/uploads/groups/" . $thumbnail;
                    }
                }
            } else {
                $errors[] = "Có lỗi xảy ra khi tải ảnh lên.";
            }
        }

        if (empty($name)) {
            $errors[] = "Tên nhóm là bắt buộc.";
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("INSERT INTO groups (name, description, creator_id, thumbnail) VALUES (:name, :description, :creator_id, :thumbnail)");
                $stmt->execute(['name' => $name, 'description' => $description, 'creator_id' => $user['id'], 'thumbnail' => $thumbnail]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi tạo nhóm: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && isset($_POST['group_id'])) {
        $groupId = $_POST['group_id'];
        try {
            $stmt = $conn->prepare("SELECT creator_id, thumbnail FROM groups WHERE id = :group_id");
            $stmt->execute(['group_id' => $groupId]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$group) {
                $errors[] = "Nhóm không tồn tại.";
            } elseif ($group['creator_id'] != $user['id']) {
                $errors[] = "Bạn không có quyền xóa nhóm này.";
            } else {
                $conn->beginTransaction();

                if ($group['thumbnail'] && file_exists(__DIR__ . '/../' . $group['thumbnail'])) {
                    if (!unlink(__DIR__ . '/../' . $group['thumbnail'])) {
                        $errors[] = "Không thể xóa file ảnh đại diện.";
                    }
                }

                $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = :group_id");
                $stmt->execute(['group_id' => $groupId]);
                $stmt = $conn->prepare("DELETE FROM chat_messages WHERE group_id = :group_id");
                $stmt->execute(['group_id' => $groupId]);
                $stmt = $conn->prepare("DELETE FROM groups WHERE id = :group_id AND creator_id = :creator_id");
                $stmt->execute(['group_id' => $groupId, 'creator_id' => $user['id']]);
                $conn->commit();
                $success = true;
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Lỗi khi xóa nhóm: " . $e->getMessage();
        }
    } elseif ($action === 'kick' && isset($_POST['group_id']) && isset($_POST['user_id'])) {
        $groupId = $_POST['group_id'];
        $userId = $_POST['user_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = :group_id AND user_id = :user_id");
            $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi kick thành viên: " . $e->getMessage();
        }
    } elseif ($action === 'add_member' && isset($_POST['group_id']) && isset($_POST['user_id'])) {
        $groupId = $_POST['group_id'];
        $userId = $_POST['user_id'];

        try {
            // Kiểm tra xem nhóm có tồn tại và giáo viên có quyền không
            $stmt = $conn->prepare("SELECT creator_id FROM groups WHERE id = :group_id");
            $stmt->execute(['group_id' => $groupId]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$group) {
                $errors[] = "Nhóm không tồn tại.";
            } elseif ($group['creator_id'] != $user['id']) {
                $errors[] = "Bạn không có quyền thêm thành viên vào nhóm này.";
            } else {
                // Kiểm tra xem người dùng có phải là sinh viên không
                $stmt = $conn->prepare("SELECT role FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $userId]);
                $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$targetUser || $targetUser['role'] !== 'student') {
                    $errors[] = "Chỉ có thể thêm sinh viên vào nhóm.";
                } else {
                    // Kiểm tra xem sinh viên đã có trong nhóm chưa
                    $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = :group_id AND user_id = :user_id");
                    $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
                    if ($stmt->fetch()) {
                        $errors[] = "Sinh viên này đã có trong nhóm.";
                    } else {
                        // Thêm sinh viên vào nhóm
                        $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)");
                        $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);

                        // Tạo thông báo cho sinh viên
                        createJoinGroupNotification($groupId, $userId, $user['id']);
                        $success = true;
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi thêm thành viên: " . $e->getMessage();
        }
    }
}

// Lấy danh sách nhóm
try {
    $stmt = $conn->prepare("
        SELECT g.*, COUNT(gm.user_id) as member_count
        FROM groups g
        LEFT JOIN group_members gm ON g.id = gm.group_id
        WHERE g.creator_id = :creator_id
        GROUP BY g.id
    ");
    $stmt->execute(['creator_id' => $user['id']]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $groups = [];
}

// Lấy danh sách sinh viên để thêm vào nhóm
$students = [];
try {
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'student'");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $students = [];
}

$conn = null;
?>

<div class="container mt-5">
    <?php if ($action === 'create'): ?>
        <h2>Tạo Nhóm Học Mới</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Nhóm đã được tạo thành công! 
                <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=groups">Quay lại danh sách nhóm</a>
            </div>
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
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Tên nhóm:</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Mô tả:</label>
                <textarea name="description" id="description" class="form-control" rows="5"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="thumbnail">Hình ảnh đại diện:</label>
                <input type="file" name="thumbnail" id="thumbnail" class="form-control-file" accept="image/*">
                <small class="form-text text-muted">Chấp nhận JPG, PNG, GIF. Tối đa 5MB.</small>
            </div>
            <button type="submit" class="btn btn-primary">Tạo nhóm</button>
            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=groups" class="btn btn-secondary">Hủy</a>
        </form>
    <?php else: ?>
        <h2>Quản Lý Nhóm Học</h2>
        <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=groups&action=create" class="btn btn-primary mb-3">Tạo nhóm mới</a>

        <?php if ($success): ?>
            <div class="alert alert-success">Thao tác thành công!</div>
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

        <?php if (empty($groups)): ?>
            <p>Bạn chưa tạo nhóm học nào.</p>
        <?php else: ?>
            <div class="course-suggest-grid">
                <?php foreach ($groups as $group): ?>
                    <div class="course-suggest-card">
                        <div class="course-image">
                            <img src="<?php echo !empty($group['thumbnail']) ? BASE_URL . htmlspecialchars($group['thumbnail']) : BASE_URL . 'assets/images/default_course.jpg'; ?>" alt="Group Image">
                        </div>
                        <div class="course-details">
                            <h5 class="course-title"><?php echo htmlspecialchars($group['name']); ?></h5>
                            <p>
                                <i class="fas fa-users"></i> <?php echo isset($group['member_count']) ? $group['member_count'] : 0; ?>
                            </p>
                            <a href="<?php echo BASE_URL; ?>groups/chat.php?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm mt-2">Chat</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhóm này?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm mt-2">Xóa</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>