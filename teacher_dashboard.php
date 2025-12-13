<?php
require_once 'config/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('login.php', 'Please login as a teacher to access this page.', 'error');
}

// Get teacher's rooms
$stmt = $pdo->prepare("
    SELECT r.*, 
           COUNT(rs.id) as student_count,
           COUNT(q.id) as question_count
    FROM rooms r 
    LEFT JOIN room_students rs ON r.id = rs.room_id 
    LEFT JOIN questions q ON r.id = q.room_id 
    WHERE r.teacher_id = ? 
    GROUP BY r.id 
    ORDER BY r.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$rooms = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Teacher Dashboard</h1>
                <div class="user-info">
                    Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <div class="dashboard-actions">
                <a href="create_room.php" class="btn btn-primary">
                    ➕ Create New Room
                </a>
            </div>

            <div class="rooms-section">
                <h2>Your Quiz Rooms</h2>
                
                <?php if (empty($rooms)): ?>
                    <div class="empty-state">
                        <h3>No rooms created yet</h3>
                        <p>Create your first quiz room to get started!</p>
                        <a href="create_room.php" class="btn btn-primary">Create Room</a>
                    </div>
                <?php else: ?>
                    <div class="rooms-grid">
                        <?php foreach ($rooms as $room): ?>
                            <div class="room-card room-status-<?= $room['status'] ?>">
                                <div class="room-header">
                                    <h3><?= htmlspecialchars($room['title']) ?></h3>
                                    <span class="status-badge status-<?= $room['status'] ?>">
                                        <?= ucfirst($room['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="room-info">
                                    <div class="room-code">
                                        Code: <strong><?= $room['room_code'] ?></strong>
                                    </div>
                                    <div class="room-stats">
                                        <span>👥 <?= $room['student_count'] ?>/<?= $room['max_students'] ?></span>
                                        <span>❓ <?= $room['question_count'] ?> questions</span>
                                        <span>⏰ <?= $room['duration_seconds'] ?>s</span>
                                    </div>
                                </div>
                                
                                <div class="room-actions">
                                    <a href="manage_room.php?id=<?= $room['id'] ?>" class="btn btn-sm btn-primary">
                                        Manage
                                    </a>
                                    <?php if ($room['status'] === 'waiting' && $room['question_count'] > 0): ?>
                                        <button onclick="startQuiz(<?= $room['id'] ?>)" class="btn btn-sm btn-success">
                                            Start Quiz
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>