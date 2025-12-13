<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
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
    // Check if user has access to this room
    if (hasRole('teacher')) {
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$room_id, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT r.* FROM rooms r 
            JOIN room_students rs ON r.id = rs.room_id 
            WHERE r.id = ? AND rs.student_id = ?
        ");
        $stmt->execute([$room_id, $_SESSION['user_id']]);
    }
    
    $room = $stmt->fetch();
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    $response = [
        'success' => true,
        'room_id' => (int)$room['id'],
        'status' => $room['status'],
        'title' => $room['title'],
        'room_code' => $room['room_code']
    ];
    
    // Add time information for live quizzes
    if ($room['status'] === 'live' && $room['start_time']) {
        $start_time = strtotime($room['start_time']);
        $current_time = time();
        $elapsed = $current_time - $start_time;
        $time_remaining = max(0, $room['duration_seconds'] - $elapsed);
        
        $response['start_time'] = $start_time * 1000; // JavaScript timestamp
        $response['duration'] = (int)$room['duration_seconds'];
        $response['elapsed'] = (int)$elapsed;
        $response['time_remaining'] = (int)$time_remaining;
        
        // Check if quiz should be finished due to time
        if ($time_remaining <= 0) {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'finished' WHERE id = ?");
            $stmt->execute([$room_id]);
            $response['status'] = 'finished';
        }
    }
    
    // Add student-specific information
    if (hasRole('student')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as answered FROM answers WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$room_id, $_SESSION['user_id']]);
        $answered_count = $stmt->fetch()['answered'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $total_questions = $stmt->fetch()['total'];
        
        $response['progress'] = [
            'answered' => (int)$answered_count,
            'total' => (int)$total_questions,
            'completed' => $answered_count >= $total_questions
        ];
    }
    
    // Add teacher-specific information
    if (hasRole('teacher')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_students WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $student_count = $stmt->fetch()['count'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $question_count = $stmt->fetch()['count'];
        
        $response['stats'] = [
            'students' => (int)$student_count,
            'questions' => (int)$question_count,
            'max_students' => (int)$room['max_students']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>