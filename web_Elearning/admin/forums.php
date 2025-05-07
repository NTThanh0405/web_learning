<?php
// File: admin/forums.php
require_once dirname(__DIR__, 1) . '/config/database.php';
require_once dirname(__DIR__, 1) . '/config/config.php';
require_once dirname(__DIR__, 1) . '/includes/auth_functions.php';

// Only allow admin access
redirectIfNotLoggedIn();
redirectIfNotRole('admin');

$user = getCurrentUser();
$conn = getDBConnection();
$errors = [];
$success = false;

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    logout();
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Handle forum post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_forum_post') {
    $postId = $_POST['post_id'] ?? null;
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM forum_comments WHERE post_id = :post_id");
        $stmt->execute(['post_id' => $postId]);
        $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = :post_id");
        $stmt->execute(['post_id' => $postId]);
        $conn->commit();
        $success = true;
        $successMessage = "Bài đăng diễn đàn đã được xóa thành công!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors[] = "Lỗi khi xóa bài đăng: " . $e->getMessage();
    }
}

// Fetch forum posts
$forumPostsStmt = $conn->prepare("
    SELECT fp.*, u.full_name as user_name 
    FROM forum_posts fp 
    JOIN users u ON fp.user_id = u.id 
    WHERE fp.status = 'active'
    ORDER BY fp.created_at DESC
");
$forumPostsStmt->execute();
$forumPosts = $forumPostsStmt->fetchAll(PDO::FETCH_ASSOC);

$conn = null;
?>

    <style>
        .navbar-dark {
            background-color: #007bff !important;
        }
        .section-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            margin-top: 30px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        /* Consistent table layout for forum posts */
        .forum-table {
            table-layout: fixed;
            width: 100%;
        }
        .forum-table th:nth-child(1), .forum-table td:nth-child(1) { /* Tiêu đề */
            width: 20%;
        }
        .forum-table th:nth-child(2), .forum-table td:nth-child(2) { /* Nội dung */
            width: 40%;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .forum-table th:nth-child(3), .forum-table td:nth-child(3) { /* Người đăng */
            width: 15%;
        }
        .forum-table th:nth-child(4), .forum-table td:nth-child(4) { /* Ngày đăng */
            width: 15%;
            text-align: center;
        }
        .forum-table th:nth-child(5), .forum-table td:nth-child(5) { /* Hành động */
            width: 10%;
            text-align: center;
        }
    </style>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- List of forum posts -->
        <div class="section-title">Danh sách bài đăng diễn đàn</div>
        <table class="table table-bordered forum-table">
            <thead>
                <tr>
                    <th>Tiêu đề</th>
                    <th>Nội dung</th>
                    <th>Người đăng</th>
                    <th>Ngày đăng</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($forumPosts)): ?>
                    <?php foreach ($forumPosts as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                            <td><?php echo htmlspecialchars($post['content']); ?></td>
                            <td><?php echo htmlspecialchars($post['user_name']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa bài đăng này?');">
                                    <input type="hidden" name="action" value="delete_forum_post">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Không có bài đăng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $successMessage ?? "Thao tác thành công!"; ?></div>
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
    </div>