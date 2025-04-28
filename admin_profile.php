<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Check if user is admin
if (!isAdmin()) {
    header("Location: home.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = "";
$error = "";

// Get current admin data
$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email already exists (excluding current user)
        $query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists";
        } else {
            // Update admin profile
            $query = "UPDATE users SET username = :username, email = :email, phone = :phone WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":user_id", $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = "Profile updated successfully!";
                // Refresh admin data
                $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                $stmt->execute();
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update profile";
            }
        }
    }
}

// Handle profile image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_image']['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    // Validate file type
    if (!in_array(strtolower($ext), $allowed)) {
        $error = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
    } else {
        // Validate file size (max 5MB)
        if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
            $error = "File size too large. Maximum size is 5MB.";
        } else {
            // Generate unique filename
            $new_filename = 'admin_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/profile_images/' . $new_filename;
            
            // Delete old profile image if exists
            if (!empty($admin['profile_image']) && file_exists($admin['profile_image'])) {
                unlink($admin['profile_image']);
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Update database with new image path
                $query = "UPDATE users SET profile_image = :profile_image WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":profile_image", $upload_path);
                $stmt->bindParam(":user_id", $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = "Profile image updated successfully!";
                    // Refresh admin data
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
                    $stmt->bindParam(":user_id", $_SESSION['user_id']);
                    $stmt->execute();
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update profile image";
                }
            } else {
                $error = "Failed to upload image. Please try again.";
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
    <title>Admin Profile - ANPR System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #1a1a1a;
            --accent-color: #e74c3c;
            --text-color: #ffffff;
            --text-secondary: #b3b3b3;
            --light-bg: #121212;
            --card-bg: #1e1e1e;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
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
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--primary-color);
            transition: var(--transition);
        }
        
        .back-btn:hover {
            background-color: var(--primary-color);
            color: white;
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
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }
        
        .error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
            border-left: 3px solid var(--accent-color);
        }
        
        .profile-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-image {
            position: relative;
            width: 150px;
            height: 150px;
        }
        
        .profile-image img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
        
        .profile-image-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .profile-image-upload:hover {
            background-color: #2980b9;
            transform: scale(1.1);
        }
        
        .profile-info h2 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .profile-info p {
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 15px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: rgba(255, 255, 255, 0.08);
        }
        
        .btn {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Profile</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-section">
            <div class="profile-header">
                <div class="profile-image">
                    <img id="profile-preview" src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/150'; ?>" alt="Profile Image">
                    <label for="profile_image" class="profile-image-upload">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($admin['username']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin['phone']); ?></p>
                    <p><i class="fas fa-user-shield"></i> Admin</p>
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="file" id="profile_image" name="profile_image" class="hidden" accept="image/*">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($admin['phone']); ?>">
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Handle profile image upload and preview
        document.getElementById('profile_image').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                
                reader.readAsDataURL(file);
                
                // Submit form after file selection
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html> 