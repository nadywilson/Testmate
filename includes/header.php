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
            <a href="/testmate/logout.php" class="btn-logout">Logout (<?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>)</a>
        <?php else: ?>
            <a href="/testmate/study-materials.php">Browse</a>
            <a href="/testmate/login.php">Login</a>
            <a href="/testmate/register.php" class="btn-nav-primary">Register Free</a>
        <?php endif; ?>
    </div>
</nav>