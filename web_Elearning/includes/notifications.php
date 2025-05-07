<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Lấy số lượng thông báo chưa đọc của người dùng
 * @param int $user_id ID của người dùng
 * @return int Số lượng thông báo chưa đọc
 */
function countUnreadNotifications($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute(['user_id' => $user_id]);
    $count = $stmt->fetchColumn();
    $conn = null;
    return $count;
}

/**
 * Lấy danh sách thông báo chưa đọc của người dùng
 * @param int $user_id ID của người dùng
 * @param int $limit Số lượng thông báo tối đa cần lấy
 * @return array Danh sách thông báo
 */
function getUnreadNotifications($user_id, $limit = 5) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT n.*, u.full_name AS sender_name, u.avatar 
        FROM notifications n 
        JOIN users u ON n.sender_id = u.id 
        WHERE n.user_id = :user_id 
        AND n.is_read = 0 
        ORDER BY n.created_at DESC 
        LIMIT :limit
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $conn = null;
    return $notifications;
}

/**
 * Lấy tất cả thông báo của người dùng (bao gồm đã đọc và chưa đọc)
 * @param int $user_id ID của người dùng
 * @param int $limit Số lượng thông báo tối đa cần lấy
 * @return array Danh sách thông báo
 */
function getAllNotifications($user_id, $limit = null) {
    $conn = getDBConnection();
    $query = "
        SELECT n.*, u.full_name AS sender_name, u.avatar 
        FROM notifications n 
        JOIN users u ON n.sender_id = u.id 
        WHERE n.user_id = :user_id 
        ORDER BY n.created_at DESC
    ";
    if ($limit !== null) {
        $query .= " LIMIT :limit";
    }
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if ($limit !== null) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $conn = null;
    return $notifications;
}

/**
 * Đánh dấu một thông báo là đã đọc
 * @param int $notification_id ID của thông báo
 */
function markNotificationAsRead($notification_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
    $stmt->execute(['id' => $notification_id]);
    $conn = null;
}

/**
 * Tạo thông báo mới cho một người dùng
 * @param int $user_id ID của người nhận thông báo
 * @param int $sender_id ID của người gửi thông báo
 * @param string $type Loại thông báo ('course', 'forum', 'group')
 * @param string $title Tiêu đề thông báo
 * @param string $content Nội dung thông báo
 * @param int|null $related_id ID liên quan (ví dụ: group_id, course_id)
 * @param string $scope Phạm vi thông báo ('global', 'course')
 */
function createNotification($user_id, $sender_id, $type, $title, $content, $related_id = null, $scope = 'global') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, sender_id, type, scope, title, content, related_id, is_read, created_at)
        VALUES (:user_id, :sender_id, :type, :scope, :title, :content, :related_id, 0, NOW())
    ");
    $stmt->execute([
        'user_id' => $user_id,
        'sender_id' => $sender_id,
        'type' => $type,
        'scope' => $scope,
        'title' => $title,
        'content' => $content,
        'related_id' => $related_id
    ]);
    $conn = null;
}

/**
 * Tạo thông báo nhóm học cho tất cả sinh viên trong nhóm
 * @param int $group_id ID của nhóm học
 * @param int $sender_id ID của giáo viên gửi thông báo
 * @param string $title Tiêu đề thông báo
 * @param string $content Nội dung thông báo
 * @param string $scope Phạm vi thông báo ('global', 'course')
 */
function createGroupNotification($group_id, $sender_id, $title, $content, $scope = 'global') {
    $conn = getDBConnection();
    
    // Lấy danh sách sinh viên trong nhóm
    $stmt = $conn->prepare("
        SELECT gm.user_id 
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        WHERE gm.group_id = :group_id AND u.role = 'student'
    ");
    $stmt->execute(['group_id' => $group_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tạo thông báo cho từng sinh viên
    foreach ($students as $student) {
        createNotification(
            $student['user_id'],
            $sender_id,
            'group',
            $title,
            $content,
            $group_id,
            $scope
        );
    }
    
    $conn = null;
}

/**
 * Tạo thông báo khi sinh viên được thêm vào nhóm
 * @param int $group_id ID của nhóm học
 * @param int $student_id ID của sinh viên
 * @param int $sender_id ID của giáo viên thêm sinh viên
 */
function createJoinGroupNotification($group_id, $student_id, $sender_id) {
    $conn = getDBConnection();
    
    // Lấy tên nhóm
    $stmt = $conn->prepare("SELECT name FROM groups WHERE id = :group_id");
    $stmt->execute(['group_id' => $group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    $group_name = $group['name'] ?? 'Nhóm học';

    // Lấy tên giáo viên
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = :sender_id");
    $stmt->execute(['sender_id' => $sender_id]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    $sender_name = $sender['full_name'] ?? 'Giáo viên';

    // Tạo tiêu đề và nội dung thông báo
    $title = "Đã được thêm vào $group_name";
    $content = "$sender_name đã thêm bạn vào nhóm $group_name.";

    // Gọi hàm createNotification để tạo thông báo
    createNotification(
        $student_id,
        $sender_id,
        'group',
        $title,
        $content,
        $group_id,
        'group'
    );

    $conn = null;
}