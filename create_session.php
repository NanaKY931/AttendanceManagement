<?php
// create_session.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // Ensures DB connection is established

if (session_status() === PHP_SESSION_NONE) session_start();

// Authorization: Must be Faculty or FI
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['faculty', 'fi'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Faculty or FI only']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 10; // Default 10 min

if ($course_id <= 0 || $duration <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid course or duration parameter.']);
    exit;
}

// 1. Generate a secure, easy-to-read, 6-character attendance code
$code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

// 2. Insert the new session into the 'sessions' table
// We assume the 'sessions' table has columns: course_id, session_datetime, attendance_code, duration_minutes, is_active, created_by_user_id
$insert_sql = "INSERT INTO `sessions`(course_id, session_datetime, attendance_code, duration_minutes, is_active, created_by_user_id) 
             VALUES (?, NOW(), ?, ?, 1, ?)";

if ($stmt = $conn->prepare($insert_sql)) {
    $stmt->bind_param('isii', $course_id, $code, $duration, $user_id);
    
    if ($stmt->execute()) {
        $session_id = $stmt->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Session created successfully.',
            'session_id' => $session_id,
            'code' => $code,
            'duration' => $duration,
            'course_id' => $course_id // Used by JS for displaying the course code
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database insertion failed: ' . $conn->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query preparation failed.']);
}
?>