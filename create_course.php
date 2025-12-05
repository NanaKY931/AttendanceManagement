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
$course_code = isset($_POST['course_code']) ? trim($_POST['course_code']) : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$term = isset($_POST['term']) ? trim($_POST['term']) : '';

if ($course_code === '' || $title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Course code and title required']);
    exit;
}

$insert = $conn->prepare('INSERT INTO courses (course_code, title, term, faculty_id) VALUES (?, ?, ?, ?)');
$insert->bind_param('sssi', $course_code, $title, $term, $faculty_id);
if ($insert->execute()) {
    echo json_encode(['success' => true, 'course_id' => $insert->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create course']);
}
$insert->close();
?>