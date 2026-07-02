<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /testmate/mock-test.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$qids       = $_POST['qids']    ?? [];
$corrects   = $_POST['correct'] ?? [];
$qtexts     = $_POST['qtexts']  ?? [];
$opta       = $_POST['opta']    ?? [];
$optb       = $_POST['optb']    ?? [];
$optc       = $_POST['optc']    ?? [];
$optd       = $_POST['optd']    ?? [];
$total      = count($qids);
$time_taken = (int)($_POST['time_taken'] ?? 0);
$score      = 0;
$results    = [];

// Mark each answer
for ($i = 0; $i < $total; $i++) {
    $qid         = $qids[$i];
    $user_answer = strtoupper(trim($_POST['ans_' . $qid] ?? ''));
    $right       = strtoupper(trim($corrects[$i]));
    $is_correct  = ($user_answer === $right);
    if ($is_correct) $score++;

    $results[] = [
        'question'    => $qtexts[$i],
        'user_answer' => $user_answer,
        'correct'     => $right,
        'is_correct'  => $is_correct,
        'option_a'    => $opta[$i],
        'option_b'    => $optb[$i],
        'option_c'    => $optc[$i],
        'option_d'    => $optd[$i],
    ];
}

$percentage  = $total > 0 ? round($score / $total * 100) : 0;
$passed      = $percentage >= 80 ? 1 : 0;
$mins_used   = floor($time_taken / 60);
$secs_used   = $time_taken % 60;

// Save to database
$save = $conn->prepare("INSERT INTO mock_scores (user_id, score, total, passed, time_taken) VALUES (?, ?, ?, ?, ?)");
$save->bind_param("iiiii", $user_id, $score, $total, $passed, $time_taken);
$save->execute();
?>
<?php include 'includes/header.php'; ?>

<div class="container" style="max-width:800px;">

    <!-- Result Banner -->
    <div style="border-radius:16px;padding:40px;text-align:center;color:white;margin-bottom:28px;
        background:<?= $passed ? 'linear-gradient(135deg,#27ae60,#2ecc71)' : 'linear-gradient(135deg,#c0392b,#e74c3c)' ?>">

        <div style="font-size:64px;margin-bottom:12px;"><?= $passed ? '🎉' : '📚' ?></div>
        <h1 style="font-size:30px;margin-bottom:8px;">
            <?= $passed ? 'YOU PASSED!' : 'Not Passed This Time' ?>
        </h1>
        <p style="font-size:15px;opacity:.9;margin-bottom:20px;">
            <?= $passed
                ? 'Excellent work! You are well prepared for your licence test.'
                : 'You need 80% to pass. Study the weak topics and try again!' ?>
        </p>

        <!-- Big Score -->
        <div style="font-size:60px;font-weight:800;line-height:1;"><?= $score ?>/<?= $total ?></div>
        <div style="font-size:22px;margin:8px 0 16px;"><?= $percentage ?>%</div>

        <!-- Stats pills -->
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:16px;">
            <span style="background:rgba(255,255,255,.2);padding:7px 18px;border-radius:20px;font-size:14px;">
                ⏱️ Time: <?= $mins_used ?>m <?= $secs_used ?>s
            </span>
            <span style="background:rgba(255,255,255,.2);padding:7px 18px;border-radius:20px;font-size:14px;">
                ✅ Correct: <?= $score ?>
            </span>
            <span style="background:rgba(255,255,255,.2);padding:7px 18px;border-radius:20px;font-size:14px;">
                ❌ Wrong: <?= $total - $score ?>
            </span>
        </div>

        <div style="font-size:13px;opacity:.75;">
            Pass mark: 80% (<?= ceil($total * 0.8) ?>/<?= $total ?> correct)
        </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;">
        <a href="/testmate/mock-test.php"       class="btn btn-primary">🔄 Try Again</a>
        <a href="/testmate/study-materials.php" class="btn btn-outline">📚 Study More</a>
        <a href="/testmate/progress.php"        class="btn btn-outline">📊 My Progress</a>
        <a href="/testmate/dashboard.php"       class="btn btn-outline">🏠 Dashboard</a>
    </div>

    <!-- Answer Review -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">📋 Answer Review</h2>

    <?php foreach ($results as $i => $r):
        $opts = [
            'A' => $r['option_a'],
            'B' => $r['option_b'],
            'C' => $r['option_c'],
            'D' => $r['option_d'],
        ];
        $user  = $r['user_answer'];
        $right = $r['correct'];
    ?>
    <div class="card" style="margin-bottom:14px;border-left:4px solid <?= $r['is_correct'] ? '#27ae60' : '#e74c3c' ?>;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">
                Question <?= $i+1 ?>
            </span>
            <?php if ($r['is_correct']): ?>
                <span class="badge badge-pass">✅ Correct</span>
            <?php else: ?>
                <span class="badge badge-fail">❌ Wrong</span>
            <?php endif; ?>
        </div>

        <p style="font-weight:500;font-size:15px;margin-bottom:12px;line-height:1.5;">
            <?= htmlspecialchars($r['question']) ?>
        </p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <?php foreach ($opts as $key => $val):
                $bg = '#f8f9fa';
                if ($key === $right)                    $bg = '#eafaf1';
                if ($key === $user && $user !== $right) $bg = '#fdecea';
            ?>
            <div style="background:<?= $bg ?>;padding:8px 12px;border-radius:6px;font-size:14px;">
                <strong><?= $key ?>.</strong> <?= htmlspecialchars($val) ?>
                <?php if ($key === $right):             ?> ✅<?php endif; ?>
                <?php if ($key === $user && $user !== $right): ?> ❌<?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$r['is_correct'] && $user): ?>
        <div style="margin-top:10px;font-size:13px;color:#888;">
            Your answer: <strong style="color:#e74c3c;"><?= $user ?>. <?= htmlspecialchars($opts[$user] ?? '–') ?></strong>
            &nbsp;→&nbsp;
            Correct: <strong style="color:#27ae60;"><?= $right ?>. <?= htmlspecialchars($opts[$right] ?? '') ?></strong>
        </div>
        <?php elseif (!$user): ?>
        <div style="margin-top:10px;font-size:13px;color:#e67e22;">
            ⚠️ Not answered — correct was: <strong><?= $right ?>. <?= htmlspecialchars($opts[$right] ?? '') ?></strong>
        </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

    <!-- Bottom Actions -->
    <div style="display:flex;gap:12px;flex-wrap:wrap;padding:20px 0 40px;">
        <a href="/testmate/mock-test.php"       class="btn btn-primary">🔄 Try Again</a>
        <a href="/testmate/study-materials.php" class="btn btn-outline">📚 Study More</a>
        <a href="/testmate/dashboard.php"       class="btn btn-outline">🏠 Dashboard</a>
    </div>

</div>

<?php include 'includes/footer.php'; ?>