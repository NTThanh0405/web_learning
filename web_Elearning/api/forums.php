<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');

redirectIfNotLoggedIn();
$user = getCurrentUser();

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'delete_post') {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    if ($post_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID bài đăng không hợp lệ']);
        exit;
    }

    $conn = getDBConnection();

    // Kiểm tra quyền sở hữu bài đăng
    $stmt = $conn->prepare("SELECT user_id FROM forum_posts WHERE id = :post_id AND status = 'active'");
    $stmt->execute(['post_id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post || $post['user_id'] !== $user['id']) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn không có quyền xóa bài đăng này']);
        exit;
    }

    try {
        // Bắt đầu giao dịch
        $conn->beginTransaction();

        // Xóa tất cả bình luận liên quan
        $stmt = $conn->prepare("DELETE FROM forum_comments WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $post_id]);

        // Xóa bài đăng
        $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = :post_id");
        $stmt->execute(['post_id' => $post_id]);

        // Commit giao dịch
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Bài đăng đã được xóa']);
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error deleting post: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Có lỗi xảy ra khi xóa bài đăng']);
    }

    $conn = null;
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ']);
?>