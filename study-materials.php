<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$topic     = null;
$materials = [];
$questions = [];
$mode      = $_GET['mode'] ?? 'read'; // 'read' or 'video'

if (isset($_GET['topic'])) {
    $topic_id = (int)$_GET['topic'];

    $t = $conn->prepare("SELECT * FROM topics WHERE id = ?");
    $t->bind_param("i", $topic_id);
    $t->execute();
    $topic = $t->get_result()->fetch_assoc();

    $m = $conn->prepare("SELECT * FROM materials WHERE topic_id = ? ORDER BY sort_order ASC, id ASC");
    $m->bind_param("i", $topic_id);
    $m->execute();
    $materials = $m->get_result()->fetch_all(MYSQLI_ASSOC);

    $q = $conn->prepare("SELECT * FROM questions WHERE topic_id = ? ORDER BY RAND() LIMIT 3");
    $q->bind_param("i", $topic_id);
    $q->execute();
    $questions = $q->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get simulations for this topic
    $sim = $conn->prepare("SELECT * FROM simulations WHERE topic_id = ? AND is_active = 1");
    $sim->bind_param("i", $topic_id);
    $sim->execute();
    $simulations = $sim->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<?php include 'includes/header.php'; ?>

<style>
/* Simulation Animation Styles */
.sim-stage {
    width: 100%;
    height: 280px;
    background: #87CEEB;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    margin-bottom: 0;
}

/* Road */
.road-surface {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 38%;
    background: #666;
}
.road-line-center {
    position: absolute;
    bottom: 16%;
    left: 0; right: 0;
    height: 5px;
    background: repeating-linear-gradient(90deg, white 0px, white 40px, transparent 40px, transparent 80px);
}
.pavement-l {
    position: absolute;
    bottom: 38%; left: 0; right: 0;
    height: 8px;
    background: #aaa;
}

/* Sky elements */
.cloud {
    position: absolute;
    top: 14%; left: 10%;
    font-size: 28px;
    opacity: .7;
    animation: drift 8s linear infinite;
}
@keyframes drift { from{left:10%} to{left:90%} }

/* Trees */
.tree {
    position: absolute;
    bottom: 38%;
    font-size: 36px;
}

/* Car */
.sim-car {
    position: absolute;
    bottom: 14%;
    font-size: 38px;
    transition: left 1.8s ease-in-out;
    z-index: 10;
    line-height: 1;
}

/* Traffic light */
.tl-pole {
    position: absolute;
    right: 22%;
    bottom: 38%;
    z-index: 8;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.tl-box {
    background: #1a1a1a;
    border: 2px solid #333;
    border-radius: 8px;
    padding: 6px 7px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 2px;
}
.tl-bulb {
    width: 22px; height: 22px;
    border-radius: 50%;
    background: #333;
}
.tl-bulb.red-on    { background:#e74c3c; box-shadow:0 0 10px #e74c3c,0 0 20px rgba(231,76,60,.4); }
.tl-bulb.amber-on  { background:#f1c40f; box-shadow:0 0 10px #f1c40f,0 0 20px rgba(241,196,15,.4); }
.tl-bulb.green-on  { background:#2ecc71; box-shadow:0 0 10px #2ecc71,0 0 20px rgba(46,204,113,.4); }
.tl-stem { width:5px; height:55px; background:#777; border-radius:3px; }

/* Stop sign */
.stop-pole {
    position: absolute;
    right: 22%;
    bottom: 38%;
    z-index: 8;
    text-align: center;
}
.stop-board {
    width: 52px; height: 52px;
    background: #e74c3c;
    clip-path: polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .05em;
    margin: 0 auto 2px;
}
.stop-stem { width:5px; height:55px; background:#777; border-radius:3px; margin:0 auto; }

/* Result overlay */
.result-icon {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -55%);
    font-size: 72px;
    z-index: 20;
    opacity: 0;
    transition: opacity .4s;
    text-shadow: 0 4px 16px rgba(0,0,0,.3);
    pointer-events: none;
}
.result-icon.show { opacity: 1; }

/* Controls bar */
.sim-controls {
    background: #2c3e50;
    border-radius: 0 0 12px 12px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.sim-status {
    color: rgba(255,255,255,.85);
    font-size: 13px;
    flex: 1;
}
.play-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 7px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: background .2s;
    white-space: nowrap;
}
.play-btn:hover { background: #2980b9; }
.play-btn:disabled { background: #555; cursor: not-allowed; }

/* Explanation box */
.sim-explanation {
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    line-height: 1.7;
    margin-top: 10px;
    display: none;
}

/* Mode switcher */
.mode-tabs {
    display: flex;
    border-radius: 10px;
    overflow: hidden;
    border: 2px solid #2c3e50;
    margin-bottom: 24px;
    width: fit-content;
}
.mode-tab {
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    color: #2c3e50;
    background: white;
    transition: all .2s;
}
.mode-tab.active { background: #2c3e50; color: white; }
.mode-tab:hover:not(.active) { background: #f0f4f8; }
</style>

<div class="page-header">
    <h1>Study Materials</h1>
    <p>Choose a topic to study. Take the quiz after each topic to test yourself.</p>
</div>

<div class="container">

    <!-- Topic Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-bottom:40px;">
        <?php foreach ($topics as $tp): ?>
        <div class="card" style="padding:28px;transition:transform .2s;<?= (isset($topic) && $topic['id'] == $tp['id']) ? 'border:2px solid #3498db;' : '' ?>"
             onmouseover="this.style.transform='translateY(-4px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <h2 style="font-size:18px;margin-bottom:8px;"><?= htmlspecialchars($tp['name']) ?></h2>
            <p style="color:#666;font-size:14px;margin-bottom:20px;line-height:1.6;"><?= htmlspecialchars($tp['description']) ?></p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="/testmate/study-materials.php?topic=<?= $tp['id'] ?>&mode=read" class="btn btn-outline" style="font-size:14px;padding:8px 16px;">Read</a>
                <a href="/testmate/study-materials.php?topic=<?= $tp['id'] ?>&mode=video" class="btn btn-primary" style="font-size:14px;padding:8px 16px;">Watch</a>
                <a href="/testmate/quiz.php?topic=<?= $tp['id'] ?>" class="btn btn-outline" style="font-size:14px;padding:8px 16px;background:#f8f9fa;">Quiz</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($topic): ?>
    <div style="border-top:2px solid #eee;padding-top:32px;">

        <!-- Topic Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
            <h2 style="font-size:22px;"><?= htmlspecialchars($topic['name']) ?></h2>
            <a href="/testmate/quiz.php?topic=<?= $topic['id'] ?>" class="btn btn-primary">Take the Quiz</a>
        </div>

        <!-- Mode Switcher -->
        <div class="mode-tabs">
            <a href="/testmate/study-materials.php?topic=<?= $topic['id'] ?>&mode=read"
               class="mode-tab <?= $mode === 'read' ? 'active' : '' ?>">
                Reading Mode
            </a>
            <a href="/testmate/study-materials.php?topic=<?= $topic['id'] ?>&mode=video"
               class="mode-tab <?= $mode === 'video' ? 'active' : '' ?>">
                Video / Animation Mode
            </a>
        </div>

        <?php if ($mode === 'read'): ?>
        <!-- ═══════════════════════════════ -->
        <!-- READ MODE                       -->
        <!-- ═══════════════════════════════ -->

        <?php if (!empty($materials)): ?>
            <?php foreach ($materials as $mat): ?>
            <div class="card" style="margin-bottom:20px;">
                <h3 style="font-size:18px;margin-bottom:12px;color:#2c3e50;"><?= htmlspecialchars($mat['title']) ?></h3>
                <?php if ($mat['file_type'] === 'image' && $mat['file_path']): ?>
                <img src="<?= htmlspecialchars($mat['file_path']) ?>"
                     style="max-width:100%;border-radius:8px;margin-bottom:14px;display:block;">
                <?php endif; ?>
                <?php if ($mat['content']): ?>
                <p style="color:#444;font-size:15px;line-height:1.8;white-space:pre-line;"><?= htmlspecialchars($mat['content']) ?></p>
                <?php endif; ?>
                <?php if ($mat['file_type'] === 'pdf' && $mat['file_path']): ?>
                <div style="margin-top:16px;">
                    <a href="<?= htmlspecialchars($mat['file_path']) ?>" target="_blank" class="btn btn-outline" style="font-size:14px;">View / Download PDF</a>
                    <div style="margin-top:12px;border-radius:8px;overflow:hidden;border:1px solid #eee;">
                        <iframe src="<?= htmlspecialchars($mat['file_path']) ?>" width="100%" height="500px" style="display:block;border:none;"></iframe>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($mat['file_type'] === 'video' && $mat['file_path']): ?>
                <video src="<?= htmlspecialchars($mat['file_path']) ?>" controls
                       style="max-width:100%;border-radius:8px;margin-top:14px;display:block;"></video>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php
            $builtin = [
                1 => [
                    ['title'=>'Regulatory Signs','text'=>'Regulatory signs tell you what you MUST or MUST NOT do. They are usually circular with a red border. Examples include STOP signs (octagonal, red), No Entry signs (red circle with white bar), speed limit signs (red circle with number), and no overtaking signs.'],
                    ['title'=>'Warning Signs','text'=>'Warning signs alert you to hazards ahead. They are yellow diamond or triangle shaped with black symbols. Examples: sharp bend ahead, pedestrian crossing, railway crossing, slippery road, animals crossing.'],
                    ['title'=>'Informational Signs','text'=>'Informational signs guide you. Blue rectangular signs show directions and distances. Blue circular signs with white arrows are mandatory direction signs — you MUST go in the direction shown.'],
                    ['title'=>'Traffic Lights','text'=>'RED = Stop completely. AMBER = Stop if safe to do so. GREEN = Proceed if safe. A flashing amber means proceed with caution. A flashing red means treat it as a stop sign.'],
                    ['title'=>'Parking Signs','text'=>'A blue P sign shows parking is allowed. A P with a red line through it means NO PARKING. A red circle with a red X means NO STOPPING at any time. A yellow kerb means no stopping or parking.'],
                ],
                2 => [
                    ['title'=>'Right of Way','text'=>'At an unmarked intersection, give way to traffic from the RIGHT. At a four-way stop, the vehicle that arrives FIRST goes first. If two arrive at the same time, give way to the vehicle on the right. Always give way to emergency vehicles.'],
                    ['title'=>'Overtaking Rules','text'=>'Never overtake on a solid white centre line, near a crest or bend, near a pedestrian crossing, or at an intersection. You may overtake on the left ONLY when the vehicle ahead signals to turn right.'],
                    ['title'=>'Cell Phones','text'=>'Using a hand-held cell phone while driving is illegal. You may only use a hands-free device. Even at a red light, holding your phone is an offence.'],
                    ['title'=>'Emergency Vehicles','text'=>'When you hear sirens or see flashing lights, move to the LEFT and slow down. Stop if necessary to let the emergency vehicle pass. Never follow it closely or block its path.'],
                    ['title'=>'Alcohol and Driving','text'=>'The legal blood alcohol limit is 0.08g per 100ml of blood. The safest choice is ZERO alcohol before driving. A conviction can result in a fine, licence suspension or imprisonment.'],
                ],
                3 => [
                    ['title'=>'Urban Areas','text'=>'Inside towns and cities the general speed limit is 60 km/h unless signs indicate otherwise. Near schools during school hours the limit drops to 40 km/h. In parking areas keep to 20 km/h.'],
                    ['title'=>'Open Roads','text'=>'On tarred open roads outside towns the limit is 120 km/h. On gravel roads the limit is 100 km/h. Heavy vehicles are limited to 100 km/h on all open roads.'],
                    ['title'=>'Learner Drivers','text'=>'A person driving on a learner\'s licence must not exceed 80 km/h at any time, regardless of the posted speed limit.'],
                    ['title'=>'Stopping Distance','text'=>'Stopping distance increases with the SQUARE of your speed. Double your speed and your stopping distance quadruples. At 60 km/h stopping distance is about 36 metres. At 120 km/h it is about 144 metres.'],
                    ['title'=>'Following Distance','text'=>'Keep at least a 2-second gap behind the vehicle in front on dry roads. In wet conditions double this to 4 seconds.'],
                ],
                4 => [
                    ['title'=>'Parking Distances','text'=>'Do not park within 3 metres of a fire hydrant, 6 metres of a pedestrian crossing, 9 metres of an intersection, or in front of a private driveway.'],
                    ['title'=>'Parking on a Hill','text'=>'Facing DOWNHILL: turn wheels TOWARDS the kerb. Facing UPHILL with a kerb: turn wheels AWAY from the kerb. Always apply the handbrake when parked.'],
                    ['title'=>'Illegal Parking','text'=>'Never park on a pavement. Double parking is always illegal. Never park on a yellow kerb line or block a driveway or fire lane.'],
                    ['title'=>'Parallel Parking','text'=>'Signal and pull up alongside the vehicle ahead. Reverse at an angle into the space. Straighten up. Leave about 30cm from the kerb.'],
                ],
                5 => [
                    ['title'=>'Before You Start','text'=>'Always adjust your seat, head restraint and all mirrors. Fasten your seatbelt. Only then start the engine.'],
                    ['title'=>'Warning Lights','text'=>'RED battery light = charging failed, stop safely soon. RED oil can = oil pressure critically low, stop immediately. Temperature in red = engine overheating, stop safely.'],
                    ['title'=>'Lights','text'=>'Use low beam at night and in poor visibility. Switch to low beam when an oncoming vehicle approaches. Use fog lights in fog — never high beam as it reflects back.'],
                    ['title'=>'Tyres and ABS','text'=>'Check tyre pressure at least monthly. ABS prevents wheel lock-up under hard braking so you can still steer. Press firmly and steer — do not pump the pedal.'],
                    ['title'=>'Handbrake','text'=>'Always apply the handbrake when parked, even on flat ground. On a hill, apply the handbrake BEFORE releasing the foot brake.'],
                ],
            ];
            foreach ($builtin[$topic['id']] ?? [] as $section):
            ?>
            <div class="card" style="margin-bottom:16px;">
                <h3 style="font-size:17px;margin-bottom:10px;"><?= $section['title'] ?></h3>
                <p style="color:#555;font-size:15px;line-height:1.8;"><?= $section['text'] ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php else: ?>
        <!-- ═══════════════════════════════ -->
        <!-- VIDEO / ANIMATION MODE          -->
        <!-- ═══════════════════════════════ -->

        <?php
        // Built-in video scenarios per topic
        $video_scenarios = [
            1 => [ // Road Signs
                [
                    'title'       => 'The STOP Sign',
                    'description' => 'Watch how a driver should correctly respond to a STOP sign.',
                    'type'        => 'stop_sign',
                    'scenarios'   => [
                        ['car_stops'=>true,  'is_correct'=>true,  'label'=>'Driver stops completely', 'explanation'=>'Correct! At a STOP sign you must always come to a complete stop, check for other traffic, then proceed when safe.'],
                        ['car_stops'=>false, 'is_correct'=>false, 'label'=>'Driver slows but does not stop', 'explanation'=>'Incorrect! At a STOP sign a complete stop is required. Slowing down without stopping fully is a traffic offence.'],
                    ]
                ],
                [
                    'title'       => 'The YIELD Sign',
                    'description' => 'Learn when to give way to other traffic at a yield sign.',
                    'type'        => 'yield_sign',
                    'scenarios'   => [
                        ['car_yields'=>true,  'oncoming'=>true,  'is_correct'=>true,  'label'=>'Driver slows and gives way', 'explanation'=>'Correct! At a YIELD sign you must slow down and give way to vehicles already on the main road.'],
                        ['car_yields'=>false, 'oncoming'=>true,  'is_correct'=>false, 'label'=>'Driver does not yield', 'explanation'=>'Incorrect! You must always give way at a YIELD sign when other vehicles are approaching on the main road.'],
                    ]
                ],
                [
                    'title'       => 'Speed Limit Signs',
                    'description' => 'See how a regulatory speed sign sets the maximum speed you may legally travel.',
                    'type'        => 'speed',
                    'scenarios'   => [
                        ['limit'=>60, 'speed'=>60, 'is_correct'=>true,  'explanation'=>'Correct! The driver is travelling at exactly the 60 km/h shown on the regulatory speed sign.'],
                        ['limit'=>60, 'speed'=>90, 'is_correct'=>false, 'explanation'=>'Incorrect! The sign shows a 60 km/h limit. Travelling at 90 km/h ignores the regulatory sign and is illegal.'],
                    ]
                ],
            ],
            2 => [ // Traffic Rules
                [
                    'title'       => 'Traffic Light Rules',
                    'description' => 'Watch how drivers should respond to each traffic light colour.',
                    'type'        => 'traffic_light',
                    'scenarios'   => [
                        ['light'=>'red',   'car_passes'=>true,  'is_correct'=>false, 'explanation'=>'Incorrect! You must STOP at a red traffic light. Driving through a red light is illegal and extremely dangerous.'],
                        ['light'=>'green', 'car_passes'=>true,  'is_correct'=>true,  'explanation'=>'Correct! You may proceed when the light is green, as long as it is safe to do so.'],
                        ['light'=>'amber', 'car_passes'=>true,  'is_correct'=>false, 'explanation'=>'Incorrect! At amber you must stop if it is safe. Only proceed if you are too close to stop safely.'],
                        ['light'=>'red',   'car_passes'=>false, 'is_correct'=>true,  'explanation'=>'Correct! The driver stopped at the red light. This is exactly what you must do.'],
                        ['light'=>'green', 'car_passes'=>false, 'is_correct'=>false, 'explanation'=>'Incorrect! When the light is green you may proceed if safe. Unnecessary stopping can cause accidents.'],
                        ['light'=>'amber', 'car_passes'=>false, 'is_correct'=>true,  'explanation'=>'Correct! The driver stopped safely at amber. This is the correct action when you can stop safely.'],
                    ]
                ],
                [
                    'title'       => 'Four-Way Stop — Right of Way',
                    'description' => 'See who has right of way at a four-way stop intersection.',
                    'type'        => 'four_way',
                    'scenarios'   => [
                        ['order'=>'first',  'goes'=>true,  'is_correct'=>true,  'explanation'=>'Correct! The vehicle that arrives FIRST at a four-way stop has the right of way and should proceed.'],
                        ['order'=>'second', 'goes'=>true,  'is_correct'=>false, 'explanation'=>'Incorrect! The first vehicle to arrive goes first. The second vehicle must wait.'],
                        ['order'=>'right',  'goes'=>true,  'is_correct'=>true,  'explanation'=>'Correct! When two vehicles arrive at the same time, the vehicle on the RIGHT has priority.'],
                        ['order'=>'left',   'goes'=>true,  'is_correct'=>false, 'explanation'=>'Incorrect! When two vehicles arrive simultaneously, the vehicle on the LEFT must give way to the vehicle on the RIGHT.'],
                    ]
                ],
                [
                    'title'       => 'Cell Phones & Distracted Driving',
                    'description' => 'See why using a hand-held phone while driving breaks the rules of the road.',
                    'type'        => 'vehicle_check',
                    'scenarios'   => [
                        ['action'=>'phone_in_hand',    'label'=>'Phone: In hand',           'is_correct'=>false, 'explanation'=>'Incorrect! Holding a phone while driving is illegal, even at a red light. You may only use a hands-free device.'],
                        ['action'=>'phone_hands_free', 'label'=>'Phone: Hands-free device',  'is_correct'=>true,  'explanation'=>'Correct! Using a hands-free device keeps both hands on the wheel and complies with the law.'],
                    ]
                ],
            ],
            3 => [ // Speed Limits
                [
                    'title'       => 'Speed Limits in Town',
                    'description' => 'See what happens when a driver exceeds the speed limit in an urban area.',
                    'type'        => 'speed',
                    'scenarios'   => [
                        ['limit'=>60, 'speed'=>60, 'is_correct'=>true,  'explanation'=>'Correct! The driver is travelling at 60 km/h which is the correct speed limit in a town or city.'],
                        ['limit'=>60, 'speed'=>80, 'is_correct'=>false, 'explanation'=>'Incorrect! The speed limit in a town is 60 km/h. Driving at 80 km/h is speeding and is illegal and dangerous.'],
                    ]
                ],
                [
                    'title'       => 'Speed Limits on the Open Road',
                    'description' => 'Learn the correct speed limit for tarred open roads outside town.',
                    'type'        => 'speed',
                    'scenarios'   => [
                        ['limit'=>120,'speed'=>120,'is_correct'=>true,  'explanation'=>'Correct! On an open tarred road the speed limit is 120 km/h. The driver is complying with the limit.'],
                        ['limit'=>120,'speed'=>140,'is_correct'=>false, 'explanation'=>'Incorrect! The speed limit on open tarred roads is 120 km/h. Driving at 140 km/h is speeding.'],
                    ]
                ],
                [
                    'title'       => 'Learner Driver Speed Limit',
                    'description' => 'A learner driver must never exceed 80 km/h, even where the posted limit is higher.',
                    'type'        => 'speed',
                    'scenarios'   => [
                        ['limit'=>80, 'speed'=>80,  'is_correct'=>true,  'explanation'=>'Correct! A learner driver must never exceed 80 km/h, regardless of the posted speed limit.'],
                        ['limit'=>80, 'speed'=>100, 'is_correct'=>false, 'explanation'=>'Incorrect! Even though the road may allow a higher speed, a learner driver may never exceed 80 km/h.'],
                    ]
                ],
            ],
            4 => [ // Parking Rules
                [
                    'title'       => 'Legal vs Illegal Parking Bays',
                    'description' => 'Compare parking in a proper bay against parking on the pavement.',
                    'type'        => 'parking',
                    'scenarios'   => [
                        ['location'=>'legal',    'is_correct'=>true,  'explanation'=>'Correct! The driver parked in a legal parking bay away from intersections and crossings.'],
                        ['location'=>'pavement', 'is_correct'=>false, 'explanation'=>'Incorrect! Parking on a pavement is illegal. It forces pedestrians into the road and is dangerous.'],
                    ]
                ],
                [
                    'title'       => 'No-Stopping Zones — Yellow Kerbs & Crossings',
                    'description' => 'Learn where stopping or parking is never allowed, no matter how briefly.',
                    'type'        => 'parking',
                    'scenarios'   => [
                        ['location'=>'yellow',   'is_correct'=>false, 'explanation'=>'Incorrect! A yellow kerb means no parking or stopping at any time.'],
                        ['location'=>'crossing', 'is_correct'=>false, 'explanation'=>'Incorrect! You must not park within 6 metres of a pedestrian crossing.'],
                    ]
                ],
                [
                    'title'       => 'Parking Near Intersections, Driveways & Hydrants',
                    'description' => 'These spots are illegal to park in even when there is no sign telling you so.',
                    'type'        => 'parking',
                    'scenarios'   => [
                        ['location'=>'intersection', 'is_correct'=>false, 'explanation'=>'Incorrect! You may not park within 9 metres of an intersection.'],
                        ['location'=>'driveway',     'is_correct'=>false, 'explanation'=>'Incorrect! Parking in front of a private driveway blocks access and is illegal.'],
                        ['location'=>'hydrant',      'is_correct'=>false, 'explanation'=>'Incorrect! You must not park within 3 metres of a fire hydrant.'],
                    ]
                ],
            ],
            5 => [ // Vehicle Controls
                [
                    'title'       => 'Seatbelt & Mirror Checks',
                    'description' => 'See the correct procedure before starting your vehicle.',
                    'type'        => 'vehicle_check',
                    'scenarios'   => [
                        ['action'=>'seatbelt_on',  'label'=>'Seatbelt: ON',        'is_correct'=>true,  'explanation'=>'Correct! Always fasten your seatbelt before starting the engine. It is the law and could save your life.'],
                        ['action'=>'seatbelt_off', 'label'=>'Seatbelt: OFF',       'is_correct'=>false, 'explanation'=>'Incorrect! Never drive without a seatbelt. It is both illegal and extremely dangerous.'],
                        ['action'=>'mirrors_set',  'label'=>'Mirrors: Adjusted',   'is_correct'=>true,  'explanation'=>'Correct! Always adjust your mirrors before driving to ensure maximum visibility around your vehicle.'],
                    ]
                ],
                [
                    'title'       => 'Hands-Free vs Hand-Held',
                    'description' => 'Test your knowledge on using a phone behind the wheel.',
                    'type'        => 'vehicle_check',
                    'scenarios'   => [
                        ['action'=>'phone_in_hand',    'label'=>'Phone: In hand',          'is_correct'=>false, 'explanation'=>'Incorrect! Never hold a phone while driving. It is illegal and significantly increases your risk of an accident.'],
                        ['action'=>'phone_hands_free', 'label'=>'Phone: Hands-free',        'is_correct'=>true,  'explanation'=>'Correct! A hands-free device lets you keep both hands on the wheel and stay within the law.'],
                    ]
                ],
                [
                    'title'       => 'Dashboard Warning Lights',
                    'description' => 'Learn how to respond correctly when a warning light appears on your dashboard.',
                    'type'        => 'vehicle_check',
                    'scenarios'   => [
                        ['action'=>'oil_light_stop',     'label'=>'Oil Light: RED — Driver stops immediately', 'is_correct'=>true,  'explanation'=>'Correct! A red oil warning light means oil pressure is critically low. Stop immediately to avoid engine damage.'],
                        ['action'=>'oil_light_continue', 'label'=>'Oil Light: RED — Driver keeps driving',     'is_correct'=>false, 'explanation'=>'Incorrect! Ignoring a red oil warning light can cause severe engine damage. You must stop immediately.'],
                    ]
                ],
            ],
        ];

        $topic_scenarios = $video_scenarios[$topic['id']] ?? [];
        ?>

        <?php if (empty($topic_scenarios)): ?>
        <div class="card" style="text-align:center;padding:40px;color:#888;">
            <p>No video scenarios available for this topic yet.</p>
            <a href="/testmate/study-materials.php?topic=<?= $topic['id'] ?>&mode=read" class="btn btn-primary" style="margin-top:16px;">Switch to Reading Mode</a>
        </div>
        <?php else: ?>

        <?php foreach ($topic_scenarios as $sgi => $sg): ?>
        <div class="card" style="margin-bottom:28px;padding:0;overflow:hidden;">

            <!-- Group Header -->
            <div style="background:#2c3e50;color:white;padding:16px 20px;">
                <h3 style="font-size:17px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($sg['title']) ?></h3>
                <p style="font-size:13px;opacity:.8;"><?= htmlspecialchars($sg['description']) ?></p>
            </div>

            <div style="padding:20px;">
                <?php foreach ($sg['scenarios'] as $sci => $sc):
                    $uid = 'sg' . $sgi . 'sc' . $sci;
                ?>
                <div style="margin-bottom:20px;">
                    <div style="font-size:13px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
                        Scenario <?= $sci+1 ?>
                        <?php if (isset($sc['light'])): ?>
                            — <?= ucfirst($sc['light']) ?> light
                        <?php elseif (isset($sc['car_stops'])): ?>
                            — <?= $sc['car_stops'] ? 'Driver stops' : 'Driver does not stop' ?>
                        <?php elseif (isset($sc['car_yields'])): ?>
                            — <?= $sc['car_yields'] ? 'Driver yields' : 'Driver does not yield' ?>
                        <?php elseif (isset($sc['order'])): ?>
                            — Arrives <?= ucfirst($sc['order']) ?>, <?= $sc['goes'] ? 'proceeds' : 'waits' ?>
                        <?php elseif (isset($sc['speed'])): ?>
                            — Driving at <?= $sc['speed'] ?> km/h (limit: <?= $sc['limit'] ?> km/h)
                        <?php elseif (isset($sc['location'])): ?>
                            — <?= ucfirst(str_replace('_',' ',$sc['location'])) ?> parking
                        <?php elseif (isset($sc['action'])): ?>
                            — <?= ucfirst(str_replace('_',' ',$sc['action'])) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Animation Stage -->
                    <div style="border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1);">
                        <div class="sim-stage" id="stage-<?= $uid ?>">
                            <!-- Sky -->
                            <div class="cloud">☁️</div>
                            <div style="position:absolute;top:18%;right:15%;font-size:22px;opacity:.6;">☁️</div>

                            <!-- Trees -->
                            <div class="tree" style="left:5%;">🌳</div>
                            <div class="tree" style="left:12%;">🌲</div>
                            <div class="tree" style="right:5%;">🌳</div>

                            <!-- Road -->
                            <div class="road-surface"></div>
                            <div class="road-line-center"></div>
                            <div class="pavement-l"></div>

                            <!-- Sign/Light based on type -->
                            <?php if ($sg['type'] === 'traffic_light'): ?>
                            <div class="tl-pole">
                                <div class="tl-box">
                                    <div class="tl-bulb <?= ($sc['light']??'')==='red'   ? 'red-on'   : '' ?>" id="red-<?= $uid ?>"></div>
                                    <div class="tl-bulb <?= ($sc['light']??'')==='amber' ? 'amber-on' : '' ?>" id="amb-<?= $uid ?>"></div>
                                    <div class="tl-bulb <?= ($sc['light']??'')==='green' ? 'green-on' : '' ?>" id="grn-<?= $uid ?>"></div>
                                </div>
                                <div class="tl-stem"></div>
                            </div>

                            <?php elseif ($sg['type'] === 'stop_sign' || $sg['type'] === 'yield_sign'): ?>
                            <div class="stop-pole">
                                <?php if ($sg['type'] === 'stop_sign'): ?>
                                <div class="stop-board">STOP</div>
                                <?php else: ?>
                                <div style="width:52px;height:52px;background:#e74c3c;clip-path:polygon(50% 100%,0% 0%,100% 0%);display:flex;align-items:flex-start;justify-content:center;padding-top:6px;margin:0 auto 2px;">
                                    <span style="color:white;font-size:8px;font-weight:900;">YIELD</span>
                                </div>
                                <?php endif; ?>
                                <div class="stop-stem"></div>
                            </div>

                            <?php elseif ($sg['type'] === 'speed'): ?>
                            <!-- Speed limit sign -->
                            <div style="position:absolute;right:22%;bottom:38%;z-index:8;text-align:center;">
                                <div style="width:52px;height:52px;border-radius:50%;border:5px solid #e74c3c;background:white;display:flex;align-items:center;justify-content:center;margin:0 auto 2px;font-size:14px;font-weight:900;color:#333;">
                                    <?= $sc['limit'] ?>
                                </div>
                                <div style="width:5px;height:55px;background:#777;border-radius:3px;margin:0 auto;"></div>
                            </div>
                            <!-- Speed indicator -->
                            <div id="speedo-<?= $uid ?>" style="position:absolute;top:12%;left:12%;background:rgba(0,0,0,.7);color:white;padding:6px 12px;border-radius:8px;font-size:13px;font-weight:700;z-index:15;display:none;">
                                <?= $sc['speed'] ?> km/h
                            </div>

                            <?php elseif ($sg['type'] === 'parking'): ?>
                            <!-- Parking indicator sign -->
                            <div style="position:absolute;right:22%;bottom:38%;z-index:8;text-align:center;">
                                <?php if ($sc['location'] === 'legal'): ?>
                                <div style="width:40px;height:48px;background:#3498db;border-radius:6px;display:flex;align-items:center;justify-content:center;margin:0 auto 2px;font-size:20px;font-weight:900;color:white;">P</div>
                                <?php elseif ($sc['location'] === 'yellow'): ?>
                                <div style="position:absolute;bottom:0;left:0;right:0;height:6px;background:#f1c40f;"></div>
                                <div style="background:rgba(0,0,0,.6);color:white;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;">YELLOW KERB</div>
                                <?php else: ?>
                                <div style="width:40px;height:40px;border-radius:50%;border:4px solid #e74c3c;background:white;display:flex;align-items:center;justify-content:center;margin:0 auto 2px;font-size:16px;font-weight:900;color:#e74c3c;text-decoration:line-through;">P</div>
                                <?php endif; ?>
                                <div style="width:5px;height:30px;background:#777;border-radius:3px;margin:0 auto;"></div>
                            </div>

                            <?php elseif ($sg['type'] === 'vehicle_check'): ?>
                            <!-- Vehicle interior indicator -->
                            <div style="position:absolute;top:15%;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.75);color:white;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;z-index:15;text-align:center;white-space:nowrap;">
                                <?= htmlspecialchars($sc['label'] ?? ucfirst(str_replace('_',' ',$sc['action']))) ?>
                            </div>
                            <?php endif; ?>

                            <!-- Car -->
                            <div class="sim-car" id="car-<?= $uid ?>" style="left:5%;">🚗</div>

                            <!-- Result Icon (study mode shows automatically) -->
                            <div class="result-icon" id="result-<?= $uid ?>">
                                <?= $sc['is_correct'] ? '✅' : '❌' ?>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="sim-controls">
                            <span class="sim-status" id="status-<?= $uid ?>">Click Play to watch the scenario</span>
                            <button class="play-btn" id="playbtn-<?= $uid ?>"
                                    data-uid="<?= htmlspecialchars($uid) ?>"
                                    data-type="<?= htmlspecialchars($sg['type']) ?>"
                                    data-scenario='<?= htmlspecialchars(json_encode($sc), ENT_QUOTES, "UTF-8") ?>'
                                    onclick="playScenario(this)">
                                Play
                            </button>
                        </div>
                    </div>

                    <!-- Explanation (shown after play) -->
                    <div class="sim-explanation <?= $sc['is_correct'] ? '' : '' ?>"
                         id="expl-<?= $uid ?>"
                         style="background:<?= $sc['is_correct'] ? '#eafaf1' : '#fdecea' ?>;border-left:4px solid <?= $sc['is_correct'] ? '#27ae60' : '#e74c3c' ?>;">
                        <strong style="color:<?= $sc['is_correct'] ? '#27ae60' : '#e74c3c' ?>;">
                            <?= $sc['is_correct'] ? 'Correct Action' : 'Incorrect Action' ?>
                        </strong><br>
                        <?= htmlspecialchars($sc['explanation']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
        <?php endif; // end video mode ?>

        <!-- Sample Questions -->
        <?php if (!empty($questions)): ?>
        <div style="margin-top:32px;">
            <h3 style="font-size:18px;margin-bottom:16px;">Sample Questions for this Topic</h3>
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
                Take the <?= htmlspecialchars($topic['name']) ?> Quiz
            </a>
        </div>

    </div>
    <?php endif; ?>

</div>

<script>
function playScenario(btn) {
    const uid      = btn.dataset.uid;
    const type     = btn.dataset.type;
    const scenario = JSON.parse(btn.dataset.scenario);

    const car    = document.getElementById('car-' + uid);
    const result = document.getElementById('result-' + uid);
    const status = document.getElementById('status-' + uid);
    const expl   = document.getElementById('expl-' + uid);
    const speedo = document.getElementById('speedo-' + uid);

    if (!car) return;

    // Reset
    car.style.left = '5%';
    result.classList.remove('show');
    expl.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Playing...';

    if (speedo) speedo.style.display = 'none';

    // Phase 1 — car starts moving
    setTimeout(() => {
        status.textContent = 'Watch the scenario carefully...';

        if (speedo) speedo.style.display = 'block';

        if (type === 'traffic_light') {
            // Car moves toward traffic light
            const stops = !scenario.car_passes;
            car.style.left = stops ? '45%' : '92%';
        } else if (type === 'stop_sign' || type === 'yield_sign') {
            const stops = scenario.car_stops || scenario.car_yields;
            car.style.left = stops ? '48%' : '92%';
        } else if (type === 'speed') {
            car.style.left = '75%';
        } else if (type === 'parking') {
            car.style.left = '60%';
        } else if (type === 'vehicle_check') {
            car.style.left = '30%';
        } else if (type === 'four_way') {
            car.style.left = scenario.goes ? '75%' : '40%';
        } else {
            car.style.left = '70%';
        }
    }, 400);

    // Phase 2 — show result
    setTimeout(() => {
        result.classList.add('show');
        status.textContent = scenario.is_correct
            ? 'This action is CORRECT'
            : 'This action is INCORRECT';
        expl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Replay';
    }, 2400);
}
</script>

<?php include 'includes/footer.php'; ?>