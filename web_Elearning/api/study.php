<?php
header('Content-Type: application/json');
ob_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Kiểm tra quyền truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('student');

$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    error_log("User session invalid: " . print_r($user, true));
    http_response_code(401);
    echo json_encode(['error' => 'Phiên đăng nhập không hợp lệ']);
    exit();
}

$conn = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: '';
$response = [];

// Hàm kiểm tra hoàn thành trang
function hasCompletedLessonPages($conn, $lesson_id, $user_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT p.id) as total_pages
        FROM pages p
        JOIN lesson_items li ON p.lesson_item_id = li.id
        WHERE li.lesson_id = :lesson_id
    ");
    $stmt->execute(['lesson_id' => $lesson_id]);
    $total_pages = $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT upp.page_id) as completed_pages
        FROM user_page_progress upp
        JOIN pages p ON upp.page_id = p.id
        JOIN lesson_items li ON p.lesson_item_id = li.id
        WHERE li.lesson_id = :lesson_id AND upp.user_id = :user_id AND upp.completed = 1
    ");
    $stmt->execute(['lesson_id' => $lesson_id, 'user_id' => $user_id]);
    $completed_pages = $stmt->fetchColumn();

    return $total_pages > 0 && $completed_pages >= $total_pages;
}

// Hàm kiểm tra hoàn thành tất cả bài học
function hasCompletedAllLessons($conn, $course_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM lessons WHERE course_id = :course_id");
    $stmt->execute(['course_id' => $course_id]);
    $lessons = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($lessons as $lesson_id) {
        if (!hasCompletedLessonPages($conn, $lesson_id, $user_id)) {
            return false;
        }
    }
    return true;
}

// Hàm kiểm tra đạt tất cả bài kiểm tra
function hasPassedAllLessonQuizzes($conn, $course_id, $user_id) {
    $stmt = $conn->prepare("
        SELECT DISTINCT l.id
        FROM lessons l
        JOIN tests t ON l.id = t.lesson_id
        WHERE l.course_id = :course_id AND t.type = 'lesson'
    ");
    $stmt->execute(['course_id' => $course_id]);
    $lessons_with_quizzes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($lessons_with_quizzes as $lesson_id) {
        $stmt = $conn->prepare("
            SELECT t.id, t.max_score
            FROM tests t
            WHERE t.lesson_id = :lesson_id AND t.type = 'lesson'
        ");
        $stmt->execute(['lesson_id' => $lesson_id]);
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($quizzes as $quiz) {
            $stmt = $conn->prepare("
                SELECT score
                FROM test_results
                WHERE test_id = :test_id AND student_id = :student_id
                ORDER BY completed_at DESC
                LIMIT 1
            ");
            $stmt->execute(['test_id' => $quiz['id'], 'student_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || ($result['score'] / $quiz['max_score'] * 100) < 75) {
                return false;
            }
        }
    }
    return true;
}

switch ($method) {
    case 'GET':
        $course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: null;
        $lesson_id = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT) ?: null;
        $item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: null;
        $current_page = filter_input(INPUT_GET, 'current_page', FILTER_VALIDATE_INT) ?: 1;
        $current_page = max(1, $current_page);
        $show_quiz = isset($_GET['show_quiz']) && $_GET['show_quiz'] === 'true';
        $show_final_test = isset($_GET['show_final_test']) && $_GET['show_final_test'] === 'true';

        if (!$course_id) {
            $stmt = $conn->prepare("
                SELECT c.*, u.full_name AS teacher_name
                FROM courses c
                JOIN users u ON c.teacher_id = u.id
                JOIN course_enrollments ce ON c.id = ce.course_id
                WHERE ce.student_id = :student_id AND ce.status = 'approved' AND c.status = 'active'
                ORDER BY c.title
            ");
            $stmt->execute(['student_id' => $user['id']]);
            $response['enrolled_courses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        }

        $stmt = $conn->prepare("
            SELECT c.*, u.full_name AS teacher_name, u.email AS teacher_email
            FROM courses c
            JOIN users u ON c.teacher_id = u.id
            JOIN course_enrollments ce ON c.id = ce.course_id
            WHERE c.id = :course_id AND ce.student_id = :student_id AND ce.status = 'approved' AND c.status = 'active'
        ");
        $stmt->execute(['course_id' => $course_id, 'student_id' => $user['id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            http_response_code(403);
            $response['error'] = 'Khóa học không tồn tại hoặc bạn không có quyền truy cập.';
            break;
        }

        $response['course'] = $course;
        $stmt = $conn->prepare("SELECT id, title, description FROM lessons WHERE course_id = :course_id ORDER BY created_at");
        $stmt->execute(['course_id' => $course_id]);
        $response['lessons'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'view_lesson' && $lesson_id) {
            $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = :lesson_id AND course_id = :course_id");
            $stmt->execute(['lesson_id' => $lesson_id, 'course_id' => $course_id]);
            $current_lesson = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current_lesson) {
                http_response_code(404);
                $response['error'] = 'Bài học không tồn tại.';
                break;
            }

            $response['current_lesson'] = $current_lesson;
            $stmt = $conn->prepare("SELECT * FROM lesson_items WHERE lesson_id = :lesson_id ORDER BY order_number");
            $stmt->execute(['lesson_id' => $lesson_id]);
            $lesson_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lesson_items as &$item) {
                $stmt = $conn->prepare("SELECT * FROM pages WHERE lesson_item_id = :item_id ORDER BY page_number");
                $stmt->execute(['item_id' => $item['id']]);
                $item['pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $response['lesson_items'] = $lesson_items;

            $stmt = $conn->prepare("SELECT * FROM tests WHERE lesson_id = :lesson_id AND type = 'lesson'");
            $stmt->execute(['lesson_id' => $lesson_id]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($quizzes as &$quiz) {
                $stmt = $conn->prepare("SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id");
                $stmt->execute(['test_id' => $quiz['id']]);
                $quiz['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $response['quizzes'] = $quizzes;
        }

        if ($action === 'final_test' || $show_final_test) {
            if (!hasCompletedAllLessons($conn, $course_id, $user['id']) || !hasPassedAllLessonQuizzes($conn, $course_id, $user['id'])) {
                http_response_code(403);
                $response['error'] = 'Bạn cần hoàn thành tất cả các bài học và đạt tất cả các bài kiểm tra (điểm ≥ 75%) trước khi làm bài kiểm tra cuối khóa.';
            } else {
                $stmt = $conn->prepare("SELECT * FROM tests WHERE course_id = :course_id AND type = 'course'");
                $stmt->execute(['course_id' => $course_id]);
                $final_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($final_tests as &$test) {
                    $stmt = $conn->prepare("SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id");
                    $stmt->execute(['test_id' => $test['id']]);
                    $test['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                $response['final_tests'] = $final_tests;
            }
        }
        break;

    case 'POST':
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT) ?: null;
        $lesson_id = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT) ?: null;

        if (!$course_id || ($action === 'submit_quiz' && !$lesson_id)) {
            http_response_code(400);
            $response['error'] = 'Thiếu thông tin khóa học hoặc bài học.';
            break;
        }

        $stmt = $conn->prepare("
            SELECT c.id
            FROM courses c
            JOIN course_enrollments ce ON c.id = ce.course_id
            WHERE c.id = :course_id AND ce.student_id = :student_id AND ce.status = 'approved' AND c.status = 'active'
        ");
        $stmt->execute(['course_id' => $course_id, 'student_id' => $user['id']]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            http_response_code(403);
            $response['error'] = 'Khóa học không tồn tại hoặc bạn không có quyền truy cập.';
            break;
        }

        if ($action === 'submit_quiz') {
            $quiz_answers = $_POST['quiz'] ?? [];
            $option_mappings = $_POST['option_mapping'] ?? [];
            $stmt = $conn->prepare("
                SELECT id, question, option1, option2, option3, option4, correct_option, max_score
                FROM tests
                WHERE lesson_id = :lesson_id AND type = 'lesson'
            ");
            $stmt->execute(['lesson_id' => $lesson_id]);
            $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($quizzes as &$quiz) {
                $stmt = $conn->prepare("SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id");
                $stmt->execute(['test_id' => $quiz['id']]);
                $quiz['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $session_key = "quiz_order_{$course_id}_{$lesson_id}";
            if (isset($_SESSION[$session_key])) {
                $ordered_quizzes = [];
                $quiz_order = $_SESSION[$session_key]['order'];
                foreach ($quiz_order as $quiz_id) {
                    foreach ($quizzes as $quiz) {
                        if ($quiz['id'] == $quiz_id) {
                            $ordered_quizzes[] = $quiz;
                            break;
                        }
                    }
                }
                $quizzes = $ordered_quizzes;
            } else {
                shuffle($quizzes);
                $_SESSION[$session_key] = [
                    'order' => array_column($quizzes, 'id'),
                    'mappings' => []
                ];
            }

            $score = 0;
            $total_score = 0;
            $results = [];

            foreach ($quizzes as $quiz) {
                $total_score += $quiz['max_score'];
                $user_answer = $quiz_answers[$quiz['id']] ?? null;
                $original_answer = null;
                $is_correct = false;

                if ($user_answer && isset($option_mappings[$quiz['id']])) {
                    $mapping = json_decode($option_mappings[$quiz['id']], true);
                    $original_answer = array_search((int)$user_answer, $mapping);
                    $is_correct = $original_answer !== false && (int)$original_answer === $quiz['correct_option'];
                }

                $question_score = $is_correct ? $quiz['max_score'] : 0;
                $score += $question_score;

                $results[$quiz['id']] = [
                    'user_answer' => $user_answer,
                    'original_answer' => $original_answer,
                    'is_correct' => $is_correct,
                    'question_score' => $question_score,
                    'max_score' => $quiz['max_score']
                ];

                $stmt = $conn->prepare("
                    INSERT INTO test_results (student_id, test_id, score, attempt_number, course_id, completed_at)
                    VALUES (:student_id, :test_id, :score, :attempt_number, :course_id, NOW())
                    ON DUPLICATE KEY UPDATE score = :score, completed_at = NOW()
                ");
                $stmt->execute([
                    'student_id' => $user['id'],
                    'test_id' => $quiz['id'],
                    'score' => $question_score,
                    'attempt_number' => 1,
                    'course_id' => $course_id
                ]);
            }

            $percentage = $total_score > 0 ? ($score / $total_score) * 100 : 0;
            $response['quiz_results'] = [
                'score' => $score,
                'total_score' => $total_score,
                'percentage' => $percentage,
                'passed' => $percentage >= 75,
                'results' => $results
            ];
        } elseif ($action === 'submit_final_test') {
            $test_answers = $_POST['final_test'] ?? [];
            $option_mappings = $_POST['option_mapping'] ?? [];
            $stmt = $conn->prepare("
                SELECT id, question, option1, option2, option3, option4, correct_option, max_score
                FROM tests
                WHERE course_id = :course_id AND type = 'course'
            ");
            $stmt->execute(['course_id' => $course_id]);
            $final_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($final_tests as &$test) {
                $stmt = $conn->prepare("SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id");
                $stmt->execute(['test_id' => $test['id']]);
                $test['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $score = 0;
            $total_score = 0;
            $results = [];

            foreach ($final_tests as $test) {
                $total_score += $test['max_score'];
                $user_answer = $test_answers[$test['id']] ?? null;
                $original_answer = null;
                $is_correct = false;

                if ($user_answer && isset($option_mappings[$test['id']])) {
                    $mapping = json_decode($option_mappings[$test['id']], true);
                    $original_answer = array_search((int)$user_answer, $mapping);
                    $is_correct = $original_answer !== false && (int)$original_answer === $test['correct_option'];
                }

                $question_score = $is_correct ? $test['max_score'] : 0;
                $score += $question_score;

                $results[$test['id']] = [
                    'user_answer' => $user_answer,
                    'original_answer' => $original_answer,
                    'is_correct' => $is_correct,
                    'question_score' => $question_score,
                    'max_score' => $test['max_score']
                ];

                $stmt = $conn->prepare("
                    INSERT INTO test_results (student_id, test_id, score, attempt_number, course_id, completed_at)
                    VALUES (:student_id, :test_id, :score, :attempt_number, :course_id, NOW())
                    ON DUPLICATE KEY UPDATE score = :score, completed_at = NOW()
                ");
                $stmt->execute([
                    'student_id' => $user['id'],
                    'test_id' => $test['id'],
                    'score' => $question_score,
                    'attempt_number' => 1,
                    'course_id' => $course_id
                ]);
            }

            $percentage = $total_score > 0 ? ($score / $total_score) * 100 : 0;
            $response['final_test_results'] = [
                'score' => $score,
                'total_score' => $total_score,
                'percentage' => $percentage,
                'passed' => $percentage >= 75,
                'results' => $results
            ];
        }
        break;

    default:
        http_response_code(405);
        $response['error'] = 'Phương thức không được hỗ trợ.';
}

$conn = null;
echo json_encode($response);
ob_end_flush();
?>