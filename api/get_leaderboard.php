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
    
    // Get leaderboard
    $stmt = $pdo->prepare("
        SELECT 
            u.name,
            u.id as user_id,
            COALESCE(student_scores.score, 0) as score,
            COALESCE(student_scores.answered, 0) as answered,
            COALESCE(student_scores.last_answer_time, rs.joined_at) as last_answer_time
        FROM room_students rs 
        JOIN users u ON rs.student_id = u.id 
        LEFT JOIN (
            SELECT 
                student_id, 
                SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as score,
                COUNT(*) as answered,
                MAX(answered_at) as last_answer_time
            FROM answers 
            WHERE room_id = ? 
            GROUP BY student_id
        ) student_scores ON rs.student_id = student_scores.student_id
        WHERE rs.room_id = ? 
        ORDER BY score DESC, last_answer_time ASC
    ");
    $stmt->execute([$room_id, $room_id]);
    $leaderboard = $stmt->fetchAll();
    
    // Get total questions
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $total_questions = $stmt->fetch()['total'];
    
    // Format leaderboard
    $formatted_leaderboard = [];
    foreach ($leaderboard as $index => $student) {
        $formatted_leaderboard[] = [
            'rank' => $index + 1,
            'name' => $student['name'],
            'score' => (int)$student['score'],
            'answered' => (int)$student['answered'],
            'total_questions' => (int)$total_questions,
            'percentage' => $total_questions > 0 ? round(($student['score'] / $total_questions) * 100, 1) : 0,
            'is_current_user' => $student['user_id'] == $_SESSION['user_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'leaderboard' => $formatted_leaderboard,
        'total_students' => count($leaderboard),
        'room_status' => $room['status']
    ]);
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>