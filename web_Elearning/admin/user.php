<?php
// File: admin/user.php
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

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = $_POST['user_id'] ?? null;
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $targetRole = $stmt->fetchColumn();

        $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE student_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $stmt = $conn->prepare("DELETE FROM courses WHERE teacher_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $stmt = $conn->prepare("DELETE FROM groups WHERE creator_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id AND role != 'admin'");
        $stmt->execute(['user_id' => $userId]);
        $conn->commit();
        $success = true;
        if ($targetRole === 'teacher') {
            $successMessage = "Giáo viên đã được xóa thành công!";
        } elseif ($targetRole === 'student') {
            $successMessage = "Sinh viên đã được xóa thành công!";
        } else {
            $successMessage = "Tài khoản đã được xóa thành công!";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $errors[] = "Lỗi khi xóa tài khoản: " . $e->getMessage();
    }
}

// Fetch teachers
$teachersStmt = $conn->prepare("
    SELECT u.*, COUNT(c.id) as course_count 
    FROM users u 
    LEFT JOIN courses c ON u.id = c.teacher_id 
    WHERE u.role = 'teacher' 
    GROUP BY u.id
");
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch students
$studentsStmt = $conn->prepare("
    SELECT u.*, COUNT(ce.course_id) as course_count 
    FROM users u 
    LEFT JOIN course_enrollments ce ON u.id = ce.student_id 
    WHERE u.role = 'student' 
    GROUP BY u.id
");
$studentsStmt->execute();
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        /* Ensure consistent table layout */
        .user-table {
            table-layout: fixed;
            width: 100%;
        }
        .user-table th, .user-table td {
            padding: 12px;
            text-align: left;
        }
        /* Define column widths */
        .user-table th:nth-child(1), .user-table td:nth-child(1) { /* Name */
            width: 25%;
        }
        .user-table th:nth-child(2), .user-table td:nth-child(2) { /* Email */
            width: 25%;
        }
        .user-table th:nth-child(3), .user-table td:nth-child(3) { /* Password */
            width: 30%;
            overflow-wrap: break-word; /* Allow long passwords to wrap */
            white-space: normal; /* Enable wrapping */
        }
        .user-table th:nth-child(4), .user-table td:nth-child(4) { /* Course count */
            width: 10%;
            text-align: center;
        }
        .user-table th:nth-child(5), .user-table td:nth-child(5) { /* Action */
            width: 10%;
            text-align: center;
        }
    </style>

<!-- List of teachers -->
<div class="section-title">Danh sách giáo viên</div>
<table class="table table-bordered user-table">
    <thead>
        <tr>
            <th>Tên giáo viên</th>
            <th>Email</th>
            <th>Mật khẩu</th>
            <th>Số khóa học</th>
            <th>Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($teachers)): ?>
            <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                    <td><?php echo htmlspecialchars($teacher['password']); ?></td>
                    <td><?php echo $teacher['course_count']; ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa giáo viên này?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $teacher['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">Không có giáo viên nào.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- List of students -->
<div class="section-title">Danh sách sinh viên</div>
<table class="table table-bordered user-table">
    <thead>
        <tr>
            <th>Tên sinh viên</th>
            <th>Email</th>
            <th>Mật khẩu</th>
            <th>Số khóa học tham gia</th>
            <th>Hành động</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($students)): ?>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td><?php echo htmlspecialchars($student['password']); ?></td>
                    <td><?php echo $student['course_count']; ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa sinh viên này?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-center">Không có sinh viên nào.</td>
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