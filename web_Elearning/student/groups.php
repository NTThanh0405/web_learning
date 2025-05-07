<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Chỉ cho phép sinh viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('student');

$user = getCurrentUser();

// Lấy kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Lấy danh sách các nhóm học mà sinh viên đã tham gia
$stmt = $conn->prepare("
    SELECT g.*, gm.joined_at
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = :user_id
");
$stmt->execute(['user_id' => $user['id']]);
$joined_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Đóng kết nối
$conn = null;
?>

<!-- Nội dung chính của groups.php -->
<div class="course-recommendations">
    <h2>Nhóm học đã tham gia</h2>
    <?php if (empty($joined_groups)): ?>
        <p>Bạn chưa tham gia nhóm học nào.</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($joined_groups as $group): ?>
                <div class="col-md-4">
                    <div class="group-card" data-group-id="<?php echo $group['id']; ?>">
                        <img src="<?php echo $group['thumbnail'] ? BASE_URL . htmlspecialchars($group['thumbnail']) : BASE_URL . 'assets/images/group_default.jpg'; ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                        <div class="group-card-body">
                            <p><?php echo htmlspecialchars($group['name']); ?></p>
                            <span class="badge">Tham gia: <?php echo date('d/m/Y', strtotime($group['joined_at'])); ?></span>
                            <div class="group-actions">
                                <a href="<?php echo BASE_URL; ?>groups/chat.php?group_id=<?php echo $group['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-comments"></i> Chat</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>