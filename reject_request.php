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
$verify = $conn->prepare('SELECT 1 FROM join_requests WHERE id = ? AND faculty_id = ? AND status = "pending" LIMIT 1');
$verify->bind_param('ii', $request_id, $faculty_id);
$verify->execute();
$vr = $verify->get_result();
if (!$vr || $vr->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Request not found or not permitted']);
    $verify->close();
    exit;
}
$verify->close();

$update = $conn->prepare('UPDATE join_requests SET status = "rejected" WHERE id = ?');
$update->bind_param('i', $request_id);
$update->execute();
$update->close();

echo json_encode(['success' => true, 'message' => 'Request rejected']);

?>