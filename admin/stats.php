<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

// Topic stats
$topic_stats = $conn->query("
    SELECT t.name, t.icon,
           COUNT(qs.id) AS attempts,
           ROUND(AVG(qs.score / qs.total * 100)) AS avg_pct,
           SUM(CASE WHEN qs.score/qs.total >= 0.8 THEN 1 ELSE 0 END) AS passed
    FROM topics t
    LEFT JOIN quiz_scores qs ON t.id = qs.topic_id
    GROUP BY t.id
    ORDER BY avg_pct ASC
")->fetch_all(MYSQLI_ASSOC);

// Mock stats by day
$mock_by_day = $conn->query("
    SELECT DATE(taken_at) AS day, COUNT(*) AS total, SUM(passed) AS passed
    FROM mock_scores
    GROUP BY DATE(taken_at)
    ORDER BY day DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Overall mock stats
$mock_overall = $conn->query("SELECT COUNT(*) AS total, SUM(passed) AS passed, ROUND(AVG(score/total*100)) AS avg_pct FROM mock_scores")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Statistics – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;}
    </style>
</head>
<body>
<nav class="navbar">
    <a href="/testmate/admin/index.php" class="brand"><span class="brand-icon">T</span> TestMate Admin</a>
    <div class="nav-links">
        <a href="/testmate/index.php" style="color:rgba(255,255,255,.8);font-size:14px;">← View Site</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="admin-wrap">
    <div class="sidebar">
        <h3>Main</h3>
        <a href="/testmate/admin/index.php">📊 Dashboard</a>
        <a href="/testmate/admin/users.php">👥 Users</a>
        <a href="/testmate/admin/stats.php" class="active">📈 Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
    </div>
    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:24px;">📈 Statistics</h1>

        <!-- Mock Test Overview -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#2c3e50;"><?= $mock_overall['total'] ?></div>
                <div style="font-size:13px;color:#888;margin-top:4px;">Total Mock Tests</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= $mock_overall['passed'] ?></div>
                <div style="font-size:13px;color:#888;margin-top:4px;">Passed</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#e74c3c;"><?= ($mock_overall['total'] - $mock_overall['passed']) ?></div>
                <div style="font-size:13px;color:#888;margin-top:4px;">Failed</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#3498db;"><?= $mock_overall['avg_pct'] ?>%</div>
                <div style="font-size:13px;color:#888;margin-top:4px;">Average Score</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;">

            <!-- Topic Stats -->
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">📋 Quiz Performance by Topic</h2>
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
                        <div style="font-size:12px;color:#999;margin-top:4px;"><?= $ts['attempts'] ?> attempts · <?= $ts['passed'] ?> passed</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Mock Tests by Day -->
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">📅 Recent Mock Tests by Day</h2>
                <?php if (empty($mock_by_day)): ?>
                <div class="card" style="text-align:center;padding:40px;color:#888;">No mock tests yet</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Total</th><th>Passed</th><th>Pass Rate</th></tr></thead>
                        <tbody>
                        <?php foreach ($mock_by_day as $d):
                            $rate = $d['total'] > 0 ? round($d['passed']/$d['total']*100) : 0;
                        ?>
                        <tr>
                            <td style="font-size:13px;"><?= date('d M Y', strtotime($d['day'])) ?></td>
                            <td><?= $d['total'] ?></td>
                            <td style="color:#27ae60;font-weight:600;"><?= $d['passed'] ?></td>
                            <td>
                                <span class="badge <?= $rate >= 80 ? 'badge-pass' : 'badge-fail' ?>"><?= $rate ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>