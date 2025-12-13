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
    SELECT r.*, rs.id as student_room_id 
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
<body>
    <div class="quiz-container" id="quizContainer">
        <header class="quiz-header">
            <div class="quiz-info">
                <h1><?= htmlspecialchars($room['title']) ?></h1>
                <div class="quiz-meta">
                    <span class="room-code">Room: <?= $room['room_code'] ?></span>
                    <span class="quiz-progress">Progress: <span id="progress"><?= $progress ?></span>/<?= $total_questions ?></span>
                    <span class="quiz-timer" id="timer">--:--</span>
                </div>
            </div>
            <div class="quiz-status">
                <span class="status-badge status-<?= $room['status'] ?>" id="quizStatus">
                    <?= ucfirst($room['status']) ?>
                </span>
                <a href="join_room.php" class="btn btn-sm btn-secondary">Back</a>
            </div>
        </header>

        <main class="quiz-main">
            <?php if ($room['status'] === 'waiting'): ?>
                <div class="waiting-screen">
                    <div class="waiting-content">
                        <h2>Waiting for Quiz to Start</h2>
                        <p>Your teacher will start the quiz soon. Stay on this page!</p>
                        <div class="waiting-stats">
                            <div class="stat">
                                <span class="stat-number"><?= $total_questions ?></span>
                                <span class="stat-label">Questions</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?= $room['duration_seconds'] ?>s</span>
                                <span class="stat-label">Duration</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($room['status'] === 'finished'): ?>
                <div class="finished-screen">
                    <div class="finished-content">
                        <h2>Quiz Completed</h2>
                        <p>Thanks for participating! Check the final leaderboard below.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Live Quiz Screen -->
                <div class="quiz-screen" id="quizScreen">
                    <div class="question-container" id="questionContainer">
                        <!-- Questions loaded via JavaScript -->
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
            startTime: <?= $room['start_time'] ? "new Date('" . $room['start_time'] . "').getTime()" : 'null' ?>,
            duration: <?= $room['duration_seconds'] ?> * 1000,
            currentQuestion: <?= $progress + 1 ?>,
            totalQuestions: <?= $total_questions ?>
        };
    </script>
    <script src="assets/app.js"></script>
</body>
</html>