<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Chỉ cho phép sinh viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('student');

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

// Đánh dấu đã đọc khi xem trang này
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
$stmt->execute(['user_id' => $user['id']]);

// Xử lý URL cho từng thông báo
foreach ($notifications as &$notif) {
    switch ($notif['type']) {
        case 'forum':
        case 'comment_reply':
            // Trỏ về forums/create.php với post_id nếu có
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "student/index.php?tab=forums&post_id=" . $notif['related_id'] 
                : BASE_URL . "student/index.php?tab=forums";
            break;
        case 'course':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "courses/view.php?course_id=" . $notif['related_id'] 
                : BASE_URL . "student/index.php?tab=courses";
            break;
        case 'lesson':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "lessons/view.php?lesson_id=" . $notif['related_id'] 
                : BASE_URL . "student/index.php?tab=courses";
            break;
        case 'group':
            $notif['url'] = !empty($notif['related_id']) 
                ? BASE_URL . "student/index.php?tab=groups&group_id=" . $notif['related_id'] 
                : BASE_URL . "student/index.php?tab=groups";
            break;
        default:
            $notif['url'] = BASE_URL . "student/index.php?tab=notifications"; // Mặc định về trang thông báo
    }
}

$conn = null;
?>

<style>
    .list-group-item {
        transition: background-color 0.3s;
        padding: 15px;
        border: none;
        border-bottom: 1px solid #eee;
    }
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    .avatar-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 15px;
        object-fit: cover;
    }
    .notification-content {
        flex-grow: 1;
    }
    .notification-title {
        font-weight: bold;
        color: #333;
    }
    .notification-title.unread {
        color: #004085;
    }
    .notification-message {
        color: #555;
        margin: 2px 0;
    }
    .notification-message.unread {
        color: #004085;
    }
    .notification-time {
        font-size: 12px;
        color: #888;
    }
    a {
        text-decoration: none;
        color: inherit;
    }
    a:hover {
        color: #0066cc;
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
                        <li class="list-group-item" style="display: flex; align-items: center;">
                            <img src="<?php echo $notif['avatar'] ? BASE_URL . $notif['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                                 alt="Avatar" class="avatar-img">
                            <div class="notification-content">
                                <a href="<?php echo htmlspecialchars($notif['url']); ?>">
                                    <span class="notification-title <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                                        <?php echo htmlspecialchars($notif['title']); ?>
                                    </span><br>
                                    <span class="notification-message <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                                        <?php echo htmlspecialchars($notif['content']); ?>
                                    </span><br>
                                    <small class="notification-time"><?php echo htmlspecialchars($notif['created_at']); ?></small>
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
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>