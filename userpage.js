// Store user data and transactions
let userData = {};
let transactions = [];
let accountBalance = 5000.00; // Default starting balance

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

    // Update user info in header
    updateUserInfo();

    // Load saved transactions from localStorage
    loadTransactions();

    // Load saved balance from localStorage
    loadBalance();

    // Update account number (generate a random one based on user ID)
    updateAccountNumber();

    // Apply saved color theme
    applyColorTheme();

    // Event listeners
    document.getElementById('logoutBtn').addEventListener('click', logout);
    document.getElementById('transactionForm').addEventListener('submit', sendMoney);
    document.getElementById('cancelBtn').addEventListener('click', resetForm);

    // Add a button to simulate receiving money (for demo purposes)
    const dashboardCards = document.querySelector('.dashboard-cards');
    const receiveMoneyCard = document.createElement('div');
    receiveMoneyCard.className = 'dashboard-card';
    receiveMoneyCard.innerHTML = `
        <div class="card-header">
            <div class="card-title">Quick Actions</div>
            <div class="card-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                </svg>
            </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <button id="receiveMoneyBtn" class="btn btn-primary">Receive Money (Demo)</button>
            <button id="viewAccountBtn" class="btn btn-outline">View Account Details</button>
        </div>
    `;
    dashboardCards.appendChild(receiveMoneyCard);

    // Add event listener for the receive money button
    document.getElementById('receiveMoneyBtn').addEventListener('click', receiveMoney);
    document.getElementById('viewAccountBtn').addEventListener('click', viewAccountDetails);

    // Theme toggle functionality
    initThemeToggle();
});

function updateUserInfo() {
    const userAvatar = document.getElementById('userAvatar');
    const userName = document.getElementById('userName');

    // Check if user has a saved avatar
    const savedAvatar = localStorage.getItem('userAvatar');

    if (savedAvatar) {
        // Display the saved avatar image
        userAvatar.innerHTML = `<img src="${savedAvatar}" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover;">`;
    } else {
        // Display the first letter of the username or email
        if (userData.username) {
            userAvatar.textContent = userData.username.charAt(0).toUpperCase();
        } else if (userData.email) {
            userAvatar.textContent = userData.email.charAt(0).toUpperCase();
        }
    }

    // Set the user name
    if (userData.username) {
        userName.textContent = userData.username;
    } else if (userData.email) {
        userName.textContent = userData.email.split('@')[0];
    }
}

function updateAccountNumber() {
    const accountNumber = document.getElementById('accountNumber');
    // Generate a random account number based on user ID
    const userId = userData.id || Math.floor(Math.random() * 10000);
    const randomPart = Math.floor(Math.random() * 10000000000).toString().padStart(10, '0');
    const formattedNumber = `${userId}${randomPart}`.match(/.{1,4}/g).join('-');
    accountNumber.textContent = formattedNumber;
}

function loadTransactions() {
    const savedTransactions = localStorage.getItem('userTransactions');
    if (savedTransactions) {
        transactions = JSON.parse(savedTransactions);
        renderTransactions();
    }
}

function saveTransactions() {
    localStorage.setItem('userTransactions', JSON.stringify(transactions));
}

function loadBalance() {
    const savedBalance = localStorage.getItem('userBalance');
    if (savedBalance) {
        accountBalance = parseFloat(savedBalance);
        updateBalanceDisplay();
    } else {
        // Save initial balance
        saveBalance();
    }
}

function saveBalance() {
    localStorage.setItem('userBalance', accountBalance.toString());
    updateBalanceDisplay();
}

function updateBalanceDisplay() {
    const balanceElement = document.getElementById('accountBalance');
    balanceElement.textContent = `$${accountBalance.toFixed(2)}`;
}

function sendMoney(e) {
    e.preventDefault();

    const recipientAccount = document.getElementById('recipientAccount').value;
    const amount = parseFloat(document.getElementById('amount').value);
    const description = document.getElementById('description').value || 'Transfer';

    // Validate amount
    if (isNaN(amount) || amount <= 0) {
        showToast('Please enter a valid amount', 'error');
        return;
    }

    // Check if user has enough balance
    if (amount > accountBalance) {
        showToast('Insufficient balance', 'error');
        return;
    }

    // Create transaction object
    const transaction = {
        id: Date.now(),
        type: 'sent',
        amount: amount,
        recipient: recipientAccount,
        description: description,
        date: new Date().toISOString()
    };

    // Update balance
    accountBalance -= amount;
    saveBalance();

    // Add transaction to history
    transactions.unshift(transaction);
    saveTransactions();

    // Render transactions
    renderTransactions();

    // Reset form
    resetForm();

    // Show success message
    showToast('Transaction successful!', 'success');
}

function renderTransactions() {
    const transactionList = document.getElementById('transactionList');

    if (transactions.length === 0) {
        transactionList.innerHTML = '<p style="text-align: center; padding: 1rem;">No transactions yet.</p>';
        return;
    }

    transactionList.innerHTML = '';

    transactions.forEach(transaction => {
        const transactionItem = document.createElement('div');
        transactionItem.className = 'transaction-item';

        const formattedDate = new Date(transaction.date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        const isReceived = transaction.type === 'received';

        transactionItem.innerHTML = `
            <div class="transaction-details">
                <div class="transaction-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        ${isReceived
                            ? '<path d="M16 8A8 8 0 1 0 0 8a8 8 0 0 0 16 0zm-5.904-2.803a.5.5 0 1 1 .707.707L6.707 10h2.768a.5.5 0 0 1 0 1H5.5a.5.5 0 0 1-.5-.5V6.525a.5.5 0 0 1 1 0v2.768l4.096-4.096z"/>'
                            : '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-5.904-2.803a.5.5 0 1 0 .707.707L6.707 10h2.768a.5.5 0 0 0 0-1H5.5a.5.5 0 0 0-.5.5V13.5a.5.5 0 0 0 1 0v-2.768l4.096-4.096z"/>'
                        }
                    </svg>
                </div>
                <div class="transaction-info">
                    <h4>${isReceived ? 'Received from' : 'Sent to'} ${isReceived ? transaction.sender : transaction.recipient}</h4>
                    <p>${transaction.description}</p>
                    <p>${formattedDate}</p>
                </div>
            </div>
            <div class="transaction-amount ${transaction.type}">
                ${isReceived ? '+' : '-'}$${transaction.amount.toFixed(2)}
            </div>
        `;

        transactionList.appendChild(transactionItem);
    });
}

function resetForm() {
    document.getElementById('transactionForm').reset();
}

function receiveMoney() {
    // Generate a random amount between $10 and $1000
    const amount = Math.floor(Math.random() * 990) + 10;

    // Generate a random sender name
    const senders = [
        'John Smith',
        'Jane Doe',
        'Michael Johnson',
        'Sarah Williams',
        'Robert Brown',
        'Emily Davis',
        'David Miller',
        'Lisa Wilson',
        'James Taylor',
        'Jennifer Anderson'
    ];
    const sender = senders[Math.floor(Math.random() * senders.length)];

    // Generate a random account number
    const senderAccount = Math.floor(Math.random() * 10000000000).toString().padStart(10, '0').match(/.{1,4}/g).join('-');

    // Create transaction object
    const transaction = {
        id: Date.now(),
        type: 'received',
        amount: amount,
        sender: sender,
        senderAccount: senderAccount,
        description: 'Payment received',
        date: new Date().toISOString()
    };

    // Update balance
    accountBalance += amount;
    saveBalance();

    // Add transaction to history
    transactions.unshift(transaction);
    saveTransactions();

    // Render transactions
    renderTransactions();

    // Show success message
    showToast(`Received $${amount.toFixed(2)} from ${sender}`, 'success');
}

function viewAccountDetails() {
    // Create a modal to display account details
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.style.display = 'block';

    const accountNumber = document.getElementById('accountNumber').textContent;

    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Account Details</h2>
                <button class="close-btn" id="closeAccountModal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.7;">Account Holder</h3>
                    <p style="font-size: 1.25rem; font-weight: 600;">${userData.username || userData.email}</p>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.7;">Account Number</h3>
                    <p style="font-size: 1.25rem; font-weight: 600; letter-spacing: 1px;">${accountNumber}</p>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.7;">Account Type</h3>
                    <p style="font-size: 1.25rem; font-weight: 600;">Checking Account</p>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.7;">Current Balance</h3>
                    <p style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">$${accountBalance.toFixed(2)}</p>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.5rem; opacity: 0.7;">Account Status</h3>
                    <p style="font-size: 1.25rem; font-weight: 600; color: var(--success-color);">Active</p>
                </div>

                <button class="btn btn-primary" style="width: 100%;" id="closeAccountDetailsBtn">Close</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Add event listeners to close the modal
    document.getElementById('closeAccountModal').addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    document.getElementById('closeAccountDetailsBtn').addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    });
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
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

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
