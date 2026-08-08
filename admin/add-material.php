<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

$msg     = '';
$edit_m  = null;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($edit_id > 0) {
    $em = $conn->prepare("SELECT * FROM materials WHERE id = ?");
    $em->bind_param("i", $edit_id);
    $em->execute();
    $edit_m = $em->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid        = (int)$_POST['topic_id'];
    $title      = trim($_POST['title']);
    $content    = trim($_POST['content']);
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $pid        = (int)($_POST['edit_id'] ?? 0);
    $file_path  = $edit_m['file_path'] ?? null;
    $file_type  = $edit_m['file_type'] ?? 'image';

    if (!empty($_FILES['material_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
        $type_map = [
            'jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image','webp'=>'image',
            'pdf'=>'pdf',
            'mp4'=>'video','webm'=>'video','mov'=>'video',
        ];
        if (isset($type_map[$ext])) {
            $file_type  = $type_map[$ext];
            $filename   = 'm_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/testmate/uploads/materials/';
            if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $upload_dir . $filename)) {
                $file_path = '/testmate/uploads/materials/' . $filename;
            } else {
                $msg = "❌ Upload failed. Check uploads/materials/ folder exists and is writable.";
            }
        } else {
            $msg = "❌ Only JPG, PNG, GIF, WEBP, PDF, MP4, WEBM or MOV allowed.";
        }
    }

    if (isset($_POST['remove_file'])) {
        $file_path = null;
        $file_type = 'image';
    }

    if (!$msg) {
        if ($title === '') {
            $msg = "❌ Title is required.";
        } elseif ($pid > 0) {
            $s = $conn->prepare("UPDATE materials SET topic_id=?, title=?, content=?, file_type=?, file_path=?, sort_order=? WHERE id=?");
            $s->bind_param("issssii", $tid, $title, $content, $file_type, $file_path, $sort_order, $pid);
            $s->execute();
            $msg = "✅ Material updated!";
            $em2 = $conn->prepare("SELECT * FROM materials WHERE id = ?");
            $em2->bind_param("i", $pid);
            $em2->execute();
            $edit_m  = $em2->get_result()->fetch_assoc();
            $edit_id = $pid;
        } else {
            $s = $conn->prepare("INSERT INTO materials (topic_id, title, content, file_type, file_path, sort_order) VALUES (?,?,?,?,?,?)");
            $s->bind_param("issssi", $tid, $title, $content, $file_type, $file_path, $sort_order);
            $s->execute();
            $msg    = "✅ Material added!";
            $edit_m = null;
            $edit_id = 0;
        }
    }
}

$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$materials = $conn->query("SELECT m.*, t.name AS topic_name FROM materials m JOIN topics t ON m.topic_id = t.id ORDER BY m.topic_id, m.sort_order, m.id DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $edit_id ? 'Edit' : 'Add' ?> Material – TestMate Admin</title>
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
        .mat-row{background:white;border-radius:10px;padding:14px 18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 6px rgba(0,0,0,.05);}
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
        <a href="/testmate/admin/add-question.php">➕ Add Question</a>
        <a href="/testmate/admin/review-scores.php">✅ Review Scores</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
        <a href="/testmate/admin/add-material.php" class="active">➕ Add Material</a>
    </div>
    <div class="main-content">
        <h1 style="font-size:22px;margin-bottom:20px;">
            <?= $edit_id ? '✏️ Edit Material' : '➕ Add Study Material' ?>
        </h1>

        <?php if ($msg): ?>
        <div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px;max-width:700px;">
            <?= $msg ?>
        </div>
        <?php endif; ?>

        <div class="card" style="max-width:700px;padding:24px;margin-bottom:30px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

                <div class="form-group">
                    <label>Topic</label>
                    <select name="topic_id" required>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"
                            <?= ($edit_m && $edit_m['topic_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= $t['icon'] ?? '' ?> <?= htmlspecialchars($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required placeholder="e.g. Traffic Light Rules"
                           value="<?= htmlspecialchars($edit_m['title'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Content <span style="color:#999;font-weight:400;">(text shown in Reading Mode, optional if uploading a video/PDF)</span></label>
                    <textarea name="content" rows="4" placeholder="Write the reading material here..."><?= htmlspecialchars($edit_m['content'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>File <span style="color:#999;font-weight:400;">(image, PDF, or video)</span></label>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="material_file" accept="image/*,.pdf,video/*" onchange="previewFile(this)">
                        <span style="font-size:2rem;display:block;margin-bottom:8px;">📎</span>
                        <p style="font-size:14px;color:#666;margin:0;"><strong style="color:#3498db;">Click to upload</strong> or drag and drop</p>
                        <p style="font-size:12px;color:#999;margin-top:4px;">JPG, PNG, GIF, WEBP, PDF, MP4, WEBM, MOV</p>
                    </div>
                    <div id="filePreview" style="display:none;margin-top:12px;">
                        <p id="previewFileName" style="font-size:13px;color:#2c3e50;"></p>
                        <p style="font-size:12px;color:#27ae60;margin-top:6px;">✅ File selected</p>
                    </div>
                    <?php if ($edit_m && $edit_m['file_path']): ?>
                    <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:8px;">
                        <p style="font-size:13px;color:#666;margin-bottom:8px;">Current file (<?= htmlspecialchars($edit_m['file_type']) ?>):</p>
                        <?php if ($edit_m['file_type'] === 'image'): ?>
                        <img src="<?= htmlspecialchars($edit_m['file_path']) ?>" style="max-width:200px;border-radius:6px;">
                        <?php elseif ($edit_m['file_type'] === 'pdf'): ?>
                        <a href="<?= htmlspecialchars($edit_m['file_path']) ?>" target="_blank">📄 View current PDF</a>
                        <?php else: ?>
                        <video src="<?= htmlspecialchars($edit_m['file_path']) ?>" controls style="max-width:220px;border-radius:6px;"></video>
                        <?php endif; ?>
                        <br>
                        <label style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="remove_file"> Remove file
                        </label>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Sort Order <span style="color:#999;font-weight:400;">(lower shows first)</span></label>
                    <input type="number" name="sort_order" value="<?= $edit_m['sort_order'] ?? 0 ?>">
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $edit_id ? '💾 Update Material' : '➕ Add Material' ?>
                    </button>
                    <?php if ($edit_id): ?>
                    <a href="/testmate/admin/add-material.php" class="btn btn-outline">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 style="font-size:16px;font-weight:700;margin-bottom:14px;">Recent Materials</h2>
        <?php if (empty($materials)): ?>
        <div class="card" style="padding:24px;text-align:center;color:#888;">No materials added yet.</div>
        <?php else: ?>
        <?php foreach ($materials as $m): ?>
        <div class="mat-row">
            <div>
                <div style="font-weight:600;color:#2c3e50;"><?= htmlspecialchars($m['title']) ?></div>
                <div style="font-size:12px;color:#888;"><?= htmlspecialchars($m['topic_name']) ?> · <?= htmlspecialchars($m['file_type'] ?? 'text only') ?></div>
            </div>
            <a href="/testmate/admin/add-material.php?edit=<?= $m['id'] ?>" style="color:#3498db;font-size:13px;text-decoration:none;">Edit</a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        document.getElementById('previewFileName').textContent = '📎 ' + input.files[0].name;
        document.getElementById('filePreview').style.display = 'block';
        document.getElementById('uploadArea').style.borderColor = '#27ae60';
    }
}
</script>
</body>
</html>