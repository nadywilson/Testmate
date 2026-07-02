<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

// Block admins
if ($_SESSION['role'] === 'admin') {
    include 'includes/header.php';
    echo '<div style="max-width:560px;margin:80px auto;text-align:center;">
        <div style="font-size:4rem;margin-bottom:16px;">🔐</div>
        <h2 style="font-size:22px;margin-bottom:10px;color:#2c3e50;">Administrator Account</h2>
        <p style="color:#666;margin-bottom:24px;line-height:1.6;">Administrators cannot take practice tests.<br>Please login as a Learner to practice.</p>
        <a href="/testmate/admin/index.php" class="btn btn-primary">Go to Admin Dashboard</a>
    </div>';
    include 'includes/footer.php';
    exit();
}

$result    = $conn->query("SELECT q.*, t.name AS topic_name FROM questions q JOIN topics t ON q.topic_id = t.id ORDER BY RAND() LIMIT 50");
$questions = $result->fetch_all(MYSQLI_ASSOC);
?>
<?php include 'includes/header.php'; ?>

<style>
.timer-bar { background:#1a252f; padding:12px 30px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:60px; z-index:99; }
.timer-display { font-size:26px; font-weight:800; color:#2ecc71; font-variant-numeric:tabular-nums; display:flex; align-items:center; gap:8px; }
.timer-display.warning { color:#f39c12; }
.timer-display.danger  { color:#e74c3c; animation:pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.timer-info { color:rgba(255,255,255,.7); font-size:14px; }
.timer-info strong { color:white; }
.q-card { background:white; border-radius:12px; padding:24px; margin-bottom:18px; box-shadow:0 2px 8px rgba(0,0,0,.06); border-left:4px solid #e0e0e0; transition:border-color .2s; }
.q-card.answered { border-left-color:#27ae60; }
.q-meta { display:flex; justify-content:space-between; margin-bottom:10px; }
.q-num  { font-size:12px; font-weight:700; text-transform:uppercase; color:#999; }
.q-cat  { font-size:11px; background:#f0f0f0; color:#666; padding:3px 10px; border-radius:20px; }
.q-text { font-size:16px; font-weight:500; color:#2c3e50; line-height:1.6; margin-bottom:14px; }
.options { display:flex; flex-direction:column; gap:8px; }
.opt-label { display:flex; align-items:center; gap:12px; padding:11px 16px; border:2px solid #e8e8e8; border-radius:8px; cursor:pointer; font-size:15px; transition:all .15s; }
.opt-label:hover { border-color:#3498db; background:#f0f8ff; }
.opt-label:has(input:checked) { border-color:#27ae60; background:#eafaf1; font-weight:500; }
.opt-label input { accent-color:#3498db; width:18px; height:18px; flex-shrink:0; }
.opt-key { width:26px; height:26px; border-radius:50%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#555; flex-shrink:0; }
.float-counter { position:fixed; bottom:24px; right:24px; background:#2c3e50; color:white; padding:14px 18px; border-radius:12px; text-align:center; box-shadow:0 4px 16px rgba(0,0,0,.2); z-index:99; }
.float-counter .big { font-size:24px; font-weight:800; color:#2ecc71; display:block; }
.q-image { max-width:100%; max-height:250px; border-radius:8px; margin-bottom:14px; display:block; object-fit:contain; }
</style>

<div class="timer-bar">
    <div class="timer-display" id="timerEl">⏱️ <span id="timeDisplay">60:00</span></div>
    <div class="timer-info">
        Answered: <strong id="answeredCount">0</strong> / <?= count($questions) ?>
        &nbsp;|&nbsp; Pass: <strong><?= ceil(count($questions) * 0.8) ?>/<?= count($questions) ?></strong>
    </div>
</div>

<div class="container" style="max-width:800px;">
    <div style="text-align:center;margin-bottom:28px;">
        <h1 style="font-size:26px;margin-bottom:6px;">Full Practice Test</h1>
        <p style="color:#666;">Answer all <?= count($questions) ?> questions before time runs out. Auto-submits when timer ends.</p>
    </div>

    <form method="POST" action="/testmate/mock-result.php" id="mockForm">
        <input type="hidden" name="time_taken"      id="timeTaken" value="0">
        <input type="hidden" name="total_questions" value="<?= count($questions) ?>">

        <?php foreach ($questions as $i => $q): ?>
        <div class="q-card" id="card-<?= $q['id'] ?>">
            <div class="q-meta">
                <span class="q-num">Question <?= $i+1 ?> of <?= count($questions) ?></span>
                <span class="q-cat"><?= htmlspecialchars($q['topic_name']) ?></span>
            </div>

            <?php if (!empty($q['image_path'])): ?>
            <img src="<?= htmlspecialchars($q['image_path']) ?>" class="q-image" alt="Question image">
            <?php endif; ?>

            <p class="q-text"><?= htmlspecialchars($q['question']) ?></p>

            <input type="hidden" name="qids[]"    value="<?= $q['id'] ?>">
            <input type="hidden" name="correct[]" value="<?= $q['correct_answer'] ?>">
            <input type="hidden" name="qtexts[]"  value="<?= htmlspecialchars($q['question']) ?>">
            <input type="hidden" name="opta[]"    value="<?= htmlspecialchars($q['option_a']) ?>">
            <input type="hidden" name="optb[]"    value="<?= htmlspecialchars($q['option_b']) ?>">
            <input type="hidden" name="optc[]"    value="<?= htmlspecialchars($q['option_c']) ?>">
            <input type="hidden" name="optd[]"    value="<?= htmlspecialchars($q['option_d']) ?>">

            <div class="options">
                <?php foreach (['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']] as $key=>$val): ?>
                <label class="opt-label">
                    <input type="radio" name="ans_<?= $q['id'] ?>" value="<?= $key ?>"
                           onchange="markAnswered(<?= $q['id'] ?>)">
                    <span class="opt-key"><?= $key ?></span>
                    <?= htmlspecialchars($val) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="text-align:center;padding:30px 0 60px;">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirmSubmit()">Submit Test</button>
            <p style="color:#999;font-size:13px;margin-top:10px;">Unanswered questions will be marked wrong.</p>
        </div>
    </form>
</div>

<div class="float-counter">
    <span class="big" id="floatCount">0</span>
    <small style="font-size:12px;opacity:.7;">of <?= count($questions) ?> answered</small>
</div>

<script>
const TOTAL = 3600;
let secondsLeft = TOTAL;
const startTime = Date.now();

function tick() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    secondsLeft   = Math.max(0, TOTAL - elapsed);
    const m = Math.floor(secondsLeft / 60);
    const s = secondsLeft % 60;
    document.getElementById('timeDisplay').textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    document.getElementById('timeTaken').value = TOTAL - secondsLeft;
    const el = document.getElementById('timerEl');
    el.className = 'timer-display';
    if (secondsLeft <= 300) el.classList.add('danger');
    else if (secondsLeft <= 600) el.classList.add('warning');
    if (secondsLeft <= 0) { autoSubmit(); return; }
}
setInterval(tick, 500);
tick();

function autoSubmit() {
    const f = document.getElementById('mockForm');
    if (!f) return;
    const h = document.createElement('input');
    h.type = 'hidden'; h.name = 'auto_submitted'; h.value = '1';
    f.appendChild(h);
    f.submit();
}

const answered = new Set();
function markAnswered(qid) {
    answered.add(qid);
    const n = answered.size;
    document.getElementById('answeredCount').textContent = n;
    document.getElementById('floatCount').textContent    = n;
    const card = document.getElementById('card-' + qid);
    if (card) card.classList.add('answered');
}
function confirmSubmit() {
    const total = <?= count($questions) ?>;
    const left  = total - answered.size;
    if (left > 0) return confirm(left + ' question(s) not answered.\nUnanswered will be marked wrong.\n\nSubmit anyway?');
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>