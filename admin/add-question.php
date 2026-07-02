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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!empty($_FILES['question_image']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $filename   = 'q_' . time() . '_' . rand(100,999) . '.' . $ext;
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/testmate/uploads/questions/';
            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = '/testmate/uploads/questions/' . $filename;
            } else {
                $msg = "❌ Upload failed. Check uploads/questions/ folder exists.";
            }
        } else {
            $msg = "❌ Only JPG, PNG, GIF or WEBP allowed.";
        }
    }

    if (isset($_POST['remove_image'])) {
        $image_path = null;
    }

    if (!$msg) {
        if ($pid > 0) {
            $s = $conn->prepare("UPDATE questions SET topic_id=?,question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_answer=?,explanation=?,image_path=? WHERE id=?");
            $s->bind_param("isssssssi", $tid,$qtext,$oa,$ob,$oc,$od,$ans,$expl,$image_path,$pid);
            $s->execute();
            $msg = "✅ Question updated!";
            $eq2 = $conn->prepare("SELECT * FROM questions WHERE id = ?");
            $eq2->bind_param("i", $pid);
            $eq2->execute();
            $edit_q  = $eq2->get_result()->fetch_assoc();
            $edit_id = $pid;
        } else {
            $s = $conn->prepare("INSERT INTO questions (topic_id,question,option_a,option_b,option_c,option_d,correct_answer,explanation,image_path) VALUES (?,?,?,?,?,?,?,?,?)");
            $s->bind_param("issssssss", $tid,$qtext,$oa,$ob,$oc,$od,$ans,$expl,$image_path);
            $s->execute();
            $msg    = "✅ Question added!";
            $edit_q = null;
            $edit_id = 0;
        }
    }
}

$topics = $conn->query("SELECT * FROM topics ORDER BY id")->fetch_all(MYSQLI_ASSOC);
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
        select,textarea{width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;font-family:inherit;outline:none;transition:border-color .2s;}
        select:focus,textarea:focus{border-color:#3498db;}
        textarea{resize:vertical;}
        .upload-area{border:2px dashed #ddd;border-radius:10px;padding:24px;text-align:center;cursor:pointer;background:#fafafa;position:relative;transition:all .2s;}
        .upload-area:hover{border-color:#3498db;background:#f0f8ff;}
        .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
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
            <?php if (str_starts_with($msg,'✅') && !$edit_id): ?>
            <a href="/testmate/admin/add-question.php" style="margin-left:12px;font-weight:600;">Add another →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card" style="max-width:700px;">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">

                <div class="form-group">
                    <label>Topic</label>
                    <select name="topic_id" required>
                        <?php foreach ($topics as $t): ?>
                        <option value="<?= $t['id'] ?>"
                            <?= ($edit_q && $edit_q['topic_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= $t['icon'] ?> <?= htmlspecialchars($t['name']) ?>
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
                    <label>Question Image <span style="color:#999;font-weight:400;">(optional — appears above the question)</span></label>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="question_image" accept="image/*" onchange="previewImage(this)">
                        <span style="font-size:2rem;display:block;margin-bottom:8px;">🖼️</span>
                        <p style="font-size:14px;color:#666;margin:0;"><strong style="color:#3498db;">Click to upload</strong> or drag and drop</p>
                        <p style="font-size:12px;color:#999;margin-top:4px;">JPG, PNG, GIF, WEBP — max 5MB</p>
                    </div>
                    <div id="imagePreview" style="display:none;margin-top:12px;">
                        <img id="previewImg" src="" style="max-width:200px;border-radius:8px;border:1px solid #eee;">
                        <p style="font-size:12px;color:#27ae60;margin-top:6px;">✅ Image selected</p>
                    </div>
                    <?php if ($edit_q && $edit_q['image_path']): ?>
                    <div style="margin-top:12px;padding:12px;background:#f0f4f8;border-radius:8px;">
                        <p style="font-size:13px;color:#666;margin-bottom:8px;">Current image:</p>
                        <img src="<?= htmlspecialchars($edit_q['image_path']) ?>" style="max-width:180px;border-radius:6px;">
                        <br>
                        <label style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" name="remove_image"> Remove image
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
        </div>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
            document.getElementById('uploadArea').style.borderColor = '#27ae60';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>