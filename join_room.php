<?php
require_once 'config/db.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole('student')) {
    redirect('login.php', 'Please login as a student to access this page.', 'error');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_code = trim(strtoupper($_POST['room_code']));
    
    if (empty($room_code)) {
        $error = 'Room code is required';
    } else {
        // Find room by code
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = ?");
        $stmt->execute([$room_code]);
        $room = $stmt->fetch();
        
        if (!$room) {
            $error = 'Invalid room code';
        } elseif ($room['status'] === 'finished') {
            $error = 'This quiz has already finished';
        } else {
            // Check if student is already in room
            $stmt = $pdo->prepare("SELECT id FROM room_students WHERE room_id = ? AND student_id = ?");
            $stmt->execute([$room['id'], $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                redirect('quiz.php?room=' . $room['id'], 'You are already in this room!', 'info');
            } else {
                // Check room capacity
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_students WHERE room_id = ?");
                $stmt->execute([$room['id']]);
                $student_count = $stmt->fetch()['count'];
                
                if ($student_count >= $room['max_students']) {
                    $error = 'Room is full (maximum ' . $room['max_students'] . ' students)';
                } else {
                    // Join room
                    $stmt = $pdo->prepare("INSERT INTO room_students (room_id, student_id) VALUES (?, ?)");
                    if ($stmt->execute([$room['id'], $_SESSION['user_id']])) {
                        redirect('quiz.php?room=' . $room['id'], 'Successfully joined room: ' . $room['title'], 'success');
                    } else {
                        $error = 'Failed to join room. Please try again.';
                    }
                }
            }
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Room - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Join Quiz Room</h1>
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

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="join-room-container">
                <div class="join-room-card">
                    <h2>Enter Room Code</h2>
                    <p>Ask your teacher for the room code to join the quiz</p>
                    
                    <form method="POST" class="join-room-form">
                        <div class="room-code-input">
                            <input 
                                type="text" 
                                name="room_code" 
                                placeholder="Enter room code" 
                                maxlength="10"
                                value="<?= htmlspecialchars($_POST['room_code'] ?? '') ?>"
                                style="font-size: 2rem; text-align: center; text-transform: uppercase;"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg">Join Room</button>
                    </form>
                </div>
            </div>

            <div class="my-rooms-section">
                <h3>My Active Rooms</h3>
                <?php
                // Get rooms student has joined
                $stmt = $pdo->prepare("
                    SELECT r.*, rs.joined_at,
                           (SELECT COUNT(*) FROM room_students WHERE room_id = r.id) as student_count
                    FROM rooms r 
                    JOIN room_students rs ON r.id = rs.room_id 
                    WHERE rs.student_id = ? AND r.status != 'finished'
                    ORDER BY rs.joined_at DESC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $my_rooms = $stmt->fetchAll();
                ?>

                <?php if (empty($my_rooms)): ?>
                    <p class="text-muted">You haven't joined any rooms yet.</p>
                <?php else: ?>
                    <div class="rooms-list">
                        <?php foreach ($my_rooms as $room): ?>
                            <div class="room-item">
                                <div class="room-info">
                                    <h4><?= htmlspecialchars($room['title']) ?></h4>
                                    <div class="room-meta">
                                        <span class="room-code">Code: <?= $room['room_code'] ?></span>
                                        <span class="status-badge status-<?= $room['status'] ?>">
                                            <?= ucfirst($room['status']) ?>
                                        </span>
                                        <span>👥 <?= $room['student_count'] ?> students</span>
                                    </div>
                                </div>
                                <div class="room-actions">
                                    <a href="quiz.php?room=<?= $room['id'] ?>" class="btn btn-sm btn-primary">
                                        <?= $room['status'] === 'live' ? 'Join Quiz' : 'View Room' ?>
                                    </a>
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