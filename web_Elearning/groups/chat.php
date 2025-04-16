<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

// Chỉ cho phép người dùng đã đăng nhập truy cập
redirectIfNotLoggedIn();

$user = getCurrentUser();
$conn = getDBConnection();
$groupId = $_GET['group_id'] ?? null;
$errors = [];
$success = false;

if (!$groupId) {
    die("Không tìm thấy ID nhóm.");
}

// Lấy thông tin nhóm
try {
    $stmt = $conn->prepare("SELECT g.*, u.full_name AS creator_name FROM groups g JOIN users u ON g.creator_id = u.id WHERE g.id = :group_id");
    $stmt->execute(['group_id' => $groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        die("Nhóm không tồn tại.");
    }
} catch (PDOException $e) {
    $errors[] = "Lỗi: " . $e->getMessage();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chat Nhóm - <?php echo htmlspecialchars($group['name']); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/chat.css">
    <style>
        body {
            background: url('<?php echo BASE_URL; ?>assets/images/chat_background.png') repeat;
            font-family: Arial, sans-serif;
        }
        .container {
            display: flex;
            height: 100vh;
            padding: 0;
        }
        .chat-container {
            display: flex;
            width: 100%;
        }
        .member-list {
            width: 30%;
            background: #fff;
            border-right: 1px solid #ddd;
            padding: 10px;
            overflow-y: auto;
        }
        .member-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .member-item img.avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .member-info .name {
            font-weight: bold;
        }
        .member-info .email {
            font-size: 0.9em;
            color: #666;
        }
        .member-info .owner {
            font-size: 0.8em;
            color: #007bff;
        }
        .chat-area {
            width: 70%;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            background: #4a90e2;
            color: white;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-header .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2em;
            cursor: pointer;
        }
        .chat-header .action-buttons {
            display: flex;
            gap: 10px;
        }
        .chat-header .action-buttons button {
            background: none;
            border: none;
            color: white;
            text-transform: uppercase;
            font-size: 0.9em;
            cursor: pointer;
        }
        .messages {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
        }
        .message {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .message.mine {
            flex-direction: row-reverse;
        }
        .message-header {
            display: flex;
            align-items: center; /* Căn giữa avatar và tên theo chiều dọc */
        }
        .message.mine .message-header {
            flex-direction: row-reverse; /* Đảo ngược thứ tự avatar và tên cho tin nhắn của mình */
        }
        .message .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 10px;
        }
        .message .sender {
            font-size: 0.9em;
            color: #555;
            margin: 0 5px; /* Khoảng cách giữa tên và avatar */
        }
        .message.mine .sender {
            text-align: right;
        }
        .message .content {
            max-width: 90%;
            padding: 10px;
            border-radius: 10px;
            background: #e0e0e0;
            white-space: nowrap !important;
            word-break: normal;
        }
        .message.mine .content {
            background: #4a90e2;
            color: white;
        }
        .message .time {
            font-size: 0.8em;
            color: #999;
        }
        .message img {
            max-width: 200px;
            border-radius: 10px;
        }
        .message-input {
            padding: 10px;
            background: #fff;
            border-top: 1px solid #ddd;
        }
        .message-input form {
            display: flex;
            align-items: center;
        }
        .message-input .input-group {
            width: 100%;
        }
        .message-input .form-control {
            border-radius: 20px;
        }
        .message-input .btn {
            border-radius: 20px;
        }
        .search-bar {
            margin-top: 10px;
        }
        .search-results {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            width: 90%;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="chat-container">
            <!-- Danh sách thành viên -->
            <div class="member-list">
                <h4>Danh sách thành viên 
                    <?php if ($user['id'] === $group['creator_id']): ?>
                        <div class="search-bar">
                            <input type="text" id="searchInput" class="form-control" placeholder="Tìm sinh viên bằng email">
                            <div id="searchResults" class="search-results"></div>
                        </div>
                    <?php endif; ?>
                </h4>
                <div id="memberList"></div>
            </div>

            <!-- Khu vực chat -->
            <div class="chat-area">
                <div class="chat-header">
                    <button class="back-btn" onclick="goBack()">⬅️</button>
                    <?php echo htmlspecialchars($group['name']); ?>
                    <div class="action-buttons">
                        <button id="memberButton" onclick="toggleMemberList()">Member (<span id="memberCount">0</span>)</button>
                        <button onclick="leaveGroup()">Leave</button>
                    </div>
                </div>
                <div class="messages" id="messageList"></div>
                <div class="message-input">
                    <form id="messageForm" enctype="multipart/form-data">
                        <div class="input-group">
                            <input type="text" name="message" id="messageInput" class="form-control" placeholder="Type your message here">
                            <div class="input-group-append">
                                <label class="btn btn-outline-secondary">
                                    📎 <input type="file" id="attachmentInput" name="attachment" style="display: none;" accept="image/*">
                                </label>
                                <button type="submit" class="btn btn-primary">➡️</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
    <script>
        const groupId = <?php echo json_encode($groupId); ?>;
        const userId = <?php echo json_encode($user['id']); ?>;
        const creatorId = <?php echo json_encode($group['creator_id']); ?>;
        const apiUrl = '<?php echo BASE_URL; ?>api/chat.php?group_id=' + groupId;

        // Hàm quay lại trang trước
        function goBack() {
            window.history.back();
        }

        // Hàm hiển thị/ẩn danh sách thành viên
        function toggleMemberList() {
            $('.member-list').toggle();
            $('.chat-area').css('width', $('.member-list').is(':visible') ? '70%' : '100%');
        }

        // Hàm rời nhóm
        function leaveGroup() {
            if (confirm('Bạn có chắc muốn rời nhóm này không?')) {
                $.ajax({
                    url: apiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: 'leave_group' }),
                    success: function(response) {
                        if (response.success) {
                            if (response.group_deleted) {
                                alert('Nhóm đã bị xóa vì bạn là giáo viên.');
                            } else {
                                alert('Bạn đã rời nhóm thành công.');
                            }
                            window.location.href = '<?php echo BASE_URL; ?>groups.php';
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Lỗi khi rời nhóm');
                    }
                });
            }
        }

        // Load danh sách thành viên và cập nhật số thành viên
        function loadMembers() {
            $.get(apiUrl + '&action=members', function(response) {
                if (response.success) {
                    const members = response.data;
                    let html = '';
                    members.forEach(member => {
                        html += `
                            <div class="member-item">
                                <img src="${member.profile_picture ? '<?php echo BASE_URL; ?>' + member.profile_picture : '<?php echo BASE_URL; ?>assets/images/default_profile.jpg'}" alt="Profile Picture" class="avatar">
                                <div class="member-info">
                                    <div class="name">${member.full_name}</div>
                                    <div class="email">${member.email}</div>
                                    ${member.is_creator ? '<div class="owner">Owner</div>' : ''}
                                </div>
                                ${userId === creatorId && !member.is_creator ? `
                                    <button class="btn btn-sm btn-danger delete-btn kickMember" data-user-id="${member.id}">Xóa</button>` : ''}
                            </div>`;
                    });
                    $('#memberList').html(html);
                    // Cập nhật số thành viên
                    $('#memberCount').text(members.length);
                } else {
                    $('#memberList').html('<p>Lỗi tải danh sách thành viên</p>');
                    $('#memberCount').text('0');
                }
            });
        }

        // Load danh sách tin nhắn
        function loadMessages() {
            $.get(apiUrl + '&action=messages', function(response) {
                if (response.success) {
                    const messages = response.data;
                    let html = '';
                    messages.forEach(msg => {
                        const isMine = msg.user_id === userId;
                        // Loại bỏ ký tự xuống dòng trong tin nhắn
                        const messageContent = msg.message.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                        html += `
                            <div class="message ${isMine ? 'mine' : 'other'}">
                                <div class="message-header">
                                    <img src="${msg.profile_picture ? '<?php echo BASE_URL; ?>' + msg.profile_picture : '<?php echo BASE_URL; ?>assets/images/default_profile.jpg'}" alt="Avatar" class="avatar">
                                    <div class="sender">${msg.full_name}</div>
                                </div>
                                <div>
                                    <div class="content">
                                        ${msg.message.startsWith('assets/uploads/groups/') ? 
                                            `<img src="<?php echo BASE_URL; ?>${msg.message}" alt="Attachment">` : messageContent}
                                    </div>
                                    <div class="time">${msg.created_at}</div>
                                </div>
                            </div>`;
                    });
                    $('#messageList').html(html);
                    $('#messageList').scrollTop($('#messageList')[0].scrollHeight);
                } else {
                    $('#messageList').html('<p>Lỗi tải tin nhắn</p>');
                }
            });
        }

        // Gửi tin nhắn hoặc ảnh
        $('#messageForm').submit(function(e) {
            e.preventDefault();
            const message = $('#messageInput').val().trim();
            const file = $('#attachmentInput')[0].files[0];

            if (message) {
                $.ajax({
                    url: apiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: 'send_message', message: message }),
                    success: function(response) {
                        if (response.success) {
                            $('#messageInput').val('');
                            loadMessages();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Lỗi khi gửi tin nhắn');
                    }
                });
            } else if (file) {
                const formData = new FormData();
                formData.append('attachment', file);
                $.ajax({
                    url: apiUrl,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#attachmentInput').val('');
                            loadMessages();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Lỗi khi tải ảnh lên');
                    }
                });
            }
        });

        // Xóa thành viên
        $(document).on('click', '.kickMember', function() {
            const userIdToKick = $(this).data('user-id');
            if (confirm('Bạn có chắc muốn xóa thành viên này?')) {
                $.ajax({
                    url: apiUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: 'kick_member', user_id: userIdToKick }),
                    success: function(response) {
                        if (response.success) {
                            loadMembers();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Lỗi khi xóa thành viên');
                    }
                });
            }
        });

        // Tìm kiếm và thêm thành viên
        $('#searchInput').on('keyup', function() {
            const query = $(this).val();
            if (query.length < 2) {
                $('#searchResults').empty();
                return;
            }
            $.ajax({
                url: '<?php echo BASE_URL; ?>api/search.php',
                method: 'GET',
                data: { query: query, context: 'chat' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const results = response.data.students.map(student => `
                            <div>${student.full_name} (${student.email})
                                <button class="btn btn-sm btn-success" onclick="addMember(${student.id})">Thêm</button>
                            </div>`).join('');
                        $('#searchResults').html(results || '<div>Không tìm thấy</div>');
                    } else {
                        $('#searchResults').html('<div>' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#searchResults').html('<div>Lỗi tìm kiếm</div>');
                }
            });
        });

        function addMember(userId) {
            $.ajax({
                url: apiUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'add_member', user_id: userId }),
                success: function(response) {
                    if (response.success) {
                        loadMembers();
                        $('#searchResults').empty();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Lỗi khi thêm thành viên');
                }
            });
        }

        // Tự động ẩn kết quả tìm kiếm khi click ra ngoài
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-bar').length) {
                $('#searchResults').empty();
            }
        });

        // Load dữ liệu ban đầu
        loadMembers();
        loadMessages();

        // Cập nhật tin nhắn định kỳ (tùy chọn)
        setInterval(loadMessages, 5000); // Cập nhật mỗi 5 giây
    </script>
</body>
</html>