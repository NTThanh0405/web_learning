<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Hàm tiện ích
function sanitizeInput($data) {
    return trim($data ?? '');
}

function validateRequiredFields($fields) {
    $errors = [];
    foreach ($fields as $field => $value) {
        if (empty($value)) $errors[] = "Vui lòng nhập $field.";
    }
    return $errors;
}

function fetchData($conn, $query, $params = []) {
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['errors' => ["Lỗi truy vấn: " . $e->getMessage()]];
    }
}

function handleImageUpload($file, $test_id, $type, $option_index = null) {
    $target_dir = __DIR__ . '/../assets/uploads/test_images/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($imageFileType, $allowed_types)) {
        return ['error' => 'Chỉ hỗ trợ định dạng JPG, JPEG, PNG, GIF.'];
    }

    $new_filename = 'test_' . $test_id . '_' . $type . ($option_index !== null ? '_option' . $option_index : '') . '_' . time() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'path' => 'assets/uploads/test_images/' . $new_filename,
            'type' => $type,
            'option_index' => $option_index
        ];
    }
    return ['error' => 'Lỗi khi tải lên hình ảnh.'];
}

function saveImageToDatabase($conn, $test_id, $image_data) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO test_images (test_id, type, option_index, image_path, created_at)
            VALUES (:test_id, :type, :option_index, :image_path, NOW())
        ");
        $stmt->execute([
            'test_id' => $test_id,
            'type' => $image_data['type'],
            'option_index' => $image_data['option_index'],
            'image_path' => $image_data['path']
        ]);
        return true;
    } catch (PDOException $e) {
        return ['error' => "Lỗi khi lưu hình ảnh vào cơ sở dữ liệu: " . $e->getMessage()];
    }
}

function deleteImage($conn, $test_id, $type, $option_index = null) {
    try {
        // Lấy đường dẫn ảnh để xóa file
        $stmt = $conn->prepare("SELECT image_path FROM test_images WHERE test_id = :test_id AND type = :type" . ($option_index !== null ? " AND option_index = :option_index" : ""));
        $params = ['test_id' => $test_id, 'type' => $type];
        if ($option_index !== null) $params['option_index'] = $option_index;
        $stmt->execute($params);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // Xóa file vật lý
            $file_path = __DIR__ . '/../' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Xóa bản ghi trong database
            $stmt = $conn->prepare("DELETE FROM test_images WHERE test_id = :test_id AND type = :type" . ($option_index !== null ? " AND option_index = :option_index" : ""));
            $stmt->execute($params);
        }
        return true;
    } catch (PDOException $e) {
        return ['error' => "Lỗi khi xóa hình ảnh: " . $e->getMessage()];
    }
}

function createMultipleChoiceQuestions($conn, $questions, $type, $courseId, $lessonId = null) {
    if (empty($questions)) return ['errors' => ["Vui lòng thêm ít nhất một câu hỏi."]];
    $errors = [];
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("
            INSERT INTO tests (type, course_id, lesson_id, question, option1, option2, option3, option4, correct_option, max_score)
            VALUES (:type, :course_id, :lesson_id, :question, :option1, :option2, :option3, :option4, :correct_option, :max_score)
        ");
        foreach ($questions as $index => $q) {
            $question = sanitizeInput($q['question']);
            $options = array_map('sanitizeInput', [$q['option1'], $q['option2'], $q['option3'], $q['option4']]);
            $correctOption = (int)($q['correct_option'] ?? 0);
            $maxScore = (float)($q['max_score'] ?? 1.0);
            if (!empty($question) && count(array_filter($options)) === 4 && in_array($correctOption, [1, 2, 3, 4])) {
                $stmt->execute([
                    'type' => $type,
                    'course_id' => $courseId,
                    'lesson_id' => $lessonId,
                    'question' => $question,
                    'option1' => $options[0],
                    'option2' => $options[1],
                    'option3' => $options[2],
                    'option4' => $options[3],
                    'correct_option' => $correctOption,
                    'max_score' => $maxScore
                ]);
                $test_id = $conn->lastInsertId();

                // Xử lý hình ảnh cho câu hỏi
                if (isset($_FILES['questions']['name'][$index]['question_image']) && !empty($_FILES['questions']['name'][$index]['question_image'])) {
                    $image_result = handleImageUpload($_FILES['questions']['tmp_name'][$index]['question_image'], $test_id, 'question');
                    if (isset($image_result['error'])) {
                        $errors[] = $image_result['error'];
                    } else {
                        $save_result = saveImageToDatabase($conn, $test_id, $image_result);
                        if (isset($save_result['error'])) {
                            $errors[] = $save_result['error'];
                        }
                    }
                }

                // Xử lý hình ảnh cho các đáp án
                for ($i = 1; $i <= 4; $i++) {
                    if (isset($_FILES['questions']['name'][$index]['option' . $i . '_image']) && !empty($_FILES['questions']['name'][$index]['option' . $i . '_image'])) {
                        $image_result = handleImageUpload($_FILES['questions']['tmp_name'][$index]['option' . $i . '_image'], $test_id, 'option', $i);
                        if (isset($image_result['error'])) {
                            $errors[] = $image_result['error'];
                        } else {
                            $save_result = saveImageToDatabase($conn, $test_id, $image_result);
                            if (isset($save_result['error'])) {
                                $errors[] = $save_result['error'];
                            }
                        }
                    }
                }
            } else {
                $errors[] = "Câu hỏi $index không hợp lệ.";
            }
        }
        if (!empty($errors)) {
            $conn->rollBack();
            return ['errors' => $errors];
        }
        $conn->commit();
        return ['success' => "Câu hỏi đã được tạo thành công!"];
    } catch (PDOException $e) {
        $conn->rollBack();
        return ['errors' => ["Lỗi khi tạo: " . $e->getMessage()]];
    }
}

// Xử lý logic chính
function handleLessonActions($user) {
    $conn = getDBConnection();
    $action = $_GET['action'] ?? '';
    $courseId = $_GET['course_id'] ?? null;
    $lessonId = $_GET['lesson_id'] ?? null;
    $errors = $courseId ? [] : ["Không tìm thấy khóa học."];
    $success = false;

    if ($action === 'create_lesson' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = sanitizeInput($_POST['title']);
        $items = $_POST['items'] ?? [];
        $errors = validateRequiredFields(['tiêu đề bài học' => $title]);
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                $stmt = $conn->prepare("INSERT INTO lessons (course_id, title, created_by) VALUES (:course_id, :title, :created_by)");
                $stmt->execute(['course_id' => $courseId, 'title' => $title, 'created_by' => $user['id']]);
                $lessonId = $conn->lastInsertId();

                foreach ($items as $index => $itemData) {
                    $itemTitle = sanitizeInput($itemData['title']);
                    if ($itemTitle) {
                        $stmt = $conn->prepare("INSERT INTO lesson_items (lesson_id, title, order_number) VALUES (:lesson_id, :title, :order_number)");
                        $stmt->execute(['lesson_id' => $lessonId, 'title' => $itemTitle, 'order_number' => $index + 1]);
                        $itemId = $conn->lastInsertId();

                        foreach ($itemData['pages'] ?? [] as $pageData) {
                            $pageTitle = sanitizeInput($pageData['title']);
                            $pageContent = sanitizeInput($pageData['content']);
                            $pageNumber = (int)($pageData['number'] ?? 1);
                            if ($pageTitle && $pageContent) {
                                $stmt = $conn->prepare("INSERT INTO pages (lesson_item_id, title, content, page_number) VALUES (:lesson_item_id, :title, :content, :page_number)");
                                $stmt->execute(['lesson_item_id' => $itemId, 'title' => $pageTitle, 'content' => $pageContent, 'page_number' => $pageNumber]);
                            }
                        }
                    }
                }
                $conn->commit();
                $success = "Bài học đã được tạo thành công!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Lỗi khi tạo bài học: " . $e->getMessage();
            }
        }
    }

    if ($action === 'edit_lesson' && $lessonId && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = sanitizeInput($_POST['title']);
        $items = $_POST['items'] ?? [];
        $errors = validateRequiredFields(['tiêu đề bài học' => $title]);
        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                // Update lesson title
                $stmt = $conn->prepare("UPDATE lessons SET title = :title WHERE id = :lesson_id AND created_by = :created_by");
                $stmt->execute(['title' => $title, 'lesson_id' => $lessonId, 'created_by' => $user['id']]);

                // Get existing lesson items
                $existingItems = fetchData($conn, "SELECT id FROM lesson_items WHERE lesson_id = :lesson_id ORDER BY order_number", ['lesson_id' => $lessonId]);
                $existingItemIds = array_column($existingItems, 'id');

                // Process submitted items
                $submittedItemIds = [];
                foreach ($items as $index => $itemData) {
                    $itemTitle = sanitizeInput($itemData['title']);
                    if ($itemTitle) {
                        $itemId = $itemData['id'] ?? null;
                        if ($itemId && in_array($itemId, $existingItemIds)) {
                            // Update existing item
                            $stmt = $conn->prepare("UPDATE lesson_items SET title = :title, order_number = :order_number WHERE id = :item_id AND lesson_id = :lesson_id");
                            $stmt->execute(['title' => $itemTitle, 'order_number' => $index + 1, 'item_id' => $itemId, 'lesson_id' => $lessonId]);
                        } else {
                            // Insert new item
                            $stmt = $conn->prepare("INSERT INTO lesson_items (lesson_id, title, order_number) VALUES (:lesson_id, :title, :order_number)");
                            $stmt->execute(['lesson_id' => $lessonId, 'title' => $itemTitle, 'order_number' => $index + 1]);
                            $itemId = $conn->lastInsertId();
                        }
                        $submittedItemIds[] = $itemId;

                        // Get existing pages for this item
                        $existingPages = fetchData($conn, "SELECT id FROM pages WHERE lesson_item_id = :item_id ORDER BY page_number", ['item_id' => $itemId]);
                        $existingPageIds = array_column($existingPages, 'id');

                        // Process pages
                        $submittedPageIds = [];
                        foreach ($itemData['pages'] ?? [] as $pageData) {
                            $pageTitle = sanitizeInput($pageData['title']);
                            $pageContent = sanitizeInput($pageData['content']);
                            $pageNumber = (int)($pageData['number'] ?? 1);
                            $pageId = $pageData['id'] ?? null;
                            if ($pageTitle && $pageContent) {
                                if ($pageId && in_array($pageId, $existingPageIds)) {
                                    // Update existing page
                                    $stmt = $conn->prepare("UPDATE pages SET title = :title, content = :content, page_number = :page_number WHERE id = :page_id AND lesson_item_id = :item_id");
                                    $stmt->execute(['title' => $pageTitle, 'content' => $pageContent, 'page_number' => $pageNumber, 'page_id' => $pageId, 'item_id' => $itemId]);
                                } else {
                                    // Insert new page
                                    $stmt = $conn->prepare("INSERT INTO pages (lesson_item_id, title, content, page_number) VALUES (:lesson_item_id, :title, :content, :page_number)");
                                    $stmt->execute(['lesson_item_id' => $itemId, 'title' => $pageTitle, 'content' => $pageContent, 'page_number' => $pageNumber]);
                                    $pageId = $conn->lastInsertId();
                                }
                                $submittedPageIds[] = $pageId;
                            }
                        }

                        // Delete pages not in submitted data
                        $pagesToDelete = array_diff($existingPageIds, $submittedPageIds);
                        if ($pagesToDelete) {
                            $stmt = $conn->prepare("DELETE FROM pages WHERE id IN (" . implode(',', array_fill(0, count($pagesToDelete), '?')) . ") AND lesson_item_id = ?");
                            $stmt->execute(array_merge($pagesToDelete, [$itemId]));
                        }
                    }
                }

                // Delete items not in submitted data
                $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);
                if ($itemsToDelete) {
                    $stmt = $conn->prepare("DELETE FROM lesson_items WHERE id IN (" . implode(',', array_fill(0, count($itemsToDelete), '?')) . ") AND lesson_id = ?");
                    $stmt->execute(array_merge($itemsToDelete, [$lessonId]));
                }

                $conn->commit();
                $success = "Bài học đã được cập nhật thành công!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Lỗi khi sửa bài học: " . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_lesson' && $lessonId) {
        try {
            $stmt = $conn->prepare("DELETE FROM lessons WHERE id = :lesson_id AND created_by = :created_by");
            $stmt->execute(['lesson_id' => $lessonId, 'created_by' => $user['id']]);
            header("Location: " . BASE_URL . "teacher/index.php?tab=lessons&course_id=$courseId&success=" . urlencode("Bài học đã được xóa!"));
            exit();
        } catch (PDOException $e) {
            $errors[] = "Lỗi khi xóa bài học: " . $e->getMessage();
        }
    }

    if ($action === 'create_quiz' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $lessonId = $_POST['lesson_id'] ?? null;
        $result = createMultipleChoiceQuestions($conn, $_POST['questions'] ?? [], 'lesson', $courseId, $lessonId);
        $errors = $result['errors'] ?? [];
        $success = $result['success'] ?? false;
    }

    if ($action === 'edit_quiz' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $testId = $_POST['test_id'] ?? null;
        $fields = array_map('sanitizeInput', [
            'question' => $_POST['question'],
            'option1' => $_POST['option1'],
            'option2' => $_POST['option2'],
            'option3' => $_POST['option3'],
            'option4' => $_POST['option4']
        ]);
        $correctOption = (int)($_POST['correct_option'] ?? 0);
        $maxScore = (float)($_POST['max_score'] ?? 1.0);
        $errors = $testId && !empty(array_filter($fields)) && in_array($correctOption, [1, 2, 3, 4]) ? [] : ["Dữ liệu không hợp lệ."];
        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                $stmt = $conn->prepare("
                    UPDATE tests 
                    SET question = :question, option1 = :option1, option2 = :option2, option3 = :option3, 
                        option4 = :option4, correct_option = :correct_option, max_score = :max_score 
                    WHERE id = :test_id
                ");
                $stmt->execute(array_merge($fields, [
                    'correct_option' => $correctOption,
                    'max_score' => $maxScore,
                    'test_id' => $testId
                ]));

                // Xử lý xóa ảnh
                if (isset($_POST['remove_question_image']) && $_POST['remove_question_image'] == '1') {
                    $delete_result = deleteImage($conn, $testId, 'question');
                    if (isset($delete_result['error'])) {
                        $errors[] = $delete_result['error'];
                    }
                }
                for ($i = 1; $i <= 4; $i++) {
                    if (isset($_POST["remove_option{$i}_image"]) && $_POST["remove_option{$i}_image"] == '1') {
                        $delete_result = deleteImage($conn, $testId, 'option', $i);
                        if (isset($delete_result['error'])) {
                            $errors[] = $delete_result['error'];
                        }
                    }
                }

                // Xử lý hình ảnh mới cho câu hỏi
                if (isset($_FILES['question_image']) && !empty($_FILES['question_image']['name'])) {
                    $image_result = handleImageUpload($_FILES['question_image'], $testId, 'question');
                    if (isset($image_result['error'])) {
                        $errors[] = $image_result['error'];
                    } else {
                        $save_result = saveImageToDatabase($conn, $testId, $image_result);
                        if (isset($save_result['error'])) {
                            $errors[] = $save_result['error'];
                        }
                    }
                }

                // Xử lý hình ảnh mới cho các đáp án
                for ($i = 1; $i <= 4; $i++) {
                    if (isset($_FILES['option' . $i . '_image']) && !empty($_FILES['option' . $i . '_image']['name'])) {
                        $image_result = handleImageUpload($_FILES['option' . $i . '_image'], $testId, 'option', $i);
                        if (isset($image_result['error'])) {
                            $errors[] = $image_result['error'];
                        } else {
                            $save_result = saveImageToDatabase($conn, $testId, $image_result);
                            if (isset($save_result['error'])) {
                                $errors[] = $save_result['error'];
                            }
                        }
                    }
                }

                if (!empty($errors)) {
                    $conn->rollBack();
                    return ['errors' => $errors];
                }
                $conn->commit();
                $success = "Câu hỏi đã được cập nhật!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $errors[] = "Lỗi khi sửa câu hỏi: " . $e->getMessage();
            }
        }
    }

    if ($action === 'create_final_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = createMultipleChoiceQuestions($conn, $_POST['questions'] ?? [], 'course', $courseId);
        $errors = $result['errors'] ?? [];
        $success = $result['success'] ?? false;
    }

    $course = fetchData($conn, "SELECT title FROM courses WHERE id = :course_id", ['course_id' => $courseId])[0] ?? null;
    $lessons = fetchData($conn, "SELECT * FROM lessons WHERE course_id = :course_id ORDER BY created_at", ['course_id' => $courseId]);
    foreach ($lessons as &$lesson) {
        $progress = fetchData($conn, "
            SELECT COUNT(*) as total_pages, SUM(CASE WHEN upp.completed = 1 THEN 1 ELSE 0 END) as completed_pages 
            FROM lesson_items li 
            JOIN pages p ON p.lesson_item_id = li.id 
            LEFT JOIN user_page_progress upp ON upp.page_id = p.id AND upp.user_id = :user_id 
            WHERE li.lesson_id = :lesson_id", 
            ['lesson_id' => $lesson['id'], 'user_id' => $user['id']]
        )[0];
        $lesson['is_completed'] = ($progress['total_pages'] > 0 && $progress['total_pages'] == $progress['completed_pages']);
    }

    $GLOBALS['lesson_data'] = compact('errors', 'success', 'lessons', 'courseId', 'course', 'conn');
}

// Hiển thị giao diện
function renderLessons($user) {
    extract($GLOBALS['lesson_data']);
    $action = $_GET['action'] ?? '';
    $lessonId = $_GET['lesson_id'] ?? null;
    $currentPage = (int)($_GET['current_page'] ?? 1);

    if ($action === 'view' && $lessonId) {
        $lesson = fetchData($conn, "SELECT * FROM lessons WHERE id = :lesson_id AND created_by = :created_by", ['lesson_id' => $lessonId, 'created_by' => $user['id']])[0] ?? null;
        $lessonItems = fetchData($conn, "SELECT * FROM lesson_items WHERE lesson_id = :lesson_id ORDER BY order_number", ['lesson_id' => $lessonId]);
        foreach ($lessonItems as &$item) {
            $item['pages'] = fetchData($conn, "SELECT * FROM pages WHERE lesson_item_id = :item_id ORDER BY page_number", ['item_id' => $item['id']]);
            $item['current_page_data'] = array_filter($item['pages'], fn($page) => $page['page_number'] == $currentPage)[0] ?? $item['pages'][0] ?? null;
        }
        $quizzes = fetchData($conn, "SELECT * FROM tests WHERE lesson_id = :lesson_id AND type = 'lesson'", ['lesson_id' => $lessonId]);
        foreach ($quizzes as &$quiz) {
            $images = fetchData($conn, "SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id", ['test_id' => $quiz['id']]);
            $quiz['images'] = [
                'question' => [],
                'options' => []
            ];
            foreach ($images as $image) {
                $image_path = BASE_URL . $image['image_path'];
                if ($image['type'] === 'question') {
                    $quiz['images']['question'][] = $image_path;
                } elseif ($image['type'] === 'option' && $image['option_index'] >= 1 && $image['option_index'] <= 4) {
                    $quiz['images']['options'][$image['option_index']][] = $image_path;
                }
            }
        }
    }
    if ($action === 'view_final_tests') {
        $finalTests = fetchData($conn, "SELECT * FROM tests WHERE course_id = :course_id AND type = 'course'", ['course_id' => $courseId]);
        foreach ($finalTests as &$test) {
            $images = fetchData($conn, "SELECT type, option_index, image_path FROM test_images WHERE test_id = :test_id", ['test_id' => $test['id']]);
            $test['images'] = [
                'question' => [],
                'options' => []
            ];
            foreach ($images as $image) {
                $image_path = BASE_URL . $image['image_path'];
                if ($image['type'] === 'question') {
                    $test['images']['question'][] = $image_path;
                } elseif ($image['type'] === 'option' && $image['option_index'] >= 1 && $image['option_index'] <= 4) {
                    $test['images']['options'][$image['option_index']][] = $image_path;
                }
            }
        }
    }
    if ($action === 'edit_lesson' && $lessonId) {
        $lesson = fetchData($conn, "SELECT * FROM lessons WHERE id = :lesson_id AND created_by = :created_by", ['lesson_id' => $lessonId, 'created_by' => $user['id']])[0] ?? null;
        $lessonItems = fetchData($conn, "SELECT * FROM lesson_items WHERE lesson_id = :lesson_id ORDER BY order_number", ['lesson_id' => $lessonId]);
        foreach ($lessonItems as &$item) {
            $item['pages'] = fetchData($conn, "SELECT * FROM pages WHERE lesson_item_id = :item_id ORDER BY page_number", ['item_id' => $item['id']]);
        }
    }
?>

<div class="container mt-5">
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($action === 'create_lesson'): ?>
        <h2>Tạo Bài Học Mới</h2>
        <form method="POST">
            <div class="form-group"><label>Tiêu đề:</label><input type="text" name="title" class="form-control" required></div>
            <div id="itemsContainer">
                <div class="item-group mb-3">
                    <input type="text" name="items[0][title]" class="form-control" placeholder="Tên mục">
                    <div class="page-group">
                        <select name="items[0][pages][0][number]" class="form-control d-inline-block w-25"><?php for ($i = 1; $i <= 15; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select>
                        <input type="text" name="items[0][pages][0][title]" class="form-control d-inline-block w-25 ml-2" placeholder="Tiêu đề trang">
                        <textarea name="items[0][pages][0][content]" class="form-control mt-2" rows="10" placeholder="Nội dung (||SPLIT|| để chia cột)"></textarea>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2 add-page-btn">Thêm trang</button>
                </div>
            </div>
            <button type="button" class="btn btn-secondary mb-3" id="addItemBtn">Thêm mục</button>
            <button type="submit" class="btn btn-primary">Tạo</button>
            <a href="<?php echo BASE_URL; ?>teacher/index.php?tab=lessons&course_id=<?php echo $courseId; ?>" class="btn btn-secondary">Hủy</a>
        </form>
    <?php elseif ($action === 'edit_lesson' && $lessonId && $lesson): ?>
        <h2>Sửa Bài Học: <?php echo htmlspecialchars($lesson['title']); ?></h2>
        <form method="POST" action="?tab=lessons&action=edit_lesson&course_id=<?php echo $courseId; ?>&lesson_id=<?php echo $lessonId; ?>">
            <div class="form-group">
                <label>Tiêu đề bài học:</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
            </div>
            <div id="itemsContainer">
                <?php foreach ($lessonItems as $index => $item): ?>
                    <div class="item-group mb-3">
                        <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo $item['id']; ?>">
                        <input type="text" name="items[<?php echo $index; ?>][title]" class="form-control" placeholder="Tên mục" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                        <?php foreach ($item['pages'] as $pageIndex => $page): ?>
                            <div class="page-group">
                                <input type="hidden" name="items[<?php echo $index; ?>][pages][<?php echo $pageIndex; ?>][id]" value="<?php echo $page['id']; ?>">
                                <select name="items[<?php echo $index; ?>][pages][<?php echo $pageIndex; ?>][number]" class="form-control d-inline-block w-25">
                                    <?php for ($i = 1; $i <= 15; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $page['page_number'] == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <input type="text" name="items[<?php echo $index; ?>][pages][<?php echo $pageIndex; ?>][title]" class="form-control d-inline-block w-25 ml-2" placeholder="Tiêu đề trang" value="<?php echo htmlspecialchars($page['title']); ?>" required>
                                <textarea name="items[<?php echo $index; ?>][pages][<?php echo $pageIndex; ?>][content]" class="form-control mt-2" rows="10" placeholder="Nội dung (||SPLIT|| để chia cột)" required><?php echo htmlspecialchars($page['content']); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-secondary mt-2 add-page-btn">Thêm trang</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary mb-3" id="addItemBtn">Thêm mục</button>
            <button type="submit" class="btn btn-primary">Lưu</button>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>" class="btn btn-secondary">Hủy</a>
        </form>
    <?php elseif ($action === 'view' && $lessonId && $lesson): ?>
        <h2>Chi Tiết: <?php echo htmlspecialchars($lesson['title']); ?></h2>
        <div class="d-flex justify-content-end mb-3">
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=edit_lesson&lesson_id=<?php echo $lessonId; ?>" class="btn btn-warning mr-2">Sửa</a>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=delete_lesson&lesson_id=<?php echo $lessonId; ?>" class="btn btn-danger mr-2" onclick="return confirm('Bạn có chắc chắn?');">Xóa</a>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>" class="btn btn-secondary">Quay lại</a>
        </div>
        <h3>Danh Sách Mục</h3>
        <?php if ($lessonItems): ?>
            <div class="accordion" id="lessonItemsAccordion">
                <?php foreach ($lessonItems as $item): ?>
                    <div class="card">
                        <div class="card-header"><button class="btn btn-link" data-toggle="collapse" data-target="#collapse<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></button></div>
                        <div id="collapse<?php echo $item['id']; ?>" class="collapse" data-parent="#lessonItemsAccordion">
                            <div class="card-body">
                                <?php if ($item['current_page_data']): ?>
                                    <div class="row page-content" data-content="<?php echo htmlspecialchars($item['current_page_data']['content']); ?>">
                                        <div class="col-md-6 content-left"><h5><?php echo htmlspecialchars($item['current_page_data']['title']); ?></h5></div>
                                        <div class="col-md-6 content-right"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-secondary prev-btn" <?php echo $currentPage > 1 ? "data-page='" . ($currentPage - 1) . "' data-lesson-id='$lessonId' data-course-id='$courseId'" : 'disabled'; ?>>Prev</button>
                                        <button class="btn btn-secondary next-btn" <?php echo $currentPage < count($item['pages']) ? "data-page='" . ($currentPage + 1) . "' data-lesson-id='$lessonId' data-course-id='$courseId'" : 'disabled'; ?>>Next</button>
                                    </div>
                                <?php else: ?><p>Chưa có trang.</p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?><p>Chưa có mục.</p><?php endif; ?>
        <h3>Bài Kiểm Tra</h3>
        <?php if ($quizzes): ?>
            <ul class="list-group mb-3">
                <?php foreach ($quizzes as $quiz): ?>
                    <li class="list-group-item">
                        <strong>Câu hỏi:</strong> <?php echo htmlspecialchars($quiz['question']); ?>
                        <?php if (!empty($quiz['images']['question'])): ?>
                            <div>
                                <?php foreach ($quiz['images']['question'] as $image): ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Question Image" style="max-width: 200px; margin: 10px;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?><br>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <strong>Đáp án <?php echo $i; ?>:</strong> <?php echo htmlspecialchars($quiz["option$i"]); ?>
                            <?php if (!empty($quiz['images']['options'][$i])): ?>
                                <div>
                                    <?php foreach ($quiz['images']['options'][$i] as $image): ?>
                                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Option <?php echo $i; ?> Image" style="max-width: 100px; margin: 5px;">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?><br>
                        <?php endfor; ?>
                        <strong>Đáp án đúng:</strong> Đáp án <?php echo $quiz['correct_option']; ?><br>
                        <strong>Điểm tối đa:</strong> <?php echo htmlspecialchars($quiz['max_score']); ?>
                        <button class="btn btn-warning btn-sm edit-quiz-btn" 
                                data-id="<?php echo $quiz['id']; ?>" 
                                data-question="<?php echo htmlspecialchars($quiz['question']); ?>" 
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    data-option<?php echo $i; ?>="<?php echo htmlspecialchars($quiz["option$i"]); ?>" 
                                    data-option<?php echo $i; ?>-image="<?php echo !empty($quiz['images']['options'][$i]) ? htmlspecialchars($quiz['images']['options'][$i][0]) : ''; ?>"
                                <?php endfor; ?>
                                data-question-image="<?php echo !empty($quiz['images']['question']) ? htmlspecialchars($quiz['images']['question'][0]) : ''; ?>"
                                data-correct="<?php echo $quiz['correct_option']; ?>" 
                                data-max-score="<?php echo htmlspecialchars($quiz['max_score']); ?>">Sửa</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><p>Chưa có câu hỏi.</p><?php endif; ?>
    <?php elseif ($action === 'create_quiz' || $action === 'create_final_test'): ?>
        <?php echo renderQuestionForm($action === 'create_quiz' ? 'quiz' : 'final_test', $courseId, $lessons, "?tab=lessons&action=$action&course_id=$courseId"); ?>
    <?php elseif ($action === 'view_final_tests'): ?>
        <h2>Danh Sách Câu Hỏi Test Cuối Khóa</h2>
        <?php if ($finalTests): ?>
            <ul class="list-group mb-3">
                <?php foreach ($finalTests as $test): ?>
                    <li class="list-group-item">
                        <strong>Câu hỏi:</strong> <?php echo htmlspecialchars($test['question']); ?>
                        <?php if (!empty($test['images']['question'])): ?>
                            <div>
                                <?php foreach ($test['images']['question'] as $image): ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Question Image" style="max-width: 200px; margin: 10px;">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?><br>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <strong>Đáp án <?php echo $i; ?>:</strong> <?php echo htmlspecialchars($test["option$i"]); ?>
                            <?php if (!empty($test['images']['options'][$i])): ?>
                                <div>
                                    <?php foreach ($test['images']['options'][$i] as $image): ?>
                                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Option <?php echo $i; ?> Image" style="max-width: 100px; margin: 5px;">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?><br>
                        <?php endfor; ?>
                        <strong>Đáp án đúng:</strong> Đáp án <?php echo $test['correct_option']; ?><br>
                        <strong>Điểm tối đa:</strong> <?php echo htmlspecialchars($test['max_score']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><p>Chưa có câu hỏi.</p><?php endif; ?>
        <a href="?tab=lessons&course_id=<?php echo $courseId; ?>" class="btn btn-secondary">Quay lại</a>
    <?php else: ?>
        <h2><?php echo htmlspecialchars($course['title'] ?? 'Khóa học không xác định'); ?></h2>
        <div class="action-buttons mt-3">
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=create_lesson" class="btn btn-primary mb-3">Tạo bài học</a>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=create_quiz" class="btn btn-primary mb-3">Tạo kiểm tra</a>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=create_final_test" class="btn btn-primary mb-3">Tạo test cuối khóa</a>
            <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=view_final_tests" class="btn btn-info mb-3">Xem test cuối khóa</a>
        </div>
        <h3>Danh sách bài học</h3>
        <ul class="lesson-list">
            <?php foreach ($lessons as $index => $lesson): ?>
                <li class="lesson-item <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>">
                    <a href="?tab=lessons&course_id=<?php echo $courseId; ?>&action=view&lesson_id=<?php echo $lesson['id']; ?>">
                        <span class="lesson-number"><?php echo $index + 1; ?>.</span>
                        <span class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></span>
                        <span class="<?php echo $lesson['is_completed'] ? 'checkmark' : 'circle'; ?>"><?php echo $lesson['is_completed'] ? '✔' : ''; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="modal fade" id="editQuizModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Sửa câu hỏi</h5></div>
            <form method="POST" action="?tab=lessons&action=edit_quiz&course_id=<?php echo $courseId; ?>&lesson_id=<?php echo $lessonId; ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="test_id" id="editQuizId">
                    <div class="form-group"><label>Câu hỏi:</label><input type="text" name="question" id="editQuestion" class="form-control" required></div>
                    <div class="form-group">
                        <label>Hình ảnh câu hỏi:</label>
                        <div id="currentQuestionImage" class="mb-2"></div>
                        <input type="file" name="question_image" class="form-control-file" accept="image/*">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="remove_question_image" value="1" class="form-check-input" id="removeQuestionImage">
                            <label class="form-check-label" for="removeQuestionImage">Xóa hình ảnh hiện tại</label>
                        </div>
                    </div>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="form-group"><label>Đáp án <?php echo $i; ?>:</label><input type="text" name="option<?php echo $i; ?>" id="editOption<?php echo $i; ?>" class="form-control" required></div>
                        <div class="form-group">
                            <label>Hình ảnh đáp án <?php echo $i; ?>:</label>
                            <div id="currentOption<?php echo $i; ?>Image" class="mb-2"></div>
                            <input type="file" name="option<?php echo $i; ?>_image" class="form-control-file" accept="image/*">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="remove_option<?php echo $i; ?>_image" value="1" class="form-check-input" id="removeOption<?php echo $i; ?>Image">
                                <label class="form-check-label" for="removeOption<?php echo $i; ?>Image">Xóa hình ảnh hiện tại</label>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <div class="form-group"><label>Đáp án đúng:</label>
                        <select name="correct_option" id="editCorrectOption" class="form-control" required>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <option value="<?php echo $i; ?>">Đáp án <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Điểm tối đa:</label><input type="number" step="0.1" name="max_score" id="editMaxScore" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Lưu</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    let itemCount = <?php echo isset($lessonItems) ? count($lessonItems) : 1; ?>;
    let pageCounts = <?php echo isset($lessonItems) ? json_encode(array_map(function($item) { return count($item['pages']); }, $lessonItems)) : '{}'; ?>;
    let questionCount = 1;
    let finalQuestionCount = 1;

    const addDynamicField = (containerId, template, counter) => {
        $(`#${containerId}`).append(template(counter));
        return counter + 1;
    };

    const itemTemplate = count => `
        <div class="item-group mb-3">
            <input type="text" name="items[${count}][title]" class="form-control" placeholder="Tên mục">
            <div class="page-group">
                <select name="items[${count}][pages][0][number]" class="form-control d-inline-block w-25"><?php for ($i = 1; $i <= 15; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select>
                <input type="text" name="items[${count}][pages][0][title]" class="form-control d-inline-block w-25 ml-2" placeholder="Tiêu đề trang">
                <textarea name="items[${count}][pages][0][content]" class="form-control mt-2" rows="3" placeholder="Nội dung (||SPLIT|| để chia cột)"></textarea>
            </div>
            <button type="button" class="btn btn-secondary mt-2 add-page-btn">Thêm trang</button>
        </div>`;

    const pageTemplate = (itemIndex, pageCount) => `
        <div class="page-group">
            <select name="items[${itemIndex}][pages][${pageCount}][number]" class="form-control d-inline-block w-25"><?php for ($i = 1; $i <= 15; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select>
            <input type="text" name="items[${itemIndex}][pages][${pageCount}][title]" class="form-control d-inline-block w-25 ml-2" placeholder="Tiêu đề trang">
            <textarea name="items[${itemIndex}][pages][${pageCount}][content]" class="form-control mt-2" rows="10" placeholder="Nội dung (||SPLIT|| để chia cột)"></textarea>
        </div>`;

    const questionTemplate = count => `
        <div class="question-group mb-3">
            <input type="text" name="questions[${count}][question]" class="form-control mb-2" placeholder="Câu hỏi" required>
            <input type="file" name="questions[${count}][question_image]" class="form-control-file mb-2" accept="image/*">
            ${[1, 2, 3, 4].map(i => `
                <input type="text" name="questions[${count}][option${i}]" class="form-control mb-2" placeholder="Đáp án ${i}" required>
                <input type="file" name="questions[${count}][option${i}_image]" class="form-control-file mb-2" accept="image/*">
            `).join('')}
            <select name="questions[${count}][correct_option]" class="form-control mb-2" required>
                ${[1, 2, 3, 4].map(i => `<option value="${i}">Đáp án ${i}</option>`).join('')}
            </select>
            <input type="number" step="0.1" name="questions[${count}][max_score]" class="form-control mb-2" placeholder="Điểm tối đa" value="1.0" required>
        </div>`;

    $('#addItemBtn').click(() => {
        itemCount = addDynamicField('itemsContainer', itemTemplate, itemCount);
        pageCounts[itemCount - 1] = 1;
    });

    $(document).on('click', '.add-page-btn', function() {
        let itemIndex = $(this).closest('.item-group').index();
        let pageCount = pageCounts[itemIndex] || 0;
        $(this).before(pageTemplate(itemIndex, pageCount));
        pageCounts[itemIndex] = pageCount + 1;
    });

    $('#addQuestionBtn').click(() => {
        questionCount = addDynamicField('questionsContainer', questionTemplate, questionCount);
    });

    $('#addFinalQuestionBtn').click(() => {
        finalQuestionCount = addDynamicField('finalQuestionsContainer', questionTemplate, finalQuestionCount);
    });

    $('.edit-quiz-btn').click(function() {
        $('#editQuizId').val($(this).data('id'));
        $('#editQuestion').val($(this).data('question'));
        for (let i = 1; i <= 4; i++) {
            $(`#editOption${i}`).val($(this).data(`option${i}`));
            let optionImage = $(this).data(`option${i}-image`);
            $(`#currentOption${i}Image`).html(optionImage ? `<img src="${optionImage}" alt="Option ${i} Image" style="max-width: 100px; margin: 5px;"><br>` : '');
            $(`#removeOption${i}Image`).prop('disabled', !optionImage);
        }
        let questionImage = $(this).data('question-image');
        $('#currentQuestionImage').html(questionImage ? `<img src="${questionImage}" alt="Question Image" style="max-width: 200px; margin: 10px;"><br>` : '');
        $('#removeQuestionImage').prop('disabled', !questionImage);
        $('#editCorrectOption').val($(this).data('correct'));
        $('#editMaxScore').val($(this).data('max-score'));
        $('#editQuizModal').modal('show');
    });

    $('.page-content').each(function() {
        let content = $(this).data('content'), parts = content.split('||SPLIT||');
        $(this).find('.content-left').append(parts[0]?.replace(/\n/g, '<br>') || content.replace(/\n/g, '<br>'));
        $(this).find('.content-right').html(parts[1]?.replace(/\n/g, '<br>') || 'Không có nội dung.');
    });

    $('.next-btn, .prev-btn').click(function(e) {
        e.preventDefault();
        let page = $(this).data('page'), lessonId = $(this).data('lesson-id'), courseId = $(this).data('course-id');
        $.ajax({
            url: '<?php echo BASE_URL; ?>courses/get_page_content.php',
            method: 'GET',
            data: { course_id: courseId, lesson_id: lessonId, current_page: page },
            dataType: 'text',
            success: function(response) {
                try {
                    let data = JSON.parse(response);
                    if (data.success) {
                        let currentItem = $('.collapse.show .card-body');
                        currentItem.find('.content-left h5').text(data.title);
                        let parts = data.content.split('||SPLIT||');
                        currentItem.find('.content-left').html(parts[0]?.replace(/\n/g, '<br>') || data.content.replace(/\n/g, '<br>'));
                        currentItem.find('.content-right').html(parts[1]?.replace(/\n/g, '<br>') || 'Không có nội dung.');
                        window.history.pushState({}, '', `?tab=lessons&action=view&course_id=${courseId}&lesson_id=${lessonId}&current_page=${page}`);
                        updateNavigationButtons(courseId, lessonId, page, data.totalPages);
                    } else alert('Không thể tải trang: ' + data.error);
                } catch (e) {
                    alert('Lỗi xử lý dữ liệu: ' + e.message);
                }
            },
            error: () => alert('Lỗi kết nối server.')
        });
    });

    function updateNavigationButtons(courseId, lessonId, currentPage, totalPages) {
        $('.prev-btn').attr('disabled', currentPage <= 1).data({ page: currentPage - 1, 'lesson-id': lessonId, 'course-id': courseId });
        $('.next-btn').attr('disabled', currentPage >= totalPages).data({ page: currentPage + 1, 'lesson-id': lessonId, 'course-id': courseId });
    }
});
</script>
<?php
}

function renderQuestionForm($type, $courseId, $lessons, $actionUrl) {
    ob_start(); ?>
    <h2><?php echo $type === 'quiz' ? 'Tạo Bài Kiểm Tra' : 'Tạo Test Cuối Khóa'; ?></h2>
    <form method="POST" action="<?php echo $actionUrl; ?>" enctype="multipart/form-data">
        <?php if ($type === 'quiz'): ?>
            <div class="form-group"><label>Chọn bài học:</label>
                <select name="lesson_id" class="form-control" required>
                    <?php foreach ($lessons as $lesson): ?>
                        <option value="<?php echo $lesson['id']; ?>"><?php echo htmlspecialchars($lesson['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div id="<?php echo $type === 'quiz' ? 'questionsContainer' : 'finalQuestionsContainer'; ?>">
            <div class="question-group mb-3">
                <input type="text" name="questions[0][question]" class="form-control mb-2" placeholder="Câu hỏi" required>
                <input type="file" name="questions[0][question_image]" class="form-control-file mb-2" accept="image/*">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <input type="text" name="questions[0][option<?php echo $i; ?>]" class="form-control mb-2" placeholder="Đáp án <?php echo $i; ?>" required>
                    <input type="file" name="questions[0][option<?php echo $i; ?>_image]" class="form-control-file mb-2" accept="image/*">
                <?php endfor; ?>
                <select name="questions[0][correct_option]" class="form-control mb-2" required>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>">Đáp án <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
                <input type="number" step="0.1" name="questions[0][max_score]" class="form-control mb-2" placeholder="Điểm tối đa" value="1.0" required>
            </div>
        </div>
        <button type="button" class="btn btn-secondary mb-3" id="<?php echo $type === 'quiz' ? 'addQuestionBtn' : 'addFinalQuestionBtn'; ?>">Thêm câu hỏi</button>
        <button type="submit" class="btn btn-primary">Tạo</button>
        <a href="?tab=lessons&course_id=<?php echo $courseId; ?>" class="btn btn-secondary">Hủy</a>
    </form>
    <?php return ob_get_clean();
}
?>