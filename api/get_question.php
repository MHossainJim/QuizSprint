<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('student')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    echo json_encode(['success' => false, 'message' => 'Room ID required']);
    exit;
}

try {
    // Check if student is in this room
    $stmt = $pdo->prepare("
        SELECT r.*, rs.id as student_room_id 
        FROM rooms r 
        LEFT JOIN room_students rs ON r.id = rs.room_id AND rs.student_id = ?
        WHERE r.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $room_id]);
    $room = $stmt->fetch();
    
    if (!$room || !$room['student_room_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    if ($room['status'] !== 'live') {
        echo json_encode(['success' => false, 'message' => 'Quiz not live', 'status' => $room['status']]);
        exit;
    }
    
    // Get student's answered questions
    $stmt = $pdo->prepare("SELECT question_id FROM answers WHERE room_id = ? AND student_id = ?");
    $stmt->execute([$room_id, $_SESSION['user_id']]);
    $answered_questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get next unanswered question
    $stmt = $pdo->prepare("
        SELECT * FROM questions 
        WHERE room_id = ? AND id NOT IN (" . str_repeat('?,', count($answered_questions)) . "0) 
        ORDER BY question_order 
        LIMIT 1
    ");
    $stmt->execute(array_merge([$room_id], $answered_questions));
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'No more questions']);
        exit;
    }
    
    // Check if quiz time is up
    $start_time = strtotime($room['start_time']);
    $current_time = time();
    $elapsed = $current_time - $start_time;
    
    if ($elapsed >= $room['duration_seconds']) {
        echo json_encode(['success' => false, 'message' => 'Quiz time is up']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'question' => [
            'id' => $question['id'],
            'text' => $question['question_text'],
            'options' => [
                1 => $question['option1'],
                2 => $question['option2'],
                3 => $question['option3'],
                4 => $question['option4']
            ]
        ],
        'progress' => count($answered_questions) + 1,
        'time_remaining' => max(0, $room['duration_seconds'] - $elapsed)
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>