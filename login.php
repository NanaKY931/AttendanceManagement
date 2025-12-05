<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Set proper content type
header('Content-Type: application/json; charset=utf-8');

// Initialize response
$response = array('success' => false, 'message' => '');

// Handle AJAX POST requests for authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    require_once 'db_connect.php';
    
    // Get and sanitize input data
    $email = isset($_POST['username']) ? trim(strtolower($_POST['username'])) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember_me = isset($_POST['remember']) && $_POST['remember'] === '1';
    
    // Server-side validation
    $errors = array();
    
    // Check if both fields are provided
    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    }
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Validate email domain (Ashesi email required)
    if (!empty($email) && !preg_match('/@ashesi\.edu\.gh$/', $email)) {
        $errors[] = 'Only Ashesi emails are allowed.';
    }
    
    // Validate password not empty
    if (empty($password) || strlen($password) > 128) {
        $errors[] = 'Invalid password provided.';
    }
    
    // Check for SQL injection patterns in password (defense in depth)
    $malicious_patterns = array("'", "--", ";", "/*", "*/", "UNION", "SELECT", "DROP");
    foreach ($malicious_patterns as $pattern) {
        if (stripos($email . $password, $pattern) !== false) {
            error_log("Potential SQL injection attempt on login: " . $email);
            $errors[] = 'Invalid characters in input.';
            break;
        }
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        $response['success'] = false;
        $response['message'] = implode(' ', $errors);
        echo json_encode($response);
        exit;
    }
    
    // Query database to find user by email
    $stmt = $conn->prepare('SELECT id, fullname, password_hash, role FROM users WHERE email = ?');
    if (!$stmt) {
        error_log('Database prepare error: ' . $conn->error);
        http_response_code(500);
        $response['message'] = 'Database error. Please try again later.';
        echo json_encode($response);
        exit;
    }
    
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        error_log('Database execute error: ' . $stmt->error);
        http_response_code(500);
        $response['message'] = 'Database error. Please try again later.';
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password using bcrypt
        if (password_verify($password, $user['password_hash'])) {
            $stmt->close();
            
            // Create/regenerate session
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['fullname'];
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Handle "Remember Me" functionality
            if ($remember_me) {
                // Set a secure cookie for 30 days
                $cookie_token = bin2hex(random_bytes(32));
                $expiry = time() + (86400 * 30); // 30 days
                
                // Store token in database for later verification
                $token_stmt = $conn->prepare('UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?');
                if ($token_stmt) {
                    $expiry_date = date('Y-m-d H:i:s', $expiry);
                    $token_stmt->bind_param('ssi', $cookie_token, $expiry_date, $user['id']);
                    $token_stmt->execute();
                    $token_stmt->close();
                    
                    // Set secure cookie
                    setcookie(
                        'remember_token',
                        $cookie_token,
                        $expiry,
                        '/',
                        '',
                        isset($_SERVER['HTTPS']),
                        true  // HttpOnly - not accessible via JavaScript
                    );
                }
            }
            
            $conn->close();
            
            // Determine dashboard based on role
            $dashboard = 'S DASHBOARD.html'; // Default for students
            if ($user['role'] === 'faculty') {
                $dashboard = 'F DASHBOARD.html';
            } elseif ($user['role'] === 'fi'|| $user['role'] === 'Faculty Intern') {
                $dashboard = 'FI DASHBOARD.html';
            }
            
            // Return success response
            http_response_code(200);
            $response['success'] = true;
            $response['message'] = 'Login successful!';
            $response['username'] = $user['fullname'];
            $response['user_id'] = $user['id'];
            $response['role'] = $user['role'];
            $response['dashboard'] = $dashboard;
            echo json_encode($response);
            exit;
        } else {
            // Password does not match
            error_log("Failed login attempt for email: " . $email);
            http_response_code(401);
            $response['success'] = false;
            $response['message'] = 'Invalid email or password.';
            $stmt->close();
            $conn->close();
            echo json_encode($response);
            exit;
        }
    } else {
        // User not found
        error_log("Login attempt for non-existent email: " . $email);
        http_response_code(401);
        $response['success'] = false;
        $response['message'] = 'Invalid email or password.';
        $stmt->close();
        $conn->close();
        echo json_encode($response);
        exit;
    }
}
?>
