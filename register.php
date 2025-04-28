<?php
session_start();
require_once "config/database.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists";
        } else {
            try {
                $db->beginTransaction();
                
                // Create new user
                $query = "INSERT INTO users (username, email, phone, password_hash, role, status, auth_type) 
                         VALUES (:username, :email, :phone, :password_hash, 'user', 'active', 'local')";
                
                $stmt = $db->prepare($query);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":phone", $phone);
                $stmt->bindParam(":password_hash", $password_hash);
                
                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();
                    
                    // Log the registration
                    $log_query = "INSERT INTO access_logs (plate_number, recognized, message) 
                                VALUES (:plate, :recognized, :message)";
                    $log_stmt = $db->prepare($log_query);
                    $plate = "USER_REGISTRATION";
                    $recognized = true;
                    $message = "New user registered: " . $username;
                    $log_stmt->bindParam(":plate", $plate);
                    $log_stmt->bindParam(":recognized", $recognized);
                    $log_stmt->bindParam(":message", $message);
                    $log_stmt->execute();
                    
                    $db->commit();
                    $success = "Registration successful! You can now login.";
                } else {
                    throw new Exception("Registration failed");
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANPR System - Register</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --accent-color: #3498db;
            --accent-hover: #2980b9;
            --error-color: #e74c3c;
            --success-color: #2ecc71;
            --input-bg: #2c2c2c;
            --input-border: #3d3d3d;
            --input-focus: #4a4a4a;
            --border-radius: 8px;
            --box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-image: linear-gradient(to bottom right, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6)), url('/api/placeholder/1920/1080');
            background-size: cover;
            background-position: center;
            overflow: hidden;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
        }
        
        .register-info {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        
        .register-branding {
            margin-bottom: 30px;
        }
        
        .register-branding h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .register-branding h1 i {
            margin-right: 15px;
            font-size: 38px;
            color: var(--accent-color);
        }
        
        .register-branding p {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.6;
        }
        
        .register-benefits {
            margin-top: 40px;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .benefit-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .benefit-icon i {
            color: var(--accent-color);
            font-size: 16px;
        }
        
        .benefit-text h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .benefit-text p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .register-form-container {
            padding: 40px;
            background-color: var(--card-bg);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow-y: auto;
            max-height: 100vh;
        }
        
        .register-form-header {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .register-form-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .register-form-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 3px solid var(--error-color);
            color: var(--error-color);
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .error-message i {
            margin-right: 10px;
        }
        
        .success-message {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 3px solid var(--success-color);
            color: var(--success-color);
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .success-message i {
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border-radius: var(--border-radius);
            border: 1px solid var(--input-border);
            background-color: var(--input-bg);
            color: var(--text-primary);
            font-size: 15px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            background-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--text-primary);
        }
        
        .password-strength {
            margin-top: 8px;
            height: 5px;
            background-color: var(--input-border);
            border-radius: 3px;
            overflow: hidden;
        }
        
        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
        }
        
        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            margin-top: 10px;
        }
        
        .btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .register-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .register-footer a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .terms-privacy {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 15px;
        }
        
        .terms-privacy a {
            color: var(--accent-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .terms-privacy a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .register-branding,
        .register-benefits,
        .register-form-header,
        .form-group,
        .btn,
        .register-footer {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .register-branding { animation-delay: 0.1s; }
        .register-benefits { animation-delay: 0.2s; }
        .register-form-header { animation-delay: 0.3s; }
        .form-group:nth-child(1) { animation-delay: 0.4s; }
        .form-group:nth-child(2) { animation-delay: 0.5s; }
        .form-group:nth-child(3) { animation-delay: 0.6s; }
        .form-group:nth-child(4) { animation-delay: 0.7s; }
        .form-group:nth-child(5) { animation-delay: 0.8s; }
        .btn { animation-delay: 0.9s; }
        .register-footer { animation-delay: 1s; }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .register-info {
                display: none;
            }
            
            .register-form-container {
                padding: 30px;
                max-height: 90vh;
            }
        }
        
        @media (max-width: 576px) {
            .register-form-container {
                padding: 25px 20px;
            }
        }
        
        /* Particle Background Animation */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
        }
        
        /* Form Steps */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-steps:before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--input-border);
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            width: 32px;
            height: 32px;
            background-color: var(--input-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-weight: 600;
            border: 2px solid var(--input-border);
        }
        
        .step.active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .step.completed {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .step-label {
            position: absolute;
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <!-- Particle Background Animation -->
    <div class="particles" id="particles"></div>
    
    <div class="register-container">
        <div class="register-info">
            <div class="register-branding">
                <h1><i class="fas fa-car"></i> ANPR System</h1>
                <p>Join our advanced Automatic Number Plate Recognition System and experience efficient vehicle management.</p>
            </div>
            
            <div class="register-benefits">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Easy Registration</h3>
                        <p>Quick and hassle-free account creation process.</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Vehicle Management</h3>
                        <p>Add and manage multiple vehicles under one account.</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Notifications</h3>
                        <p>Receive alerts for vehicle entry, exit, and parking duration.</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Online Payments</h3>
                        <p>Secure payment processing for parking fees and subscriptions.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-form-container">
            <div class="register-form-header">
                <h2>Create Account</h2>
                <p>Fill in your details to start using our service</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-with-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="Enter your phone number" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Create a password" required onkeyup="checkPasswordStrength()">
                        <span class="toggle-password" onclick="togglePasswordVisibility('password', 'toggleIcon1')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </span>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="passwordStrengthMeter"></div>
                    </div>
                    <div class="strength-text">
                        <span id="passwordStrengthText">Password strength</span>
                        <span id="passwordCriteria">8+ chars, uppercase, number</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required onkeyup="checkPasswordMatch()">
                        <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>
                    <div id="passwordMatchMessage" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
                
                <div class="register-footer">
                    Already have an account? <a href="login.php">Sign In</a>
                </div>
                
                <div class="terms-privacy">
                    By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }
        
        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById("password").value;
            const meter = document.getElementById("passwordStrengthMeter");
            const strengthText = document.getElementById("passwordStrengthText");
            
            // Define strength criteria
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChars = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            const isLongEnough = password.length >= 8;
            
            // Calculate strength
            let strength = 0;
            if (password.length > 0) strength += 20;
            if (password.length >= 8) strength += 20;
            if (hasUpperCase) strength += 20;
            if (hasLowerCase) strength += 20;
            if (hasNumbers) strength += 10;
            if (hasSpecialChars) strength += 10;
            
            // Update meter
            meter.style.width = strength + "%";
            
            // Set color based on strength
            if (strength < 30) {
                meter.style.backgroundColor = "#e74c3c";
                strengthText.textContent = "Weak";
                strengthText.style.color = "#e74c3c";
            } else if (strength < 70) {
                meter.style.backgroundColor = "#f39c12";
                strengthText.textContent = "Medium";
                strengthText.style.color = "#f39c12";
            } else {
                meter.style.backgroundColor = "#2ecc71";
                strengthText.textContent = "Strong";
                strengthText.style.color = "#2ecc71";
            }
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirm_password").value;
            const matchMessage = document.getElementById("passwordMatchMessage");
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchMessage.textContent = "Passwords match";
                    matchMessage.style.color = "#2ecc71";
                } else {
                    matchMessage.textContent = "Passwords do not match";
                    matchMessage.style.color = "#e74c3c";
                }
            } else {
                matchMessage.textContent = "";
            }
        }
        
        // Particle background animation
        document.addEventListener("DOMContentLoaded", function() {
            const particlesContainer = document.getElementById("particles");
            const particleCount = 50;
            
            // Create particles
            for (let i = 0; i < particleCount; i++) {
                createParticle();
            }
            
            function createParticle() {
                const particle = document.createElement("div");
                particle.classList.add("particle");
                
                // Random position
                const posX = Math.random() * window.innerWidth;
                const posY = Math.random() * window.innerHeight;
                
                // Random size
                const size = Math.random() * 3 + 1;
                
                // Random opacity
                const opacity = Math.random() * 0.5 + 0.1;
                
                // Set particle properties
                particle.style.left = posX + "px";
                particle.style.top = posY + "px";
                particle.style.width = size + "px";
                particle.style.height = size + "px";
                particle.style.opacity = opacity;
                
                // Add to container
                particlesContainer.appendChild(particle);
                
                // Animate particle
                animateParticle(particle);
            }
            
            function animateParticle(particle) {
                // Random duration
                const duration = Math.random() * 20000 + 10000; // 10-30 seconds
                
                // Random destination
                const destX = Math.random() * window.innerWidth;
                const destY = Math.random() * window.innerHeight;
                
                // Apply animation
                particle.animate(
                    [
                        { transform: "translate(0, 0)" },
                        { transform: `translate(${destX - parseFloat(particle.style.left)}px, ${destY - parseFloat(particle.style.top)}px)` }
                    ],
                    {
                        duration: duration,
                        easing: "linear",
                        fill: "forwards"
                    }
                ).onfinish = function() {
                    particle.remove();
                    createParticle();
                };
            }
            
            // If there's a success message, show login link prominently
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                const form = document.querySelector('form');
                form.style.display = 'none';
                
                const loginLink = document.createElement('a');
                loginLink.href = 'login.php';
                loginLink.className = 'btn';
                loginLink.innerHTML = '<i class="fas fa-sign-in-alt"></i> Go to Login';
                loginLink.style.marginTop = '20px';
                
                successMessage.insertAdjacentElement('afterend', loginLink);
            }
        });
    </script>
</body>
</html>