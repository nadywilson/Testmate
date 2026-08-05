<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id    = $_SESSION['user_id'];
$activity_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$activity_id) {
    header("Location: /testmate/activities.php");
    exit();
}

$a = $conn->prepare("SELECT act.*, t.name AS topic_name FROM activities act LEFT JOIN topics t ON act.topic_id = t.id WHERE act.id = ?");
$a->bind_param("i", $activity_id);
$a->execute();
$activity = $a->get_result()->fetch_assoc();

if (!$activity) {
    header("Location: /testmate/activities.php");
    exit();
}

$content = json_decode($activity['content'], true);

// Get previous result
$prev = $conn->prepare("SELECT * FROM activity_results WHERE user_id=? AND activity_id=?");
$prev->bind_param("ii", $user_id, $activity_id);
$prev->execute();
$prev_result = $prev->get_result()->fetch_assoc();
?>
<?php include 'includes/header.php'; ?>

<style>
/* Drag and Drop */
.dnd-container { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
.signs-pool { background:#f8f9fa; border-radius:12px; padding:16px; }
.signs-pool h3 { font-size:15px; font-weight:600; margin-bottom:12px; color:#2c3e50; }
.sign-item {
    background:white; border:2px solid #e0e0e0; border-radius:8px;
    padding:12px; margin-bottom:8px; cursor:grab; transition:all .2s;
    display:flex; align-items:center; gap:10px; font-size:14px;
}
.sign-item:hover { border-color:#3498db; background:#f0f8ff; }
.sign-item.dragging { opacity:.5; border-style:dashed; }
.sign-item .sign-icon {
    width:50px; height:50px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; flex-shrink:0; font-weight:800;
}

.drop-zones { background:#f8f9fa; border-radius:12px; padding:16px; }
.drop-zones h3 { font-size:15px; font-weight:600; margin-bottom:12px; color:#2c3e50; }
.drop-zone {
    border:2px dashed #ccc; border-radius:8px; padding:12px 16px;
    margin-bottom:8px; min-height:52px; transition:all .2s;
    display:flex; align-items:center; gap:10px; font-size:14px; color:#888;
}
.drop-zone.dragover { border-color:#3498db; background:#f0f8ff; }
.drop-zone.correct  { border-color:#27ae60; background:#eafaf1; color:#27ae60; }
.drop-zone.wrong    { border-color:#e74c3c; background:#fdecea; color:#e74c3c; }
.drop-zone.filled   { border-style:solid; border-color:#3498db; color:#333; }

/* Image Quiz */
.sign-display {
    width:160px; height:160px; border-radius:16px; margin:0 auto 20px;
    display:flex; align-items:center; justify-content:center;
    font-size:48px; font-weight:800; color:white;
}
.quiz-options { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.quiz-opt {
    padding:14px 16px; border:2px solid #e0e0e0; border-radius:8px;
    cursor:pointer; font-size:14px; transition:all .2s; text-align:center;
    background:white;
}
.quiz-opt:hover { border-color:#3498db; background:#f0f8ff; }
.quiz-opt.correct { border-color:#27ae60; background:#eafaf1; color:#27ae60; font-weight:600; }
.quiz-opt.wrong   { border-color:#e74c3c; background:#fdecea; color:#e74c3c; }

/* Score Banner */
.score-banner {
    border-radius:12px; padding:24px; text-align:center;
    color:white; margin-bottom:20px;
}
</style>

<div class="page-header">
    <h1><?= htmlspecialchars($activity['title']) ?></h1>
    <p><?= htmlspecialchars($activity['description']) ?></p>
</div>

<div class="container" style="max-width:900px;">

    <?php if ($prev_result && $prev_result['completed']): ?>
    <div class="score-banner" style="background:<?= $prev_result['score'] >= ($prev_result['max_score'] * 0.8) ? '#27ae60' : '#e67e22' ?>">
        <div style="font-size:28px;font-weight:800;"><?= $prev_result['score'] ?>/<?= $prev_result['max_score'] ?> points</div>
        <div style="opacity:.9;margin-top:4px;">Previous attempt — try again to improve!</div>
    </div>
    <?php endif; ?>

    <?php if ($activity['type'] === 'h5p'): ?>

        <?php if (isset($content['signs'])): ?>
        <!-- DRAG AND DROP ROAD SIGNS -->
        <div style="background:#eaf4ff;border-left:4px solid #3498db;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
            <strong style="color:#2471a3;">How to play:</strong>
            <span style="color:#555;font-size:14px;"> Drag each road sign from the left and drop it onto the correct meaning on the right.</span>
        </div>

        <div id="dndResult" style="display:none;" class="score-banner"></div>

        <div class="dnd-container">
            <!-- Signs Pool -->
            <div class="signs-pool">
                <h3>Road Signs</h3>
                <div id="signsPool">
                    <?php
                    $sign_styles = [
                        'stop'       => ['bg'=>'#e74c3c','text'=>'STOP','shape'=>'octagon'],
                        'yield'      => ['bg'=>'#e74c3c','text'=>'YIELD','shape'=>'triangle'],
                        'no_entry'   => ['bg'=>'#e74c3c','text'=>'NO','shape'=>'circle'],
                        'speed_60'   => ['bg'=>'white','text'=>'60','shape'=>'circle','border'=>'#e74c3c','color'=>'#333'],
                        'pedestrian' => ['bg'=>'#f1c40f','text'=>'PED','shape'=>'diamond'],
                        'warning'    => ['bg'=>'#f1c40f','text'=>'!','shape'=>'triangle'],
                    ];
                    $shuffled = $content['signs'];
                    shuffle($shuffled);
                    foreach ($shuffled as $sign):
                        $style = $sign_styles[$sign['image']] ?? ['bg'=>'#888','text'=>'?'];
                        $border = isset($style['border']) ? "border:3px solid {$style['border']};" : '';
                        $textcolor = $style['color'] ?? 'white';
                    ?>
                    <div class="sign-item" draggable="true"
                         data-sign="<?= $sign['image'] ?>"
                         id="sign-<?= $sign['image'] ?>">
                        <div class="sign-icon" style="background:<?= $style['bg'] ?>;<?= $border ?>color:<?= $textcolor ?>;">
                            <?= $style['text'] ?>
                        </div>
                        <?= htmlspecialchars($sign['image'] === 'speed_60' ? 'Speed Sign' : ucwords(str_replace('_',' ',$sign['image']))) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Drop Zones -->
            <div class="drop-zones">
                <h3>Meanings</h3>
                <?php foreach ($content['signs'] as $sign): ?>
                <div class="drop-zone"
                     data-answer="<?= $sign['image'] ?>"
                     id="zone-<?= $sign['image'] ?>">
                    <span class="zone-text"><?= htmlspecialchars($sign['meaning']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="text-align:center;">
            <button onclick="checkDragDrop()" class="btn btn-primary btn-lg" id="checkBtn" disabled>
                Check Answers
            </button>
            <button onclick="resetDragDrop()" class="btn btn-outline" style="margin-left:10px;">Reset</button>
        </div>

        <script>
        const totalSigns = <?= count($content['signs']) ?>;
        let draggedSign = null;
        let placements = {};
        const startTime = Date.now();

        // Drag events
        document.querySelectorAll('.sign-item').forEach(item => {
            item.addEventListener('dragstart', e => {
                draggedSign = item;
                item.classList.add('dragging');
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
            });
        });

        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('dragover');
            });
            zone.addEventListener('dragleave', () => {
                zone.classList.remove('dragover');
            });
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('dragover');
                if (!draggedSign) return;

                const signId   = draggedSign.dataset.sign;
                const zoneId   = zone.dataset.answer;
                const signIcon = draggedSign.querySelector('.sign-icon').outerHTML;

                // Remove from previous zone if placed
                Object.keys(placements).forEach(k => {
                    if (placements[k] === signId) {
                        const oldZone = document.getElementById('zone-' + k);
                        oldZone.innerHTML = '<span class="zone-text">' + oldZone.querySelector('.zone-text')?.textContent + '</span>';
                        oldZone.classList.remove('filled');
                        delete placements[k];
                    }
                });

                // Return previous sign in this zone to pool if there was one
                if (placements[zoneId]) {
                    const prevSign = document.getElementById('sign-' + placements[zoneId]);
                    if (prevSign) prevSign.style.display = 'flex';
                }

                // Place sign in zone
                const meaning = zone.querySelector('.zone-text')?.textContent || zone.textContent.trim();
                zone.innerHTML = signIcon + '<span class="zone-text">' + meaning + '</span>';
                zone.classList.add('filled');
                draggedSign.style.display = 'none';
                placements[zoneId] = signId;

                // Enable check button if all zones filled
                document.getElementById('checkBtn').disabled = Object.keys(placements).length < totalSigns;
            });
        });

        function checkDragDrop() {
            let score = 0;
            Object.keys(placements).forEach(zoneId => {
                const zone = document.getElementById('zone-' + zoneId);
                if (placements[zoneId] === zoneId) {
                    zone.classList.add('correct');
                    score++;
                } else {
                    zone.classList.add('wrong');
                }
            });

            const pct = Math.round(score / totalSigns * 100);
            const banner = document.getElementById('dndResult');
            banner.style.display = 'block';
            banner.style.background = pct >= 80 ? '#27ae60' : '#e67e22';
            banner.innerHTML = '<div style="font-size:28px;font-weight:800;">' + score + '/' + totalSigns + ' correct (' + pct + '%)</div><div style="opacity:.9;margin-top:4px;">' + (pct >= 80 ? 'Excellent! Well done!' : 'Keep practising!') + '</div>';
            banner.scrollIntoView({behavior:'smooth'});

            document.getElementById('checkBtn').disabled = true;

            // Save result
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            saveResult(<?= $activity_id ?>, score, totalSigns, timeTaken);
        }

        function resetDragDrop() {
            placements = {};
            document.querySelectorAll('.sign-item').forEach(s => s.style.display = 'flex');
            document.querySelectorAll('.drop-zone').forEach(z => {
                z.className = 'drop-zone';
                const meaning = z.querySelector('.zone-text')?.textContent || z.textContent.trim();
                z.innerHTML = '<span class="zone-text">' + meaning + '</span>';
            });
            document.getElementById('checkBtn').disabled = true;
            document.getElementById('dndResult').style.display = 'none';
        }
        </script>

        <?php elseif (isset($content['type']) && $content['type'] === 'image_quiz'): ?>
        <!-- IMAGE QUIZ -->
        <div style="background:#eaf4ff;border-left:4px solid #3498db;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
            <strong style="color:#2471a3;">How to play:</strong>
            <span style="color:#555;font-size:14px;"> Look at each road sign and select the correct meaning.</span>
        </div>

        <div id="quizContainer">
            <?php
            $sign_styles = [
                'stop'     => ['bg'=>'#e74c3c','text'=>'STOP'],
                'yield'    => ['bg'=>'#e74c3c','text'=>'YIELD'],
                'no_entry' => ['bg'=>'#e74c3c','text'=>'NO ENTRY'],
                'speed_60' => ['bg'=>'white','text'=>'60'],
                'warning'  => ['bg'=>'#f1c40f','text'=>'!'],
            ];
            ?>
            <?php foreach ($content['questions'] as $qi => $qq): ?>
            <div class="card" style="margin-bottom:20px;" id="question-<?= $qi ?>">
                <p style="font-size:12px;font-weight:700;text-transform:uppercase;color:#999;margin-bottom:16px;">Question <?= $qi+1 ?> of <?= count($content['questions']) ?></p>

                <?php $s = $sign_styles[$qq['sign']] ?? ['bg'=>'#888','text'=>'?']; ?>
                <div class="sign-display" style="background:<?= $s['bg'] ?>;color:<?= $s['bg'] === 'white' ? '#333' : 'white' ?>;border:<?= $s['bg'] === 'white' ? '3px solid #e74c3c' : 'none' ?>;">
                    <?= $s['text'] ?>
                </div>

                <div class="quiz-options" id="options-<?= $qi ?>">
                    <?php foreach ($qq['options'] as $oi => $opt): ?>
                    <div class="quiz-opt"
                         onclick="selectOption(<?= $qi ?>, <?= $oi ?>, <?= $qq['correct'] ?>)"
                         id="opt-<?= $qi ?>-<?= $oi ?>">
                        <?= htmlspecialchars($opt) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;margin-top:20px;">
            <div id="imageQuizResult" style="display:none;" class="score-banner"></div>
            <button onclick="submitImageQuiz()" class="btn btn-primary btn-lg" id="submitImageQuiz">Submit Answers</button>
        </div>

        <script>
        const imageQuizAnswers = {};
        const correctAnswers   = <?= json_encode(array_column($content['questions'], 'correct')) ?>;
        const totalQ           = <?= count($content['questions']) ?>;
        const startTime2       = Date.now();

        function selectOption(qi, oi, correct) {
            // Clear previous selection for this question
            document.querySelectorAll('[id^="opt-' + qi + '-"]').forEach(el => {
                el.style.borderColor = '#e0e0e0';
                el.style.background  = 'white';
                el.style.color       = '#333';
            });
            const el = document.getElementById('opt-' + qi + '-' + oi);
            el.style.borderColor = '#3498db';
            el.style.background  = '#f0f8ff';
            imageQuizAnswers[qi] = oi;
        }

        function submitImageQuiz() {
            if (Object.keys(imageQuizAnswers).length < totalQ) {
                alert('Please answer all questions before submitting.');
                return;
            }
            let score = 0;
            Object.keys(imageQuizAnswers).forEach(qi => {
                const userAns    = imageQuizAnswers[qi];
                const correctAns = correctAnswers[qi];
                const correctEl  = document.getElementById('opt-' + qi + '-' + correctAns);
                const userEl     = document.getElementById('opt-' + qi + '-' + userAns);
                correctEl.classList.add('correct');
                if (userAns === correctAns) {
                    score++;
                } else {
                    userEl.classList.add('wrong');
                }
                // Disable all options
                document.querySelectorAll('[id^="opt-' + qi + '-"]').forEach(el => el.onclick = null);
            });

            const pct = Math.round(score / totalQ * 100);
            const result = document.getElementById('imageQuizResult');
            result.style.display = 'block';
            result.style.background = pct >= 80 ? '#27ae60' : '#e67e22';
            result.innerHTML = '<div style="font-size:28px;font-weight:800;">' + score + '/' + totalQ + ' correct (' + pct + '%)</div><div style="opacity:.9;margin-top:4px;">' + (pct >= 80 ? 'Excellent!' : 'Keep studying road signs!') + '</div>';
            result.scrollIntoView({behavior:'smooth'});
            document.getElementById('submitImageQuiz').disabled = true;

            const timeTaken = Math.floor((Date.now() - startTime2) / 1000);
            saveResult(<?= $activity_id ?>, score, totalQ, timeTaken);
        }
        </script>
        <?php endif; ?>

    <?php elseif ($activity['type'] === 'leaflet'): ?>
    <!-- LEAFLET MAP SIMULATION -->
    <div style="background:#eaf4ff;border-left:4px solid #27ae60;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
        <strong style="color:#1a7a4a;">Map Simulation:</strong>
        <span style="color:#555;font-size:14px;"> This is a real map of Windhoek, Namibia. Study the intersection and answer the questions below.</span>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <div id="map" style="height:400px;border-radius:12px;margin-bottom:24px;border:2px solid #e0e0e0;"></div>

    <!-- Traffic Scenarios -->
    <div id="scenarios">
        <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">Traffic Scenarios — Who goes first?</h2>

        <div class="card" style="margin-bottom:16px;" id="scenario1">
            <p style="font-weight:500;margin-bottom:12px;">Scenario 1: You are at an unmarked four-way intersection. A vehicle arrives from your right at the same time as you. Who has right of way?</p>
            <div style="display:flex;flex-direction:column;gap:8px;" id="opts1">
                <button onclick="checkScenario(1, 'b', this)" class="btn btn-outline" data-val="a" style="text-align:left;">A. You go first — you arrived at the same time</button>
                <button onclick="checkScenario(1, 'b', this)" class="btn btn-outline" data-val="b" style="text-align:left;">B. The vehicle on the right goes first</button>
                <button onclick="checkScenario(1, 'b', this)" class="btn btn-outline" data-val="c" style="text-align:left;">C. The largest vehicle goes first</button>
                <button onclick="checkScenario(1, 'b', this)" class="btn btn-outline" data-val="d" style="text-align:left;">D. The vehicle going straight goes first</button>
            </div>
            <div id="feedback1" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:14px;"></div>
        </div>

        <div class="card" style="margin-bottom:16px;" id="scenario2">
            <p style="font-weight:500;margin-bottom:12px;">Scenario 2: You want to turn right at an intersection. An oncoming vehicle is going straight. Who gives way?</p>
            <div style="display:flex;flex-direction:column;gap:8px;" id="opts2">
                <button onclick="checkScenario(2, 'a', this)" class="btn btn-outline" data-val="a" style="text-align:left;">A. You give way — the oncoming vehicle going straight has priority</button>
                <button onclick="checkScenario(2, 'a', this)" class="btn btn-outline" data-val="b" style="text-align:left;">B. The oncoming vehicle gives way to you</button>
                <button onclick="checkScenario(2, 'a', this)" class="btn btn-outline" data-val="c" style="text-align:left;">C. Both vehicles stop and wait</button>
                <button onclick="checkScenario(2, 'a', this)" class="btn btn-outline" data-val="d" style="text-align:left;">D. Flash your lights and proceed</button>
            </div>
            <div id="feedback2" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:14px;"></div>
        </div>

        <div class="card" style="margin-bottom:16px;" id="scenario3">
            <p style="font-weight:500;margin-bottom:12px;">Scenario 3: You are approaching a pedestrian crossing and a pedestrian is waiting on the pavement. What must you do?</p>
            <div style="display:flex;flex-direction:column;gap:8px;" id="opts3">
                <button onclick="checkScenario(3, 'c', this)" class="btn btn-outline" data-val="a" style="text-align:left;">A. Sound your horn to warn the pedestrian</button>
                <button onclick="checkScenario(3, 'c', this)" class="btn btn-outline" data-val="b" style="text-align:left;">B. Speed up to pass before the pedestrian crosses</button>
                <button onclick="checkScenario(3, 'c', this)" class="btn btn-outline" data-val="c" style="text-align:left;">C. Slow down and stop to let the pedestrian cross</button>
                <button onclick="checkScenario(3, 'c', this)" class="btn btn-outline" data-val="d" style="text-align:left;">D. Continue at the same speed — pedestrians must wait</button>
            </div>
            <div id="feedback3" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:14px;"></div>
        </div>

        <div style="text-align:center;margin-top:20px;">
            <div id="mapResult" style="display:none;" class="score-banner"></div>
            <button onclick="submitMapQuiz()" class="btn btn-primary btn-lg" id="submitMap">Submit Answers</button>
        </div>
    </div>

    <script>
    // Leaflet Map
    const map = L.map('map').setView([<?= $content['lat'] ?? -22.5609 ?>, <?= $content['lng'] ?? 17.0658 ?>], <?= $content['zoom'] ?? 15 ?>);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map);

    // Add markers for key locations
    L.marker([-22.5609, 17.0658]).addTo(map)
        .bindPopup('<strong>Windhoek City Centre</strong><br>Practice traffic scenarios here')
        .openPopup();

    L.marker([-22.5580, 17.0720]).addTo(map)
        .bindPopup('<strong>Four-Way Stop</strong><br>Who has right of way?');

    L.marker([-22.5640, 17.0640]).addTo(map)
        .bindPopup('<strong>Pedestrian Crossing</strong><br>You must stop for pedestrians');

    // Scenario answers
    const scenarioAnswers = {};
    const correctScenarios = { 1:'b', 2:'a', 3:'c' };
    const startTime3 = Date.now();

    function checkScenario(num, correct, btn) {
        const val = btn.dataset.val;
        scenarioAnswers[num] = val;

        // Highlight selection
        document.querySelectorAll('#opts' + num + ' button').forEach(b => {
            b.style.borderColor = '#e0e0e0';
            b.style.background  = 'white';
            b.style.color       = '#333';
        });
        btn.style.borderColor = '#3498db';
        btn.style.background  = '#f0f8ff';
    }

    function submitMapQuiz() {
        if (Object.keys(scenarioAnswers).length < 3) {
            alert('Please answer all 3 scenarios first.');
            return;
        }
        let score = 0;
        [1,2,3].forEach(num => {
            const correct  = correctScenarios[num];
            const userAns  = scenarioAnswers[num];
            const feedback = document.getElementById('feedback' + num);
            feedback.style.display = 'block';

            document.querySelectorAll('#opts' + num + ' button').forEach(b => {
                if (b.dataset.val === correct) {
                    b.style.borderColor = '#27ae60';
                    b.style.background  = '#eafaf1';
                    b.style.color       = '#27ae60';
                }
                if (b.dataset.val === userAns && userAns !== correct) {
                    b.style.borderColor = '#e74c3c';
                    b.style.background  = '#fdecea';
                    b.style.color       = '#e74c3c';
                }
                b.disabled = true;
            });

            if (userAns === correct) {
                score++;
                feedback.style.background = '#eafaf1';
                feedback.style.color = '#27ae60';
                feedback.textContent = 'Correct! Well done.';
            } else {
                feedback.style.background = '#fdecea';
                feedback.style.color = '#e74c3c';
                feedback.textContent = 'Incorrect. The correct answer is highlighted in green.';
            }
        });

        const pct = Math.round(score / 3 * 100);
        const result = document.getElementById('mapResult');
        result.style.display = 'block';
        result.style.background = pct >= 80 ? '#27ae60' : '#e67e22';
        result.innerHTML = '<div style="font-size:28px;font-weight:800;">' + score + '/3 correct (' + pct + '%)</div><div style="opacity:.9;margin-top:4px;">' + (pct >= 80 ? 'Excellent traffic knowledge!' : 'Study the traffic rules and try again!') + '</div>';
        result.scrollIntoView({behavior:'smooth'});
        document.getElementById('submitMap').disabled = true;

        const timeTaken = Math.floor((Date.now() - startTime3) / 1000);
        saveResult(<?= $activity_id ?>, score, 3, timeTaken);
    }
    </script>

    <?php elseif ($activity['type'] === 'threejs'): ?>
    <!-- THREE.JS ANIMATION -->
    <div style="background:#f3e8ff;border-left:4px solid #8e44ad;border-radius:10px;padding:14px 20px;margin-bottom:24px;">
        <strong style="color:#6c3483;">3D Animation:</strong>
        <span style="color:#555;font-size:14px;"> Watch the traffic scenario and answer the hazard perception questions.</span>
    </div>

    <canvas id="threeCanvas" style="width:100%;height:400px;border-radius:12px;border:2px solid #e0e0e0;display:block;margin-bottom:24px;"></canvas>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
    // Three.js Road Scene
    const canvas   = document.getElementById('threeCanvas');
    const renderer = new THREE.WebGLRenderer({ canvas, antialias:true });
    renderer.setSize(canvas.offsetWidth, 400);
    renderer.setClearColor(0x87CEEB); // Sky blue

    const scene  = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(60, canvas.offsetWidth / 400, 0.1, 1000);
    camera.position.set(0, 8, 16);
    camera.lookAt(0, 0, 0);

    // Lighting
    const ambient = new THREE.AmbientLight(0xffffff, 0.6);
    scene.add(ambient);
    const sun = new THREE.DirectionalLight(0xffffff, 0.8);
    sun.position.set(10, 20, 10);
    scene.add(sun);

    // Road surface
    const roadGeo  = new THREE.PlaneGeometry(6, 30);
    const roadMat  = new THREE.MeshLambertMaterial({ color: 0x555555 });
    const road     = new THREE.Mesh(roadGeo, roadMat);
    road.rotation.x = -Math.PI / 2;
    scene.add(road);

    // Pavement left
    const pavL = new THREE.Mesh(new THREE.PlaneGeometry(4, 30), new THREE.MeshLambertMaterial({ color: 0xccbbaa }));
    pavL.rotation.x = -Math.PI / 2;
    pavL.position.x = -5;
    scene.add(pavL);

    // Pavement right
    const pavR = new THREE.Mesh(new THREE.PlaneGeometry(4, 30), new THREE.MeshLambertMaterial({ color: 0xccbbaa }));
    pavR.rotation.x = -Math.PI / 2;
    pavR.position.x = 5;
    scene.add(pavR);

    // Centre line markings
    for (let i = -12; i < 12; i += 3) {
        const line = new THREE.Mesh(
            new THREE.PlaneGeometry(0.15, 1.5),
            new THREE.MeshLambertMaterial({ color: 0xffffff })
        );
        line.rotation.x = -Math.PI / 2;
        line.position.set(0, 0.01, i);
        scene.add(line);
    }

    // Car (player's car) — blue box
    const carGeo = new THREE.BoxGeometry(1.6, 0.8, 3);
    const carMat = new THREE.MeshLambertMaterial({ color: 0x3498db });
    const car    = new THREE.Mesh(carGeo, carMat);
    car.position.set(-1.2, 0.4, 8);
    scene.add(car);

    // Windscreen
    const wsGeo = new THREE.BoxGeometry(1.4, 0.5, 0.1);
    const wsMat = new THREE.MeshLambertMaterial({ color: 0x87CEEB, transparent:true, opacity:0.6 });
    const ws    = new THREE.Mesh(wsGeo, wsMat);
    ws.position.set(-1.2, 0.85, 6.7);
    scene.add(ws);

    // Oncoming car — red box
    const carR   = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.8, 3), new THREE.MeshLambertMaterial({ color: 0xe74c3c }));
    carR.position.set(1.2, 0.4, -10);
    scene.add(carR);

    // Pedestrian (yellow cylinder)
    const pedGeo = new THREE.CylinderGeometry(0.2, 0.2, 1.6, 8);
    const pedMat = new THREE.MeshLambertMaterial({ color: 0xf1c40f });
    const ped    = new THREE.Mesh(pedGeo, pedMat);
    ped.position.set(3.5, 0.8, 2);
    scene.add(ped);

    // Stop sign post
    const postGeo = new THREE.CylinderGeometry(0.05, 0.05, 2, 8);
    const postMat = new THREE.MeshLambertMaterial({ color: 0x888888 });
    const post    = new THREE.Mesh(postGeo, postMat);
    post.position.set(3.2, 1, -2);
    scene.add(post);

    // Stop sign board
    const signGeo = new THREE.BoxGeometry(0.8, 0.8, 0.05);
    const signMat = new THREE.MeshLambertMaterial({ color: 0xe74c3c });
    const sign    = new THREE.Mesh(signGeo, signMat);
    sign.position.set(3.2, 2.1, -2);
    scene.add(sign);

    // Trees
    [[4,0,-5],[4,0,3],[-4,0,-8],[-4,0,5]].forEach(([x,y,z]) => {
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.15,0.2,1.5,8), new THREE.MeshLambertMaterial({color:0x8B4513}));
        trunk.position.set(x, 0.75, z);
        scene.add(trunk);
        const top = new THREE.Mesh(new THREE.SphereGeometry(0.8,8,8), new THREE.MeshLambertMaterial({color:0x228B22}));
        top.position.set(x, 2, z);
        scene.add(top);
    });

    // Animation
    let frame = 0;
    let hazardClicked = false;

    function animate() {
        requestAnimationFrame(animate);
        frame++;

        // Move oncoming car
        carR.position.z += 0.05;
        if (carR.position.z > 15) carR.position.z = -15;

        // Pedestrian moves toward road slowly
        if (ped.position.x > 3.0) ped.position.x -= 0.008;

        // Camera slight bob
        camera.position.y = 8 + Math.sin(frame * 0.02) * 0.1;

        renderer.render(scene, camera);
    }
    animate();

    // Click to detect hazard
    canvas.addEventListener('click', () => {
        if (!hazardClicked) {
            hazardClicked = true;
            document.getElementById('hazardFeedback').style.display = 'block';
        }
    });
    </script>

    <div id="hazardFeedback" style="display:none;background:#eafaf1;border-left:4px solid #27ae60;border-radius:10px;padding:14px 20px;margin-bottom:20px;">
        <strong style="color:#27ae60;">Hazard Detected!</strong>
        <p style="color:#555;font-size:14px;margin-top:4px;">Good observation! In this scene there are multiple hazards: an oncoming vehicle, a pedestrian approaching the road, and a stop sign ahead. Always scan the full road ahead.</p>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h3 style="font-size:16px;margin-bottom:14px;">Hazard Perception Questions</h3>

        <p style="font-weight:500;margin-bottom:10px;">Q1. How many hazards can you identify in the scene above?</p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;" id="hq1">
            <button onclick="answerHQ(1,'b',this,'b')" class="btn btn-outline" data-val="a" style="text-align:left;">A. One hazard — the oncoming car</button>
            <button onclick="answerHQ(1,'b',this,'b')" class="btn btn-outline" data-val="b" style="text-align:left;">B. Three hazards — oncoming car, pedestrian, stop sign</button>
            <button onclick="answerHQ(1,'b',this,'b')" class="btn btn-outline" data-val="c" style="text-align:left;">C. No hazards — the road is clear</button>
        </div>

        <p style="font-weight:500;margin-bottom:10px;">Q2. What should you do when you see a pedestrian approaching the road?</p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;" id="hq2">
            <button onclick="answerHQ(2,'c',this,'c')" class="btn btn-outline" data-val="a" style="text-align:left;">A. Sound your horn and continue</button>
            <button onclick="answerHQ(2,'c',this,'c')" class="btn btn-outline" data-val="b" style="text-align:left;">B. Speed up to pass before they reach the road</button>
            <button onclick="answerHQ(2,'c',this,'c')" class="btn btn-outline" data-val="c" style="text-align:left;">C. Slow down and be prepared to stop</button>
        </div>

        <div style="text-align:center;">
            <div id="hqResult" style="display:none;" class="score-banner"></div>
            <button onclick="submitHQ()" class="btn btn-primary">Submit Answers</button>
        </div>
    </div>

    <script>
    const hqAnswers = {};
    const startTime4 = Date.now();

    function answerHQ(q, correct, btn, val) {
        hqAnswers[q] = btn.dataset.val;
        document.querySelectorAll('#hq' + q + ' button').forEach(b => {
            b.style.borderColor = '#e0e0e0';
            b.style.background  = 'white';
            b.style.color       = '#333';
        });
        btn.style.borderColor = '#3498db';
        btn.style.background  = '#f0f8ff';
    }

    function submitHQ() {
        if (Object.keys(hqAnswers).length < 2) {
            alert('Please answer both questions.');
            return;
        }
        const correct = {1:'b', 2:'c'};
        let score = 0;
        [1,2].forEach(q => {
            document.querySelectorAll('#hq' + q + ' button').forEach(b => {
                if (b.dataset.val === correct[q]) {
                    b.style.borderColor = '#27ae60';
                    b.style.background  = '#eafaf1';
                    b.style.color       = '#27ae60';
                }
                if (b.dataset.val === hqAnswers[q] && hqAnswers[q] !== correct[q]) {
                    b.style.borderColor = '#e74c3c';
                    b.style.background  = '#fdecea';
                    b.style.color       = '#e74c3c';
                }
                b.disabled = true;
            });
            if (hqAnswers[q] === correct[q]) score++;
        });

        const pct = Math.round(score / 2 * 100);
        const result = document.getElementById('hqResult');
        result.style.display = 'block';
        result.style.background = score >= 2 ? '#27ae60' : '#e67e22';
        result.innerHTML = '<div style="font-size:24px;font-weight:800;">' + score + '/2 correct</div><div style="opacity:.9;margin-top:4px;">' + (score >= 2 ? 'Perfect hazard awareness!' : 'Keep practising hazard perception!') + '</div>';
        result.scrollIntoView({behavior:'smooth'});

        const timeTaken = Math.floor((Date.now() - startTime4) / 1000);
        saveResult(<?= $activity_id ?>, score, 2, timeTaken);
    }
    </script>

    <?php endif; ?>

    <!-- Back button -->
    <div style="text-align:center;margin-top:30px;padding-bottom:40px;">
        <a href="/testmate/activities.php" class="btn btn-outline">Back to All Activities</a>
        <a href="/testmate/dashboard.php" class="btn btn-primary" style="margin-left:10px;">Dashboard</a>
    </div>

</div>

<!-- Save Result Function (shared) -->
<script>
function saveResult(activityId, score, maxScore, timeSpent) {
    fetch('/testmate/activities.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'complete_activity=1&activity_id=' + activityId + '&score=' + score + '&max_score=' + maxScore + '&time_spent=' + timeSpent
    });
}
</script>

<?php include 'includes/footer.php'; ?>