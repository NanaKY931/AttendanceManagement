<?php
// login_handler.php (Conceptual PHP script for the form action)

function authenticate_user($email, $password) {
    // Connect to database (example using mysqli)
    $conn = new mysqli("localhost", "user", "password", "database");
    
    if ($conn->connect_error) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $stmt->close();
            $conn->close();
            return $user['id'];
        }
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['username'];
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember']); // This is '1' if checked, or not set otherwise.

    // 1. AUTHENTICATE USER (Check password hash against database)
    $user_id = authenticate_user ($email, $password);
    
    if ($user_id) {
        // 2. SET SESSION / BASIC COOKIE
        session_start();
        $_SESSION['user_id'] = $user_id;

        // 3. REMEMBER ME LOGIC (SET LONG-LASTING COOKIE)
        if ($remember_me) {
            // Generate a secure, unique identifier (the token)
            $token = bin2hex(random_bytes(32));
            $expiry_time = time() + (86400 * 30); // 30 days expiry (86400 seconds in a day)
            
            setcookie('remember_token', $token, $expiry_time, "/"); 
            // IMPORTANT: You would then save this token and the user_id in a new database table
            // so you can verify the token on future visits (this requires a new SQL table).
            // Example SQL (conceptual):
            // INSERT INTO persistent_logins (user_id, token, expires) VALUES (..., ..., ...);
        }

        // 4. REDIRECT to Dashboard
        header("Location: S DASHBOARD.html");
        exit;
    } else {
        // Handle failed login
        // Redirect back to login.html with an error message
        header("Location: login.html?error=invalid");
        exit;
    }
}
?>