<?php
session_start();

// Cấu hình
$upload_dir = 'C:/nas_storage/'; // Thư mục lưu trữ file
$max_file_size = 1024 * 1024 * 100; // 100MB
$log_file = 'C:/nas_storage/log.txt'; // File log hoạt động
$users = [
    'admin' => password_hash('admin123', PASSWORD_BCRYPT), // User mẫu
];

// Hàm ghi log
function log_activity($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Đăng nhập
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['username'] = $username;
        log_activity("User $username logged in.");
    } else {
        die("Đăng nhập thất bại");
    }
}

// Đăng xuất
if (isset($_GET['logout'])) {
    log_activity("User {$_SESSION['username']} logged out.");
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo '
    <form method="post">
        Username: <input type="text" name="username" required><br>
        Password: <input type="password" name="password" required><br>
        <input type="submit" name="login" value="Login">
    </form>
    ';
    exit;
}

// Xử lý upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $subdir = isset($_POST['subdir']) ? trim($_POST['subdir'], '/') . '/' : '';
    $target_dir = $upload_dir . $subdir;

    // Tạo thư mục nếu chưa tồn tại
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Kiểm tra lỗi
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload thất bại với mã lỗi: " . $file['error']);
    }

    // Kiểm tra kích thước file
    if ($file['size'] > $max_file_size) {
        die("File vượt quá kích thước cho phép (100MB)");
    }

    // Tạo tên file an toàn
    $file_name = basename($file['name']);
    $target_path = $target_dir . $file_name;

    // Di chuyển file vào thư mục lưu trữ
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        log_activity("User {$_SESSION['username']} uploaded file: $subdir$file_name");
        echo "Upload thành công: " . htmlspecialchars($file_name);
    } else {
        echo "Upload thất bại";
    }
}

// Xử lý download file
if (isset($_GET['download'])) {
    $file_path = $upload_dir . basename($_GET['download']);

    // Kiểm tra file tồn tại
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        log_activity("User {$_SESSION['username']} downloaded file: " . basename($file_path));
        exit;
    } else {
        die("File không tồn tại");
    }
}

// Xử lý tạo thư mục
if (isset($_POST['create_dir'])) {
    $new_dir = $upload_dir . trim($_POST['new_dir'], '/') . '/';
    if (!is_dir($new_dir)) {
        mkdir($new_dir, 0777, true);
        log_activity("User {$_SESSION['username']} created directory: $new_dir");
        echo "Thư mục đã được tạo: " . htmlspecialchars($new_dir);
    } else {
        echo "Thư mục đã tồn tại";
    }
}

// Xử lý tìm kiếm file
$search_results = [];
if (isset($_GET['search'])) {
    $search_query = $_GET['search'];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir));
    foreach ($files as $file) {
        if (strpos($file->getFilename(), $search_query) !== false) {
            $search_results[] = $file->getPathname();
        }
    }
}

// Hiển thị danh sách file
function list_files($dir) {
    $files = scandir($dir);
    $files = array_diff($files, array('.', '..'));
    echo "<ul>";
    foreach ($files as $file) {
        $file_path = $dir . $file;
        if (is_dir($file_path)) {
            echo "<li><strong>$file/</strong> <a href='?subdir=$file'>Mở</a></li>";
        } else {
            echo "<li>$file <a href='?download=$file'>Download</a></li>";
        }
    }
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>NAS Simulator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .file-list { margin-top: 20px; }
        .file-item { padding: 5px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>NAS Simulator</h1>
        <p>Xin chào, <?= htmlspecialchars($_SESSION['username']) ?>! <a href="?logout=1">Đăng xuất</a></p>

        <!-- Form upload -->
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <input type="text" name="subdir" placeholder="Thư mục con (tùy chọn)">
            <input type="submit" value="Upload">
        </form>

        <!-- Tạo thư mục -->
        <form method="post">
            <input type="text" name="new_dir" placeholder="Tên thư mục mới" required>
            <input type="submit" name="create_dir" value="Tạo thư mục">
        </form>

        <!-- Tìm kiếm file -->
        <form method="get">
            <input type="text" name="search" placeholder="Tìm kiếm file" required>
            <input type="submit" value="Tìm kiếm">
        </form>

        <!-- Hiển thị kết quả tìm kiếm -->
        <?php if (!empty($search_results)): ?>
            <h3>Kết quả tìm kiếm:</h3>
            <ul>
                <?php foreach ($search_results as $result): ?>
                    <li><?= htmlspecialchars($result) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- Danh sách file -->
        <div class="file-list">
            <h3>Danh sách file:</h3>
            <?php
            $current_dir = $upload_dir . (isset($_GET['subdir']) ? $_GET['subdir'] . '/' : '');
            list_files($current_dir);
            ?>
        </div>
    </div>
</body>
</html>
