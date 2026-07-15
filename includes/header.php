<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMate – Namibia Learner's Licence</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
</head>
<body>

<!-- Back/Forward Navigation Bar -->
<div style="background:#f0f4f8;border-bottom:1px solid #e0e0e0;padding:6px 20px;display:flex;align-items:center;gap:8px;">
    <button onclick="history.back()"
        style="background:white;border:1px solid #ccc;border-radius:6px;padding:5px 14px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:5px;color:#333;transition:all .2s;"
        onmouseover="this.style.background='#e8e8e8'" onmouseout="this.style.background='white'">
        ← Back
    </button>
    <button onclick="history.forward()"
        style="background:white;border:1px solid #ccc;border-radius:6px;padding:5px 14px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:5px;color:#333;transition:all .2s;"
        onmouseover="this.style.background='#e8e8e8'" onmouseout="this.style.background='white'">
        Forward →
    </button>
    <span style="font-size:12px;color:#999;margin-left:4px;">
        <?php
        $page = basename($_SERVER['PHP_SELF'], '.php');
        $page = str_replace(['-','_'], ' ', $page);
        echo ucwords($page);
        ?>
    </span>
</div>

<!-- Main Navbar -->
<nav class="navbar">
    <a href="/testmate/index.php" class="brand">
        <span class="brand-icon">T</span> TestMate
    </a>
    <div class="nav-links">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/testmate/study-materials.php">Study</a>
            <a href="/testmate/quiz.php">Quizzes</a>
            <a href="/testmate/mock-test.php">Mock Test</a>
            <a href="/testmate/progress.php">Progress</a>
            <a href="/testmate/logout.php" class="btn-logout">
                Logout (<?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>)
            </a>
        <?php else: ?>
            <a href="/testmate/study-materials.php">Browse</a>
            <a href="/testmate/login.php">Login</a>
            <a href="/testmate/register.php" class="btn-nav-primary">Register Free</a>
        <?php endif; ?>
    </div>
</nav>