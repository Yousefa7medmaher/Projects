document.addEventListener('DOMContentLoaded', function () {
    const formSlide = document.getElementById('formSlide');
    const showRegister = document.getElementById('showRegister');
    const showLogin = document.getElementById('showLogin');
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    // Check if URL has a hash for register
    if (window.location.hash === '#register') {
        formSlide.classList.add('show-register');
    }

    showRegister.addEventListener('click', function () {
        formSlide.classList.add('show-register');
        window.location.hash = 'register';
    });

    showLogin.addEventListener('click', function () {
        formSlide.classList.remove('show-register');
        window.location.hash = '';
    });

    // Theme toggle functionality
    if (themeToggle) {
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
            updateThemeIcon(true);
        }

        themeToggle.addEventListener('click', function() {
            const isDarkMode = document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
            updateThemeIcon(isDarkMode);
        });
    }

    function updateThemeIcon(isDarkMode) {
        if (isDarkMode) {
            themeIcon.innerHTML = '<path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>';
        } else {
            themeIcon.innerHTML = '<path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>';
        }
    }

    function showNotification(type, title, message, duration = 5000) {
        const container = document.getElementById('notificationContainer');

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;

        let iconText = '';
        switch (type) {
            case 'success':
                iconText = '✓';
                break;
            case 'error':
                iconText = '✕';
                break;
            case 'info':
                iconText = 'ℹ';
                break;
            default:
                iconText = '!';
        }

        notification.innerHTML = `
            <div class="notification-icon">${iconText}</div>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
            <div class="notification-close">×</div>
        `;

        container.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            closeNotification(notification);
        });

        if (duration) {
            setTimeout(() => {
                closeNotification(notification);
            }, duration);
        }
    }

    function closeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 400);
    }

 // LOGIN
document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    // Check for admin credentials
    if (email === 'ahmedsherif123@gmail.com' && password === 'ahmed112233') {
        // Create admin user object
        const adminUser = {
            id: 1,
            username: 'Ahmed Sherif',
            email: 'ahmedsherif123@gmail.com',
            role: 'admin'
        };

        // Generate a mock token
        const token = 'admin_' + Math.random().toString(36).substring(2);

        // Save token and user to localStorage
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(adminUser));

        showNotification('success', 'Admin Login Successful', `Welcome back, Ahmed!`);

        // Redirect to admin dashboard
        setTimeout(() => {
            window.location.href = 'admin_dashboard.html';
        }, 1500);
        return;
    }

    try {
        // For demo purposes, we'll simulate a successful login for any other credentials
        // In a real application, you would validate against your backend

        // Simulate API call
        const mockResponse = {
            success: true,
            data: {
                token: 'user_' + Math.random().toString(36).substring(2),
                user: {
                    id: Math.floor(Math.random() * 1000) + 2,
                    username: email.split('@')[0],
                    email: email,
                    role: 'user'
                }
            }
        };

        // Save token and user to localStorage
        localStorage.setItem('token', mockResponse.data.token);
        localStorage.setItem('user', JSON.stringify(mockResponse.data.user));

        showNotification('success', 'Login Successful', `Welcome back, ${mockResponse.data.user.username}!`);

        // Redirect to user dashboard
        setTimeout(() => {
            window.location.href = 'userpage.html';
        }, 1500);

        /* Commented out actual API call for demo purposes
        const response = await fetch('http://localhost:3000/test/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            const token = data.data.token;
            const user = data.data.user;

            // Save token and user to localStorage
            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(user));

            showNotification('success', 'Login Successful', `Welcome back, ${user.email}!`);

            // Redirect based on user role
            setTimeout(() => {
                if (user.role === 'admin') {
                    window.location.href = 'admin_dashboard.html';
                } else {
                    window.location.href = 'dashboard.html';
                }
            }, 1500);
        } else {
            showNotification('error', 'Login Failed', data.message || 'Invalid credentials');
        }
        */
    } catch (error) {
        showNotification('error', 'Network Error', 'Unable to reach server.');
        console.error(error);
    }
});

    // REGISTER
    document.getElementById('registerForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('register-username').value;
        const email = document.getElementById('register-email').value;
        const phone = document.getElementById('register-phone').value;
        const password = document.getElementById('register-password').value;
        const confirm = document.getElementById('register-confirm').value;

        if (password !== confirm) {
            showNotification('error', 'Registration Failed', 'Passwords do not match.');
            return;
        }

        try {
            // For demo purposes, we'll simulate a successful registration
            // In a real application, you would send this data to your backend

            // Create a new user object
            const newUser = {
                id: Math.floor(Math.random() * 10000) + 1000,
                username: username,
                email: email,
                phone: phone,
                role: 'user'
            };

            // Generate a token
            const token = 'user_' + Math.random().toString(36).substring(2);

            // Save user data to localStorage
            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(newUser));

            // Show success notification
            showNotification('success', 'Registration Successful', `Welcome, ${username}!`);

            // Redirect to user dashboard
            setTimeout(() => {
                window.location.href = 'userpage.html';
            }, 1500);

            /* Commented out actual API call for demo purposes
            const response = await fetch('http://localhost:3000/test/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username,
                    email,
                    phone,
                    password_hash: password
                })
            });

            const data = await response.json();

            if (response.ok) {
                showNotification('success', 'Registration Successful', `Welcome, ${username}!`);
                setTimeout(() => {
                    showNotification('info', 'Verification Email Sent', 'Please check your email to verify your account.', 7000);
                }, 1500);
            } else {
                showNotification('error', 'Registration Failed', data.message || 'Something went wrong.');
            }
            */
        } catch (error) {
            showNotification('error', 'Network Error', 'Unable to reach server.');
            console.error(error);
        }
    });

    // Initial welcome notification
    setTimeout(() => {
        showNotification('info', 'Welcome', 'Please login to your account or create a new one.', 5000);
    }, 1000);
});


function togglePassword() {
    var passwordField = document.getElementById("register-password");
    var confirmPasswordField = document.getElementById("register-confirm");
    var passwordIcon = document.getElementById("toggle-password");
    var confirmPasswordIcon = document.getElementById("toggle-confirm-password");

    // Toggle password visibility for both password and confirm password
    if (passwordField.type === "password") {
        passwordField.type = "text";
        passwordIcon.classList.remove("fa-eye");
        passwordIcon.classList.add("fa-eye-slash");
    } else {
        passwordField.type = "password";
        passwordIcon.classList.remove("fa-eye-slash");
        passwordIcon.classList.add("fa-eye");
    }

    // Toggle confirm password visibility
    if (confirmPasswordField.type === "password") {
        confirmPasswordField.type = "text";
        confirmPasswordIcon.classList.remove("fa-eye");
        confirmPasswordIcon.classList.add("fa-eye-slash");
    } else {
        confirmPasswordField.type = "password";
        confirmPasswordIcon.classList.remove("fa-eye-slash");
        confirmPasswordIcon.classList.add("fa-eye");
    }
}