<?php
// get_student_attendance.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // Ensures DB connection is established

if (session_status() === PHP_SESSION_NONE) session_start();

// Authorization: Must be Student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'guest') !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Students only.']);
    exit;
}

$student_id = intval($_SESSION['user_id']);
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// --- 1. DETAILED REPORT (Specific Course) ---
if ($course_id !== null && $course_id > 0) {
    // Check if student is enrolled in this course first (optional, but good practice)
    $check_enrollment = $conn->prepare("SELECT 1 FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $check_enrollment->bind_param('ii', $course_id, $student_id);
    $check_enrollment->execute();
    if ($check_enrollment->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not enrolled in this course.']);
        exit;
    }

    // SQL: List ALL sessions for the course and LEFT JOIN the student's attendance record
    // This allows us to see which sessions were MISSED (attendance.id will be NULL)
    $sql = "SELECT
                s.id AS session_id,
                s.session_datetime,
                a.check_in_time,
                CASE WHEN a.id IS NOT NULL THEN 'Present' ELSE 'Absent' END AS status
            FROM
                `sessions` s
            LEFT JOIN
                `attendance` a ON s.id = a.session_id AND a.student_id = ?
            WHERE
                s.course_id = ?
            ORDER BY
                s.session_datetime DESC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $student_id, $course_id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($results);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Detailed query failed.']);
    }

// --- 2. SUMMARY REPORT (All Courses) ---
} else {
    // SQL: For each enrolled course, count total sessions and sessions attended by the student.
    $sql = "SELECT
                c.id AS course_id,
                c.course_code,
                c.title,
                (SELECT COUNT(s.id) FROM `sessions` s WHERE s.course_id = c.id) AS total_sessions,
                (SELECT COUNT(a.id)
                FROM `attendance` a
                JOIN `sessions` s ON a.session_id = s.id
                WHERE a.student_id = ? AND s.course_id = c.id) AS sessions_attended
            FROM
                `course_enrollments` ce
            JOIN
                `courses` c ON ce.course_id = c.id
            WHERE
                ce.student_id = ?
            ORDER BY
                c.course_code ASC";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ii', $student_id, $student_id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate percentage in PHP before sending (easier than complex SQL for presentation)
        $summary = array_map(function($row) {
            $total = intval($row['total_sessions']);
            $attended = intval($row['sessions_attended']);
            $row['attendance_percent'] = $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;
            return $row;
        }, $results);
        
        echo json_encode($summary);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Summary query failed.']);
    }
}
?>