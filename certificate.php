<?php
require 'includes/auth.php';
require 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

$best = $conn->prepare("SELECT score, total, passed, taken_at FROM mock_scores WHERE user_id = ? AND passed = 1 ORDER BY score DESC LIMIT 1");
$best->bind_param("i", $user_id);
$best->execute();
$best = $best->get_result()->fetch_assoc();

if (!$best) {
    include 'includes/header.php';
    echo '<div style="max-width:600px;margin:60px auto;text-align:center;">
        <h2 style="color:#e74c3c;margin-bottom:12px;">No Certificate Yet</h2>
        <p style="color:#666;margin-bottom:20px;">You need to pass the mock test with 80% or more to earn a certificate.</p>
        <a href="/testmate/mock-test.php" class="btn btn-primary">Take the Practice Test</a>
    </div>';
    include 'includes/footer.php';
    exit();
}

$percentage  = round($best['score'] / $best['total'] * 100);
$date        = date('d F Y', strtotime($best['taken_at']));
$cert_number = 'TM-' . strtoupper(substr(md5($user_id . $best['taken_at']), 0, 8));
?>
<?php include 'includes/header.php'; ?>

<div class="container" style="max-width:800px;">

    <div style="text-align:center;margin-bottom:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
        <button onclick="window.print()" class="btn btn-primary">Print Certificate</button>
        <a href="/testmate/dashboard.php" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <div id="certificate" style="
        border: 12px solid #2c3e50;
        border-radius: 8px;
        padding: 50px;
        text-align: center;
        background: white;
        font-family: Georgia, serif;
        box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    ">
        <div style="border: 3px solid #f1c40f;padding: 30px;border-radius: 4px;">

            <div style="margin-bottom:24px;">
                <div style="display:inline-block;background:#2c3e50;color:white;width:60px;height:60px;border-radius:12px;line-height:60px;font-size:28px;font-weight:800;margin-bottom:10px;">T</div>
                <div style="font-size:13px;letter-spacing:3px;text-transform:uppercase;color:#888;margin-bottom:4px;">TestMate Namibia</div>
                <div style="font-size:11px;color:#aaa;letter-spacing:1px;">Learner's Licence Preparation Platform</div>
            </div>

            <div style="font-size:13px;letter-spacing:4px;text-transform:uppercase;color:#888;margin-bottom:12px;">Certificate of Achievement</div>

            <div style="font-size:15px;color:#555;margin-bottom:10px;">This is to certify that</div>

            <div style="font-size:36px;font-weight:700;color:#2c3e50;margin-bottom:10px;font-style:italic;border-bottom:2px solid #f1c40f;padding-bottom:10px;">
                <?= htmlspecialchars($name) ?>
            </div>

            <div style="font-size:15px;color:#555;margin:16px 0;">
                has successfully completed the TestMate Practice Test<br>
                and demonstrated readiness for the Namibian Learner's Licence Test
            </div>

            <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:20px 0;display:inline-block;min-width:250px;">
                <div style="font-size:42px;font-weight:800;color:#27ae60;"><?= $percentage ?>%</div>
                <div style="font-size:14px;color:#888;">Score: <?= $best['score'] ?>/<?= $best['total'] ?> correct</div>
            </div>

            <div style="font-size:14px;color:#888;margin-top:16px;">
                Date Achieved: <strong style="color:#2c3e50;"><?= $date ?></strong>
            </div>

            <div style="margin-top:30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;">
                <div style="text-align:left;">
                    <div style="width:150px;border-top:2px solid #2c3e50;padding-top:6px;font-size:13px;color:#666;">TestMate Administrator</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:11px;color:#aaa;">Certificate No.</div>
                    <div style="font-size:13px;font-weight:600;color:#2c3e50;"><?= $cert_number ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="width:150px;border-top:2px solid #2c3e50;padding-top:6px;font-size:13px;color:#666;margin-left:auto;">Date</div>
                </div>
            </div>

        </div>
    </div>

</div>

<style>
@media print {
    .navbar, button, .btn, header, footer,
    [style*="position:fixed"] { display: none !important; }
    body { background: white; }
    .container { max-width: 100%; margin: 0; padding: 0; }
    #certificate { box-shadow: none; }
}
</style>

<?php include 'includes/footer.php'; ?>