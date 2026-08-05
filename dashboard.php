<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

// Topic scores
$topic_scores = $conn->prepare("
    SELECT t.name, t.icon,
           ROUND(AVG(qs.score / qs.total * 100)) AS avg_pct,
           COUNT(qs.id) AS attempts
    FROM quiz_scores qs
    JOIN topics t ON qs.topic_id = t.id
    WHERE qs.user_id = ?
    GROUP BY qs.topic_id
    ORDER BY avg_pct ASC
");
$topic_scores->bind_param("i", $user_id);
$topic_scores->execute();
$topic_scores = $topic_scores->get_result()->fetch_all(MYSQLI_ASSOC);

// Mock history
$mock_history = $conn->prepare("
    SELECT score, total, passed, taken_at
    FROM mock_scores
    WHERE user_id = ?
    ORDER BY taken_at DESC
    LIMIT 5
");
$mock_history->bind_param("i", $user_id);
$mock_history->execute();
$mock_history = $mock_history->get_result()->fetch_all(MYSQLI_ASSOC);

// Overall readiness
$overall = 0;
if (!empty($topic_scores)) {
    $overall = round(array_sum(array_column($topic_scores, 'avg_pct')) / count($topic_scores));
}

// Total quizzes
$total_quizzes = $conn->prepare("SELECT COUNT(*) AS cnt FROM quiz_scores WHERE user_id = ?");
$total_quizzes->bind_param("i", $user_id);
$total_quizzes->execute();
$total_quizzes = $total_quizzes->get_result()->fetch_assoc()['cnt'];

// Mock stats
$total_mocks = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(passed),0) AS passed_cnt FROM mock_scores WHERE user_id = ?");
$total_mocks->bind_param("i", $user_id);
$total_mocks->execute();
$mock_stats = $total_mocks->get_result()->fetch_assoc();

// Daily reminder and streak
$today = date('Y-m-d');
$last  = $conn->prepare("SELECT last_active, streak FROM users WHERE id = ?");
$last->bind_param("i", $user_id);
$last->execute();
$last_data   = $last->get_result()->fetch_assoc();
$streak      = $last_data['streak'] ?? 0;
$last_active = $last_data['last_active'];
$reminder    = '';

if ($last_active) {
    $diff = (int)((strtotime($today) - strtotime($last_active)) / 86400);
    if ($diff === 0) {
        // already active today, keep streak
    } elseif ($diff === 1) {
        $streak++;
        $conn->query("UPDATE users SET last_active='$today', streak=$streak WHERE id=$user_id");
    } else {
        $streak = 1;
        $conn->query("UPDATE users SET last_active='$today', streak=$streak WHERE id=$user_id");
    }
    if ($diff >= 1) {
        $reminder = $diff === 1
            ? "You have not practised since yesterday — keep your streak going!"
            : "You have not practised in $diff days — come back and keep improving!";
    }
} else {
    $conn->query("UPDATE users SET last_active='$today', streak=1 WHERE id=$user_id");
    $streak = 1;
}

// Assigned quizzes count
$assigned = $conn->prepare("SELECT COUNT(*) AS cnt FROM assigned_quizzes WHERE user_id = ? AND is_completed = 0");
$assigned->bind_param("i", $user_id);
$assigned->execute();
$assigned_count = $assigned->get_result()->fetch_assoc()['cnt'];
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>Welcome back, <?= htmlspecialchars($name) ?>!</h1>
    <p>Here is your learning progress at a glance</p>
</div>

<div class="container">

    <!-- Daily Reminder -->
    <?php if ($reminder): ?>
    <div style="background:#fff8f0;border-left:4px solid #e67e22;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <div style="font-weight:600;color:#e67e22;margin-bottom:2px;">Practice Reminder</div>
            <div style="font-size:14px;color:#555;"><?= $reminder ?></div>
        </div>
        <a href="/testmate/quiz.php" class="btn btn-primary" style="background:#e67e22;font-size:14px;padding:8px 18px;">Practice Now</a>
    </div>
    <?php endif; ?>

    <!-- Streak -->
    <?php if ($streak > 1): ?>
    <div style="background:#eafaf1;border-left:4px solid #27ae60;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
        <div style="font-weight:600;color:#27ae60;margin-bottom:2px;">Day Streak: <?= $streak ?> days in a row!</div>
        <div style="font-size:14px;color:#555;">Great consistency! Keep practising every day.</div>
    </div>
    <?php endif; ?>

    <!-- Assigned Quiz Alert -->
    <?php if ($assigned_count > 0): ?>
    <div style="background:#eaf4ff;border-left:4px solid #3498db;border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <div style="font-weight:600;color:#2471a3;margin-bottom:2px;">Assigned Quiz</div>
            <div style="font-size:14px;color:#555;">Your instructor has assigned <?= $assigned_count ?> question<?= $assigned_count > 1 ? 's' : '' ?> for you to complete.</div>
        </div>
        <a href="/testmate/assigned-quiz.php" class="btn btn-primary" style="font-size:14px;padding:8px 18px;">Start Assigned Quiz</a>
    </div>
    <?php endif; ?>

    <!-- Readiness Banner -->
    <?php
    $color = $overall >= 80 ? '#27ae60' : ($overall >= 60 ? '#e67e22' : '#e74c3c');
    $msg   = $overall >= 80
        ? "You are looking ready. Consider booking your test soon."
        : ($overall >= 60
            ? "Good progress. Focus on your weaker topics to push past 80%."
            : "Keep going — study the materials and take more quizzes.");
    ?>
    <div class="card" style="display:flex;align-items:center;gap:28px;margin-bottom:28px;flex-wrap:wrap;">
        <div style="width:120px;height:120px;border-radius:50%;background:<?= $color ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;flex-shrink:0;">
            <span style="font-size:32px;font-weight:800;line-height:1;"><?= $overall ?>%</span>
            <span style="font-size:11px;opacity:0.9;margin-top:2px;">Ready</span>
        </div>
        <div style="flex:1;min-width:200px;">
            <h2 style="font-size:20px;margin-bottom:6px;">
                <?php if ($overall >= 80): ?>You are Test Ready!
                <?php elseif ($overall >= 60): ?>Almost There!
                <?php else: ?>Keep Studying!
                <?php endif; ?>
            </h2>
            <p style="color:#666;font-size:14px;margin-bottom:14px;"><?= $msg ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/testmate/mock-test.php" class="btn btn-primary">Take Mock Test</a>
                <a href="/testmate/study-materials.php" class="btn btn-outline">Study Materials</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#3498db;"><?= $total_quizzes ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Quizzes Taken</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= $mock_stats['cnt'] ?? 0 ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Mock Tests Taken</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= $mock_stats['passed_cnt'] ?? 0 ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Mock Tests Passed</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#f1c40f;"><?= $streak ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Day Streak</div>
        </div>
    </div>

    <!-- Topic Performance -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">Topic Performance</h2>

    <?php if (empty($topic_scores)): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <p style="color:#888;margin-bottom:16px;">You have not taken any quizzes yet.</p>
        <a href="/testmate/quiz.php" class="btn btn-primary">Start Your First Quiz</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:28px;">
        <?php foreach ($topic_scores as $ts):
            $pct   = round($ts['avg_pct']);
            $color = $pct >= 80 ? 'green' : ($pct >= 60 ? 'orange' : 'red');
            $hex   = $pct >= 80 ? '#27ae60' : ($pct >= 60 ? '#e67e22' : '#e74c3c');
        ?>
        <div class="card" style="display:grid;grid-template-columns:1fr auto;align-items:center;gap:16px;padding:16px 20px;">
            <div>
                <div style="font-weight:600;margin-bottom:6px;"><?= htmlspecialchars($ts['name']) ?></div>
                <div class="progress-bar">
                    <div class="progress-fill fill-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div style="font-size:12px;color:#999;margin-top:4px;"><?= $ts['attempts'] ?> attempt<?= $ts['attempts'] > 1 ? 's' : '' ?></div>
            </div>
            <div style="font-size:22px;font-weight:800;color:<?= $hex ?>;min-width:50px;text-align:right;"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Mock Test History -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">Recent Mock Tests</h2>

    <?php if (empty($mock_history)): ?>
    <div class="card" style="text-align:center;padding:40px;">
        <p style="color:#888;margin-bottom:16px;">No mock tests taken yet.</p>
        <a href="/testmate/mock-test.php" class="btn btn-primary">Take a Mock Test</a>
    </div>
    <?php else: ?>
    <div class="table-wrap" style="margin-bottom:28px;">
        <table>
            <thead>
                <tr><th>Date</th><th>Score</th><th>Percentage</th><th>Result</th></tr>
            </thead>
            <tbody>
            <?php foreach ($mock_history as $m):
                $pct = round($m['score'] / $m['total'] * 100);
            ?>
                <tr>
                    <td><?= date('d M Y, H:i', strtotime($m['taken_at'])) ?></td>
                    <td><?= $m['score'] ?>/<?= $m['total'] ?></td>
                    <td><?= $pct ?>%</td>
                    <td>
                        <?php if ($m['passed']): ?>
                            <span class="badge badge-pass">PASSED</span>
                        <?php else: ?>
                            <span class="badge badge-fail">FAILED</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">Quick Actions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:30px;">
        <a href="/testmate/study-materials.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">Study Materials</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Review all topics</div>
        </a>
        <a href="/testmate/quiz.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">Topic Quiz</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Test one topic</div>
        </a>
        <a href="/testmate/mock-test.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">Mock Test</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Full timed test</div>
        </a>
        <a href="/testmate/progress.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">My Progress</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">View your scores</div>
        </a>
        <a href="/testmate/leaderboard.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">Leaderboard</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Top performers</div>
        </a>
        <a href="/testmate/certificate.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">Certificate</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Print achievement</div>
        </a>
        <a href="/testmate/profile.php" class="card" style="text-decoration:none;text-align:center;padding:20px;transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='translateY(0)'">
            <div style="font-weight:600;color:#2c3e50;font-size:15px;">My Profile</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Edit your account</div>
        </a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>