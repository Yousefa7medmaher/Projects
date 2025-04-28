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

// Get user ID from URL
$user_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$user_id) {
    header("Location: users.php");
    exit();
}

// Get user and car information
$query = "SELECT u.*, c.car_letters, c.car_numbers, c.car_image 
          FROM users u 
          LEFT JOIN cars c ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: users.php");
    exit();
}

// Handle form submission
if (isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = trim($_POST['role']);
    $car_letters = trim($_POST['car_letters']);
    $car_numbers = trim($_POST['car_numbers']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else if (!preg_match('/^[\p{Arabic}]{2,3}$/u', $car_letters)) {
        $error = "Invalid car letters. Please enter 2-3 Arabic letters";
    } else if (!preg_match('/^[0-9]{4}$/', $car_numbers)) {
        $error = "Invalid car numbers. Please enter 4 numbers";
    } else {
        try {
            $db->beginTransaction();
            
            // Update user information
            $query = "UPDATE users SET 
                     username = :username,
                     email = :email,
                     phone = :phone,
                     role = :role
                     WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":role", $role);
            $stmt->bindParam(":user_id", $user_id);
            
            if ($stmt->execute()) {
                // Handle car image upload
                $car_image = $user['car_image']; // Keep existing image by default
                if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['car_image']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (!in_array(strtolower($ext), $allowed)) {
                        throw new Exception("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
                    }
                    
                    if ($_FILES['car_image']['size'] > 5 * 1024 * 1024) {
                        throw new Exception("File size too large. Maximum size is 5MB.");
                    }
                    
                    $new_filename = 'car_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = 'uploads/car_images/' . $new_filename;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists('uploads/car_images')) {
                        mkdir('uploads/car_images', 0777, true);
                    }
                    
                    // Delete old car image if exists
                    if (!empty($user['car_image']) && file_exists($user['car_image'])) {
                        unlink($user['car_image']);
                    }
                    
                    if (!move_uploaded_file($_FILES['car_image']['tmp_name'], $upload_path)) {
                        throw new Exception("Failed to upload car image");
                    }
                    
                    $car_image = $upload_path;
                }
                
                // Update or create car record
                $query = "SELECT id FROM cars WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Update existing car record
                    $query = "UPDATE cars SET 
                             car_letters = :car_letters,
                             car_numbers = :car_numbers,
                             car_image = :car_image
                             WHERE user_id = :user_id";
                } else {
                    // Create new car record
                    $query = "INSERT INTO cars (user_id, car_letters, car_numbers, car_image) 
                             VALUES (:user_id, :car_letters, :car_numbers, :car_image)";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":car_letters", $car_letters);
                $stmt->bindParam(":car_numbers", $car_numbers);
                $stmt->bindParam(":car_image", $car_image);
                
                if ($stmt->execute()) {
                    $db->commit();
                    $message = "User information updated successfully!";
                    // Refresh user data
                    $query = "SELECT u.*, c.car_letters, c.car_numbers, c.car_image 
                             FROM users u 
                             LEFT JOIN cars c ON u.id = c.user_id 
                             WHERE u.id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    throw new Exception("Failed to update car information");
                }
            } else {
                throw new Exception("Failed to update user information");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
            
            // Clean up uploaded file if it exists
            if (isset($upload_path) && file_exists($upload_path)) {
                unlink($upload_path);
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
    <title>Edit User - ANPR System</title>
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
        
        .edit-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
        
        .car-image-preview {
            width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            border: 3px solid var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit User</h1>
            <a href="users.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
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
        
        <div class="edit-section">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="section-header">
                    <h2>User Information</h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="section-header">
                    <h2>Car Information</h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="car_letters">Car Letters (Arabic)</label>
                        <input type="text" id="car_letters" name="car_letters" class="form-control" 
                               value="<?php echo htmlspecialchars($user['car_letters'] ?? ''); ?>" 
                               pattern="[\u0600-\u06FF]{2,3}"
                               title="Please enter 2-3 Arabic letters"
                               required>
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Enter 2-3 Arabic letters
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_numbers">Car Numbers</label>
                        <input type="text" id="car_numbers" name="car_numbers" class="form-control" 
                               value="<?php echo htmlspecialchars($user['car_numbers'] ?? ''); ?>" 
                               pattern="[0-9]{4}"
                               title="Please enter 4 numbers"
                               required>
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Enter 4 numbers
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Car Image</label>
                        <?php if ($user['car_image']): ?>
                            <img src="<?php echo htmlspecialchars($user['car_image']); ?>" 
                                 alt="Car Image" 
                                 class="car-image-preview"
                                 id="current-image">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/200x150" 
                                 alt="No Image" 
                                 class="car-image-preview"
                                 id="current-image">
                        <?php endif; ?>
                        
                        <label for="car_image">Update Car Image</label>
                        <input type="file" id="car_image" name="car_image" class="form-control" accept="image/*">
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Upload a new image (max 5MB)
                        </small>
                    </div>
                </div>
                
                <button type="submit" name="update_user" class="btn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Preview new image when selected
        document.getElementById('car_image').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('current-image').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html> 