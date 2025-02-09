<?php
session_start();

// Cấu hình
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

// Xử lý upload file (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json');

    $file = $_FILES['file'];
    $subdir = isset($_POST['subdir']) ? trim($_POST['subdir'], '/') . '/' : '';
    $target_dir = $upload_dir . $subdir;

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die(json_encode(['status' => 'error', 'message' => 'Upload failed with error code: ' . $file['error']]));
    }

    if ($file['size'] > $max_file_size) {
        die(json_encode(['status' => 'error', 'message' => 'File exceeds maximum allowed size (5GB)']));
    }

    $file_name = basename($file['name']);
    $target_path = $target_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        log_activity("User {$_SESSION['username']} uploaded file: $subdir$file_name");
        echo json_encode(['status' => 'success', 'filename' => $file_name]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
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
        document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e) {
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
                        alert('Upload thành công: ' + response.filename);
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
    </script>
</body>
</html>
