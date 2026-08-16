<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];

$result    = $conn->query("SELECT q.*, t.name AS topic_name FROM questions q JOIN topics t ON q.topic_id = t.id ORDER BY RAND() LIMIT 50");
$questions = $result->fetch_all(MYSQLI_ASSOC);
$total_q   = count($questions);
$pass_mark = ceil($total_q * 0.8);
?>
<?php include 'includes/header.php'; ?>

<style>
/* ===== START SCREEN ===== */
.start-screen {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 120px);
    padding: 40px 20px;
}
.start-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    max-width: 520px;
    width: 100%;
    box-shadow: 0 8px 32px rgba(0,0,0,.1);
    text-align: center;
}
.start-card h1 {
    font-size: 28px;
    color: #2c3e50;
    margin-bottom: 8px;
}
.start-card .subtitle {
    color: #888;
    font-size: 15px;
    margin-bottom: 28px;
}
.start-stats {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-bottom: 28px;
}
.stat-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px 20px;
    min-width: 100px;
}
.stat-box .num {
    font-size: 28px;
    font-weight: 800;
    color: #3498db;
    display: block;
}
.stat-box .lbl {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.start-rules {
    text-align: left;
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 28px;
}
.start-rules h3 {
    font-size: 14px;
    text-transform: uppercase;
    color: #666;
    margin-bottom: 12px;
    letter-spacing: .5px;
}
.start-rules ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.start-rules li {
    padding: 6px 0;
    font-size: 14px;
    color: #555;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.start-rules li::before {
    content: "\2713";
    color: #27ae60;
    font-weight: bold;
    flex-shrink: 0;
}
.btn-start {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border: none;
    padding: 16px 48px;
    font-size: 18px;
    font-weight: 700;
    border-radius: 50px;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(46, 204, 113, .35);
    transition: transform .15s, box-shadow .15s;
}
.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 204, 113, .45);
}

/* ===== TEST SCREEN (hidden initially) ===== */
#testScreen { display: none; }

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

<!-- ===== START SCREEN ===== -->
<div id="startScreen" class="start-screen">
    <div class="start-card">
        <div style="font-size:48px;margin-bottom:12px;">&#128221;</div>
        <h1>Full Practice Test</h1>
        <p class="subtitle">Ready to test your knowledge?</p>

        <div class="start-stats">
            <div class="stat-box">
                <span class="num"><?php echo $total_q; ?></span>
                <span class="lbl">Questions</span>
            </div>
            <div class="stat-box">
                <span class="num">60</span>
                <span class="lbl">Minutes</span>
            </div>
            <div class="stat-box">
                <span class="num"><?php echo $pass_mark; ?></span>
                <span class="lbl">To Pass</span>
            </div>
        </div>

        <div class="start-rules">
            <h3>Before You Start</h3>
            <ul>
                <li>Once you start, the timer cannot be paused.</li>
                <li>Answer all <?php echo $total_q; ?> questions before time runs out.</li>
                <li>You need <?php echo $pass_mark; ?> correct answers to pass (80%).</li>
                <li>Unanswered questions will be marked wrong.</li>
                <li>The test auto-submits when the timer reaches zero.</li>
            </ul>
        </div>

        <button class="btn-start" onclick="startTest()">Start Test Now</button>
        <p style="color:#bbb;font-size:12px;margin-top:14px;">Click the button above when you are ready.</p>
    </div>
</div>

<!-- ===== TEST SCREEN ===== -->
<div id="testScreen">
    <div class="timer-bar">
        <div class="timer-display" id="timerEl">&#9201; <span id="timeDisplay">60:00</span></div>
        <div class="timer-info">
            Answered: <strong id="answeredCount">0</strong> / <?php echo $total_q; ?>
            &nbsp;|&nbsp; Pass: <strong><?php echo $pass_mark; ?>/<?php echo $total_q; ?></strong>
        </div>
    </div>

    <div class="container" style="max-width:800px;">
        <div style="text-align:center;margin-bottom:28px;">
            <h1 style="font-size:26px;margin-bottom:6px;">Full Practice Test</h1>
            <p style="color:#666;">Answer all <?php echo $total_q; ?> questions before time runs out. Auto-submits when timer ends.</p>
        </div>

        <form method="POST" action="/testmate/mock-result.php" id="mockForm">
            <input type="hidden" name="time_taken"      id="timeTaken" value="0">
            <input type="hidden" name="total_questions" value="<?php echo $total_q; ?>">

            <?php foreach ($questions as $i => $q): ?>
            <div class="q-card" id="card-<?php echo $q['id']; ?>">
                <div class="q-meta">
                    <span class="q-num">Question <?php echo $i+1; ?> of <?php echo $total_q; ?></span>
                    <span class="q-cat"><?php echo htmlspecialchars($q['topic_name']); ?></span>
                </div>

                <?php if (!empty($q['image_path'])):
                    $ftype = $q['file_type'] ?? 'image';
                ?>
                    <?php if ($ftype === 'pdf'): ?>
                    <iframe src="<?php echo htmlspecialchars($q['image_path']); ?>" style="width:100%;height:300px;border:1px solid #eee;border-radius:8px;margin-bottom:14px;"></iframe>
                    <?php elseif ($ftype === 'video'): ?>
                    <video src="<?php echo htmlspecialchars($q['image_path']); ?>" controls class="q-image"></video>
                    <?php else: ?>
                    <img src="<?php echo htmlspecialchars($q['image_path']); ?>" class="q-image" alt="Question image">
                    <?php endif; ?>
                <?php endif; ?>

                <p class="q-text"><?php echo htmlspecialchars($q['question']); ?></p>

                <input type="hidden" name="qids[]"    value="<?php echo $q['id']; ?>">
                <input type="hidden" name="correct[]" value="<?php echo $q['correct_answer']; ?>">
                <input type="hidden" name="qtexts[]"  value="<?php echo htmlspecialchars($q['question']); ?>">
                <input type="hidden" name="opta[]"    value="<?php echo htmlspecialchars($q['option_a']); ?>">
                <input type="hidden" name="optb[]"    value="<?php echo htmlspecialchars($q['option_b']); ?>">
                <input type="hidden" name="optc[]"    value="<?php echo htmlspecialchars($q['option_c']); ?>">
                <input type="hidden" name="optd[]"    value="<?php echo htmlspecialchars($q['option_d']); ?>">
                <input type="hidden" name="topic_ids[]" value="<?php echo (int)$q['topic_id']; ?>">
                <input type="hidden" name="topic_names[]" value="<?php echo htmlspecialchars($q['topic_name']); ?>">

                <div class="options">
                    <?php foreach (['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']] as $key=>$val): ?>
                    <label class="opt-label">
                        <input type="radio" name="ans_<?php echo $q['id']; ?>" value="<?php echo $key; ?>"
                               onchange="markAnswered(<?php echo $q['id']; ?>)">
                        <span class="opt-key"><?php echo $key; ?></span>
                        <?php echo htmlspecialchars($val); ?>
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
        <small style="font-size:12px;opacity:.7;">of <?php echo $total_q; ?> answered</small>
    </div>
</div>

<script>
let timerInterval = null;
let startTime = null;
const TOTAL = 3600;
let secondsLeft = TOTAL;

function startTest() {
    document.getElementById('startScreen').style.display = 'none';
    document.getElementById('testScreen').style.display = 'block';
    startTime = Date.now();
    timerInterval = setInterval(tick, 500);
    tick();
    window.scrollTo(0, 0);
}

function tick() {
    if (!startTime) return;
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

function autoSubmit() {
    if (timerInterval) clearInterval(timerInterval);
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
    const total = <?php echo $total_q; ?>;
    const left  = total - answered.size;
    if (left > 0) return confirm(left + ' question(s) not answered.\nUnanswered will be marked wrong.\n\nSubmit anyway?');
    return true;
}
</script>

<?php include 'includes/footer.php'; ?>