<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

$topics = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$q_counts = [];
$m_counts = [];
foreach ($topics as $t) {
    $c = $conn->prepare("SELECT COUNT(*) AS cnt FROM questions WHERE topic_id = ?");
    $c->bind_param("i", $t['id']);
    $c->execute();
    $q_counts[$t['id']] = $c->get_result()->fetch_assoc()['cnt'];

    $mc = $conn->prepare("SELECT COUNT(*) AS cnt FROM materials WHERE topic_id = ?");
    $mc->bind_param("i", $t['id']);
    $mc->execute();
    $m_counts[$t['id']] = $mc->get_result()->fetch_assoc()['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Materials – TestMate Admin</title>
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
        <a href="/testmate/admin/stats.php">📈 Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php" class="active">📚 Materials</a>
        <a href="/testmate/admin/add-material.php">➕ Add Material</a>
    </div>
    <div class="main-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h1 style="font-size:22px;">📚 Study Materials</h1>
            <a href="/testmate/admin/add-material.php" class="btn btn-primary">➕ Add Material</a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;">
            <?php foreach ($topics as $t): ?>
            <div class="card" style="padding:22px;">
                <div style="font-size:2rem;margin-bottom:10px;"><?= $t['icon'] ?></div>
                <h3 style="font-size:16px;margin-bottom:6px;"><?= htmlspecialchars($t['name']) ?></h3>
                <p style="color:#888;font-size:13px;margin-bottom:14px;"><?= htmlspecialchars($t['description']) ?></p>

                <div style="display:flex;gap:16px;margin-bottom:16px;">
                    <div style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:800;color:#3498db;"><?= $q_counts[$t['id']] ?></div>
                        <div style="font-size:12px;color:#888;">Questions</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.5rem;font-weight:800;color:#27ae60;"><?= $m_counts[$t['id']] ?></div>
                        <div style="font-size:12px;color:#888;">Materials</div>
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="/testmate/study-materials.php?topic=<?= $t['id'] ?>"
                       class="btn btn-outline" style="font-size:13px;padding:6px 12px;">👁️ View</a>
                    <a href="/testmate/admin/add-material.php?topic=<?= $t['id'] ?>"
                       class="btn btn-primary" style="font-size:13px;padding:6px 12px;">➕ Add</a>
                    <a href="/testmate/admin/questions.php?topic=<?= $t['id'] ?>"
                       class="btn btn-outline" style="font-size:13px;padding:6px 12px;">❓ Questions</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>