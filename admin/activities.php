<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM activities WHERE id=" . (int)$_GET['delete']);
    header("Location: /testmate/admin/activities.php?deleted=1");
    exit();
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $conn->query("UPDATE activities SET is_active = NOT is_active WHERE id=" . (int)$_GET['toggle']);
    header("Location: /testmate/admin/activities.php");
    exit();
}

$activities = $conn->query("
    SELECT a.*, t.name AS topic_name,
           COUNT(ar.id) AS completions,
           ROUND(AVG(ar.score / ar.max_score * 100)) AS avg_score
    FROM activities a
    LEFT JOIN topics t ON a.topic_id = t.id
    LEFT JOIN activity_results ar ON a.id = ar.activity_id AND ar.completed = 1
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activities – TestMate Admin</title>
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
        <a href="/testmate/admin/activities.php" class="active">Activities</a>
    </div>
    <div class="main-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h1 style="font-size:22px;">Interactive Activities (<?= count($activities) ?>)</h1>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">Activity deleted.</div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Title</th><th>Topic</th><th>Type</th><th>Completions</th><th>Avg Score</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($activities as $a): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($a['title']) ?></td>
                    <td style="font-size:13px;color:#888;"><?= htmlspecialchars($a['topic_name'] ?? 'All') ?></td>
                    <td>
                        <span class="badge" style="background:<?= $a['type'] === 'h5p' ? '#eaf4ff' : ($a['type'] === 'leaflet' ? '#eafaf1' : '#f3e8ff') ?>;color:<?= $a['type'] === 'h5p' ? '#2471a3' : ($a['type'] === 'leaflet' ? '#27ae60' : '#8e44ad') ?>;">
                            <?= strtoupper($a['type']) ?>
                        </span>
                    </td>
                    <td><?= $a['completions'] ?></td>
                    <td><?= $a['avg_score'] ? $a['avg_score'] . '%' : '—' ?></td>
                    <td>
                        <span class="badge <?= $a['is_active'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= $a['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="/testmate/activity-view.php?id=<?= $a['id'] ?>" target="_blank"
                           style="color:#3498db;font-size:13px;text-decoration:none;margin-right:10px;">Preview</a>
                        <a href="/testmate/admin/activities.php?toggle=<?= $a['id'] ?>"
                           style="color:#e67e22;font-size:13px;text-decoration:none;margin-right:10px;">
                            <?= $a['is_active'] ? 'Hide' : 'Show' ?>
                        </a>
                        <a href="/testmate/admin/activities.php?delete=<?= $a['id'] ?>"
                           onclick="return confirm('Delete this activity?')"
                           style="color:#e74c3c;font-size:13px;text-decoration:none;">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:24px;background:#f0f4f8;border-radius:10px;padding:20px;">
            <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;color:#2c3e50;">About Activities</h3>
            <p style="font-size:14px;color:#666;line-height:1.6;">
                Activities are interactive learning modules. Currently TestMate supports three types:
                <strong>H5P</strong> (drag-and-drop, image quizzes),
                <strong>Leaflet</strong> (map-based traffic scenarios using real Namibian roads), and
                <strong>Three.js</strong> (3D hazard perception animations).
                New activities are added via the database. Contact your developer to add custom activities.
            </p>
        </div>
    </div>
</div>
</body>
</html>