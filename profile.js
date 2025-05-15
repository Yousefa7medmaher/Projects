// Store user data
let userData = {};
let userAvatar = null;

document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    const token = localStorage.getItem('token');
    
    if (!token) {
        // Redirect to login page if not logged in
        window.location.href = 'login_Register.html';
        return;
    }
    
    // Load user data
    userData = user;
    
    // Update user info in header and profile
    updateUserInfo();
    
    // Load saved avatar from localStorage
    loadAvatar();
    
    // Load saved theme preferences
    loadThemePreferences();
    
    // Event listeners
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('personalInfoForm').addEventListener('submit', updatePersonalInfo);
    document.getElementById('passwordForm').addEventListener('submit', updatePassword);
    document.getElementById('cancelPersonalBtn').addEventListener('click', resetPersonalForm);
    document.getElementById('cancelPasswordBtn').addEventListener('click', resetPasswordForm);
    document.getElementById('changeAvatarBtn').addEventListener('click', triggerAvatarUpload);
    document.getElementById('avatarInput').addEventListener('change', handleAvatarChange);
    document.getElementById('darkModeToggle').addEventListener('change', toggleDarkMode);
    
    // Theme option event listeners
    const themeOptions = document.querySelectorAll('.theme-option');
    themeOptions.forEach(option => {
        option.addEventListener('click', () => {
            const theme = option.getAttribute('data-theme');
            setColorTheme(theme);
            
            // Update active state
            themeOptions.forEach(opt => opt.classList.remove('active'));
            option.classList.add('active');
        });
    });
    
    // Theme toggle functionality
    initThemeToggle();
});

function updateUserInfo() {
    // Update header user info
    const userAvatarHeader = document.getElementById('userAvatar');
    const userNameHeader = document.getElementById('userName');
    
    // Update profile info
    const profileAvatar = document.getElementById('profileAvatar');
    const profileName = document.getElementById('profileName');
    const profileEmail = document.getElementById('profileEmail');
    
    // Update form fields
    const fullNameInput = document.getElementById('fullName');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    
    // Set user name in header
    if (userData.username) {
        userNameHeader.textContent = userData.username;
    } else if (userData.email) {
        userNameHeader.textContent = userData.email.split('@')[0];
    }
    
    // Set profile name and email
    profileName.textContent = userData.username || userData.email.split('@')[0];
    profileEmail.textContent = userData.email;
    
    // Set form field values
    fullNameInput.value = userData.username || '';
    emailInput.value = userData.email || '';
    phoneInput.value = userData.phone || '';
    
    // Set avatar text if no image
    if (!userAvatar) {
        const initial = (userData.username ? userData.username.charAt(0) : userData.email.charAt(0)).toUpperCase();
        userAvatarHeader.textContent = initial;
        
        // Remove any existing image and add the initial
        while (profileAvatar.firstChild) {
            if (profileAvatar.lastChild.className !== 'avatar-overlay') {
                profileAvatar.removeChild(profileAvatar.lastChild);
            } else {
                break;
            }
        }
        profileAvatar.prepend(document.createTextNode(initial));
    }
}

function loadAvatar() {
    const savedAvatar = localStorage.getItem('userAvatar');
    if (savedAvatar) {
        userAvatar = savedAvatar;
        
        // Set avatar in header
        const userAvatarHeader = document.getElementById('userAvatar');
        userAvatarHeader.innerHTML = `<img src="${userAvatar}" alt="User Avatar">`;
        
        // Set avatar in profile
        const profileAvatar = document.getElementById('profileAvatar');
        
        // Remove any existing content except the overlay
        while (profileAvatar.firstChild) {
            if (profileAvatar.lastChild.className !== 'avatar-overlay') {
                profileAvatar.removeChild(profileAvatar.lastChild);
            } else {
                break;
            }
        }
        
        // Add the image
        const img = document.createElement('img');
        img.src = userAvatar;
        img.alt = 'User Avatar';
        profileAvatar.prepend(img);
    }
}

function triggerAvatarUpload() {
    document.getElementById('avatarInput').click();
}

function handleAvatarChange(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(event) {
            userAvatar = event.target.result;
            
            // Save to localStorage
            localStorage.setItem('userAvatar', userAvatar);
            
            // Update UI
            loadAvatar();
            
            // Show success message
            showToast('Profile picture updated successfully', 'success');
        };
        
        reader.readAsDataURL(file);
    }
}

function updatePersonalInfo(e) {
    e.preventDefault();
    
    const fullName = document.getElementById('fullName').value;
    const phone = document.getElementById('phone').value;
    
    // Update user data
    userData.username = fullName;
    userData.phone = phone;
    
    // Save to localStorage
    localStorage.setItem('user', JSON.stringify(userData));
    
    // Update UI
    updateUserInfo();
    
    // Show success message
    showToast('Personal information updated successfully', 'success');
}

function updatePassword(e) {
    e.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    // Simple validation
    if (newPassword !== confirmPassword) {
        showToast('New passwords do not match', 'error');
        return;
    }
    
    // In a real app, you would verify the current password against the stored password
    // For demo purposes, we'll just accept any current password
    
    // Show success message
    showToast('Password updated successfully', 'success');
    
    // Reset form
    resetPasswordForm();
}

function resetPersonalForm() {
    document.getElementById('personalInfoForm').reset();
    updateUserInfo(); // Reset to saved values
}

function resetPasswordForm() {
    document.getElementById('passwordForm').reset();
}

function loadThemePreferences() {
    // Load color theme
    const savedColorTheme = localStorage.getItem('colorTheme') || 'default';
    setColorTheme(savedColorTheme);
    
    // Set active state on the theme option
    const themeOption = document.querySelector(`.theme-option[data-theme="${savedColorTheme}"]`);
    if (themeOption) {
        themeOption.classList.add('active');
    }
    
    // Load dark mode preference
    const darkModeToggle = document.getElementById('darkModeToggle');
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        darkModeToggle.checked = true;
    }
}

function setColorTheme(theme) {
    // Remove any existing theme classes
    document.body.classList.remove('theme-green', 'theme-purple', 'theme-orange', 'theme-red');
    
    // Add the new theme class if it's not default
    if (theme !== 'default') {
        document.body.classList.add(`theme-${theme}`);
    }
    
    // Save to localStorage
    localStorage.setItem('colorTheme', theme);
}

function toggleDarkMode() {
    const isDarkMode = document.getElementById('darkModeToggle').checked;
    
    if (isDarkMode) {
        document.body.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
    } else {
        document.body.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
    }
    
    // Update theme icon
    updateThemeIcon(isDarkMode);
}

function logout() {
    // Clear local storage
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    
    // Redirect to login page
    window.location.href = 'login_Register.html';
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastContent = document.getElementById('toastContent');
    const toastIcon = document.getElementById('toastIcon');
    
    // Set message
    toastContent.textContent = message;
    
    // Set type
    toast.className = 'toast';
    toast.classList.add(type);
    
    // Set icon
    if (type === 'success') {
        toastIcon.innerHTML = '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>';
    } else {
        toastIcon.innerHTML = '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>';
    }
    
    // Show toast
    toast.classList.add('show');
    
    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    
    // Check for saved theme preference or use device preference
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark' || (!savedTheme && prefersDarkScheme.matches)) {
        document.body.classList.add('dark-theme');
        document.getElementById('darkModeToggle').checked = true;
        updateThemeIcon(true);
    }
    
    themeToggle.addEventListener('click', () => {
        const isDarkMode = document.body.classList.toggle('dark-theme');
        document.getElementById('darkModeToggle').checked = isDarkMode;
        localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        updateThemeIcon(isDarkMode);
    });
}

function updateThemeIcon(isDarkMode) {
    const themeIcon = document.getElementById('themeIcon');
    
    if (isDarkMode) {
        themeIcon.innerHTML = '<path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>';
    } else {
        themeIcon.innerHTML = '<path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>';
    }
}
