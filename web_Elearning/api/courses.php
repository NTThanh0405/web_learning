<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Khởi tạo phản hồi mặc định
$response = ['success' => false, 'message' => ''];

// Kiểm tra phương thức yêu cầu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Phương thức yêu cầu không hợp lệ. Chỉ hỗ trợ POST.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Lấy kết nối cơ sở dữ liệu
$conn = getDBConnection();

// Lấy hành động từ tham số
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Đăng ký khóa học
    if ($action === 'enroll') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$course_id || !$user_id) {
            $response['message'] = 'Thông tin khóa học hoặc người dùng không hợp lệ.';
            error_log("Invalid input: course_id=$course_id, user_id=$user_id");
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Kiểm tra khóa học tồn tại và đang hoạt động
        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = :course_id AND status = 'active'");
        $stmt->execute(['course_id' => $course_id]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Khóa học không tồn tại hoặc không hoạt động.';
            error_log("Course not found: course_id=$course_id");
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Kiểm tra vai trò người dùng
        $user = getCurrentUser();
        if (!$user || $user['id'] != $user_id || $user['role'] !== 'student') {
            $response['message'] = 'Bạn không có quyền đăng ký khóa học.';
            error_log("Invalid user for enroll: user_id=$user_id, role=" . ($user['role'] ?? 'none'));
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Kiểm tra trạng thái ghi danh hiện tại
        $stmt = $conn->prepare("
            SELECT status 
            FROM course_enrollments 
            WHERE course_id = :course_id AND student_id = :student_id
        ");
        $stmt->execute(['course_id' => $course_id, 'student_id' => $user_id]);
        $existing_enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_enrollment) {
            if ($existing_enrollment['status'] === 'pending') {
                $response['success'] = true;
                $response['message'] = 'Yêu cầu đăng ký đã được gửi trước đó và đang chờ duyệt.';
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            } elseif ($existing_enrollment['status'] === 'approved') {
                $response['success'] = true;
                $response['message'] = 'Bạn đã được phê duyệt cho khóa học này.';
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            } elseif ($existing_enrollment['status'] === 'rejected') {
                // Cập nhật trạng thái thành pending thay vì từ chối
                $stmt = $conn->prepare("
                    UPDATE course_enrollments 
                    SET status = 'pending', enrolled_at = NOW()
                    WHERE course_id = :course_id AND student_id = :student_id
                ");
                $stmt->execute(['course_id' => $course_id, 'student_id' => $user_id]);
                $response['success'] = true;
                $response['message'] = 'Đã gửi lại yêu cầu đăng ký thành công, đang chờ duyệt.';
            }
        } else {
            // Ghi danh mới
            $stmt = $conn->prepare("
                INSERT INTO course_enrollments (course_id, student_id, status, enrolled_at)
                VALUES (:course_id, :student_id, 'pending', NOW())
            ");
            $stmt->execute(['course_id' => $course_id, 'student_id' => $user_id]);
            $response['success'] = true;
            $response['message'] = 'Đã gửi yêu cầu đăng ký thành công, đang chờ duyệt.';
        }
    }

    // Lấy thông tin khóa học cơ bản
    elseif ($action === 'get_course_info') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

        if (!$course_id) {
            $response['message'] = 'Thiếu thông tin khóa học.';
        } else {
            $stmt = $conn->prepare("
                SELECT c.id, c.title, c.description, u.full_name AS teacher_name, c.thumbnail
                FROM courses c
                JOIN users u ON c.teacher_id = u.id
                WHERE c.id = :course_id AND c.status = 'active'
            ");
            $stmt->execute(['course_id' => $course_id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($course) {
                $course['thumbnail'] = $course['thumbnail'] ? BASE_URL . $course['thumbnail'] : BASE_URL . 'assets/images/course_default.jpg';
                $response['success'] = true;
                $response['data'] = $course;
            } else {
                $response['message'] = 'Không tìm thấy khóa học.';
                error_log("Course not found: course_id=$course_id");
            }
        }
    }

    // Lấy dữ liệu đầy đủ của khóa học
    elseif ($action === 'get_full_course_data') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $user = getCurrentUser();

        if (!$course_id) {
            $response['message'] = 'Thiếu thông tin khóa học.';
        } elseif (!$user || ($user['role'] !== 'student' && $user['role'] !== 'teacher')) {
            $response['message'] = 'Bạn không có quyền truy cập.';
            error_log("Invalid role for get_full_course_data: user_id=" . ($user['id'] ?? 'none') . ", role=" . ($user['role'] ?? 'none'));
        } else {
            // Kiểm tra quyền truy cập
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM course_enrollments 
                WHERE course_id = :course_id 
                AND student_id = :user_id 
                AND status = 'approved'
            ");
            $stmt->execute(['course_id' => $course_id, 'user_id' => $user['id']]);
            $is_enrolled = $stmt->fetchColumn();

            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM courses 
                WHERE id = :course_id 
                AND teacher_id = :user_id
            ");
            $stmt->execute(['course_id' => $course_id, 'user_id' => $user['id']]);
            $is_teacher = $stmt->fetchColumn();

            if (!$is_enrolled && !$is_teacher) {
                $response['message'] = 'Bạn không có quyền truy cập khóa học này.';
                error_log("Access denied: course_id=$course_id, user_id=" . $user['id']);
            } else {
                // Lấy thông tin khóa học
                $stmt = $conn->prepare("
                    SELECT c.id, c.title, c.description, u.full_name AS teacher_name, c.thumbnail
                    FROM courses c
                    JOIN users u ON c.teacher_id = u.id
                    WHERE c.id = :course_id AND c.status = 'active'
                ");
                $stmt->execute(['course_id' => $course_id]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$course) {
                    $response['message'] = 'Khóa học không tồn tại hoặc không hoạt động.';
                    error_log("Course not found in get_full_course_data: course_id=$course_id");
                } else {
                    $course['thumbnail'] = $course['thumbnail'] ? BASE_URL . $course['thumbnail'] : BASE_URL . 'assets/images/course_default.jpg';

                    // Lấy danh sách bài học
                    $stmt = $conn->prepare("
                        SELECT l.id, l.title, l.description, l.created_at, 'lesson' AS type
                        FROM lessons l
                        WHERE l.course_id = :course_id
                        ORDER BY l.created_at
                    ");
                    $stmt->execute(['course_id' => $course_id]);
                    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Lấy danh sách bài kiểm tra cuối khóa
                    $stmt = $conn->prepare("
                        SELECT t.id, t.question AS title, 'Bài kiểm tra cuối khóa' AS description, 
                               t.created_at, 'test' AS type, t.max_score
                        FROM tests t
                        WHERE t.course_id = :course_id AND t.type = 'course'
                        ORDER BY t.created_at
                    ");
                    $stmt->execute(['course_id' => $course_id]);
                    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $response['success'] = true;
                    $response['data'] = [
                        'course' => $course,
                        'items' => array_merge($lessons, $tests)
                    ];
                }
            }
        }
    }

    // Lấy chi tiết bài kiểm tra
    elseif ($action === 'get_test') {
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        $user = getCurrentUser();

        if (!$course_id || !$test_id) {
            $response['message'] = 'Thiếu thông tin khóa học hoặc bài kiểm tra.';
        } elseif (!$user || $user['role'] !== 'student') {
            $response['message'] = 'Bạn không có quyền truy cập bài kiểm tra.';
            error_log("Invalid role for get_test: user_id=" . ($user['id'] ?? 'none') . ", role=" . ($user['role'] ?? 'none'));
        } else {
            // Kiểm tra ghi danh
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM course_enrollments 
                WHERE course_id = :course_id 
                AND student_id = :user_id 
                AND status = 'approved'
            ");
            $stmt->execute(['course_id' => $course_id, 'user_id' => $user['id']]);
            $is_enrolled = $stmt->fetchColumn();

            if (!$is_enrolled) {
                $response['message'] = 'Bạn chưa ghi danh hoặc chưa được phê duyệt cho khóa học này.';
                error_log("Not enrolled: course_id=$course_id, user_id=" . $user['id']);
            } else {
                // Lấy thông tin bài kiểm tra
                $stmt = $conn->prepare("
                    SELECT id, question, option1, option2, option3, option4, correct_option, max_score, type
                    FROM tests
                    WHERE id = :test_id AND course_id = :course_id
                ");
                $stmt->execute(['test_id' => $test_id, 'course_id' => $course_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($test) {
                    // Lấy hình ảnh liên quan
                    $stmt = $conn->prepare("
                        SELECT type, option_index, image_path
                        FROM test_images
                        WHERE test_id = :test_id
                    ");
                    $stmt->execute(['test_id' => $test_id]);
                    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $test['images'] = [
                        'question' => [],
                        'options' => []
                    ];
                    foreach ($images as $image) {
                        $image['image_path'] = BASE_URL . $image['image_path'];
                        if ($image['type'] === 'question') {
                            $test['images']['question'][] = $image['image_path'];
                        } elseif ($image['type'] === 'option' && $image['option_index'] >= 1 && $image['option_index'] <= 4) {
                            $test['images']['options'][$image['option_index']][] = $image['image_path'];
                        }
                    }

                    // Loại bỏ correct_option để tránh lộ đáp án
                    unset($test['correct_option']);
                    $response['success'] = true;
                    $response['data'] = $test;
                } else {
                    $response['message'] = 'Không tìm thấy bài kiểm tra.';
                    error_log("Test not found: test_id=$test_id, course_id=$course_id");
                }
            }
        }
    }

    // Nộp bài kiểm tra
    elseif ($action === 'submit_test') {
        $test_id = filter_input(INPUT_POST, 'test_id', FILTER_VALIDATE_INT);
        $answer = filter_input(INPUT_POST, 'answer', FILTER_VALIDATE_INT);
        $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        $user = getCurrentUser();

        if (!$test_id || !$answer || !$course_id || !($answer >= 1 && $answer <= 4)) {
            $response['message'] = 'Thiếu thông tin hoặc câu trả lời không hợp lệ.';
        } elseif (!$user || $user['role'] !== 'student') {
            $response['message'] = 'Bạn không có quyền nộp bài kiểm tra.';
            error_log("Invalid role for submit_test: user_id=" . ($user['id'] ?? 'none') . ", role=" . ($user['role'] ?? 'none'));
        } else {
            // Kiểm tra ghi danh
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM course_enrollments 
                WHERE course_id = :course_id 
                AND student_id = :user_id 
                AND status = 'approved'
            ");
            $stmt->execute(['course_id' => $course_id, 'user_id' => $user['id']]);
            $is_enrolled = $stmt->fetchColumn();

            if (!$is_enrolled) {
                $response['message'] = 'Bạn chưa ghi danh hoặc chưa được phê duyệt cho khóa học này.';
                error_log("Not enrolled for submit_test: course_id=$course_id, user_id=" . $user['id']);
            } else {
                // Lấy thông tin bài kiểm tra
                $stmt = $conn->prepare("
                    SELECT course_id, correct_option, max_score
                    FROM tests
                    WHERE id = :test_id AND course_id = :course_id
                ");
                $stmt->execute(['test_id' => $test_id, 'course_id' => $course_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($test) {
                    // Tính điểm
                    $score = ($answer == $test['correct_option']) ? $test['max_score'] : 0;

                    // Lưu kết quả
                    $stmt = $conn->prepare("
                        INSERT INTO test_results (student_id, test_id, score, attempt_number, course_id, completed_at)
                        VALUES (:student_id, :test_id, :score, 1, :course_id, NOW())
                        ON DUPLICATE KEY UPDATE score = :score, completed_at = NOW()
                    ");
                    $stmt->execute([
                        'student_id' => $user['id'],
                        'test_id' => $test_id,
                        'score' => $score,
                        'course_id' => $test['course_id']
                    ]);

                    $response['success'] = true;
                    $response['message'] = 'Nộp bài kiểm tra thành công.';
                    $response['data'] = [
                        'score' => $score,
                        'max_score' => $test['max_score'],
                        'is_correct' => $answer == $test['correct_option']
                    ];
                } else {
                    $response['message'] = 'Không tìm thấy bài kiểm tra.';
                    error_log("Test not found in submit_test: test_id=$test_id, course_id=$course_id");
                }
            }
        }
    }

    else {
        $response['message'] = 'Hành động không được hỗ trợ.';
        error_log("Invalid action: $action");
    }
} catch (Exception $e) {
    $response['message'] = 'Lỗi hệ thống. Vui lòng thử lại sau.';
    error_log("API error in courses.php: " . $e->getMessage());
}

// Đóng kết nối và trả về phản hồi
$conn = null;
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>