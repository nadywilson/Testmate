<?php
require 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /testmate/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Learner';

// Fetch stats
$quiz_count = $conn->query("SELECT COUNT(*) FROM quiz_scores WHERE user_id = $user_id")->fetch_row()[0];
$mock_count = $conn->query("SELECT COUNT(*) FROM mock_scores WHERE user_id = $user_id")->fetch_row()[0];
$mock_passed = $conn->query("SELECT COUNT(*) FROM mock_scores WHERE user_id = $user_id AND passed = 1")->fetch_row()[0];

// Day streak (simplified - count consecutive days with activity)
$streak = 3; // You can calculate this from quiz_scores/mock_scores dates

// Topic performance
$topics = $conn->query("
    SELECT t.name, 
           COUNT(qs.id) as attempts,
           AVG(qs.score/qs.total*100) as avg_score
    FROM topics t
    LEFT JOIN quiz_scores qs ON t.id = qs.topic_id AND qs.user_id = $user_id
    GROUP BY t.id
    HAVING attempts > 0
    ORDER BY avg_score DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - TestMate</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }

        /* Top Navbar */
        .topbar {
            background: #2c3e50;
            color: white;
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background .2s;
        }
        .menu-btn:hover { background: rgba(255,255,255,.15); }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; font-weight: 700; font-size: 18px; }
        .brand-icon { width: 34px; height: 34px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .topbar-right a { color: rgba(255,255,255,.85); text-decoration: none; font-size: 14px; transition: color .2s; }
        .topbar-right a:hover { color: white; }
        .btn-logout { background: #e74c3c; color: white; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; }

        /* Sidebar - Hidden by default */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,.4);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all .3s;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }

        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 260px;
            height: 100vh;
            background: #1a252f;
            color: white;
            padding: 80px 0 24px;
            z-index: 999;
            transform: translateX(-100%);
            transition: transform .3s ease;
            overflow-y: auto;
        }
        .sidebar.active { transform: translateX(0); }
        .sidebar h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255,255,255,.35);
            padding: 0 24px;
            margin: 20px 0 10px;
        }
        .sidebar h3:first-child { margin-top: 0; }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 15px;
            transition: all .15s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,.08);
            color: white;
        }
        .sidebar-icon { font-size: 20px; width: 24px; text-align: center; }

        /* Main Content */
        .main-content {
            margin-top: 60px;
            padding: 30px;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-banner h1 { font-size: 26px; margin-bottom: 8px; }
        .welcome-banner p { color: rgba(255,255,255,.7); font-size: 15px; }
        .welcome-arrow { font-size: 28px; color: rgba(255,255,255,.4); }

        /* Streak Banner */
        .streak-banner {
            background: #e8f5e9;
            border-left: 4px solid #27ae60;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .streak-banner strong { color: #27ae60; font-size: 16px; }
        .streak-banner p { color: #555; font-size: 14px; margin-top: 4px; }

        /* Study Card */
        .study-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .progress-circle {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #e74c3c;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        .progress-circle .pct { font-size: 28px; font-weight: 800; }
        .progress-circle .label { font-size: 12px; opacity: .9; }
        .study-info h2 { font-size: 20px; margin-bottom: 8px; color: #2c3e50; }
        .study-info p { color: #666; font-size: 14px; margin-bottom: 16px; }
        .study-buttons { display: flex; gap: 12px; }
        .btn-dark { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; border: none; cursor: pointer; }
        .btn-light { background: white; color: #2c3e50; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; border: 1px solid #2c3e50; }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .stat-box .num { font-size: 36px; font-weight: 800; margin-bottom: 6px; }
        .stat-box .label { color: #888; font-size: 14px; }
        .stat-box:nth-child(1) .num { color: #3498db; }
        .stat-box:nth-child(2) .num { color: #27ae60; }
        .stat-box:nth-child(3) .num { color: #e74c3c; }
        .stat-box:nth-child(4) .num { color: #f39c12; }

        /* Topic Performance */
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #2c3e50; }
        .topic-list { display: flex; flex-direction: column; gap: 12px; }
        .topic-item {
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .topic-name { font-weight: 600; color: #2c3e50; }
        .topic-score { font-size: 14px; color: #666; }
        .topic-bar {
            width: 200px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .topic-bar-fill {
            height: 100%;
            background: #3498db;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<!-- Top Navbar -->
<div class="topbar">
    <div class="topbar-left">
        <button class="menu-btn" onclick="toggleSidebar()">&#9776;</button>
        <a href="/testmate/dashboard.php" class="brand">
            <div class="brand-icon">T</div>
            TestMate
        </a>
    </div>
    <div class="topbar-right">
        <a href="/testmate/progress.php">Progress</a>
        <a href="#" onclick="toggleSidebar(); return false;">Rankings</a>
        <a href="/testmate/profile.php">Profile</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($user_name); ?>)</a>
    </div>
</div>

<!-- Sidebar Overlay (click to close) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Slide-out Sidebar -->
<div class="sidebar" id="sidebar">
    <h3>Menu</h3>
    <a href="/testmate/quiz.php"><span class="sidebar-icon">&#128216;</span> Quizzes</a>
    <a href="/testmate/history.php"><span class="sidebar-icon">&#128203;</span> History</a>
    <a href="/testmate/mock-test.php"><span class="sidebar-icon">&#128221;</span> Mock Test</a>
    <a href="/testmate/study-materials.php"><span class="sidebar-icon">&#128218;</span> Study Materials</a>
    <a href="/testmate/simulations.php"><span class="sidebar-icon">&#127916;</span> Simulations</a>

    <h3>Account</h3>
    <a href="/testmate/progress.php"><span class="sidebar-icon">&#128200;</span> Progress</a>
    <a href="/testmate/rankings.php"><span class="sidebar-icon">&#127942;</span> Rankings</a>
    <a href="/testmate/profile.php"><span class="sidebar-icon">&#128100;</span> Profile</a>
    <a href="/testmate/logout.php"><span class="sidebar-icon">&#128682;</span> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here is your learning progress at a glance</p>
        </div>
        <div class="welcome-arrow">&#8594;</div>
    </div>

    <!-- Streak -->
    <div class="streak-banner">
        <strong>Day Streak: <?php echo $streak; ?> days in a row!</strong>
        <p>Great consistency! Keep practising every day.</p>
    </div>

    <!-- Study Card -->
    <div class="study-card">
        <div class="progress-circle">
            <div class="pct">21%</div>
            <div class="label">Ready</div>
        </div>
        <div class="study-info">
            <h2>Keep Studying!</h2>
            <p>Keep going — study the materials and take more quizzes.</p>
            <div class="study-buttons">
                <a href="/testmate/mock-test.php" class="btn-dark">Take Mock Test</a>
                <a href="/testmate/study-materials.php" class="btn-light">Study Materials</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="num"><?php echo $quiz_count; ?></div>
            <div class="label">Quizzes Taken</div>
        </div>
        <div class="stat-box">
            <div class="num"><?php echo $mock_count; ?></div>
            <div class="label">Mock Tests Taken</div>
        </div>
        <div class="stat-box">
            <div class="num"><?php echo $mock_passed; ?></div>
            <div class="label">Mock Tests Passed</div>
        </div>
        <div class="stat-box">
            <div class="num"><?php echo $streak; ?></div>
            <div class="label">Day Streak</div>
        </div>
    </div>

    <!-- Topic Performance -->
    <h2 class="section-title">Topic Performance</h2>
    <div class="topic-list">
        <?php if (empty($topics)): ?>
        <div class="topic-item" style="color:#888;">No quiz data yet. Start taking quizzes to see your performance!</div>
        <?php else: ?>
            <?php foreach ($topics as $t): 
                $score = round($t['avg_score'] ?? 0);
                $color = $score >= 80 ? '#27ae60' : ($score >= 50 ? '#f39c12' : '#e74c3c');
            ?>
            <div class="topic-item">
                <div class="topic-name"><?php echo htmlspecialchars($t['name']); ?></div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="topic-score"><?php echo $score; ?>% avg</div>
                    <div class="topic-bar">
                        <div class="topic-bar-fill" style="width:<?php echo $score; ?>%;background:<?php echo $color; ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

// Close sidebar when pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }
});
</script>

</body>
</html>