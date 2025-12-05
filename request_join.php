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
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

if ($course_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid course id']);
    exit;
}

// Check already enrolled
$check_enrolled = $conn->prepare('SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
$check_enrolled->bind_param('ii', $course_id, $student_id);
$check_enrolled->execute();
$er = $check_enrolled->get_result();
if ($er && $er->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Already enrolled in this course']);
    $check_enrolled->close();
    exit;
}
$check_enrolled->close();

// Get course faculty
$get_course = $conn->prepare('SELECT faculty_id FROM courses WHERE id = ? LIMIT 1');
$get_course->bind_param('i', $course_id);
$get_course->execute();
$cr = $get_course->get_result();
if (!$cr || $cr->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Course not found']);
    $get_course->close();
    exit;
}
$course_row = $cr->fetch_assoc();
$faculty_id = intval($course_row['faculty_id']);
$get_course->close();

// Insert or ignore if already requested
$insert = $conn->prepare('INSERT INTO join_requests (course_id, faculty_id, student_id, status) VALUES (?, ?, ?, "pending")');
$insert->bind_param('iii', $course_id, $faculty_id, $student_id);
if ($insert->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request submitted']);
} else {
    // If duplicate (unique_request) then notify
    if ($conn->errno === 1062) {
        echo json_encode(['success' => false, 'error' => 'You have already requested to join this course']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit request']);
    }
}
$insert->close();

?>