<?php
require_once 'config/db.php';

// Check if user is logged in and is a teacher
if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('login.php', 'Please login as a teacher to perform this action.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = $_POST['room_id'] ?? null;
    
    if (!$room_id) {
        redirect('teacher_dashboard.php', 'Invalid room ID.', 'error');
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$room_id, $_SESSION['user_id']]);
    $room = $stmt->fetch();
    
    if (!$room) {
        redirect('teacher_dashboard.php', 'Room not found or access denied.', 'error');
    }
    
    // Delete room (cascade delete should handle related data if set up, otherwise we might need manual cleanup)
    // Assuming foreign keys are set to CASCADE or we rely on simple deletion for now.
    // If not, we should delete questions and room_students first.
    
    try {
        $pdo->beginTransaction();
        
        // Delete questions
        $stmt = $pdo->prepare("DELETE FROM questions WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Delete room_students
        $stmt = $pdo->prepare("DELETE FROM room_students WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Delete room
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$room_id]);
        
        $pdo->commit();
        redirect('teacher_dashboard.php', 'Room deleted successfully.', 'success');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('teacher_dashboard.php', 'Failed to delete room: ' . $e->getMessage(), 'error');
    }
} else {
    redirect('teacher_dashboard.php');
}
