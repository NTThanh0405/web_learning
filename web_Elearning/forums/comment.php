<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Kiểm tra đăng nhập
redirectIfNotLoggedIn();

// Đặt header trả về JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)$_POST['post_id'];
    $content = trim($_POST['content']); // Dữ liệu từ form vẫn là 'content'
    $parent_comment_id = isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== '' ? (int)$_POST['parent_comment_id'] : null;
    $user_id = $_SESSION['user_id'];

    // Kiểm tra dữ liệu đầu vào
    if ($post_id <= 0 || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        exit();
    }

    $conn = getDBConnection();

    try {
        // Thêm bình luận, sử dụng cột 'comment' thay vì 'content'
        $stmt = $conn->prepare("
            INSERT INTO forum_comments (post_id, user_id, comment, parent_comment_id, created_at)
            VALUES (:post_id, :user_id, :comment, :parent_comment_id, NOW())
        ");
        $stmt->execute([
            'post_id' => $post_id,
            'user_id' => $user_id,
            'comment' => $content, // Gán giá trị từ $content vào cột 'comment'
            'parent_comment_id' => $parent_comment_id
        ]);
        $comment_id = $conn->lastInsertId();

        // Gửi thông báo đến tác giả bài đăng
        $stmt = $conn->prepare("SELECT user_id FROM forum_posts WHERE id = :post_id");
        $stmt->execute(['post_id' => $post_id]);
        $post_author = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post_author && $post_author['user_id'] != $user_id) {
            createNotification(
                $post_author['user_id'],
                'comment_reply',
                'Có người trả lời bài viết của bạn',
                $content,
                $post_id,
                $comment_id
            );
        }

        // Nếu là trả lời bình luận, gửi thông báo đến tác giả bình luận gốc
        if ($parent_comment_id) {
            $stmt = $conn->prepare("SELECT user_id FROM forum_comments WHERE id = :parent_comment_id");
            $stmt->execute(['parent_comment_id' => $parent_comment_id]);
            $parent_comment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parent_comment && $parent_comment['user_id'] != $user_id && $parent_comment['user_id'] != $post_author['user_id']) {
                createNotification(
                    $parent_comment['user_id'],
                    'comment_reply',
                    'Có người trả lời bình luận của bạn',
                    $content,
                    $post_id,
                    $comment_id
                );
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Bình luận đã được gửi thành công']);
    } catch (PDOException $e) {
        // Trả về lỗi chi tiết trong JSON để dễ gỡ lỗi
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi gửi bình luận: ' . $e->getMessage()]);
    }

    $conn = null;
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
exit();
?>