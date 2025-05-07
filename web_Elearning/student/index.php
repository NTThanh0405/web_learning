<?php
ob_start(); // Bắt đầu output caching

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Chỉ cho phép sinh viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('student');

$user = getCurrentUser();

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('user_id', '', time() - 3600, "/");
    setcookie('role', '', time() - 3600, "/");
    header("Location: " . BASE_URL . "auth/login.php");
    ob_end_flush();
    exit();
}

// Lấy kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Xóa thông báo quá 24 ngày
try {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < NOW() - INTERVAL 24 DAY AND user_id = :user_id");
    $stmt->execute(['user_id' => $user['id']]);
} catch (PDOException $e) {
    error_log("Error deleting old notifications: " . $e->getMessage());
}

// Lấy số lượng thông báo chưa đọc
$unread_count = countUnreadNotifications($user['id']);

// Định nghĩa ánh xạ giữa category và tên ngành học
$categories = [
    'all' => 'Tất cả',
    'cong_nghe_thong_tin' => 'Công nghệ thông tin',
    'triet_hoc' => 'Triết học',
    'chinh_tri' => 'Chính trị',
    'luat' => 'Luật',
    'kinh_te' => 'Kinh tế',
    'ky_thuat' => 'Kỹ thuật'
];

// Lấy dữ liệu khóa học
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.title, c.description, c.thumbnail, c.category, 
               u.full_name AS teacher_name, 
               ce.status AS enrollment_status,
               COUNT(ce2.student_id) AS student_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.student_id = :student_id
        LEFT JOIN course_enrollments ce2 ON c.id = ce2.course_id AND ce2.status = 'approved'
        WHERE c.status = 'active' 
        AND u.role = 'teacher'
        GROUP BY c.id, c.title, c.description, c.thumbnail, c.category, u.full_name, ce.status
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(['student_id' => $user['id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// Lấy thông báo khóa học từ giáo viên
try {
    $stmt = $conn->prepare("
        SELECT n.*, u.full_name AS sender_name, u.avatar 
        FROM notifications n 
        JOIN users u ON n.sender_id = u.id 
        WHERE n.user_id = :user_id 
        AND n.type = 'course' 
        AND u.role = 'teacher'
        ORDER BY n.created_at DESC
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $course_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching course notifications: " . $e->getMessage());
    $course_notifications = [];
}

// Xác định tab hiện tại
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'courses';

// Đóng kết nối
$conn = null;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ Sinh viên - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .notification-bell {
            position: relative;
            cursor: pointer;
        }
        .notification-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
        }
        .nav-tabs .nav-link {
            color: #0066cc;
            border-radius: 5px;
            margin-right: 5px;
            cursor: pointer;
        }
        .nav-tabs .nav-link.active {
            background-color: #0066cc;
            color: white;
        }
        .nav-tabs .nav-link:hover {
            background-color: #e6f3ff;
        }
        .course-recommendations {
            position: relative;
            padding: 10px 0;
            margin: 20px;
            background-color: #e6f0ff;
            border-radius: 10px;
        }
        .course-slider {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 10px;
            max-height: 650px;
            overflow-x: auto;
            scroll-behavior: smooth;
        }
        .course-item {
            flex: 0 0 calc(25% - 15px);
            max-width: calc(25% - 15px);
            min-width: 200px;
            margin-bottom: 15px;
        }
        .course-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .course-card:hover {
            transform: scale(1.05);
        }
        .course-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .course-card-body {
            padding: 10px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .course-card-body p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .course-card-body .badge {
            background-color: #28a745;
            color: #fff;
        }
        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .register-link {
            color: #0066cc;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }
        .register-link.enrolled {
            cursor: default;
        }
        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .nav-arrow:hover {
            opacity: 1;
        }
        .nav-arrow.prev {
            left: 10px;
        }
        .nav-arrow.next {
            right: 10px;
        }
        .nav-arrow.hidden {
            display: none;
        }
        .course-notifications {
            max-height: 600px;
            overflow-y: auto;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .course-card {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .course-card img {
            margin-right: 10px;
        }
        .course-card-body p {
            margin: 0;
            font-size: 13px;
        }
        .course-card-body small {
            font-size: 11px;
        }
    </style>
</head>
<body class="dashboard">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="EPU Logo">
            </div>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=courses" class="<?php echo $tab === 'courses' ? 'active' : ''; ?>"><i class="fas fa-book"></i> Khóa học</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=groups" class="<?php echo $tab === 'groups' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Nhóm học</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=study" class="<?php echo $tab === 'study' ? 'active' : ''; ?>"><i class="fas fa-check-square"></i> Học tập</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=forums" class="<?php echo $tab === 'forums' ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Diễn đàn</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=notifications" class="<?php echo $tab === 'notifications' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Thông báo</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/index.php?tab=profile" class="<?php echo $tab === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user"></i> Tài khoản</a></li>
                <li class="logout"><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
            </ul>
        </div>

        <!-- Nội dung chính -->
        <div class="main-content">
            <!-- Thanh trên cùng -->
            <div class="top-bar">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Tìm kiếm khóa học, giáo viên...">
                    <div id="searchResults" class="search-results"></div>
                </div>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                    <span class="badge"><?php echo $user['role']; ?></span>
                    <img src="<?php echo $user['avatar'] ? BASE_URL . $user['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" alt="User Avatar">
                    <div class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-count"><?php echo $unread_count > 0 ? $unread_count : ''; ?></span>
                        <div class="notification-panel" id="notificationPanel">
                            <div class="notification-header">
                                <h2>Thông báo</h2>
                            </div>
                            <div class="notification-list" id="notificationList"></div>
                            <div class="show-all-notifications"><a href="<?php echo BASE_URL; ?>student/index.php?tab=notifications">Hiển thị tất cả thông báo</a></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nội dung động -->
            <?php if ($tab === 'courses'): ?>
                <div class="row">
                    <!-- Tabs lọc theo ngành -->
                    <div class="col-md-12">
                        <ul class="nav nav-tabs mt-3">
                            <?php foreach ($categories as $key => $name): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $key === 'all' ? 'active' : ''; ?>" 
                                       data-category="<?php echo htmlspecialchars($key); ?>">
                                       <?php echo htmlspecialchars($name); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Danh sách khóa học -->
                    <div class="col-md-9">
                        <div class="course-recommendations">
                            <?php if (empty($courses)): ?>
                                <p class="text-muted mt-3">Chưa có khóa học nào để hiển thị.</p>
                            <?php else: ?>
                                <div class="course-slider" id="course-slider">
                                    <?php foreach ($courses as $course): ?>
                                        <div class="course-item" data-category="<?php echo htmlspecialchars($course['category'] ?? ''); ?>">
                                            <div class="course-card" data-course-id="<?php echo $course['id']; ?>">
                                                <img src="<?php echo $course['thumbnail'] ? BASE_URL . $course['thumbnail'] : BASE_URL . 'assets/images/course_default.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($course['title']); ?>" class="course-image">
                                                <div class="course-card-body">
                                                    <p><?php echo htmlspecialchars($course['title']); ?></p>
                                                    <div class="course-footer">
                                                        <span class="badge">Khóa học</span>
                                                        <?php if ($course['enrollment_status'] === 'pending'): ?>
                                                            <span class="register-link enrolled" style="color: #6c757d;">Đã gửi đăng ký</span>
                                                        <?php elseif ($course['enrollment_status'] === 'approved'): ?>
                                                            <span class="register-link enrolled" style="color: #28a745;">Đã tham gia</span>
                                                        <?php elseif ($course['enrollment_status'] === 'rejected'): ?>
                                                            <span class="register-link" data-course-id="<?php echo $course['id']; ?>" style="color: #dc3545;">Gửi lại đăng ký</span>
                                                        <?php else: ?>
                                                            <span class="register-link" data-course-id="<?php echo $course['id']; ?>">Đăng ký</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Nút điều hướng -->
                                <button class="nav-arrow prev hidden" data-slider="course-slider">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="nav-arrow next hidden" data-slider="course-slider">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Danh sách thông báo khóa học -->
                    <div class="col-md-3">
                        <div class="course-notifications">
                            <h3>Thông báo của khóa học:</h3>
                            <?php if (empty($course_notifications)): ?>
                                <p>Không có thông báo khóa học nào.</p>
                            <?php else: ?>
                                <div class="notification-list">
                                    <?php foreach ($course_notifications as $notif): ?>
                                        <div class="course-card">
                                            <img src="<?php echo $notif['avatar'] ? BASE_URL . $notif['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                                                 alt="Avatar" style="width: 30px; height: 30px; border-radius: 50%;">
                                            <div class="course-card-body">
                                                <p><strong><?php echo htmlspecialchars($notif['sender_name']); ?>:</strong> <?php echo htmlspecialchars($notif['title']); ?></p>
                                                <p><?php echo htmlspecialchars($notif['content']); ?></p>
                                                <small class="text-muted"><?php echo htmlspecialchars($notif['created_at']); ?></small>
                                                <?php if (!empty($notif['related_id'])): ?>
                                                    <a href="<?php echo BASE_URL; ?>courses/view.php?course_id=<?php echo $notif['related_id']; ?>" class="badge badge-primary">Xem khóa học</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'groups'): ?>
                <?php include __DIR__ . '/groups.php'; ?>
            <?php elseif ($tab === 'study'): ?>
                <?php include __DIR__ . '/study.php'; ?>
            <?php elseif ($tab === 'forums'): ?>
                <?php include __DIR__ . '/../forums/create.php'; ?>
            <?php elseif ($tab === 'notifications'): ?>
                <?php include __DIR__ . '/notifications.php'; ?>
            <?php elseif ($tab === 'profile'): ?>
                <?php include __DIR__ . '/profile.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal để hiển thị thông tin khóa học -->
    <div class="modal fade" id="courseInfoModal" tabindex="-1" role="dialog" aria-labelledby="courseInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="courseInfoModalLabel">Thông tin khóa học</h5>
                </div>
                <div class="modal-body">
                    <p><strong>Tên giáo viên:</strong> <span id="teacherName"></span></p>
                    <p><strong>Tên môn:</strong> <span id="courseTitle"></span></p>
                    <p><strong>Mô tả:</strong> <span id="courseDescription"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Hàm khởi tạo sự kiện cho tab forums
        function initializeForumEvents() {
            console.log("Initializing forum events for student");

            $(document).off('click', '.create-post-btn').on('click', '.create-post-btn', function(e) {
                e.preventDefault();
                $('#createPostModal').modal('show');
            });

            $(document).off('click', '.toggle-comments').on('click', '.toggle-comments', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var postId = $(this).data('post-id');
                var $commentsSection = $('#comments-' + postId);
                $commentsSection.slideToggle(200);
            });

            $(document).off('click', '.show-child-comments').on('click', '.show-child-comments', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var commentId = $(this).data('comment-id');
                var postId = $(this).closest('.post-container').find('.comment-form').data('post-id');
                var $childComments = $('#child-comments-' + commentId);

                console.log('Show child comments clicked:', { commentId, postId });

                if ($childComments.is(':visible')) {
                    $childComments.slideUp(200);
                    $(this).text('Xem thêm bình luận (' + $childComments.find('.d-flex').length + ')');
                } else {
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>api/comments.php',
                        method: 'GET',
                        data: { 
                            parent_comment_id: commentId, 
                            post_id: postId,
                            action: 'get_child_comments' 
                        },
                        dataType: 'json',
                        beforeSend: function() {
                            console.log('Sending AJAX request for child comments:', { parent_comment_id: commentId, post_id: postId });
                        },
                        success: function(response) {
                            console.log('AJAX response:', response);
                            if (response.status === 'success') {
                                var commentsHtml = '';
                                response.child_comments.forEach(function(comment) {
                                    commentsHtml += `
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="${comment.avatar ? '<?php echo BASE_URL; ?>' + comment.avatar : '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
                                                 alt="Avatar" class="rounded-circle" style="width: 30px; height: 30px;">
                                            <div class="ml-2">
                                                <strong>${comment.full_name}</strong>
                                                <small class="text-muted">${new Date(comment.created_at).toLocaleString('vi-VN')}</small>
                                                <p>${comment.comment.replace(/\n/g, '<br>')}</p>
                                                <a href="#" class="reply-comment" 
                                                   data-comment-id="${comment.id}" 
                                                   data-post-id="${comment.post_id}" 
                                                   data-username="${comment.full_name}">Trả lời</a>
                                            </div>
                                        </div>
                                    `;
                                });
                                $childComments.html(commentsHtml);
                                $childComments.slideDown(200);
                                $(e.target).text('Ẩn bình luận');
                            } else {
                                console.error('Error loading child comments:', response.message);
                                alert('Lỗi khi tải bình luận con: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error loading child comments:', status, error, xhr.responseText);
                            alert('Lỗi khi tải bình luận con: ' + (xhr.responseText || 'Không thể kết nối đến server'));
                        }
                    });
                }
            });

            $(document).off('click', '.reply-comment').on('click', '.reply-comment', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var commentId = $(this).data('comment-id');
                var postId = $(this).data('post-id');
                var username = $(this).data('username');
                var $form = $('.comment-form[data-post-id="' + postId + '"]');
                var $textarea = $form.find('textarea');
                var $replyingTo = $form.find('.replying-to');
                var $replyingText = $replyingTo.find('.replying-text');

                $form.find('.parent-comment-id').val(commentId);
                $replyingText.text('Đang trả lời ' + username);
                $replyingTo.show();
                $textarea.val('@' + username + ' ').focus();
                $('#comments-' + postId).slideDown(200);
            });

            $(document).off('click', '.cancel-reply').on('click', '.cancel-reply', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $form = $(this).closest('.comment-form');
                var $textarea = $form.find('textarea');
                var $replyingTo = $form.find('.replying-to');

                $form.find('.parent-comment-id').val('');
                $replyingTo.hide().find('.replying-text').text('');
                $textarea.val('').focus();
            });

            $('.comment-form textarea').on('focus', function() {
                var $form = $(this).closest('.comment-form');
                var parentCommentId = $form.find('.parent-comment-id').val();
                if (!parentCommentId) {
                    $form.find('.replying-to').hide().find('.replying-text').text('');
                    if (!$(this).val().startsWith('@')) {
                        $(this).val('');
                    }
                }
            });

            $(document).off('click', '.show-all-comments').on('click', '.show-all-comments', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var postId = $(this).data('post-id');
                $.ajax({
                    url: '<?php echo BASE_URL; ?>api/comments.php',
                    method: 'GET',
                    data: { post_id: postId, action: 'get_all_parent_comments' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            var commentsHtml = '';
                            response.comments.forEach(function(comment) {
                                commentsHtml += `
                                    <div class="d-flex align-items-center mb-2 parent-comment" data-comment-id="${comment.id}">
                                        <img src="${comment.avatar ? '<?php echo BASE_URL; ?>' + comment.avatar : '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
                                             alt="Avatar" class="rounded-circle" style="width: 30px; height: 30px;">
                                        <div class="ml-2">
                                            <strong>${comment.full_name}</strong>
                                            <small class="text-muted">${new Date(comment.created_at).toLocaleString('vi-VN')}</small>
                                            <p>${comment.comment.replace(/\n/g, '<br>')}</p>
                                            <a href="#" class="reply-comment" data-comment-id="${comment.id}" data-post-id="${postId}" data-username="${comment.full_name}">Trả lời</a>
                                            ${comment.child_count > 0 ? `<a href="#" class="show-child-comments" data-comment-id="${comment.id}">Xem thêm bình luận (${comment.child_count})</a>` : ''}
                                        </div>
                                    </div>
                                    <div class="child-comments ml-5" id="child-comments-${comment.id}"></div>
                                `;
                            });
                            $('#comments-' + postId + ' .parent-comments').html(commentsHtml);
                            $('#comments-' + postId).slideDown(200);
                            $(e.target).hide();
                        } else {
                            console.error('Error loading parent comments:', response.message);
                            alert('Lỗi khi tải bình luận: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error loading parent comments:', status, error, xhr.responseText);
                        alert('Lỗi khi tải bình luận: ' + (xhr.responseText || 'Không thể kết nối đến server'));
                    }
                });
            });

            $('.comment-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var postId = $form.data('post-id');
                var formData = $form.serialize();

                $.ajax({
                    url: '<?php echo BASE_URL; ?>forums/comment.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $.ajax({
                                url: '<?php echo BASE_URL; ?>api/comments.php',
                                method: 'GET',
                                data: { post_id: postId, action: 'get_all_parent_comments' },
                                dataType: 'json',
                                success: function(commentResponse) {
                                    if (commentResponse.status === 'success') {
                                        var commentsHtml = '';
                                        commentResponse.comments.forEach(function(comment) {
                                            commentsHtml += `
                                                <div class="d-flex align-items-center mb-2 parent-comment" data-comment-id="${comment.id}">
                                                    <img src="${comment.avatar ? '<?php echo BASE_URL; ?>' + comment.avatar : '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
                                                         alt="Avatar" class="rounded-circle" style="width: 30px; height: 30px;">
                                                    <div class="ml-2">
                                                        <strong>${comment.full_name}</strong>
                                                        <small class="text-muted">${new Date(comment.created_at).toLocaleString('vi-VN')}</small>
                                                        <p>${comment.comment.replace(/\n/g, '<br>')}</p>
                                                        <a href="#" class="reply-comment" 
                                                           data-comment-id="${comment.id}" 
                                                           data-post-id="${postId}" 
                                                           data-username="${comment.full_name}">Trả lời</a>
                                                        ${comment.child_count > 0 ? `<a href="#" class="show-child-comments" data-comment-id="${comment.id}">Xem thêm bình luận (${comment.child_count})</a>` : ''}
                                                    </div>
                                                </div>
                                                <div class="child-comments ml-5" id="child-comments-${comment.id}"></div>
                                            `;
                                        });
                                        $('#comments-' + postId + ' .parent-comments').html(commentsHtml);
                                        $('#comments-' + postId).slideDown(200);
                                        var commentCount = commentResponse.comments.length;
                                        $('.toggle-comments[data-post-id="' + postId + '"]').html(`<i class="fas fa-comment"></i> ${commentCount} bình luận`);
                                    } else {
                                        console.error('Error reloading comments:', commentResponse.message);
                                        alert('Lỗi khi tải lại bình luận: ' + commentResponse.message);
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('AJAX error reloading comments:', status, error, xhr.responseText);
                                    alert('Lỗi khi tải lại bình luận: ' + (xhr.responseText || 'Không thể kết nối đến server'));
                                }
                            });

                            $form.find('.parent-comment-id').val('');
                            $form.find('.replying-to').hide().find('.replying-text').text('');
                            $form.find('textarea').val('');
                        } else {
                            console.error('Error submitting comment:', response.message);
                            alert('Lỗi khi gửi bình luận: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error submitting comment:', status, error, xhr.responseText);
                        alert('Lỗi khi gửi bình luận: ' + (xhr.responseText || 'Không thể kết nối đến server'));
                    }
                });
            });
        }

        // Xử lý tab lọc khóa học theo ngành
        $('.nav-tabs .nav-link').on('click', function(e) {
            e.preventDefault();
            $('.nav-tabs .nav-link').removeClass('active');
            $(this).addClass('active');

            var category = $(this).data('category');
            var $courses = $('.course-item');
            var $noCourses = $('.course-recommendations .text-muted');

            if (category === 'all') {
                $courses.show();
                if ($courses.length > 0) {
                    $noCourses.hide();
                } else {
                    $noCourses.show();
                }
            } else {
                $courses.hide();
                var $matchingCourses = $courses.filter('[data-category="' + category + '"]');
                $matchingCourses.show();
                if ($matchingCourses.length === 0) {
                    if ($noCourses.length === 0) {
                        $('.course-recommendations').prepend('<p class="text-muted mt-3">Chưa có khóa học nào để hiển thị.</p>');
                    } else {
                        $noCourses.show();
                    }
                } else {
                    $noCourses.hide();
                }
            }

            updateNavArrows();
        });

        // Cập nhật trạng thái nút điều hướng
        function updateNavArrows() {
            var $slider = $('#course-slider');
            var $prev = $slider.siblings('.nav-arrow.prev');
            var $next = $slider.siblings('.nav-arrow.next');
            var courseCount = $slider.find('.course-item:visible').length;
            var maxDisplay = 20; // 4 khóa x 5 hàng

            if (courseCount > maxDisplay) {
                $prev.removeClass('hidden');
                $next.removeClass('hidden');
            } else {
                $prev.addClass('hidden');
                $next.addClass('hidden');
            }
        }

        // Xử lý cuộn slider
        $('.nav-arrow').on('click', function() {
            var $slider = $('#' + $(this).data('slider'));
            var scrollAmount = $slider.width() * 0.8;
            var currentScroll = $slider.scrollLeft();

            if ($(this).hasClass('prev')) {
                $slider.animate({ scrollLeft: currentScroll - scrollAmount }, 300);
            } else {
                $slider.animate({ scrollLeft: currentScroll + scrollAmount }, 300);
            }
        });

        // Cập nhật số lượng thông báo chưa đọc
        function updateNotificationCount() {
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/notifications.php',
                type: 'POST',
                data: { action: 'get_unread_count' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('.notification-count').text(response.unread_count > 0 ? response.unread_count : '');
                        $('.notification-count').toggle(response.unread_count > 0);
                    }
                },
                error: function() {
                    console.error('Error fetching notification count');
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
                    if (response.status === 'success') {
                        let notificationList = $('#notificationList');
                        notificationList.empty();

                        if (response.notifications.length > 0) {
                            response.notifications.forEach(function(notif) {
                                let url = notif.url || '<?php echo BASE_URL; ?>student/index.php?tab=notifications';
                                let avatar = notif.avatar ? '<?php echo BASE_URL; ?>' + notif.avatar : '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg';
                                let unreadStyle = notif.is_read == 0 ? 'font-weight: bold; color: #004085;' : '';
                                notificationList.append(`
                                    <div class="notification-item" data-id="${notif.id}">
                                        <img src="${avatar}" alt="User">
                                        <a href="${url}" class="notification-link">
                                            <span class="notification-title" style="${unreadStyle}">${notif.title}</span>
                                            <span class="notification-message" style="${unreadStyle}">${notif.content}</span>
                                            <span class="notification-time">${notif.created_at}</span>
                                        </a>
                                    </div>
                                `);
                            });
                        } else {
                            notificationList.append('<div class="notification-item">Không có thông báo mới</div>');
                        }
                    }
                },
                error: function() {
                    console.error('Error fetching notifications');
                }
            });
        }

        // Hiển thị/ẩn panel thông báo và đánh dấu tất cả là đã đọc
        $('.notification-bell').on('click', function(e) {
            e.stopPropagation();
            let panel = $('#notificationPanel');
            if (panel.is(':visible')) {
                panel.slideUp(200);
            } else {
                $.ajax({
                    url: '<?php echo BASE_URL; ?>api/notifications.php',
                    type: 'POST',
                    data: { action: 'mark_all_as_read' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('.notification-count').text('').hide();
                            loadNotifications();
                            panel.slideDown(200);
                        }
                    },
                    error: function() {
                        console.error('Error marking all notifications as read');
                    }
                });
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
                            if (url && url !== '#') {
                                window.location.href = url;
                            }
                        }
                    },
                    error: function() {
                        console.error('Error marking notification as read');
                    }
                });
            }
        });

        // Ẩn panel khi nhấp ra ngoài
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.notification-bell').length && !$(e.target).closest('#notificationPanel').length) {
                $('#notificationPanel').slideUp(200);
            }
        });

        // Cập nhật tự động mỗi 30 giây
        setInterval(updateNotificationCount, 30000);
        updateNotificationCount();

        // Xử lý nút Đăng ký khóa học
        $(document).on('click', '.register-link:not(.enrolled)', function(e) {
            e.preventDefault();
            console.log('Nút Đăng ký được nhấn');
            var $registerLink = $(this);
            var courseId = $registerLink.data('course-id');

            console.log('courseId:', courseId, 'Type:', typeof courseId);

            if (!courseId || isNaN(courseId)) {
                console.error('Khóa học không hợp lệ:', courseId);
                alert('Khóa học không hợp lệ. Vui lòng thử lại.');
                return;
            }

            $.ajax({
                url: '<?php echo BASE_URL; ?>api/courses.php',
                method: 'POST',
                data: {
                    action: 'enroll',
                    course_id: courseId,
                    user_id: <?php echo $user['id']; ?>
                },
                dataType: 'json',
                beforeSend: function() {
                    console.log('Gửi yêu cầu AJAX: course_id=' + courseId);
                    $registerLink.text('Đang xử lý...').css('cursor', 'not-allowed');
                },
                success: function(response) {
                    console.log('Phản hồi AJAX:', response);
                    if (response.success) {
                        if (response.message.includes('đã được phê duyệt')) {
                            $registerLink.text('Đã tham gia')
                                        .css({ 'color': '#28a745', 'cursor': 'default' })
                                        .addClass('enrolled');
                        } else {
                            $registerLink.text('Đã gửi đăng ký')
                                        .css({ 'color': '#6c757d', 'cursor': 'default' })
                                        .addClass('enrolled');
                        }
                    } else {
                        console.error('Yêu cầu đăng ký thất bại:', response.message);
                        alert('Lỗi: ' + response.message);
                        // Khôi phục nút nếu bị từ chối hoặc lỗi
                        if (response.message.includes('từ chối')) {
                            $registerLink.text('Gửi lại đăng ký')
                                        .css({ 'color': '#dc3545', 'cursor': 'pointer' });
                        } else {
                            $registerLink.text('Đăng ký')
                                        .css({ 'color': '#0066cc', 'cursor': 'pointer' });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error, xhr.responseText);
                    alert('Lỗi kết nối đến server khi đăng ký khóa học. Vui lòng thử lại.');
                    $registerLink.text($registerLink.text().includes('Gửi lại') ? 'Gửi lại đăng ký' : 'Đăng ký')
                                 .css({ 'color': $registerLink.text().includes('Gửi lại') ? '#dc3545' : '#0066cc', 'cursor': 'pointer' });
                }
            });
        });

        // Xử lý nhấn vào hình ảnh khóa học để xem thông tin
        $('.course-image').on('click', function(e) {
            e.preventDefault();
            var courseId = $(this).closest('.course-card').data('course-id');

            if (!courseId || isNaN(courseId)) {
                alert('Khóa học không hợp lệ.');
                return;
            }

            $.ajax({
                url: '<?php echo BASE_URL; ?>api/courses.php?action=get_course_info',
                method: 'POST',
                data: { course_id: courseId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#teacherName').text(response.data.teacher_name || 'Không có thông tin');
                        $('#courseTitle').text(response.data.title || 'Không có tiêu đề');
                        $('#courseDescription').text(response.data.description || 'Không có mô tả');
                        $('#courseInfoModal').modal('show');
                    } else {
                        alert(response.message || 'Không thể lấy thông tin khóa học.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Lỗi khi lấy thông tin khóa học. Vui lòng thử lại.');
                }
            });
        });

        // Chức năng tìm kiếm
        $('#searchInput').on('keyup', function() {
            var query = $(this).val();
            if (query.length < 2) {
                $('#searchResults').hide();
                return;
            }
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/search.php',
                method: 'GET',
                data: { query: query, context: 'student' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var results = '';
                        response.data.courses.forEach(function(course) {
                            results += `<div>${course.title} - Khóa học</div>`;
                        });
                        response.data.teachers.forEach(function(teacher) {
                            results += `<div>${teacher.full_name} (${teacher.email}) - Giáo viên</div>`;
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

        // Khởi tạo sự kiện cho tab forums nếu đang ở tab này
        <?php if ($tab === 'forums'): ?>
            initializeForumEvents();
        <?php endif; ?>

        // Xử lý tìm kiếm trong tab groups
        <?php if ($tab === 'groups'): ?>
            $('.search-bar input').on('keyup', function() {
                var searchText = $(this).val().toLowerCase();
                $('.group-card').each(function() {
                    var groupName = $(this).find('p').text().toLowerCase();
                    $(this).toggle(groupName.includes(searchText));
                });
            });
        <?php endif; ?>

        // Khởi tạo trạng thái nút điều hướng khi tải trang
        updateNavArrows();
    });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Kết thúc caching và gửi output
?>