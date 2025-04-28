<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = "";
$error = "";

// Helper function to validate Arabic text
function isArabicText($text) {
    return preg_match('/^[\x{0600}-\x{06FF}]{2,3}$/u', $text);
}

function normalizeArabicText($text) {
    // Remove any diacritics and normalize Arabic characters
    $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
    $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    $text = str_replace(['ة'], 'ه', $text);
    $text = str_replace(['ى'], 'ي', $text);
    return trim($text);
}

// Function to convert English numbers to Arabic numbers
function convertToArabicNumbers($str) {
    $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    return str_replace($western, $arabic, $str);
}

// Function to check if string contains only numbers
function containsOnlyNumbers($str) {
    return preg_match('/^[0-9]+$/', $str);
}

// Get current user data
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's vehicles
$query = "SELECT v.*, p.letters, p.numbers 
          FROM vehicles v 
          JOIN plates p ON v.plate_id = p.plate_id 
          JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id 
          WHERE uv.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if (isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    try {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Email already exists");
        }
        
        // Start transaction
        $db->beginTransaction();
        
        // Update user data
        $query = "UPDATE users SET email = :email, phone = :phone WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Only JPG, PNG, and GIF images are allowed for profile");
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception("Profile image size should not exceed 5MB");
            }
            
            $upload_dir = 'uploads/profile_images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old image if exists
                if ($user['profile_image'] && file_exists($user['profile_image'])) {
                    unlink($user['profile_image']);
                }
                
                // Update profile image in database
                $query = "UPDATE users SET profile_image = :profile_image WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":profile_image", $target_path);
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                $stmt->execute();
            } else {
                throw new Exception("Failed to upload profile image");
            }
        }
        
        // Commit transaction
        $db->commit();
        
        $message = "Profile updated successfully!";
        
        // Refresh user data
        $query = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Handle vehicle addition
if (isset($_POST['add_vehicle'])) {
    $letters = normalizeArabicText(trim($_POST['letters']));
    $numbers = trim($_POST['numbers']);
    
    try {
        // Validate plate format
        if (!isArabicText($letters)) {
            throw new Exception("يجب إدخال حرفين أو ثلاثة أحرف عربية فقط");
        }
        
        // Validate numbers (accept both English and Arabic numbers)
        if (!containsOnlyNumbers($numbers) || strlen($numbers) > 4) {
            throw new Exception("يجب إدخال 4 أرقام كحد أقصى");
        }
        
        // Convert numbers to Arabic
        $arabicNumbers = convertToArabicNumbers($numbers);
        
        $db->beginTransaction();
        
        // Check if plate already exists
        $query = "SELECT plate_id FROM plates WHERE letters = :letters AND numbers = :numbers";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":letters", $letters);
        $stmt->bindParam(":numbers", $arabicNumbers);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $plate = $stmt->fetch(PDO::FETCH_ASSOC);
            $plate_id = $plate['plate_id'];
            
            // Check if this plate is already registered to any user
            $query = "SELECT uv.user_id FROM user_vehicles uv 
                     JOIN vehicles v ON uv.vehicle_id = v.vehicle_id 
                     WHERE v.plate_id = :plate_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":plate_id", $plate_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("رقم اللوحة مسجل مسبقاً");
            }
        } else {
            // Create new plate
            $query = "INSERT INTO plates (letters, numbers) VALUES (:letters, :numbers)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":letters", $letters);
            $stmt->bindParam(":numbers", $arabicNumbers);
            $stmt->execute();
            $plate_id = $db->lastInsertId();
        }
        
        // Create vehicle
        $query = "INSERT INTO vehicles (plate_id) VALUES (:plate_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":plate_id", $plate_id);
        $stmt->execute();
        $vehicle_id = $db->lastInsertId();
        
        // Link vehicle to user
        $query = "INSERT INTO user_vehicles (user_id, vehicle_id) VALUES (:user_id, :vehicle_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":vehicle_id", $vehicle_id);
        $stmt->execute();
        
        $db->commit();
        $message = "Vehicle added successfully!";
        
        // Refresh vehicles list
        $query = "SELECT v.*, p.letters, p.numbers 
                 FROM vehicles v 
                 JOIN plates p ON v.plate_id = p.plate_id 
                 JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id 
                 WHERE uv.user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - ANPR System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #1a1a1a;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --text-color: #ffffff;
            --text-secondary: #b3b3b3;
            --light-bg: #121212;
            --card-bg: #1e1e1e;
            --navbar-bg: #0a0a0a;
            --footer-bg: #0a0a0a;
            --border-radius: 10px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: var(--navbar-bg);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile img {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }

        .logout {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--accent-color);
            transition: var(--transition);
        }

        .logout:hover {
            background-color: var(--accent-color);
            color: white;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            flex: 1;
        }
        
        .profile-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .profile-header h2 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .success-message {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-gap: 20px;
        }
        
        .profile-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .profile-section h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #444;
            background-color: #2c2c2c;
            color: var(--text-color);
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-group input:disabled {
            background-color: #252525;
            cursor: not-allowed;
        }
        
        .profile-image-preview {
            max-width: 100px;
            margin-top: 10px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .vehicles-list {
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        
        table th {
            background-color: #2c2c2c;
            color: var(--primary-color);
        }
        
        table tr:hover {
            background-color: #252525;
        }

        .footer {
            background-color: var(--footer-bg);
            padding: 20px 0;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }
        
        .footer-info {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .footer-links {
            display: flex;
            gap: 15px;
        }
        
        .footer-link {
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            font-size: 14px;
        }
        
        .footer-link:hover {
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
            
            .navbar-container, .footer-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
            }
        }
        
        .help-text {
            color: var(--text-secondary);
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        input[dir="rtl"],
        input[type="text"][name="letters"],
        input[type="text"][name="numbers"] {
            text-align: right;
            direction: rtl;
            font-size: 16px;
            padding: 10px 15px;
        }
        
        .form-group label {
            text-align: right;
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
        }
        
        [dir="rtl"] .form-group {
            text-align: right;
        }
        
        .arabic-numbers-preview {
            color: var(--primary-color);
            font-size: 18px;
            margin-top: 5px;
            text-align: right;
            direction: rtl;
            min-height: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="nav-links">
                <a href="home.php" class="nav-link">Home</a>
                <a href="profile.php" class="nav-link">Profile</a>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                    <a href="admin_users.php" class="nav-link">إدارة المستخدمين</a>
                <?php endif; ?>
            </div>
            <div class="user-profile">
                <?php if ($user['profile_image']): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                <?php endif; ?>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        <div class="profile-header">
            <h2>User Profile</h2>
            <?php if ($message): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="profile-content">
            <div class="profile-section">
                <h3>Personal Information</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile_image">Profile Image</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="profile-image-preview">
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                </form>
            </div>
            
            <div class="profile-section">
                <h3>My Vehicles</h3>
                <form method="POST" action="" id="vehicleForm" dir="rtl">
                    <div class="form-group">
                        <label for="letters">حروف اللوحة (عربي)</label>
                        <input type="text" 
                               id="letters" 
                               name="letters" 
                               required 
                               pattern="[\u0600-\u06FF]{2,3}"
                               placeholder="مثال: بيس"
                               style="text-align: right; direction: rtl;"
                               maxlength="3">
                        <small class="help-text">أدخل 2-3 حروف عربية</small>
                    </div>
                    <div class="form-group">
                        <label for="numbers">أرقام اللوحة</label>
                        <input type="text" 
                               id="numbers" 
                               name="numbers" 
                               required 
                               pattern="[0-9]{1,4}"
                               placeholder="مثال: 1 إلى 1234"
                               maxlength="4">
                        <small class="help-text">أدخل من 1 إلى 4 أرقام</small>
                    </div>
                    <button type="submit" name="add_vehicle" class="btn-primary">إضافة مركبة</button>
                </form>
                
                <div class="vehicles-list">
                    <?php if ($vehicles): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="text-align: right;">رقم اللوحة</th>
                                    <th style="text-align: right;">تاريخ الإضافة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <tr>
                                        <td style="text-align: right; direction: rtl;">
                                            <?php echo htmlspecialchars($vehicle['letters'] . ' ' . $vehicle['numbers']); ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php echo date('Y/m/d', strtotime($vehicle['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: right;">لا توجد مركبات مسجلة.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-info">
                © 2024 ANPR System. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="privacy.php" class="footer-link">Privacy Policy</a>
                <a href="terms.php" class="footer-link">Terms of Service</a>
                <a href="contact.php" class="footer-link">Contact Us</a>
            </div>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('vehicleForm');
        const lettersInput = document.getElementById('letters');
        const numbersInput = document.getElementById('numbers');
        const numbersDisplay = document.createElement('div');
        
        // Add Arabic numbers display
        numbersDisplay.className = 'arabic-numbers-preview';
        numbersInput.parentNode.insertBefore(numbersDisplay, numbersInput.nextSibling);
        
        // Function to convert to Arabic numbers for display
        function convertToArabicNumbers(str) {
            const western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            const arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            return str.split('').map(char => {
                const index = western.indexOf(char);
                return index !== -1 ? arabic[index] : char;
            }).join('');
        }
        
        // Validate Arabic letters input
        lettersInput.addEventListener('input', function(e) {
            let value = e.target.value;
            // Remove any non-Arabic characters
            value = value.replace(/[^\u0600-\u06FF]/g, '');
            // Limit to 3 characters
            value = value.slice(0, 3);
            e.target.value = value;
        });
        
        // Validate numbers input and show Arabic preview
        numbersInput.addEventListener('input', function(e) {
            let value = e.target.value;
            // Remove any non-numeric characters
            value = value.replace(/[^0-9]/g, '');
            // Limit to 4 digits
            value = value.slice(0, 4);
            e.target.value = value;
            
            // Update Arabic numbers preview
            numbersDisplay.textContent = value ? convertToArabicNumbers(value) : '';
        });
        
        form.addEventListener('submit', function(e) {
            const letters = lettersInput.value;
            const numbers = numbersInput.value;
            
            if (letters.length < 2 || letters.length > 3) {
                e.preventDefault();
                alert('يجب إدخال حرفين أو ثلاثة أحرف عربية فقط');
                return;
            }
            
            if (numbers.length === 0 || numbers.length > 4) {
                e.preventDefault();
                alert('يجب إدخال 4 أرقام كحد أقصى');
                return;
            }
        });
    });
    </script>
</body>
</html>