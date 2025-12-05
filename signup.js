// --- Helper function for sweetalert2 signup error display ---
function showSignupError(text) {
    Swal.fire({
        icon: 'error',
        title: 'Registration Failed!',
        text: text,
        confirmButtonColor: '#622020'
    });
    return false;
}

function isStrongPassword(password) {
    if (password.length < 8) return false;
    if (!/\d/.test(password)) return false;
    if (!/[A-Z]/.test(password)) return false;
    return true;
}

function MalChars(input) {
    return input.includes("'") || input.includes("--") || input.includes(";");
}


function validateSignup(event) {
    event.preventDefault();
    
    const fullNameInput = document.getElementById('fullname').value;
    const emailInput = document.getElementById('email').value;
    const roleInput = document.getElementById('role').value;
    const passwordInput = document.getElementById('password').value;
    const confirmPasswordInput = document.getElementById('confirm-password').value;

    if (!fullNameInput || !emailInput || !roleInput || !passwordInput || !confirmPasswordInput) {
        return showSignupError("Registration Failed: All fields must be filled out.");
    }
    
    if (roleInput === "--" || roleInput === "") {
        return showSignupError("Registration Failed: Please select a user role.");
    }

    if (passwordInput !== confirmPasswordInput) {
        return showSignupError("Password Mismatch: The Password and Confirm Password fields must be identical.");
    }
    
    const requiredDomain = "@ashesi.edu.gh";
    if (!emailInput.endsWith(requiredDomain)) {
        return showSignupError(`Registration Failed: Only emails ending in ${requiredDomain} are allowed.`);
    }
    
    if (!isStrongPassword(passwordInput)) {
        return showSignupError("Weak Password: Password must be 8+ characters and include at least one number and one uppercase letter.");
    }

    const combinedInput = `${fullNameInput} ${emailInput} ${passwordInput}`;
    if (MalChars(combinedInput)) {
        return showSignupError("Security Warning: Please avoid special characters like quotes or semicolons in your inputs.");
    }

    // All client-side validations passed, submit the form via AJAX
    submitSignupForm(event.target, fullNameInput, emailInput, roleInput, passwordInput, confirmPasswordInput);

    return false;
}

// Function to submit the form via AJAX to the PHP backend
function submitSignupForm(form, fullName, email, role, password, confirmPassword) {
    const formData = new FormData();
    formData.append('fullname', fullName);
    formData.append('email', email);
    formData.append('role', role);
    formData.append('password', password);
    formData.append('confirm-password', confirmPassword);

    // Show loading state
    Swal.fire({
        icon: 'info',
        title: 'Processing...',
        text: 'Please wait while we create your account.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send POST request to Signup.php
    fetch('Signup.php', {
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
                throw new Error(`HTTP Error: ${response.status}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Registration Successful!',
                text: 'Your account has been created. Redirecting to login...',
                allowOutsideClick: false,
                didOpen: () => {
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 2000);
                }
            });
        } else {
            showSignupError(data.message || 'An error occurred during registration.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showSignupError(error.message || 'Network error. Please check your connection and try again.');
    });
}

// Attach the validation function to the form's submit event
document.addEventListener("DOMContentLoaded", () => {
    const signupForm = document.querySelector('.signup-form');
    if (signupForm) {
        signupForm.addEventListener('submit', validateSignup);
    }
});