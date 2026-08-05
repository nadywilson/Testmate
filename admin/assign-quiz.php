<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

$msg   = '';
$error = '';

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user = (int)$_POST['user_id'];
    $question_ids = $_POST['question_ids'] ?? [];
    $admin_id     = $_SESSION['user_id'];

    if (empty($question_ids)) {
        $error = "Please select at least one question.";
    } elseif (!$target_user) {
        $error = "Please select a user.";
    } else {
        $count = 0;
        foreach ($question_ids as $qid) {
            $qid = (int)$qid;
            // Check not already assigned and incomplete
            $check = $conn->prepare("SELECT id FROM assigned_quizzes WHERE user_id=? AND question_id=? AND is_completed=0");
            $check->bind_param("ii", $target_user, $qid);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO assigned_quizzes (user_id, admin_id, question_id) VALUES (?,?,?)");
                $ins->bind_param("iii", $target_user, $admin_id, $qid);
                $ins->execute();
                $count++;
            }
        }
        $msg = "$count question(s) assigned successfully!";
    }
}

// Get all learners
$users = $conn->query("SELECT id, name, email FROM users WHERE role='user' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get all topics and questions
$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$topic_filter = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;

if ($topic_filter > 0) {
    $q = $conn->prepare("SELECT q.*, t.name AS topic_name FROM questions q JOIN topics t ON q.topic_id=t.id WHERE q.topic_id=? ORDER BY q.id");
    $q->bind_param("i", $topic_filter);
    $q->execute();
    $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $questions = $conn->query("SELECT q.*, t.name AS topic_name FROM questions q JOIN topics t ON q.topic_id=t.id ORDER BY t.id, q.id")->fetch_all(MYSQLI_ASSOC);
}

// Get assignment history
$history = $conn->query("
    SELECT aq.*, u.name AS user_name, q.question AS question_text
    FROM assigned_quizzes aq
    JOIN users u ON aq.user_id = u.id
    JOIN questions q ON aq.question_id = q.id
    ORDER BY aq.assigned_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Quiz – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;overflow-y:auto;}
        select{width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;outline:none;}
        .q-checkbox{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border:1.5px solid #eee;border-radius:8px;margin-bottom:8px;cursor:pointer;transition:border-color .15s;}
        .q-checkbox:hover{border-color:#3498db;background:#f0f8ff;}
        .q-checkbox input{margin-top:3px;flex-shrink:0;accent-color:#3498db;width:16px;height:16px;}
        .q-checkbox label{font-size:14px;cursor:pointer;color:#333;}
        .q-topic-tag{font-size:11px;background:#eaf4ff;color:#2471a3;padding:2px 8px;border-radius:10px;display:inline-block;margin-bottom:4px;}
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
        <a href="/testmate/admin/index.php">Dashboard</a>
        <a href="/testmate/admin/users.php">Users</a>
        <a href="/testmate/admin/stats.php">Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">All Questions</a>
        <a href="/testmate/admin/add-question.php">Add Question</a>
        <a href="/testmate/admin/assign-quiz.php" class="active">Assign Quiz</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">Materials</a>
        <a href="/testmate/admin/add-material.php">Add Material</a>
    </div>

    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:20px;">Assign Questions to a User</h1>

        <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

            <!-- Assignment Form -->
            <div class="card">
                <h2 style="font-size:17px;margin-bottom:16px;">New Assignment</h2>

                <form method="POST">
                    <div class="form-group">
                        <label>Select User</label>
                        <select name="user_id" required>
                            <option value="">— Choose a learner —</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top:16px;">
                        <label>Filter by Topic</label>
                        <select onchange="location='assign-quiz.php?topic='+this.value">
                            <option value="0">All Topics</option>
                            <?php foreach ($topics as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $topic_filter == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-top:16px;margin-bottom:8px;">
                        <label style="font-size:13px;font-weight:600;">Select Questions</label>
                        <div style="font-size:12px;color:#888;margin-bottom:8px;">
                            <button type="button" onclick="selectAll(true)" style="background:none;border:none;color:#3498db;cursor:pointer;font-size:12px;padding:0;margin-right:10px;">Select All</button>
                            <button type="button" onclick="selectAll(false)" style="background:none;border:none;color:#888;cursor:pointer;font-size:12px;padding:0;">Clear All</button>
                        </div>
                    </div>

                    <div style="max-height:400px;overflow-y:auto;border:1px solid #eee;border-radius:8px;padding:10px;">
                        <?php foreach ($questions as $q): ?>
                        <div class="q-checkbox">
                            <input type="checkbox" name="question_ids[]" value="<?= $q['id'] ?>" id="q<?= $q['id'] ?>">
                            <label for="q<?= $q['id'] ?>">
                                <span class="q-topic-tag"><?= htmlspecialchars($q['topic_name']) ?></span><br>
                                <?= htmlspecialchars(substr($q['question'], 0, 100)) ?>...
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px;">
                        Assign Selected Questions
                    </button>
                </form>
            </div>

            <!-- Assignment History -->
            <div>
                <h2 style="font-size:17px;margin-bottom:16px;">Recent Assignments</h2>
                <?php if (empty($history)): ?>
                <div class="card" style="text-align:center;padding:30px;color:#888;">No assignments yet.</div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($history as $h): ?>
                    <div class="card" style="padding:14px 16px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:6px;">
                            <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($h['user_name']) ?></div>
                            <span class="badge <?= $h['is_completed'] ? 'badge-pass' : 'badge-medium' ?>">
                                <?= $h['is_completed'] ? 'Done' : 'Pending' ?>
                            </span>
                        </div>
                        <div style="font-size:13px;color:#666;margin-bottom:4px;">
                            <?= htmlspecialchars(substr($h['question_text'], 0, 80)) ?>...
                        </div>
                        <div style="font-size:12px;color:#aaa;"><?= date('d M Y H:i', strtotime($h['assigned_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function selectAll(state) {
    document.querySelectorAll('input[name="question_ids[]"]').forEach(cb => cb.checked = state);
}
</script>
</body>
</html>