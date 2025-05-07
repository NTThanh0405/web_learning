<?php
ob_start(); // Start output buffering

require_once dirname(__DIR__, 1) . '/config/database.php';
require_once dirname(__DIR__, 1) . '/config/config.php';
require_once dirname(__DIR__, 1) . '/includes/auth_functions.php';

// Only allow admin access
redirectIfNotLoggedIn();
redirectIfNotRole('admin');

$user = getCurrentUser();
$conn = getDBConnection();
$errors = [];
$success = false;

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_start(); // Ensure session is active
    session_unset(); // Clear session variables
    session_destroy(); // Destroy the session
    // Clear cookies (consistent with student/index.php)
    setcookie('user_id', '', time() - 3600, "/");
    setcookie('role', '', time() - 3600, "/");
    header("Location: " . BASE_URL . "auth/login.php"); // Consistent redirect path
    ob_end_flush();
    exit();
}

// Determine which section to display
$section = $_GET['section'] ?? 'dashboard';

// Load dashboard data only if section is dashboard
if ($section === 'dashboard') {
    $totalStudents = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalCourses = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalTeachers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $totalGroups = $conn->query("SELECT COUNT(*) FROM groups")->fetchColumn();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EPU</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: Arial, sans-serif;
        }
        .top-bar {
            background-color: white;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .logo {
            height: 40px;
        }
        .search-bar {
            width: 350px;
            border-radius: 20px;
            padding: 8px 15px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            background-color: #f5f5f5;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
            border: 2px solid #f0f0f0;
        }
        .user-badge {
            background-color: #9c27b0;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            position: absolute;
            top: 20px;
            right: 140px;
        }
        .menu-tabs {
            background-color: #2196f3;
            color: white;
            padding: 10px 0;
        }
        .menu-tabs .nav-link {
            color: white;
            padding: 8px 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .menu-tabs .nav-link i {
            margin-right: 8px;
        }
        .menu-tabs .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
        }
        .dashboard-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .dashboard-card img {
            width: 64px;
            height: 64px;
            margin-bottom: 15px;
        }
        .dashboard-card p {
            font-size: 16px;
            color: #666;
            margin: 0;
            margin-bottom: 8px;
        }
        .dashboard-card .number {
            font-size: 32px;
            font-weight: bold;
        }
        .dashboard-card .number.students {
            color: #4caf50;
        }
        .dashboard-card .number.courses {
            color: #2196f3;
        }
        .dashboard-card .number.teachers {
            color: #ff9800;
        }
        .dashboard-card .number.groups {
            color: #2196f3;
        }
        .icon-button {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
        }
        .icon-button:hover {
            color: #2196f3;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            margin-right: 10px;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <a href="<?php echo BASE_URL; ?>admin/index.php">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="EPU Logo" class="logo">
                    </a>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control search-bar" placeholder="Tìm kiếm Khóa học, Tài liệu, Môn học...">
                </div>
                <div class="col-md-5 d-flex justify-content-end align-items-center">
                    <button class="icon-button mr-3">
                        <i class="fas fa-home"></i>
                    </button>
                    <button class="icon-button mr-3">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button class="icon-button mr-3">
                        <i class="fas fa-cog"></i>
                    </button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logout-btn">Đăng xuất</button>
                    </form>
                    <div class="user-info">
                        <div class="d-flex flex-column align-items-end mr-2">
                            <span><?php echo htmlspecialchars($user['username'] ?? 'admin1'); ?></span>
                            <small class="text-muted">admin</small>
                        </div>
                        <img src="<?php echo $user['avatar'] ? BASE_URL . $user['avatar'] : BASE_URL . 'assets/images/avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Tabs -->
    <div class="menu-tabs">
        <div class="container">
            <ul class="nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'dashboard' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php?section=dashboard">
                        <i class="fas fa-home"></i> Trang chủ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'user' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php?section=user">
                        <i class="fas fa-users"></i> Danh sách User
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'list' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php?section=list">
                        <i class="fas fa-book"></i> Danh sách Khóa/Nhóm
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'forums' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php?section=forums">
                        <i class="fas fa-comments"></i> Danh sách Diễn đàn
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php
        if ($section === 'dashboard') {
            ?>
            <!-- Dashboard: Statistics -->
            <div class="row">
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <img src="https://img.icons8.com/color/64/000000/user.png" alt="Sinh viên">
                        <p>Tổng số sinh viên</p>
                        <div class="number students"><?php echo $totalStudents; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <img src="https://img.icons8.com/color/64/000000/books.png" alt="Khóa học">
                        <p>Tổng số khóa học</p>
                        <div class="number courses"><?php echo $totalCourses; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <img src="https://img.icons8.com/color/64/000000/teacher.png" alt="Giáo viên">
                        <p>Tổng số giáo viên</p>
                        <div class="number teachers"><?php echo $totalTeachers; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <img src="https://img.icons8.com/color/64/000000/graduation-cap.png" alt="Nhóm học">
                        <p>Số nhóm học</p>
                        <div class="number groups"><?php echo $totalGroups; ?></div>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $successMessage ?? "Thao tác thành công!"; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php
        } elseif ($section === 'user') {
            include 'user.php';
        } elseif ($section === 'list') {
            include 'list.php';
        } elseif ($section === 'forums') {
            include 'forums.php';
        }
        ?>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>
</html>
<?php
ob_end_flush(); // End buffering and send output
?>