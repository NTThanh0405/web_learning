<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$post_id = $_GET['post_id'] ?? $_POST['post_id'] ?? 0;

switch ($action) {
    case 'get_all_parent_comments':
        // Lấy tất cả bình luận cha của bài đăng
        if ($post_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid post_id']);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                SELECT fc.*, u.full_name, u.avatar, 
                       (SELECT COUNT(*) FROM forum_comments WHERE parent_comment_id = fc.id AND status = 'active') as child_count
                FROM forum_comments fc 
                JOIN users u ON fc.user_id = u.id 
                WHERE fc.post_id = :post_id AND fc.status = 'active' AND fc.parent_comment_id IS NULL 
                ORDER BY fc.created_at ASC
            ");
            $stmt->execute(['post_id' => $post_id]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Điều chỉnh đường dẫn avatar
            foreach ($comments as &$comment) {
                if (!empty($comment['avatar'])) {
                    $comment['avatar'] = BASE_URL . $comment['avatar'];
                } else {
                    $comment['avatar'] = BASE_URL . 'assets/images/user_avatar.jpg';
                }
            }

            echo json_encode([
                'status' => 'success',
                'comments' => $comments
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error fetching comments: ' . $e->getMessage()
            ]);
        }
        break;

    case 'get_child_comments':
        // Lấy tất cả bình luận con của một bình luận cha
        $parent_comment_id = $_GET['parent_comment_id'] ?? 0;
        if ($post_id <= 0 || $parent_comment_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid post_id or parent_comment_id']);
            exit();
        }

        try {
            $stmt = $conn->prepare("
                SELECT fc.*, u.full_name, u.avatar 
                FROM forum_comments fc 
                JOIN users u ON fc.user_id = u.id 
                WHERE fc.post_id = :post_id AND fc.parent_comment_id = :parent_comment_id AND fc.status = 'active' 
                ORDER BY fc.created_at ASC
            ");
            $stmt->execute([
                'post_id' => $post_id,
                'parent_comment_id' => $parent_comment_id
            ]);
            $child_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Điều chỉnh đường dẫn avatar
            foreach ($child_comments as &$comment) {
                if (!empty($comment['avatar'])) {
                    $comment['avatar'] = BASE_URL . $comment['avatar'];
                } else {
                    $comment['avatar'] = BASE_URL . 'assets/images/user_avatar.jpg';
                }
            }

            echo json_encode([
                'status' => 'success',
                'child_comments' => $child_comments
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error fetching child comments: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

$conn = null;
exit();
?>