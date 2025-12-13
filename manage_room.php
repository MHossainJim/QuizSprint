<?php
require_once 'config/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('login.php', 'Please login as a teacher to access this page.', 'error');
}

// Auto-update room statuses
updateRoomStatuses($pdo);

$room_id = $_GET['id'] ?? null;
if (!$room_id) {
    redirect('teacher_dashboard.php', 'Room not specified.', 'error');
}

// Get room details
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND teacher_id = ?");
$stmt->execute([$room_id, $_SESSION['user_id']]);
$room = $stmt->fetch();

if (!$room) {
    redirect('teacher_dashboard.php', 'Room not found or access denied.', 'error');
}

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add_question') {
        $question_text = trim($_POST['question_text']);
        $option1 = trim($_POST['option1']);
        $option2 = trim($_POST['option2']);
        $option3 = trim($_POST['option3']);
        $option4 = trim($_POST['option4']);
        $correct_option = (int)$_POST['correct_option'];
        
        if (empty($question_text) || empty($option1) || empty($option2) || empty($option3) || empty($option4)) {
            $error = 'All question fields are required';
        } elseif (!in_array($correct_option, [1, 2, 3, 4])) {
            $error = 'Please select a correct option';
        } else {
            // Get next question order
            $stmt = $pdo->prepare("SELECT MAX(question_order) as max_order FROM questions WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $max_order = $stmt->fetch()['max_order'] ?? 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO questions (room_id, question_text, option1, option2, option3, option4, correct_option, question_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$room_id, $question_text, $option1, $option2, $option3, $option4, $correct_option, $max_order + 1])) {
                $success = 'Question added successfully!';
            } else {
                $error = 'Failed to add question. Please try again.';
            }
        }
    } elseif ($action === 'start_quiz' && $room['status'] === 'waiting') {
        // Check if there are questions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $question_count = $stmt->fetch()['count'];
        
        if ($question_count === 0) {
            $error = 'Cannot start quiz without questions';
        } else {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'live', start_time = NOW() WHERE id = ?");
            if ($stmt->execute([$room_id])) {
                redirect('manage_room.php?id=' . $room_id, 'Quiz started successfully!', 'success');
            } else {
                $error = 'Failed to start quiz';
            }
        }
    } elseif ($action === 'stop_quiz' && $room['status'] === 'live') {
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'finished' WHERE id = ?");
        if ($stmt->execute([$room_id])) {
            redirect('manage_room.php?id=' . $room_id, 'Quiz stopped successfully!', 'success');
        } else {
            $error = 'Failed to stop quiz';
        }
    } elseif ($action === 'delete_room') {
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
        if ($stmt->execute([$room_id])) {
            redirect('teacher_dashboard.php', 'Room deleted successfully!', 'success');
        } else {
            $error = 'Failed to delete room';
        }
    }
}

// Get room statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM room_students WHERE room_id = ?) as student_count,
        (SELECT COUNT(*) FROM questions WHERE room_id = ?) as question_count
");
$stmt->execute([$room_id, $room_id]);
$stats = $stmt->fetch();

// Get questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE room_id = ? ORDER BY question_order");
$stmt->execute([$room_id]);
$questions = $stmt->fetchAll();

// Get students in room
$stmt = $pdo->prepare("
    SELECT u.name, rs.joined_at,
           COALESCE(student_scores.score, 0) as score,
           COALESCE(student_scores.answered, 0) as answered
    FROM room_students rs 
    JOIN users u ON rs.student_id = u.id 
    LEFT JOIN (
        SELECT student_id, 
               SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as score,
               COUNT(*) as answered
        FROM answers 
        WHERE room_id = ? 
        GROUP BY student_id
    ) student_scores ON rs.student_id = student_scores.student_id
    WHERE rs.room_id = ? 
    ORDER BY score DESC, rs.joined_at ASC
");
$stmt->execute([$room_id, $room_id]);
$students = $stmt->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Room: <?= htmlspecialchars($room['title']) ?> - QuizSprint</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1>Manage Room</h1>
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

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Room Info -->
            <div class="room-info-card">
                <div class="room-header">
                    <h2><?= htmlspecialchars($room['title']) ?></h2>
                    <span class="status-badge status-<?= $room['status'] ?>">
                        <?= ucfirst($room['status']) ?>
                    </span>
                </div>
                
                <div class="room-details">
                    <div class="room-code-display">
                        <label>Room Code:</label>
                        <span class="room-code-large"><?= $room['room_code'] ?></span>
                    </div>
                    
                    <div class="room-stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?= $stats['student_count'] ?>/<?= $room['max_students'] ?></span>
                            <span class="stat-label">Students</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $stats['question_count'] ?></span>
                            <span class="stat-label">Questions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $room['duration_seconds'] ?>s</span>
                            <span class="stat-label">Duration</span>
                        </div>
                    </div>
                </div>

                <?php if ($room['status'] === 'waiting' && $stats['question_count'] > 0): ?>
                    <form method="POST" class="start-quiz-form">
                        <input type="hidden" name="action" value="start_quiz">
                        <button type="submit" class="btn btn-success btn-lg">Start Quiz</button>
                    </form>
                <?php elseif ($room['status'] === 'live'): ?>
                    <form method="POST" class="start-quiz-form" onsubmit="return confirm('Are you sure you want to stop the quiz?');">
                        <input type="hidden" name="action" value="stop_quiz">
                        <button type="submit" class="btn btn-secondary btn-lg" style="background: #dc3545; border: none;">Stop Quiz</button>
                    </form>
                <?php endif; ?>

                <div class="room-actions-footer" style="margin-top: 20px; text-align: center;">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this room? This action cannot be undone.');">
                        <input type="hidden" name="action" value="delete_room">
                        <button type="submit" class="btn btn-outline btn-sm" style="color: #dc3545; border-color: #dc3545;">Delete Room</button>
                    </form>
                </div>
            </div>

            <div class="manage-sections">
                <!-- Add Question Section -->
                <?php if ($room['status'] === 'waiting'): ?>
                    <div class="section">
                        <h3>Add Question</h3>
                        <form method="POST" class="question-form">
                            <input type="hidden" name="action" value="add_question">
                            
                            <div class="form-group">
                                <label for="question_text">Question</label>
                                <textarea 
                                    id="question_text" 
                                    name="question_text" 
                                    placeholder="Enter your question here..."
                                    required
                                ><?= htmlspecialchars($_POST['question_text'] ?? '') ?></textarea>
                            </div>

                            <div class="options-grid">
                                <div class="form-group">
                                    <label for="option1">Option 1</label>
                                    <input type="text" id="option1" name="option1" value="<?= htmlspecialchars($_POST['option1'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="option2">Option 2</label>
                                    <input type="text" id="option2" name="option2" value="<?= htmlspecialchars($_POST['option2'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="option3">Option 3</label>
                                    <input type="text" id="option3" name="option3" value="<?= htmlspecialchars($_POST['option3'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="option4">Option 4</label>
                                    <input type="text" id="option4" name="option4" value="<?= htmlspecialchars($_POST['option4'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="correct_option">Correct Option</label>
                                <select id="correct_option" name="correct_option" required>
                                    <option value="">Select correct option</option>
                                    <option value="1" <?= ($_POST['correct_option'] ?? '') === '1' ? 'selected' : '' ?>>Option 1</option>
                                    <option value="2" <?= ($_POST['correct_option'] ?? '') === '2' ? 'selected' : '' ?>>Option 2</option>
                                    <option value="3" <?= ($_POST['correct_option'] ?? '') === '3' ? 'selected' : '' ?>>Option 3</option>
                                    <option value="4" <?= ($_POST['correct_option'] ?? '') === '4' ? 'selected' : '' ?>>Option 4</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Question</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Questions List -->
                <div class="section">
                    <h3>Questions (<?= count($questions) ?>)</h3>
                    <?php if (empty($questions)): ?>
                        <p class="text-muted">No questions added yet.</p>
                    <?php else: ?>
                        <div class="questions-list">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-item">
                                    <div class="question-number"><?= $index + 1 ?></div>
                                    <div class="question-content">
                                        <div class="question-text">
                                            <?= htmlspecialchars($question['question_text']) ?>
                                        </div>
                                        <div class="question-options">
                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                                <span class="option <?= $i == $question['correct_option'] ? 'correct' : '' ?>">
                                                    <?= chr(64 + $i) ?>. <?= htmlspecialchars($question['option' . $i]) ?>
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Students List -->
                <div class="section">
                    <h3>Students (<?= count($students) ?>)</h3>
                    <?php if (empty($students)): ?>
                        <p class="text-muted">No students joined yet.</p>
                    <?php else: ?>
                        <div class="students-list">
                            <?php foreach ($students as $index => $student): ?>
                                <div class="student-item">
                                    <div class="student-rank">#<?= $index + 1 ?></div>
                                    <div class="student-info">
                                        <div class="student-name"><?= htmlspecialchars($student['name']) ?></div>
                                        <div class="student-stats">
                                            Score: <?= $student['score'] ?> | 
                                            Answered: <?= $student['answered'] ?>/<?= count($questions) ?> |
                                            Joined: <?= date('H:i', strtotime($student['joined_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="student-score">
                                        <?= $student['score'] ?> points
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>