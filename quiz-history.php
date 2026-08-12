<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

// Fetch all quiz attempts for this learner
$stmt = $conn->prepare("
    SELECT qs.id, qs.topic_id, qs.score, qs.total, qs.submitted_at, qs.status,
           t.name AS topic_name, t.icon
    FROM quiz_scores qs
    JOIN topics t ON qs.topic_id = t.id
    WHERE qs.user_id = ?
    ORDER BY qs.submitted_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$attempts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_attempts = count($attempts);
$avg_score = 0;
if ($total_attempts > 0) {
    $sum = 0;
    foreach ($attempts as $a) {
        $sum += $a['total'] > 0 ? ($a['score'] / $a['total']) * 100 : 0;
    }
    $avg_score = round($sum / $total_attempts);
}
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>📜 Quiz History</h1>
    <p>Review your past quiz attempts and track your progress</p>
</div>

<div class="container">

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#3498db;"><?= $total_attempts ?></div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Quizzes Taken</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:#27ae60;"><?= $avg_score ?>%</div>
            <div style="font-size:13px;color:#888;margin-top:4px;">Average Score</div>
        </div>
    </div>

    <?php if (empty($attempts)): ?>
    <div class="card" style="text-align:center;padding:50px;">
        <div style="font-size:3rem;margin-bottom:16px;">📝</div>
        <h2 style="font-size:18px;margin-bottom:8px;color:#374151;">No quizzes yet</h2>
        <p style="color:#888;margin-bottom:20px;">You haven't taken any quizzes. Start learning now!</p>
        <a href="/testmate/quiz.php" class="btn btn-primary">Browse Topics</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($attempts as $a):
            $pct = $a['total'] > 0 ? round(($a['score'] / $a['total']) * 100) : 0;
            $hex = $pct >= 80 ? '#27ae60' : ($pct >= 60 ? '#e67e22' : '#e74c3c');
        ?>
        <div class="card" style="display:grid;grid-template-columns:48px 1fr auto auto;align-items:center;gap:16px;padding:16px 20px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">
                <?= htmlspecialchars($a['icon'] ?? '📚') ?>
            </div>
            <div>
                <div style="font-weight:600;color:#111827;margin-bottom:4px;">
                    <?= htmlspecialchars($a['topic_name']) ?>
                    <?php if ($a['status'] !== 'approved'): ?>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:600;text-transform:uppercase;margin-left:6px;
                            <?php if ($a['status'] === 'pending'): ?>background:#fef3c7;color:#92400e;<?php else: ?>background:#fee2e2;color:#991b1b;<?php endif; ?>">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;color:#888;">
                    <?= date('F j, Y \a\t g:i A', strtotime($a['submitted_at'])) ?>
                </div>
            </div>
            <div style="text-align:right;min-width:60px;">
                <div style="font-size:1.25rem;font-weight:800;color:<?= $hex ?>;"><?= $pct ?>%</div>
                <div style="font-size:12px;color:#888;"><?= $a['score'] ?>/<?= $a['total'] ?></div>
            </div>
            <a href="/testmate/quiz.php?result_id=<?= $a['id'] ?>" class="btn btn-primary" style="font-size:13px;padding:8px 16px;">Review</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>