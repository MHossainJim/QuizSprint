<?php
require_once 'config/db.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole('student')) {
    redirect('login.php', 'Please login as a student to access this page.', 'error');
}

// Auto-update room statuses
updateRoomStatuses($pdo);

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
                <div class="header-left">
                    <div class="dashboard-logo">
                        <span class="logo-text">QuizSprint</span>
                    </div>
                    <h1>Student Dashboard</h1>
                </div>
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <span class="user-role">Student</span>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        Logout
                    </a>
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

            <div class="join-room-hero">
                <div class="join-room-container">
                    <div class="join-room-card">
                        <h2>Join a Quiz Room</h2>
                        <p>Enter the room code provided by your teacher to join the quiz</p>
                        
                        <form method="POST" class="join-room-form">
                            <div class="room-code-input">
                                <label>Room Code</label>
                                <input 
                                    type="text" 
                                    name="room_code" 
                                    placeholder="ABCD12" 
                                    maxlength="10"
                                    value="<?= htmlspecialchars($_POST['room_code'] ?? '') ?>"
                                    required
                                    autocomplete="off"
                                >
                            </div>
                            <button type="submit" class="btn btn-primary btn-large">
                                Join Room
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <section class="my-rooms-section">
                <div class="section-header">
                    <h3>My Active Rooms</h3>
                    <div class="refresh-btn" onclick="location.reload()">
                        Refresh
                    </div>
                </div>
                
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
                    <div class="empty-state">
                        <h4>No active rooms</h4>
                        <p>Join your first quiz room using the code above!</p>
                    </div>
                <?php else: ?>
                    <div class="rooms-grid">
                        <?php foreach ($my_rooms as $room): ?>
                            <div class="student-room-card room-status-<?= $room['status'] ?>">
                                <div class="room-header">
                                    <h4><?= htmlspecialchars($room['title']) ?></h4>
                                    <span class="status-badge status-<?= $room['status'] ?>">
                                        <?= ucfirst($room['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="room-info">
                                    <div class="room-meta">
                                        <div class="meta-item">
                                            <span>Code: <?= $room['room_code'] ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span><?= $room['student_count'] ?> students</span>
                                        </div>
                                        <div class="meta-item">
                                            <span>Joined <?= date('M j', strtotime($room['joined_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="room-actions">
                                    <?php if ($room['status'] === 'live'): ?>
                                        <a href="quiz.php?room=<?= $room['id'] ?>" class="btn btn-success btn-sm pulse">
                                            Join Live Quiz
                                        </a>
                                    <?php elseif ($room['status'] === 'waiting'): ?>
                                        <a href="quiz.php?room=<?= $room['id'] ?>" class="btn btn-primary btn-sm">
                                            View Room
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>