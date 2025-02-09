<?php
session_start();

// Cấu hình
ini_set('upload_max_filesize', '5G'); // Tăng giới hạn upload lên 5GB
ini_set('post_max_size', '5G');       // Tăng giới hạn POST lên 5GB
ini_set('max_execution_time', '600'); // Tăng thời gian thực thi lên 10 phút
ini_set('max_input_time', '600');     // Tăng thời gian nhập liệu lên 10 phút

$upload_dir = 'C:/nas_storage/';      // Thư mục lưu trữ file
$max_file_size = 1024 * 1024 * 1024 * 5; // 5GB
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
        die("File vượt quá kích thước cho phép (5GB)");
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

// Xử lý xóa file
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $current_dir = $upload_dir . (isset($_GET['subdir']) ? $_GET['subdir'] . '/' : '');
    $file_path = $current_dir . $filename;

    if (file_exists($file_path) && !is_dir($file_path)) {
        unlink($file_path);
        log_activity("User {$_SESSION['username']} deleted file: $filename");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
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
    global $upload_dir;
    $files = scandir($dir);
    $files = array_diff($files, array('.', '..'));
    echo "<ul>";
    foreach ($files as $file) {
        $file_path = $dir . $file;
        if (is_dir($file_path)) {
            echo "<li><strong>$file/</strong> <a href='?subdir=$file'>Mở</a></li>";
        } else {
            echo "<li>$file 
                    <a href='?download=$file' class='btn btn-sm btn-primary'><i class='fas fa-download'></i> Download</a>
                    <a href='?delete=$file' class='btn btn-sm btn-danger' onclick=\"return confirm('Bạn có chắc muốn xóa file này?')\"><i class='fas fa-trash'></i> Delete</a>
                </li>";
        }
    }
    echo "</ul>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>NAS Simulator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        .form-group {
            margin-bottom: 15px;
        }
        input[type="text"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        input[type="text"]:focus, input[type="password"]:focus, input[type="file"]:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary {
            background: #4a90e2;
            color: white;
        }
        .btn-primary:hover {
            background: #357abd;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .file-list {
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s ease;
        }
        .file-item:hover {
            background: #f8f9fa;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-icon {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            background: #4a90e2;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .file-name {
            flex-grow: 1;
            font-weight: 500;
        }
        .search-results {
            margin-top: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .login-container h1 {
            margin-bottom: 20px;
            color: #2a5298;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 10px;
            }
            .header {
                flex-direction: column;
                gap: 10px;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .file-icon {
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['username'])): ?>
    <div class="login-container">
        <h1><i class="fas fa-lock"></i> NAS Login</h1>
        <form method="post">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary">
                <span class="loading"></span> Login
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-folder"></i> NAS Simulator</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout=1" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Form upload -->
        <form method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <input type="file" name="file" required style="flex-grow: 1;">
                <input type="text" name="subdir" placeholder="Subdirectory (optional)" style="flex-basis: 200px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
            </div>
        </form>

        <!-- Tạo thư mục -->
        <form method="post" style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="new_dir" placeholder="New directory" required>
                <button type="submit" name="create_dir" class="btn btn-primary"><i class="fas fa-folder-plus"></i> Create Folder</button>
            </div>
        </form>

        <!-- Tìm kiếm file -->
        <form method="get" style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
                <input type="text" name="search" placeholder="Search files..." required style="flex-grow: 1;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>

        <!-- Kết quả tìm kiếm -->
        <?php if (!empty($search_results)): ?>
        <div class="search-results">
            <h3>Search Results:</h3>
            <?php foreach ($search_results as $result): ?>
                <div class="file-item">
                    <div class="file-icon"><i class="fas fa-file"></i></div>
                    <div class="file-name"><?= htmlspecialchars($result) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Danh sách file -->
        <div class="file-list">
            <h3>File List:</h3>
            <?php
            $current_dir = $upload_dir . (isset($_GET['subdir']) ? $_GET['subdir'] . '/' : '');
            list_files($current_dir);
            ?>
        </div>
    </div>
    <?php endif; ?>
</body>
    </html>
