<?php
require 'includes/db_connect.php';

$success = '';
$error   = '';
$token   = '';

// Ensure reset columns exist
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires DATETIME DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $upd->bind_param("ssi", $token, $expires, $user['id']);
            $upd->execute();

            $success = "A password reset token has been generated for <strong>" . htmlspecialchars($email) . "</strong>.";
        } else {
            // Don't reveal if email exists
            $success = "If an account exists with that email, a reset link has been prepared.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – TestMate</title>
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
            background: #2c3e50; color: white;
        }
        .submit-btn:hover { background: #1a252f; }
        .alert-error { background: #fdecea; color: #e74c3c; border-left: 4px solid #e74c3c; padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .alert-success { background: #eafaf1; color: #27ae60; border-left: 4px solid #27ae60; padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .token-box {
            background: #f0f4f8;
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 16px;
            margin-top: 16px;
            text-align: center;
        }
        .token-box .label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .token-box code {
            display: block;
            font-size: 16px;
            font-family: monospace;
            color: #2c3e50;
            background: white;
            padding: 10px;
            border-radius: 6px;
            word-break: break-all;
            user-select: all;
        }
        .token-box .hint { font-size: 12px; color: #888; margin-top: 8px; }
        .auth-switch { text-align: center; margin-top: 20px; font-size: 14px; color: #888; }
        .auth-switch a { color: #3498db; font-weight: 600; text-decoration: none; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #888; text-decoration: none; margin-bottom: 20px; }
        .back-link:hover { color: #333; }
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
        <h2>Forgot Password?</h2>
        <p class="sub">Enter your email and we'll generate a reset token for you.</p>

        <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($token): ?>
        <div class="token-box">
            <div class="label">Your Reset Token</div>
            <code><?php echo htmlspecialchars($token); ?></code>
            <div class="hint">Copy this token and click below to reset your password.<br>Token expires in 1 hour.</div>
        </div>
        <div style="text-align:center;margin-top:16px;">
            <a href="/testmate/reset-password.php?token=<?php echo htmlspecialchars($token); ?>" class="submit-btn" style="display:inline-block;text-decoration:none;text-align:center;">Reset My Password</a>
        </div>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com" required autofocus
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <button type="submit" class="submit-btn">Send Reset Token</button>
        </form>
        <?php endif; ?>

        <div class="auth-switch">
            Remember your password? <a href="/testmate/login.php">Login here</a>
        </div>
    </div>
</div>

<div style="color:rgba(255,255,255,.3);font-size:12px;text-align:center;padding:16px;">
    © 2026 TestMate Namibia
</div>

</body>
</html>