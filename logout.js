// logout.js
// Requires SweetAlert2 (Swal) to be loaded on the page.
// This file governs the logout process, handling both user interaction and server communication.
// It can be triggered by clicking elements with the 'logout' class or data-logout attribute,
// or programmatically by calling the logout() function.

async function logout(event) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();

    try {
        const response = await fetch('logout.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await response.json(); // wait for JSON response

        if (!response.ok) {
            throw new Error(data.message || 'Logout failed');
        }

        // Inform the user with SweetAlert and wait for confirmation
        await Swal.fire({
            icon: 'success',
            title: data.title || 'Logged out',
            text: data.message || 'You have been logged out.',
            confirmButtonText: 'Go to login'
        });

        // Redirect to login page after confirmation
        window.location.href = data.redirect || 'login.php';

    } catch (err) {
        console.error('Logout error:', err);
        await Swal.fire({
            icon: 'error',
            title: 'Logout error',
            text: err.message || 'An error occurred while logging out.'
        });
    }
}

// Expose function so it can be triggered programmatically
window.logout = logout;

// Attach event listeners to any Logout button/element
function attachLogoutListeners(selector = 'button.logout, a.logout, .logout, [data-logout]') {
    document.querySelectorAll(selector).forEach(el => {
        el.addEventListener('click', logout);
    });
}

// Attach listeners when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => attachLogoutListeners());
} else {
    attachLogoutListeners();
}