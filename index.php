<?php
require_once 'config/db.php';

// Redirect logged in users
if (isLoggedIn()) {
    $redirect_url = hasRole('teacher') ? 'teacher_dashboard.php' : 'join_room.php';
    redirect($redirect_url);
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizSprint - Live Quiz Platform</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="home-container">
        <div class="hero">
            <div class="hero-content">
                <h1 class="hero-title">QuizSprint</h1>
                <p class="hero-subtitle">Create and participate in live quizzes with real-time leaderboards</p>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                </div>
            </div>
        </div>

        <div class="features">
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">👨‍🏫</div>
                    <h3>For Teachers</h3>
                    <p>Create quiz rooms, add questions, and monitor student performance in real-time</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>For Students</h3>
                    <p>Join rooms with a simple code and compete on live leaderboards</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Real-time</h3>
                    <p>See results instantly with live updates and interactive quiz experience</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
