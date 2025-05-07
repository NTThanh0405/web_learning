<?php
// File: admin/list.php
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

// Handle course deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_course') {
    $courseId = $_POST['course_id'] ?? null;
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE course_id = :course_id");
        $stmt->execute(['course_id' => $courseId]);
        $stmt = $conn->prepare("DELETE FROM lessons WHERE course_id = :course_id");
        $stmt->execute(['course_id' => $courseId]);
        $stmt = $conn->prepare("DELETE FROM tests WHERE course_id = :course_id");
        $stmt->execute(['course_id' => $courseId]);
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = :course_id");
        $stmt->execute(['course_id' => $courseId]);
        $conn->commit();
        $success = true;
        $successMessage = "Khóa học đã được xóa thành công!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors[] = "Lỗi khi xóa khóa học: " . $e->getMessage();
    }
}

// Fetch groups
$groupsStmt = $conn->prepare("SELECT g.*, u.full_name as creator_name FROM groups g JOIN users u ON g.creator_id = u.id");
$groupsStmt->execute();
$groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses
$coursesStmt = $conn->prepare("SELECT c.*, u.full_name as teacher_name FROM courses c JOIN users u ON c.teacher_id = u.id");
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

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
        /* Consistent table layout for groups */
        .group-table {
            table-layout: fixed;
            width: 100%;
        }
        .group-table th:nth-child(1), .group-table td:nth-child(1) { /* Tên nhóm */
            width: 30%;
        }
        .group-table th:nth-child(2), .group-table td:nth-child(2) { /* Mô tả */
            width: 50%;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .group-table th:nth-child(3), .group-table td:nth-child(3) { /* Người tạo */
            width: 20%;
        }
        /* Consistent table layout for courses */
        .course-table {
            table-layout: fixed;
            width: 100%;
        }
        .course-table th:nth-child(1), .course-table td:nth-child(1) { /* Tên khóa học */
            width: 25%;
        }
        .course-table th:nth-child(2), .course-table td:nth-child(2) { /* Mô tả */
            width: 35%;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .course-table th:nth-child(3), .course-table td:nth-child(3) { /* Giáo viên */
            width: 20%;
        }
        .course-table th:nth-child(4), .course-table td:nth-child(4) { /* Trạng thái */
            width: 10%;
            text-align: center;
        }
        .course-table th:nth-child(5), .course-table td:nth-child(5) { /* Hành động */
            width: 10%;
            text-align: center;
        }
    </style>

<!-- List of groups -->
<div class="section-title">Danh sách nhóm học</div>
<table class="table table-bordered group-table">
    <thead>
        <tr>
            <th>Tên nhóm</th>
            <th>Mô tả</th>
            <th>Người tạo</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?php echo htmlspecialchars($group['name']); ?></td>
                    <td><?php echo htmlspecialchars($group['description'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($group['creator_name']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center">Không có nhóm học nào.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- List of courses -->
<div class="section-title">Danh sách khóa học</div>
<table class="table table-bordered course-table">
    <thead>
        <tr>
            <th>Tên khóa học</th>
            <th>Mô tả</th>
            <th>Giáo viên</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($courses)): ?>
            <?php foreach ($courses as $course): ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                    <td><?php echo htmlspecialchars($course['description'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                    <td><?php echo $course['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa khóa học này?');">
                            <input type="hidden" name="action" value="delete_course">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">Không có khóa học nào.</td>
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