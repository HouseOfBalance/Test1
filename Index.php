<!DOCTYPE html>
<html>
<head>
    <title>NAS Simulator</title>
    <style>
        /* Reset CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Body và nền */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Container chính */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        input[type="text"],
        input[type="password"],
        input[type="file"] {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="file"]:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }

        /* Buttons */
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

        /* File list */
        .file-list {
            margin-top: 2rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .file-item {
            display: flex;
            align-items: center;
            padding: 1rem;
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
            margin-right: 1rem;
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

        /* Search results */
        .search-results {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* Login form */
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
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

        /* Navigation */
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #666;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['username'])): ?>
    <div class="login-container">
        <h1>🔒 NAS Login</h1>
        <form method="post">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary">Login</button>
        </form>
    </div>
    <?php else: ?>
    <div class="container">
        <div class="header">
            <h1>📁 NAS Simulator</h1>
            <div class="user-info">
                <span>👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="?logout=1" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <form method="post" enctype="multipart/form-data" style="flex-grow: 1;">
                <div style="display: flex; gap: 0.5rem;">
                    <input type="file" name="file" required style="flex-grow: 1;">
                    <input type="text" name="subdir" placeholder="Subdirectory (optional)" style="flex-basis: 200px;">
                    <button type="submit" class="btn btn-primary">📤 Upload</button>
                </div>
            </form>
            
            <form method="post" style="display: flex; gap: 0.5rem;">
                <input type="text" name="new_dir" placeholder="New directory" required>
                <button type="submit" name="create_dir" class="btn btn-primary">📁 Create Folder</button>
            </form>
        </div>

        <!-- Search Form -->
        <form method="get" style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="search" placeholder="Search files..." required style="flex-grow: 1;">
                <button type="submit" class="btn btn-primary">🔍 Search</button>
            </div>
        </form>

        <!-- Search Results -->
        <?php if (!empty($search_results)): ?>
        <div class="search-results">
            <h3>Search Results:</h3>
            <?php foreach ($search_results as $result): ?>
                <div class="file-item">
                    <div class="file-icon">📄</div>
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
                    <div class="file-icon"><?= $is_dir ? '📁' : '📄' ?></div>
                    <div class="file-name">
                        <?= htmlspecialchars($file) ?>
                        <?php if ($is_dir): ?>
                            <a href="?subdir=<?= urlencode($file) ?>" style="margin-left: 0.5rem; font-size: 0.9em; color: #4a90e2;">[Open]</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_dir): ?>
                        <a href="?download=<?= urlencode($file) ?>" class="btn btn-primary">⬇️ Download</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
