<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

$msg     = '';
$edit_q  = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($edit_id > 0) {
    $eq = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $eq->bind_param("i", $edit_id);
    $eq->execute();
    $edit_q = $eq->get_result()->fetch_assoc();
}

$topics = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// All simulations, embedded as JSON for the JS to filter by topic + know each one's animation_type
$all_simulations = $conn->query("SELECT id, topic_id, title, animation_type FROM simulations WHERE is_active = 1 ORDER BY topic_id, id")->fetch_all(MYSQLI_ASSOC);

// ═══════════════════════════════════════════════════════
// POST handling
// ═══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtype = $_POST['question_type'] ?? 'mcq';

    // ── MCQ (existing behaviour, unchanged) ──
    if ($qtype === 'mcq') {
        $tid        = (int)$_POST['topic_id'];
        $qtext      = trim($_POST['question']);
        $oa         = trim($_POST['option_a']);
        $ob         = trim($_POST['option_b']);
        $oc         = trim($_POST['option_c']);
        $od         = trim($_POST['option_d']);
        $ans        = strtoupper(trim($_POST['correct_answer']));
        $expl       = trim($_POST['explanation']);
        $pid        = (int)($_POST['edit_id'] ?? 0);
        $image_path = $edit_q['image_path'] ?? null;
        $file_type  = $edit_q['file_type'] ?? 'image';

        if (!empty($_FILES['question_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
            $type_map = [
                'jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image','webp'=>'image',
                'pdf'=>'pdf',
                'mp4'=>'video','webm'=>'video','mov'=>'video',
            ];
            if (isset($type_map[$ext])) {
                $file_type  = $type_map[$ext];
                $filename   = 'q_' . time() . '_' . rand(100,999) . '.' . $ext;
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/testmate/uploads/questions/';
                if (move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = '/testmate/uploads/questions/' . $filename;
                } else {
                    $msg = "❌ Upload failed. Check uploads/questions/ folder exists.";
                }
            } else {
                $msg = "❌ Only JPG, PNG, GIF, WEBP, PDF, MP4, WEBM or MOV allowed.";
            }
        }

        if (isset($_POST['remove_image'])) {
            $image_path = null;
            $file_type  = 'image';
        }

        if (!$msg) {
            if ($pid > 0) {
                $s = $conn->prepare("UPDATE questions SET topic_id=?,question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_answer=?,explanation=?,image_path=?,file_type=? WHERE id=?");
                $s->bind_param("issssssssi", $tid,$qtext,$oa,$ob,$oc,$od,$ans,$expl,$image_path,$file_type,$pid);
                $s->execute();
                $msg = "✅ MCQ question updated!";
                $eq2 = $conn->prepare("SELECT * FROM questions WHERE id = ?");
                $eq2->bind_param("i", $pid);
                $eq2->execute();
                $edit_q  = $eq2->get_result()->fetch_assoc();
                $edit_id = $pid;
            } else {
                $s = $conn->prepare("INSERT INTO questions (topic_id,question,option_a,option_b,option_c,option_d,correct_answer,explanation,image_path,file_type) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $s->bind_param("isssssssss", $tid,$qtext,$oa,$ob,$oc,$od,$ans,$expl,$image_path,$file_type);
                $s->execute();
                $msg    = "✅ MCQ question added!";
                $edit_q = null;
                $edit_id = 0;
            }
        }
    }

    // ── VIDEO SCENARIO ──
    elseif ($qtype === 'video') {
        $sim_id    = (int)$_POST['simulation_id'];
        $anim_type = $_POST['animation_type'] ?? '';
        $is_correct = ($_POST['is_correct'] ?? '') === '1';
        $expl      = trim($_POST['video_explanation']);
        $qtext     = trim($_POST['video_question']);

        // Fetch the simulation to append this scenario to its scenario_data JSON
        $s = $conn->prepare("SELECT * FROM simulations WHERE id = ?");
        $s->bind_param("i", $sim_id);
        $s->execute();
        $sim = $s->get_result()->fetch_assoc();

        if (!$sim) {
            $msg = "❌ Please choose a valid simulation.";
        } else {
            $scenarios = json_decode($sim['scenario_data'], true) ?: [];
            $new_index = count($scenarios);

            $scenario = ['id' => $new_index + 1, 'is_correct' => $is_correct, 'explanation' => $expl];

            switch ($anim_type) {
                case 'traffic_light':
                    $scenario['light']       = $_POST['light'] ?? 'red';
                    $scenario['car_passes']  = ($_POST['car_passes'] ?? '') === '1';
                    break;
                case 'road_signs':
                    $scenario['sign']         = $_POST['sign'] ?? 'stop';
                    $scenario['driver_stops'] = ($_POST['driver_stops'] ?? '') === '1';
                    break;
                case 'four_way_stop':
                    $scenario['arriving_order'] = $_POST['arriving_order'] ?? 'first';
                    $scenario['action']         = $_POST['fw_action'] ?? 'go';
                    break;
                case 'speed':
                    $scenario['limit'] = (int)($_POST['limit'] ?? 0);
                    $scenario['speed'] = (int)($_POST['speed'] ?? 0);
                    break;
                case 'parking':
                    $scenario['location'] = $_POST['location'] ?? 'legal';
                    break;
                case 'vehicle_check':
                    $scenario['action'] = $_POST['vc_action'] ?? '';
                    $scenario['label']  = trim($_POST['vc_label'] ?? '');
                    break;
            }

            $scenarios[] = $scenario;

            $upd = $conn->prepare("UPDATE simulations SET scenario_data = ? WHERE id = ?");
            $json = json_encode($scenarios);
            $upd->bind_param("si", $json, $sim_id);
            $upd->execute();

            $correct_answer = $is_correct ? 'correct' : 'incorrect';
            $ins = $conn->prepare("INSERT INTO simulation_questions (simulation_id, scenario_index, question, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("iisss", $sim_id, $new_index, $qtext, $correct_answer, $expl);
            $ins->execute();

            $msg = "✅ Video scenario added to \"" . htmlspecialchars($sim['title']) . "\"!";
        }
    }

    // ── DRAG & DROP PAIR ──
    elseif ($qtype === 'dragdrop') {
        $tid    = (int)$_POST['dd_topic_id'];
        $value  = trim($_POST['dd_value']);
        $target = trim($_POST['dd_target']);

        if ($tid > 0 && $value !== '' && $target !== '') {
            $ins = $conn->prepare("INSERT INTO dragdrop_pairs (topic_id, value, target) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $tid, $value, $target);
            $ins->execute();
            $msg = "✅ Drag & Drop pair added!";
        } else {
            $msg = "❌ Please fill in the topic, value, and target.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Question – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;}
        select,textarea,input[type="text"],input[type="number"]{width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;font-family:inherit;outline:none;transition:border-color .2s;}
        select:focus,textarea:focus,input:focus{border-color:#3498db;}
        textarea{resize:vertical;}
        .upload-area{border:2px dashed #ddd;border-radius:10px;padding:24px;text-align:center;cursor:pointer;background:#fafafa;position:relative;transition:all .2s;}
        .upload-area:hover{border-color:#3498db;background:#f0f8ff;}
        .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}

        .type-tabs{display:flex;gap:10px;margin-bottom:24px;}
        .type-tab{flex:1;padding:16px;border:2px solid #e0e0e0;border-radius:10px;text-align:center;cursor:pointer;background:white;transition:all .15s;}
        .type-tab.active{border-color:#3498db;background:#f0f8ff;}
        .type-tab .ti{font-size:24px;display:block;margin-bottom:6px;}
        .type-tab .tl{font-size:13px;font-weight:700;color:#2c3e50;}
        .type-section{display:none;}
        .type-section.active{display:block;}
        .yn-toggle{display:flex;gap:10px;}
        .yn-toggle label{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:2px solid #e0e0e0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;transition:all .15s;}
        .yn-toggle input{accent-color:#3498db;}
    </style>
</head>
<body>
<nav class="navbar">
    <a href="/testmate/admin/index.php" class="brand"><span class="brand-icon">T</span> TestMate Admin</a>
    <div class="nav-links">
        <a href="/testmate/index.php" style="color:rgba(255,255,255,.8);font-size:14px;">← View Site</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
<div class="admin-wrap">
    <div class="sidebar">
        <h3>Main</h3>
        <a href="/testmate/admin/index.php">📊 Dashboard</a>
        <a href="/testmate/admin/users.php">👥 Users</a>
        <a href="/testmate/admin/stats.php">📈 Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">❓ All Questions</a>
        <a href="/testmate/admin/add-question.php" class="active">➕ Add Question</a>
        <a href="/testmate/admin/review-scores.php">✅ Review Scores</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
        <a href="/testmate/admin/add-material.php">➕ Add Material</a>
    </div>
    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:20px;">
            <?= $edit_id ? '✏️ Edit Question' : '➕ Add New Question' ?>
        </h1>

        <?php if ($msg): ?>
        <div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px;max-width:700px;">
            <?= $msg ?>
        </div>
        <?php endif; ?>

        <div class="card" style="max-width:700px;padding:24px;">

            <?php if (!$edit_id): ?>
            <!-- Question type selector (hidden entirely when editing an MCQ) -->
            <div class="type-tabs">
                <div class="type-tab active" data-type="mcq" onclick="selectType('mcq')">
                    <span class="ti">📝</span>
                    <span class="tl">Multiple Choice</span>
                </div>
                <div class="type-tab" data-type="video" onclick="selectType('video')">
                    <span class="ti">🎬</span>
                    <span class="tl">Video Scenario</span>
                </div>
                <div class="type-tab" data-type="dragdrop" onclick="selectType('dragdrop')">
                    <span class="ti">🧩</span>
                    <span class="tl">Drag &amp; Drop</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════ MCQ FORM ═══════════ -->
            <form method="POST" enctype="multipart/form-data" id="mcqForm" class="type-section active" data-section="mcq">
                <input type="hidden" name="question_type" value="mcq">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

                <div class="form-group">
                    <label>Topic</label>
                    <select name="topic_id" required>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"
                            <?= ($edit_q && $edit_q['topic_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= $t['icon'] ?? '' ?> <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question" rows="3" required
                        placeholder="Type your question here..."><?= htmlspecialchars($edit_q['question'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Question File <span style="color:#999;font-weight:400;">(optional — image, PDF, or video shown above the question)</span></label>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="question_image" accept="image/*,.pdf,video/*" onchange="previewImage(this)">
                        <span style="font-size:2rem;display:block;margin-bottom:8px;">📎</span>
                        <p style="font-size:14px;color:#666;margin:0;"><strong style="color:#3498db;">Click to upload</strong> or drag and drop</p>
                        <p style="font-size:12px;color:#999;margin-top:4px;">JPG, PNG, GIF, WEBP, PDF, MP4, WEBM, MOV — max 5MB</p>
                    </div>
                    <div id="imagePreview" style="display:none;margin-top:12px;">
                        <img id="previewImg" src="" style="max-width:200px;border-radius:8px;border:1px solid #eee;display:none;">
                        <p id="previewFileName" style="font-size:13px;color:#2c3e50;margin-bottom:0;"></p>
                        <p style="font-size:12px;color:#27ae60;margin-top:6px;">✅ File selected</p>
                    </div>
                    <?php if ($edit_q && $edit_q['image_path']): ?>
                    <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:8px;">
                        <p style="font-size:13px;color:#666;margin-bottom:8px;">Current file (<?= htmlspecialchars($edit_q['file_type'] ?? 'image') ?>):</p>
                        <?php if (($edit_q['file_type'] ?? 'image') === 'image'): ?>
                        <img src="<?= htmlspecialchars($edit_q['image_path']) ?>" style="max-width:180px;border-radius:6px;">
                        <?php elseif (($edit_q['file_type'] ?? '') === 'pdf'): ?>
                        <a href="<?= htmlspecialchars($edit_q['image_path']) ?>" target="_blank">📄 View current PDF</a>
                        <?php else: ?>
                        <video src="<?= htmlspecialchars($edit_q['image_path']) ?>" controls style="max-width:220px;border-radius:6px;"></video>
                        <?php endif; ?>
                        <br>
                        <label style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="remove_image"> Remove file
                        </label>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $key=>$label): ?>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Option <?= $label ?></label>
                        <input type="text" name="option_<?= $key ?>" required
                               placeholder="Option <?= $label ?>"
                               value="<?= htmlspecialchars($edit_q['option_'.$key] ?? '') ?>">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-group" style="margin-top:16px;">
                    <label>Correct Answer</label>
                    <select name="correct_answer" required>
                        <?php foreach (['A','B','C','D'] as $opt): ?>
                        <option value="<?= $opt ?>"
                            <?= ($edit_q && strtoupper($edit_q['correct_answer']) === $opt) ? 'selected' : '' ?>>
                            <?= $opt ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Explanation <span style="color:#999;font-weight:400;">(shown after answer)</span></label>
                    <textarea name="explanation" rows="2"
                        placeholder="Explain why this answer is correct..."><?= htmlspecialchars($edit_q['explanation'] ?? '') ?></textarea>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_id ? '💾 Update Question' : '➕ Add Question' ?>
                    </button>
                    <a href="/testmate/admin/questions.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>

            <!-- ═══════════ VIDEO SCENARIO FORM ═══════════ -->
            <?php if (!$edit_id): ?>
            <form method="POST" id="videoForm" class="type-section" data-section="video">
                <input type="hidden" name="question_type" value="video">
                <input type="hidden" name="animation_type" id="videoAnimType" value="">

                <div class="form-group">
                    <label>Topic</label>
                    <select id="videoTopicSelect" onchange="filterSimulations()" required>
                        <option value="">Choose a topic...</option>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= $t['icon'] ?? '' ?> <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Simulation <span style="color:#999;font-weight:400;">(video series this scenario belongs to)</span></label>
                    <select name="simulation_id" id="simulationSelect" onchange="onSimulationChange()" required>
                        <option value="">Choose a topic first...</option>
                    </select>
                </div>

                <!-- Traffic Light fields -->
                <div class="video-fields" data-anim="traffic_light" style="display:none;">
                    <div class="form-group">
                        <label>Light Colour</label>
                        <select name="light">
                            <option value="red">Red</option>
                            <option value="amber">Amber</option>
                            <option value="green">Green</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Car's Action</label>
                        <div class="yn-toggle">
                            <label><input type="radio" name="car_passes" value="1"> Drives through</label>
                            <label><input type="radio" name="car_passes" value="0" checked> Stops</label>
                        </div>
                    </div>
                </div>

                <!-- Road Signs fields -->
                <div class="video-fields" data-anim="road_signs" style="display:none;">
                    <div class="form-group">
                        <label>Sign</label>
                        <select name="sign">
                            <option value="stop">STOP</option>
                            <option value="yield">YIELD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Driver's Action</label>
                        <div class="yn-toggle">
                            <label><input type="radio" name="driver_stops" value="1" checked> Stops / gives way</label>
                            <label><input type="radio" name="driver_stops" value="0"> Does not stop</label>
                        </div>
                    </div>
                </div>

                <!-- Four-Way Stop fields -->
                <div class="video-fields" data-anim="four_way_stop" style="display:none;">
                    <div class="form-group">
                        <label>Arriving Order</label>
                        <select name="arriving_order">
                            <option value="first">First to arrive</option>
                            <option value="second">Second to arrive</option>
                            <option value="same_time_right">Arrived together — on the right</option>
                            <option value="same_time_left">Arrived together — on the left</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle's Action</label>
                        <select name="fw_action">
                            <option value="go">Proceeds</option>
                            <option value="wait">Waits</option>
                        </select>
                    </div>
                </div>

                <!-- Speed fields -->
                <div class="video-fields" data-anim="speed" style="display:none;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Speed Limit (km/h)</label>
                            <input type="number" name="limit" placeholder="e.g. 60">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Driver's Speed (km/h)</label>
                            <input type="number" name="speed" placeholder="e.g. 60">
                        </div>
                    </div>
                </div>

                <!-- Parking fields -->
                <div class="video-fields" data-anim="parking" style="display:none;">
                    <div class="form-group">
                        <label>Parking Location</label>
                        <select name="location">
                            <option value="legal">Legal parking bay</option>
                            <option value="pavement">Pavement</option>
                            <option value="yellow">Yellow kerb</option>
                            <option value="crossing">Near a pedestrian crossing</option>
                            <option value="intersection">Near an intersection</option>
                            <option value="driveway">In front of a driveway</option>
                            <option value="hydrant">Near a fire hydrant</option>
                        </select>
                    </div>
                </div>

                <!-- Vehicle Check fields -->
                <div class="video-fields" data-anim="vehicle_check" style="display:none;">
                    <div class="form-group">
                        <label>Action Key <span style="color:#999;font-weight:400;">(internal identifier, e.g. seatbelt_on)</span></label>
                        <input type="text" name="vc_action" placeholder="e.g. seatbelt_on">
                    </div>
                    <div class="form-group">
                        <label>On-screen Label</label>
                        <input type="text" name="vc_label" placeholder="e.g. Seatbelt: ON">
                    </div>
                </div>

                <div class="form-group">
                    <label>Was this action Correct or Incorrect?</label>
                    <div class="yn-toggle">
                        <label><input type="radio" name="is_correct" value="1" checked> ✅ Correct</label>
                        <label><input type="radio" name="is_correct" value="0"> ❌ Incorrect</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quiz Question Text <span style="color:#999;font-weight:400;">(shown to the learner in Quiz Mode)</span></label>
                    <textarea name="video_question" rows="2" required placeholder="e.g. A car approaches a RED light and drives through. Was this correct?"></textarea>
                </div>

                <div class="form-group">
                    <label>Explanation <span style="color:#999;font-weight:400;">(shown after the answer)</span></label>
                    <textarea name="video_explanation" rows="2" required placeholder="Explain why this action was correct or incorrect..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">➕ Add Video Scenario</button>
            </form>

            <!-- ═══════════ DRAG & DROP FORM ═══════════ -->
            <form method="POST" id="dragdropForm" class="type-section" data-section="dragdrop">
                <input type="hidden" name="question_type" value="dragdrop">

                <div class="form-group">
                    <label>Topic</label>
                    <select name="dd_topic_id" required>
                        <option value="">Choose a topic...</option>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= $t['icon'] ?? '' ?> <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Value <span style="color:#999;font-weight:400;">(the draggable chip text)</span></label>
                    <input type="text" name="dd_value" required placeholder="e.g. Urban area / town">
                </div>

                <div class="form-group">
                    <label>Target <span style="color:#999;font-weight:400;">(the drop-zone text it must be matched to)</span></label>
                    <input type="text" name="dd_target" required placeholder="e.g. 60 km/h">
                </div>

                <button type="submit" class="btn btn-primary">➕ Add Drag &amp; Drop Pair</button>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const isImage = file.type.startsWith('image/');
        const imgEl = document.getElementById('previewImg');
        const nameEl = document.getElementById('previewFileName');

        if (isImage) {
            const reader = new FileReader();
            reader.onload = e => { imgEl.src = e.target.result; imgEl.style.display = 'block'; };
            reader.readAsDataURL(file);
            nameEl.textContent = '';
        } else {
            imgEl.style.display = 'none';
            nameEl.textContent = '📎 ' + file.name;
        }
        document.getElementById('imagePreview').style.display = 'block';
        document.getElementById('uploadArea').style.borderColor = '#27ae60';
    }
}

function selectType(type) {
    document.querySelectorAll('.type-tab').forEach(t => t.classList.toggle('active', t.dataset.type === type));
    document.querySelectorAll('.type-section').forEach(s => s.classList.toggle('active', s.dataset.section === type));
}

// All active simulations, for client-side filtering by topic
const ALL_SIMULATIONS = <?= json_encode($all_simulations) ?>;

function filterSimulations() {
    const topicId = document.getElementById('videoTopicSelect').value;
    const sel = document.getElementById('simulationSelect');
    sel.innerHTML = '';

    if (!topicId) {
        sel.innerHTML = '<option value="">Choose a topic first...</option>';
        return;
    }

    const matches = ALL_SIMULATIONS.filter(s => String(s.topic_id) === String(topicId));
    if (matches.length === 0) {
        sel.innerHTML = '<option value="">No simulations yet for this topic</option>';
        document.querySelectorAll('.video-fields').forEach(f => f.style.display = 'none');
        return;
    }

    sel.innerHTML = '<option value="">Choose a simulation...</option>' +
        matches.map(s => `<option value="${s.id}" data-anim="${s.animation_type}">${s.title} (${s.animation_type})</option>`).join('');
    onSimulationChange();
}

function onSimulationChange() {
    const sel = document.getElementById('simulationSelect');
    const opt = sel.options[sel.selectedIndex];
    const anim = opt ? opt.dataset.anim : '';

    document.getElementById('videoAnimType').value = anim || '';
    document.querySelectorAll('.video-fields').forEach(f => {
        f.style.display = (f.dataset.anim === anim) ? 'block' : 'none';
    });
}
</script>
</body>
</html>s