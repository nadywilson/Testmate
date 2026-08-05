<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$msg     = '';
$error   = '';

$u = $conn->prepare("SELECT * FROM users WHERE id = ?");
$u->bind_param("i", $user_id);
$u->execute();
$user = $u->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']);
        $bio   = trim($_POST['bio']);
        $lcode = $_POST['licence_code'];
        $pic   = $user['profile_pic'];

        if (!empty($_FILES['profile_pic']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $filename   = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/testmate/uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename)) {
                    $pic = '/testmate/uploads/profiles/' . $filename;
                }
            }
        }

        $s = $conn->prepare("UPDATE users SET name=?, bio=?, licence_code=?, profile_pic=? WHERE id=?");
        $s->bind_param("ssssi", $name, $bio, $lcode, $pic, $user_id);
        $s->execute();
        $_SESSION['name'] = $name;
        $msg = "Profile updated successfully!";

        $u2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $u2->bind_param("i", $user_id);
        $u2->execute();
        $user = $u2->get_result()->fetch_assoc();
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new     = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $s = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s->bind_param("si", $hashed, $user_id);
            $s->execute();
            $msg = "Password changed successfully!";
        }
    }
}

$quiz_count = $conn->prepare("SELECT COUNT(*) AS c FROM quiz_scores WHERE user_id=?");
$quiz_count->bind_param("i", $user_id);
$quiz_count->execute();
$quiz_count = $quiz_count->get_result()->fetch_assoc()['c'];

$mock_count = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(passed),0) AS p FROM mock_scores WHERE user_id=?");
$mock_count->bind_param("i", $user_id);
$mock_count->execute();
$mock_data = $mock_count->get_result()->fetch_assoc();
?>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h1>My Profile</h1>
    <p>Manage your account and preferences</p>
</div>

<div class="container" style="max-width:900px;">

    <?php if ($msg): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

        <!-- Left: Profile Form -->
        <div class="card">
            <h2 style="font-size:18px;margin-bottom:20px;">Profile Information</h2>

            <div style="text-align:center;margin-bottom:20px;">
                <?php if ($user['profile_pic']): ?>
                <img src="<?= htmlspecialchars($user['profile_pic']) ?>"
                     style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #2c3e50;">
                <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#2c3e50;color:white;display:inline-flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;margin:0 auto;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*"
                           style="width:100%;padding:8px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;">
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email (cannot be changed)</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                           style="background:#f8f9fa;color:#888;width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;">
                </div>

                <div class="form-group">
                    <label>Licence Code</label>
                    <select name="licence_code" style="width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;outline:none;">
                        <option value="Code1" <?= $user['licence_code'] === 'Code1' ? 'selected' : '' ?>>Code 1 — Motorcycle</option>
                        <option value="Code2" <?= $user['licence_code'] === 'Code2' ? 'selected' : '' ?>>Code 2 — Car / Light Vehicle</option>
                        <option value="Code3" <?= $user['licence_code'] === 'Code3' ? 'selected' : '' ?>>Code 3 — Heavy Vehicle / Truck</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>About Me (optional)</label>
                    <textarea name="bio" rows="3"
                        style="width:100%;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:15px;font-family:inherit;resize:vertical;"
                        placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
            </form>
        </div>

        <!-- Right Column -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Stats -->
            <div class="card">
                <h2 style="font-size:18px;margin-bottom:16px;">My Stats</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="text-align:center;padding:16px;background:#f8f9fa;border-radius:8px;">
                        <div style="font-size:1.8rem;font-weight:800;color:#3498db;"><?= $quiz_count ?></div>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Quizzes Taken</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:#f8f9fa;border-radius:8px;">
                        <div style="font-size:1.8rem;font-weight:800;color:#2c3e50;"><?= $mock_data['c'] ?></div>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Mock Tests</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:#f8f9fa;border-radius:8px;">
                        <div style="font-size:1.8rem;font-weight:800;color:#27ae60;"><?= $mock_data['p'] ?></div>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Tests Passed</div>
                    </div>
                    <div style="text-align:center;padding:16px;background:#f8f9fa;border-radius:8px;">
                        <div style="font-size:1.8rem;font-weight:800;color:#f1c40f;"><?= $user['streak'] ?? 0 ?></div>
                        <div style="font-size:12px;color:#888;margin-top:4px;">Day Streak</div>
                    </div>
                </div>
                <div style="margin-top:14px;font-size:13px;color:#888;">
                    <div>Member since: <strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong></div>
                    <div style="margin-top:4px;">Licence Code: <strong><?= $user['licence_code'] ?></strong></div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <h2 style="font-size:18px;margin-bottom:16px;">Change Password</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="At least 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full">Change Password</button>
                </form>
            </div>

            <!-- Certificate -->
            <div class="card" style="text-align:center;padding:24px;">
                <h3 style="font-size:16px;margin-bottom:8px;">Achievement Certificate</h3>
                <p style="color:#888;font-size:13px;margin-bottom:14px;">
                    Pass the mock test with 80% or more to earn your certificate.
                </p>
                <a href="/testmate/certificate.php" class="btn btn-primary">View Certificate</a>
            </div>

        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>