<?php
session_start();

// Cấu hình hệ thống
ini_set('upload_max_filesize', '5G'); // Tăng giới hạn upload lên 5GB
ini_set('post_max_size', '5G');       // Tăng giới hạn POST lên 5GB
ini_set('max_execution_time', '600'); // Tăng thời gian thực thi lên 10 phút
ini_set('max_input_time', '600');     // Tăng thời gian nhập liệu lên 10 phút

$upload_dir = 'C:/nas_storage/';      // Thư mục lưu trữ file
$max_file_size = 5 * 1024 * 1024 * 1024; // 5GB
$log_file = 'C:/nas_storage/log.txt'; // File log hoạt động
$users = [
    'admin' => password_hash('admin123', PASSWORD_BCRYPT), // User mẫu
];

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

// Đăng nhập
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['username'] = $username;
        session_regenerate_id(true);
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

// Xử lý upload file (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    header('Content-Type: application/json');

    if (!verify_csrf_token($_POST['csrf_token'])) {
        die(json_encode(['status' => 'error', 'message' => 'CSRF token không hợp lệ']));
    }

    $subdir = isset($_POST['subdir']) ? trim($_POST['subdir'], '/') . '/' : '';
    $target_dir = $upload_dir . $subdir;

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

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
        echo json_encode(['status' => 'success', 'files' => $uploaded_files]);
    } else {
        echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
    }
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
    } else {
        die("File không tồn tại");
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAS System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Progress Container */
        #upload-progress {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 300px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 15px;
            display: none;
            z-index: 1000;
        }

        .progress-bar {
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: #4a90e2;
            width: 0%;
            transition: width 0.3s ease;
        }

        .time-estimate {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Các phần CSS khác giữ nguyên */
        /* ... (phần CSS trước đó) ... */
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['username'])): ?>
    <div class="login-box">
        <h2 style="text-align: center; margin-bottom: 1.5rem;">NAS Login</h2>
        <form method="post">
            <div style="margin-bottom: 1rem;">
                <input type="text" name="username" placeholder="Username" required 
                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <input type="password" name="password" placeholder="Password" required 
                    style="width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            <button type="submit" name="login" class="btn" 
                style="width: 100%; background: #4a90e2; color: white;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
    <?php else: ?>
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
                <form method="post" enctype="multipart/form-data" id="upload-form" style="display: flex; gap: 1rem;">
                    <input type="file" name="files[]" multiple required 
                        style="flex-grow: 1; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <button type="submit" class="btn btn-download">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </form>
            </div>

            <div class="file-list">
                <h3 style="margin-bottom: 1rem;">Danh sách file:</h3>
                <?php display_files($upload_dir); ?>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div id="upload-progress">
        <div class="progress-header">
            <h4>Uploading File...</h4>
            <span id="progress-percent">0%</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="time-estimate">
            Time remaining: <span id="time-remaining">Calculating...</span>
        </div>
    </div>

    <script>
        // Xử lý upload file bằng AJAX
        document.getElementById('upload-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const xhr = new XMLHttpRequest();
            const progressContainer = document.getElementById('upload-progress');
            const progressFill = document.querySelector('.progress-fill');
            const progressPercent = document.getElementById('progress-percent');
            const timeRemaining = document.getElementById('time-remaining');

            let uploadStartTime;

            xhr.upload.addEventListener('loadstart', () => {
                progressContainer.style.display = 'block';
                uploadStartTime = Date.now();
            });

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total * 100).toFixed(1);
                    progressFill.style.width = percent + '%';
                    progressPercent.textContent = percent + '%';

                    // Tính thời gian còn lại
                    const timeElapsed = (Date.now() - uploadStartTime) / 1000;
                    const uploadSpeed = e.loaded / timeElapsed;
                    const remainingBytes = e.total - e.loaded;
                    const remainingTime = remainingBytes / uploadSpeed;

                    timeRemaining.textContent = formatTime(remainingTime);
                }
            });

            xhr.addEventListener('load', () => {
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        alert('Upload thành công: ' + response.files.join(', '));
                        window.location.reload();
                    } else {
                        alert('Lỗi: ' + response.message);
                    }
                }, 1000);
            });

            xhr.open('POST', '', true);
            xhr.send(formData);
        });

        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            seconds = Math.floor(seconds % 60);
            return `${minutes}m ${seconds}s`;
        }

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
    <?php endif; ?>
</body>
                </html>
