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
                <div class="header-left">
                    <div class="dashboard-logo">
                        <span class="logo-icon">🚀</span>
                        <span class="logo-text">QuizSprint</span>
                    </div>
                    <h1>Teacher Dashboard</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar">👨‍🏫</div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <span class="user-role">Teacher</span>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <span>Logout</span>
                        <span>→</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-icon">🏠</div>
                    <div class="stat-info">
                        <div class="stat-number"><?= count($rooms) ?></div>
                        <div class="stat-label">Total Rooms</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <div class="stat-number"><?= array_sum(array_column($rooms, 'student_count')) ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">❓</div>
                    <div class="stat-info">
                        <div class="stat-number"><?= array_sum(array_column($rooms, 'question_count')) ?></div>
                        <div class="stat-label">Total Questions</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-info">
                        <div class="stat-number"><?= count(array_filter($rooms, fn($r) => $r['status'] === 'live')) ?></div>
                        <div class="stat-label">Live Rooms</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-actions">
                <a href="create_room.php" class="btn btn-primary btn-large">
                    <span class="btn-icon">➕</span>
                    Create New Quiz Room
                </a>
            </div>

            <div class="rooms-section">
                <div class="section-header">
                    <h2>Your Quiz Rooms</h2>
                    <div class="section-filters">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="waiting">Waiting</button>
                        <button class="filter-btn" data-filter="live">Live</button>
                        <button class="filter-btn" data-filter="finished">Finished</button>
                    </div>
                </div>
                
                <?php if (empty($rooms)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h3>No quiz rooms created yet</h3>
                        <p>Create your first quiz room and start engaging with students!</p>
                        <a href="create_room.php" class="btn btn-primary">
                            <span class="btn-icon">➕</span>
                            Create Your First Room
                        </a>
                    </div>
                <?php else: ?>
                    <div class="rooms-grid">
                        <?php foreach ($rooms as $room): ?>
                            <div class="room-card room-status-<?= $room['status'] ?>" data-status="<?= $room['status'] ?>">
                                <div class="room-header">
                                    <h3><?= htmlspecialchars($room['title']) ?></h3>
                                    <div class="room-badges">
                                        <span class="status-badge status-<?= $room['status'] ?>">
                                            <?= $room['status'] === 'waiting' ? '⏳' : ($room['status'] === 'live' ? '🔴' : '✅') ?>
                                            <?= ucfirst($room['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="room-info">
                                    <div class="room-code-display">
                                        <label>Room Code</label>
                                        <div class="code-container">
                                            <span class="code-text"><?= $room['room_code'] ?></span>
                                            <button class="copy-btn" onclick="copyCode('<?= $room['room_code'] ?>')" title="Copy code">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="room-stats">
                                        <div class="stat-item">
                                            <span class="stat-icon">👥</span>
                                            <span class="stat-text"><?= $room['student_count'] ?>/<?= $room['max_students'] ?> students</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-icon">❓</span>
                                            <span class="stat-text"><?= $room['question_count'] ?> questions</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-icon">⏱️</span>
                                            <span class="stat-text"><?= gmdate("i:s", $room['duration_seconds']) ?> duration</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="room-actions">
                                    <a href="manage_room.php?id=<?= $room['id'] ?>" class="btn btn-outline btn-sm">
                                        <span class="btn-icon">⚙️</span>
                                        Manage
                                    </a>
                                    <?php if ($room['status'] === 'waiting' && $room['question_count'] > 0): ?>
                                        <button onclick="startQuiz(<?= $room['id'] ?>)" class="btn btn-success btn-sm">
                                            <span class="btn-icon">▶️</span>
                                            Start Quiz
                                        </button>
                                    <?php elseif ($room['status'] === 'live'): ?>
                                        <a href="manage_room.php?id=<?= $room['id'] ?>" class="btn btn-primary btn-sm">
                                            <span class="btn-icon">📊</span>
                                            View Live
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="room-footer">
                                    <span class="created-date">Created <?= date('M j, Y', strtotime($room['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="assets/app.js"></script>
    <script>
        // Copy room code to clipboard
        function copyCode(code) {
            navigator.clipboard.writeText(code).then(function() {
                // Show success feedback
                const btn = event.target;
                const originalIcon = btn.textContent;
                btn.textContent = '✅';
                btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                
                setTimeout(() => {
                    btn.textContent = originalIcon;
                    btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                }, 2000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
            });
        }

        // Start quiz function
        function startQuiz(roomId) {
            if (confirm('Are you sure you want to start this quiz? Students will be able to begin answering questions.')) {
                // You can add AJAX call here to start the quiz
                window.location.href = 'manage_room.php?id=' + roomId + '&action=start';
            }
        }

        // Filter rooms by status
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const roomCards = document.querySelectorAll('.room-card');

            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterBtns.forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    roomCards.forEach(card => {
                        if (filter === 'all') {
                            card.style.display = 'block';
                        } else {
                            const status = card.getAttribute('data-status');
                            card.style.display = status === filter ? 'block' : 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>