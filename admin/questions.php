<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $del = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $del->bind_param("i", $_GET['delete']);
    $del->execute();
    header("Location: /testmate/admin/questions.php?msg=deleted");
    exit();
}

$topic_filter = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;

if ($topic_filter > 0) {
    $q = $conn->prepare("SELECT q.*, t.name AS topic_name, t.icon FROM questions q JOIN topics t ON q.topic_id = t.id WHERE q.topic_id = ? ORDER BY q.id DESC");
    $q->bind_param("i", $topic_filter);
    $q->execute();
    $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $questions = $conn->query("SELECT q.*, t.name AS topic_name, t.icon FROM questions q JOIN topics t ON q.topic_id = t.id ORDER BY q.id DESC")->fetch_all(MYSQLI_ASSOC);
}

$topics = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Questions – TestMate Admin</title>
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
        <a href="/testmate/admin/questions.php" class="active">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
    </div>
    <div class="main-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <h1 style="font-size:22px;">❓ Questions (<?= count($questions) ?>)</h1>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <!-- Topic filter -->
                <select onchange="location='questions.php?topic='+this.value"
                    style="padding:8px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;">
                    <option value="0">All Topics</option>
                    <?php foreach ($topics as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $topic_filter == $t['id'] ? 'selected' : '' ?>>
                        <?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <a href="/testmate/admin/add-question.php" class="btn btn-primary" style="font-size:14px;padding:8px 16px;">➕ Add Question</a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
            <?= $_GET['msg'] === 'deleted' ? 'Question deleted.' : 'Question saved!' ?>
        </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>#</th><th>Topic</th><th>Question</th><th>Answer</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q): ?>
                <tr>
                    <td style="color:#888;font-size:13px;"><?= $q['id'] ?></td>
                    <td><span style="font-size:13px;"><?= $q['icon'] ?> <?= htmlspecialchars($q['topic_name']) ?></span></td>
                    <td style="font-size:14px;max-width:300px;"><?= htmlspecialchars(substr($q['question'], 0, 80)) ?>...</td>
                    <td><span class="badge" style="background:#eaf4ff;color:#2471a3;font-size:13px;"><?= $q['correct_answer'] ?></span></td>
                    <td>
                        <a href="/testmate/admin/add-question.php?edit=<?= $q['id'] ?>" style="color:#3498db;font-size:13px;text-decoration:none;margin-right:10px;">Edit</a>
                        <a href="/testmate/admin/questions.php?delete=<?= $q['id'] ?>"
                           onclick="return confirm('Delete this question?')"
                           style="color:#e74c3c;font-size:13px;text-decoration:none;">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>