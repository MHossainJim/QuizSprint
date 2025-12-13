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
$room_code = trim(strtoupper($input['room_code'] ?? ''));

if (empty($room_code)) {
    echo json_encode(['success' => false, 'message' => 'Room code is required']);
    exit;
}

try {
    // Find room by code
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_code = ?");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Invalid room code']);
        exit;
    }
    
    if ($room['status'] === 'finished') {
        echo json_encode(['success' => false, 'message' => 'This quiz has already finished']);
        exit;
    }
    
    // Check if student is already in room
    $stmt = $pdo->prepare("SELECT id FROM room_students WHERE room_id = ? AND student_id = ?");
    $stmt->execute([$room['id'], $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already in room', 'room_id' => $room['id']]);
        exit;
    }
    
    // Check room capacity
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM room_students WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $student_count = $stmt->fetch()['count'];
    
    if ($student_count >= $room['max_students']) {
        echo json_encode(['success' => false, 'message' => 'Room is full']);
        exit;
    }
    
    // Join room
    $stmt = $pdo->prepare("INSERT INTO room_students (room_id, student_id) VALUES (?, ?)");
    if ($stmt->execute([$room['id'], $_SESSION['user_id']])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully joined room: ' . $room['title'],
            'room_id' => $room['id']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to join room']);
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>