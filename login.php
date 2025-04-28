<?php
session_start();
require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    $query = "SELECT * FROM users WHERE email = :email AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Log the successful login
            $log_query = "INSERT INTO access_logs (plate_number, recognized, message) 
                         VALUES (:plate, :recognized, :message)";
            $log_stmt = $db->prepare($log_query);
            $plate = "USER_LOGIN";
            $recognized = true;
            $message = "User " . $user['username'] . " logged in successfully";
            $log_stmt->bindParam(":plate", $plate);
            $log_stmt->bindParam(":recognized", $recognized);
            $log_stmt->bindParam(":message", $message);
            $log_stmt->execute();
            
            if ($user['role'] == 'admin') {
                header("Location: dashboard.php");
            } else {
                header("Location: home.php");
            }
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found or account is inactive";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANPR System - Login</title>
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
        }
        
        .login-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
            position: relative;
        }
        
        .login-info {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
        }
        
        .login-branding {
            margin-bottom: 30px;
        }
        
        .login-branding h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .login-branding h1 i {
            margin-right: 15px;
            font-size: 38px;
            color: var(--accent-color);
        }
        
        .login-branding p {
            color: var(--text-secondary);
            font-size: 16px;
            line-height: 1.6;
        }
        
        .login-features {
            margin-top: 40px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .feature-icon i {
            color: var(--accent-color);
            font-size: 16px;
        }
        
        .feature-text h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .feature-text p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .login-form-container {
            padding: 60px;
            background-color: var(--card-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .login-form-header h2 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-form-header p {
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
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            height: 18px;
            width: 18px;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 3px;
            margin-right: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .checkbox-container:hover input ~ .checkmark {
            background-color: var(--input-focus);
        }
        
        .checkbox-container input:checked ~ .checkmark {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .checkmark:after {
            content: "";
            display: none;
        }
        
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .forgot-password {
            color: var(--accent-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .forgot-password:hover {
            color: var(--accent-hover);
            text-decoration: underline;
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
        }
        
        .btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .login-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .login-footer a:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .login-divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
        }
        
        .divider-line {
            flex: 1;
            height: 1px;
            background-color: var(--input-border);
        }
        
        .divider-text {
            padding: 0 15px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-branding,
        .login-features,
        .login-form-header,
        .form-group,
        .form-options,
        .btn,
        .login-footer {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .login-branding { animation-delay: 0.1s; }
        .login-features { animation-delay: 0.2s; }
        .login-form-header { animation-delay: 0.3s; }
        .form-group:nth-child(1) { animation-delay: 0.4s; }
        .form-group:nth-child(2) { animation-delay: 0.5s; }
        .form-options { animation-delay: 0.6s; }
        .btn { animation-delay: 0.7s; }
        .login-footer { animation-delay: 0.8s; }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .login-info {
                display: none;
            }
            
            .login-form-container {
                padding: 40px;
            }
        }
        
        @media (max-width: 576px) {
            .login-form-container {
                padding: 30px 20px;
            }
            
            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
    </style>
</head>
<body>
    <!-- Particle Background Animation -->
    <div class="particles" id="particles"></div>
    
    <div class="login-container">
        <div class="login-info">
            <div class="login-branding">
                <h1><i class="fas fa-car"></i> ANPR System</h1>
                <p>Advanced Automatic Number Plate Recognition System for efficient parking management and vehicle tracking.</p>
            </div>
            
            <div class="login-features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Secure Access</h3>
                        <p>End-to-end encryption and secure authentication protocols.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Real-time Monitoring</h3>
                        <p>Instant vehicle detection and processing capabilities.</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Analytics Dashboard</h3>
                        <p>Comprehensive reporting and data visualization tools.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-form-container">
            <div class="login-form-header">
                <h2>Welcome Back</h2>
                <p>Please enter your credentials to access your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" id="remember" name="remember">
                        <span class="checkmark"></span>
                        Remember me
                    </label>
                    
                    <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                
                <div class="login-divider">
                    <div class="divider-line"></div>
                    <div class="divider-text">or</div>
                    <div class="divider-line"></div>
                </div>
                
                <div class="login-footer">
                    Don't have an account? <a href="register.php">Create Account</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");
            
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
        });
    </script>
</body>
</html>