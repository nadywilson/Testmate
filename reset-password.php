<?php
require 'includes/db_connect.php';

$success = '';
$error   = '';
$token   = $_GET['token'] ?? $_POST['token'] ?? '';

// Ensure reset columns exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token    = trim($_POST['token']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($token) || empty($password) || empty($confirm)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $upd->bind_param("si", $hash, $user['id']);
            $upd->execute();

            $success = "Your password has been reset successfully! You can now login with your new password.";
            $token = ''; // clear token so form hides
        } else {
            $error = "Invalid or expired token. Please request a new one.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – TestMate</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #1a252f, #2c3e50); min-height: 100vh; display: flex; flex-direction: column; }
        .login-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; }
        .logo-area { text-align: center; margin-bottom: 36px; }
        .logo-icon { width: 64px; height: 64px; background: #3498db; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: white; margin-bottom: 12px; }
        .logo-area h1 { color: white; font-size: 28px; font-weight: 800; }
        .logo-area p  { color: rgba(255,255,255,.6); font-size: 14px; margin-top: 4px; }

        .login-box {
            background: white;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        .login-box h2 { font-size: 22px; margin-bottom: 4px; }
        .login-box .sub { color: #888; font-size: 13px; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-group input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 15px; outline: none; transition: border-color .2s;
        }
        .form-group input:focus { border-color: #3498db; }
        .submit-btn {
            width: 100%; padding: 13px;
            border: none; border-radius: 8px;
            font-size: 16px; font-weight: 700;
            cursor: pointer; transition: all .2s; margin-top: 6px;
            background: #27ae60; color: white;
        }
        .submit-btn:hover { background: #219a52; }
        .alert-error { background: #fdecea; color: #e74c3c; border-left: 4px solid #e74c3c; padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .alert-success { background: #eafaf1; color: #27ae60; border-left: 4px solid #27ae60; padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .auth-switch { text-align: center; margin-top: 20px; font-size: 14px; color: #888; }
        .auth-switch a { color: #3498db; font-weight: 600; text-decoration: none; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #888; text-decoration: none; margin-bottom: 20px; }
        .back-link:hover { color: #333; }
        .token-display {
            background: #f0f4f8;
            border-radius: 8px;
            padding: 10px 14px;
            font-family: monospace;
            font-size: 13px;
            color: #555;
            margin-bottom: 16px;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="logo-area">
        <div class="logo-icon">T</div>
        <h1>TestMate</h1>
        <p>Namibia Learner's Licence Preparation</p>
    </div>

    <div class="login-box">
        <a href="/testmate/login.php" class="back-link">← Back to Login</a>
        <h2>Reset Password</h2>
        <p class="sub">Enter your token and choose a new password.</p>

        <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-success"><?php echo $success; ?></div>
        <div style="text-align:center;margin-top:20px;">
            <a href="/testmate/login.php" class="submit-btn" style="display:inline-block;text-decoration:none;text-align:center;background:#2c3e50;">Go to Login</a>
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <?php if (empty($token)): ?>
            <div class="form-group">
                <label>Reset Token</label>
                <input type="text" name="token" placeholder="Paste your reset token here" required>
            </div>
            <?php else: ?>
            <div class="token-display">Token: <?php echo htmlspecialchars($token); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" placeholder="Min 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Repeat password" required>
            </div>
            <button type="submit" class="submit-btn">Reset Password</button>
        </form>
        <?php endif; ?>

        <div class="auth-switch">
            Don't have a token? <a href="/testmate/forgot-password.php">Request one</a>
        </div>
    </div>
</div>

<div style="color:rgba(255,255,255,.3);font-size:12px;text-align:center;padding:16px;">
    © 2026 TestMate Namibia
</div>

</body>
</html>