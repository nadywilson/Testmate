<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php");
    exit();
}

$message = '';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM simulation_questions WHERE simulation_id = $id");
    $conn->query("DELETE FROM simulation_results WHERE simulation_id = $id");
    $conn->query("DELETE FROM simulations WHERE id = $id");
    header("Location: /testmate/admin/simulations.php?msg=deleted");
    exit();
}

// Handle toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE simulations SET is_active = NOT is_active WHERE id = $id");
    header("Location: /testmate/admin/simulations.php?msg=toggled");
    exit();
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sim'])) {
    $topic_id = (int)$_POST['topic_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $animation_type = $_POST['animation_type'];
    $scenario_data = $_POST['scenario_data'];

    if ($title !== '' && $scenario_data !== '') {
        $stmt = $conn->prepare("INSERT INTO simulations (topic_id, title, description, animation_type, scenario_data, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("issss", $topic_id, $title, $description, $animation_type, $scenario_data);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();
        header("Location: /testmate/admin/simulations.php?msg=added&id=$new_id");
        exit();
    } else {
        $message = "Title and Scenario Data are required.";
    }
}

// Get all simulations
$sims = $conn->query("
    SELECT s.*, t.name AS topic_name,
           COUNT(DISTINCT sq.id) AS question_count
    FROM simulations s
    LEFT JOIN topics t ON s.topic_id = t.id
    LEFT JOIN simulation_questions sq ON s.id = sq.simulation_id
    GROUP BY s.id
    ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);

$topics = $conn->query("SELECT id, name FROM topics ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m === 'deleted') $message = "Simulation deleted.";
    elseif ($m === 'toggled') $message = "Status updated.";
    elseif ($m === 'added') $message = "Simulation added successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simulations – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;}
        .card{background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-weight:600;margin-bottom:6px;font-size:14px;color:#374151;}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-family:inherit;}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#2563eb;}
        .form-group textarea{min-height:120px;resize:vertical;}
        .btn{padding:10px 20px;border:none;border-radius:6px;font-size:14px;cursor:pointer;font-weight:600;transition:opacity .2s;text-decoration:none;display:inline-block;}
        .btn:hover{opacity:.9;}
        .btn-primary{background:#2563eb;color:white;}
        .btn-success{background:#059669;color:white;}
        .btn-danger{background:#dc2626;color:white;}
        .btn-outline{background:white;color:#374151;border:1px solid #d1d5db;}
        .btn-sm{padding:6px 12px;font-size:12px;}
        .alert{background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;}
        .alert-error{background:#fee2e2;color:#991b1b;}
        table{width:100%;border-collapse:collapse;font-size:14px;}
        th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #e5e7eb;}
        th{background:#f9fafb;font-weight:600;color:#4b5563;font-size:12px;text-transform:uppercase;letter-spacing:.05em;}
        tr:hover{background:#f9fafb;}
        .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;}
        .badge-active{background:#d1fae5;color:#065f46;}
        .badge-inactive{background:#e5e7eb;color:#6b7280;}
        .actions{display:flex;gap:8px;}
        .sim-preview{font-size:28px;text-align:center;padding:16px;background:#f9fafb;border-radius:8px;}
        .empty{text-align:center;padding:40px;color:#9ca3af;}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        pre{background:#1a252f;color:#a8e6cf;padding:16px;border-radius:8px;overflow-x:auto;font-size:12px;line-height:1.5;}
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
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:6px;">🎬 Simulations</h1>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Manage animated traffic scenario simulations.</p>

        <?php if ($message): ?>
        <div class="alert <?= strpos($message, 'required') !== false ? 'alert-error' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Add Simulation Form -->
        <div class="card">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:16px;">➕ Add New Simulation</h2>
            <form method="POST">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Topic</label>
                        <select name="topic_id" required>
                            <option value="">Select topic...</option>
                            <?php foreach ($topics as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Animation Type</label>
                        <select name="animation_type" required>
                            <option value="traffic_light">🚦 Traffic Light</option>
                            <option value="four_way_stop">🛑 Four-Way Stop</option>
                            <option value="road_signs">🚧 Road Signs</option>
                            <option value="speed">🏎️ Speed Limit</option>
                            <option value="parking">🅿️ Parking</option>
                            <option value="vehicle_check">🚘 Vehicle Check</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="e.g. Traffic Light Rules" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" placeholder="Short description of the simulation">
                </div>
                <div class="form-group">
                    <label>Scenario Data (JSON)</label>
                    <textarea name="scenario_data" placeholder='[{"light":"red","car_passes":false,"is_correct":true,"explanation":"You must stop at a red light."}]' required></textarea>
                    <p style="font-size:12px;color:#888;margin-top:6px;">Enter a JSON array of scenario objects. Each object needs fields matching the animation type.</p>
                </div>
                <button type="submit" name="add_sim" class="btn btn-primary">Add Simulation</button>
            </form>
        </div>

        <!-- Simulations List -->
        <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">All Simulations</h2>
        <?php if (empty($sims)): ?>
        <div class="card empty">
            <p>No simulations yet. Add one above.</p>
        </div>
        <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Topic</th>
                        <th>Type</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sims as $s):
                        $icon = '🚦';
                        if ($s['animation_type'] === 'four_way_stop') $icon = '🛑';
                        elseif ($s['animation_type'] === 'road_signs') $icon = '🚧';
                        elseif ($s['animation_type'] === 'speed') $icon = '🏎️';
                        elseif ($s['animation_type'] === 'parking') $icon = '🅿️';
                        elseif ($s['animation_type'] === 'vehicle_check') $icon = '🚘';
                    ?>
                    <tr>
                        <td><div class="sim-preview"><?= $icon ?></div></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($s['title']) ?></td>
                        <td><?= htmlspecialchars($s['topic_name'] ?? 'General') ?></td>
                        <td><?= htmlspecialchars(str_replace('_', ' ', $s['animation_type'])) ?></td>
                        <td><?= $s['question_count'] ?></td>
                        <td>
                            <span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="?toggle=<?= $s['id'] ?>" class="btn btn-outline btn-sm">
                                    <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </a>
                                <a href="?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this simulation and all its data?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- JSON Examples -->
        <div class="card" style="background:#f8f9fa;">
            <h3 style="font-size:15px;margin-bottom:12px;">📖 JSON Format Examples</h3>
            <p style="font-size:13px;color:#666;margin-bottom:12px;">Copy-paste these as starting templates for your scenario data:</p>

            <p style="font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">Traffic Light:</p>
            <pre>[{"light":"red","car_passes":false,"is_correct":true,"explanation":"Stop at red."},
{"light":"green","car_passes":true,"is_correct":true,"explanation":"Go on green."}]</pre>

            <p style="font-size:12px;font-weight:600;color:#374151;margin:12px 0 4px;">Speed Limit:</p>
            <pre>[{"speed":120,"limit":80,"is_correct":false,"explanation":"120 km/h exceeds the 80 km/h limit."}]</pre>

            <p style="font-size:12px;font-weight:600;color:#374151;margin:12px 0 4px;">Parking:</p>
            <pre>[{"location":"legal","is_correct":true,"explanation":"Parking in a designated bay is legal."},
{"location":"yellow","is_correct":false,"explanation":"No parking on yellow lines."}]</pre>
        </div>

        <!-- DB Setup -->
        <div class="card" style="background:#f8f9fa;">
            <h3 style="font-size:15px;margin-bottom:12px;">🔧 Database Setup (run in phpMyAdmin if needed)</h3>
            <pre>CREATE TABLE IF NOT EXISTS simulations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    animation_type VARCHAR(50) NOT NULL,
    scenario_data LONGTEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS simulation_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simulation_id INT NOT NULL,
    question TEXT NOT NULL,
    correct_answer VARCHAR(20) NOT NULL,
    explanation TEXT,
    scenario_index INT DEFAULT 0,
    FOREIGN KEY (simulation_id) REFERENCES simulations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulation_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    simulation_id INT NOT NULL,
    mode VARCHAR(20) DEFAULT 'quiz',
    score INT DEFAULT 0,
    total INT DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (simulation_id) REFERENCES simulations(id) ON DELETE CASCADE
);</pre>
        </div>
    </div>
</div>
</body>
</html>