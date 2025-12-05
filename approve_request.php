<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: faculty only']);
    exit;
}

$faculty_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);
$request_id = isset($input['request_id']) ? intval($input['request_id']) : 0;

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request id']);
    exit;
}

// Verify request belongs to this faculty and is pending
$verify = $conn->prepare('SELECT course_id, student_id FROM join_requests WHERE id = ? AND faculty_id = ? AND status = "pending" LIMIT 1');
$verify->bind_param('ii', $request_id, $faculty_id);
$verify->execute();
$vr = $verify->get_result();
if (!$vr || $vr->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Request not found or not permitted']);
    $verify->close();
    exit;
}
$row = $vr->fetch_assoc();
$course_id = intval($row['course_id']);
$student_id = intval($row['student_id']);
$verify->close();

// Add enrollment (ignore duplicate using INSERT IGNORE)
$enroll = $conn->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
$enroll->bind_param('ii', $course_id, $student_id);
$enroll->execute();
$enroll->close();

// Update request status
$update = $conn->prepare('UPDATE join_requests SET status = "approved" WHERE id = ?');
$update->bind_param('i', $request_id);
$update->execute();
$update->close();

echo json_encode(['success' => true, 'message' => 'Request approved and student enrolled']);

?>