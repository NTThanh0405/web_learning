<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();
$conn = getDBConnection();

// Lấy tất cả thông báo
$stmt = $conn->prepare("
    SELECT n.*, u.avatar 
    FROM notifications n 
    LEFT JOIN users u ON n.sender_id = u.id 
    WHERE n.user_id = :user_id 
    ORDER BY n.created_at DESC
");
$stmt->execute(['user_id' => $user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý URL cho từng thông báo
foreach ($notifications as &$notif) {
    switch ($notif['type']) {
        case 'forum':
        case 'comment_reply':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "teacher/index.php?tab=forums&post_id=" . $notif['related_id'] 
                : BASE_URL . "teacher/index.php?tab=forums";
            break;
        case 'course':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "courses/view.php?course_id=" . $notif['related_id'] 
                : BASE_URL . "teacher/index.php?tab=courses";
            break;
        case 'lesson':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "lessons/view.php?lesson_id=" . $notif['related_id'] 
                : BASE_URL . "teacher/index.php?tab=courses";
            break;
        case 'group':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "groups/view.php?group_id=" . $notif['related_id'] 
                : BASE_URL . "teacher/index.php?tab=groups";
            break;
        default:
            $notif['url'] = '#';
    }
}

$conn = null;
?>

<style>
    .list-group-item {
        transition: background-color 0.3s;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .avatar-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }
    .unread {
        background-color: #e6f0fa; /* Màu xanh nhạt cho thông báo chưa đọc */
    }
    .unread:hover {
        background-color: #d0e0f5; /* Màu xanh đậm hơn khi hover */
    }
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h3><i class="fas fa-bell"></i> Tất cả thông báo</h3>
            <?php if (empty($notifications)): ?>
                <p class="text-muted">Không có thông báo nào.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notifications as $notif): ?>
                        <li class="list-group-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>" style="display: flex; align-items: center;">
                            <img src="<?php echo $notif['avatar'] ? BASE_URL . $notif['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                                 alt="Avatar" class="avatar-img">
                            <div>
                                <a href="<?php echo htmlspecialchars($notif['url']); ?>" style="text-decoration: none; color: inherit;">
                                    <strong><?php echo htmlspecialchars($notif['title']); ?></strong><br>
                                    <?php echo htmlspecialchars($notif['content']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($notif['created_at']); ?></small>
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>