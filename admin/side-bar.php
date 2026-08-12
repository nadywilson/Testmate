<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <h3>MAIN</h3>
    <a href="/testmate/admin/index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="/testmate/admin/review-scores.php" class="<?= $current_page === 'review-scores.php' ? 'active' : '' ?>">✅ Review Scores</a>

    <h3>USERS</h3>
    <a href="/testmate/admin/users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">👥 Users</a>
    <a href="/testmate/admin/add-user.php" class="<?= $current_page === 'add-user.php' ? 'active' : '' ?>">➕ Add User</a>

    <h3>STATISTICS</h3>
    <a href="/testmate/admin/stats.php" class="<?= $current_page === 'stats.php' ? 'active' : '' ?>">📈 Statistics</a>

    <h3>QUESTIONS</h3>
    <a href="/testmate/admin/questions.php" class="<?= $current_page === 'questions.php' ? 'active' : '' ?>">❓ All Questions</a>
    <a href="/testmate/admin/add-question.php" class="<?= $current_page === 'add-question.php' ? 'active' : '' ?>">➕ Add Question</a>
    <a href="/testmate/admin/assign-quiz.php" class="<?= $current_page === 'assign-quiz.php' ? 'active' : '' ?>">🎯 Assign Quiz</a>
    <a href="/testmate/admin/dragdrop_pairs.php" class="<?= $current_page === 'dragdrop_pairs.php' ? 'active' : '' ?>">🧩 Drag & Drop</a>

    <h3>CONTENT</h3
    <a href="/testmate/admin/simulations.php" class="<?= $current_page === 'simulations.php' ? 'active' : '' ?>">🎬 Simulations</a>
    <a href="/testmate/admin/activities.php" class="<?= $current_page === 'activities.php' ? 'active' : '' ?>">📋 Activities</a>
    <a href="/testmate/admin/materials.php" class="<?= $current_page === 'materials.php' ? 'active' : '' ?>">📚 Materials</a>
    <a href="/testmate/admin/add-material.php" class="<?= $current_page === 'add-material.php' ? 'active' : '' ?>">➕ Add Material</a>
</div>