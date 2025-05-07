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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chat Nhóm - <?php echo htmlspecialchars($group['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-[#eaf2ff] min-h-screen flex flex-col">
    <!-- Header -->
    <header class="flex items-center justify-between bg-[#6cb7ff] px-4 sm:px-6 md:px-8 h-12 text-[#0f2f5a] font-semibold text-lg select-none">
        <div class="flex items-center space-x-2">
            <button aria-label="Back" class="focus:outline-none" onclick="  goBack()">
                <i class="fas fa-arrow-left"></i>
            </button>
            <span><?php echo htmlspecialchars($group['name']); ?></span>
        </div>
        <nav class="flex space-x-4 text-xs font-normal">
            <button class="hover:underline" onclick="toggleMemberList()">Member (<span id="memberCount">0</span>)</button>
            <button class="hover:underline" onclick="leaveGroup()">Leave</button>
        </nav>
    </header>

    <main class="flex flex-1 overflow-hidden">
        <!-- Left panel: Danh sách thành viên -->
        <section class="bg-white w-72 min-w-[18rem] border-r border-gray-300 flex flex-col member-list">
            <h2 class="font-bold text-sm px-4 pt-4 pb-2 select-none">Danh sách thành viên</h2>
            <?php if ($user['id'] === $group['creator_id']): ?>
                <div class="search-bar px-4 pb-2">
                    <input type="text" id="searchInput" class="w-full text-xs border border-[#a0d8ff] rounded-lg px-3 py-2 focus:outline-none" placeholder="Tìm sinh viên bằng email">
                    <div id="searchResults" class="search-results"></div>
                </div>
            <?php endif; ?>
            <ul id="memberList" class="overflow-y-auto flex-1 scrollbar-thin"></ul>
        </section>

        <!-- Right panel: Chat area -->
        <section class="flex-1 flex flex-col relative bg-white overflow-hidden chat-area">
            <!-- Background pattern -->
            <img alt="Background pattern" class="absolute inset-0 w-full h-full object-cover pointer-events-none select-none" src="https://storage.googleapis.com/a1aa/image/a98c19cd-45ac-45f3-5b41-f7d3d5f03017.jpg" width="600" height="600"/>
            <!-- Chat messages container -->
            <div id="messageList" class="relative flex-1 overflow-y-auto px-4 py-3 space-y-2 z-10"></div>
            <!-- Input area -->
            <form id="messageForm" class="z-10 relative flex items-center border border-[#a0d8ff] rounded-lg m-4" enctype="multipart/form-data">
                <input aria-label="Type your message here" name="message" id="messageInput" class="flex-1 text-xs text-[#a0b9d6] placeholder-[#a0b9d6] rounded-lg px-3 py-2 focus:outline-none" placeholder="Type your message here" type="text"/>
                <label class="text-[#0f2f5a] px-3 py-2 focus:outline-none cursor-pointer">
                    <i class="fas fa-paperclip"></i>
                    <input type="file" id="attachmentInput" name="attachment" style="display: none;" accept="image/*">
                </label>
                <button aria-label="Send message" class="text-[#0f2f5a] px-3 py-2 focus:outline-none" type="submit">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </section>
    </main>

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
            $('.chat-area').css('width', $('.member-list').is(':visible') ? 'calc(100% - 18rem)' : '100%');
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
                            <li class="flex items-center gap-3 px-4 py-2 cursor-default select-none ${member.is_creator ? 'bg-[#f0f4ff]' : ''}">
                                <img alt="Avatar" class="w-8 h-8 rounded-full object-cover" height="32" width="32" src="${member.profile_picture ? '<?php echo BASE_URL; ?>' + member.profile_picture : '<?php echo BASE_URL; ?>assets/images/default_profile.jpg'}"/>
                                <div class="flex flex-col text-xs leading-tight">
                                    <span class="font-semibold text-[#0f2f5a] ${member.is_creator ? 'flex items-center gap-1' : ''}">
                                        ${member.full_name}
                                        ${member.is_creator ? '<span class="text-[10px] font-normal text-gray-500">Owner</span>' : ''}
                                    </span>
                                    <span class="text-gray-600">${member.email}</span>
                                </div>
                                ${userId === creatorId && !member.is_creator ? `
                                    <button class="btn btn-sm btn-danger delete-btn kickMember ml-auto text-xs text-white bg-red-500 hover:bg-red-600 rounded px-2 py-1" data-user-id="${member.id}">Xóa</button>` : ''}
                            </li>`;
                    });
                    $('#memberList').html(html);
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
                        const messageContent = msg.message.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                        html += `
                            <div class="flex items-start space-x-2 max-w-xs ${isMine ? 'ml-auto flex-row-reverse' : ''}">
                                <img alt="Avatar" class="w-8 h-8 rounded-full object-cover mt-1" height="32" width="32" src="${msg.profile_picture ? '<?php echo BASE_URL; ?>' + msg.profile_picture : '<?php echo BASE_URL; ?>assets/images/default_profile.jpg'}"/>
                                <div class="${isMine ? 'text-right' : ''}">
                                    <div class="text-xs font-semibold text-[#0f2f5a] select-text">${msg.full_name}</div>
                                    <div class="bg-${isMine ? '[#6cb7ff]' : '[#a0d8ff]'} text-xs text-[#0f2f5a] rounded-lg px-3 py-1 mt-1 max-w-xs break-words">
                                        ${msg.message.startsWith('assets/uploads/groups/') ? 
                                            `<img src="<?php echo BASE_URL; ?>${msg.message}" alt="Attachment" class="max-w-full rounded-lg"/>` : messageContent}
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-1">${msg.created_at}</div>
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
                            <div class="flex justify-between items-center">
                                <span>${student.full_name} (${student.email})</span>
                                <button class="text-xs text-white bg-green-500 hover:bg-green-600 rounded px-2 py-1" onclick="addMember(${student.id})">Thêm</button>
                            </div>`).join('');
                        $('#searchResults').html(results || '<div class="text-gray-600">Không tìm thấy</div>');
                    } else {
                        $('#searchResults').html('<div class="text-gray-600">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#searchResults').html('<div class="text-gray-600">Lỗi tìm kiếm</div>');
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

        // Cập nhật tin nhắn định kỳ
        setInterval(loadMessages, 5000);
    </script>
</body>
</html>