<?php
require_once __DIR__ . '/../config/database.php';

// Prevent any unwanted output
ob_start(); // Start output buffering to catch stray output

header('Content-Type: application/json');
$conn = getDBConnection();

$courseId = $_GET['course_id'] ?? null;
$lessonId = $_GET['lesson_id'] ?? null;
$currentPage = $_GET['current_page'] ?? 1;

if (!$courseId || !$lessonId) {
    $response = ['success' => false, 'error' => 'Thiếu thông tin khóa học hoặc bài học'];
    echo json_encode($response);
    ob_end_flush();
    exit;
}

try {
    // Get current page info
    $stmt = $conn->prepare("
        SELECT p.title, p.content, p.page_number
        FROM pages p
        JOIN lesson_items li ON p.lesson_item_id = li.id
        WHERE li.lesson_id = :lesson_id
        AND p.page_number = :page_number
    ");
    $stmt->execute(['lesson_id' => $lessonId, 'page_number' => $currentPage]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        // Get total pages
        $stmtTotal = $conn->prepare("
            SELECT COUNT(*) as total_pages
            FROM pages p
            JOIN lesson_items li ON p.lesson_item_id = li.id
            WHERE li.lesson_id = :lesson_id
        ");
        $stmtTotal->execute(['lesson_id' => $lessonId]);
        $totalPages = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total_pages'];

        $response = [
            'success' => true,
            'title' => $page['title'],
            'content' => $page['content'],
            'currentPage' => (int)$page['page_number'],
            'totalPages' => (int)$totalPages
        ];
    } else {
        $response = ['success' => false, 'error' => 'Không tìm thấy trang'];
    }
} catch (PDOException $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

// Ensure no stray output corrupts the JSON
ob_end_clean(); // Clear any buffered output
echo json_encode($response); // Output only the JSON
exit; // Ensure nothing else runs after this
?>