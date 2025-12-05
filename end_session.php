<?php
// end_session.php
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
$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? intval($input['session_id']) : 0;

if ($session_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid session ID provided.']);
    exit;
}

// 1. Update the session status: Set is_active = 0 for the specified session ID,
//    but only if the current logged-in user created it and it is currently active (is_active = 1).
$update_sql = "UPDATE `sessions` SET is_active = 0
                WHERE id = ? AND created_by_user_id = ? AND is_active = 1";

if ($stmt = $conn->prepare($update_sql)) {
    $stmt->bind_param('ii', $session_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Session deactivated successfully.']);
        } else {
            // This happens if the session was not found, was already ended, or the user didn't create it.
            echo json_encode(['success' => false, 'error' => 'Session not found or not active/authorized to end.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database update failed.']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query preparation failed.']);
}
?>