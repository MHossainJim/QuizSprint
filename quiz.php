<?php
require_once 'config/db.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole('student')) {
    redirect('login.php', 'Please login as a student to access this page.', 'error');
}

// Auto-update room statuses
updateRoomStatuses($pdo);

// Get room ID
$room_id = $_GET['room'] ?? null;
if (!$room_id) {
    redirect('join_room.php', 'Room not specified.', 'error');
}

// Check if student is in this room
$stmt = $pdo->prepare("
    SELECT r.*, rs.id as student_room_id,
           TIMESTAMPDIFF(SECOND, r.start_time, NOW()) as elapsed_seconds
    FROM rooms r 
    LEFT JOIN room_students rs ON r.id = rs.room_id AND rs.student_id = ?
    WHERE r.id = ?
");
$stmt->execute([$_SESSION['user_id'], $room_id]);
$room = $stmt->fetch();

if (!$room) {
    redirect('join_room.php', 'Room not found.', 'error');
}

if (!$room['student_room_id']) {
    redirect('join_room.php', 'You are not a member of this room.', 'error');
}

// Get student's progress
$stmt = $pdo->prepare("SELECT COUNT(*) as answered FROM answers WHERE room_id = ? AND student_id = ?");
$stmt->execute([$room_id, $_SESSION['user_id']]);
$progress = $stmt->fetch()['answered'];

// Get total questions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE room_id = ?");
$stmt->execute([$room_id]);
$total_questions = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?= htmlspecialchars($room['title']) ?> - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="quiz-page">
    <div class="quiz-container" id="quizContainer">
        <header class="quiz-header-modern">
            <div class="quiz-header-left">
                <div class="quiz-title-group">
                    <h1 class="quiz-title"><?= htmlspecialchars($room['title']) ?></h1>
                    <span class="quiz-code-badge">Room: <?= $room['room_code'] ?></span>
                </div>
            </div>
            <div class="quiz-header-right">
                <div class="quiz-stats-inline">
                    <div class="stat-inline">
                        <span class="stat-icon">📝</span>
                        <span class="stat-value" id="progress"><?= $progress ?></span>/<span><?= $total_questions ?></span>
                    </div>
                    <div class="stat-inline timer-stat">
                        <span class="stat-icon">⏱️</span>
                        <span class="stat-value" id="timer">--:--</span>
                    </div>
                </div>
                <span class="status-badge status-<?= $room['status'] ?>" id="quizStatus">
                    <?= ucfirst($room['status']) ?>
                </span>
            </div>
        </header>

        <main class="quiz-main-modern">
            <?php if ($room['status'] === 'waiting'): ?>
                <div class="waiting-screen-modern">
                    <div class="waiting-animation">
                        <div class="pulse-ring"></div>
                        <div class="pulse-icon">⏳</div>
                    </div>
                    <h2 class="waiting-title">Waiting for Quiz to Start</h2>
                    <p class="waiting-subtitle">Your teacher will start the quiz soon. Stay on this page!</p>
                    <div class="waiting-info-grid">
                        <div class="info-card">
                            <div class="info-icon">📋</div>
                            <div class="info-value"><?= $total_questions ?></div>
                            <div class="info-label">Questions</div>
                        </div>
                        <div class="info-card">
                            <div class="info-icon">⏱️</div>
                            <div class="info-value"><?= gmdate('i:s', $room['duration_seconds']) ?></div>
                            <div class="info-label">Duration</div>
                        </div>
                    </div>
                </div>
            <?php elseif ($room['status'] === 'finished'): ?>
                <div class="finished-screen-modern">
                    <div class="finished-icon">🎉</div>
                    <h2 class="finished-title">Quiz Completed!</h2>
                    <p class="finished-subtitle">Thanks for participating! Check the leaderboard below to see your ranking.</p>
                </div>
            <?php else: ?>
                <!-- Live Quiz Screen -->
                <div class="quiz-screen" id="quizScreen">
                    <div class="question-container" id="questionContainer">
                        <!-- Questions loaded via JavaScript -->
                        <div class="loading-spinner">Loading question...</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Leaderboard -->
            <div class="leaderboard-section">
                <h3>🏆 Live Leaderboard</h3>
                <div class="leaderboard" id="leaderboard">
                    <div class="loading">Loading leaderboard...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Pass room data to JavaScript
        window.quizData = {
            roomId: <?= $room_id ?>,
            status: '<?= $room['status'] ?>',
            elapsed: <?= $room['elapsed_seconds'] ?? 0 ?>,
            duration: <?= $room['duration_seconds'] ?>,
            currentQuestion: <?= $progress + 1 ?>,
            totalQuestions: <?= $total_questions ?>
        };
    </script>
    <script src="assets/app.js"></script>
</body>
</html>