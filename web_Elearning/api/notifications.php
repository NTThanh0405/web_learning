<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');

// Kiểm tra người dùng đã đăng nhập
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'get_unread_count':
        $unread_count = countUnreadNotifications($user['id']);
        echo json_encode(['status' => 'success', 'unread_count' => $unread_count]);
        break;

    case 'get_notifications':
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 5;
        $notifications = getAllNotifications($user['id'], $limit);
        foreach ($notifications as &$notif) {
            switch ($notif['type']) {
                case 'forum':
                case 'comment_reply':
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
                    $notif['url'] = BASE_URL . "student/index.php?tab=notifications";
            }
        }
        echo json_encode(['status' => 'success', 'notifications' => $notifications]);
        break;

    case 'mark_as_read':
        $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        if ($notification_id) {
            markNotificationAsRead($notification_id);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
        }
        break;

    case 'mark_all_as_read':
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $user['id']]);
        $conn = null;
        echo json_encode(['status' => 'success']);
        break;

    case 'create_course_notification':
        if ($user['role'] !== 'teacher') {
            echo json_encode(['status' => 'error', 'message' => 'Only teachers can create course notifications']);
            exit;
        }

        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $scope = isset($_POST['scope']) ? trim($_POST['scope']) : 'global';

        if (!$course_id || !$title || !$content) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit;
        }

        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = :course_id AND teacher_id = :user_id");
        $stmt->execute(['course_id' => $course_id, 'user_id' => $user['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid course or unauthorized']);
            $conn = null;
            exit;
        }
        $conn = null;

        try {
            createCourseNotification($course_id, $user['id'], $title, $content, $scope);
            echo json_encode(['status' => 'success', 'message' => 'Course notification created']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create notification: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>