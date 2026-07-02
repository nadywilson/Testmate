<?php
require 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? '/testmate/admin/index.php' : '/testmate/dashboard.php'));
    exit();
}

$error    = '';
$mode     = $_GET['mode'] ?? ''; // 'admin' or 'learner'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $mode     = $_POST['mode'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {

            // Check role matches selected mode
            if ($mode === 'admin' && $user['role'] !== 'admin') {
                $error = "You do not have administrator access.";
            } elseif ($mode === 'learner' && $user['role'] === 'admin') {
                $error = "Please use the Administrator login.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                header("Location: " . ($user['role'] === 'admin' ? '/testmate/admin/index.php' : '/testmate/dashboard.php'));
                exit();
            }
        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TestMate</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #1a252f, #2c3e50); min-height: 100vh; display: flex; flex-direction: column; }

        .login-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Logo */
        .logo-area { text-align: center; margin-bottom: 36px; }
        .logo-icon { width: 64px; height: 64px; background: #3498db; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: white; margin-bottom: 12px; }
        .logo-area h1 { color: white; font-size: 28px; font-weight: 800; }
        .logo-area p  { color: rgba(255,255,255,.6); font-size: 14px; margin-top: 4px; }

        /* Mode selector cards */
        .mode-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            width: 100%;
            max-width: 480px;
            margin-bottom: 0;
        }
        .mode-card {
            background: rgba(255,255,255,.08);
            border: 2px solid rgba(255,255,255,.15);
            border-radius: 14px;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            color: white;
            display: block;
        }
        .mode-card:hover {
            background: rgba(255,255,255,.15);
            border-color: rgba(255,255,255,.4);
            transform: translateY(-3px);
        }
        .mode-card.admin:hover  { border-color: #e74c3c; background: rgba(231,76,60,.15); }
        .mode-card.learner:hover { border-color: #3498db; background: rgba(52,152,219,.15); }
        .mode-card .card-icon { font-size: 2.5rem; margin-bottom: 12px; display: block; }
        .mode-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .mode-card p  { font-size: 12px; opacity: .7; }

        /* Login form box */
        .login-box {
            background: white;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        .login-box .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #888;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            margin-bottom: 20px;
        }
        .login-box .back-btn:hover { color: #333; }
        .login-box .mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .mode-badge.admin   { background: #fdecea; color: #e74c3c; }
        .mode-badge.learner { background: #eaf4ff; color: #2471a3; }
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
        }
        .submit-btn.admin   { background: #e74c3c; color: white; }
        .submit-btn.admin:hover   { background: #c0392b; }
        .submit-btn.learner { background: #2c3e50; color: white; }
        .submit-btn.learner:hover { background: #1a252f; }

        .alert-error { background: #fdecea; color: #e74c3c; border-left: 4px solid #e74c3c; padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }

        .auth-switch { text-align: center; margin-top: 20px; font-size: 14px; color: #888; }
        .auth-switch a { color: #3498db; font-weight: 600; text-decoration: none; }

        .admin-note {
            background: rgba(231,76,60,.1);
            border: 1px solid rgba(231,76,60,.3);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            color: #c0392b;
            margin-bottom: 16px;
            text-align: center;
        }

        footer-note { color: rgba(255,255,255,.4); font-size: 12px; text-align: center; padding: 20px; }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- Logo -->
    <div class="logo-area">
        <div class="logo-icon">T</div>
        <h1>TestMate</h1>
        <p>Namibia Learner's Licence Preparation</p>
    </div>

    <?php if (!$mode): ?>
    <!-- ── MODE SELECTOR ── -->
    <div class="mode-cards">
        <a href="/testmate/login.php?mode=admin" class="mode-card admin">
            <span class="card-icon">🔐</span>
            <h3>Administrator</h3>
            <p>Manage content, questions and users</p>
        </a>
        <a href="/testmate/login.php?mode=learner" class="mode-card learner">
            <span class="card-icon">🎓</span>
            <h3>Learner</h3>
            <p>Study, quiz and track your progress</p>
        </a>
    </div>
    <p style="color:rgba(255,255,255,.4);font-size:12px;margin-top:20px;">
        Don't have an account? <a href="/testmate/register.php" style="color:rgba(255,255,255,.7);">Register as a Learner</a>
    </p>

    <?php else: ?>
    <!-- ── LOGIN FORM ── -->
    <div class="login-box">
        <button class="back-btn" onclick="location.href='/testmate/login.php'">
            ← Back
        </button>

        <div class="mode-badge <?= $mode ?>">
            <?= $mode === 'admin' ? '🔐 Administrator Login' : '🎓 Learner Login' ?>
        </div>

        <?php if ($mode === 'admin'): ?>
        <div class="admin-note">
            ⚠️ Administrator access is restricted. Only authorised personnel may login here.
        </div>
        <?php endif; ?>

        <h2>Welcome Back</h2>
        <p class="sub">Enter your credentials to continue</p>

        <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Your password" required>
            </div>

            <button type="submit" class="submit-btn <?= $mode ?>">
                <?= $mode === 'admin' ? '🔐 Login as Administrator' : '🎓 Login as Learner' ?>
            </button>
        </form>

        <?php if ($mode === 'learner'): ?>
        <div class="auth-switch">
            No account yet? <a href="/testmate/register.php">Register free</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<div style="color:rgba(255,255,255,.3);font-size:12px;text-align:center;padding:16px;">
    © 2026 TestMate Namibia
</div>

</body>
</html>