<?php
require_once __DIR__ . '/../config/database.php';

ob_start(); // Ngăn đầu ra không mong muốn
header('Content-Type: application/json');
$conn = getDBConnection();

$courseId = $_GET['course_id'] ?? null;
$lessonId = $_GET['lesson_id'] ?? null;

if (!$courseId || !$lessonId) {
    $response = ['success' => false, 'error' => 'Thiếu thông tin khóa học hoặc bài học'];
    echo json_encode($response);
    ob_end_flush();
    exit;
}

try {
    // Lấy danh sách câu hỏi trắc nghiệm
    $stmt = $conn->prepare("
        SELECT id, question, option1, option2, option3, option4
        FROM quizzes
        WHERE lesson_id = :lesson_id
    ");
    $stmt->execute(['lesson_id' => $lessonId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($quizzes) {
        $response = [
            'success' => true,
            'quizzes' => $quizzes,
            'totalQuestions' => count($quizzes)
        ];
    } else {
        $response = ['success' => false, 'error' => 'Không tìm thấy câu hỏi trắc nghiệm'];
    }
} catch (PDOException $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

ob_end_clean();
echo json_encode($response);
exit;
?>