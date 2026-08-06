<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

if (isset($_GET['toggle'])) {
    $conn->query("UPDATE simulations SET is_active = NOT is_active WHERE id=" . (int)$_GET['toggle']);
    header("Location: /testmate/admin/simulations.php");
    exit();
}

$simulations = $conn->query("
    SELECT s.*, t.name AS topic_name,
           COUNT(DISTINCT sq.id) AS q_count,
           COUNT(DISTINCT sr.id) AS completions,
           ROUND(AVG(sr.score/sr.total*100)) AS avg_score
    FROM simulations s
    LEFT JOIN topics t ON s.topic_id = t.id
    LEFT JOIN simulation_questions sq ON s.id = sq.simulation_id
    LEFT JOIN simulation_results sr ON s.id = sr.simulation_id AND sr.completed = 1
    GROUP BY s.id
    ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simulations – TestMate Admin</title>
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
        <a href="/testmate/index.php" style="color:rgba(255,255,255,.8);font-size:14px;">View Site</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="admin-wrap">
    <div class="sidebar">
        <h3>Main</h3>
        <a href="/testmate/admin/index.php">Dashboard</a>
        <a href="/testmate/admin/users.php">Users</a>
        <a href="/testmate/admin/stats.php">Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">All Questions</a>
        <a href="/testmate/admin/add-question.php">Add Question</a>
        <a href="/testmate/admin/assign-quiz.php">Assign Quiz</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">Materials</a>
        <a href="/testmate/admin/add-material.php">Add Material</a>
        <a href="/testmate/admin/activities.php">Activities</a>
        <a href="/testmate/admin/simulations.php" class="active">Simulations</a>
    </div>
    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:20px;">Video Simulations (<?= count($simulations) ?>)</h1>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Title</th><th>Topic</th><th>Type</th><th>Questions</th><th>Completions</th><th>Avg Score</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($simulations as $s): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['title']) ?></td>
                    <td style="font-size:13px;color:#888;"><?= htmlspecialchars($s['topic_name'] ?? 'General') ?></td>
                    <td><span class="badge" style="background:#eaf4ff;color:#2471a3;"><?= htmlspecialchars($s['animation_type']) ?></span></td>
                    <td><?= $s['q_count'] ?></td>
                    <td><?= $s['completions'] ?></td>
                    <td><?= $s['avg_score'] ? $s['avg_score'].'%' : '—' ?></td>
                    <td>
                        <span class="badge <?= $s['is_active']?'badge-pass':'badge-fail' ?>">
                            <?= $s['is_active']?'Active':'Hidden' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="/testmate/simulations.php?id=<?= $s['id'] ?>&mode=study" target="_blank"
                           style="color:#3498db;font-size:13px;text-decoration:none;margin-right:10px;">Preview</a>
                        <a href="/testmate/admin/simulations.php?toggle=<?= $s['id'] ?>"
                           style="color:#e67e22;font-size:13px;text-decoration:none;">
                            <?= $s['is_active']?'Hide':'Show' ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:24px;background:#f0f4f8;border-radius:10px;padding:20px;">
            <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;">How Simulations Work</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:14px;color:#666;line-height:1.7;">
                <div>
                    <strong style="color:#2471a3;">Study Mode</strong><br>
                    The animation plays and automatically shows the learner whether the action was correct or incorrect with a tick or cross. This teaches the learner by demonstrating correct behavior.
                </div>
                <div>
                    <strong style="color:#c0392b;">Quiz Mode</strong><br>
                    The same animation plays but does NOT reveal the answer. The learner must decide if the action was correct or incorrect. Only after submitting all answers is feedback shown.
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>