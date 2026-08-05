<?php include 'includes/header.php'; ?>

<style>
/* ── Slideshow Background ── */
.hero {
    position: relative;
    min-height: 260px;
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

/* Dark overlay so text is readable */
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
    padding: 20px 20px;
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

/* Slideshow dots */
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
</style>

<!-- Hero with Slideshow Background -->
<section class="hero">

    <!-- Slideshow -->
    <div class="slideshow" id="slideshow">
        <div class="slide active" style="background-image: url('/testmate/images/slide1.jpg');"></div>
<div class="slide" style="background-image: url('/testmate/images/slide2.jpg');"></div>
<div class="slide" style="background-image: url('/testmate/images/slide3.jpg');"></div>
<div class="slide" style="background-image: url('/testmate/images/slide4.jpg');"></div>
    </div>

    <!-- Dots -->
    <div class="slide-dots" id="dots">
        <button class="dot active" onclick="goToSlide(0)"></button>
        <button class="dot" onclick="goToSlide(1)"></button>
        <button class="dot" onclick="goToSlide(2)"></button>
        <button class="dot" onclick="goToSlide(3)"></button>
    </div>

    <!-- Content -->
    <div class="hero-content">
        <h1>Pass Your <span>Learner's Licence</span><br>First Time.</h1>
        <p>Practice road signs, traffic rules, speed limits and take timed practice tests — completely free.</p>
        <div class="hero-buttons">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/testmate/dashboard.php" class="btn btn-lg" style="background:#f1c40f;color:#2c3e50;font-weight:700;">Go to Dashboard</a>
                <a href="/testmate/mock-test.php" class="btn btn-lg" style="background:white;color:#2c3e50;font-weight:700;">Take Practice Test</a>
            <?php else: ?>
                <a href="/testmate/register.php" class="btn btn-lg" style="background:#f1c40f;color:#2c3e50;font-weight:700;">Get Started Free</a>
                <a href="/testmate/login.php" class="btn btn-lg" style="background:white;color:#2c3e50;font-weight:700;">Login</a>
                <a href="/testmate/login.php" class="btn btn-lg" style="background:rgba(255,255,255,0.15);color:white;border:2px solid white;font-weight:700;">Browse Materials</a>
            <?php endif; ?>
        </div>
    </div>

</section>

<!-- Feature Cards -->
<section class="features">
    <div class="feature-card">
        <h3>Study Materials</h3>
        <p>Learn road signs, traffic rules, speed limits and vehicle controls with clear explanations.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Start Studying</a>
    </div>
    <div class="feature-card">
        <h3>Topic Quizzes</h3>
        <p>Test yourself on one topic at a time. Get instant feedback and see what you got wrong.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Try a Quiz</a>
    </div>
    <div class="feature-card">
        <h3>Full Practice Test</h3>
        <p>50 questions, 60-minute countdown. Pass mark 80% — just like the real test.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Start Practice Test</a>
    </div>
    <div class="feature-card">
        <h3>Track Progress</h3>
        <p>Monitor your scores, see your weak areas and know when you are ready for the real test.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">View Progress</a>
    </div>
</section>

<!-- Stats Bar -->
<section style="background:#2c3e50;color:white;padding:40px 20px;text-align:center;">
    <div style="max-width:800px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:30px;">
        <div>
            <div style="font-size:2.5rem;font-weight:800;color:#f1c40f;">60+</div>
            <div style="opacity:0.8;font-size:14px;">Practice Questions</div>
        </div>
        <div>
            <div style="font-size:2.5rem;font-weight:800;color:#f1c40f;">5</div>
            <div style="opacity:0.8;font-size:14px;">Topics Covered</div>
        </div>
        <div>
            <div style="font-size:2.5rem;font-weight:800;color:#f1c40f;">80%</div>
            <div style="opacity:0.8;font-size:14px;">Pass Mark</div>
        </div>
        <div>
            <div style="font-size:2.5rem;font-weight:800;color:#f1c40f;">Free</div>
            <div style="opacity:0.8;font-size:14px;">Always Free</div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

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

// Auto advance every 5 seconds
setInterval(nextSlide, 5000);
</script>