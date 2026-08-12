<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMate – Namibia Learner's Licence</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        /* ── Left sidebar (Study / Simulations / Quizzes / Mock Test) ── */
        .tm-sidebar {
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            width: 210px;
            background: #1a252f;
            padding: 20px 0;
            z-index: 90;
            overflow-y: auto;
        }
        .tm-sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            color: rgba(255,255,255,.8);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all .15s;
            border-left: 3px solid transparent;
        }
        .tm-sidebar a:hover {
            background: rgba(255,255,255,.08);
            color: white;
        }
        .tm-sidebar a.active {
            background: rgba(52,152,219,.15);
            color: white;
            border-left-color: #3498db;
        }
        .tm-sidebar .tm-icon { font-size: 18px; width: 22px; text-align: center; flex-shrink: 0; }

        /* Push page content right so it doesn't sit under the sidebar */
        body.has-sidebar { padding-left: 210px; }

        /* Mobile: collapse sidebar into a horizontal scrollable row under the navbar */
        @media (max-width: 820px) {
            body.has-sidebar { padding-left: 0; padding-top: 54px; }
            .tm-sidebar {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                bottom: auto;
                width: auto;
                height: 54px;
                display: flex;
                flex-direction: row;
                align-items: stretch;
                padding: 0;
                overflow-x: auto;
                overflow-y: hidden;
                border-bottom: 1px solid rgba(255,255,255,.08);
            }
            .tm-sidebar a {
                flex: 0 0 auto;
                flex-direction: column;
                justify-content: center;
                gap: 2px;
                padding: 6px 16px;
                font-size: 11px;
                border-left: none;
                border-bottom: 3px solid transparent;
                white-space: nowrap;
            }
            .tm-sidebar a.active { border-left-color: transparent; border-bottom-color: #3498db; }
            .tm-icon { font-size: 16px; }
        }

        /* Shift the floating back/home buttons so they don't sit under the sidebar */
        body.has-sidebar .tm-back-btn { left: 234px; }
        body.has-sidebar .tm-home-btn { left: 294px; }
        @media (max-width: 820px) {
            body.has-sidebar .tm-back-btn { left: 24px; }
            body.has-sidebar .tm-home-btn { left: 84px; }
        }
    </style>
</head>
<body<?= $is_logged_in ? ' class="has-sidebar"' : '' ?>>

<!-- Back Button - Bottom Left -->
<button onclick="history.back()"
    title="Go Back"
    class="tm-back-btn"
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

<!-- Home Button - Bottom Left, next to Back -->
<a href="/testmate/index.php"
    title="Go to Homepage"
    class="tm-home-btn"
    style="position:fixed;bottom:24px;left:84px;z-index:999;
           width:44px;height:44px;border-radius:50%;
           background:#2c3e50;color:white;border:none;
           font-size:20px;cursor:pointer;
           box-shadow:0 4px 12px rgba(0,0,0,0.2);
           display:flex;align-items:center;justify-content:center;
           transition:all 0.2s;text-decoration:none;"
    onmouseover="this.style.background='#3498db';this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#2c3e50';this.style.transform='scale(1)'">
    &#8962;
</a>

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
    <div class="nav-links">
    <?php if ($is_logged_in): ?>
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
</nav>

<?php if ($is_logged_in):
    $current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Left Sidebar -->
<div class="tm-sidebar">
        <a href="/testmate/quiz.php" class="<?= $current_page === 'quiz.php' ? 'active' : '' ?>">
        <span class="tm-icon">✅</span> Quizzes
    </a>
    <a href="/testmate/quiz-history.php" class="<?= $current_page === 'quiz-history.php' ? 'active' : '' ?>">
        <span class="tm-icon">📜</span> History
    </a>
    <a href="/testmate/mock-test.php" class="<?= $current_page === 'mock-test.php' ? 'active' : '' ?>">
        <span class="tm-icon">📝</span> Mock Test
    </a>
    <a href="/testmate/study-materials.php" class="<?= $current_page === 'study-materials.php' ? 'active' : '' ?>">
        <span class="tm-icon">📚</span> Study
    </a>
    <a href="/testmate/simulations.php" class="<?= $current_page === 'simulations.php' ? 'active' : '' ?>">
        <span class="tm-icon">🎬</span> Simulations
    </a>
    <a href="/testmate/quiz.php" class="<?= $current_page === 'quiz.php' ? 'active' : '' ?>">
        <span class="tm-icon">✅</span> Quizzes
    </a>
    <a href="/testmate/mock-test.php" class="<?= $current_page === 'mock-test.php' ? 'active' : '' ?>">
        <span class="tm-icon">📝</span> Mock Test
    </a>
</div>
<?php endif; ?>