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

$sql = "SELECT jr.id AS request_id, jr.course_id, jr.student_id, jr.status, jr.created_at,
               c.course_code, c.title AS course_title, u.fullname AS student_name
        FROM join_requests jr
        JOIN courses c ON jr.course_id = c.id
        JOIN users u ON jr.student_id = u.id
        WHERE jr.faculty_id = ? AND jr.status = 'pending'
        ORDER BY jr.created_at ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $faculty_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query prepare failed']);
}
?>