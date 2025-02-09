
<?php
session_start();

// Existing code from the uploaded file
{uploaded_php_content}

// Additional functionality starts here

// Login handler
if (!isset($_GET['step']) || $_GET['step'] == 'login') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'], $_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Dummy login credentials (replace with real authentication)
        if ($username === 'admin' && $password === 'adminpass') {
            $_SESSION['logged_in'] = true;
            header('Location: ?step=second_auth');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }

    // Display login form
    echo '<!DOCTYPE html>
    <html>
    <head><title>Login</title></head>
    <body>
        <h1>Login</h1>';
    if (isset($error)) echo "<p style='color:red;'>$error</p>";
    echo '<form method="POST">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required><br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required><br>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>';
    exit();
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
        if ($second_password === 'mypersonalpass') {
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
    <head><title>Second Authentication</title></head>
    <body>
        <h1>Personal Password</h1>';
    if (isset($error)) echo "<p style='color:red;'>$error</p>";
    echo '<form method="POST">
            <label for="second_password">Personal Password:</label>
            <input type="password" id="second_password" name="second_password" required><br>
            <button type="submit">Submit</button>
        </form>
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

    // Display main page content
    echo '<!DOCTYPE html>
    <html>
    <head><title>Main Page</title></head>
    <body>
        <h1>Welcome to the Main Page</h1>
        <p>You have successfully logged in and passed personal authentication.</p>
    </body>
    </html>';
    exit();
}
?>
