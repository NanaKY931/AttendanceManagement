// --- SweetAlert Helper Function ---
function showLoginError(text) {
    Swal.fire({
        icon: 'error',
        title: 'Login Failed!',
        text: text,
        confirmButtonColor: '#622020'
    });
    return false;
}

// --- Helper function for password strength validation ---
function isStrongPassword(password) {
    if (password.length < 8) return false;
    if (!/\d/.test(password)) return false;
    if (!/[A-Z]/.test(password)) return false;
    return true;
}

// --- Simple SQLi character check (defense in depth) ---
function MalChars(input) {
    return input.includes("'") || input.includes("--") || input.includes(";");
}

function validateLogin(event) {
    event.preventDefault();
    
    const emailInput = document.getElementById('username').value.trim();
    const passwordInput = document.getElementById('password').value;
    const rememberMeChecked = document.getElementById('remember').checked;

    // 1. Empty Field Check
    if (!emailInput || !passwordInput) {
        return showLoginError("All fields must be filled out for authentication.");
    }

    // 2. Email Domain Check
    const requiredDomain = "@ashesi.edu.gh";
    if (!emailInput.endsWith(requiredDomain)) {
        return showLoginError(`Only emails ending in ${requiredDomain} are allowed, as per school policy.`);
    }
    
    // 3. Password Strength Check
    if (!isStrongPassword(passwordInput)) {
        return showLoginError("Weak Password: Password must be 8+ characters and include at least one number and one uppercase letter.");
    }

    // 4. Client-Side SQLi Avoidance Check
    if (MalChars(emailInput) || MalChars(passwordInput)) {
        return showLoginError("Security Warning: Please avoid special characters like quotes or semicolons.");
    }

    // All validation passed, submit via AJAX
    submitLoginForm(event.target, emailInput, passwordInput, rememberMeChecked);

    return false;
}

// Function to submit the form via AJAX to the PHP backend
function submitLoginForm(form, email, password, rememberMe) {
    const formData = new FormData();
    formData.append('username', email);
    formData.append('password', password);
    if (rememberMe) {
        formData.append('remember', '1');
    }

    // Show loading state
    Swal.fire({
        icon: 'info',
        title: 'Authenticating...',
        text: 'Please wait while we verify your credentials.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send POST request to login.php
    fetch('login.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        // Check for HTTP errors
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || `HTTP Error: ${response.status}`);
            }).catch(err => {
                if (err instanceof SyntaxError) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                throw err;
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Determine dashboard URL based on role
            let dashboardUrl = data.dashboard || 'S DASHBOARD.html';
            
            Swal.fire({
                icon: 'success',
                title: 'Login Successful!',
                text: `Welcome back, ${data.username}!`,
                allowOutsideClick: false,
                didOpen: () => {
                    setTimeout(() => {
                        window.location.href = dashboardUrl;
                    }, 1500);
                }
            });
        } else {
            showLoginError(data.message || 'An error occurred during login.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showLoginError(error.message || 'Network error. Please check your connection and try again.');
    });
}

// Check if user is already logged in
function checkExistingSession() {
    fetch('check_session.php', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.logged_in) {
            // User is already logged in, redirect to their dashboard
            Swal.fire({
                icon: 'info',
                title: 'Already Logged In',
                text: 'You are already logged in. Redirecting to your dashboard...',
                allowOutsideClick: false,
                didOpen: () => {
                    setTimeout(() => {
                        window.location.href = data.dashboard || 'S DASHBOARD.html';
                    }, 1500);
                }
            });
        }
    })
    .catch(error => {
        console.error('Session check error:', error);
        // Continue with login page if session check fails
    });
}

// Attach the validation function to the form's submit event and check session on page load
document.addEventListener("DOMContentLoaded", () => {
    // Check if user is already logged in
    checkExistingSession();
    
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', validateLogin);
    }
});