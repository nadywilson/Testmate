<?php
require 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMate – Namibia Learner's Licence</title>
    <link rel="stylesheet" href="/testmate/css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }

        /* Top Navbar */
        .topbar {
            background: #2c3e50;
            color: white;
            padding: 0 24px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 22px;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background .2s;
        }
        .menu-btn:hover { background: rgba(255,255,255,.15); }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; font-weight: 700; font-size: 18px; }
        .brand-icon { width: 34px; height: 34px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .topbar-right a { color: rgba(255,255,255,.85); text-decoration: none; font-size: 14px; transition: color .2s; }
        .topbar-right a:hover { color: white; }
        .btn-logout { background: #e74c3c; color: white; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn-login { background: #3498db; color: white; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; }

        /* Sidebar - Hidden by default */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,.4);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all .3s;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }

        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 260px;
            height: 100vh;
            background: #1a252f;
            color: white;
            padding: 80px 0 24px;
            z-index: 999;
            transform: translateX(-100%);
            transition: transform .3s ease;
            overflow-y: auto;
        }
        .sidebar.active { transform: translateX(0); }
        .sidebar h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255,255,255,.35);
            padding: 0 24px;
            margin: 20px 0 10px;
        }
        .sidebar h3:first-child { margin-top: 0; }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: 15px;
            transition: all .15s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,.08);
            color: white;
        }
        .sidebar-icon { font-size: 20px; width: 24px; text-align: center; }

        /* Main Content */
        .main-content { margin-top: 60px; }

        /* Hero */
        .hero {
            position: relative;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
        }
        .slideshow {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        .slide.active { opacity: 1; }
        .slide::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.52);
        }
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .hero-content h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        .hero-content h1 span { color: #f1c40f; }
        .hero-content p {
            font-size: 0.9rem;
            opacity: 0.92;
            margin-bottom: 32px;
            line-height: 1.6;
            text-shadow: 0 1px 4px rgba(0,0,0,0.4);
        }
        .hero-buttons {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-yellow { background: #f1c40f; color: #2c3e50; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; border: none; cursor: pointer; }
        .btn-white { background: white; color: #2c3e50; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; border: none; cursor: pointer; }
        .btn-outline-white { background: rgba(255,255,255,0.15); color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 15px; border: 2px solid white; cursor: pointer; }

        .slide-dots {
            position: absolute;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            display: flex;
            gap: 8px;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            cursor: pointer;
            transition: background .3s;
            border: none;
            padding: 0;
        }
        .dot.active { background: white; }

        /* Feature Cards */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            padding: 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .feature-card {
            background: white;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }
        .feature-card h3 { font-size: 18px; margin-bottom: 12px; color: #2c3e50; }
        .feature-card p { color: #888; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
        .feature-card .btn-outline {
            display: inline-block;
            padding: 10px 24px;
            border: 2px solid #2c3e50;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all .2s;
        }
        .feature-card .btn-outline:hover { background: #2c3e50; color: white; }

        /* Stats Bar */
        .stats-bar {
            background: #2c3e50;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .stats-bar-inner {
            max-width: 800px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 30px;
        }
        .stats-bar .num { font-size: 2.5rem; font-weight: 800; color: #f1c40f; }
        .stats-bar .lbl { opacity: 0.8; font-size: 14px; }
    </style>
</head>
<body>

<!-- Top Navbar -->
<div class="topbar">
    <div class="topbar-left">
        <button class="menu-btn" onclick="toggleSidebar()">&#9776;</button>
        <a href="/testmate/index.php" class="brand">
            <div class="brand-icon">T</div>
            TestMate
        </a>
    </div>
    <div class="topbar-right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/testmate/progress.php">Progress</a>
            <a href="/testmate/rankings.php">Rankings</a>
            <a href="/testmate/profile.php">Profile</a>
            <a href="/testmate/logout.php" class="btn-logout">Logout (<?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>)</a>
        <?php else: ?>
            <a href="/testmate/login.php">Login</a>
            <a href="/testmate/register.php" class="btn-login">Get Started</a>
        <?php endif; ?>
    </div>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Slide-out Sidebar -->
<div class="sidebar" id="sidebar">
    <h3>Menu</h3>
    <a href="/testmate/quiz.php"><span class="sidebar-icon">&#128216;</span> Quizzes</a>
    <a href="/testmate/history.php"><span class="sidebar-icon">&#128203;</span> History</a>
    <a href="/testmate/mock-test.php"><span class="sidebar-icon">&#128221;</span> Mock Test</a>
    <a href="/testmate/study-materials.php"><span class="sidebar-icon">&#128218;</span> Study Materials</a>
    <a href="/testmate/simulations.php"><span class="sidebar-icon">&#127916;</span> Simulations</a>

    <h3>Account</h3>
    <a href="/testmate/progress.php"><span class="sidebar-icon">&#128200;</span> Progress</a>
    <a href="/testmate/rankings.php"><span class="sidebar-icon">&#127942;</span> Rankings</a>
    <a href="/testmate/profile.php"><span class="sidebar-icon">&#128100;</span> Profile</a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="/testmate/logout.php"><span class="sidebar-icon">&#128682;</span> Logout</a>
    <?php else: ?>
    <a href="/testmate/login.php"><span class="sidebar-icon">&#128273;</span> Login</a>
    <?php endif; ?>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Hero with Slideshow -->
    <section class="hero">
        <div class="slideshow" id="slideshow">
            <div class="slide active" style="background-image: url('/testmate/images/slide1.jpg');"></div>
            <div class="slide" style="background-image: url('/testmate/images/slide2.jpg');"></div>
            <div class="slide" style="background-image: url('/testmate/images/slide3.jpg');"></div>
            <div class="slide" style="background-image: url('/testmate/images/slide4.jpg');"></div>
        </div>
        <div class="slide-dots" id="dots">
            <button class="dot active" onclick="goToSlide(0)"></button>
            <button class="dot" onclick="goToSlide(1)"></button>
            <button class="dot" onclick="goToSlide(2)"></button>
            <button class="dot" onclick="goToSlide(3)"></button>
        </div>
        <div class="hero-content">
            <h1>Pass Your <span>Learner's Licence</span><br>First Time.</h1>
            <p>Practice road signs, traffic rules, speed limits and take timed practice tests — completely free.</p>
            <div class="hero-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/testmate/dashboard.php" class="btn-yellow">Go to Dashboard</a>
                    <a href="/testmate/mock-test.php" class="btn-white">Take Practice Test</a>
                <?php else: ?>
                    <a href="/testmate/register.php" class="btn-yellow">Get Started Free</a>
                    <a href="/testmate/login.php" class="btn-white">Login</a>
                    <a href="/testmate/study-materials.php" class="btn-outline-white">Browse Materials</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Feature Cards -->
    <section class="features">
        <div class="feature-card">
            <h3>Study Materials</h3>
            <p>Learn road signs, traffic rules, speed limits and vehicle controls with clear explanations.</p>
            <a href="/testmate/login.php" class="btn-outline">Start Studying</a>
        </div>
        <div class="feature-card">
            <h3>Topic Quizzes</h3>
            <p>Test yourself on one topic at a time. Get instant feedback and see what you got wrong.</p>
            <a href="/testmate/login.php" class="btn-outline">Try a Quiz</a>
        </div>
        <div class="feature-card">
            <h3>Full Practice Test</h3>
            <p>50 questions, 60-minute countdown. Pass mark 80% — just like the real test.</p>
            <a href="/testmate/login.php" class="btn-outline">Start Practice Test</a>
        </div>
        <div class="feature-card">
            <h3>Track Progress</h3>
            <p>Monitor your scores, see your weak areas and know when you are ready for the real test.</p>
            <a href="/testmate/login.php" class="btn-outline">View Progress</a>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="stats-bar">
        <div class="stats-bar-inner">
            <div>
                <div class="num">60+</div>
                <div class="lbl">Practice Questions</div>
            </div>
            <div>
                <div class="num">5</div>
                <div class="lbl">Topics Covered</div>
            </div>
            <div>
                <div class="num">80%</div>
                <div class="lbl">Pass Mark</div>
            </div>
            <div>
                <div class="num">Free</div>
                <div class="lbl">Always Free</div>
            </div>
        </div>
    </section>

</div>

<script>
let current = 0;
const slides = document.querySelectorAll('.slide');
const dots   = document.querySelectorAll('.dot');

function goToSlide(n) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = n;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
}

function nextSlide() {
    goToSlide((current + 1) % slides.length);
}

setInterval(nextSlide, 5000);

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }
});
</script>

</body>
</html>