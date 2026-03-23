<?php
require_once 'includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirectBasedOnRole();
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields';
    } else {
        $conn = $db->getConnection();
        $username = $conn->real_escape_string($username);
        $password = $conn->real_escape_string($password);
        $role = $conn->real_escape_string($role);
        
        $sql = "SELECT * FROM users WHERE username = '$username' AND role = '$role' AND status = 'active'";
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Direct string comparison (FIXED: removed password_verify)
            if ($password === $user['password']) {
                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = " . $user['user_id'];
                $conn->query($updateSql);
                
                redirectBasedOnRole();
                exit();
            } else {
                $error = 'Invalid password';
            }
        } else {
            $error = 'User not found or inactive';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>Please login to continue</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form" data-validate>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="management">Management</option>
                        <option value="resident">Resident</option>
                        <option value="guard">Security Guard</option>
                    </select>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>