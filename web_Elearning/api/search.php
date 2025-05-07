<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_functions.php';

header('Content-Type: application/json');
$conn = getDBConnection();
$user = getCurrentUser();
$query = $_GET['query'] ?? '';
$context = $_GET['context'] ?? '';

$response = ['success' => false, 'data' => ['students' => [], 'courses' => [], 'groups' => [], 'teachers' => []], 'message' => ''];

if (empty($query)) {
    $response['message'] = 'Vui lòng nhập từ khóa tìm kiếm.';
    echo json_encode($response);
    exit;
}

try {
    if ($context === 'teacher') {
        // Tìm sinh viên
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE role = 'student' AND (full_name LIKE :query OR email LIKE :query) LIMIT 10");
        $stmt->execute(['query' => "%$query%"]);
        $response['data']['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tìm khóa học
        $stmt = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = :teacher_id AND title LIKE :query LIMIT 10");
        $stmt->execute(['teacher_id' => $user['id'], 'query' => "%$query%"]);
        $response['data']['courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tìm nhóm học
        $stmt = $conn->prepare("SELECT id, name FROM groups WHERE creator_id = :teacher_id AND name LIKE :query LIMIT 10");
        $stmt->execute(['teacher_id' => $user['id'], 'query' => "%$query%"]);
        $response['data']['groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
    } elseif ($context === 'student') {
        // Tìm khóa học
        $stmt = $conn->prepare("SELECT id, title FROM courses WHERE status = 'active' AND title LIKE :query LIMIT 10");
        $stmt->execute(['query' => "%$query%"]);
        $response['data']['courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tìm giáo viên
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE role = 'teacher' AND (full_name LIKE :query OR email LIKE :query) LIMIT 10");
        $stmt->execute(['query' => "%$query%"]);
        $response['data']['teachers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
    } elseif ($context === 'chat') {
        // Tìm sinh viên bằng email
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE role = 'student' AND email LIKE :query LIMIT 10");
        $stmt->execute(['query' => "%$query%"]);
        $response['data']['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['success'] = true;
    } else {
        $response['message'] = 'Ngữ cảnh không hợp lệ.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Lỗi: ' . $e->getMessage();
}

echo json_encode($response);
$conn = null;
?>