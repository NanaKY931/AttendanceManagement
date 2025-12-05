<?php
// Database Configuration and Connection (db_connect.php)
// Connects to the attendancemanagement database

// Enable error logging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Load environment variables from .env file
$envFile = __DIR__ . '/env/connection.env';
if (file_exists($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
} else {
    error_log('Warning: connection.env file not found at ' . $envFile);
}

// Database configuration
// 1. Connection Parameters
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'attendancemanagement';
$port = 3306;

// Create mysqli connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection error. Please try again later.']));
}

// Create users table if it doesn't exist
$create_users_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('student', 'fi', 'faculty') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    remember_token VARCHAR(64) DEFAULT NULL,
    token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_remember_token (remember_token)
);
";

if (!$conn->query($create_users_table)) {
    error_log("Error creating users table: " . $conn->error);
}

// Create courses table
$create_courses_table = "
CREATE TABLE IF NOT EXISTS courses (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    term VARCHAR(100) DEFAULT NULL,
    faculty_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_course_code (course_code),
    INDEX idx_faculty_id (faculty_id),
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
);
";

if (!$conn->query($create_courses_table)) {
    error_log("Error creating courses table: " . $conn->error);
}

// Create course_enrollments table
$create_enrollments_table = "
CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_enrollment (course_id, student_id)
);
";

if (!$conn->query($create_enrollments_table)) {
    error_log("Error creating course_enrollments table: " . $conn->error);
}

// Create join_requests table
$create_join_requests_table = "
CREATE TABLE IF NOT EXISTS join_requests (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    faculty_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (course_id, student_id)
);
";

if (!$conn->query($create_join_requests_table)) {
    error_log("Error creating join_requests table: " . $conn->error);
}

// Start session for CSRF token management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>