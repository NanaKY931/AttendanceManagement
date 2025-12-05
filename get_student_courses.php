<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'student') !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: students only']);
    exit;
}

$student_id = intval($_SESSION['user_id']);

$sql = "SELECT c.id AS course_id, c.course_code, c.title, c.term, u.fullname AS faculty_name, e.enrolled_at
        FROM course_enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN users u ON c.faculty_id = u.id
        WHERE e.student_id = ?
        ORDER BY c.course_code ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $student_id);
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