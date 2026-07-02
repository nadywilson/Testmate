<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

// Quiz scores per topic
$topic_scores = $conn->prepare("
    SELECT t.name, t.icon,
           ROUND(AVG(qs.score / qs.total * 100)) AS avg_pct,
           COUNT(qs.id) AS attempts,
           MAX(qs.score) AS best_score,
           MAX(qs.total) AS total_q
    FROM quiz_scores qs
    JOIN topics t ON qs.topic_id = t.id
    WHERE qs.user_id = ?
    GROUP BY qs.topic_id
    ORDER BY avg_pct ASC
");
$topic_scores->bind_param("i", $user_id);
$topic_scores->execute();
$topic_scores = $topic_scores->get_result()->fetch_all(MYSQLI_ASSOC);

// Mock test history
$mock_history = $conn->prepare("
    SELECT score, total, passed, time_taken, taken_at
    FROM mock_scores
    WHERE user_id = ?
    ORDER BY taken_at DESC
    LIMIT 10
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
$tq = $conn->prepare("SELECT COUNT(*) AS cnt FROM quiz_scores WHERE user_id = ?");
$tq->bind_param("i", $user_id);
$tq->execute();
$total_quizzes = $tq->get_result()->fetch_assoc()['cnt'];

// Mock stats
$ms = $conn->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(passed),0) AS passed_cnt FROM mock_scores WHERE user_id = ?");
$ms->bind_param("i", $user_id);
$ms->execute();
$mock_stats = $ms->get_result()->fetch_assoc();

// Weak topics below 60%
$weak = array_filter($topic_scores, fn($t) => $t['avg_pct'] < 60);
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>📊 My Progress</h1>
    <p>Track your performance and see where to focus next</p>
</div>

<div class="container">

    <!-- Readiness Banner -->
    <?php
        $color = $overall >= 80 ? '#27ae60' : ($overall >= 60 ? '#e67e22' : '#e74c3c');
        $msg   = $overall >= 80
            ? "You're looking ready! Consider booking your test soon."
            : ($overall >= 60
                ? "Good progress! Focus on weaker topics to push past 80%."
                : "Keep going — study the materials and take more quizzes.");
    ?>
    <div class="card" style="display:flex;align-items:center;gap:28px;margin-bottom:28px;flex-wrap:wrap;">
        <div style="width:130px;height:130px;border-radius:50%;background:<?= $color ?>;
                    display:flex;flex-direction:column;align-items:center;justify-content:center;
                    color:white;flex-shrink:0;">
            <span style="font-size:34px;font-weight:800;line-height:1;"><?= $overall ?>%</span>
            <span style="font-size:11px;opacity:.9;margin-top:2px;">Ready</span>
        </div>
        <div style="flex:1;min-width:200px;">
            <h2 style="font-size:22px;margin-bottom:6px;">
                <?php if ($overall >= 80): ?>🎉 You're Test Ready!
                <?php elseif ($overall >= 60): ?>📈 Almost There!
                <?php else: ?>📚 Keep Studying!
                <?php endif; ?>
            </h2>
            <p style="color:#666;font-size:14px;margin-bottom:14px;"><?= $msg ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/testmate/mock-test.php"       class="btn btn-primary">Take Mock Test</a>
                <a href="/testmate/study-materials.php" class="btn btn-outline">Study Materials</a>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#3498db;"><?= $total_quizzes ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Quizzes Taken</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#2c3e50;"><?= $mock_stats['cnt'] ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Mock Tests Taken</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= $mock_stats['passed_cnt'] ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Mock Tests Passed</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#e74c3c;"><?= count($weak) ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Weak Topics</div>
        </div>
    </div>

    <!-- Topic Performance -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">📋 Performance by Topic</h2>

    <?php if (empty($topic_scores)): ?>
    <div class="card" style="text-align:center;padding:40px;margin-bottom:28px;">
        <div style="font-size:40px;margin-bottom:12px;">📝</div>
        <p style="color:#888;margin-bottom:16px;">No quizzes taken yet.</p>
        <a href="/testmate/quiz.php" class="btn btn-primary">Start a Quiz</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:28px;">
        <?php foreach ($topic_scores as $ts):
            $pct   = round($ts['avg_pct']);
            $cl    = $pct >= 80 ? 'green' : ($pct >= 60 ? 'orange' : 'red');
            $hex   = $pct >= 80 ? '#27ae60' : ($pct >= 60 ? '#e67e22' : '#e74c3c');
        ?>
        <div class="card" style="display:grid;grid-template-columns:36px 1fr auto;align-items:center;gap:16px;padding:16px 20px;">
            <span style="font-size:22px;text-align:center;"><?= $ts['icon'] ?></span>
            <div>
                <div style="font-weight:600;margin-bottom:6px;"><?= htmlspecialchars($ts['name']) ?></div>
                <div class="progress-bar">
                    <div class="progress-fill fill-<?= $cl ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <div style="font-size:12px;color:#999;margin-top:4px;">
                    <?= $ts['attempts'] ?> attempt<?= $ts['attempts'] > 1 ? 's' : '' ?> &nbsp;·&nbsp;
                    Best: <?= $ts['best_score'] ?>/<?= $ts['total_q'] ?>
                </div>
            </div>
            <div style="font-size:22px;font-weight:800;color:<?= $hex ?>;min-width:52px;text-align:right;">
                <?= $pct ?>%
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Weak Areas -->
    <?php if (!empty($weak)): ?>
    <div style="background:#fff8f0;border:1px solid #f5cba7;border-radius:12px;padding:20px;margin-bottom:28px;">
        <h3 style="color:#e67e22;font-size:16px;margin-bottom:12px;">⚠️ Focus on These Topics</h3>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <?php foreach ($weak as $wt): ?>
            <a href="/testmate/study-materials.php?topic=<?= array_search($wt, $topic_scores) + 1 ?>"
               style="background:#fdebd0;color:#ca6f1e;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;">
                <?= $wt['icon'] ?> <?= htmlspecialchars($wt['name']) ?> — <?= round($wt['avg_pct']) ?>%
            </a>
            <?php endforeach; ?>
        </div>
        <p style="font-size:13px;color:#888;">These topics are below 60%. Study the materials and retake those quizzes.</p>
    </div>
    <?php endif; ?>

    <!-- Mock Test History -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">⏱️ Mock Test History</h2>

    <?php if (empty($mock_history)): ?>
    <div class="card" style="text-align:center;padding:40px;margin-bottom:28px;">
        <div style="font-size:40px;margin-bottom:12px;">⏱️</div>
        <p style="color:#888;margin-bottom:16px;">No mock tests taken yet.</p>
        <a href="/testmate/mock-test.php" class="btn btn-primary">Take a Mock Test</a>
    </div>
    <?php else: ?>
    <div class="table-wrap" style="margin-bottom:30px;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Time Used</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mock_history as $m):
                $pct  = round($m['score'] / $m['total'] * 100);
                $mins = floor($m['time_taken'] / 60);
                $secs = $m['time_taken'] % 60;
            ?>
                <tr>
                    <td><?= date('d M Y, H:i', strtotime($m['taken_at'])) ?></td>
                    <td><?= $m['score'] ?>/<?= $m['total'] ?></td>
                    <td><?= $pct ?>%</td>
                    <td><?= $mins ?>m <?= $secs ?>s</td>
                    <td>
                        <?php if ($m['passed']): ?>
                            <span class="badge badge-pass">✅ PASSED</span>
                        <?php else: ?>
                            <span class="badge badge-fail">❌ FAILED</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>