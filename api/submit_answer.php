<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn() || !hasRole('student')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$room_id = $input['room_id'] ?? null;
$question_id = $input['question_id'] ?? null;
$selected_option = $input['selected_option'] ?? null;

if (!$room_id || !$question_id || !$selected_option) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($selected_option, [1, 2, 3, 4])) {
    echo json_encode(['success' => false, 'message' => 'Invalid option']);
    exit;
}

try {
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
    
    if (!$room || !$room['student_room_id']) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    if ($room['status'] !== 'live') {
        echo json_encode(['success' => false, 'message' => 'Quiz not live']);
        exit;
    }
    
    // Get question details
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ? AND room_id = ?");
    $stmt->execute([$question_id, $room_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question not found']);
        exit;
    }
    
    // Check if already answered
    $stmt = $pdo->prepare("SELECT id FROM answers WHERE room_id = ? AND question_id = ? AND student_id = ?");
    $stmt->execute([$room_id, $question_id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already answered this question']);
        exit;
    }
    
    // Check if quiz time is up
    if ($room['elapsed_seconds'] >= $room['duration_seconds']) {
        echo json_encode(['success' => false, 'message' => 'Quiz time is up']);
        exit;
    }
    
    // Save answer
    $is_correct = ($selected_option == $question['correct_option']);
    
    $stmt = $pdo->prepare("
        INSERT INTO answers (room_id, question_id, student_id, selected_option, is_correct) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$room_id, $question_id, $_SESSION['user_id'], $selected_option, $is_correct])) {
        echo json_encode([
            'success' => true,
            'correct' => $is_correct,
            'correct_option' => (int)$question['correct_option'],
            'selected_option' => (int)$selected_option,
            'message' => $is_correct ? 'Correct!' : 'Incorrect'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save answer']);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>