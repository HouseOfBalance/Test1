<?php
session_start();

// Cấu hình hệ thống
$upload_dir = 'C:/nas_storage/';
$max_file_size = 1024 * 1024 * 1024 * 5; // 5GB
$log_file = 'C:/nas_storage/log.txt';
$users = [
    'admin' => password_hash('admin123', PASSWORD_BCRYPT),
];

// Cấu hình PHP động
ini_set('upload_max_filesize', '5G');
ini_set('post_max_size', '5G');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// Hàm ghi log
function log_activity($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Hàm tạo CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hàm kiểm tra CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        session_regenerate_id(true);
        log_activity("User $username logged in.");
        header('Location: ?step=second_auth');
        exit();
    } else {
        die("Đăng nhập thất bại");
    }
}

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    log_activity("User {$_SESSION['username']} logged out.");
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Second authentication
if (isset($_GET['step']) && $_GET['step'] == 'second_auth') {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header('Location: ?step=login');
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['second_password'])) {
        $second_password = $_POST['second_password'];

        // Dummy personal password validation (replace with real logic)
        if ($second_password === 'tranvankhanh') {
            $_SESSION['second_auth'] = true;
            header('Location: ?step=main');
            exit();
        } else {
            $error = 'Invalid personal password';
        }
    }

    // Display second authentication form
    echo '<!DOCTYPE html>
    <html>
    <head><title>Second Authentication</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: "Segoe UI", sans-serif; 
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .login-box form {
            display: flex;
            flex-direction: column;
        }
        .login-box input {
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .login-box button {
            padding: 0.8rem;
            background: #4a90e2;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }
        .login-box button:hover {
            background: #357abd;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
    </head>
    <body>
        <div class="login-box">
            <h1>Personal Password</h1>';
    if (isset($error)) echo "<p class='error'>$error</p>";
    echo '<form method="POST">
            <input type="password" name="second_password" placeholder="Personal Password" required>
            <button type="submit">Submit</button>
        </form>
        </div>
    </body>
    </html>';
    exit();
}

// Main page
if (isset($_GET['step']) && $_GET['step'] == 'main') {
    if (!isset($_SESSION['logged_in']) || !isset($_SESSION['second_auth']) || !$_SESSION['second_auth']) {
        header('Location: ?step=login');
        exit();
    }

    // Hàm tính kích thước thư mục
    function calculate_folder_size($path) {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    // Xử lý upload file hoặc thư mục
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            die("CSRF token không hợp lệ");
        }

        $subdir = isset($_POST['subdir']) ? trim($_POST['subdir'], '/') . '/' : '';
        $target_dir = $upload_dir . $subdir;

        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $uploaded_files = [];
        $errors = [];

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['files']['name'][$key];
            $file_size = $_FILES['files']['size'][$key];
            $file_tmp = $_FILES['files']['tmp_name'][$key];
            $file_error = $_FILES['files']['error'][$key];

            if ($file_error !== UPLOAD_ERR_OK) {
                $errors[] = "Lỗi khi upload file $file_name";
                continue;
            }

            if ($file_size > $max_file_size) {
                $errors[] = "File $file_name vượt quá kích thước cho phép (5GB)";
                continue;
            }

            $new_file_name = uniqid() . '_' . basename($file_name);
            $target_path = $target_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                $uploaded_files[] = $new_file_name;
                log_activity("User {$_SESSION['username']} uploaded: $subdir$new_file_name");
            } else {
                $errors[] = "Không thể upload file $file_name";
            }
        }

        if (!empty($uploaded_files)) {
            $_SESSION['upload_success'] = implode(', ', $uploaded_files);
        }
        if (!empty($errors)) {
            $_SESSION['upload_error'] = implode('<br>', $errors);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Xử lý download file
    if (isset($_GET['download'])) {
        $file_path = $upload_dir . basename($_GET['download']);
        
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            log_activity("User {$_SESSION['username']} downloaded: " . basename($file_path));
            exit;
        }
    }

    // Xử lý xóa file
    if (isset($_GET['delete'])) {
        $file_path = $upload_dir . basename($_GET['delete']);
        
        if (file_exists($file_path) && unlink($file_path)) {
            log_activity("User {$_SESSION['username']} deleted: " . basename($file_path));
            echo "<script>alert('Đã xóa file thành công')</script>";
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }

    // Xử lý tạo thư mục
    if (isset($_POST['create_dir'])) {
        if (!verify_csrf_token($_POST['csrf_token'])) {
            die("CSRF token không hợp lệ");
        }

        $new_dir = $upload_dir . trim($_POST['new_dir'], '/') . '/';
        
        if (!is_dir($new_dir)) {
            mkdir($new_dir, 0777, true);
            log_activity("User {$_SESSION['username']} created directory: $new_dir");
            echo "<script>alert('Đã tạo thư mục thành công')</script>";
        } else {
            echo "<script>alert('Thư mục đã tồn tại')</script>";
        }
    }

    // Hàm hiển thị file
    function display_files($dir) {
        $files = scandir($dir);
        foreach (array_diff($files, ['.', '..']) as $file) {
            $file_path = $dir . $file;
            $is_dir = is_dir($file_path);
            $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
            $is_video = in_array($file_ext, ['mp4', 'webm']);
            
            echo '<div class="file-item">
                    <div class="file-icon">'.($is_dir ? '<i class="fas fa-folder"></i>' : ($is_image ? '<i class="fas fa-image"></i>' : ($is_video ? '<i class="fas fa-video"></i>' : '<i class="fas fa-file"></i>'))).'</div>
                    <div class="file-name">'.htmlspecialchars($file).($is_dir ? '/' : '').'</div>
                    <div class="file-actions">'.
                        (!$is_dir ? 
                        ($is_image || $is_video ? '<a href="?preview='.urlencode($file).'" class="btn-preview"><i class="fas fa-eye"></i></a>' : '') .
                        '<a href="?download='.urlencode($file).'" class="btn-download"><i class="fas fa-download"></i></a>
                         <a href="?delete='.urlencode($file).'" class="btn-delete" onclick="return confirm(\'Xóa file này?\')"><i class="fas fa-trash"></i></a>' 
                        : '<a href="?subdir='.urlencode($file).'" class="btn-open"><i class="fas fa-folder-open"></i></a>').'
                    </div>
                  </div>';
        }
    }

    // Display main page content
    echo '<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>NAS System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { 
                font-family: "Segoe UI", sans-serif; 
                background: #f0f2f5;
                min-height: 100vh;
                padding: 1rem;
                background-image: url("'.(isset($_GET['preview']) && in_array(pathinfo($_GET['preview'], PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png']) ? $upload_dir . urlencode($_GET['preview']) : '').'");
                background-size: cover;
                background-position: center;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                padding: 1rem;
            }
            
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #eee;
            }
            
            .file-manager {
                display: grid;
                gap: 1.5rem;
            }
            
            .upload-section {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
            }
            
            .file-list {
                background: #fff;
                border: 1px solid #eee;
                border-radius: 8px;
                padding: 1rem;
            }
            
            .file-item {
                display: flex;
                align-items: center;
                padding: 1rem;
                background: #fff;
                border-radius: 8px;
                transition: all 0.2s;
                margin-bottom: 0.5rem;
            }
            
            .file-item:hover {
                background: #f8f9fa;
                transform: translateX(5px);
            }
            
            .file-icon {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 1rem;
                color: #4a90e2;
            }
            
            .file-name {
                flex-grow: 1;
                color: #333;
                font-weight: 500;
            }
            
            .file-actions {
                display: flex;
                gap: 0.5rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .btn-download {
                background: #4a90e2;
                color: white;
            }
            
            .btn-delete {
                background: #e74c3c;
                color: white;
            }
            
            .btn-open {
                background: #2ecc71;
                color: white;
            }
            
            .btn-preview {
                background: #f1c40f;
                color: white;
            }
            
            .btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }
            
            .login-box {
                max-width: 400px;
                margin: 5rem auto;
                padding: 2rem;
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            
            .toast {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 8px;
                color: white;
                background: #4CAF50;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                animation: slideIn 0.5s ease-out;
                z-index: 1000;
            }
            
            .toast.error {
                background: #f44336;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }

            @media (max-width: 768px) {
                body {
                    padding: 0.5rem;
                }
                .container {
                    padding: 0.5rem;
                    border-radius: 0;
                }
                .header {
                    flex-direction: column;
                    align-items: flex-start;
                    margin-bottom: 1rem;
                }
                .upload-section {
                    padding: 0.5rem;
                }
                .file-item {
                    flex-direction: column;
                    align-items: flex-start;
                    padding: 0.5rem;
                }
                .file-actions {
                    margin-top: 0.5rem;
                    flex-wrap: wrap;
                }
                .btn {
                    width: 100%;
                    justify-content: center;
                    margin-bottom: 0.5rem;
                }
                .login-box {
                    margin: 2rem auto;
                    padding: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php if (isset($_SESSION['upload_success'])): ?>
            <div class="toast">
                Upload thành công: <?= htmlspecialchars($_SESSION['upload_success']) ?>
                <?php unset($_SESSION['upload_success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['upload_error'])): ?>
                        <div class="toast error">
                <?= $_SESSION['upload_error'] ?>
                <?php unset($_SESSION['upload_error']); ?>
            </div>
            <?php endif; ?>

            <div class="header">
                <h1><i class="fas fa-server"></i> NAS System</h1>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <span style="color: #666;">Xin chào, <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="?logout=1" class="btn btn-delete"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            
            <div class="file-manager">
                <div class="upload-section">
                    <form method="post" enctype="multipart/form-data" style="display: flex; gap: 1rem;">
                        <input type="file" name="files[]" multiple required 
                            style="flex-grow: 1; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <button type="submit" class="btn btn-download">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </form>
                    
                    <!-- Thêm form tạo thư mục -->
                    <form method="post" style="margin-top: 1rem; display: flex; gap: 1rem;">
                        <input type="text" name="new_dir" placeholder="Tên thư mục mới" required 
                            style="flex-grow: 1; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <button type="submit" name="create_dir" class="btn btn-open">
                            <i class="fas fa-folder-plus"></i> Tạo thư mục
                        </button>
                    </form>
                </div>
                
                <!-- Breadcrumb điều hướng -->
                <div class="breadcrumb" style="margin-bottom: 1rem; color: #666;">
                    <?php 
                    $current_dir = isset($_GET['subdir']) ? $_GET['subdir'] : '';
                    echo '<a href="?step=main">Root</a> / ';
                    if (!empty($current_dir)) {
                        $parts = explode('/', $current_dir);
                        $path = '';
                        foreach ($parts as $part) {
                            if (!empty($part)) {
                                $path .= $part . '/';
                                echo '<a href="?subdir='.urlencode($path).'">'.$part.'</a> / ';
                            }
                        }
                    }
                    ?>
                </div>

                <div class="file-list">
                    <h3 style="margin-bottom: 1rem;">Danh sách file:</h3>
                    <?php 
                    $current_directory = $upload_dir.(isset($_GET['subdir']) ? $_GET['subdir'].'/' : '');
                    display_files($current_directory); 
                    ?>
                </div>
            </div>
        </div>
        <script>
            // Xử lý preview media
            <?php if(isset($_GET['preview'])): ?>
                const previewUrl = '<?= $upload_dir.urlencode($_GET['preview']) ?>';
                const isVideo = previewUrl.match(/\.(mp4|webm)$/i);
                
                if(isVideo) {
                    document.body.innerHTML = `
                        <video controls autoplay style="width: 100%; height: 100%; object-fit: contain;">
                            <source src="${previewUrl}" type="video/${previewUrl.split('.').pop()}">
                        </video>
                        <button onclick="window.history.back()" style="position: fixed; top: 20px; right: 20px; padding: 10px; background: #fff; border: none; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                } else {
                    document.body.innerHTML = `
                        <img src="${previewUrl}" style="width: 100%; height: 100%; object-fit: contain;">
                        <button onclick="window.history.back()" style="position: fixed; top: 20px; right: 20px; padding: 10px; background: #fff; border: none; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                }
            <?php endif; ?>

            // Tự động ẩn toast sau 3 giây
            document.addEventListener('DOMContentLoaded', function() {
                const toasts = document.querySelectorAll('.toast');
                toasts.forEach(toast => {
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
            });
        </script>
    </body>
    </html>
<?php endif; ?>
