<?php
// check_session.php - Check if user is already logged in

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Set proper content type
header('Content-Type: application/json; charset=utf-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize response
$response = array(
    'logged_in' => false,
    'username' => null,
    'user_id' => null,
    'role' => null,
    'dashboard' => null
);

// Check if user session exists and is valid
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Verify session hasn't expired (optional: set a timeout)
    $session_timeout = 3600; // 1 hour in seconds
    $current_time = time();
    $login_time = isset($_SESSION['login_time']) ? $_SESSION['login_time'] : $current_time;
    
    // If session is still valid
    if (($current_time - $login_time) < $session_timeout) {
        $response['logged_in'] = true;
        $response['username'] = $_SESSION['username'];
        $response['user_id'] = $_SESSION['user_id'];
        $response['role'] = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';
        
        // Determine dashboard based on role
        $role = $response['role'];
        if ($role === 'faculty') {
            $response['dashboard'] = 'F DASHBOARD.html';
        } elseif ($role === 'fi') {
            $response['dashboard'] = 'FI DASHBOARD.html';
        } else {
            $response['dashboard'] = 'S DASHBOARD.html';
        }
    } else {
        // Session has expired, clear it
        session_destroy();
        $response['logged_in'] = false;
    }
}

// Return JSON response
http_response_code(200);
echo json_encode($response);
?>
