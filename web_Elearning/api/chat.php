<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_functions.php';

// Chỉ cho phép người dùng đã đăng nhập truy cập
redirectIfNotLoggedIn();

$user = getCurrentUser();
$conn = getDBConnection();
$groupId = $_GET['group_id'] ?? null;

if (!$groupId) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID nhóm']);
    exit;
}

// Lấy thông tin nhóm để kiểm tra quyền creator (dùng trong kick/add member)
try {
    $stmt = $conn->prepare("SELECT creator_id, thumbnail FROM groups WHERE id = :group_id");
    $stmt->execute(['group_id' => $groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Nhóm không tồn tại']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    exit;
}

// Xử lý các yêu cầu API
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Lấy danh sách tin nhắn hoặc thành viên
        if (isset($_GET['action']) && $_GET['action'] === 'messages') {
            try {
                $stmt = $conn->prepare("SELECT cm.*, u.full_name, u.avatar AS profile_picture FROM chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.group_id = :group_id ORDER BY cm.created_at ASC");
                $stmt->execute(['group_id' => $groupId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $messages]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
        } elseif (isset($_GET['action']) && $_GET['action'] === 'members') {
            try {
                $stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, u.avatar AS profile_picture, gm.user_id = g.creator_id AS is_creator FROM group_members gm JOIN users u ON gm.user_id = u.id JOIN groups g ON gm.group_id = g.id WHERE gm.group_id = :group_id");
                $stmt->execute(['group_id' => $groupId]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $members]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
        }
        break;

    case 'POST':
        // Xử lý upload ảnh
        if (isset($_FILES['attachment'])) {
            $file = $_FILES['attachment'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                $uploadDir = __DIR__ . '/../assets/uploads/groups_chat/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = 'attachment_' . time() . '.' . $ext;
                $uploadPath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $message = "assets/uploads/groups/" . $fileName;
                    try {
                        $stmt = $conn->prepare("INSERT INTO chat_messages (group_id, user_id, message) VALUES (:group_id, :user_id, :message)");
                        $stmt->execute(['group_id' => $groupId, 'user_id' => $user['id'], 'message' => $message]);
                        echo json_encode(['success' => true, 'message' => 'Ảnh đã được gửi']);
                    } catch (PDOException $e) {
                        // Nếu lưu vào DB thất bại, xóa file vừa upload để tránh rác
                        unlink($uploadPath);
                        echo json_encode(['success' => false, 'message' => 'Lỗi khi lưu ảnh: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể tải ảnh lên']);
                }
            } else {
                $errorMsg = $file['error'] === UPLOAD_ERR_OK ? 'File không hợp lệ hoặc quá lớn' : 'Lỗi upload file';
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
        }
        // Xử lý gửi tin nhắn hoặc quản lý thành viên
        else {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['action'])) {
                if ($data['action'] === 'send_message') {
                    $message = trim($data['message'] ?? '');
                    if (!empty($message)) {
                        try {
                            $stmt = $conn->prepare("INSERT INTO chat_messages (group_id, user_id, message) VALUES (:group_id, :user_id, :message)");
                            $stmt->execute(['group_id' => $groupId, 'user_id' => $user['id'], 'message' => $message]);
                            echo json_encode(['success' => true, 'message' => 'Tin nhắn đã được gửi']);
                        } catch (PDOException $e) {
                            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Tin nhắn không được để trống']);
                    }
                } elseif ($data['action'] === 'kick_member' && $user['id'] === $group['creator_id']) {
                    $userId = $data['user_id'] ?? null;
                    if ($userId) {
                        try {
                            $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = :group_id AND user_id = :user_id");
                            $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
                            echo json_encode(['success' => true, 'message' => 'Đã xóa thành viên']);
                        } catch (PDOException $e) {
                            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                    }
                } elseif ($data['action'] === 'add_member' && $user['id'] === $group['creator_id']) {
                    $userId = $data['user_id'] ?? null;
                    if ($userId) {
                        try {
                            $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_id) VALUES (:group_id, :user_id)");
                            $stmt->execute(['group_id' => $groupId, 'user_id' => $userId]);
                            echo json_encode(['success' => true, 'message' => 'Đã thêm thành viên']);
                        } catch (PDOException $e) {
                            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Thiếu user_id']);
                    }
                } elseif ($data['action'] === 'leave_group') {
                    try {
                        // Kiểm tra nếu người dùng là creator
                        if ($user['id'] === $group['creator_id']) {
                            // Xóa tất cả tin nhắn của nhóm
                            $stmt = $conn->prepare("SELECT message FROM chat_messages WHERE group_id = :group_id AND message LIKE 'assets/uploads/groups/%'");
                            $stmt->execute(['group_id' => $groupId]);
                            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($attachments as $attachment) {
                                $filePath = __DIR__ . '/../' . $attachment['message'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }

                            // Xóa bản ghi trong chat_messages
                            $stmt = $conn->prepare("DELETE FROM chat_messages WHERE group_id = :group_id");
                            $stmt->execute(['group_id' => $groupId]);

                            // Xóa bản ghi trong group_members
                            $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = :group_id");
                            $stmt->execute(['group_id' => $groupId]);

                            // Xóa thumbnail của nhóm nếu có
                            if ($group['thumbnail']) {
                                $thumbnailPath = __DIR__ . '/../' . $group['thumbnail'];
                                if (file_exists($thumbnailPath)) {
                                    unlink($thumbnailPath);
                                }
                            }

                            // Xóa nhóm
                            $stmt = $conn->prepare("DELETE FROM groups WHERE id = :group_id");
                            $stmt->execute(['group_id' => $groupId]);

                            echo json_encode(['success' => true, 'message' => 'Nhóm đã bị xóa', 'group_deleted' => true]);
                        } else {
                            // Xóa thành viên khỏi nhóm
                            $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = :group_id AND user_id = :user_id");
                            $stmt->execute(['group_id' => $groupId, 'user_id' => $user['id']]);
                            echo json_encode(['success' => true, 'message' => 'Bạn đã rời nhóm', 'group_deleted' => false]);
                        }
                    } catch (PDOException $e) {
                        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ hoặc không đủ quyền']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Thiếu tham số action']);
            }
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ']);
        break;
}

$conn = null;
?>