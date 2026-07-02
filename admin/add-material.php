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

// Handle delete
if (isset($_GET['delete'])) {
    $del = $conn->prepare("DELETE FROM materials WHERE id = ?");
    $del->bind_param("i", $_GET['delete']);
    $del->execute();
    header("Location: /testmate/admin/add-material.php?deleted=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic_id   = (int)$_POST['topic_id'];
    $title      = trim($_POST['title']);
    $content    = trim($_POST['content']);
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $pid        = (int)($_POST['edit_id'] ?? 0);
    $file_path  = $edit_m['file_path'] ?? null;
    $file_type  = $edit_m['file_type'] ?? 'none';

    if (!empty($_FILES['material_file']['name'])) {
        $ext         = strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION));
        $allowed_img = ['jpg','jpeg','png','gif','webp'];
        $allowed_pdf = ['pdf'];

        if (in_array($ext, $allowed_img)) {
            $file_type = 'image';
        } elseif (in_array($ext, $allowed_pdf)) {
            $file_type = 'pdf';
        } else {
            $msg = "❌ Only images (JPG, PNG, WEBP) or PDF files allowed.";
        }

        if (!$msg) {
            $filename   = 'mat_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/testmate/uploads/materials/';
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $upload_dir . $filename)) {
                $file_path = '/testmate/uploads/materials/' . $filename;
            } else {
                $msg = "❌ Upload failed. Make sure the uploads/materials/ folder exists.";
            }
        }
    }

    if (!$msg) {
        if ($pid > 0) {
            $s = $conn->prepare("UPDATE materials SET topic_id=?,title=?,content=?,file_path=?,file_type=?,sort_order=? WHERE id=?");
            $s->bind_param("issssii", $topic_id,$title,$content,$file_path,$file_type,$sort_order,$pid);
            $s->execute();
            $msg = "✅ Material updated successfully!";
        } else {
            $s = $conn->prepare("INSERT INTO materials (topic_id,title,content,file_path,file_type,sort_order) VALUES (?,?,?,?,?,?)");
            $s->bind_param("issssi", $topic_id,$title,$content,$file_path,$file_type,$sort_order);
            $s->execute();
            $msg = "✅ Material added successfully!";
            $edit_m = null;
            $edit_id = 0;
        }
    }
}

$topics    = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$materials = $conn->query("
    SELECT m.*, t.name AS topic_name, t.icon
    FROM materials m
    JOIN topics t ON m.topic_id = t.id
    ORDER BY m.topic_id, m.sort_order
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Study Materials – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap { display:flex; min-height:calc(100vh - 60px); }
        .sidebar { width:230px; background:#1a252f; color:white; padding:24px 0; flex-shrink:0; position:sticky; top:60px; height:calc(100vh - 60px); overflow-y:auto; }
        .sidebar h3 { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.4); padding:0 20px; margin-bottom:8px; margin-top:20px; }
        .sidebar h3:first-child { margin-top:0; }
        .sidebar a { display:flex; align-items:center; gap:10px; padding:10px 20px; color:rgba(255,255,255,.8); text-decoration:none; font-size:14px; transition:all .15s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(255,255,255,.1); color:white; }
        .main-content { flex:1; padding:30px; background:#f5f6fa; overflow-y:auto; }
        select, textarea { width:100%; padding:10px 14px; border:1.5px solid #ddd; border-radius:8px; font-size:15px; font-family:inherit; outline:none; transition:border-color .2s; }
        select:focus, textarea:focus { border-color:#3498db; }
        textarea { resize:vertical; }
        .upload-area { border:2px dashed #ddd; border-radius:10px; padding:24px; text-align:center; cursor:pointer; background:#fafafa; position:relative; transition:all .2s; }
        .upload-area:hover { border-color:#3498db; background:#f0f8ff; }
        .upload-area input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
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
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">📚 Materials</a>
        <a href="/testmate/admin/add-material.php" class="active">➕ Add Material</a>
    </div>

    <div class="main-content">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:start;">

            <!-- LEFT: Form -->
            <div>
                <h2 style="font-size:20px;margin-bottom:20px;">
                    <?= $edit_id ? '✏️ Edit Material' : '➕ Add Study Material' ?>
                </h2>

                <?php if ($msg): ?>
                <div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:16px;">
                    <?= $msg ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success" style="margin-bottom:16px;">Material deleted.</div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

                        <div class="form-group">
                            <label>Topic</label>
                            <select name="topic_id" required>
                                <?php foreach ($topics as $t): ?>
                                <option value="<?= $t['id'] ?>"
                                    <?= ($edit_m && $edit_m['topic_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Chapter Title</label>
                            <input type="text" name="title" required
                                   placeholder="e.g. Warning Signs"
                                   value="<?= htmlspecialchars($edit_m['title'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Content <span style="color:#999;font-weight:400;">(text, explanation)</span></label>
                            <textarea name="content" rows="6"
                                placeholder="Write the study content here..."><?= htmlspecialchars($edit_m['content'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Attach File <span style="color:#999;font-weight:400;">(image or PDF — optional)</span></label>
                            <div class="upload-area" id="uploadArea">
                                <input type="file" name="material_file" accept="image/*,.pdf"
                                       onchange="previewFile(this)">
                                <span style="font-size:2rem;display:block;margin-bottom:8px;">📎</span>
                                <p style="font-size:14px;color:#666;margin:0;"><strong style="color:#3498db;">Click to upload</strong> or drag and drop</p>
                                <p style="font-size:12px;color:#999;margin-top:4px;">Images (JPG, PNG, WEBP) or PDF</p>
                            </div>

                            <div id="filePreview" style="display:none;margin-top:12px;padding:12px;background:#eafaf1;border-radius:8px;font-size:13px;color:#27ae60;">
                                ✅ File selected: <span id="fileName"></span>
                            </div>

                            <?php if ($edit_m && $edit_m['file_path']): ?>
                            <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:8px;">
                                <p style="font-size:13px;color:#666;margin-bottom:8px;">Current file:</p>
                                <?php if ($edit_m['file_type'] === 'image'): ?>
                                    <img src="<?= htmlspecialchars($edit_m['file_path']) ?>" style="max-width:150px;border-radius:6px;">
                                <?php elseif ($edit_m['file_type'] === 'pdf'): ?>
                                    <a href="<?= htmlspecialchars($edit_m['file_path']) ?>" target="_blank"
                                       style="color:#3498db;font-size:13px;">📄 View PDF</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Sort Order <span style="color:#999;font-weight:400;">(lower number = shown first)</span></label>
                            <input type="number" name="sort_order" min="0" value="<?= $edit_m['sort_order'] ?? 0 ?>">
                        </div>

                        <div style="display:flex;gap:10px;margin-top:8px;">
                            <button type="submit" class="btn btn-primary">
                                <?= $edit_id ? '💾 Update Material' : '➕ Add Material' ?>
                            </button>
                            <?php if ($edit_id): ?>
                            <a href="/testmate/admin/add-material.php" class="btn btn-outline">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT: Existing Materials -->
            <div>
                <h2 style="font-size:20px;margin-bottom:20px;">📚 All Materials (<?= count($materials) ?>)</h2>

                <?php if (empty($materials)): ?>
                <div class="card" style="text-align:center;padding:40px;color:#888;">
                    No materials added yet.
                </div>
                <?php else: ?>

                <?php
                $grouped = [];
                foreach ($materials as $m) {
                    $grouped[$m['topic_name']][] = $m;
                }
                ?>

                <?php foreach ($grouped as $topic_name => $items): ?>
                <div style="margin-bottom:20px;">
                    <h3 style="font-size:14px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
                        <?= htmlspecialchars($topic_name) ?>
                    </h3>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach ($items as $m): ?>
                        <div class="card" style="padding:14px 16px;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                                <div style="flex:1;">
                                    <div style="font-weight:600;font-size:14px;margin-bottom:4px;">
                                        <?= htmlspecialchars($m['title']) ?>
                                    </div>
                                    <div style="font-size:12px;color:#999;">
                                        <?php if ($m['file_type'] === 'image'): ?>
                                            🖼️ Has image
                                        <?php elseif ($m['file_type'] === 'pdf'): ?>
                                            📄 Has PDF
                                        <?php endif; ?>
                                        <?= $m['content'] ? '· ' . mb_substr(strip_tags($m['content']), 0, 60) . '...' : '' ?>
                                    </div>
                                </div>
                                <div style="display:flex;gap:8px;flex-shrink:0;">
                                    <a href="/testmate/admin/add-material.php?edit=<?= $m['id'] ?>"
                                       style="font-size:12px;color:#3498db;text-decoration:none;">Edit</a>
                                    <a href="/testmate/admin/add-material.php?delete=<?= $m['id'] ?>"
                                       onclick="return confirm('Delete this material?')"
                                       style="font-size:12px;color:#e74c3c;text-decoration:none;">Delete</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileName').textContent = input.files[0].name;
        document.getElementById('filePreview').style.display = 'block';
        document.getElementById('uploadArea').style.borderColor = '#27ae60';
    }
}
</script>
</body>
</html>