<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Chỉ cho phép giáo viên truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('teacher');

$user = getCurrentUser();

// Lấy kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Lấy danh sách khóa học của giáo viên
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = :teacher_id");
$stmt->execute(['teacher_id' => $user['id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sinh viên đã tham gia từng khóa học và thông tin tiến độ
$courseEnrollments = [];
foreach ($courses as $course) {
    // Đếm tổng số bài quiz (số lesson có ít nhất một câu hỏi)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT l.id) as total_quizzes 
        FROM lessons l
        JOIN tests t ON t.lesson_id = l.id 
        WHERE l.course_id = :course_id
        AND t.type = 'lesson'
    ");
    $stmt->execute(['course_id' => $course['id']]);
    $totalQuizzes = $stmt->fetch(PDO::FETCH_ASSOC)['total_quizzes'];

    // Lấy danh sách sinh viên đã đăng ký khóa học
    $stmt = $conn->prepare("
        SELECT e.*, u.full_name AS student_name 
        FROM course_enrollments e 
        JOIN users u ON e.student_id = u.id 
        WHERE e.course_id = :course_id AND e.status = 'approved'
    ");
    $stmt->execute(['course_id' => $course['id']]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy thông tin tiến độ cho từng sinh viên
    foreach ($enrollments as &$enrollment) {
        // Đếm số bài quiz hoàn thành đúng (tất cả câu hỏi trong lesson đều đạt max_score)
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT l.id) as completed_quizzes
            FROM lessons l
            JOIN tests t ON t.lesson_id = l.id
            LEFT JOIN test_results tr ON tr.test_id = t.id 
                AND tr.student_id = :student_id 
                AND tr.course_id = :course_id
            WHERE l.course_id = :course_id 
            AND t.type = 'lesson'
            AND tr.completed_at IS NOT NULL
            GROUP BY l.id
            HAVING SUM(CASE WHEN tr.score = t.max_score THEN 1 ELSE 0 END) = COUNT(t.id)
        ");
        $stmt->execute([
            'student_id' => $enrollment['student_id'],
            'course_id' => $course['id']
        ]);
        $completedQuizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $completedQuizzesCount = count($completedQuizzes);

        // Tính phần trăm hoàn thành bài quiz
        $quizCompletionRate = $totalQuizzes > 0 ? round(($completedQuizzesCount / $totalQuizzes) * 100, 2) : 0;
        $enrollment['quiz_completion_rate'] = $quizCompletionRate;

        // Điểm trung bình quiz
        $stmt = $conn->prepare("
            SELECT AVG(tr.score) as avg_quiz_score
            FROM test_results tr
            JOIN tests t ON tr.test_id = t.id
            WHERE tr.student_id = :student_id 
            AND tr.course_id = :course_id
            AND t.type = 'lesson'
        ");
        $stmt->execute([
            'student_id' => $enrollment['student_id'],
            'course_id' => $course['id']
        ]);
        $quizScore = $stmt->fetch(PDO::FETCH_ASSOC);
        $enrollment['avg_quiz_score'] = ($quizScore && $quizScore['avg_quiz_score'] !== null) ? round($quizScore['avg_quiz_score'], 2) : 'Chưa có';

        // Đếm số lần làm bài quiz
        $stmt = $conn->prepare("
            SELECT COUNT(*) as quiz_attempts
            FROM test_results tr
            JOIN tests t ON tr.test_id = t.id
            WHERE tr.student_id = :student_id 
            AND tr.course_id = :course_id
            AND t.type = 'lesson'
        ");
        $stmt->execute([
            'student_id' => $enrollment['student_id'],
            'course_id' => $course['id']
        ]);
        $quizAttempts = $stmt->fetch(PDO::FETCH_ASSOC);
        $enrollment['quiz_attempts'] = $quizAttempts['quiz_attempts'] > 0 ? $quizAttempts['quiz_attempts'] : 'Chưa có';

        // Điểm final test (lấy lần làm cuối cùng)
        $stmt = $conn->prepare("
            SELECT tr.score as final_score, t.max_score
            FROM test_results tr
            JOIN tests t ON tr.test_id = t.id
            WHERE tr.student_id = :student_id 
            AND tr.course_id = :course_id
            AND t.type = 'course'
            ORDER BY tr.completed_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'student_id' => $enrollment['student_id'],
            'course_id' => $course['id']
        ]);
        $finalScore = $stmt->fetch(PDO::FETCH_ASSOC);

        // Đếm số lần làm bài final test
        $stmt = $conn->prepare("
            SELECT COUNT(*) as final_attempts
            FROM test_results tr
            JOIN tests t ON tr.test_id = t.id
            WHERE tr.student_id = :student_id 
            AND tr.course_id = :course_id
            AND t.type = 'course'
        ");
        $stmt->execute([
            'student_id' => $enrollment['student_id'],
            'course_id' => $course['id']
        ]);
        $finalAttempts = $stmt->fetch(PDO::FETCH_ASSOC);
        $enrollment['final_attempts'] = $finalAttempts['final_attempts'] > 0 ? $finalAttempts['final_attempts'] : 'Chưa có';

        if ($finalScore && $finalScore['final_score'] !== null) {
            $finalPercentage = round(($finalScore['final_score'] / $finalScore['max_score']) * 100, 2);
            $enrollment['final_score'] = $finalScore['final_score'];
            $enrollment['final_percentage'] = $finalPercentage;
            // Cập nhật trạng thái hoàn thành nếu đạt >= 75% và hoàn thành tất cả bài quiz
            if ($finalPercentage >= 75 && $quizCompletionRate >= 100) {
                $enrollment['completed_at'] = true; // Giả định hoàn thành
            }
        } else {
            $enrollment['final_score'] = 'Chưa có';
            $enrollment['final_percentage'] = 'Chưa có';
        }
    }
    unset($enrollment); // Xóa tham chiếu sau vòng lặp

    $courseEnrollments[$course['id']] = [
        'title' => $course['title'],
        'enrollments' => $enrollments,
        'total_quizzes' => $totalQuizzes
    ];
}

$conn = null;
?>

<div class="teacher-action-card">
    <h3>Xem điểm và Tiến độ Sinh viên</h3>
    <?php if (empty($courseEnrollments) || !array_filter(array_column($courseEnrollments, 'enrollments'))): ?>
        <p>Không có sinh viên nào để xem điểm hoặc tiến độ.</p>
    <?php else: ?>
        <?php foreach ($courseEnrollments as $courseId => $courseData): ?>
            <?php if (!empty($courseData['enrollments'])): ?>
                <h4 class="mt-4"><?php echo htmlspecialchars($courseData['title']); ?> 
                    (Tổng số bài quiz: <?php echo $courseData['total_quizzes']; ?>)
                </h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sinh viên</th>
                            <th>Tiến độ (%)</th>
                            <th>Điểm Quiz Trung bình</th>
                            <th>Số lần làm Quiz</th>
                            <th>Điểm cuối khóa (%)</th>
                            <th>Số lần làm Final</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courseData['enrollments'] as $enrollment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['quiz_completion_rate']); ?>%</td>
                                <td><?php echo htmlspecialchars($enrollment['avg_quiz_score']); ?></td>
                                <td><?php echo htmlspecialchars($enrollment['quiz_attempts']); ?></td>
                                <td>
                                    <?php 
                                    if ($enrollment['final_score'] === 'Chưa có') {
                                        echo 'Chưa có';
                                    } else {
                                        echo htmlspecialchars($enrollment['final_score']) . ' (' . $enrollment['final_percentage'] . '%)';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($enrollment['final_attempts']); ?></td>
                                <td>
                                    <?php if ($enrollment['completed_at'] ?? false): ?>
                                        <span class="text-success">Đã hoàn thành</span>
                                    <?php else: ?>
                                        <span class="text-warning">Chưa hoàn thành</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>