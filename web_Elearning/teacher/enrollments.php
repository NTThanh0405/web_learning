<?php
ob_start(); // Start output buffering
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Chỉ cho phép giáo viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();

// Kiểm tra nếu $user không hợp lệ, gán dữ liệu rỗng
if (!$user || !is_array($user)) {
    $pendingEnrollments = [];
} else {
    $conn = getDBConnection();

    try {
        $stmt = $conn->prepare("SELECT e.*, c.title AS course_title, u.full_name AS student_name 
                                FROM course_enrollments e 
                                JOIN courses c ON e.course_id = c.id 
                                JOIN users u ON e.student_id = u.id 
                                WHERE c.teacher_id = :teacher_id AND e.status = 'pending'");
        $stmt->execute(['teacher_id' => $user['id']]);
        $pendingEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pendingEnrollments = [];
    }

    // Xử lý duyệt sinh viên
    if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
        $enrollmentId = $_GET['id'];
        try {
            $stmt = $conn->prepare("UPDATE course_enrollments SET status = 'approved' WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $enrollmentId]);
            header("Location: " . BASE_URL . "teacher/index.php?tab=enrollments&success=approve");
            exit();
        } catch (PDOException $e) {
            header("Location: " . BASE_URL . "teacher/index.php?tab=enrollments&error=approve");
            exit();
        }
    }

    // Xử lý từ chối sinh viên
    if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
        $enrollmentId = $_GET['id'];
        try {
            $stmt = $conn->prepare("UPDATE course_enrollments SET status = 'rejected' WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $enrollmentId]);
            header("Location: " . BASE_URL . "teacher/index.php?tab=enrollments&success=reject");
            exit();
        } catch (PDOException $e) {
            header("Location: " . BASE_URL . "teacher/index.php?tab=enrollments&error=reject");
            exit();
        }
    }

    $conn = null;
}
?>

<div class="container mt-5">
    <h2>Duyệt Sinh Viên Chờ Đăng Ký</h2>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'approve'): ?>
        <div class="alert alert-success">Duyệt sinh viên thành công!</div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'reject'): ?>
        <div class="alert alert-success">Đã từ chối sinh viên thành công!</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'approve'): ?>
        <div class="alert alert-danger">Có lỗi xảy ra khi duyệt sinh viên!</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'reject'): ?>
        <div class="alert alert-danger">Có lỗi xảy ra khi từ chối sinh viên!</div>
    <?php endif; ?>

    <?php if (empty($pendingEnrollments)): ?>
        <p>Không có sinh viên nào đang chờ duyệt.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Tên Sinh Viên</th>
                    <th>Khóa Học</th>
                    <th>Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingEnrollments as $enrollment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=enrollments&action=approve&id=<?php echo $enrollment['id']; ?>" class="btn btn-sm btn-success mr-2">Duyệt</a>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=enrollments&action=reject&id=<?php echo $enrollment['id']; ?>" class="btn btn-sm btn-danger">Từ chối</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
ob_end_flush(); // Send buffered output
?>