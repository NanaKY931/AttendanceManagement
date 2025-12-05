<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Set proper content type
header('Content-Type: application/json; charset=utf-8');

// Handle form submission
$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    require_once 'db_connect.php';

    //Include session checking
    require_once 'check_session.php';
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        $response['message'] = 'Security token validation failed. Please try again.';
        echo json_encode($response);
        exit;
    }
    
    // Get and sanitize input data
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? trim(strtolower($_POST['email'])) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm-password']) ? $_POST['confirm-password'] : '';
    
    // Server-side validation
    $errors = array();
    
    // Check if all fields are provided
    if (empty($fullname) || empty($email) || empty($role) || empty($password) || empty($confirm_password)) {
        $errors[] = 'All fields must be filled out.';
    }
    
    // Validate full name length and characters (allows international characters)
    if (!empty($fullname)) {
        if (strlen($fullname) > 255) {
            $errors[] = 'Full name cannot exceed 255 characters.';
        }
        // Allow letters, spaces, hyphens, and apostrophes from various languages
        if (!preg_match('/^[\p{L}\s\-\']+$/u', $fullname)) {
            $errors[] = 'Full name contains invalid characters.';
        }
    }
    
    // Validate email format
    if (!empty($email)) {
        if (strlen($email) > 255) {
            $errors[] = 'Email cannot exceed 255 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }
    }
    
    // Validate email domain (Ashesi email required)
    if (!empty($email) && !preg_match('/@ashesi\.edu\.gh$/', $email)) {
        $errors[] = 'Only emails ending in @ashesi.edu.gh are allowed.';
    }
    
    // Validate role
    if (!empty($role) && !in_array($role, ['student', 'fi', 'faculty'])) {
        $errors[] = 'Invalid user role selected.';
    }
    
    // Validate password match
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Validate password strength
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (strlen($password) > 128) {
            $errors[] = 'Password cannot exceed 128 characters.';
        }
    }
    
    // Check for SQL injection patterns (defense in depth - prepared statements are primary defense)
    $malicious_patterns = array("'", "--", ";", "/*", "*/", "xp_", "sp_", "UNION", "SELECT", "DROP");
    $combined_input = $fullname . $email . $password;
    foreach ($malicious_patterns as $pattern) {
        if (stripos($combined_input, $pattern) !== false) {
            // Log potential attack
            error_log("Potential SQL injection attempt: " . $combined_input);
            $errors[] = 'Invalid characters detected in input.';
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
    
    // Check if email already exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
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
    
    if ($result->num_rows > 0) {
        http_response_code(409);
        $response['success'] = false;
        $response['message'] = 'This email address is already registered.';
        $stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $stmt->close();
    
    // Hash the password using bcrypt (cost 12 is appropriate for 2024)
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    if ($password_hash === false) {
        error_log('Password hashing failed');
        http_response_code(500);
        $response['message'] = 'Error processing password. Please try again.';
        echo json_encode($response);
        exit;
    }
    
    // Prepare and execute insert statement
    $insert_stmt = $conn->prepare('INSERT INTO users (fullname, email, role, password_hash) VALUES (?, ?, ?, ?)');
    if (!$insert_stmt) {
        error_log('Insert prepare error: ' . $conn->error);
        http_response_code(500);
        $response['message'] = 'Database error. Please try again later.';
        echo json_encode($response);
        exit;
    }
    
    $insert_stmt->bind_param('ssss', $fullname, $email, $role, $password_hash);
    
    if (!$insert_stmt->execute()) {
        error_log('Insert execute error: ' . $insert_stmt->error);
        http_response_code(500);
        // Check if it's a duplicate email error
        if (strpos($insert_stmt->error, 'Duplicate entry') !== false) {
            $response['message'] = 'This email address is already registered.';
        } else {
            $response['message'] = 'Error during registration. Please try again.';
        }
        $insert_stmt->close();
        echo json_encode($response);
        exit;
    }
    
    $insert_stmt->close();
    $conn->close();
    
    // Registration successful
    http_response_code(201);
    $response['success'] = true;
    $response['message'] = 'Registration successful! Redirecting to login...';
    echo json_encode($response);
    exit;
}

// Generate CSRF token for form
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System - Registration</title>
    <link rel="stylesheet" href="mainstyle.css">
</head>
<body>
    <main class="auth-container">
        <header>
            <img src="logo.webp" alt="Ashesi University Logo" class="logo" width="150" height="150">
            <h1>Create Your Account</h1>
        </header>

        <form class="signup-form" action="Signup.php" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" placeholder="Enter your full legal name" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your Ashesi email (required)" required>

            <label for="role">I am a:</label>
            <select id="role" name="role" required>
                <option value="--" disabled selected>Select your user type</option>
                <option value="student">Student</option>
                <option value="fi">Faculty Intern</option>
                <option value="faculty">Faculty</option>
            </select>
            
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Create a password" required>

            <label for="confirm-password">Confirm Password</label>
            <input type="password" id="confirm-password" name="confirm-password" placeholder="Re-enter password" required>

            <button type="submit" class="submit-btn">Register</button>
        </form>

        <p class="switch-link">
            Already have an account?
            <a href="login.php">Log In Here</a>
        </p>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script type="module" src="signup.js"></script>
    </main>
</body>
</html>