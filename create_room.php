<?php
require_once 'config/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('login.php', 'Please login as a teacher to access this page.', 'error');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $duration_minutes = (int)$_POST['duration_minutes'];
    $max_students = (int)$_POST['max_students'];
    
    if (empty($title)) {
        $error = 'Room title is required';
    } elseif ($duration_minutes < 1 || $duration_minutes > 60) {
        $error = 'Duration must be between 1 and 60 minutes';
    } elseif ($max_students < 1 || $max_students > 40) {
        $error = 'Maximum students must be between 1 and 40';
    } else {
        // Generate unique room code
        do {
            $room_code = generateRoomCode();
            $stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_code = ?");
            $stmt->execute([$room_code]);
        } while ($stmt->fetch());
        
        // Create room
        $stmt = $pdo->prepare("
            INSERT INTO rooms (teacher_id, room_code, title, duration_seconds, max_students) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([
            $_SESSION['user_id'], 
            $room_code, 
            $title, 
            $duration_minutes * 60, 
            $max_students
        ])) {
            $room_id = $pdo->lastInsertId();
            redirect('manage_room.php?id=' . $room_id, 'Room created successfully! Code: ' . $room_code, 'success');
        } else {
            $error = 'Failed to create room. Please try again.';
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
    <title>Create Room - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Create New Room</h1>
                <div class="user-info">
                    Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <div class="page-actions">
                <a href="teacher_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="room-form">
                    <div class="form-group">
                        <label for="title">Room Title</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            placeholder="e.g., Math Quiz - Chapter 5"
                            value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration_minutes">Quiz Duration (minutes)</label>
                            <input 
                                type="number" 
                                id="duration_minutes" 
                                name="duration_minutes" 
                                min="1" 
                                max="60" 
                                value="<?= $_POST['duration_minutes'] ?? '10' ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="max_students">Maximum Students</label>
                            <input 
                                type="number" 
                                id="max_students" 
                                name="max_students" 
                                min="1" 
                                max="40" 
                                value="<?= $_POST['max_students'] ?? '30' ?>"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">Create Room</button>
                </form>

                <div class="create-room-info">
                    <h3>What happens next?</h3>
                    <ol>
                        <li>Your room will be created with a unique code</li>
                        <li>Add questions to your quiz</li>
                        <li>Share the room code with students</li>
                        <li>Start the quiz when ready!</li>
                    </ol>
                </div>
            </div>
        </main>
    </div>
</body>
</html>