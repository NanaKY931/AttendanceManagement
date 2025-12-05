<?php
// get_faculty_courses.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Authorization: Must be Faculty or FI
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['faculty', 'fi'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Faculty or FI only']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Select courses where the user is the assigned faculty
$sql = "SELECT id, course_code, title FROM courses WHERE faculty_id = ? ORDER BY course_code ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $courses = [];
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
    echo json_encode($courses);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query prepare failed']);
}
?>