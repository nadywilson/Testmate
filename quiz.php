<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id  = $_SESSION['user_id'];
$topic_id = isset($_GET['topic']) ? (int)$_GET['topic'] : 0;

// Block admins
if ($_SESSION['role'] === 'admin') {
    include 'includes/header.php';
    echo '<div style="max-width:560px;margin:80px auto;text-align:center;">
        <div style="font-size:4rem;margin-bottom:16px;">🔐</div>
        <h2 style="font-size:22px;margin-bottom:10px;color:#2c3e50;">Administrator Account</h2>
        <p style="color:#666;margin-bottom:24px;">Administrators cannot take quizzes. Please login as a Learner to practice.</p>
        <a href="/testmate/admin/index.php" class="btn btn-primary">Go to Admin Dashboard</a>
    </div>';
    include 'includes/footer.php';
    exit();
}

$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$questions = [];
$topic     = null;
$mode      = $_GET['mode'] ?? 'normal'; // normal or retry

// ── Bonus drag-and-drop pairs (2 random pairs per topic, pulled from DB), appended to a normal-mode topic quiz ──
$dragdrop_bonus = [];
if ($topic_id > 0) {
    $dd = $conn->prepare("SELECT * FROM dragdrop_pairs WHERE topic_id = ? ORDER BY RAND() LIMIT 2");
    $dd->bind_param("i", $topic_id);
    $dd->execute();
    $dragdrop_bonus[$topic_id] = $dd->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($topic_id > 0) {
    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $topic_id);
    $t->execute();
    $topic = $t->get_result()->fetch_assoc();

    if ($mode === 'retry') {
        // Get failed questions for this user and topic
        $q = $conn->prepare("
            SELECT q.* FROM questions q
            JOIN failed_questions fq ON q.id = fq.question_id
            WHERE fq.user_id = ? AND fq.topic_id = ?
            ORDER BY fq.times_failed DESC
            LIMIT 5
        ");
        $q->bind_param("ii", $user_id, $topic_id);
        $q->execute();
        $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);

        // If no failed questions fall back to normal
        if (empty($questions)) {
            $mode = 'normal';
        }
    }

    if ($mode === 'normal') {
        $q = $conn->prepare("SELECT * FROM questions WHERE topic_id = ? ORDER BY RAND() LIMIT 5");
        $q->bind_param("i", $topic_id);
        $q->execute();
        $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic_id   = (int)$_POST['topic_id'];
    $qids       = $_POST['qids'];
    $corrects   = $_POST['correct'];
    $total      = count($qids);
    $score      = 0;
    $results    = [];

    for ($i = 0; $i < $total; $i++) {
        $qid         = $qids[$i];
        $user_answer = strtoupper(trim($_POST['ans_' . $qid] ?? ''));
        $right       = strtoupper(trim($corrects[$i]));
        $is_correct  = ($user_answer === $right);
        if ($is_correct) $score++;

        $results[] = [
            'type'        => 'mcq',
            'question'    => $_POST['qtexts'][$i],
            'user_answer' => $user_answer,
            'correct'     => $right,
            'is_correct'  => $is_correct,
            'option_a'    => $_POST['opta'][$i],
            'option_b'    => $_POST['optb'][$i],
            'option_c'    => $_POST['optc'][$i],
            'option_d'    => $_POST['optd'][$i],
            'explanation' => $_POST['explanations'][$i],
            'image_path'  => $_POST['images'][$i],
            'file_type'   => $_POST['filetypes'][$i] ?? 'image',
            'question_id' => $qid,
        ];

        // Save or update failed questions
        if (!$is_correct) {
            $stmt = $conn->prepare("
                INSERT INTO failed_questions (user_id, question_id, topic_id, times_failed)
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE times_failed = times_failed + 1, last_failed = NOW()
            ");
            $stmt->bind_param("iii", $user_id, $qid, $topic_id);
            $stmt->execute();
        } else {
            // Remove from failed if they got it right
            $stmt = $conn->prepare("DELETE FROM failed_questions WHERE user_id = ? AND question_id = ?");
            $stmt->bind_param("ii", $user_id, $qid);
            $stmt->execute();
        }
    }

    // Bonus drag-and-drop round (only present on normal-mode submissions that included it)
    if (isset($_POST['has_dragdrop']) && !empty($_POST['dd_pairs'])) {
        foreach ($_POST['dd_pairs'] as $pairId) {
            $pairId     = (int)$pairId;
            $placed     = $_POST['dd_zone_' . $pairId] ?? '';
            $is_correct = ((int)$placed === $pairId);
            $total++;
            if ($is_correct) $score++;

            $results[] = [
                'type'          => 'dragdrop',
                'value'         => $_POST['dd_value_' . $pairId] ?? '',
                'target'        => $_POST['dd_target_' . $pairId] ?? '',
                'is_correct'    => $is_correct,
            ];
        }
    }

    // Save as pending — the learner will not see their score until an admin approves it
    $results_json = json_encode($results);
    $save = $conn->prepare("INSERT INTO quiz_scores (user_id, topic_id, score, total, status, results_json, submitted_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
    $save->bind_param("iiiis", $user_id, $topic_id, $score, $total, $results_json);
    $save->execute();
    $result_id = $conn->insert_id;

    header("Location: /testmate/quiz.php?result_id=" . $result_id);
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<style>
.dd-board { display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start; }
@media (max-width: 760px) { .dd-board { grid-template-columns: 1fr; } }
.dd-col-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#888; margin-bottom:10px; }
.dd-chip {
    background:white; border:2px solid #3498db; color:#2471a3; border-radius:10px;
    padding:12px 14px; margin-bottom:10px; font-size:13px; font-weight:600; cursor:grab;
    box-shadow:0 2px 6px rgba(0,0,0,.06); transition:all .15s; user-select:none;
}
.dd-chip:hover { background:#f0f8ff; }
.dd-chip.placed { opacity:.35; cursor:not-allowed; }
.dd-chip.selected { border-color:#e67e22; background:#fff8f0; color:#e67e22; }
.dd-chip.dragging { opacity:.4; }
.dd-zone {
    background:#f8f9fa; border:2px dashed #ccc; border-radius:10px; padding:12px 14px;
    margin-bottom:10px; min-height:50px; transition:all .15s;
}
.dd-zone.dragover { border-color:#3498db; background:#f0f8ff; }
.dd-zone-label { font-size:13px; color:#444; margin-bottom:6px; }
.dd-zone-slot {
    background:white; border:2px solid #e0e0e0; border-radius:8px; padding:9px 12px;
    font-size:13px; font-weight:600; color:#999; min-height:18px; display:flex;
    align-items:center; justify-content:space-between; gap:8px; cursor:pointer;
}
.dd-zone-slot.filled { border-color:#3498db; color:#2c3e50; background:#f0f8ff; }
.dd-zone.correct .dd-zone-slot { border-color:#27ae60; background:#eafaf1; color:#1e8449; }
.dd-zone.incorrect .dd-zone-slot { border-color:#e74c3c; background:#fdecea; color:#c0392b; }
.dd-zone.correct { border-color:#27ae60; border-style:solid; }
.dd-zone.incorrect { border-color:#e74c3c; border-style:solid; }
</style>

<div class="page-header">
    <h1>✅ Topic Quizzes</h1>
    <p>5 questions per quiz · 15 minute timer · Instant feedback</p>
</div>

<div class="container">

<?php if (isset($_GET['result_id'])):
    $result_id = (int)$_GET['result_id'];

    $rq = $conn->prepare("SELECT * FROM quiz_scores WHERE id = ? AND user_id = ?");
    $rq->bind_param("ii", $result_id, $user_id);
    $rq->execute();
    $score_row = $rq->get_result()->fetch_assoc();

    // Auto-reveal: once 2 minutes have passed since submission, flip pending -> approved automatically.
    // Admin can still approve earlier via the review page to reveal sooner.
    $seconds_left = 0;
    if ($score_row && $score_row['status'] === 'pending') {
        $elapsed = time() - strtotime($score_row['submitted_at']);
        if ($elapsed >= 120) {
            $upd = $conn->prepare("UPDATE quiz_scores SET status = 'approved' WHERE id = ?");
            $upd->bind_param("i", $result_id);
            $upd->execute();
            $score_row['status'] = 'approved';
        } else {
            $seconds_left = 120 - $elapsed;
        }
    }

    if (!$score_row):
?>
    <div class="card" style="text-align:center;padding:50px;color:#888;">
        <p>We couldn't find that quiz result.</p>
        <a href="/testmate/quiz.php" class="btn btn-primary" style="margin-top:16px;">Back to Topics</a>
    </div>

<?php elseif ($score_row['status'] === 'pending'): ?>

    <div class="card" style="text-align:center;padding:50px;">
        <div style="font-size:3rem;margin-bottom:16px;">⏳</div>
        <h2 style="font-size:22px;margin-bottom:10px;color:#2c3e50;">Submitted — Awaiting Review</h2>
        <p style="color:#666;max-width:420px;margin:0 auto 24px;line-height:1.6;">
            Your quiz has been submitted and auto-marked. Your score will reveal automatically in
            <strong id="revealCountdown"><?= $seconds_left ?></strong> seconds — or sooner if an administrator approves it early.
        </p>
        <a href="/testmate/quiz.php" class="btn btn-primary">Back to Topics</a>
        <a href="/testmate/dashboard.php" class="btn btn-outline" style="margin-left:10px;">Dashboard</a>
    </div>

    <script>
    let secsLeft = <?= $seconds_left ?>;
    const cd = document.getElementById('revealCountdown');
    const timer = setInterval(() => {
        secsLeft--;
        if (cd) cd.textContent = Math.max(secsLeft, 0);
        if (secsLeft <= 0) {
            clearInterval(timer);
            window.location.reload();
        }
    }, 1000);
    </script>

<?php elseif ($score_row['status'] === 'rejected'): ?>

    <div class="card" style="text-align:center;padding:50px;">
        <div style="font-size:3rem;margin-bottom:16px;">🚫</div>
        <h2 style="font-size:22px;margin-bottom:10px;color:#c0392b;">Result Not Approved</h2>
        <p style="color:#666;max-width:420px;margin:0 auto 24px;line-height:1.6;">
            An administrator did not approve this attempt. Please contact your instructor if you believe this is a mistake, or try the quiz again.
        </p>
        <a href="/testmate/quiz.php?topic=<?= $score_row['topic_id'] ?>" class="btn btn-primary">Try Again</a>
        <a href="/testmate/quiz.php" class="btn btn-outline" style="margin-left:10px;">All Topics</a>
    </div>

<?php else:
    // status === 'approved'
    $results    = json_decode($score_row['results_json'], true) ?: [];
    $score      = $score_row['score'];
    $total      = $score_row['total'];
    $percentage = round($score / $total * 100);
    $tid        = $score_row['topic_id'];

    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $tid);
    $t->execute();
    $rtopic = $t->get_result()->fetch_assoc();

    // Count failed questions for this topic
    $fq = $conn->prepare("SELECT COUNT(*) AS cnt FROM failed_questions WHERE user_id = ? AND topic_id = ?");
    $fq->bind_param("ii", $user_id, $tid);
    $fq->execute();
    $failed_count = $fq->get_result()->fetch_assoc()['cnt'];
?>

    <!-- Score Banner -->
    <div style="border-radius:14px;padding:36px;text-align:center;color:white;margin-bottom:28px;
        background:<?= $percentage >= 80 ? 'linear-gradient(135deg,#27ae60,#2ecc71)' : ($percentage >= 60 ? 'linear-gradient(135deg,#e67e22,#f39c12)' : 'linear-gradient(135deg,#c0392b,#e74c3c)') ?>">
        <div style="font-size:56px;font-weight:800;line-height:1;"><?= $score ?>/<?= $total ?></div>
        <div style="font-size:24px;margin:8px 0;"><?= $percentage ?>%</div>
        <h2 style="font-size:22px;margin-bottom:6px;">
            <?php if ($percentage >= 80): ?>🎉 Excellent!
            <?php elseif ($percentage >= 60): ?>👍 Good effort!
            <?php else: ?>📚 Keep practising!
            <?php endif; ?>
        </h2>
        <p style="opacity:.9;font-size:15px;"><?= htmlspecialchars($rtopic['name']) ?> Quiz</p>
    </div>

    <!-- Action Buttons -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;">
    <a href="/testmate/quiz.php?topic=<?= $tid ?>" class="btn btn-primary">Try Again</a>
    <?php if ($failed_count > 0): ?>
    <a href="/testmate/quiz.php?topic=<?= $tid ?>&mode=retry" class="btn btn-outline" style="border-color:#e74c3c;color:#e74c3c;">
        Retry Failed Questions
    </a>
    <?php endif; ?>
    <a href="/testmate/quiz.php" class="btn btn-outline">All Topics</a>
    <a href="/testmate/dashboard.php" class="btn btn-outline">Dashboard</a>
</div>

<!-- Study Direction Banner -->
<?php if ($percentage < 80): ?>
<div style="background:#fff8f0;border:1px solid #f5cba7;border-radius:12px;padding:20px 24px;margin-bottom:28px;">
    <h3 style="color:#e67e22;font-size:16px;font-weight:700;margin-bottom:8px;">You scored below 80% — Here is what to study</h3>
    <p style="color:#555;font-size:14px;margin-bottom:16px;line-height:1.6;">
        Based on your results, we recommend going back to study the
        <strong><?= htmlspecialchars($rtopic['name']) ?></strong> topic before trying again.
        You can read the material or watch the animated video explanations.
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="/testmate/study-materials.php?topic=<?= $tid ?>&mode=read"
           class="btn btn-outline" style="font-size:14px;padding:8px 18px;">
            Read <?= htmlspecialchars($rtopic['name']) ?>
        </a>
        <a href="/testmate/study-materials.php?topic=<?= $tid ?>&mode=video"
           class="btn btn-primary" style="font-size:14px;padding:8px 18px;background:#e67e22;border:none;">
            Watch Video Explanations
        </a>
    </div>
</div>
<?php endif; ?>

    <!-- Answer Review -->
    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">📋 Answer Review</h2>

    <?php foreach ($results as $i => $r): ?>
    <div class="card" style="margin-bottom:14px;border-left:4px solid <?= $r['is_correct'] ? '#27ae60' : '#e74c3c' ?>;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">
                <?= ($r['type'] ?? 'mcq') === 'dragdrop' ? 'Drag & Drop' : 'Question ' . ($i+1) ?>
            </span>
            <?php if ($r['is_correct']): ?>
                <span class="badge badge-pass">✅ Correct</span>
            <?php else: ?>
                <span class="badge badge-fail">❌ Wrong</span>
            <?php endif; ?>
        </div>

        <?php if (($r['type'] ?? 'mcq') === 'dragdrop'): ?>

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;font-size:14px;">
            <span style="background:#eaf4ff;color:#2471a3;padding:8px 14px;border-radius:8px;font-weight:600;"><?= htmlspecialchars($r['value']) ?></span>
            <span style="color:#999;">→</span>
            <span style="background:<?= $r['is_correct'] ? '#eafaf1' : '#fdecea' ?>;color:<?= $r['is_correct'] ? '#1e8449' : '#c0392b' ?>;padding:8px 14px;border-radius:8px;font-weight:600;">
                <?= htmlspecialchars($r['target']) ?>
            </span>
        </div>

        <?php else:
            $opts = ['A'=>$r['option_a'],'B'=>$r['option_b'],'C'=>$r['option_c'],'D'=>$r['option_d']];
        ?>

        <?php if ($r['image_path']):
            $rftype = $r['file_type'] ?? 'image';
        ?>
            <?php if ($rftype === 'pdf'): ?>
            <iframe src="<?= htmlspecialchars($r['image_path']) ?>" style="width:100%;height:300px;border:1px solid #eee;border-radius:8px;margin-bottom:12px;"></iframe>
            <?php elseif ($rftype === 'video'): ?>
            <video src="<?= htmlspecialchars($r['image_path']) ?>" controls style="max-width:100%;max-height:250px;border-radius:8px;margin-bottom:12px;display:block;"></video>
            <?php else: ?>
            <img src="<?= htmlspecialchars($r['image_path']) ?>"
                 style="max-width:100%;max-height:200px;border-radius:8px;margin-bottom:12px;display:block;">
            <?php endif; ?>
        <?php endif; ?>

        <p style="font-weight:500;margin-bottom:12px;font-size:15px;"><?= htmlspecialchars($r['question']) ?></p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px;">
            <?php foreach ($opts as $key => $val):
                $is_user    = ($key === $r['user_answer']);
                $is_correct = ($key === $r['correct']);
                $bg = '#f8f9fa';
                if ($is_correct) $bg = '#eafaf1';
                elseif ($is_user && !$is_correct) $bg = '#fdecea';
            ?>
            <div style="background:<?= $bg ?>;padding:8px 12px;border-radius:6px;font-size:14px;">
                <strong><?= $key ?>.</strong> <?= htmlspecialchars($val) ?>
                <?php if ($is_correct): ?> ✅<?php endif; ?>
                <?php if ($is_user && !$is_correct): ?> ❌<?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($r['explanation']): ?>
<div style="background:#f0f8ff;border-left:3px solid #3498db;padding:10px 14px;border-radius:0 6px 6px 0;font-size:13px;color:#555;">
    <?= htmlspecialchars($r['explanation']) ?>
</div>
<?php endif; ?>
<?php if (!$r['is_correct']): ?>
<div style="margin-top:8px;font-size:13px;color:#888;">
    Need help with this?
    <a href="/testmate/study-materials.php?topic=<?= $tid ?>&mode=read" style="color:#3498db;font-weight:600;">Read the <?= htmlspecialchars($rtopic['name']) ?> notes</a>
    or
    <a href="/testmate/study-materials.php?topic=<?= $tid ?>&mode=video" style="color:#e67e22;font-weight:600;">Watch the video</a>
</div>
<?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

<?php endif; // close inner score-status if/elseif/else chain ?>

<?php elseif ($topic_id > 0 && !empty($questions)): ?>

    <div style="max-width:750px;margin:0 auto;">

        <?php if ($mode === 'retry'): ?>
        <div style="background:#fdecea;border:1px solid #f5b7b1;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
            <span style="font-size:1.5rem;">❌</span>
            <div>
                <strong style="color:#c0392b;">Retry Mode</strong>
                <p style="font-size:13px;color:#888;margin:0;">These are questions you previously got wrong. Get them right to clear them!</p>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-size:22px;"><?= $topic['icon'] ?> <?= htmlspecialchars($topic['name']) ?></h2>
                <p style="color:#888;font-size:14px;">
                    5 questions<?= ($mode === 'normal' && !empty($dragdrop_bonus[$topic_id])) ? ' + drag &amp; drop' : '' ?> · 15 minutes · Answer all then click Submit
                </p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <!-- 15-minute timer -->
                <div id="timerBadge" style="background:#2c3e50;color:white;padding:8px 16px;border-radius:20px;font-size:15px;font-weight:700;font-variant-numeric:tabular-nums;">
                    ⏱️ <span id="timerDisplay">15:00</span>
                </div>
                <span id="answeredBadge" style="background:#eaf4ff;color:#2471a3;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;">
                    0/5 answered
                </span>
            </div>
        </div>

        <form method="POST" id="quizForm">
            <input type="hidden" name="topic_id" value="<?= $topic_id ?>">
            <input type="hidden" name="time_taken" id="timeTaken" value="0">

            <?php foreach ($questions as $i => $q): ?>
            <div class="card" style="margin-bottom:16px;border-left:4px solid #e0e0e0;transition:border-color .2s;" id="card-<?= $q['id'] ?>">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">Question <?= $i+1 ?> of 5</span>
                </div>

                <?php if (!empty($q['image_path'])):
                    $ftype = $q['file_type'] ?? 'image';
                ?>
                    <?php if ($ftype === 'pdf'): ?>
                    <iframe src="<?= htmlspecialchars($q['image_path']) ?>" style="width:100%;height:350px;border:1px solid #eee;border-radius:8px;margin-bottom:12px;display:block;"></iframe>
                    <?php elseif ($ftype === 'video'): ?>
                    <video src="<?= htmlspecialchars($q['image_path']) ?>" controls style="max-width:100%;max-height:300px;border-radius:8px;margin-bottom:12px;display:block;"></video>
                    <?php else: ?>
                    <img src="<?= htmlspecialchars($q['image_path']) ?>"
                         style="max-width:100%;max-height:250px;border-radius:8px;margin-bottom:12px;display:block;object-fit:contain;">
                    <?php endif; ?>
                <?php endif; ?>

                <p style="font-size:16px;font-weight:500;margin-bottom:14px;line-height:1.6;"><?= htmlspecialchars($q['question']) ?></p>

                <input type="hidden" name="qids[]"         value="<?= $q['id'] ?>">
                <input type="hidden" name="correct[]"      value="<?= $q['correct_answer'] ?>">
                <input type="hidden" name="qtexts[]"       value="<?= htmlspecialchars($q['question']) ?>">
                <input type="hidden" name="opta[]"         value="<?= htmlspecialchars($q['option_a']) ?>">
                <input type="hidden" name="optb[]"         value="<?= htmlspecialchars($q['option_b']) ?>">
                <input type="hidden" name="optc[]"         value="<?= htmlspecialchars($q['option_c']) ?>">
                <input type="hidden" name="optd[]"         value="<?= htmlspecialchars($q['option_d']) ?>">
                <input type="hidden" name="explanations[]" value="<?= htmlspecialchars($q['explanation'] ?? '') ?>">
                <input type="hidden" name="images[]"       value="<?= htmlspecialchars($q['image_path'] ?? '') ?>">
                <input type="hidden" name="filetypes[]"    value="<?= htmlspecialchars($q['file_type'] ?? 'image') ?>">

                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach (['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']] as $key=>$val): ?>
                    <label style="display:flex;align-items:center;gap:12px;padding:12px 16px;border:2px solid #e8e8e8;border-radius:8px;cursor:pointer;transition:all .15s;font-size:15px;">
                        <input type="radio" name="ans_<?= $q['id'] ?>" value="<?= $key ?>"
                               onchange="markAnswered('<?= $q['id'] ?>')"
                               style="accent-color:#3498db;width:18px;height:18px;flex-shrink:0;">
                        <span style="width:26px;height:26px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;"><?= $key ?></span>
                        <?= htmlspecialchars($val) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php $bonus_pairs = $dragdrop_bonus[$topic_id] ?? []; ?>
            <?php if ($mode === 'normal' && !empty($bonus_pairs)): ?>
            <input type="hidden" name="has_dragdrop" value="1">
            <div class="card" style="margin-bottom:16px;border-left:4px solid #e67e22;">
                <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
                    <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#e67e22;">🧩 Drag &amp; Drop</span>
                    <span id="ddBonusCounter" style="font-size:12px;color:#888;">0/<?= count($bonus_pairs) ?> placed</span>
                </div>
                <p style="font-size:14px;color:#666;margin-bottom:16px;">Drag each value onto its matching scenario. Worth 1 point per correct pair.</p>

                <div class="dd-board">
                    <div>
                        <div class="dd-col-title">Values</div>
                        <div id="ddChipsBonus">
                            <?php $shuffled_vals = $bonus_pairs; shuffle($shuffled_vals); foreach ($shuffled_vals as $p): ?>
                            <div class="dd-chip" id="bchip-<?= $p['id'] ?>" data-pair="<?= $p['id'] ?>" draggable="true" onclick="bonusSelectChip(this)"><?= htmlspecialchars($p['value']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <div class="dd-col-title">Match to</div>
                        <div id="ddZonesBonus">
                            <?php $shuffled_targets = $bonus_pairs; shuffle($shuffled_targets); foreach ($shuffled_targets as $p): ?>
                            <div class="dd-zone" id="bzone-<?= $p['id'] ?>" data-pair="<?= $p['id'] ?>">
                                <div class="dd-zone-label"><?= htmlspecialchars($p['target']) ?></div>
                                <div class="dd-zone-slot" id="bslot-<?= $p['id'] ?>" onclick="bonusZoneClicked('<?= $p['id'] ?>')">
                                    <span id="bslot-text-<?= $p['id'] ?>">Drop here</span>
                                </div>
                            </div>
                            <input type="hidden" name="dd_pairs[]"          value="<?= $p['id'] ?>">
                            <input type="hidden" name="dd_zone_<?= $p['id'] ?>"  id="dd-input-<?= $p['id'] ?>" value="">
                            <input type="hidden" name="dd_value_<?= $p['id'] ?>" value="<?= htmlspecialchars($p['value']) ?>">
                            <input type="hidden" name="dd_target_<?= $p['id'] ?>" value="<?= htmlspecialchars($p['target']) ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div style="text-align:center;padding:24px 0;">
                <button type="submit" class="btn btn-primary btn-lg" onclick="return confirmSubmit()">
                    Submit Quiz
                </button>
                <p style="color:#999;font-size:13px;margin-top:10px;">Quiz auto-submits when time runs out</p>
            </div>
        </form>
    </div>

<?php else: ?>

    <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">Choose a topic:</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
        <?php foreach ($topics as $tp):
            // Count failed questions per topic for this user
            $fq = $conn->prepare("SELECT COUNT(*) AS cnt FROM failed_questions WHERE user_id = ? AND topic_id = ?");
            $fq->bind_param("ii", $user_id, $tp['id']);
            $fq->execute();
            $fc = $fq->get_result()->fetch_assoc()['cnt'];
        ?>
        <div class="card" style="padding:28px;transition:transform .2s;"
             onmouseover="this.style.transform='translateY(-4px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size:2.5rem;margin-bottom:12px;"><?= $tp['icon'] ?></div>
            <h3 style="font-size:17px;margin-bottom:6px;"><?= htmlspecialchars($tp['name']) ?></h3>
            <p style="color:#888;font-size:13px;margin-bottom:16px;"><?= htmlspecialchars($tp['description']) ?></p>

            <?php if ($fc > 0): ?>
            <div style="background:#fdecea;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:13px;color:#c0392b;">
                ❌ <?= $fc ?> failed question<?= $fc > 1 ? 's' : '' ?> to retry
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="/testmate/quiz.php?topic=<?= $tp['id'] ?>" class="btn btn-primary" style="font-size:14px;padding:8px 18px;">Start Quiz →</a>
                <?php if ($fc > 0): ?>
                <a href="/testmate/quiz.php?topic=<?= $tp['id'] ?>&mode=retry" class="btn btn-outline" style="font-size:14px;padding:8px 18px;border-color:#e74c3c;color:#e74c3c;">❌ Retry</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

</div>

<!-- Floating counter -->
<?php if ($topic_id > 0 && !empty($questions) && !isset($_GET['results'])): ?>
<div style="position:fixed;bottom:24px;right:24px;background:#2c3e50;color:white;padding:14px 18px;border-radius:12px;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.2);z-index:99;">
    <span style="font-size:24px;font-weight:800;color:#2ecc71;display:block;" id="floatCount">0</span>
    <span style="font-size:12px;opacity:.7;">of 5 answered</span>
</div>
<?php endif; ?>

<script>
// 15-minute timer
const TOTAL = 15 * 60;
let secondsLeft = TOTAL;
const startTime = Date.now();

function tick() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    secondsLeft   = Math.max(0, TOTAL - elapsed);
    const m = Math.floor(secondsLeft / 60);
    const s = secondsLeft % 60;
    const display = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    const el = document.getElementById('timerDisplay');
    if (el) el.textContent = display;

    const badge = document.getElementById('timerBadge');
    if (badge) {
        if (secondsLeft <= 60) badge.style.background = '#e74c3c';
        else if (secondsLeft <= 300) badge.style.background = '#e67e22';
    }

    const ti = document.getElementById('timeTaken');
    if (ti) ti.value = TOTAL - secondsLeft;

    if (secondsLeft <= 0) {
        const f = document.getElementById('quizForm');
        if (f) f.submit();
        return;
    }
}

if (document.getElementById('timerDisplay')) {
    setInterval(tick, 500);
    tick();
}

const answered = new Set();
function markAnswered(qid) {
    answered.add(qid);
    const n = answered.size;
    const fc = document.getElementById('floatCount');
    if (fc) fc.textContent = n;
    const ab = document.getElementById('answeredBadge');
    if (ab) ab.textContent = n + '/5 answered';
    const card = document.getElementById('card-' + qid);
    if (card) card.style.borderLeftColor = '#27ae60';
}

function confirmSubmit() {
    const left = 5 - answered.size;
    const ddLeft = TOTAL_BONUS_PAIRS - Object.keys(bonusPlacements).length;

    if (left > 0 || ddLeft > 0) {
        let msg = [];
        if (left > 0) msg.push(left + ' quiz question(s) unanswered');
        if (ddLeft > 0) msg.push(ddLeft + ' drag & drop pair(s) not placed');
        return confirm(msg.join(' and ') + '.\nUnanswered items will be marked wrong.\n\nSubmit anyway?');
    }
    return true;
}

// ── Bonus drag-and-drop round ──
const bonusPlacements = {};
let bonusSelectedChip = null;
const TOTAL_BONUS_PAIRS = document.querySelectorAll('#ddZonesBonus .dd-zone').length;

document.querySelectorAll('#ddChipsBonus .dd-chip').forEach(chip => {
    chip.addEventListener('dragstart', e => {
        if (chip.classList.contains('placed')) { e.preventDefault(); return; }
        e.dataTransfer.setData('text/plain', chip.dataset.pair);
        chip.classList.add('dragging');
    });
    chip.addEventListener('dragend', () => chip.classList.remove('dragging'));
});

document.querySelectorAll('#ddZonesBonus .dd-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const pairId = e.dataTransfer.getData('text/plain');
        bonusPlaceChip(pairId, zone.dataset.pair);
    });
});

function bonusSelectChip(chipEl) {
    if (chipEl.classList.contains('placed')) return;
    document.querySelectorAll('#ddChipsBonus .dd-chip.selected').forEach(c => c.classList.remove('selected'));
    if (bonusSelectedChip === chipEl.dataset.pair) { bonusSelectedChip = null; return; }
    chipEl.classList.add('selected');
    bonusSelectedChip = chipEl.dataset.pair;
}

function bonusZoneClicked(zoneId) {
    if (bonusSelectedChip) {
        bonusPlaceChip(bonusSelectedChip, zoneId);
        bonusSelectedChip = null;
        document.querySelectorAll('#ddChipsBonus .dd-chip.selected').forEach(c => c.classList.remove('selected'));
    } else if (bonusPlacements[zoneId]) {
        bonusRemoveFromZone(zoneId);
    }
}

function bonusPlaceChip(pairId, zoneId) {
    if (bonusPlacements[zoneId]) bonusRemoveFromZone(zoneId, false);
    for (const [zId, pId] of Object.entries(bonusPlacements)) {
        if (pId === pairId) bonusRemoveFromZone(zId, false);
    }
    bonusPlacements[zoneId] = pairId;

    const chip = document.getElementById('bchip-' + pairId);
    const slotText = document.getElementById('bslot-text-' + zoneId);
    const slot = document.getElementById('bslot-' + zoneId);
    const input = document.getElementById('dd-input-' + zoneId);

    if (chip) chip.classList.add('placed');
    if (slot) slot.classList.add('filled');
    if (slotText) slotText.textContent = chip ? chip.textContent : '';
    if (input) input.value = pairId;

    updateBonusCounter();
}

function bonusRemoveFromZone(zoneId, updateUi = true) {
    const pairId = bonusPlacements[zoneId];
    if (!pairId) return;
    delete bonusPlacements[zoneId];

    const chip = document.getElementById('bchip-' + pairId);
    const slotText = document.getElementById('bslot-text-' + zoneId);
    const slot = document.getElementById('bslot-' + zoneId);
    const input = document.getElementById('dd-input-' + zoneId);

    if (chip) chip.classList.remove('placed');
    if (slot) slot.classList.remove('filled');
    if (slotText) slotText.textContent = 'Drop here';
    if (input) input.value = '';

    if (updateUi) updateBonusCounter();
}

function updateBonusCounter() {
    const n = Object.keys(bonusPlacements).length;
    const el = document.getElementById('ddBonusCounter');
    if (el) el.textContent = n + '/' + TOTAL_BONUS_PAIRS + ' placed';
}
</script>

<?php include 'includes/footer.php'; ?>