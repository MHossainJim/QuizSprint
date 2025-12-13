<?php
require_once 'config/db.php';

// Check if database is ready and redirect logged in users
try {
    $pdo->query("SELECT 1 FROM users LIMIT 1");
    $database_ready = true;
    if (isLoggedIn()) {
        $redirect_url = hasRole('teacher') ? 'teacher_dashboard.php' : 'join_room.php';
        redirect($redirect_url);
    }
    $flash = getFlashMessage();
} catch (PDOException $e) {
    $database_ready = false;
    $flash = null;
}
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
    <?php if (!$database_ready): ?>
        <div class="setup-warning">
            <div class="setup-warning-content">
                <div class="warning-icon">⚠️</div>
                <h3>Database Setup Required</h3>
                <p>Please import the database.sql file into phpMyAdmin and refresh this page to get started.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="home-container">
        <!-- Navigation Header -->
        <nav class="home-nav">
            <div class="nav-content">
                <div class="logo">
                    <span class="logo-icon">🚀</span>
                    <span class="logo-text">QuizSprint</span>
                </div>
                <div class="nav-actions">
                    <?php if ($database_ready): ?>
                        <a href="login.php" class="nav-link">Login</a>
                        <a href="register.php" class="btn btn-primary">Get Started</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="hero">
            <div class="hero-content">
                <div class="hero-badge">✨ Live Quiz Platform</div>
                <h1 class="hero-title">Transform Learning with <span class="text-gradient">QuizSprint</span></h1>
                <p class="hero-subtitle">Create engaging quiz experiences with real-time participation and instant leaderboards</p>
                
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-number">40+</div>
                        <div class="stat-label">Students per room</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">⚡</div>
                        <div class="stat-label">Real-time updates</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number">📊</div>
                        <div class="stat-label">Live leaderboards</div>
                    </div>
                </div>

                <div class="hero-buttons">
                    <?php if ($database_ready): ?>
                        <a href="register.php" class="btn btn-primary btn-large">Start Teaching Today</a>
                        <a href="login.php" class="btn btn-outline">Join as Student</a>
                    <?php else: ?>
                        <button class="btn btn-primary btn-large" disabled>Setup Required</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <section class="features">
            <div class="features-header">
                <h2>Why Choose QuizSprint?</h2>
                <p>Everything you need for interactive learning experiences</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">👨‍🏫</div>
                    <h3>For Educators</h3>
                    <p>Create engaging quiz rooms, add custom questions, and monitor student performance with detailed analytics</p>
                    <div class="feature-highlights">
                        <span class="highlight">✓ Custom Questions</span>
                        <span class="highlight">✓ Real-time Monitoring</span>
                        <span class="highlight">✓ Performance Analytics</span>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>For Students</h3>
                    <p>Join quiz rooms instantly with simple room codes and compete on live leaderboards with classmates</p>
                    <div class="feature-highlights">
                        <span class="highlight">✓ Easy Join Process</span>
                        <span class="highlight">✓ Live Competition</span>
                        <span class="highlight">✓ Instant Results</span>
                    </div>
                </div>
                <div class="feature-card featured">
                    <div class="feature-icon">⚡</div>
                    <h3>Real-time Experience</h3>
                    <p>Experience lightning-fast updates with live leaderboards and instant question delivery</p>
                    <div class="feature-highlights">
                        <span class="highlight">✓ Live Updates</span>
                        <span class="highlight">✓ Instant Feedback</span>
                        <span class="highlight">✓ Zero Delay</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="section-content">
                <h2>How It Works</h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Create or Join</h3>
                        <p>Teachers create rooms, students join with codes</p>
                    </div>
                    <div class="step-arrow">→</div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Play Live</h3>
                        <p>Answer questions in real-time competition</p>
                    </div>
                    <div class="step-arrow">→</div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>See Results</h3>
                        <p>View instant results and leaderboards</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
