<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';
require_once __DIR__ . '/../includes/notifications.php';

// Kiểm tra đăng nhập
redirectIfNotLoggedIn();
$user = getCurrentUser();

// Danh sách ngành (tương tự courses.php)
$categories = [
    'all' => 'Tất cả',
    'cong_nghe_thong_tin' => 'Công nghệ thông tin',
    'triet_hoc' => 'Triết học',
    'chinh_tri' => 'Chính trị',
    'luat' => 'Luật',
    'kinh_te' => 'Kinh tế',
    'ky_thuat' => 'Kỹ thuật'
];

// Xử lý tạo bài đăng mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = $_POST['category'] ?? 'cong_nghe_thong_tin';
    $conn = getDBConnection();

    if (empty($title)) {
        $error = "Tiêu đề bài đăng là bắt buộc.";
    } elseif (empty($content)) {
        $error = "Nội dung bài đăng là bắt buộc.";
    } elseif (!array_key_exists($category, $categories)) {
        $error = "Ngành không hợp lệ.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO forum_posts (user_id, title, content, category, created_at)
                VALUES (:user_id, :title, :content, :category, NOW())
            ");
            $stmt->execute([
                'user_id' => $user['id'],
                'title' => $title,
                'content' => $content,
                'category' => $category
            ]);
            $success = "Bài đăng đã được tạo thành công.";
        } catch (PDOException $e) {
            $error = "Lỗi khi tạo bài đăng: " . $e->getMessage();
        }
    }
    $conn = null;
}

// Xử lý xóa bài đăng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT user_id FROM forum_posts WHERE id = :post_id");
    $stmt->execute(['post_id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post && $post['user_id'] == $user['id']) {
        try {
            $stmt = $conn->prepare("DELETE FROM forum_posts WHERE id = :post_id");
            $stmt->execute(['post_id' => $post_id]);
            $success = "Bài đăng đã được xóa thành công.";
        } catch (PDOException $e) {
            $error = "Lỗi khi xóa bài đăng: " . $e->getMessage();
        }
    } else {
        $error = "Bạn không có quyền xóa bài đăng này.";
    }
    $conn = null;
}

// Lấy danh sách bài đăng
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT fp.*, u.full_name, u.avatar, u.role,
           (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id = fp.id AND fc.parent_comment_id IS NULL) AS comment_count
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    ORDER BY fp.created_at DESC
");
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$conn = null;

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diễn đàn - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        .post-container { 
            display: flex; 
            background-color: #e6f3ff; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 20px; 
        }
        .post-avatar { 
            width: 60px; 
            height: 60px; 
            border-radius: 10px; 
            margin-right: 15px; 
        }
        .post-content { 
            flex-grow: 1; 
        }
        .post-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .post-header .badge { 
            background-color: #d1e7ff; 
            color: #000; 
            font-size: 14px; 
        }
        .post-title { 
            font-size: 18px; 
            font-weight: bold; 
            margin: 5px 0; 
        }
        .post-description { 
            margin: 5px 0; 
        }
        .post-category {
            color: #555;
            font-size: 14px;
            margin: 5px 0;
        }
        .post-actions { 
            display: flex; 
            gap: 10px; 
            margin-top: 10px; 
            align-items: center; 
        }
        .post-actions i { 
            color: #0066cc; 
            cursor: pointer; 
        }
        .create-post-btn { 
            background-color: #0066cc; 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 20px; 
            float: right; 
        }
        .create-post-btn:hover { 
            background-color: #005bb5; 
        }
        .delete-post-btn { 
            background-color: #dc3545; 
            color: white; 
            border: none; 
            padding: 5px 10px; 
            border-radius: 5px; 
            font-size: 14px; 
        }
        .delete-post-btn:hover { 
            background-color: #c82333; 
        }
        .comments { 
            display: none; 
            margin-top: 10px; 
        }
        .child-comments { 
            border-left: 2px solid #e0e0e0; 
            padding-left: 10px; 
            display: none; 
        }
        .reply-comment, .show-child-comments, .show-all-comments { 
            color: #0066cc; 
            font-size: 14px; 
            text-decoration: none; 
            margin-right: 10px; 
        }
        .reply-comment:hover, .show-child-comments:hover, .show-all-comments:hover { 
            text-decoration: underline; 
        }
        .comment-form .replying-to { 
            font-style: italic; 
            color: #555; 
            margin-bottom: 5px; 
            display: none; 
        }
        .cancel-reply { 
            color: #dc3545; 
            font-size: 14px; 
            text-decoration: none; 
            margin-left: 10px; 
        }
        .cancel-reply:hover { 
            text-decoration: underline; 
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            background-color: #e6f3ff;
            border-bottom: none;
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header with Title and Create Post Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Diễn đàn</h2>
            <button class="create-post-btn" data-toggle="modal" data-target="#createPostModal">Tạo bài đăng</button>
        </div>

        <!-- Tabs lọc theo ngành -->
        <ul class="nav nav-tabs mb-3">
            <?php foreach ($categories as $key => $name): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $key === 'all' ? 'active' : ''; ?>" 
                       data-category="<?php echo htmlspecialchars($key); ?>">
                       <?php echo htmlspecialchars($name); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Thông báo -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Danh sách bài đăng -->
        <?php if (empty($posts)): ?>
            <p class="text-muted">Chưa có bài đăng nào trong diễn đàn.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-container" data-category="<?php echo htmlspecialchars($post['category']); ?>">
                    <img src="<?php echo $post['avatar'] ? BASE_URL . $post['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                         alt="Avatar" class="post-avatar">
                    <div class="post-content">
                        <div class="post-header">
                            <div>
                                <span><?php echo htmlspecialchars($post['full_name']); ?></span>
                                <span class="badge"><?php echo htmlspecialchars($post['role']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?></small>
                        </div>
                        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                        <div class="post-description"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                        <div class="post-category">
                            <strong>Ngành:</strong> <?php echo htmlspecialchars($categories[$post['category']] ?? 'Không xác định'); ?>
                        </div>
                        <div class="post-actions">
                            <a href="#" class="toggle-comments" data-post-id="<?php echo $post['id']; ?>">
                                <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?> bình luận
                            </a>
                            <?php if ($post['user_id'] == $user['id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn xóa bài đăng này?');">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <button type="submit" name="delete_post" class="delete-post-btn">Xóa</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <!-- Phần bình luận -->
                        <div class="comments" id="comments-<?php echo $post['id']; ?>">
                            <!-- Bình luận cha -->
                            <div class="parent-comments">
                                <?php
                                $conn = getDBConnection();
                                $stmt = $conn->prepare("
                                    SELECT fc.*, u.full_name, u.avatar, 
                                           (SELECT COUNT(*) FROM forum_comments fcc WHERE fcc.parent_comment_id = fc.id) AS child_count
                                    FROM forum_comments fc
                                    JOIN users u ON fc.user_id = u.id
                                    WHERE fc.post_id = :post_id AND fc.parent_comment_id IS NULL
                                    ORDER BY fc.created_at DESC
                                    LIMIT 2
                                ");
                                $stmt->execute(['post_id' => $post['id']]);
                                $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($comments as $comment):
                                ?>
                                    <div class="d-flex align-items-center mb-2 parent-comment" data-comment-id="<?php echo $comment['id']; ?>">
                                        <img src="<?php echo $comment['avatar'] ? BASE_URL . $comment['avatar'] : BASE_URL . 'assets/images/user_avatar.jpg'; ?>" 
                                             alt="Avatar" class="rounded-circle" style="width: 30px; height: 30px;">
                                        <div class="ml-2">
                                            <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></small>
                                            <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                            <a href="#" class="reply-comment" 
                                               data-comment-id="<?php echo $comment['id']; ?>" 
                                               data-post-id="<?php echo $post['id']; ?>" 
                                               data-username="<?php echo htmlspecialchars($comment['full_name']); ?>">Trả lời</a>
                                            <?php if ($comment['child_count'] > 0): ?>
                                                <a href="#" class="show-child-comments" 
                                                   data-comment-id="<?php echo $comment['id']; ?>">
                                                   Xem thêm bình luận (<?php echo $comment['child_count']; ?>)
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Bình luận con -->
                                    <div class="child-comments ml-5" id="child-comments-<?php echo $comment['id']; ?>">
                                        <!-- Được tải động qua AJAX -->
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($post['comment_count'] > 2): ?>
                                    <a href="#" class="show-all-comments" data-post-id="<?php echo $post['id']; ?>">
                                        Xem thêm <?php echo $post['comment_count'] - 2; ?> bình luận
                                    </a>
                                <?php endif; ?>
                                <?php $conn = null; ?>
                            </div>
                            <!-- Form bình luận -->
                            <form action="<?php echo BASE_URL; ?>forums/comment.php" method="POST" class="comment-form mt-3" data-post-id="<?php echo $post['id']; ?>">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="parent_comment_id" class="parent-comment-id">
                                <div class="replying-to">
                                    <span class="replying-text"></span>
                                    <a href="#" class="cancel-reply">Hủy</a>
                                </div>
                                <div class="form-group">
                                    <textarea name="content" class="form-control" rows="2" placeholder="Viết bình luận..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Gửi</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal tạo bài đăng -->
    <div class="modal fade" id="createPostModal" tabindex="-1" role="dialog" aria-labelledby="createPostModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPostModalLabel">Tạo bài đăng mới</h5>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="title">Tiêu đề</label>
                            <input type="text" name="title" id="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Ngành</label>
                            <select name="category" id="category" class="form-control" required>
                                <?php foreach (array_slice($categories, 1) as $key => $name): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $key === 'cong_nghe_thong_tin' ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="content">Nội dung</label>
                            <textarea name="content" id="content" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="create_post" class="btn btn-primary">Đăng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script>
$(document).ready(function() {
    $('.create-post-btn').on('click', function() {
        $('#createPostModal').modal('show');
    });

    // Xử lý tab lọc bài đăng theo ngành
    $('.nav-tabs .nav-link').on('click', function(e) {
        e.preventDefault();
        $('.nav-tabs .nav-link').removeClass('active');
        $(this).addClass('active');

        var category = $(this).data('category');
        var $posts = $('.post-container');
        var $noPosts = $('.text-muted');

        if (category === 'all') {
            $posts.show();
            if ($posts.length > 0) {
                $noPosts.hide();
            } else {
                $noPosts.show();
            }
        } else {
            $posts.hide();
            var $matchingPosts = $posts.filter('[data-category="' + category + '"]');
            $matchingPosts.show();
            if ($matchingPosts.length === 0) {
                if ($noPosts.length === 0) {
                    $('.container.mt-4').append('<p class="text-muted">Chưa có bài đăng nào trong diễn đàn.</p>');
                } else {
                    $noPosts.show();
                }
            } else {
                $noPosts.hide();
            }
        }
    });

    $(document).on('click', '.toggle-comments', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var postId = $(this).data('post-id');
        var $commentsSection = $('#comments-' + postId);
        if ($commentsSection.is(':visible')) {
            $commentsSection.slideUp(200);
        } else {
            $commentsSection.slideDown(200);
        }
    });

    $(document).on('click', '.show-child-comments', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var commentId = $(this).data('comment-id');
        var postId = $(this).closest('.post-container').find('.comment-form').data('post-id');
        var $childComments = $('#child-comments-' + commentId);
        
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
                success: function(response) {
                    if (response.status === 'success') {
                        var commentsHtml = '';
                        response.child_comments.forEach(function(comment) {
                            commentsHtml += `
                                <div class="d-flex align-items-center mb-2">
                                    <img src="${comment.avatar || '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
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
                        console.error('Lỗi tải bình luận con:', response.message);
                        alert('Lỗi: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Lỗi AJAX khi tải bình luận con:', status, error);
                    alert('Lỗi khi tải bình luận con.');
                }
            });
        }
    });

    $(document).on('click', '.reply-comment', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var commentId = $(this).data('comment-id');
        var postId = $(this).data('post-id');
        var username = $(this).data('username');
        var $form = $('.comment-form[data-post-id="' + postId + '"]');
        var $textarea = $form.find('textarea');
        var $replyingTo = $form.find('.replying-to');
        var $replyingText = $form.find('.replying-text');

        $form.find('.parent-comment-id').val(commentId);
        $replyingText.text('Đang trả lời ' + username);
        $replyingTo.show();
        $textarea.val('@' + username + ' ').focus();
        $('#comments-' + postId).slideDown(200);
    });

    $(document).on('click', '.cancel-reply', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $form = $(this).closest('.comment-form');
        var $textarea = $form.find('textarea');
        var $replyingTo = $form.find('.replying-to');

        $form.find('.parent-comment-id').val('');
        $replyingTo.hide().find('.replying-text').text('');
        $textarea.val('').focus();
    });

    $(document).on('click', '.show-all-comments', function(e) {
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
                                <img src="${comment.avatar || '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
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
                    $(e.target).hide();
                } else {
                    console.error('Lỗi tải bình luận cha:', response.message);
                    alert('Lỗi: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Lỗi AJAX khi tải bình luận cha:', status, error);
                alert('Lỗi khi tải bình luận.');
            }
        });
    });

    $(document).on('submit', '.comment-form', function(e) {
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
                                            <img src="${comment.avatar || '<?php echo BASE_URL; ?>assets/images/user_avatar.jpg'}" 
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
                                console.error('Lỗi tải lại bình luận:', commentResponse.message);
                                alert('Lỗi khi tải lại bình luận: ' + commentResponse.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Lỗi AJAX khi tải lại bình luận:', status, error);
                            alert('Lỗi khi tải lại bình luận.');
                        }
                    });

                    $form.find('.parent-comment-id').val('');
                    $form.find('.replying-to').hide().find('.replying-text').text('');
                    $form.find('textarea').val('');
                } else {
                    console.error('Lỗi gửi bình luận:', response.message);
                    alert('Lỗi khi gửi bình luận: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Lỗi AJAX khi gửi bình luận:', status, error);
                alert('Lỗi khi gửi bình luận: ' + (xhr.responseText || 'Không thể kết nối đến server'));
            }
        });
    });
});
    </script>
</body>
</html>
<?php ob_end_flush(); ?>