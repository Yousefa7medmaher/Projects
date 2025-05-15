// Store users data globally for easy access
let usersData = [];

// Function to save users data to localStorage
function saveUsersData() {
    localStorage.setItem('adminDashboardUsers', JSON.stringify(usersData));
}

// Function to load users data from localStorage
function loadUsersData() {
    const savedData = localStorage.getItem('adminDashboardUsers');
    if (savedData) {
        return JSON.parse(savedData);
    }
    return null;
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in and is admin
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    const token = localStorage.getItem('token');

    if (!token || !user.role || user.role !== 'admin') {
        // Redirect non-admin users to login page
        showUnauthorizedMessage();
    } else {
        // Update user info in header
        updateUserInfo(user);

        // Load users data
        loadUsers();
    }

    // Theme toggle functionality
    initThemeToggle();

    // Apply saved color theme
    applyColorTheme();

    // Event listeners
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('refreshBtn').addEventListener('click', loadUsers);
    document.getElementById('resetDataBtn').addEventListener('click', resetData);
    document.getElementById('addUserBtn').addEventListener('click', showAddUserModal);
    document.getElementById('searchInput').addEventListener('input', filterUsers);

    // Modal event listeners
    document.getElementById('closeEditModal').addEventListener('click', closeEditModal);
    document.getElementById('cancelEditBtn').addEventListener('click', closeEditModal);
    document.getElementById('editUserForm').addEventListener('submit', updateUser);

    document.getElementById('closeAddModal').addEventListener('click', closeAddModal);
    document.getElementById('cancelAddBtn').addEventListener('click', closeAddModal);
    document.getElementById('addUserForm').addEventListener('submit', addUser);
});

function updateUserInfo(user) {
    const userAvatar = document.querySelector('.user-avatar');
    const userName = document.querySelector('.user-name');

    // Check if user has a saved avatar
    const savedAvatar = localStorage.getItem('userAvatar');

    if (savedAvatar) {
        // Display the saved avatar image
        userAvatar.innerHTML = `<img src="${savedAvatar}" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">`;
    } else {
        // Display the first letter of the username or email
        if (user.username) {
            userAvatar.textContent = user.username.charAt(0).toUpperCase();
        } else if (user.email) {
            userAvatar.textContent = user.email.charAt(0).toUpperCase();
        }
    }

    // Set the user name
    if (user.username) {
        userName.textContent = user.username;
    } else if (user.email) {
        userName.textContent = user.email;
    }
}

function showUnauthorizedMessage() {
    const mainContent = document.querySelector('.main-content');
    mainContent.innerHTML = `
        <div style="text-align: center; padding: 3rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16" style="color: var(--danger-color); margin-bottom: 1rem;">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
            </svg>
            <h2 style="margin-bottom: 1rem; color: var(--danger-color);">Access Denied</h2>
            <p style="margin-bottom: 2rem;">You don't have permission to access this page. Please log in with an admin account.</p>
            <a href="login_Register.html" class="btn btn-primary">Go to Login</a>
        </div>
    `;
}

function logout() {
    // Clear local storage
    localStorage.removeItem('token');
    localStorage.removeItem('user');

    // Redirect to login page
    window.location.href = 'login_Register.html';
}

async function loadUsers() {
    const tableBody = document.querySelector('#usersTable tbody');

    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align: center; padding: 2rem;">
                <div style="display: inline-block; width: 2rem; height: 2rem; border: 3px solid var(--border-color); border-radius: 50%; border-top-color: var(--primary-color); animation: spin 1s linear infinite;"></div>
                <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
            </td>
        </tr>
    `;

    try {
        // First, check if we have data in localStorage
        const savedData = loadUsersData();

        if (savedData && savedData.length > 0) {
            // Use the saved data
            usersData = savedData;
            renderUsers(usersData);
            return;
        }

        // In a real application, you would fetch from your API if no local data
        // const token = localStorage.getItem('token');
        // const response = await fetch('http://localhost:3000/admin/users', {
        //     headers: {
        //         'Authorization': `Bearer ${token}`
        //     }
        // });
        // const data = await response.json();
        // usersData = data;
        // saveUsersData(); // Save to localStorage

        // For demo purposes, we'll use mock data if no saved data exists
        usersData = [
            { id: 1, username: 'john_doe', email: 'john@example.com', phone: '555-123-4567', role: 'user', status: 'active' },
            { id: 2, username: 'jane_smith', email: 'jane@example.com', phone: '555-987-6543', role: 'user', status: 'active' },
            { id: 3, username: 'admin_user', email: 'admin@example.com', phone: '555-555-5555', role: 'admin', status: 'active' },
            { id: 4, username: 'inactive_user', email: 'inactive@example.com', phone: '555-111-2222', role: 'user', status: 'inactive' },
            { id: 5, username: 'pending_user', email: 'pending@example.com', phone: '555-333-4444', role: 'user', status: 'pending' }
        ];

        // Save the initial mock data to localStorage
        saveUsersData();

        // Simulate network delay
        setTimeout(() => {
            renderUsers(usersData);
        }, 500);

    } catch (error) {
        console.error('Error loading users:', error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--danger-color);">
                    Error loading users. Please try again.
                </td>
            </tr>
        `;
    }
}

function renderUsers(users) {
    const tableBody = document.querySelector('#usersTable tbody');

    if (!users || users.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 2rem;">
                    No users found.
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = '';

    users.forEach(user => {
        const row = document.createElement('tr');
        row.setAttribute('data-user-id', user.id);

        // Status badge class
        let statusClass = '';
        switch (user.status) {
            case 'active':
                statusClass = 'status-active';
                break;
            case 'inactive':
                statusClass = 'status-inactive';
                break;
            case 'pending':
                statusClass = 'status-pending';
                break;
        }

        row.innerHTML = `
            <td>${user.id}</td>
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td>${user.phone}</td>
            <td>${user.role}</td>
            <td><span class="status-badge ${statusClass}">${user.status}</span></td>
            <td class="user-actions">
                <button class="btn btn-outline action-btn edit-user" data-id="${user.id}">Edit</button>
                <button class="btn btn-danger action-btn delete-user" data-id="${user.id}">Delete</button>
            </td>
        `;

        tableBody.appendChild(row);
    });

    // Add event listeners to action buttons
    document.querySelectorAll('.edit-user').forEach(btn => {
        btn.addEventListener('click', () => editUser(btn.getAttribute('data-id')));
    });

    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', () => deleteUser(btn.getAttribute('data-id')));
    });
}

function filterUsers() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

function editUser(userId) {
    // Find the user in the global users data
    const user = usersData.find(u => u.id == userId);

    if (!user) {
        showToast('User not found', 'error');
        return;
    }

    // Populate the form with user data
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-username').value = user.username;
    document.getElementById('edit-email').value = user.email;
    document.getElementById('edit-phone').value = user.phone;
    document.getElementById('edit-role').value = user.role;
    document.getElementById('edit-status').value = user.status;

    // Show the modal
    document.getElementById('editUserModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('show');
}

function updateUser(e) {
    e.preventDefault();

    // Get form data
    const userId = document.getElementById('edit-user-id').value;
    const username = document.getElementById('edit-username').value;
    const email = document.getElementById('edit-email').value;
    const phone = document.getElementById('edit-phone').value;
    const role = document.getElementById('edit-role').value;
    const status = document.getElementById('edit-status').value;

    // Find the user in the global users data
    const userIndex = usersData.findIndex(u => u.id == userId);

    if (userIndex === -1) {
        showToast('User not found', 'error');
        return;
    }

    // Update user data
    usersData[userIndex] = {
        ...usersData[userIndex],
        username,
        email,
        phone,
        role,
        status
    };

    // In a real application, you would send a PUT/PATCH request to your API
    // const token = localStorage.getItem('token');
    // try {
    //     const response = await fetch(`http://localhost:3000/admin/users/${userId}`, {
    //         method: 'PUT',
    //         headers: {
    //             'Content-Type': 'application/json',
    //             'Authorization': `Bearer ${token}`
    //         },
    //         body: JSON.stringify({
    //             username,
    //             email,
    //             phone,
    //             role,
    //             status
    //         })
    //     });
    //     const data = await response.json();
    //     if (response.ok) {
    //         // Update the UI
    //         renderUsers(usersData);
    //         closeEditModal();
    //         showToast('User updated successfully', 'success');
    //     } else {
    //         showToast(data.message || 'Error updating user', 'error');
    //     }
    // } catch (error) {
    //     console.error('Error updating user:', error);
    //     showToast('Network error', 'error');
    // }

    // For demo purposes, we'll just update the UI
    renderUsers(usersData);
    // Save changes to localStorage
    saveUsersData();
    closeEditModal();
    showToast('User updated successfully', 'success');
}

function deleteUser(userId) {
    if (confirm(`Are you sure you want to delete user with ID: ${userId}?`)) {
        // In a real application, you would send a DELETE request to your API
        // const token = localStorage.getItem('token');
        // try {
        //     const response = await fetch(`http://localhost:3000/admin/users/${userId}`, {
        //         method: 'DELETE',
        //         headers: {
        //             'Authorization': `Bearer ${token}`
        //         }
        //     });
        //     if (response.ok) {
        //         // Remove from global data and update UI
        //         usersData = usersData.filter(u => u.id != userId);
        //         renderUsers(usersData);
        //         showToast('User deleted successfully', 'success');
        //     } else {
        //         const data = await response.json();
        //         showToast(data.message || 'Error deleting user', 'error');
        //     }
        // } catch (error) {
        //     console.error('Error deleting user:', error);
        //     showToast('Network error', 'error');
        // }

        // For demo purposes, we'll just remove from the array and update UI
        usersData = usersData.filter(u => u.id != userId);
        renderUsers(usersData);
        // Save changes to localStorage
        saveUsersData();
        showToast('User deleted successfully', 'success');
    }
}

function showAddUserModal() {
    // Reset the form
    document.getElementById('addUserForm').reset();

    // Show the modal
    document.getElementById('addUserModal').classList.add('show');
}

function closeAddModal() {
    document.getElementById('addUserModal').classList.remove('show');
}

function addUser(e) {
    e.preventDefault();

    // Get form data
    const username = document.getElementById('add-username').value;
    const email = document.getElementById('add-email').value;
    const phone = document.getElementById('add-phone').value;
    // Password would be used in a real API call
    // const password = document.getElementById('add-password').value;
    const role = document.getElementById('add-role').value;
    const status = document.getElementById('add-status').value;

    // Generate a new ID (in a real app, this would be done by the server)
    const newId = Math.max(...usersData.map(u => u.id)) + 1;

    // Create new user object
    const newUser = {
        id: newId,
        username,
        email,
        phone,
        role,
        status
    };

    // Add to global users data
    usersData.push(newUser);

    // In a real application, you would send a POST request to your API
    // const token = localStorage.getItem('token');
    // try {
    //     const response = await fetch('http://localhost:3000/admin/users', {
    //         method: 'POST',
    //         headers: {
    //             'Content-Type': 'application/json',
    //             'Authorization': `Bearer ${token}`
    //         },
    //         body: JSON.stringify({
    //             username,
    //             email,
    //             phone,
    //             password,
    //             role,
    //             status
    //         })
    //     });
    //     const data = await response.json();
    //     if (response.ok) {
    //         // Update the UI with the new user from the server
    //         loadUsers();
    //         closeAddModal();
    //         showToast('User added successfully', 'success');
    //     } else {
    //         showToast(data.message || 'Error adding user', 'error');
    //     }
    // } catch (error) {
    //     console.error('Error adding user:', error);
    //     showToast('Network error', 'error');
    // }

    // For demo purposes, we'll just update the UI
    renderUsers(usersData);
    // Save changes to localStorage
    saveUsersData();
    closeAddModal();
    showToast('User added successfully', 'success');
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

function resetData() {
    if (confirm('Are you sure you want to reset all user data to default values? This cannot be undone.')) {
        // Remove the saved data from localStorage
        localStorage.removeItem('adminDashboardUsers');

        // Reset to default data
        usersData = [
            { id: 1, username: 'john_doe', email: 'john@example.com', phone: '555-123-4567', role: 'user', status: 'active' },
            { id: 2, username: 'jane_smith', email: 'jane@example.com', phone: '555-987-6543', role: 'user', status: 'active' },
            { id: 3, username: 'admin_user', email: 'admin@example.com', phone: '555-555-5555', role: 'admin', status: 'active' },
            { id: 4, username: 'inactive_user', email: 'inactive@example.com', phone: '555-111-2222', role: 'user', status: 'inactive' },
            { id: 5, username: 'pending_user', email: 'pending@example.com', phone: '555-333-4444', role: 'user', status: 'pending' }
        ];

        // Save the default data
        saveUsersData();

        // Update the UI
        renderUsers(usersData);

        // Show success message
        showToast('Data has been reset to default values', 'success');
    }
}

function applyColorTheme() {
    // Remove any existing theme classes
    document.body.classList.remove('theme-green', 'theme-purple', 'theme-orange', 'theme-red');

    // Get saved color theme
    const savedColorTheme = localStorage.getItem('colorTheme');

    // Apply the theme if it exists and is not default
    if (savedColorTheme && savedColorTheme !== 'default') {
        document.body.classList.add(`theme-${savedColorTheme}`);
    }
}

function initThemeToggle() {
    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');

    // Check for saved theme preference or use device preference
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const savedTheme = localStorage.getItem('theme');

    if (savedTheme === 'dark' || (!savedTheme && prefersDarkScheme.matches)) {
        document.body.classList.add('dark-theme');
        updateThemeIcon(true);
    }

    themeToggle.addEventListener('click', () => {
        const isDarkMode = document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        updateThemeIcon(isDarkMode);
    });

    function updateThemeIcon(isDarkMode) {
        if (isDarkMode) {
            themeIcon.innerHTML = '<path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/>';
        } else {
            themeIcon.innerHTML = '<path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/>';
        }
    }
}
