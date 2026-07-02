<?php
require 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$topics = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$topic  = null;
$materials = [];
$questions = [];

if (isset($_GET['topic'])) {
    $topic_id = (int)$_GET['topic'];

    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $topic_id);
    $t->execute();
    $topic = $t->get_result()->fetch_assoc();

    // Get admin-added materials
    $m = $conn->prepare("SELECT * FROM materials WHERE topic_id = ? ORDER BY sort_order ASC, id ASC");
    $m->bind_param("i", $topic_id);
    $m->execute();
    $materials = $m->get_result()->fetch_all(MYSQLI_ASSOC);

    // Sample questions preview
    $q = $conn->prepare("SELECT * FROM questions WHERE topic_id = ? ORDER BY RAND() LIMIT 3");
    $q->bind_param("i", $topic_id);
    $q->execute();
    $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>📚 Study Materials</h1>
    <p>Choose a topic to study. Take the quiz after each topic to test yourself.</p>
</div>

<div class="container">

    <!-- Topic Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-bottom:40px;">
        <?php foreach ($topics as $tp): ?>
        <div class="card" style="padding:28px;transition:transform .2s;<?= (isset($topic) && $topic['id'] == $tp['id']) ? 'border:2px solid #3498db;' : '' ?>"
             onmouseover="this.style.transform='translateY(-4px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size:2.5rem;margin-bottom:12px;"><?= $tp['icon'] ?></div>
            <h2 style="font-size:18px;margin-bottom:8px;"><?= htmlspecialchars($tp['name']) ?></h2>
            <p style="color:#666;font-size:14px;margin-bottom:20px;line-height:1.6;"><?= htmlspecialchars($tp['description']) ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/testmate/study-materials.php?topic=<?= $tp['id'] ?>" class="btn btn-outline" style="font-size:14px;padding:8px 16px;">📖 Study</a>
                <a href="/testmate/quiz.php?topic=<?= $tp['id'] ?>" class="btn btn-primary" style="font-size:14px;padding:8px 16px;">✅ Quiz</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($topic): ?>

    <!-- Topic Content -->
    <div style="border-top:2px solid #eee;padding-top:32px;">

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
            <h2 style="font-size:22px;"><?= $topic['icon'] ?> <?= htmlspecialchars($topic['name']) ?></h2>
            <a href="/testmate/quiz.php?topic=<?= $topic['id'] ?>" class="btn btn-primary">Take the Quiz →</a>
        </div>

        <?php if (!empty($materials)): ?>
        <!-- Admin-added chapters -->
        <?php foreach ($materials as $mat): ?>
        <div class="card" style="margin-bottom:20px;">

            <h3 style="font-size:18px;margin-bottom:12px;color:#2c3e50;">
                <?= htmlspecialchars($mat['title']) ?>
            </h3>

            <?php if ($mat['file_type'] === 'image' && $mat['file_path']): ?>
            <img src="<?= htmlspecialchars($mat['file_path']) ?>"
                 style="max-width:100%;border-radius:8px;margin-bottom:14px;display:block;">
            <?php endif; ?>

            <?php if ($mat['content']): ?>
            <p style="color:#444;font-size:15px;line-height:1.8;white-space:pre-line;">
                <?= htmlspecialchars($mat['content']) ?>
            </p>
            <?php endif; ?>

            <?php if ($mat['file_type'] === 'pdf' && $mat['file_path']): ?>
            <div style="margin-top:16px;">
                <a href="<?= htmlspecialchars($mat['file_path']) ?>" target="_blank"
                   class="btn btn-outline" style="font-size:14px;">
                    📄 View / Download PDF
                </a>
                <div style="margin-top:12px;border-radius:8px;overflow:hidden;border:1px solid #eee;">
                    <iframe src="<?= htmlspecialchars($mat['file_path']) ?>"
                            width="100%" height="500px" style="display:block;border:none;">
                    </iframe>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <!-- Built-in content if no admin materials yet -->
        <?php
        $builtin = [
            1 => [
                ['title'=>'🔴 Regulatory Signs','text'=>'Regulatory signs tell you what you MUST or MUST NOT do. They are usually circular with a red border. Examples include STOP signs (octagonal, red), No Entry signs (red circle with white bar), speed limit signs (red circle with number), and no overtaking signs.'],
                ['title'=>'⚠️ Warning Signs','text'=>'Warning signs alert you to hazards ahead. They are yellow diamond or triangle shaped with black symbols. Examples: sharp bend ahead, pedestrian crossing, railway crossing, slippery road, animals crossing.'],
                ['title'=>'🔵 Informational Signs','text'=>'Informational signs guide you. Blue rectangular signs show directions and distances. Blue circular signs with white arrows are mandatory direction signs — you MUST go in the direction shown.'],
                ['title'=>'🚦 Traffic Lights','text'=>'RED = Stop completely. AMBER = Stop if safe to do so. GREEN = Proceed if safe. A flashing amber means proceed with caution. A flashing red means treat it as a stop sign.'],
                ['title'=>'🅿️ Parking Signs','text'=>'A blue P sign shows parking is allowed. A P with a red line through it means NO PARKING. A red circle with a red X means NO STOPPING at any time. A yellow kerb means no stopping or parking.'],
            ],
            2 => [
                ['title'=>'🛣️ Right of Way','text'=>'At an unmarked intersection, give way to traffic from the RIGHT. At a four-way stop, the vehicle that arrives FIRST goes first. If two arrive at the same time, give way to the vehicle on the right. Always give way to emergency vehicles.'],
                ['title'=>'↔️ Overtaking Rules','text'=>'Never overtake on a solid white centre line, near a crest or bend, near a pedestrian crossing, or at an intersection. You may overtake on the left ONLY when the vehicle ahead signals to turn right.'],
                ['title'=>'📱 Cell Phones','text'=>'Using a hand-held cell phone while driving is illegal. You may only use a hands-free device. Even at a red light, holding your phone is an offence.'],
                ['title'=>'🚨 Emergency Vehicles','text'=>'When you hear sirens or see flashing lights, move to the LEFT and slow down. Stop if necessary to let the emergency vehicle pass. Never follow it closely or block its path.'],
                ['title'=>'🍺 Alcohol and Driving','text'=>'The legal blood alcohol limit is 0.08g per 100ml of blood. The safest choice is ZERO alcohol before driving. A conviction can result in a fine, licence suspension or imprisonment.'],
            ],
            3 => [
                ['title'=>'🏙️ Urban Areas','text'=>'Inside towns and cities the general speed limit is 60 km/h unless signs indicate otherwise. Near schools during school hours the limit drops to 40 km/h. In parking areas keep to 20 km/h.'],
                ['title'=>'🛣️ Open Roads','text'=>'On tarred open roads outside towns the limit is 120 km/h. On gravel roads the limit is 100 km/h. Heavy vehicles are limited to 100 km/h on all open roads.'],
                ['title'=>'🎓 Learner Drivers','text'=>'A person driving on a learner\'s licence must not exceed 80 km/h at any time, regardless of the posted speed limit.'],
                ['title'=>'⚡ Stopping Distance','text'=>'Stopping distance increases with the SQUARE of your speed. Double your speed and your stopping distance quadruples. At 60 km/h stopping distance is about 36 metres. At 120 km/h it is about 144 metres.'],
                ['title'=>'⏱️ Following Distance','text'=>'Keep at least a 2-second gap behind the vehicle in front on dry roads. In wet conditions double this to 4 seconds.'],
            ],
            4 => [
                ['title'=>'📏 Parking Distances','text'=>'Do not park within 3 metres of a fire hydrant, 6 metres of a pedestrian crossing, 9 metres of an intersection, or in front of a private driveway.'],
                ['title'=>'⛰️ Parking on a Hill','text'=>'Facing DOWNHILL: turn wheels TOWARDS the kerb. Facing UPHILL with a kerb: turn wheels AWAY from the kerb. Always apply the handbrake when parked.'],
                ['title'=>'🚫 Illegal Parking','text'=>'Never park on a pavement. Double parking is always illegal. Never park on a yellow kerb line or block a driveway or fire lane.'],
                ['title'=>'🔄 Parallel Parking','text'=>'Signal and pull up alongside the vehicle ahead. Reverse at an angle into the space. Straighten up. Leave about 30cm from the kerb.'],
            ],
            5 => [
                ['title'=>'🪑 Before You Start','text'=>'Always adjust your seat, head restraint and all mirrors. Fasten your seatbelt. Only then start the engine.'],
                ['title'=>'⚠️ Warning Lights','text'=>'RED battery light = charging failed, stop safely soon. RED oil can = oil pressure critically low, stop immediately. Temperature in red = engine overheating, stop safely.'],
                ['title'=>'💡 Lights','text'=>'Use low beam at night and in poor visibility. Switch to low beam when an oncoming vehicle approaches. Use fog lights in fog — never high beam as it reflects back.'],
                ['title'=>'🚗 Tyres and ABS','text'=>'Check tyre pressure at least monthly. ABS prevents wheel lock-up under hard braking so you can still steer. Press firmly and steer — do not pump the pedal.'],
                ['title'=>'🅿️ Handbrake','text'=>'Always apply the handbrake when parked, even on flat ground. On a hill, apply the handbrake BEFORE releasing the foot brake.'],
            ],
        ];
        $sections = $builtin[$topic['id']] ?? [];
        ?>
        <?php foreach ($sections as $section): ?>
        <div class="card" style="margin-bottom:16px;">
            <h3 style="font-size:17px;margin-bottom:10px;"><?= $section['title'] ?></h3>
            <p style="color:#555;font-size:15px;line-height:1.8;"><?= $section['text'] ?></p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Sample Questions Preview -->
        <?php if (!empty($questions)): ?>
        <div style="margin-top:32px;">
            <h3 style="font-size:18px;margin-bottom:16px;">🔍 Sample Questions</h3>
            <?php foreach ($questions as $i => $q): ?>
            <div class="card" style="margin-bottom:12px;border-left:4px solid #3498db;">
                <?php if (!empty($q['image_path'])): ?>
                <img src="<?= htmlspecialchars($q['image_path']) ?>"
                     style="max-width:100%;max-height:200px;border-radius:6px;margin-bottom:10px;display:block;object-fit:contain;">
                <?php endif; ?>
                <p style="font-weight:500;margin-bottom:8px;">Q<?= $i+1 ?>. <?= htmlspecialchars($q['question']) ?></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <?php foreach (['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']] as $key=>$val): ?>
                    <div style="background:#f8f9fa;padding:8px 12px;border-radius:6px;font-size:14px;">
                        <strong><?= $key ?>.</strong> <?= htmlspecialchars($val) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CTA -->
        <div style="text-align:center;margin-top:28px;padding:28px;background:#f8f9fa;border-radius:10px;">
            <p style="font-size:16px;margin-bottom:16px;">Ready to test your knowledge?</p>
            <a href="/testmate/quiz.php?topic=<?= $topic['id'] ?>" class="btn btn-primary btn-lg">
                Take the <?= htmlspecialchars($topic['name']) ?> Quiz →
            </a>
        </div>

    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>