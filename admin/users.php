<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

// Handle delete user
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Delete related records first
    $conn->query("DELETE FROM quiz_scores WHERE user_id = $del_id");
    $conn->query("DELETE FROM mock_scores WHERE user_id = $del_id");
    $conn->query("DELETE FROM failed_questions WHERE user_id = $del_id");
    $conn->query("DELETE FROM chat_history WHERE user_id = $del_id");
    $conn->query("DELETE FROM users WHERE id = $del_id AND role = 'user'");
    header("Location: /testmate/admin/users.php?deleted=1");
    exit();
}

// View user activity
$view_user = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$user_detail = null;
$user_quizzes = [];
$user_mocks = [];
$user_failed = [];

if ($view_user > 0) {
    $u = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $u->bind_param("i", $view_user);
    $u->execute();
    $user_detail = $u->get_result()->fetch_assoc();

    // Quiz history
    $uq = $conn->prepare("
        SELECT qs.*, t.name AS topic_name, t.icon
        FROM quiz_scores qs
        JOIN topics t ON qs.topic_id = t.id
        WHERE qs.user_id = ?
        ORDER BY qs.taken_at DESC
    ");
    $uq->bind_param("i", $view_user);
    $uq->execute();
    $user_quizzes = $uq->get_result()->fetch_all(MYSQLI_ASSOC);

    // Mock test history
    $um = $conn->prepare("SELECT * FROM mock_scores WHERE user_id = ? ORDER BY taken_at DESC");
    $um->bind_param("i", $view_user);
    $um->execute();
    $user_mocks = $um->get_result()->fetch_all(MYSQLI_ASSOC);

    // Failed questions
    $uf = $conn->prepare("
        SELECT fq.*, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, t.name AS topic_name, t.icon
        FROM failed_questions fq
        JOIN questions q ON fq.question_id = q.id
        JOIN topics t ON fq.topic_id = t.id
        WHERE fq.user_id = ?
        ORDER BY fq.times_failed DESC
    ");
    $uf->bind_param("i", $view_user);
    $uf->execute();
    $user_failed = $uf->get_result()->fetch_all(MYSQLI_ASSOC);
}

$users = $conn->query("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           COUNT(DISTINCT qs.id) AS quizzes,
           COUNT(DISTINCT ms.id) AS mocks,
           COUNT(DISTINCT fq.id) AS failed_q
    FROM users u
    LEFT JOIN quiz_scores qs ON u.id = qs.user_id
    LEFT JOIN mock_scores ms ON u.id = ms.user_id
    LEFT JOIN failed_questions fq ON u.id = fq.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;overflow-y:auto;}
        .failed-tag{background:#fdecea;color:#c0392b;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;}
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
        <a href="/testmate/admin/users.php" class="active">👥 Users</a>
        <a href="/testmate/admin/stats.php">📈 Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
        <a href="/testmate/admin/add-material.php">➕ Add Material</a>
    </div>
    <div class="main-content">

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">User deleted successfully.</div>
        <?php endif; ?>

        <?php if ($user_detail): ?>
        <!-- ── USER DETAIL VIEW ── -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:22px;margin-bottom:4px;">👤 <?= htmlspecialchars($user_detail['name']) ?></h1>
                <p style="color:#888;font-size:14px;"><?= htmlspecialchars($user_detail['email']) ?> · Joined <?= date('d M Y', strtotime($user_detail['created_at'])) ?></p>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="/testmate/admin/users.php" class="btn btn-outline">← Back to Users</a>
                <?php if ($user_detail['role'] !== 'admin'): ?>
                <a href="/testmate/admin/users.php?delete=<?= $user_detail['id'] ?>"
                   onclick="return confirm('Delete <?= htmlspecialchars($user_detail['name']) ?>? This cannot be undone.')"
                   class="btn" style="background:#e74c3c;color:white;">🗑️ Delete User</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:24px;">
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#3498db;"><?= count($user_quizzes) ?></div>
                <div style="font-size:13px;color:#888;">Quizzes Taken</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#2c3e50;"><?= count($user_mocks) ?></div>
                <div style="font-size:13px;color:#888;">Practice Tests</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= count(array_filter($user_mocks, fn($m) => $m['passed'])) ?></div>
                <div style="font-size:13px;color:#888;">Tests Passed</div>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:2rem;font-weight:800;color:#e74c3c;"><?= count($user_failed) ?></div>
                <div style="font-size:13px;color:#888;">Failed Questions</div>
            </div>
        </div>

        <!-- Failed Questions -->
        <?php if (!empty($user_failed)): ?>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:14px;">❌ Failed Questions (<?= count($user_failed) ?>)</h2>
        <div style="margin-bottom:24px;">
            <?php foreach ($user_failed as $fq): ?>
            <div class="card" style="margin-bottom:12px;border-left:4px solid #e74c3c;padding:16px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;">
                    <span style="font-size:12px;background:#f0f0f0;padding:3px 10px;border-radius:20px;color:#666;">
                        <?= $fq['icon'] ?> <?= htmlspecialchars($fq['topic_name']) ?>
                    </span>
                    <span class="failed-tag">Failed <?= $fq['times_failed'] ?> time<?= $fq['times_failed'] > 1 ? 's' : '' ?></span>
                </div>
                <p style="font-size:14px;font-weight:500;margin-bottom:8px;"><?= htmlspecialchars($fq['question']) ?></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <?php foreach (['A'=>$fq['option_a'],'B'=>$fq['option_b'],'C'=>$fq['option_c'],'D'=>$fq['option_d']] as $key=>$val): ?>
                    <div style="background:<?= strtoupper($fq['correct_answer']) === $key ? '#eafaf1' : '#f8f9fa' ?>;padding:6px 10px;border-radius:6px;font-size:13px;">
                        <strong><?= $key ?>.</strong> <?= htmlspecialchars($val) ?>
                        <?= strtoupper($fq['correct_answer']) === $key ? ' ✅' : '' ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:24px;margin-bottom:24px;color:#888;">
            ✅ No failed questions — this user is doing great!
        </div>
        <?php endif; ?>

        <!-- Quiz History -->
        <h2 style="font-size:18px;font-weight:700;margin-bottom:14px;">📋 Quiz History</h2>
        <?php if (empty($user_quizzes)): ?>
        <div class="card" style="text-align:center;padding:24px;margin-bottom:24px;color:#888;">No quizzes taken yet.</div>
        <?php else: ?>
        <div class="table-wrap" style="margin-bottom:24px;">
            <table>
                <thead><tr><th>Topic</th><th>Score</th><th>Percentage</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($user_quizzes as $uq): ?>
                <tr>
                    <td><?= $uq['icon'] ?> <?= htmlspecialchars($uq['topic_name']) ?></td>
                    <td><?= $uq['score'] ?>/<?= $uq['total'] ?></td>
                    <td>
                        <?php $pct = round($uq['score']/$uq['total']*100); ?>
                        <span class="badge <?= $pct >= 80 ? 'badge-pass' : 'badge-fail' ?>"><?= $pct ?>%</span>
                    </td>
                    <td style="font-size:13px;color:#888;"><?= date('d M Y H:i', strtotime($uq['taken_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Practice Test History -->
        <h2 style="font-size:18px;font-weight:700;margin-bottom:14px;">⏱️ Practice Test History</h2>
        <?php if (empty($user_mocks)): ?>
        <div class="card" style="text-align:center;padding:24px;color:#888;">No practice tests taken yet.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Score</th><th>Percentage</th><th>Time Used</th><th>Result</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($user_mocks as $um):
                    $pct  = round($um['score']/$um['total']*100);
                    $mins = floor($um['time_taken']/60);
                    $secs = $um['time_taken']%60;
                ?>
                <tr>
                    <td><?= $um['score'] ?>/<?= $um['total'] ?></td>
                    <td><?= $pct ?>%</td>
                    <td><?= $mins ?>m <?= $secs ?>s</td>
                    <td><span class="badge <?= $um['passed'] ? 'badge-pass' : 'badge-fail' ?>"><?= $um['passed'] ? '✅ PASSED' : '❌ FAILED' ?></span></td>
                    <td style="font-size:13px;color:#888;"><?= date('d M Y H:i', strtotime($um['taken_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ── USER LIST ── -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h1 style="font-size:22px;">👥 All Users (<?= count($users) ?>)</h1>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Quizzes</th>
                        <th>Tests</th>
                        <th>Failed Q's</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
                    <td style="color:#888;font-size:13px;"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge" style="background:#fdecea;color:#e74c3c;">Admin</span>
                        <?php else: ?>
                            <span class="badge" style="background:#eafaf1;color:#27ae60;">User</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['quizzes'] ?></td>
                    <td><?= $u['mocks'] ?></td>
                    <td>
                        <?php if ($u['failed_q'] > 0): ?>
                        <span class="failed-tag"><?= $u['failed_q'] ?> failed</span>
                        <?php else: ?>
                        <span style="color:#27ae60;font-size:13px;">✅ None</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:#888;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <a href="/testmate/admin/users.php?view=<?= $u['id'] ?>"
                           style="color:#3498db;font-size:13px;text-decoration:none;margin-right:10px;">👁️ View</a>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <a href="/testmate/admin/users.php?delete=<?= $u['id'] ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars($u['name']) ?>? This cannot be undone.')"
                           style="color:#e74c3c;font-size:13px;text-decoration:none;">🗑️ Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>