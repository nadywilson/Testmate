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

<!-- Back Button - Bottom Left -->
<button onclick="history.back()"
    title="Go Back"
    style="position:fixed;bottom:24px;left:24px;z-index:999;
           width:44px;height:44px;border-radius:50%;
           background:#2c3e50;color:white;border:none;
           font-size:20px;cursor:pointer;
           box-shadow:0 4px 12px rgba(0,0,0,0.2);
           display:flex;align-items:center;justify-content:center;
           transition:all 0.2s;"
    onmouseover="this.style.background='#3498db';this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#2c3e50';this.style.transform='scale(1)'">
    &#8592;
</button>

<!-- Forward Button - Top Right -->
<button onclick="history.forward()"
    title="Go Forward"
    style="position:fixed;top:80px;right:24px;z-index:999;
           width:44px;height:44px;border-radius:50%;
           background:#2c3e50;color:white;border:none;
           font-size:20px;cursor:pointer;
           box-shadow:0 4px 12px rgba(0,0,0,0.2);
           display:flex;align-items:center;justify-content:center;
           transition:all 0.2s;"
    onmouseover="this.style.background='#3498db';this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#2c3e50';this.style.transform='scale(1)'">
    &#8594;
</button>

<!-- Main Navbar -->
<nav class="navbar">
    <a href="/testmate/index.php" class="brand">
        <span class="brand-icon">T</span> TestMate
    </a>
    <<div class="nav-links">
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/testmate/study-materials.php">Study</a>
        <a href="/testmate/simulations.php">Simulations</a>
        <a href="/testmate/quiz.php">Quizzes</a>
        <a href="/testmate/mock-test.php">Mock Test</a>
        <a href="/testmate/progress.php">Progress</a>
        <a href="/testmate/rankings.php">Rankings</a>
        <a href="/testmate/profile.php">Profile</a>
        <a href="/testmate/logout.php" class="btn-logout">
            Logout (<?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>)
        </a>
    <?php else: ?>
        <a href="/testmate/study-materials.php">Browse</a>
        <a href="/testmate/login.php">Login</a>
        <a href="/testmate/register.php" class="btn-nav-primary">Register Free</a>
    <?php endif; ?>
</div>
    </div>
</nav>