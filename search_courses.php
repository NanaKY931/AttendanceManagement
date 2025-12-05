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
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Basic search: search by course_code or title or faculty name
$sql = "SELECT c.id AS course_id, c.course_code, c.title, c.term, u.fullname AS faculty_name,
                jr.status AS request_status
        FROM courses c
        LEFT JOIN users u ON c.faculty_id = u.id
        LEFT JOIN join_requests jr ON jr.course_id = c.id AND jr.student_id = ?
        WHERE (c.course_code LIKE ? OR c.title LIKE ? OR u.fullname LIKE ?)
        ORDER BY c.course_code ASC
        LIMIT 100";

$like = "%" . $q . "%";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('isss', $student_id, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $results = [];
    while ($row = $res->fetch_assoc()) {
        // Check if student already enrolled
        $enrolled_check = $conn->prepare('SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
        $enrolled_check->bind_param('ii', $row['course_id'], $student_id);
        $enrolled_check->execute();
        $er = $enrolled_check->get_result();
        $row['enrolled'] = ($er && $er->num_rows > 0) ? true : false;
        $enrolled_check->close();
        $results[] = $row;
    }
    echo json_encode($results);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query prepare failed']);
}

?>