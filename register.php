<?php
require 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /testmate/dashboard.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "That email is already registered. Please login.";
        } else {
            // Save the new user
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt   = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $hashed);

            if ($stmt->execute()) {
                $success = "Account created! You can now login.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="auth-wrap">
    <div class="auth-box">

        <!-- Logo -->
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:52px;height:52px;background:#3498db;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:white;">T</div>
        </div>

        <h2 style="text-align:center;">Create Your Account</h2>
        <p class="subtitle" style="text-align:center;">Start preparing for your NATIS test today</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <br><a href="/testmate/login.php" style="color:#27ae60;font-weight:700;">Click here to login →</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="e.g. John Nghipandua"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="At least 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Repeat your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;padding:12px;">
                Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-switch">
            Already have an account? <a href="/testmate/login.php">Login here</a>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>