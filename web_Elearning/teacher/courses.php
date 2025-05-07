<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Hàm xử lý logic trước khi xuất HTML
function handleCourseActions($user) {
    $conn = getDBConnection();
    $action = $_GET['action'] ?? '';
    $errors = [];
    $success = false;

    // Mảng ánh xạ category sang tên ngành
    $categories = [
        'cong_nghe_thong_tin' => 'Công nghệ thông tin',
        'triet_hoc' => 'Triết học',
        'chinh_tri' => 'Chính trị',
        'luat' => 'Luật',
        'kinh_te' => 'Kinh tế',
        'ky_thuat' => 'Kỹ thuật'
    ];

    // Xử lý tạo khóa học mới
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'cong_nghe_thong_tin';
        $status = $_POST['status'] ?? 'active';

        if (empty($title)) {
            $errors[] = "Tiêu đề khóa học là bắt buộc.";
        }

        $thumbnail = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['thumbnail'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024;

            if ($file['error'] === UPLOAD_ERR_OK) {
                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = "Chỉ chấp nhận file ảnh JPG, PNG hoặc GIF.";
                } elseif ($file['size'] > $maxSize) {
                    $errors[] = "Kích thước file ảnh không được vượt quá 5MB.";
                } else {
                    $uploadDir = __DIR__ . '/../assets/uploads/courses/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $thumbnail = 'course_' . time() . '.' . $ext;
                    $uploadPath = $uploadDir . $thumbnail;

                    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $errors[] = "Không thể tải file ảnh lên.";
                    } else {
                        $thumbnail = "assets/uploads/courses/" . $thumbnail;
                    }
                }
            } else {
                $errors[] = "Có lỗi xảy ra khi tải ảnh lên.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO courses (title, description, teacher_id, thumbnail, category, status)
                    VALUES (:title, :description, :teacher_id, :thumbnail, :category, :status)
                ");
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'teacher_id' => $user['id'],
                    'thumbnail' => $thumbnail,
                    'category' => $category,
                    'status' => $status
                ]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi lưu khóa học: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && isset($_GET['course_id'])) {
        $courseId = $_GET['course_id'];
        
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE course_id = :course_id");
            $stmt->execute(['course_id' => $courseId]);

            $stmt = $conn->prepare("SELECT thumbnail FROM courses WHERE id = :course_id AND teacher_id = :teacher_id");
            $stmt->execute(['course_id' => $courseId, 'teacher_id' => $user['id']]);
            $thumbnail = $stmt->fetchColumn();

            $stmt = $conn->prepare("DELETE FROM courses WHERE id = :course_id AND teacher_id = :teacher_id");
            $stmt->execute(['course_id' => $courseId, 'teacher_id' => $user['id']]);

            if ($thumbnail && !empty($thumbnail)) {
                $thumbnailPath = __DIR__ . '/../' . $thumbnail;
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }

            $conn->commit();
            header("Location: " . BASE_URL . "teacher/index.php?tab=courses&success=" . urlencode("Khóa học đã được xóa thành công!"));
            exit();
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Lỗi khi xóa khóa học: " . $e->getMessage();
        }
    } elseif ($action === 'remove_student' && isset($_GET['course_id']) && isset($_GET['student_id'])) {
        $courseId = $_GET['course_id'];
        $studentId = $_GET['student_id'];

        try {
            $stmt = $conn->prepare("SELECT teacher_id FROM courses WHERE id = :course_id");
            $stmt->execute(['course_id' => $courseId]);
            $courseTeacherId = $stmt->fetchColumn();

            if ($courseTeacherId === $user['id']) {
                $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE course_id = :course_id AND student_id = :student_id AND status = 'approved'");
                $stmt->execute(['course_id' => $courseId, 'student_id' => $studentId]);
                header("Location: " . BASE_URL . "teacher/index.php?tab=courses&action=view&course_id=" . $courseId . "&success=" . urlencode("Sinh viên đã được xóa khỏi khóa học!"));
                exit();
            } else {
                $errors[] = "Bạn không có quyền xóa sinh viên khỏi khóa học này.";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi xóa sinh viên: " . $e->getMessage();
        }
    } elseif ($action === 'add_announcement' && isset($_GET['course_id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $courseId = $_GET['course_id'];
        $announcement = trim($_POST['announcement'] ?? '');

        if (empty($announcement)) {
            $errors[] = "Nội dung thông báo không được để trống.";
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE courses
                    SET announcement = :announcement
                    WHERE id = :course_id AND teacher_id = :teacher_id
                ");
                $stmt->execute([
                    'announcement' => $announcement,
                    'course_id' => $courseId,
                    'teacher_id' => $user['id']
                ]);
                header("Location: " . BASE_URL . "teacher/index.php?tab=courses&action=view&course_id=" . $courseId . "&success=" . urlencode("Thông báo đã được thêm thành công!"));
                exit();
            } catch (PDOException $e) {
                $errors[] = "Lỗi khi thêm thông báo: " . $e->getMessage();
            }
        }
    }

    $GLOBALS['course_data'] = [
        'errors' => $errors,
        'success' => $success,
        'categories' => $categories,
        'conn' => $conn
    ];
}

// Hàm hiển thị giao diện
function renderCourses($user) {
    $data = $GLOBALS['course_data'];
    $errors = $data['errors'];
    $success = $data['success'];
    $categories = $data['categories'];
    $conn = $data['conn'];
    $action = $_GET['action'] ?? '';
    $courseId = $_GET['course_id'] ?? null;

    if ($action === 'view' && $courseId) {
        try {
            $stmt = $conn->prepare("
                SELECT c.*, COUNT(ce.student_id) as student_count
                FROM courses c
                LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'approved'
                WHERE c.id = :course_id AND c.teacher_id = :teacher_id
            ");
            $stmt->execute(['course_id' => $courseId, 'teacher_id' => $user['id']]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $course = null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT u.id, u.full_name
                FROM course_enrollments ce
                JOIN users u ON ce.student_id = u.id
                WHERE ce.course_id = :course_id AND ce.status = 'approved'
            ");
            $stmt->execute(['course_id' => $courseId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $students = [];
        }
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT c.*, COUNT(ce.student_id) as student_count
                FROM courses c
                LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.status = 'approved'
                WHERE c.teacher_id = :teacher_id
                GROUP BY c.id
            ");
            $stmt->execute(['teacher_id' => $user['id']]);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $courses = [];
        }
    }

    $conn = null;
?>

<div class="container mt-5">
    <?php if ($action === 'create'): ?>
        <h2>Tạo Khóa Học Mới</h2>
        <?php if ($success): ?>
            <div class="alert alert-success">
                Khóa học đã được tạo thành công! 
                <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses">Quay lại danh sách khóa học</a>
            </div>
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
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Tiêu đề khóa học:</label>
                <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Mô tả:</label>
                <textarea name="description" id="description" class="form-control" rows="5"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="category">Ngành:</label>
                <select name="category" id="category" class="form-control" required>
                    <?php foreach ($categories as $key => $name): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($_POST['category'] ?? 'cong_nghe_thong_tin') === $key ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="thumbnail">Hình ảnh đại diện:</label>
                <input type="file" name="thumbnail" id="thumbnail" class="form-control-file" accept="image/*">
                <small class="form-text text-muted">Chấp nhận JPG, PNG, GIF. Tối đa 5MB.</small>
            </div>
            <div class="form-group">
                <label for="status">Trạng thái:</label>
                <select name="status" id="status" class="form-control">
                    <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Tạo khóa học</button>
            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses" class="btn btn-secondary">Hủy</a>
        </form>
    <?php elseif ($action === 'view' && isset($_GET['course_id'])): ?>
        <?php if ($course): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Chi Tiết Khóa Học: <?php echo htmlspecialchars($course['title']); ?></h2>
                <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại
                </a>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
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
            <p><strong>Ngành:</strong> <?php echo htmlspecialchars($categories[$course['category']] ?? 'Không xác định'); ?></p>
            <p><strong>Mô tả:</strong> <?php echo htmlspecialchars($course['description'] ?? 'Không có mô tả'); ?></p>
            <p><strong>Thông báo:</strong> <?php echo htmlspecialchars($course['announcement'] ?? 'Không có thông báo'); ?></p>
            <p><strong>Số thành viên:</strong> <?php echo isset($course['student_count']) ? $course['student_count'] : 0; ?></p>

            <h3>Danh Sách Sinh Viên</h3>
            <?php if (empty($students)): ?>
                <p>Khóa học này chưa có sinh viên nào.</p>
            <?php else: ?>
                <ul class="list-group mb-3">
                    <?php foreach ($students as $student): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=remove_student&course_id=<?php echo $courseId; ?>&student_id=<?php echo htmlspecialchars($student['id']); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Bạn có chắc chắn muốn xóa sinh viên này khỏi khóa học?');">Xóa</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Đăng Nội Dung</h3>
            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=lessons&course_id=<?php echo $course['id']; ?>&action=create" class="btn btn-primary mb-3">Tạo Bài Học</a>

            <h3>Thêm Thông Báo</h3>
            <form method="POST" action="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=add_announcement&course_id=<?php echo $courseId; ?>">
                <div class="form-group">
                    <label for="announcement">Nội dung thông báo:</label>
                    <textarea name="announcement" id="announcement" class="form-control" rows="3" required><?php echo htmlspecialchars($course['announcement'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Cập nhật thông báo</button>
            </form>
        <?php else: ?>
            <p>Khóa học không tồn tại hoặc bạn không có quyền truy cập.</p>
        <?php endif; ?>
    <?php else: ?>
        <h2>Khóa Học</h2>
        <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=create" class="btn btn-primary mb-3">Tạo khóa học</a>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
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
        <?php if (empty($courses)): ?>
            <p>Bạn chưa tạo khóa học nào.</p>
        <?php else: ?>
            <div class="course-suggest-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-suggest-card">
                        <div class="course-image">
                            <img src="<?php echo !empty($course['thumbnail']) ? BASE_URL . htmlspecialchars($course['thumbnail']) : BASE_URL . 'assets/images/default_course.jpg'; ?>" alt="Course Image">
                        </div>
                        <div class="course-details">
                            <h5 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="course-category">
                                <strong>Ngành:</strong> <?php echo htmlspecialchars($categories[$course['category']] ?? 'Không xác định'); ?>
                            </p>
                            <p>
                                <i class="fas fa-users"></i> <?php echo isset($course['student_count']) ? $course['student_count'] : 0; ?>
                            </p>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=view&course_id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm mt-2">Xem chi tiết</a>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=delete&course_id=<?php echo $course['id']; ?>" class="btn btn-danger btn-sm mt-2" onclick="return confirm('Bạn có chắc chắn muốn xóa khóa học này?');">Xóa</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
}
?>