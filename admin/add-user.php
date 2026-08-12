<?php
require '../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /testmate/login.php"); exit();
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    if ($name === '' || $email === '' || $password === '') {
        $message = "Name, email, and password are required.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $message_type = 'error';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "A user with this email already exists.";
            $message_type = 'error';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $email, $hashed, $role);
            if ($stmt->execute()) {
                $message = "User added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
        $check->close();
    }
}

$users = $conn->query("
    SELECT id, name, email, role, created_at,
           (SELECT COUNT(*) FROM quiz_scores WHERE user_id = users.id) AS quiz_count,
           (SELECT COUNT(*) FROM mock_scores WHERE user_id = users.id) AS mock_count
    FROM users
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$total_users = count($users);
$learner_count = 0;
$admin_count = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $admin_count++;
    else $learner_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User – TestMate Admin</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        .admin-wrap{display:flex;min-height:calc(100vh - 60px);}
        .sidebar{width:230px;background:#1a252f;color:white;padding:24px 0;flex-shrink:0;position:sticky;top:60px;height:calc(100vh - 60px);overflow-y:auto;}
        .sidebar h3{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.4);padding:0 20px;margin-bottom:8px;margin-top:20px;}
        .sidebar h3:first-child{margin-top:0;}
        .sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.8);text-decoration:none;font-size:14px;transition:all .15s;}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.1);color:white;}
        .main-content{flex:1;padding:30px;background:#f5f6fa;overflow-y:auto;}
        .card{background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:20px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-weight:600;margin-bottom:6px;font-size:14px;color:#374151;}
        .form-group input,.form-group select{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;}
        .form-group input:focus{outline:none;border-color:#2563eb;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .btn{padding:10px 20px;border:none;border-radius:6px;font-size:14px;cursor:pointer;font-weight:600;transition:opacity .2s;text-decoration:none;display:inline-block;}
        .btn:hover{opacity:.9;}
        .btn-primary{background:#2563eb;color:white;}
        .btn-outline{background:white;color:#374151;border:1px solid #d1d5db;}
        .alert{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
        table{width:100%;border-collapse:collapse;font-size:14px;}
        th,td{padding:12px 16px;text-align:left;border-bottom:1px solid #e5e7eb;}
        th{background:#f9fafb;font-weight:600;color:#4b5563;font-size:12px;text-transform:uppercase;letter-spacing:.05em;}
        tr:hover{background:#f9fafb;}
        .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;text-transform:uppercase;}
        .badge-admin{background:#dbeafe;color:#1e40af;}
        .badge-learner{background:#d1fae5;color:#065f46;}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:24px;}
        .stat-card{background:white;padding:16px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);text-align:center;}
        .stat-value{font-size:1.75rem;font-weight:800;color:#2563eb;}
        .stat-label{font-size:13px;color:#6b7280;margin-top:4px;}
        .empty{text-align:center;padding:40px;color:#9ca3af;}
        .role-select{display:flex;gap:12px;margin-bottom:16px;}
        .role-option{flex:1;border:2px solid #e5e7eb;border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:all .2s;}
        .role-option:hover{border-color:#2563eb;}
        .role-option.selected{border-color:#2563eb;background:#eff6ff;}
        .role-option input{display:none;}
        .role-icon{font-size:28px;margin-bottom:8px;}
        .role-label{font-weight:600;font-size:14px;color:#374151;}
        .role-desc{font-size:12px;color:#6b7280;margin-top:4px;}
    </style>
</head>
<body>
<nav class="navbar">
    <a href="/testmate/admin/index.php" class="brand"><span class="brand-icon">T</span> TestMate Admin</a>
    <div class="nav-links">
        <a href="/testmate/index.php" style="color:rgba(255,255,255,.8);font-size:14px;">View Site</a>
        <a href="/testmate/logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="admin-wrap">
    <div class="sidebar">
        <h3>Main</h3>
        <a href="/testmate/admin/index.php">Dashboard</a>
        <a href="/testmate/admin/review-scores.php">Review Scores</a>
        <a href="/testmate/admin/users.php">Users</a>
        <a href="/testmate/admin/add-user.php" class="active">Add User</a>
        <a href="/testmate/admin/stats.php">Statistics</a>
        <h3>Questions</h3>
        <a href="/testmate/admin/questions.php">All Questions</a>
        <a href="/testmate/admin/add-question.php">Add Question</a>
        <a href="/testmate/admin/assign-quiz.php">Assign Quiz</a>
        <h3>Content</h3>
        <a href="/testmate/admin/materials.php">Materials</a>
        <a href="/testmate/admin/add-material.php">Add Material</a>
    </div>

    <div class="main-content">

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom:16px;">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h1 style="font-size:22px;">Add User</h1>
            <a href="/testmate/admin/users.php" class="btn btn-outline">Back to Users</a>
        </div>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#059669;"><?php echo $learner_count; ?></div>
                <div class="stat-label">Learners</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#7c3aed;"><?php echo $admin_count; ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="card">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:16px;">New User Details</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Account Type</label>
                    <div class="role-select">
                        <label class="role-option selected" onclick="selectRole(this, 'learner')">
                            <input type="radio" name="role" value="learner" checked>
                            <div class="role-icon">&#127891;</div>
                            <div class="role-label">Learner</div>
                            <div class="role-desc">Can take quizzes, mock tests, and track progress</div>
                        </label>
                        <label class="role-option" onclick="selectRole(this, 'admin')">
                            <input type="radio" name="role" value="admin">
                            <div class="role-icon">&#128737;</div>
                            <div class="role-label">Administrator</div>
                            <div class="role-desc">Full access to manage questions, users, and content</div>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label>Phone (optional)</label>
                        <input type="text" name="phone" placeholder="Not saved - column missing">
                    </div>
                </div>

                <button type="submit" name="add_user" class="btn btn-primary">Create Account</button>
            </form>
        </div>

        <!-- Existing Users -->
        <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">All Users</h2>
        <?php if (empty($users)): ?>
        <div class="card" style="text-align:center;padding:40px;color:#888;">No users registered yet.</div>
        <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Quizzes</th><th>Mocks</th><th>Joined</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge badge-admin">Admin</span>
                        <?php else: ?>
                        <span class="badge badge-learner">Learner</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $u['quiz_count']; ?></td>
                    <td><?php echo $u['mock_count']; ?></td>
                    <td style="font-size:13px;color:#888;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function selectRole(el, role) {
    document.querySelectorAll('.role-option').forEach(function(opt) {
        opt.classList.remove('selected');
    });
    el.classList.add('selected');
    el.querySelector('input').checked = true;
}
</script>
</body>
</html>