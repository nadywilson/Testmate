<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php");
    exit();
}

$name = $_SESSION['name'];

// Stats
$total_users    = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];
$total_q        = $conn->query("SELECT COUNT(*) AS c FROM questions")->fetch_assoc()['c'];
$total_mocks    = $conn->query("SELECT COUNT(*) AS c FROM mock_scores")->fetch_assoc()['c'];
$total_passed   = $conn->query("SELECT COUNT(*) AS c FROM mock_scores WHERE passed=1")->fetch_assoc()['c'];
$total_quizzes  = $conn->query("SELECT COUNT(*) AS c FROM quiz_scores")->fetch_assoc()['c'];

// Recent users
$recent_users = $conn->query("SELECT name, email, created_at FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Topic performance
$topic_stats = $conn->query("
    SELECT t.name, t.icon,
           COUNT(qs.id) AS attempts,
           ROUND(AVG(qs.score / qs.total * 100)) AS avg_pct
    FROM topics t
    LEFT JOIN quiz_scores qs ON t.id = qs.topic_id
    GROUP BY t.id
    ORDER BY avg_pct ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – TestMate</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap { display: flex; min-height: calc(100vh - 60px); }
        .sidebar {
            width: 230px; background: #1a252f; color: white;
            padding: 24px 0; flex-shrink: 0; position: sticky;
            top: 60px; height: calc(100vh - 60px); overflow-y: auto;
        }
        .sidebar h3 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.4); padding: 0 20px; margin-bottom: 8px; margin-top: 20px; }
        .sidebar h3:first-child { margin-top: 0; }
        .sidebar a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px; color: rgba(255,255,255,.8);
            text-decoration: none; font-size: 14px; transition: all .15s;
        }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.1); color: white; }
        .main-content { flex: 1; padding: 30px; background: #f5f6fa; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: white; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .stat-card .num { font-size: 2.2rem; font-weight: 800; display: block; line-height: 1.1; }
        .stat-card .lbl { font-size: 13px; color: #888; margin-top: 4px; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <a href="/testmate/admin/index.php" class="brand">
        <span class="brand-icon">T</span> TestMate Admin
    </a>
    <div class="nav-links">
        <a href="/testmate/index.php" style="color:rgba(255,255,255,.8);font-size:14px;">← View Site</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout (<?= htmlspecialchars($name) ?>)</a>
    </div>
</nav>

<div class="admin-wrap">

    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Main</h3>
        <a href="/testmate/admin/index.php" class="active">📊 Dashboard</a>
        <a href="/testmate/admin/users.php">👥 Users</a>
        <a href="/testmate/admin/stats.php">📈 Statistics</a>

        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>

        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1 style="font-size:24px;margin-bottom:4px;">Welcome, <?= htmlspecialchars($name) ?>!</h1>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Here's an overview of TestMate</p>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-card">
                <span class="num" style="color:#3498db;"><?= $total_users ?></span>
                <span class="lbl">Registered Users</span>
            </div>
            <div class="stat-card">
                <span class="num" style="color:#2c3e50;"><?= $total_q ?></span>
                <span class="lbl">Questions</span>
            </div>
            <div class="stat-card">
                <span class="num" style="color:#e67e22;"><?= $total_quizzes ?></span>
                <span class="lbl">Quizzes Taken</span>
            </div>
            <div class="stat-card">
                <span class="num" style="color:#2c3e50;"><?= $total_mocks ?></span>
                <span class="lbl">Mock Tests Taken</span>
            </div>
            <div class="stat-card">
                <span class="num" style="color:#27ae60;"><?= $total_passed ?></span>
                <span class="lbl">Mock Tests Passed</span>
            </div>
            <div class="stat-card">
                <span class="num" style="color:#e74c3c;"><?= $total_mocks > 0 ? round(($total_passed/$total_mocks)*100) : 0 ?>%</span>
                <span class="lbl">Pass Rate</span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;">

            <!-- Recent Users -->
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">👥 Recent Users</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php if (empty($recent_users)): ?>
                            <tr><td colspan="3" style="text-align:center;color:#888;">No users yet</td></tr>
                        <?php else: foreach ($recent_users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td style="font-size:13px;color:#888;"><?= htmlspecialchars($u['email']) ?></td>
                                <td style="font-size:13px;color:#888;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <a href="/testmate/admin/users.php" style="font-size:13px;color:#3498db;text-decoration:none;display:block;margin-top:8px;">View all users →</a>
            </div>

            <!-- Topic Performance -->
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">📋 Topic Performance</h2>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($topic_stats as $ts):
                        $pct = $ts['avg_pct'] ?? 0;
                        $cl  = $pct >= 80 ? '#27ae60' : ($pct >= 60 ? '#e67e22' : '#e74c3c');
                    ?>
                    <div class="card" style="padding:14px 16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-size:14px;font-weight:600;"><?= $ts['icon'] ?> <?= htmlspecialchars($ts['name']) ?></span>
                            <span style="font-size:14px;font-weight:700;color:<?= $cl ?>"><?= $pct ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $cl ?>;"></div>
                        </div>
                        <div style="font-size:12px;color:#999;margin-top:4px;"><?= $ts['attempts'] ?> attempts</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>