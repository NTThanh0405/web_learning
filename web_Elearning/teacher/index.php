<?php
ob_start(); // Bắt đầu output buffering để tránh lỗi header

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Chỉ cho phép giáo viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();

// Kiểm tra và khởi tạo dữ liệu mặc định nếu $user không hợp lệ
if (!$user || !is_array($user)) {
    $pendingEnrollments = [];
    $courses = [];
    $groups = [];
    $unread_count = 0;
} else {
    $conn = getDBConnection();

    // Xóa thông báo quá 24 ngày
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < NOW() - INTERVAL 24 DAY AND user_id = :user_id");
        $stmt->execute(['user_id' => $user['id']]);
    } catch (PDOException $e) {
        // Lỗi được ghi log thay vì hiển thị trực tiếp
    }

    // Lấy danh sách sinh viên chờ duyệt
    try {
        $stmt = $conn->prepare("SELECT e.*, c.title AS course_title, u.full_name AS student_name 
                                FROM course_enrollments e 
                                JOIN courses c ON e.course_id = c.id 
                                JOIN users u ON e.student_id = u.id 
                                WHERE c.teacher_id = :teacher_id AND e.status = 'pending' LIMIT 5");
        $stmt->execute(['teacher_id' => $user['id']]);
        $pendingEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $pendingEnrollments = [];
    }

    // Lấy danh sách khóa học
    try {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE teacher_id = :teacher_id LIMIT 3");
        $stmt->execute(['teacher_id' => $user['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $courses = [];
    }

    // Lấy danh sách nhóm học
    try {
        $stmt = $conn->prepare("SELECT * FROM groups WHERE creator_id = :teacher_id LIMIT 3");
        $stmt->execute(['teacher_id' => $user['id']]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $groups = [];
    }

    // Lấy số lượng thông báo chưa đọc
    $unread_count = countUnreadNotifications($user['id']);

    $conn = null;
}

// Xử lý logout
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('user_id', '', time() - 3600, "/");
    setcookie('role', '', time() - 3600, "/");
    header("Location: " . BASE_URL . "auth/login.php");
    ob_end_flush();
    exit();
}

// Xử lý gửi thông báo đến nhóm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $group_id = $_POST['group_id'];
    $message = trim($_POST['message']);
    $title = "Thông báo từ nhóm"; // Tiêu đề mặc định

    try {
        createGroupNotification($group_id, $user['id'], $title, $message, 'group');
        $success = "Thông báo đã được gửi thành công đến sinh viên trong nhóm.";
    } catch (PDOException $e) {
        $error = "Lỗi khi gửi thông báo: " . $e->getMessage();
    }
}

// Xác định tab hiện tại và post_id (nếu có)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

// Xử lý logic của courses và lessons trước khi xuất HTML
if ($tab === 'courses') {
    require_once __DIR__ . '/courses.php';
    handleCourseActions($user);
} elseif ($tab === 'lessons') {
    require_once __DIR__ . '/lessons.php';
    handleLessonActions($user);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ Giáo viên - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/teacher.css">
    <style>
        /* Notification Panel */
        .notification-panel {
            display: none;
            position: absolute;
            top: 45px;
            right: 0;
            width: 300px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1500;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .notification-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .notification-item .notification-link {
            flex-grow: 1;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        .notification-title {
            display: block;
            font-weight: bold;
        }
        .notification-message {
            display: block;
            color: #555;
        }
        .notification-time {
            display: block;
            font-size: 12px;
            color: #888;
        }
        .notification-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }
        .notification-link:hover {
            color: #0066cc;
        }
        .notification-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #6f42c1; /* Màu tím giống hình */
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            display: none; /* Ẩn khi không có thông báo */
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        .notification-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .show-all-notifications {
            text-align: center;
            padding: 10px;
            cursor: pointer;
            color: #0066cc;
        }
        .show-all-notifications:hover {
            background: #f8f9fa;
        }
        /* Search styles */
        .search-bar {
            position: relative;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .search-results div {
            padding: 8px;
            cursor: pointer;
        }
        .search-results div:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body class="dashboard">
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="logo">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="EPU Logo">
            </div>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php" class="<?php echo $tab === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Trang chủ</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses" class="<?php echo $tab === 'courses' ? 'active' : ''; ?>"><i class="fas fa-book"></i> Quản lý khóa học</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=groups" class="<?php echo $tab === 'groups' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Quản lý nhóm học</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=enrollments" class="<?php echo $tab === 'enrollments' ? 'active' : ''; ?>"><i class="fas fa-user-check"></i> Duyệt sinh viên</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=xem-diem" class="<?php echo $tab === 'xem-diem' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Xem điểm sinh viên</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=forums" class="<?php echo $tab === 'forums' ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Diễn đàn</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=notifications" class="<?php echo $tab === 'notifications' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Thông báo</a></li>
                <li><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=profile" class="<?php echo $tab === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Tài khoản</a></li>
                <li class="logout"><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Tìm kiếm sinh viên, khóa học, nhóm học...">
                    <div id="searchResults" class="search-results"></div>
                </div>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user['full_name'] ?? 'Unknown'); ?></span>
                    <span class="badge"><?php echo htmlspecialchars($user['role'] ?? 'teacher'); ?></span>
                    <img src="<?php echo ($user['avatar'] ?? false) ? BASE_URL . $user['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" alt="User Avatar">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count"><?php echo $unread_count; ?></span>
                        <div class="notification-panel" id="notificationPanel">
                            <div class="notification-header">
                                <h2>Thông báo</h2>
                            </div>
                            <div class="notification-list" id="notificationList"></div>
                            <div class="show-all-notifications"><a href="<?php echo BASE_URL; ?>teacher/index.php?tab=notifications">Hiển thị tất cả thông báo</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hiển thị thông báo lỗi hoặc thành công -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Nội dung theo tab -->
            <?php if ($tab === 'dashboard'): ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="teacher-action-card">
                            <h3>Tạo khóa học mới</h3>
                            <p>Tạo một khóa học mới để chia sẻ kiến thức với sinh viên.</p>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses&action=create" class="btn btn-primary">Tạo khóa học</a>
                        </div>
                        <div class="teacher-action-card">
                            <h3>Gửi thông báo đến nhóm</h3>
                            <p>Chọn nhóm học để gửi thông báo quan trọng.</p>
                            <form action="" method="POST">
                                <div class="form-group">
                                    <label for="group_id">Chọn nhóm học:</label>
                                    <select name="group_id" id="group_id" class="form-control" required>
                                        <option value="">-- Chọn nhóm --</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="message">Nội dung thông báo:</label>
                                    <textarea name="message" id="message" class="form-control" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="send_notification" class="btn btn-primary">Gửi thông báo</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="teacher-action-card">
                            <h3>Sinh viên chờ duyệt</h3>
                            <?php if (empty($pendingEnrollments)): ?>
                                <p>Không có sinh viên nào đang chờ duyệt.</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($pendingEnrollments as $enrollment): ?>
                                        <li class="list-group-item">
                                            <?php echo htmlspecialchars($enrollment['student_name']); ?> - Khóa: <?php echo htmlspecialchars($enrollment['course_title']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=enrollments" class="btn btn-primary mt-3">Xem chi tiết</a>
                        </div>
                        <div class="teacher-action-card">
                            <h3>Danh sách khóa học</h3>
                            <?php if (empty($courses)): ?>
                                <p>Bạn chưa tạo khóa học nào.</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($courses as $course): ?>
                                        <li class="list-group-item"><?php echo htmlspecialchars($course['title']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=courses" class="btn btn-primary mt-3">Xem tất cả</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'xem-diem'): ?>
                <?php include __DIR__ . '/grading.php'; ?>
            <?php elseif ($tab === 'courses'): ?>
                <?php renderCourses($user); ?>
            <?php elseif ($tab === 'lessons'): ?>
                <?php renderLessons($user); ?>
            <?php elseif ($tab === 'groups'): ?>
                <?php include __DIR__ . '/groups.php'; ?>
            <?php elseif ($tab === 'profile'): ?>
                <?php include __DIR__ . '/profile.php'; ?>
            <?php elseif ($tab === 'enrollments'): ?>
                <?php include __DIR__ . '/enrollments.php'; ?>
            <?php elseif ($tab === 'forums'): ?>
                <?php include __DIR__ . '/../forums/create.php'; ?>
            <?php elseif ($tab === 'notifications'): ?>
                <?php include __DIR__ . '/notifications.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Cập nhật số lượng thông báo chưa đọc
        function updateNotificationCount() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/notifications.php',
                type: 'POST',
                data: { action: 'get_unread_count' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('.notification-count').text(response.unread_count);
                        $('.notification-count').toggle(response.unread_count > 0);
                    } else {
                        console.log('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error fetching notification count: ' + error);
                }
            });
        }

        // Đánh dấu tất cả thông báo là đã đọc
        function markAllNotificationsAsRead() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/notifications.php',
                type: 'POST',
                data: { action: 'mark_all_as_read' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        updateNotificationCount(); // Cập nhật lại số lượng thông báo chưa đọc
                    } else {
                        console.log('Error marking all notifications as read: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error marking all notifications as read: ' + error);
                }
            });
        }

        // Tải danh sách 5 thông báo mới nhất
        function loadNotifications() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/notifications.php',
                type: 'POST',
                data: { action: 'get_notifications', limit: 5 },
                dataType: 'json',
                success: function(response) {
                    console.log('API response:', response);
                    let notificationList = $('#notificationList');
                    notificationList.empty();

                    if (response.status === 'success' && response.notifications.length > 0) {
                        response.notifications.forEach(function(notif) {
                            let url = '#';
                            switch (notif.type) {
                                case 'forum':
                                case 'comment_reply':
                                    url = notif.related_id 
                                        ? '<?php echo BASE_URL; ?>teacher/index.php?tab=forums&post_id=' + notif.related_id 
                                        : '<?php echo BASE_URL; ?>teacher/index.php?tab=forums';
                                    break;
                                case 'course':
                                    url = notif.related_id 
                                        ? '<?php echo BASE_URL; ?>courses/view.php?course_id=' + notif.related_id 
                                        : '<?php echo BASE_URL; ?>teacher/index.php?tab=courses';
                                    break;
                                case 'lesson':
                                    url = notif.related_id 
                                        ? '<?php echo BASE_URL; ?>lessons/view.php?lesson_id=' + notif.related_id 
                                        : '<?php echo BASE_URL; ?>teacher/index.php?tab=courses';
                                    break;
                                case 'group':
                                    url = notif.related_id 
                                        ? '<?php echo BASE_URL; ?>groups/view.php?group_id=' + notif.related_id 
                                        : '<?php echo BASE_URL; ?>teacher/index.php?tab=groups';
                                    break;
                                default:
                                    url = '#';
                            }
                            let avatar = notif.avatar ? '<?php echo BASE_URL; ?>' + notif.avatar : '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg';
                            let title = notif.title || 'Thông báo';
                            let content = notif.content || '';
                            let created_at = notif.created_at || '';
                            notificationList.append(`
                                <div class="notification-item" data-id="${notif.id}">
                                    <img src="${avatar}" alt="User Avatar">
                                    <a href="${url}" class="notification-link">
                                        <span class="notification-title">${title}</span>
                                        <span class="notification-message">${content}</span>
                                        <span class="notification-time">${created_at}</span>
                                    </a>
                                </div>
                            `);
                        });
                    } else {
                        notificationList.append('<div class="notification-item">Không có thông báo mới</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error fetching notifications: ' + error);
                    $('#notificationList').empty().append('<div class="notification-item">Lỗi khi tải thông báo</div>');
                }
            });
        }

        // Hiển thị/ẩn panel thông báo khi nhấp vào chuông
        $('.notification-bell').on('click', function(e) {
            e.stopPropagation();
            let panel = $('#notificationPanel');
            if (panel.is(':visible')) {
                panel.slideUp(200);
            } else {
                markAllNotificationsAsRead(); // Đánh dấu tất cả thông báo là đã đọc
                loadNotifications();
                panel.slideDown(200);
            }
        });

        // Đánh dấu đã đọc và chuyển hướng khi nhấp vào thông báo
        $(document).on('click', '.notification-item', function(e) {
            e.preventDefault();
            let notificationId = $(this).data('id');
            let url = $(this).find('.notification-link').attr('href');

            if (notificationId) {
                $.ajax({
                    url: '<?php echo BASE_URL; ?>api/notifications.php',
                    type: 'POST',
                    data: { action: 'mark_as_read', notification_id: notificationId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            updateNotificationCount();
                            if (url !== '#') {
                                window.location.href = url;
                            }
                        } else {
                            console.log('Error marking notification: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error marking notification as read: ' + error);
                    }
                });
            } else if (url !== '#') {
                window.location.href = url;
            }
        });

        // Ẩn panel khi nhấp ra ngoài
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.notification-bell').length && !$(e.target).closest('.notification-panel').length) {
                $('#notificationPanel').slideUp(200);
            }
        });

        // Cập nhật tự động mỗi 30 giây
        setInterval(updateNotificationCount, 30000);
        updateNotificationCount();

        // Tìm kiếm
        $('#searchInput').on('keyup', function() {
            var query = $(this).val();
            if (query.length < 2) {
                $('#searchResults').hide();
                return;
            }
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/search.php',
                method: 'GET',
                data: { query: query, context: 'teacher' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var results = '';
                        response.data.students.forEach(function(student) {
                            results += `<div>${student.full_name} (${student.email}) - Sinh viên</div>`;
                        });
                        response.data.courses.forEach(function(course) {
                            results += `<div>${course.title} - Khóa học</div>`;
                        });
                        response.data.groups.forEach(function(group) {
                            results += `<div>${group.name} - Nhóm học</div>`;
                        });
                        $('#searchResults').html(results || '<div>Không tìm thấy kết quả</div>').show();
                    } else {
                        $('#searchResults').html('<div>' + response.message + '</div>').show();
                    }
                },
                error: function() {
                    $('#searchResults').html('<div>Lỗi tìm kiếm</div>').show();
                }
            });
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-bar').length) {
                $('#searchResults').hide();
            }
        });

        // Xử lý nút tạo bài đăng trong tab forums
        <?php if ($tab === 'forums'): ?>
            console.log("jQuery is loaded and ready for forums tab");
            $('.create-post-btn').on('click', function() {
                console.log("CREATE POST button clicked");
                $('#createPostModal').modal('show');
            });
            $('#createPostModal').on('show.bs.modal', function() {
                console.log("Modal is being shown");
            });
        <?php endif; ?>
    });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Kết thúc buffering và gửi output
?>