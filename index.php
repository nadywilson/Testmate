<?php include 'includes/header.php'; ?>

<section class="hero">
    <h1>Pass Your <span>Learner's Licence</span><br>First Time.</h1>
    <p>Practice road signs, traffic rules, speed limits and take timed practice tests — completely free.</p>
    <div class="hero-buttons">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/testmate/dashboard.php" class="btn btn-lg" style="background:#f1c40f;color:#2c3e50;">Go to Dashboard</a>
            <a href="/testmate/mock-test.php" class="btn btn-lg" style="background:white;color:#2c3e50;">Take Practice Test</a>
        <?php else: ?>
            <a href="/testmate/register.php" class="btn btn-lg" style="background:#f1c40f;color:#2c3e50;">Get Started Free</a>
            <a href="/testmate/login.php" class="btn btn-lg" style="background:white;color:#2c3e50;">Login</a>
            <a href="/testmate/login.php" class="btn btn-lg" style="background:rgba(255,255,255,0.15);color:white;border:2px solid white;">Browse Materials</a>
        <?php endif; ?>
    </div>
</section>

<section class="features">
    <div class="feature-card">
        <span class="icon">📚</span>
        <h3>Study Materials</h3>
        <p>Learn road signs, traffic rules, speed limits and vehicle controls with clear explanations.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Start Studying</a>
    </div>
    <div class="feature-card">
        <span class="icon">✅</span>
        <h3>Topic Quizzes</h3>
        <p>Test yourself on one topic at a time. Get instant feedback and see what you got wrong.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Try a Quiz</a>
    </div>
    <div class="feature-card">
        <span class="icon">⏱️</span>
        <h3>Full Practice Test</h3>
        <p>50 questions, 60-minute countdown. Pass mark 80% — just like the real test.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">Start Practice Test</a>
    </div>
    <div class="feature-card">
        <span class="icon">📊</span>
        <h3>Track Progress</h3>
        <p>Monitor your scores, see your weak areas and know when you are ready for the real test.</p>
        <a href="/testmate/login.php" class="btn btn-outline" style="margin-top:16px;">View Progress</a>
    </div>
</section>

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