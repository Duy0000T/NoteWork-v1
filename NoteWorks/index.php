<?php
include 'db.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'welcome';

// Danh sách trang
$public_pages = ['welcome', 'login', 'register'];
$auth_pages   = ['dashboard', 'tasks', 'schedule', 'storage', 'logout'];

if (in_array($page, $auth_pages)) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?page=login");
        exit();
    }
}

if ($page == 'logout') {
    session_destroy();
    header("Location: index.php?page=welcome");
    exit();
}

if (in_array($page, $public_pages)) {
    // Trang Public (không có sidebar)
    $file = "pages/{$page}.php";
    if (file_exists($file)) include $file;
    else echo "404 Not Found";
} else {
    // Trang Auth (có sidebar + layout)
    include 'layout/head.php';
    echo '<div class="app-container">';
    include 'layout/sidebar.php';
    echo '<div class="main-content">';
    $file = "pages/{$page}.php";
    if (file_exists($file)) include $file;
    else echo "<h2>Trang không tồn tại</h2>";
    echo '</div>';
    echo '</div>';
    include 'layout/footer.php';
}
?>