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
    <!DOCTYPE html>
    <html>
    <head>
        <title>NAS Simulator</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* CSS từ index1.php.txt */
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .login-container {
                max-width: 400px;
                padding: 2rem;
                background: white;
                border-radius: 15px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            .login-container h1 {
                margin-bottom: 2rem;
                color: #2a5298;
            }
            .form-group { margin-bottom: 1.5rem; }
            input[type="text"], input[type="password"] {
                width: 100%;
                padding: 0.8rem 1rem;
                border: 2px solid #ddd;
                border-radius: 8px;
                font-size: 1rem;
            }
            .btn {
                padding: 0.8rem 1.5rem;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .btn-primary {
                background: #4a90e2;
                color: white;
            }
        </style>
    </head>
    <body>
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
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

// Xử lý upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $subdir = isset($_POST['subdir']) ? trim($_POST['subdir'], '/') . '/' : '';
    $target_dir = $upload_dir . $subdir;

    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    if ($file['error'] !== UPLOAD_ERR_OK) die("Upload error: " . $file['error']);
    if ($file['size'] > $max_file_size) die("File exceeds 100MB limit");

    $file_name = basename($file['name']);
    $target_path = $target_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        log_activity("User {$_SESSION['username']} uploaded: $subdir$file_name");
        echo "Upload thành công";
    } else {
        echo "Upload thất bại";
    }
}

// Xử lý download file
if (isset($_GET['download'])) {
    $file_path = $upload_dir . basename($_GET['download']);
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
        readfile($file_path);
        log_activity("User {$_SESSION['username']} downloaded: " . basename($file_path));
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
        echo "Thư mục đã được tạo";
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>NAS Simulator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS từ index1.php.txt */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary { background: #4a90e2; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .file-list { margin-top: 2rem; background: white; border-radius: 10px; }
        .file-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        .file-icon {
            width: 40px;
            height: 40px;
            margin-right: 1rem;
            background: #4a90e2;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        @media (max-width: 768px) {
            .container { margin: 1rem; padding: 1rem; }
            .header { flex-direction: column; gap: 1rem; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-folder"></i> NAS Simulator</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout=1" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Upload & Create Folder -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <form method="post" enctype="multipart/form-data" style="flex-grow: 1;">
                <div style="display: flex; gap: 0.5rem;">
                    <input type="file" name="file" required style="flex-grow: 1;">
                    <input type="text" name="subdir" placeholder="Subdirectory" style="flex-basis: 200px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
            <form method="post" style="display: flex; gap: 0.5rem;">
                <input type="text" name="new_dir" placeholder="New directory" required>
                <button type="submit" name="create_dir" class="btn btn-primary"><i class="fas fa-folder-plus"></i> Create</button>
            </form>
        </div>

        <!-- Search -->
        <form method="get" style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" placeholder="Search files..." required style="flex-grow: 1;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>

        <!-- Search Results -->
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

        <!-- File List -->
        <div class="file-list">
            <?php
            $current_dir = $upload_dir . (isset($_GET['subdir']) ? $_GET['subdir'] . '/' : '');
            $files = scandir($current_dir);
            $files = array_diff($files, array('.', '..'));
            
            foreach ($files as $file): 
                $file_path = $current_dir . $file;
                $is_dir = is_dir($file_path);
            ?>
                <div class="file-item">
                    <div class="file-icon"><?= $is_dir ? '<i class="fas fa-folder"></i>' : '<i class="fas fa-file"></i>' ?></div>
                    <div class="file-name">
                        <?= htmlspecialchars($file) ?>
                        <?php if ($is_dir): ?>
                            <a href="?subdir=<?= urlencode($file) ?>" style="margin-left: 0.5rem; color: #4a90e2;">[Open]</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_dir): ?>
                        <a href="?download=<?= urlencode($file) ?>" class="btn btn-primary"><i class="fas fa-download"></i> Download</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
