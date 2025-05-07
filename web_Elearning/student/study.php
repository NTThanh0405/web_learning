<?php
ob_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Kiểm tra quyền truy cập
redirectIfNotLoggedIn();
redirectIfNotRole('student');

$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    error_log("User session invalid: " . print_r($user, true));
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('user_id', '', time() - 3600, "/");
    setcookie('role', '', time() - 3600, "/");
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

// Xử lý tham số đầu vào
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT) ?: null;
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?: '';
$lesson_id = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT) ?: null;
$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT) ?: null;
$current_page = filter_input(INPUT_GET, 'current_page', FILTER_VALIDATE_INT) ?: 1;
$current_page = max(1, $current_page);
$show_quiz = isset($_GET['show_quiz']) && $_GET['show_quiz'] === 'true';
$show_final_test = isset($_GET['show_final_test']) && $_GET['show_final_test'] === 'true';

// Hàm gọi API
function callStudyApi($method, $params = [], $data = []) {
    $url = BASE_URL . 'api/study.php?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($http_code >= 400) {
        return ['error' => $result['error'] ?? 'Lỗi không xác định từ API'];
    }
    
    return $result;
}

// Khởi tạo biến
$course = null;
$lessons = [];
$lesson_items = [];
$quizzes = [];
$final_tests = [];
$quiz_results = null;
$final_test_results = null;
$error_message = '';
$current_lesson = null;

// Lấy dữ liệu từ API
if (!$course_id) {
    $api_response = callStudyApi('GET', []);
    if (isset($api_response['error'])) {
        $error_message = $api_response['error'];
    } else {
        $enrolled_courses = $api_response['enrolled_courses'] ?? [];
    }
} elseif ($course_id) {
    $params = [
        'course_id' => $course_id,
        'action' => $action,
        'lesson_id' => $lesson_id,
        'item_id' => $item_id,
        'current_page' => $current_page,
        'show_quiz' => $show_quiz ? 'true' : 'false',
        'show_final_test' => $show_final_test ? 'true' : 'false'
    ];
    $api_response = callStudyApi('GET', $params);
    
    if (isset($api_response['error'])) {
        $error_message = $api_response['error'];
    } else {
        $course = $api_response['course'] ?? null;
        $lessons = $api_response['lessons'] ?? [];
        $current_lesson = $api_response['current_lesson'] ?? null;
        $lesson_items = $api_response['lesson_items'] ?? [];
        $quizzes = $api_response['quizzes'] ?? [];
        $final_tests = $api_response['final_tests'] ?? [];
    }
}

// Xử lý nộp bài kiểm tra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_quiz' && $course_id) {
    $api_response = callStudyApi('POST', [
        'course_id' => $course_id,
        'lesson_id' => $lesson_id,
        'action' => 'submit_quiz'
    ], $_POST);
    
    if (isset($api_response['error'])) {
        $error_message = $api_response['error'];
    } else {
        $quiz_results = $api_response['quiz_results'];
        $current_lesson = callStudyApi('GET', [
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'action' => 'view_lesson'
        ])['current_lesson'] ?? null;
    }
}

// Xử lý nộp bài kiểm tra cuối khóa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_final_test' && $course_id) {
    $api_response = callStudyApi('POST', [
        'course_id' => $course_id,
        'action' => 'submit_final_test'
    ], $_POST);
    
    if (isset($api_response['error'])) {
        $error_message = $api_response['error'];
    } else {
        $final_test_results = $api_response['final_test_results'];
    }
}

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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Học tập - E-Learning</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .course-image { width: 100%; height: 200px; object-fit: cover; }
        .course-card { margin-bottom: 20px; }
        .quiz-question .options { margin-left: 20px; }
        .navigation-buttons { margin-top: 20px; }
        .content-left, .content-right { min-height: 200px; }
        .quiz-question .alert { margin-top: 15px; padding: 10px; }
        .quiz-question .alert-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .quiz-question .alert-danger { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .quiz-question .alert-info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .test-image { max-width: 150px; margin: 5px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="course-recommendations">
            <?php if (!$course_id): ?>
                <h2>Khóa học đã tham gia</h2>
                <?php if (empty($enrolled_courses)): ?>
                    <p>Bạn chưa tham gia khóa học nào.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($enrolled_courses as $course): ?>
                            <div class="col-md-4">
                                <div class="course-card">
                                    <img src="<?php echo htmlspecialchars($course['thumbnail'] ? BASE_URL . $course['thumbnail'] : BASE_URL . 'assets/images/course_default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($course['title']); ?>" 
                                         class="course-image">
                                    <div class="course-card-body p-3">
                                        <p class="font-weight-bold"><?php echo htmlspecialchars($course['title']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge badge-info">GV: <?php echo htmlspecialchars($course['teacher_name']); ?></span>
                                            <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course['id']); ?>" 
                                               class="btn btn-danger btn-sm">Xem</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($course && !$lesson_id && !$show_final_test): ?>
                <h2>Khóa học: <?php echo htmlspecialchars($course['title']); ?></h2>
                <h3>Danh sách chương</h3>
                <?php if (empty($lessons)): ?>
                    <div class="alert alert-info">Đang cập nhật nội dung khóa học.</div>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($lessons as $lesson): ?>
                            <li class="list-group-item">
                                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=view_lesson&lesson_id=' . $lesson['id']); ?>">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (hasCompletedAllLessons($conn, $course_id, $user['id']) && hasPassedAllLessonQuizzes($conn, $course_id, $user['id'])): ?>
                        <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=final_test&show_final_test=true'); ?>" 
                           class="btn btn-primary mr-2">Làm bài kiểm tra cuối khóa</a>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            Bạn cần hoàn thành tất cả các bài học và đạt tất cả các bài kiểm tra (điểm ≥ 75%) trước khi làm bài kiểm tra cuối khóa.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study'); ?>" 
                   class="btn btn-secondary">Quay lại danh sách khóa học</a>

            <?php elseif ($course && $action === 'view_lesson' && $lesson_id && !$item_id && !$show_quiz): ?>
                <h2>Chương: <?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                <h3>Danh sách mục</h3>
                <?php if (empty($lesson_items)): ?>
                    <p>Chưa có mục nào trong chương này.</p>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($lesson_items as $item): ?>
                            <li class="list-group-item">
                                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=view_lesson&lesson_id=' . $lesson_id . '&item_id=' . $item['id']); ?>">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id); ?>" 
                   class="btn btn-secondary">Quay lại danh sách chương</a>

            <?php elseif ($course && $action === 'view_lesson' && $lesson_id && $item_id && !$show_quiz): ?>
                <h2>Chương: <?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                <?php
                $current_item = null;
                foreach ($lesson_items as $item) {
                    if ($item['id'] == $item_id) {
                        $current_item = $item;
                        break;
                    }
                }

                if ($current_item && !empty($current_item['pages'])):
                    $total_pages = count($current_item['pages']);
                    $current_page = min(max(1, $current_page), $total_pages);
                    $current_page_data = null;
                    foreach ($current_item['pages'] as $page) {
                        if ($page['page_number'] == $current_page) {
                            $current_page_data = $page;
                            break;
                        }
                    }
                    if (!$current_page_data && !empty($current_item['pages'])) {
                        $current_page = $current_item['pages'][0]['page_number'];
                        $current_page_data = $current_item['pages'][0];
                        error_log("Invalid page number, reset to first page: item_id=$item_id, requested_page=$current_page");
                    }
                ?>
                    <h3>Mục: <?php echo htmlspecialchars($current_item['title']); ?></h3>
                    <?php if ($current_page_data): ?>
                        <?php
                        $conn = getDBConnection();
                        $stmt = $conn->prepare("
                            INSERT INTO user_page_progress (user_id, page_id, completed, completed_at)
                            VALUES (:user_id, :page_id, 1, NOW())
                            ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
                        ");
                        $stmt->execute(['user_id' => $user['id'], 'page_id' => $current_page_data['id']]);
                        ?>
                        <div class="row page-content" data-content="<?php echo htmlspecialchars($current_page_data['content']); ?>">
                            <div class="col-md-6 content-left">
                                <h5><?php echo htmlspecialchars($current_page_data['title'] ?? 'Trang ' . $current_page); ?></h5>
                            </div>
                            <div class="col-md-6 content-right"></div>
                        </div>
                        <div class="d-flex justify-content-between navigation-buttons">
                            <button class="btn btn-secondary prev-btn" 
                                    <?php echo $current_page > 1 ? "data-page='" . ($current_page - 1) . "' data-lesson-id='$lesson_id' data-course-id='$course_id' data-item-id='$item_id'" : 'disabled'; ?>>
                                Prev
                            </button>
                            <div class="button-group">
                                <?php if ($current_page < $total_pages): ?>
                                    <button class="btn btn-secondary next-btn" 
                                            data-page="<?php echo $current_page + 1; ?>" 
                                            data-lesson-id="<?php echo $lesson_id; ?>" 
                                            data-course-id="<?php echo $course_id; ?>" 
                                            data-item-id="<?php echo $item_id; ?>">
                                        Next
                                    </button>
                                <?php endif; ?>
                                <?php if ($current_page == $total_pages && !empty($quizzes)): ?>
                                    <button class="btn btn-primary quiz-btn" 
                                            data-lesson-id="<?php echo $lesson_id; ?>" 
                                            data-course-id="<?php echo $course_id; ?>">
                                        Làm bài trắc nghiệm
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>Không tìm thấy trang hợp lệ trong mục này.</p>
                        <?php error_log("No valid page found: item_id=$item_id, course_id=$course_id, lesson_id=$lesson_id"); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Chưa có trang nào trong mục này.</p>
                    <?php error_log("No pages found: item_id=$item_id, course_id=$course_id, lesson_id=$lesson_id"); ?>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=view_lesson&lesson_id=' . $lesson_id); ?>" 
                   class="btn btn-secondary mt-3">Quay lại danh sách mục</a>

            <?php elseif ($course && $action === 'view_lesson' && $lesson_id && $show_quiz && !$quiz_results): ?>
                <?php
                $conn = getDBConnection();
                if (!hasCompletedLessonPages($conn, $lesson_id, $user['id'])): ?>
                    <div class="alert alert-warning">
                        Bạn cần hoàn thành tất cả các trang học trước khi làm bài trắc nghiệm.
                    </div>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=view_lesson&lesson_id=' . $lesson_id); ?>" 
                       class="btn btn-secondary mt-3">Quay lại danh sách mục</a>
                <?php else: ?>
                    <h2>Chương: <?php echo htmlspecialchars($current_lesson['title']); ?></h2>
                    <h3>Câu hỏi trắc nghiệm</h3>
                    <?php if (empty($quizzes)): ?>
                        <p>Chưa có câu hỏi trắc nghiệm nào cho chương này.</p>
                    <?php else:
                        $session_key = "quiz_order_{$course_id}_{$lesson_id}";
                        if (!isset($_SESSION[$session_key]) || !isset($_GET['retry'])) {
                            shuffle($quizzes);
                            $_SESSION[$session_key] = [
                                'order' => array_column($quizzes, 'id'),
                                'mappings' => []
                            ];
                        } else {
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
                        }
                    ?>
                        <form method="POST" 
                              action="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=submit_quiz&lesson_id=' . $lesson_id); ?>">
                            <?php
                            $option_mappings = [];
                            foreach ($quizzes as $index => $quiz):
                                $options = [
                                    1 => ['text' => $quiz['option1'], 'images' => []],
                                    2 => ['text' => $quiz['option2'], 'images' => []],
                                    3 => ['text' => $quiz['option3'], 'images' => []],
                                    4 => ['text' => $quiz['option4'], 'images' => []]
                                ];
                                foreach ($quiz['images'] as $image) {
                                    if ($image['type'] === 'option' && isset($options[$image['option_index']])) {
                                        $options[$image['option_index']]['images'][] = $image['image_path'];
                                    }
                                }
                                $question_images = array_filter($quiz['images'], fn($img) => $img['type'] === 'question');
                                $keys = array_keys($options);
                                
                                if (isset($_SESSION[$session_key]['mappings'][$quiz['id']]) && isset($_GET['retry'])) {
                                    $keys = $_SESSION[$session_key]['mappings'][$quiz['id']]['keys'];
                                    $new_option_mapping = $_SESSION[$session_key]['mappings'][$quiz['id']]['mapping'];
                                } else {
                                    shuffle($keys);
                                    $new_options = [];
                                    $new_option_mapping = [];
                                    foreach ($keys as $new_index => $key) {
                                        $new_options[$new_index + 1] = $options[$key];
                                        $new_option_mapping[$key] = $new_index + 1;
                                    }
                                    $_SESSION[$session_key]['mappings'][$quiz['id']] = [
                                        'keys' => $keys,
                                        'mapping' => $new_option_mapping
                                    ];
                                }
                                $option_mappings[$quiz['id']] = $new_option_mapping;
                                $new_options = [];
                                foreach ($keys as $new_index => $key) {
                                    $new_options[$new_index + 1] = $options[$key];
                                }
                            ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <p><strong>Câu hỏi <?php echo $index + 1; ?>:</strong> 
                                           <?php echo htmlspecialchars($quiz['question']); ?> 
                                           (<?php echo $quiz['max_score']; ?> điểm)
                                        </p>
                                        <?php if (!empty($question_images)): ?>
                                            <div>
                                                <?php foreach ($question_images as $img): ?>
                                                    <img src="<?php echo htmlspecialchars(BASE_URL . $img['image_path']); ?>" 
                                                         alt="Question Image" class="test-image">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <div class="form-check">
                                                <input type="radio" 
                                                       name="quiz[<?php echo $quiz['id']; ?>]" 
                                                       value="<?php echo $i; ?>" 
                                                       class="form-check-input">
                                                <label class="form-check-label">
                                                    <?php echo htmlspecialchars($new_options[$i]['text']); ?>
                                                    <?php foreach ($new_options[$i]['images'] as $img): ?>
                                                        <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                                             alt="Option Image" class="test-image">
                                                    <?php endforeach; ?>
                                                </label>
                                            </div>
                                        <?php endfor; ?>
                                        <input type="hidden" 
                                               name="option_mapping[<?php echo $quiz['id']; ?>]" 
                                               value="<?php echo htmlspecialchars(json_encode($new_option_mapping)); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Nộp bài</button>
                        </form>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id); ?>" 
                       class="btn btn-secondary mt-3">Quay lại danh sách chương</a>
                <?php endif; ?>

            <?php elseif ($course && $action === 'submit_quiz' && $quiz_results): ?>
                <h2>Chương: <?php echo htmlspecialchars($current_lesson['title'] ?? 'Không xác định'); ?></h2>
                <h3>Kết quả bài trắc nghiệm</h3>
                <p><strong>Điểm số:</strong> 
                   <?php echo htmlspecialchars($quiz_results['score']); ?>/<?php echo htmlspecialchars($quiz_results['total_score']); ?> 
                   (<?php echo round($quiz_results['percentage'], 2); ?>%)
                </p>
                <p><strong>Kết quả:</strong> 
                   <?php echo $quiz_results['passed'] ? '<span class="text-success">Đạt</span>' : '<span class="text-danger">Không đạt</span>'; ?> 
                   (Cần ≥ 75% để qua)
                </p>
                <?php
                $session_key = "quiz_order_{$course_id}_{$lesson_id}";
                $ordered_quizzes = [];
                if (isset($_SESSION[$session_key])) {
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
                }
                foreach ($quizzes as $index => $quiz):
                    $options = [
                        1 => ['text' => $quiz['option1'], 'images' => []],
                        2 => ['text' => $quiz['option2'], 'images' => []],
                        3 => ['text' => $quiz['option3'], 'images' => []],
                        4 => ['text' => $quiz['option4'], 'images' => []]
                    ];
                    foreach ($quiz['images'] as $image) {
                        if ($image['type'] === 'option' && isset($options[$image['option_index']])) {
                            $options[$image['option_index']]['images'][] = $image['image_path'];
                        }
                    }
                    $question_images = array_filter($quiz['images'], fn($img) => $img['type'] === 'question');
                ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <p><strong>Câu hỏi <?php echo $index + 1; ?>:</strong> 
                               <?php echo htmlspecialchars($quiz['question']); ?> 
                               (<?php echo $quiz['max_score']; ?> điểm)
                            </p>
                            <?php if (!empty($question_images)): ?>
                                <div>
                                    <?php foreach ($question_images as $img): ?>
                                        <img src="<?php echo htmlspecialchars(BASE_URL . $img['image_path']); ?>" 
                                             alt="Question Image" class="test-image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p><strong>Câu trả lời của bạn:</strong> 
                               <?php 
                               $user_answer = $quiz_results['results'][$quiz['id']]['user_answer'] ?? null;
                               echo htmlspecialchars($user_answer && isset($options[$user_answer]) ? $options[$user_answer]['text'] : 'Không chọn'); 
                               ?> 
                               <?php echo $quiz_results['results'][$quiz['id']]['is_correct'] ? '<span class="text-success">✔ Đúng</span>' : '<span class="text-danger">✘ Sai</span>'; ?>
                            </p>
                            <?php if ($user_answer && !empty($options[$user_answer]['images'])): ?>
                                <div>
                                    <?php foreach ($options[$user_answer]['images'] as $img): ?>
                                        <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                             alt="User Answer Image" class="test-image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$quiz_results['results'][$quiz['id']]['is_correct']): ?>
                                <p><strong>Đáp án đúng:</strong> 
                                   <?php echo htmlspecialchars($options[$quiz['correct_option']]['text']); ?>
                                </p>
                                <?php if (!empty($options[$quiz['correct_option']]['images'])): ?>
                                    <div>
                                        <?php foreach ($options[$quiz['correct_option']]['images'] as $img): ?>
                                            <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                                 alt="Correct Answer Image" class="test-image">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=view_lesson&lesson_id=' . $lesson_id . '&show_quiz=true&retry=true'); ?>" 
                       class="btn btn-primary mr-2">Làm lại</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id); ?>" 
                       class="btn btn-secondary">Quay lại danh sách chương</a>
                </div>

            <?php elseif ($course && $action === 'final_test' && $show_final_test && !$final_test_results): ?>
                <h2>Bài kiểm tra cuối khóa: <?php echo htmlspecialchars($course['title']); ?></h2>
                <h3>Câu hỏi kiểm tra</h3>
                <?php if (empty($final_tests)): ?>
                    <p>Chưa có câu hỏi nào trong bài kiểm tra cuối khóa.</p>
                <?php else:
                    shuffle($final_tests);
                ?>
                    <form method="POST" 
                          action="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=submit_final_test'); ?>">
                        <?php
                        $option_mappings = [];
                        foreach ($final_tests as $index => $test):
                            $options = [
                                1 => ['text' => $test['option1'], 'images' => []],
                                2 => ['text' => $test['option2'], 'images' => []],
                                3 => ['text' => $test['option3'], 'images' => []],
                                4 => ['text' => $test['option4'], 'images' => []]
                            ];
                            foreach ($test['images'] as $image) {
                                if ($image['type'] === 'option' && isset($options[$image['option_index']])) {
                                    $options[$image['option_index']]['images'][] = $image['image_path'];
                                }
                            }
                            $question_images = array_filter($test['images'], fn($img) => $img['type'] === 'question');
                            $keys = array_keys($options);
                            shuffle($keys);

                            $new_options = [];
                            $new_option_mapping = [];
                            foreach ($keys as $new_index => $key) {
                                $new_options[$new_index + 1] = $options[$key];
                                $new_option_mapping[$key] = $new_index + 1;
                            }
                            $option_mappings[$test['id']] = $new_option_mapping;
                        ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <p><strong>Câu hỏi <?php echo $index + 1; ?>:</strong> 
                                       <?php echo htmlspecialchars($test['question']); ?> 
                                       (<?php echo $test['max_score']; ?> điểm)
                                    </p>
                                    <?php if (!empty($question_images)): ?>
                                        <div>
                                            <?php foreach ($question_images as $img): ?>
                                                <img src="<?php echo htmlspecialchars(BASE_URL . $img['image_path']); ?>" 
                                                     alt="Question Image" class="test-image">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <div class="form-check">
                                            <input type="radio" 
                                                   name="final_test[<?php echo $test['id']; ?>]" 
                                                   value="<?php echo $i; ?>" 
                                                   class="form-check-input">
                                            <label class="form-check-label">
                                                <?php echo htmlspecialchars($new_options[$i]['text']); ?>
                                                <?php foreach ($new_options[$i]['images'] as $img): ?>
                                                    <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                                         alt="Option Image" class="test-image">
                                                <?php endforeach; ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                    <input type="hidden" 
                                           name="option_mapping[<?php echo $test['id']; ?>]" 
                                           value="<?php echo htmlspecialchars(json_encode($new_option_mapping)); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary">Nộp bài</button>
                    </form>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id); ?>" 
                   class="btn btn-secondary mt-3">Quay lại danh sách chương</a>

            <?php elseif ($course && $action === 'submit_final_test' && $final_test_results): ?>
                <h2>Bài kiểm tra cuối khóa: <?php echo htmlspecialchars($course['title']); ?></h2>
                <h3>Kết quả bài kiểm tra</h3>
                <p><strong>Điểm số:</strong> 
                   <?php echo htmlspecialchars($final_test_results['score']); ?>/<?php echo htmlspecialchars($final_test_results['total_score']); ?> 
                   (<?php echo round($final_test_results['percentage'], 2); ?>%)
                </p>
                <p><strong>Kết quả:</strong> 
                   <?php echo $final_test_results['passed'] ? '<span class="text-success">Đạt</span>' : '<span class="text-danger">Không đạt</span>'; ?> 
                   (Cần ≥ 75% để qua)
                </p>
                <?php foreach ($final_tests as $index => $test):
                    $options = [
                        1 => ['text' => $test['option1'], 'images' => []],
                        2 => ['text' => $test['option2'], 'images' => []],
                        3 => ['text' => $test['option3'], 'images' => []],
                        4 => ['text' => $test['option4'], 'images' => []]
                    ];
                    foreach ($test['images'] as $image) {
                        if ($image['type'] === 'option' && isset($options[$image['option_index']])) {
                            $options[$image['option_index']]['images'][] = $image['image_path'];
                        }
                    }
                    $question_images = array_filter($test['images'], fn($img) => $img['type'] === 'question');
                ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <p><strong>Câu hỏi <?php echo $index + 1; ?>:</strong> 
                               <?php echo htmlspecialchars($test['question']); ?> 
                               (<?php echo $test['max_score']; ?> điểm)
                            </p>
                            <?php if (!empty($question_images)): ?>
                                <div>
                                    <?php foreach ($question_images as $img): ?>
                                        <img src="<?php echo htmlspecialchars(BASE_URL . $img['image_path']); ?>" 
                                             alt="Question Image" class="test-image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <p><strong>Câu trả lời của bạn:</strong> 
                               <?php 
                               $user_answer = $final_test_results['results'][$test['id']]['user_answer'] ?? null;
                               echo htmlspecialchars($user_answer && isset($options[$user_answer]) ? $options[$user_answer]['text'] : 'Không chọn'); 
                               ?> 
                               <?php echo $final_test_results['results'][$test['id']]['is_correct'] ? '<span class="text-success">✔ Đúng</span>' : '<span class="text-danger">✘ Sai</span>'; ?>
                            </p>
                            <?php if ($user_answer && !empty($options[$user_answer]['images'])): ?>
                                <div>
                                    <?php foreach ($options[$user_answer]['images'] as $img): ?>
                                        <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                             alt="User Answer Image" class="test-image">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$final_test_results['results'][$test['id']]['is_correct']): ?>
                                <p><strong>Đáp án đúng:</strong> 
                                   <?php echo htmlspecialchars($options[$test['correct_option']]['text']); ?>
                                </p>
                                <?php if (!empty($options[$test['correct_option']]['images'])): ?>
                                    <div>
                                        <?php foreach ($options[$test['correct_option']]['images'] as $img): ?>
                                            <img src="<?php echo htmlspecialchars(BASE_URL . $img); ?>" 
                                                 alt="Correct Answer Image" class="test-image">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id . '&action=final_test&show_final_test=true'); ?>" 
                       class="btn btn-primary mr-2">Làm lại</a>
                    <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study&course_id=' . $course_id); ?>" 
                       class="btn btn-secondary">Quay lại danh sách chương</a>
                </div>

            <?php else: ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message ?: 'Có lỗi xảy ra. Vui lòng thử lại hoặc liên hệ hỗ trợ.'); ?>
                </div>
                <a href="<?php echo htmlspecialchars(BASE_URL . 'student/index.php?tab=study'); ?>" 
                   class="btn btn-secondary">Quay lại</a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.page-content').each(function() {
            let content = $(this).data('content');
            let parts = content.split('||SPLIT||');
            $(this).find('.content-left').append(parts[0]?.replace(/\n/g, '<br>') || content.replace(/\n/g, '<br>'));
            $(this).find('.content-right').html(parts[1]?.replace(/\n/g, '<br>') || 'Không có nội dung.');
        });

        function getNextLessonId(currentLessonId) {
            let lessons = <?php echo json_encode(array_column($lessons, 'id')); ?>;
            let currentIndex = lessons.indexOf(parseInt(currentLessonId));
            if (currentIndex !== -1 && currentIndex < lessons.length - 1) {
                return lessons[currentIndex + 1];
            }
            return null;
        }

        function getPreviousLessonId(currentLessonId) {
            let lessons = <?php echo json_encode(array_column($lessons, 'id')); ?>;
            let currentIndex = lessons.indexOf(parseInt(currentLessonId));
            if (currentIndex > 0) {
                return lessons[currentIndex - 1];
            }
            return null;
        }

        function updateNavigationButtons(courseId, lessonId, itemId, currentPage, totalPages) {
            let $buttonGroup = $('.button-group');
            $buttonGroup.empty();

            if (currentPage > 1) {
                $('.prev-btn').attr('disabled', false)
                    .data({ page: currentPage - 1, 'lesson-id': lessonId, 'course-id': courseId, 'item-id': itemId })
                    .removeClass('prev-lesson-btn');
            } else {
                let prevLessonId = getPreviousLessonId(lessonId);
                if (prevLessonId) {
                    $('.prev-btn').attr('disabled', false)
                        .data({ 'lesson-id': prevLessonId, 'course-id': courseId })
                        .addClass('prev-lesson-btn')
                        .removeData('page item-id');
                } else {
                    $('.prev-btn').attr('disabled', true).removeClass('prev-lesson-btn');
                }
            }

            if (currentPage < totalPages) {
                $buttonGroup.append(
                    `<button class="btn btn-secondary next-btn" ` +
                    `data-page="${currentPage + 1}" ` +
                    `data-lesson-id="${lessonId}" ` +
                    `data-course-id="${courseId}" ` +
                    `data-item-id="${itemId}">Next</button>`
                );
            } else {
                let nextLessonId = getNextLessonId(lessonId);
                if (nextLessonId) {
                    $buttonGroup.append(
                        `<a href="<?php echo htmlspecialchars(BASE_URL); ?>student/index.php?tab=study&course_id=${courseId}&action=view_lesson&lesson_id=${nextLessonId}" ` +
                        `class="btn btn-secondary">Next Lesson</a>`
                    );
                }
                if (<?php echo !empty($quizzes) ? 'true' : 'false'; ?>) {
                    $buttonGroup.append(
                        `<button class="btn btn-primary quiz-btn" ` +
                        `data-lesson-id="${lessonId}" ` +
                        `data-course-id="${courseId}">Làm bài trắc nghiệm</button>`
                    );
                }
            }
        }

        $('.navigation-buttons').on('click', '.next-btn, .prev-btn:not(.prev-lesson-btn)', function(e) {
            e.preventDefault();
            let page = $(this).data('page');
            let lessonId = $(this).data('lesson-id');
            let courseId = $(this).data('course-id');
            let itemId = $(this).data('item-id');

            if (!courseId || !lessonId || !itemId || !page) {
                console.error('Missing parameters:', { courseId, lessonId, itemId, page });
                return;
            }

            let url = '<?php echo htmlspecialchars(BASE_URL); ?>student/index.php?tab=study' +
                      '&course_id=' + courseId +
                      '&action=view_lesson' +
                      '&lesson_id=' + lessonId +
                      '&item_id=' + itemId +
                      '¤t_page=' + page;
            window.location.href = url;
        });

        $('.navigation-buttons').on('click', '.prev-lesson-btn', function(e) {
            e.preventDefault();
            let lessonId = $(this).data('lesson-id');
            let courseId = $(this).data('course-id');
            window.location.href = 
                '<?php echo htmlspecialchars(BASE_URL); ?>student/index.php?tab=study&course_id=' + courseId + '&action=view_lesson&lesson_id=' + lessonId;
        });

        $('.navigation-buttons').on('click', '.quiz-btn', function(e) {
            e.preventDefault();
            let lessonId = $(this).data('lesson-id');
            let courseId = $(this).data('course-id');
            window.location.href = 
                '<?php echo htmlspecialchars(BASE_URL); ?>student/index.php?tab=study&course_id=' + courseId + '&action=view_lesson&lesson_id=' + lessonId + '&show_quiz=true';
        });

        <?php if ($course && $action === 'view_lesson' && $lesson_id && $item_id && !$show_quiz && isset($current_item) && !empty($current_item['pages'])): ?>
            updateNavigationButtons(
                <?php echo $course_id; ?>,
                <?php echo $lesson_id; ?>,
                <?php echo $item_id; ?>,
                <?php echo $current_page; ?>,
                <?php echo $total_pages; ?>
            );
        <?php endif; ?>
    });
    </script>
</body>
</html>
<?php
$conn = null;
ob_end_flush();
?>