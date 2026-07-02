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
        <p style="color:#666;margin-bottom:24px;line-height:1.6;">Administrators cannot take quizzes.<br>Please login as a Learner to practice.</p>
        <a href="/testmate/admin/index.php" class="btn btn-primary">Go to Admin Dashboard</a>
    </div>';
    include 'includes/footer.php';
    exit();
}

$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$questions = [];
$topic     = null;

if ($topic_id > 0) {
    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $topic_id);
    $t->execute();
    $topic = $t->get_result()->fetch_assoc();

    $q = $conn->prepare("SELECT * FROM questions WHERE topic_id = ? ORDER BY RAND() LIMIT 10");
    $q->bind_param("i", $topic_id);
    $q->execute();
    $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic_id = (int)$_POST['topic_id'];
    $qids     = $_POST['qids'];
    $corrects = $_POST['correct'];
    $total    = count($qids);
    $score    = 0;
    $results  = [];

    for ($i = 0; $i < $total; $i++) {
        $qid         = $qids[$i];
        $user_answer = strtoupper(trim($_POST['ans_' . $qid] ?? ''));
        $right       = strtoupper(trim($corrects[$i]));
        $is_correct  = ($user_answer === $right);
        if ($is_correct) $score++;
        $results[] = [
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
        ];
    }

    $save = $conn->prepare("INSERT INTO quiz_scores (user_id, topic_id, score, total) VALUES (?, ?, ?, ?)");
    $save->bind_param("iiii", $user_id, $topic_id, $score, $total);
    $save->execute();

    $percentage = round($score / $total * 100);
    $_SESSION['quiz_results']  = $results;
    $_SESSION['quiz_score']    = $score;
    $_SESSION['quiz_total']    = $total;
    $_SESSION['quiz_topic_id'] = $topic_id;
    $_SESSION['quiz_pct']      = $percentage;

    header("Location: /testmate/quiz.php?results=1&topic=" . $topic_id);
    exit();
}
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>✅ Topic Quizzes</h1>
    <p>Test yourself on one topic at a time and get instant feedback</p>
</div>

<div class="container">

<?php if (isset($_GET['results']) && isset($_SESSION['quiz_results'])):
    $results    = $_SESSION['quiz_results'];
    $score      = $_SESSION['quiz_score'];
    $total      = $_SESSION['quiz_total'];
    $percentage = $_SESSION['quiz_pct'];
    $tid        = $_SESSION['quiz_topic_id'];

    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $tid);
    $t->execute();
    $rtopic = $t->get_result()->fetch_assoc();

    unset($_SESSION['quiz_results'], $_SESSION['quiz_score'],
          $_SESSION['quiz_total'], $_SESSION['quiz_topic_id'], $_SESSION['quiz_pct']);
?>

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

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;">
        <a href="/testmate/quiz.php?topic=<?= $tid ?>" class="btn btn-primary">🔄 Retry Quiz</a>
        <a href="/testmate/quiz.php" class="btn btn-outline">📋 All Topics</a>
        <a href="/testmate/study-materials.php?topic=<?= $tid ?>" class="btn btn-outline">📖 Study This Topic</a>
        <a href="/testmate/dashboard.php" class="btn btn-outline">🏠 Dashboard</a>
    </div>

    <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">📋 Answer Review</h2>

    <?php foreach ($results as $i => $r):
        $opts = ['A'=>$r['option_a'],'B'=>$r['option_b'],'C'=>$r['option_c'],'D'=>$r['option_d']];
    ?>
    <div class="card" style="margin-bottom:14px;border-left:4px solid <?= $r['is_correct'] ? '#27ae60' : '#e74c3c' ?>;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">Question <?= $i+1 ?></span>
            <?php if ($r['is_correct']): ?>
                <span class="badge badge-pass">✅ Correct</span>
            <?php else: ?>
                <span class="badge badge-fail">❌ Wrong</span>
            <?php endif; ?>
        </div>

        <?php if ($r['image_path']): ?>
        <img src="<?= htmlspecialchars($r['image_path']) ?>"
             style="max-width:100%;max-height:200px;border-radius:8px;margin-bottom:12px;display:block;">
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
            💡 <?= htmlspecialchars($r['explanation']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

<?php elseif ($topic_id > 0 && !empty($questions)): ?>

    <div style="max-width:750px;margin:0 auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-size:22px;"><?= $topic['icon'] ?> <?= htmlspecialchars($topic['name']) ?></h2>
                <p style="color:#888;font-size:14px;">10 questions — answer all then click Submit</p>
            </div>
            <span id="answeredBadge" style="background:#eaf4ff;color:#2471a3;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;">
                0/10 answered
            </span>
        </div>

        <form method="POST" id="quizForm">
            <input type="hidden" name="topic_id" value="<?= $topic_id ?>">

            <?php foreach ($questions as $i => $q): ?>
            <div class="card" style="margin-bottom:16px;border-left:4px solid #e0e0e0;transition:border-color .2s;" id="card-<?= $q['id'] ?>">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
                    <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">Question <?= $i+1 ?> of 10</span>
                </div>

                <?php if (!empty($q['image_path'])): ?>
                <img src="<?= htmlspecialchars($q['image_path']) ?>"
                     style="max-width:100%;max-height:250px;border-radius:8px;margin-bottom:12px;display:block;object-fit:contain;">
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

            <div style="text-align:center;padding:24px 0;">
                <button type="submit" class="btn btn-primary btn-lg" onclick="return confirmSubmit()">
                    Submit Quiz
                </button>
                <p style="color:#999;font-size:13px;margin-top:10px;">Make sure you answered all 10 questions</p>
            </div>
        </form>
    </div>

<?php else: ?>

    <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;">Choose a topic:</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
        <?php foreach ($topics as $topic): ?>
        <a href="/testmate/quiz.php?topic=<?= $topic['id'] ?>" style="text-decoration:none;">
            <div class="card" style="padding:28px;transition:transform .2s;cursor:pointer;"
                 onmouseover="this.style.transform='translateY(-4px)'"
                 onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size:2.5rem;margin-bottom:12px;"><?= $topic['icon'] ?></div>
                <h3 style="font-size:17px;margin-bottom:6px;"><?= htmlspecialchars($topic['name']) ?></h3>
                <p style="color:#888;font-size:13px;margin-bottom:16px;"><?= htmlspecialchars($topic['description']) ?></p>
                <span class="btn btn-primary" style="font-size:14px;padding:8px 18px;">Start Quiz →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

</div>

<?php if ($topic_id > 0 && !empty($questions) && !isset($_GET['results'])): ?>
<div style="position:fixed;bottom:24px;right:24px;background:#2c3e50;color:white;padding:14px 18px;border-radius:12px;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.2);z-index:99;">
    <span style="font-size:24px;font-weight:800;color:#2ecc71;display:block;" id="floatCount">0</span>
    <span style="font-size:12px;opacity:.7;">of 10 answered</span>
</div>
<?php endif; ?>

<script>
const answered = new Set();
function markAnswered(qid) {
    answered.add(qid);
    const n = answered.size;
    const fc = document.getElementById('floatCount');
    if (fc) fc.textContent = n;
    const ab = document.getElementById('answeredBadge');
    if (ab) ab.textContent = n + '/10 answered';
    const card = document.getElementById('card-' + qid);
    if (card) card.style.borderLeftColor = '#27ae60';
}
function confirmSubmit() {
    const left = 10 - answered.size;
    if (left > 0) return confirm(left + ' question(s) unanswered.\nUnanswered will be marked wrong.\n\nSubmit anyway?');
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>