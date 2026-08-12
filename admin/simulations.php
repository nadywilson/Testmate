<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id  = $_SESSION['user_id'];
$sim_id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$mode     = isset($_GET['mode']) ? $_GET['mode']       : '';

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_simulation'])) {
    $sim_id   = (int)$_POST['simulation_id'];
    $answers  = $_POST['answers'] ?? [];
    $questions_data = json_decode($_POST['questions_data'], true);
    $score    = 0;
    $total    = count($questions_data);
    $results  = [];

    foreach ($questions_data as $i => $q) {
        $user_answer = $answers[$i] ?? '';
        $is_correct  = ($user_answer === $q['correct_answer']);
        if ($is_correct) $score++;
        $results[] = [
            'question'    => $q['question'],
            'user_answer' => $user_answer,
            'correct'     => $q['correct_answer'],
            'is_correct'  => $is_correct,
            'explanation' => $q['explanation'],
            'scenario_index' => $q['scenario_index'],
        ];
    }

    // Save result
    $ins = $conn->prepare("INSERT INTO simulation_results (user_id, simulation_id, mode, score, total, completed, completed_at) VALUES (?,?,'quiz',?,?,1,NOW())");
    $ins->bind_param("iiii", $user_id, $sim_id, $score, $total);
    $ins->execute();

    // Get simulation info
    $s = $conn->prepare("SELECT * FROM simulations WHERE id=?");
    $s->bind_param("i", $sim_id);
    $s->execute();
    $simulation = $s->get_result()->fetch_assoc();
    $scenarios  = json_decode($simulation['scenario_data'], true);

    include 'includes/header.php';
    ?>
    <div class="page-header">
        <h1>Quiz Results</h1>
        <p><?= htmlspecialchars($simulation['title']) ?></p>
    </div>
    <div class="container" style="max-width:800px;">
        <!-- Score Banner -->
        <?php $pct = round($score/$total*100); ?>
        <div style="border-radius:14px;padding:36px;text-align:center;color:white;margin-bottom:28px;
            background:<?= $pct>=80?'linear-gradient(135deg,#27ae60,#2ecc71)':'linear-gradient(135deg,#c0392b,#e74c3c)' ?>">
            <div style="font-size:56px;font-weight:800;"><?= $score ?>/<?= $total ?></div>
            <div style="font-size:24px;margin:8px 0;"><?= $pct ?>%</div>
            <p style="opacity:.9;"><?= $pct>=80?'Excellent! You understand the rules well.':'Keep studying and try again!' ?></p>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:28px;">
            <a href="/testmate/simulations.php?id=<?= $sim_id ?>&mode=quiz" class="btn btn-primary">Try Again</a>
            <a href="/testmate/simulations.php?id=<?= $sim_id ?>&mode=study" class="btn btn-outline">Study Mode</a>
            <a href="/testmate/simulations.php" class="btn btn-outline">All Simulations</a>
        </div>

        <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">Answer Review</h2>

        <?php foreach ($results as $i => $r):
            $scenario = $scenarios[$r['scenario_index']] ?? [];
        ?>
        <div class="card" style="margin-bottom:16px;border-left:4px solid <?= $r['is_correct']?'#27ae60':'#e74c3c' ?>;">
            <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">Question <?= $i+1 ?></span>
                <span class="badge <?= $r['is_correct']?'badge-pass':'badge-fail' ?>"><?= $r['is_correct']?'Correct':'Wrong' ?></span>
            </div>

            <!-- Mini animation replay -->
            <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin-bottom:14px;text-align:center;">
                <?php if ($simulation['animation_type'] === 'traffic_light' && $scenario): ?>
                <div style="display:inline-flex;align-items:center;gap:16px;font-size:14px;">
                    <span style="font-size:24px;">🚗</span>
                    <span style="font-size:13px;color:#666;">approaches</span>
                    <div style="background:#333;border-radius:6px;padding:4px 8px;display:inline-flex;flex-direction:column;gap:3px;">
                        <div style="width:20px;height:20px;border-radius:50%;background:<?= $scenario['light']==='red'?'#e74c3c':'#333' ?>;margin:0 auto;"></div>
                        <div style="width:20px;height:20px;border-radius:50%;background:<?= $scenario['light']==='amber'?'#f1c40f':'#333' ?>;margin:0 auto;"></div>
                        <div style="width:20px;height:20px;border-radius:50%;background:<?= $scenario['light']==='green'?'#2ecc71':'#333' ?>;margin:0 auto;"></div>
                    </div>
                    <span style="font-size:13px;color:#666;"><?= $scenario['car_passes']?'drives through':'stops' ?></span>
                    <span style="font-size:20px;"><?= $scenario['is_correct']?'✅':'❌' ?></span>
                </div>
                <?php elseif ($scenario): ?>
                <div style="display:inline-flex;align-items:center;gap:14px;font-size:14px;color:#555;">
                    <span style="font-size:24px;">🚗</span>
                    <span>
                        <?php if (isset($scenario['location'])): ?>
                            Parked: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$scenario['location']))) ?>
                        <?php elseif (isset($scenario['speed'])): ?>
                            Speed: <?= (int)$scenario['speed'] ?> km/h (limit <?= (int)$scenario['limit'] ?> km/h)
                        <?php elseif (isset($scenario['arriving_order'])): ?>
                            Arrived <?= htmlspecialchars($scenario['arriving_order']) ?> — <?= htmlspecialchars($scenario['action']) ?>
                        <?php elseif (isset($scenario['label'])): ?>
                            <?= htmlspecialchars($scenario['label']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars(ucfirst($scenario['sign'] ?? '')) ?> sign
                        <?php endif; ?>
                    </span>
                    <span style="font-size:20px;"><?= $scenario['is_correct']?'✅':'❌' ?></span>
                </div>
                <?php endif; ?>
            </div>

            <p style="font-weight:500;margin-bottom:12px;"><?= htmlspecialchars($r['question']) ?></p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <?php foreach (['correct'=>'✅ Correct','incorrect'=>'❌ Incorrect'] as $val=>$label):
                    $is_user    = ($r['user_answer'] === $val);
                    $is_correct = ($r['correct'] === $val);
                    $bg = '#f8f9fa';
                    if ($is_correct) $bg = '#eafaf1';
                    elseif ($is_user && !$is_correct) $bg = '#fdecea';
                ?>
                <div style="background:<?= $bg ?>;padding:12px 16px;border-radius:8px;font-size:15px;font-weight:500;text-align:center;border:2px solid <?= $is_correct?'#27ae60':($is_user&&!$is_correct?'#e74c3c':'#eee') ?>;">
                    <?= $label ?>
                    <?php if ($is_correct): ?> — Correct<?php endif; ?>
                    <?php if ($is_user && !$is_correct): ?> — Your Answer<?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="background:#f0f8ff;border-left:3px solid #3498db;padding:10px 14px;border-radius:0 6px 6px 0;font-size:13px;color:#555;">
                <?= htmlspecialchars($r['explanation']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

// Get all simulations list
if (!$sim_id) {
    $sims = $conn->query("
        SELECT s.*, t.name AS topic_name,
               COUNT(DISTINCT sq.id) AS question_count
        FROM simulations s
        LEFT JOIN topics t ON s.topic_id = t.id
        LEFT JOIN simulation_questions sq ON s.id = sq.simulation_id
        WHERE s.is_active = 1
        GROUP BY s.id
        ORDER BY s.id
    ")->fetch_all(MYSQLI_ASSOC);

    include 'includes/header.php';
    ?>
    <div class="page-header">
        <h1>Video Simulations</h1>
        <p>Watch animated traffic scenarios and test your knowledge</p>
    </div>
    <div class="container">
        <?php if (empty($sims)): ?>
        <div class="card" style="text-align:center;padding:60px;color:#888;">
            <h2 style="margin-bottom:12px;">No simulations available yet.</h2>
            <p>Check back soon!</p>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;">
            <?php foreach ($sims as $sim): ?>
            <div class="card" style="padding:0;overflow:hidden;">
                <!-- Animated Preview Header -->
                <div style="background:linear-gradient(135deg,#2c3e50,#3498db);padding:24px 20px;position:relative;overflow:hidden;min-height:120px;display:flex;align-items:center;justify-content:center;">
                    <?php if ($sim['animation_type'] === 'traffic_light'): ?>
                    <div style="text-align:center;">
                        <div style="display:inline-flex;align-items:center;gap:12px;">
                            <span style="font-size:32px;">🚗</span>
                            <div style="background:#222;border-radius:8px;padding:6px 10px;">
                                <div style="width:24px;height:24px;border-radius:50%;background:#e74c3c;margin-bottom:4px;box-shadow:0 0 8px #e74c3c;"></div>
                                <div style="width:24px;height:24px;border-radius:50%;background:#333;margin-bottom:4px;"></div>
                                <div style="width:24px;height:24px;border-radius:50%;background:#333;"></div>
                            </div>
                        </div>
                        <div style="color:white;font-size:13px;margin-top:10px;opacity:.9;">Traffic Light Scenarios</div>
                    </div>
                    <?php elseif ($sim['animation_type'] === 'four_way_stop'): ?>
                    <div style="text-align:center;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;width:80px;margin:0 auto;">
                            <div style="background:rgba(255,255,255,.1);height:30px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:16px;">🚗</div>
                            <div style="background:rgba(255,255,255,.3);height:30px;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                <div style="width:8px;height:8px;background:#e74c3c;border-radius:50%;"></div>
                            </div>
                            <div style="background:rgba(255,255,255,.3);height:30px;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                <div style="width:8px;height:8px;background:#e74c3c;border-radius:50%;"></div>
                            </div>
                            <div style="background:rgba(255,255,255,.1);height:30px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:16px;">🚙</div>
                        </div>
                        <div style="color:white;font-size:13px;margin-top:10px;opacity:.9;">Four-Way Stop Intersection</div>
                    </div>
                    <?php elseif ($sim['animation_type'] === 'speed'): ?>
                    <div style="text-align:center;">
                        <div style="width:56px;height:56px;border-radius:50%;border:5px solid white;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:18px;font-weight:900;color:white;">120</div>
                        <div style="color:white;font-size:13px;margin-top:10px;opacity:.9;">Speed Limit Scenarios</div>
                    </div>
                    <?php elseif ($sim['animation_type'] === 'parking'): ?>
                    <div style="text-align:center;">
                        <div style="font-size:40px;margin-bottom:8px;">🅿️</div>
                        <div style="color:white;font-size:13px;opacity:.9;">Parking Scenarios</div>
                    </div>
                    <?php elseif ($sim['animation_type'] === 'vehicle_check'): ?>
                    <div style="text-align:center;">
                        <div style="font-size:40px;margin-bottom:8px;">🚘</div>
                        <div style="color:white;font-size:13px;opacity:.9;">Vehicle Control Scenarios</div>
                    </div>
                    <?php else: ?>
                    <div style="text-align:center;">
                        <div style="font-size:40px;margin-bottom:8px;">🛑</div>
                        <div style="color:white;font-size:13px;opacity:.9;">Road Sign Scenarios</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="padding:20px;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#888;margin-bottom:6px;"><?= htmlspecialchars($sim['topic_name'] ?? 'General') ?></div>
                    <h3 style="font-size:17px;font-weight:700;margin-bottom:8px;"><?= htmlspecialchars($sim['title']) ?></h3>
                    <p style="color:#666;font-size:14px;margin-bottom:16px;line-height:1.6;"><?= htmlspecialchars($sim['description']) ?></p>
                    <div style="font-size:13px;color:#888;margin-bottom:16px;"><?= $sim['question_count'] ?> quiz questions</div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <a href="/testmate/simulations.php?id=<?= $sim['id'] ?>&mode=study"
                           class="btn btn-outline" style="text-align:center;font-size:14px;">
                            Study Mode
                        </a>
                        <a href="/testmate/simulations.php?id=<?= $sim['id'] ?>&mode=quiz"
                           class="btn btn-primary" style="text-align:center;font-size:14px;">
                            Quiz Mode
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

// Get specific simulation
$s = $conn->prepare("SELECT s.*, t.name AS topic_name FROM simulations s LEFT JOIN topics t ON s.topic_id = t.id WHERE s.id = ?");
$s->bind_param("i", $sim_id);
$s->execute();
$simulation = $s->get_result()->fetch_assoc();

if (!$simulation) {
    header("Location: /testmate/simulations.php");
    exit();
}

$scenarios = json_decode($simulation['scenario_data'], true);

// Get quiz questions for this simulation
$q = $conn->prepare("SELECT * FROM simulation_questions WHERE simulation_id = ? ORDER BY scenario_index");
$q->bind_param("i", $sim_id);
$q->execute();
$quiz_questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);

// Map scenario_index -> media (for Study Mode, which loops over scenario_data JSON, not the questions table)
$media_by_index = [];
foreach ($quiz_questions as $qq) {
    if (!empty($qq['media_path'])) {
        $media_by_index[$qq['scenario_index']] = ['path' => $qq['media_path'], 'type' => $qq['media_type']];
    }
}

include 'includes/header.php';
?>

<style>
/* Animation Styles */
.sim-container {
    background: #1a252f;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 24px;
    position: relative;
}

.sim-screen {
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px;
    position: relative;
    background: linear-gradient(180deg, #87CEEB 0%, #87CEEB 60%, #555 60%, #555 100%);
}

/* Road */
.road {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 40%;
    background: #555;
}
.road-line {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 60px; height: 6px;
    background: white; border-radius: 3px;
}

/* Car */
.car {
    font-size: 48px;
    position: absolute;
    bottom: 30%;
    transition: left 1.5s ease-in-out;
    z-index: 10;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,.3));
}

/* Traffic Light */
.traffic-light-pole {
    position: absolute;
    right: 25%;
    bottom: 30%;
    z-index: 5;
}
.traffic-light-box {
    background: #222;
    border-radius: 10px;
    padding: 8px;
    margin-bottom: 4px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 2px solid #444;
}
.light-bulb {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: #333;
    transition: background .3s, box-shadow .3s;
}
.light-bulb.red-on    { background: #e74c3c; box-shadow: 0 0 12px #e74c3c; }
.light-bulb.amber-on  { background: #f1c40f; box-shadow: 0 0 12px #f1c40f; }
.light-bulb.green-on  { background: #2ecc71; box-shadow: 0 0 12px #2ecc71; }
.light-pole-stem { width: 6px; height: 60px; background: #666; margin: 0 auto; border-radius: 3px; }

/* Stop sign */
.stop-sign {
    position: absolute;
    right: 25%;
    bottom: 30%;
    z-index: 5;
    text-align: center;
}
.stop-sign-board {
    background: #e74c3c;
    color: white;
    font-size: 12px;
    font-weight: 800;
    width: 50px; height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);
    margin: 0 auto 4px;
}
.stop-sign-pole { width: 6px; height: 60px; background: #666; margin: 0 auto; border-radius: 3px; }

/* Result overlay */
.result-overlay {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    font-size: 80px;
    z-index: 20;
    opacity: 0;
    transition: opacity .5s;
    text-shadow: 0 4px 12px rgba(0,0,0,.3);
}
.result-overlay.show { opacity: 1; }

/* Scenario info bar */
.sim-info {
    background: rgba(0,0,0,.5);
    color: white;
    padding: 12px 20px;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Mode badge */
.mode-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
}
.mode-badge.study { background: #eaf4ff; color: #2471a3; }
.mode-badge.quiz  { background: #fdecea; color: #c0392b; }

/* Question card in quiz mode */
.quiz-q-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 16px;
    border-left: 4px solid #3498db;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

/* Answer buttons */
.answer-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    background: white;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    transition: all .2s;
    width: 100%;
    margin-bottom: 10px;
}
.answer-btn:hover    { border-color: #3498db; background: #f0f8ff; }
.answer-btn.selected { border-color: #3498db; background: #f0f8ff; }
.answer-btn.correct  { border-color: #27ae60; background: #eafaf1; color: #27ae60; }
.answer-btn.wrong    { border-color: #e74c3c; background: #fdecea; color: #e74c3c; }
.answer-btn .btn-icon { font-size: 22px; }

/* New animation types: speed / parking / vehicle_check */
.speed-sign-pole {
    position: absolute; right: 25%; bottom: 30%; z-index: 5; text-align: center;
}
.speed-sign-circle {
    width: 54px; height: 54px; border-radius: 50%; border: 5px solid #e74c3c;
    background: white; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 4px; font-size: 15px; font-weight: 900; color: #333;
}
.speedo-badge {
    position: absolute; top: 12%; left: 12%; background: rgba(0,0,0,.7); color: white;
    padding: 6px 14px; border-radius: 8px; font-size: 14px; font-weight: 700; z-index: 15;
}
.parking-pole { position: absolute; right: 25%; bottom: 30%; z-index: 5; text-align: center; }
.parking-legal-p {
    width: 42px; height: 50px; background: #3498db; border-radius: 6px; display: flex;
    align-items: center; justify-content: center; margin: 0 auto 4px; font-size: 22px;
    font-weight: 900; color: white;
}
.parking-yellow-strip { position: absolute; bottom: 30%; left: 0; right: 0; height: 6px; background: #f1c40f; }
.parking-no-p {
    width: 42px; height: 42px; border-radius: 50%; border: 4px solid #e74c3c; background: white;
    display: flex; align-items: center; justify-content: center; margin: 0 auto 4px;
    font-size: 17px; font-weight: 900; color: #e74c3c; text-decoration: line-through;
}
.vehicle-check-badge {
    position: absolute; top: 15%; left: 50%; transform: translateX(-50%);
    background: rgba(0,0,0,.75); color: white; padding: 10px 18px; border-radius: 10px;
    font-size: 13px; font-weight: 600; z-index: 15; text-align: center; white-space: nowrap;
}
</style>

<!-- Page Header -->
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
        <h1><?= htmlspecialchars($simulation['title']) ?></h1>
        <p><?= htmlspecialchars($simulation['description']) ?></p>
    </div>
    <div style="display:flex;gap:10px;">
        <?php if ($mode === 'study'): ?>
            <span class="mode-badge study">Study Mode</span>
            <a href="/testmate/simulations.php?id=<?= $sim_id ?>&mode=quiz" class="btn btn-primary" style="font-size:14px;padding:8px 16px;">Switch to Quiz Mode</a>
        <?php else: ?>
            <span class="mode-badge quiz">Quiz Mode</span>
            <a href="/testmate/simulations.php?id=<?= $sim_id ?>&mode=study" class="btn btn-outline" style="font-size:14px;padding:8px 16px;">Switch to Study Mode</a>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="max-width:900px;">

<?php if ($mode === 'study'): ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- STUDY MODE — Show animation + auto reveal answer       -->
<!-- ═══════════════════════════════════════════════════════ -->

<div style="background:#eaf4ff;border-left:4px solid #3498db;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
    <strong style="color:#2471a3;">Study Mode:</strong>
    <span style="color:#555;font-size:14px;"> Watch each scenario carefully. The animation will automatically show you whether the driver's action was correct or incorrect so you can learn the rules.</span>
</div>

<?php foreach ($scenarios as $si => $scenario): ?>
<div style="margin-bottom:32px;">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;color:#2c3e50;">
        Scenario <?= $si+1 ?>
        <span style="font-size:13px;font-weight:400;color:#888;margin-left:8px;">
            <?php if ($simulation['animation_type'] === 'traffic_light'): ?>
                <?= ucfirst($scenario['light']) ?> light — Car <?= $scenario['car_passes'] ? 'drives through' : 'stops' ?>
            <?php elseif ($simulation['animation_type'] === 'four_way_stop'): ?>
                Vehicle arrives <?= str_replace('_',' ',$scenario['arriving_order']) ?> — Action: <?= $scenario['action'] ?>
            <?php elseif ($simulation['animation_type'] === 'road_signs'): ?>
                <?= ucfirst($scenario['sign'] ?? '') ?> sign — Driver <?= ($scenario['driver_stops'] ?? false) ? 'stops' : 'does not stop' ?>
            <?php elseif ($simulation['animation_type'] === 'speed'): ?>
                Driving at <?= (int)($scenario['speed'] ?? 0) ?> km/h (limit: <?= (int)($scenario['limit'] ?? 0) ?> km/h)
            <?php elseif ($simulation['animation_type'] === 'parking'): ?>
                <?= ucfirst(str_replace('_',' ',$scenario['location'] ?? '')) ?> parking
            <?php elseif ($simulation['animation_type'] === 'vehicle_check'): ?>
                <?= htmlspecialchars($scenario['label'] ?? ucfirst(str_replace('_',' ',$scenario['action'] ?? ''))) ?>
            <?php endif; ?>
        </span>
    </h3>

    <!-- Animation Container -->
    <div class="sim-container" id="sim-study-<?= $si ?>">
        <div class="sim-screen" id="screen-study-<?= $si ?>">

            <!-- Road line -->
            <div class="road-line" style="bottom:calc(30% + 10px);left:50%;position:absolute;"></div>

            <?php if ($simulation['animation_type'] === 'traffic_light'): ?>
            <!-- Traffic Light -->
            <div class="traffic-light-pole" id="tl-study-<?= $si ?>">
                <div class="traffic-light-box">
                    <div class="light-bulb <?= $scenario['light']==='red' ? 'red-on' : '' ?>" id="red-study-<?= $si ?>"></div>
                    <div class="light-bulb <?= $scenario['light']==='amber' ? 'amber-on' : '' ?>" id="amber-study-<?= $si ?>"></div>
                    <div class="light-bulb <?= $scenario['light']==='green' ? 'green-on' : '' ?>" id="green-study-<?= $si ?>"></div>
                </div>
                <div class="light-pole-stem"></div>
            </div>

            <?php elseif ($simulation['animation_type'] === 'road_signs'): ?>
            <!-- Road Sign -->
            <div class="stop-sign" id="sign-study-<?= $si ?>">
                <?php if ($scenario['sign'] === 'stop'): ?>
                <div class="stop-sign-board">STOP</div>
                <?php elseif ($scenario['sign'] === 'yield'): ?>
                <div style="width:50px;height:50px;background:#e74c3c;clip-path:polygon(50% 100%,0% 0%,100% 0%);margin:0 auto 4px;display:flex;align-items:center;justify-content:center;">
                    <span style="color:white;font-size:10px;font-weight:800;margin-top:-10px;">YIELD</span>
                </div>
                <?php endif; ?>
                <div class="stop-sign-pole"></div>
            </div>

            <?php elseif ($simulation['animation_type'] === 'four_way_stop'): ?>
            <!-- Four Way Stop Signs at all 4 positions -->
            <?php foreach ([['right:15%','bottom:35%'],['right:15%','bottom:55%'],['left:15%','bottom:35%'],['left:15%','bottom:55%']] as $pos): ?>
            <div style="position:absolute;<?= $pos[0] ?>;<?= $pos[1] ?>;text-align:center;">
                <div style="width:24px;height:24px;background:#e74c3c;clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);margin:0 auto;display:flex;align-items:center;justify-content:center;">
                    <span style="color:white;font-size:5px;font-weight:800;">STOP</span>
                </div>
                <div style="width:3px;height:30px;background:#666;margin:0 auto;"></div>
            </div>
            <?php endforeach; ?>

            <?php elseif ($simulation['animation_type'] === 'speed'): ?>
            <div class="speed-sign-pole">
                <div class="speed-sign-circle"><?= (int)($scenario['limit'] ?? 0) ?></div>
                <div style="width:5px;height:55px;background:#777;border-radius:3px;margin:0 auto;"></div>
            </div>
            <div class="speedo-badge" id="speedo-study-<?= $si ?>" style="display:none;"><?= (int)($scenario['speed'] ?? 0) ?> km/h</div>

            <?php elseif ($simulation['animation_type'] === 'parking'): ?>
            <div class="parking-pole">
                <?php if (($scenario['location'] ?? '') === 'legal'): ?>
                <div class="parking-legal-p">P</div>
                <?php elseif (($scenario['location'] ?? '') === 'yellow'): ?>
                <div class="parking-yellow-strip"></div>
                <div style="background:rgba(0,0,0,.6);color:white;padding:4px 8px;border-radius:6px;font-size:10px;font-weight:700;">YELLOW KERB</div>
                <?php else: ?>
                <div class="parking-no-p">P</div>
                <?php endif; ?>
            </div>

            <?php elseif ($simulation['animation_type'] === 'vehicle_check'): ?>
            <div class="vehicle-check-badge"><?= htmlspecialchars($scenario['label'] ?? ucfirst(str_replace('_',' ',$scenario['action'] ?? ''))) ?></div>
            <?php endif; ?>

            <!-- Car -->
            <div class="car" id="car-study-<?= $si ?>" style="left:5%;bottom:28%;">🚗</div>

            <!-- Result Overlay -->
            <div class="result-overlay" id="result-study-<?= $si ?>">
                <?= $scenario['is_correct'] ? '✅' : '❌' ?>
            </div>

        </div>

        <!-- Info bar -->
        <div class="sim-info">
            <span id="status-study-<?= $si ?>" style="font-size:13px;">Click Play to watch the scenario</span>
            <button data-si="<?= $si ?>"
                    data-scenario='<?= htmlspecialchars(json_encode($scenario), ENT_QUOTES, "UTF-8") ?>'
                    onclick="playStudyScenario(this)"
                    id="playBtn-study-<?= $si ?>"
                    style="background:#3498db;color:white;border:none;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
                Play
            </button>
        </div>
    </div>

    <?php if (isset($media_by_index[$si])):
        $sm = $media_by_index[$si];
    ?>
    <div style="margin-top:12px;">
        <?php if ($sm['type'] === 'video'): ?>
        <video src="<?= htmlspecialchars($sm['path']) ?>" controls style="max-width:100%;max-height:280px;border-radius:8px;display:block;"></video>
        <?php elseif ($sm['type'] === 'pdf'): ?>
        <a href="<?= htmlspecialchars($sm['path']) ?>" target="_blank" class="btn btn-outline" style="font-size:13px;">📄 View attached PDF</a>
        <?php else: ?>
        <img src="<?= htmlspecialchars($sm['path']) ?>" style="max-width:100%;max-height:280px;border-radius:8px;display:block;">
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Explanation (shown after play) -->
    <div id="explanation-study-<?= $si ?>" style="display:none;background:<?= $scenario['is_correct']?'#eafaf1':'#fdecea' ?>;border-left:4px solid <?= $scenario['is_correct']?'#27ae60':'#e74c3c' ?>;border-radius:0 8px 8px 0;padding:14px 18px;font-size:14px;color:#333;line-height:1.6;">
        <strong style="color:<?= $scenario['is_correct']?'#27ae60':'#e74c3c' ?>"><?= $scenario['is_correct']?'Correct Action':'Incorrect Action' ?></strong><br>
        <?= htmlspecialchars($scenario['explanation']) ?>
    </div>
</div>
<?php endforeach; ?>

<div style="text-align:center;margin-top:24px;padding-bottom:30px;">
    <a href="/testmate/simulations.php?id=<?= $sim_id ?>&mode=quiz" class="btn btn-primary btn-lg">
        Ready? Take the Quiz
    </a>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════ -->
<!-- QUIZ MODE — Show animation, learner decides answer     -->
<!-- ═══════════════════════════════════════════════════════ -->

<div style="background:#fdecea;border-left:4px solid #e74c3c;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
    <strong style="color:#c0392b;">Quiz Mode:</strong>
    <span style="color:#555;font-size:14px;"> Watch each animation carefully. Decide whether the driver's action was correct or incorrect. Do NOT reveal the answer — make your choice first, then submit all answers.</span>
</div>

<form method="POST" id="simQuizForm">
    <input type="hidden" name="submit_simulation" value="1">
    <input type="hidden" name="simulation_id" value="<?= $sim_id ?>">
    <input type="hidden" name="questions_data" value="<?= htmlspecialchars(json_encode(array_map(function($q) {
        return [
            'question'       => $q['question'],
            'correct_answer' => $q['correct_answer'],
            'explanation'    => $q['explanation'],
            'scenario_index' => $q['scenario_index'],
        ];
    }, $quiz_questions))) ?>">

    <?php foreach ($quiz_questions as $qi => $q):
        $scenario = $scenarios[$q['scenario_index']] ?? [];
    ?>
    <div class="quiz-q-card" style="margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <span style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;">Question <?= $qi+1 ?> of <?= count($quiz_questions) ?></span>
            <span style="font-size:12px;background:#fdecea;color:#c0392b;padding:3px 10px;border-radius:20px;font-weight:600;">Quiz Mode — No hints!</span>
        </div>

        <!-- Animation -->
        <div class="sim-container" id="sim-quiz-<?= $qi ?>" style="margin-bottom:14px;">
            <div class="sim-screen" id="screen-quiz-<?= $qi ?>">

                <div class="road-line" style="bottom:calc(30% + 10px);left:50%;position:absolute;"></div>

                <?php if ($simulation['animation_type'] === 'traffic_light' && $scenario): ?>
                <div class="traffic-light-pole">
                    <div class="traffic-light-box">
                        <div class="light-bulb <?= $scenario['light']==='red'?'red-on':'' ?>"></div>
                        <div class="light-bulb <?= $scenario['light']==='amber'?'amber-on':'' ?>"></div>
                        <div class="light-bulb <?= $scenario['light']==='green'?'green-on':'' ?>"></div>
                    </div>
                    <div class="light-pole-stem"></div>
                </div>
                <?php elseif ($simulation['animation_type'] === 'road_signs' && $scenario): ?>
                <div class="stop-sign">
                    <?php if (($scenario['sign']??'') === 'stop'): ?>
                    <div class="stop-sign-board">STOP</div>
                    <?php else: ?>
                    <div style="width:50px;height:50px;background:#e74c3c;clip-path:polygon(50% 100%,0% 0%,100% 0%);margin:0 auto 4px;"></div>
                    <?php endif; ?>
                    <div class="stop-sign-pole"></div>
                </div>
                <?php elseif ($simulation['animation_type'] === 'four_way_stop' && $scenario): ?>
                <?php foreach ([['right:15%','bottom:35%'],['right:15%','bottom:55%'],['left:15%','bottom:35%'],['left:15%','bottom:55%']] as $pos): ?>
                <div style="position:absolute;<?= $pos[0] ?>;<?= $pos[1] ?>;text-align:center;">
                    <div style="width:24px;height:24px;background:#e74c3c;clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);margin:0 auto;display:flex;align-items:center;justify-content:center;">
                        <span style="color:white;font-size:5px;font-weight:800;">STOP</span>
                    </div>
                    <div style="width:3px;height:30px;background:#666;margin:0 auto;"></div>
                </div>
                <?php endforeach; ?>
                <?php elseif ($simulation['animation_type'] === 'speed' && $scenario): ?>
                <div class="speed-sign-pole">
                    <div class="speed-sign-circle"><?= (int)($scenario['limit'] ?? 0) ?></div>
                    <div style="width:5px;height:55px;background:#777;border-radius:3px;margin:0 auto;"></div>
                </div>
                <div class="speedo-badge" id="speedo-quiz-<?= $qi ?>" style="display:none;"><?= (int)($scenario['speed'] ?? 0) ?> km/h</div>
                <?php elseif ($simulation['animation_type'] === 'parking' && $scenario): ?>
                <div class="parking-pole">
                    <?php if (($scenario['location'] ?? '') === 'legal'): ?>
                    <div class="parking-legal-p">P</div>
                    <?php elseif (($scenario['location'] ?? '') === 'yellow'): ?>
                    <div class="parking-yellow-strip"></div>
                    <div style="background:rgba(0,0,0,.6);color:white;padding:4px 8px;border-radius:6px;font-size:10px;font-weight:700;">YELLOW KERB</div>
                    <?php else: ?>
                    <div class="parking-no-p">P</div>
                    <?php endif; ?>
                </div>
                <?php elseif ($simulation['animation_type'] === 'vehicle_check' && $scenario): ?>
                <div class="vehicle-check-badge"><?= htmlspecialchars($scenario['label'] ?? ucfirst(str_replace('_',' ',$scenario['action'] ?? ''))) ?></div>
                <?php endif; ?>

                <div class="car" id="car-quiz-<?= $qi ?>" style="left:5%;bottom:28%;">🚗</div>

                <!-- NO result overlay in quiz mode -->
            </div>
            <div class="sim-info">
                <span id="qstatus-<?= $qi ?>" style="font-size:13px;">Click Play to watch — then decide if the action was correct</span>
                <button type="button"
                        data-qi="<?= $qi ?>"
                        data-scenario='<?= htmlspecialchars(json_encode($scenario), ENT_QUOTES, "UTF-8") ?>'
                        onclick="playQuizScenario(this)"
                        id="qplayBtn-<?= $qi ?>"
                        style="background:#e74c3c;color:white;border:none;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
                    Play
                </button>
            </div>
        </div>

        <!-- Question -->
        <?php if (!empty($q['media_path'])): ?>
        <div style="margin-bottom:14px;">
            <?php if ($q['media_type'] === 'video'): ?>
            <video src="<?= htmlspecialchars($q['media_path']) ?>" controls style="max-width:100%;max-height:280px;border-radius:8px;display:block;"></video>
            <?php elseif ($q['media_type'] === 'pdf'): ?>
            <a href="<?= htmlspecialchars($q['media_path']) ?>" target="_blank" class="btn btn-outline" style="font-size:13px;">📄 View attached PDF</a>
            <?php else: ?>
            <img src="<?= htmlspecialchars($q['media_path']) ?>" style="max-width:100%;max-height:280px;border-radius:8px;display:block;">
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <p style="font-weight:600;font-size:15px;margin-bottom:14px;color:#2c3e50;"><?= htmlspecialchars($q['question']) ?></p>

        <!-- Answer Buttons -->
        <button type="button"
                class="answer-btn"
                data-qi="<?= $qi ?>"
                data-val="correct"
                onclick="selectAnswer(<?= $qi ?>, 'correct', this)">
            <span class="btn-icon">✅</span>
            <span>Correct — The driver's action was correct</span>
        </button>
        <button type="button"
                class="answer-btn"
                data-qi="<?= $qi ?>"
                data-val="incorrect"
                onclick="selectAnswer(<?= $qi ?>, 'incorrect', this)">
            <span class="btn-icon">❌</span>
            <span>Incorrect — The driver's action was wrong</span>
        </button>

        <input type="hidden" name="answers[<?= $qi ?>]" id="answer-<?= $qi ?>" value="">
    </div>
    <?php endforeach; ?>

    <div style="text-align:center;padding:24px 0 40px;">
        <div id="quizValidationMsg" style="display:none;color:#e74c3c;font-size:14px;margin-bottom:12px;">Please answer all questions before submitting.</div>
        <button type="button" onclick="submitSimQuiz()" class="btn btn-primary btn-lg">Submit Quiz</button>
        <p style="color:#999;font-size:13px;margin-top:10px;">Make sure you have watched all animations and answered every question.</p>
    </div>
</form>

<?php endif; ?>

<div style="text-align:center;margin-bottom:40px;">
    <a href="/testmate/simulations.php" class="btn btn-outline">All Simulations</a>
    <a href="/testmate/dashboard.php" class="btn btn-outline" style="margin-left:10px;">Dashboard</a>
</div>

</div>

<script>
// ── STUDY MODE ANIMATIONS ──
function playStudyScenario(btn) {
    const si       = btn.dataset.si;
    const scenario = JSON.parse(btn.dataset.scenario);

    const car    = document.getElementById('car-study-' + si);
    const result = document.getElementById('result-study-' + si);
    const status = document.getElementById('status-study-' + si);
    const expl   = document.getElementById('explanation-study-' + si);
    const speedo = document.getElementById('speedo-study-' + si);

    if (!car) return;

    btn.disabled   = true;
    btn.textContent = 'Playing...';
    result.classList.remove('show');
    expl.style.display = 'none';
    if (speedo) speedo.style.display = 'none';

    status.textContent = 'Car approaching...';
    car.style.left = '5%';

    setTimeout(() => {
        status.textContent = 'Action happening...';
        if (speedo) speedo.style.display = 'block';

        if ('location' in scenario) {
            car.style.left = '55%';
        } else if ('label' in scenario) {
            car.style.left = '25%';
        } else if ('speed' in scenario) {
            car.style.left = '80%';
        } else if ('arriving_order' in scenario) {
            car.style.left = (scenario.action === 'go') ? '75%' : '40%';
        } else if (scenario.car_passes || scenario.driver_stops === false) {
            car.style.left = '90%';
        } else {
            car.style.left = '40%';
        }
    }, 500);

    setTimeout(() => {
        result.classList.add('show');
        status.textContent = scenario.is_correct ? 'Correct action!' : 'Incorrect action!';
        expl.style.display = 'block';
        btn.disabled   = false;
        btn.textContent = 'Replay';
    }, 2500);
}

// ── QUIZ MODE ANIMATIONS (no result shown) ──
function playQuizScenario(btn) {
    const qi       = btn.dataset.qi;
    const scenario = JSON.parse(btn.dataset.scenario);

    const car    = document.getElementById('car-quiz-' + qi);
    const status = document.getElementById('qstatus-' + qi);
    const speedo = document.getElementById('speedo-quiz-' + qi);

    if (!car) return;

    btn.disabled    = true;
    btn.textContent = 'Playing...';
    car.style.left  = '5%';
    status.textContent = 'Watch carefully...';
    if (speedo) speedo.style.display = 'none';

    setTimeout(() => {
        if (speedo) speedo.style.display = 'block';

        if ('location' in scenario) {
            car.style.left = '55%';
            status.textContent = 'The car parked here — was that correct?';
        } else if ('label' in scenario) {
            car.style.left = '25%';
            status.textContent = 'Was this the correct action?';
        } else if ('speed' in scenario) {
            car.style.left = '80%';
            status.textContent = 'Was that speed correct for this sign?';
        } else if ('arriving_order' in scenario) {
            car.style.left = (scenario.action === 'go') ? '75%' : '40%';
            status.textContent = (scenario.action === 'go') ? 'The car proceeded — was that correct?' : 'The car waited — was that correct?';
        } else if (scenario.car_passes || scenario.driver_stops === false) {
            car.style.left = '90%';
            status.textContent = 'The car drove through — was that correct?';
        } else {
            car.style.left = '40%';
            status.textContent = 'The car stopped — was that correct?';
        }
    }, 500);

    setTimeout(() => {
        btn.disabled    = false;
        btn.textContent = 'Replay';
        status.textContent = 'Now select your answer below';
    }, 2500);
}

// ── QUIZ ANSWER SELECTION ──
const selectedAnswers = {};

function selectAnswer(qi, val, btn) {
    // Clear previous selection for this question
    document.querySelectorAll('[data-qi="' + qi + '"]').forEach(b => {
        b.classList.remove('selected');
        b.style.borderColor = '#e0e0e0';
        b.style.background  = 'white';
        b.style.color       = '#333';
    });
    btn.classList.add('selected');
    btn.style.borderColor = '#3498db';
    btn.style.background  = '#f0f8ff';
    btn.style.color       = '#2c3e50';
    selectedAnswers[qi]   = val;
    document.getElementById('answer-' + qi).value = val;
}

function submitSimQuiz() {
    const total = <?= count($quiz_questions) ?>;
    if (Object.keys(selectedAnswers).length < total) {
        document.getElementById('quizValidationMsg').style.display = 'block';
        return;
    }
    document.getElementById('simQuizForm').submit();
}
</script>

<?php include 'includes/footer.php'; ?>