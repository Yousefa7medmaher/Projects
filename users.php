<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Check if user is admin
if (!isAdmin()) {
    header("Location: home.php");
    exit();
}

// Get admin info
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$message = "";
$error = "";

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];

    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account";
    } else {
        try {
            $db->beginTransaction();

            // Get all vehicles for the user
            $query = "SELECT v.vehicle_id, v.plate_id
                      FROM vehicles v
                      INNER JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id
                      WHERE uv.user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($vehicles as $vehicle) {
                $vehicle_id = $vehicle['vehicle_id'];
                $plate_id = $vehicle['plate_id'];

                // Delete vehicle logs and images
                $queryLogs = "SELECT check_in_image, check_out_image FROM vehicle_logs WHERE vehicle_id = :vehicle_id";
                $stmtLogs = $db->prepare($queryLogs);
                $stmtLogs->bindParam(":vehicle_id", $vehicle_id);
                $stmtLogs->execute();
                $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

                foreach ($logs as $log) {
                    if (!empty($log['check_in_image']) && file_exists($log['check_in_image'])) {
                        unlink($log['check_in_image']);
                    }
                    if (!empty($log['check_out_image']) && file_exists($log['check_out_image'])) {
                        unlink($log['check_out_image']);
                    }
                }

                $query = "DELETE FROM vehicle_logs WHERE vehicle_id = :vehicle_id";
                $stmtDelLogs = $db->prepare($query);
                $stmtDelLogs->bindParam(":vehicle_id", $vehicle_id);
                $stmtDelLogs->execute();

                // Delete vehicle image
                $query = "SELECT vehicle_image FROM vehicles WHERE vehicle_id = :vehicle_id";
                $stmtImg = $db->prepare($query);
                $stmtImg->bindParam(":vehicle_id", $vehicle_id);
                $stmtImg->execute();
                $vehicleData = $stmtImg->fetch(PDO::FETCH_ASSOC);

                if ($vehicleData && !empty($vehicleData['vehicle_image']) && file_exists($vehicleData['vehicle_image'])) {
                    unlink($vehicleData['vehicle_image']);
                }

                // Delete vehicle
                $query = "DELETE FROM vehicles WHERE vehicle_id = :vehicle_id";
                $stmtDelVehicle = $db->prepare($query);
                $stmtDelVehicle->bindParam(":vehicle_id", $vehicle_id);
                $stmtDelVehicle->execute();

                // Delete plate (optional if plates are not shared)
                $query = "DELETE FROM plates WHERE plate_id = :plate_id";
                $stmtDelPlate = $db->prepare($query);
                $stmtDelPlate->bindParam(":plate_id", $plate_id);
                $stmtDelPlate->execute();
            }

            // Delete user's vehicle associations
            $query = "DELETE FROM user_vehicles WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            // Delete user
            $query = "DELETE FROM users WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);

            if ($stmt->execute()) {
                $db->commit();
                $message = "User and all related data deleted successfully!";
            } else {
                throw new Exception("Failed to delete user");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Handle new user creation
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $letters = trim($_POST['car_letters']);
    $numbers = trim($_POST['car_numbers']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else if (!preg_match('/^[\p{Arabic}]{2,3}$/u', $letters)) {
        $error = "Invalid car letters. Please enter 2-3 Arabic letters.";
    } else if (!preg_match('/^[\x{0660}-\x{0669}]{4}$/u', $numbers)) {
        $error = "Invalid car numbers. Please enter 4 Arabic numbers.";
    } else {
        // Check if email already exists
        $query = "SELECT user_id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = "Email already exists.";
        } else {
            try {
                $db->beginTransaction();

                // Create new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, password_hash, phone, role, status, auth_type)
                          VALUES (:username, :email, :password_hash, :phone, :role, 'active', 'local')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password_hash", $password_hash);
                $stmt->bindParam(":phone", $phone);
                $stmt->bindParam(":role", $role);

                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();

                    // Check if plate already exists
                    $query = "SELECT plate_id FROM plates WHERE letters = :letters AND numbers = :numbers";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":letters", $letters);
                    $stmt->bindParam(":numbers", $numbers);
                    $stmt->execute();

                    if ($stmt->rowCount() > 0) {
                        $plate = $stmt->fetch(PDO::FETCH_ASSOC);
                        $plate_id = $plate['plate_id'];
                    } else {
                        // Create new plate
                        $query = "INSERT INTO plates (letters, numbers) VALUES (:letters, :numbers)";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":letters", $letters);
                        $stmt->bindParam(":numbers", $numbers);
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
                    $stmt->bindParam(":user_id", $user_id);
                    $stmt->bindParam(":vehicle_id", $vehicle_id);
                    $stmt->execute();

                    // Handle vehicle image upload
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

                        $new_filename = 'vehicle_' . $vehicle_id . '_' . time() . '.' . $ext;
                        $upload_path = 'uploads/vehicle_images/' . $new_filename;

                        // Create directory if it doesn't exist
                        if (!file_exists('uploads/vehicle_images')) {
                            mkdir('uploads/vehicle_images', 0777, true);
                        }

                        if (!move_uploaded_file($_FILES['car_image']['tmp_name'], $upload_path)) {
                            throw new Exception("Failed to upload vehicle image");
                        }

                        // Update vehicle with image
                        $query = "UPDATE vehicles SET vehicle_image = :vehicle_image WHERE vehicle_id = :vehicle_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":vehicle_image", $upload_path);
                        $stmt->bindParam(":vehicle_id", $vehicle_id);
                        $stmt->execute();
                    }

                    $db->commit();
                    $message = "User and vehicle information added successfully!";
                } else {
                    throw new Exception("Failed to create user");
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
}

// Get all users with their vehicle information
$query = "SELECT u.*, v.vehicle_id, p.letters, p.numbers, v.vehicle_image, u.created_at 
          FROM users u 
          LEFT JOIN user_vehicles uv ON u.user_id = uv.user_id 
          LEFT JOIN vehicles v ON uv.vehicle_id = v.vehicle_id 
          LEFT JOIN plates p ON v.plate_id = p.plate_id 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - ANPR System</title>
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
            --sidebar-width: 250px;
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

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--secondary-color);
            color: var(--text-color);
            padding: 20px 0;
            width: 280px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 25px 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 25px;
            font-size: 15px;
            border-left: 3px solid transparent;
            transition: var(--transition);
        }

        .sidebar-menu a i {
            margin-right: 10px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            border-left: 3px solid var(--primary-color);
        }

        .sidebar-menu a.active {
            background-color: rgba(52, 152, 219, 0.1);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 20px 30px;
            margin-left: 280px;
            min-height: 100vh;
            background-color: var(--light-bg);
            transition: margin 0.3s ease;
        }

        .container {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            max-width: calc(100% - var(--sidebar-width));
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            flex: 1;
            min-width: 200px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
            white-space: nowrap;
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
            white-space: nowrap;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .add-user-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--accent-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        th {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        td {
            font-size: 14px;
            color: var(--text-color);
        }
        
        tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-admin {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .role-user {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: rgba(127, 140, 141, 0.1);
            color: var(--text-secondary);
        }
        
        .car-image-preview {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .car-image-preview:hover {
            transform: scale(1.1);
        }
        
        .car-number {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .no-car, .no-image {
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }
        
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--accent-color);
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            margin-right: 15px;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .header-actions {
                justify-content: space-between;
                width: 100%;
            }
            
            .add-user-section {
                padding: 20px;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 20px;
            }
            
            .section-header h2 {
                font-size: 18px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .role-badge, .status-badge, .car-number {
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .car-image-preview {
                width: 40px;
                height: 40px;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .logout {
                width: 100%;
                justify-content: center;
            }
            
            .add-user-section {
                padding: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .form-control {
                padding: 10px;
            }
            
            th, td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .car-image-preview {
                width: 30px;
                height: 30px;
            }
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .menu-toggle, .logout, .btn-danger {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            body {
                background-color: white;
                color: black;
            }
            
            table {
                background-color: white;
                color: black;
            }
            
            th, td {
                border-color: #ddd;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/80'; ?>" alt="Admin Profile">
            <h2><?php echo htmlspecialchars($admin['username']); ?></h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> <span>Dashboard</span></a></li>
            <li><a href="vehicle_logs.php"><i class="fas fa-car"></i> <span>Vehicle Logs</span></a></li>
            <li><a href="users.php" class="active"><i class="fas fa-users"></i> <span>Users</span></a></li>
            <li><a href="parking_zones.php"><i class="fas fa-parking"></i> <span>Parking Zones</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li> 
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Users Management</h1>
            <div class="header-actions">
                <a href="admin_profile.php" class="user-profile">
                    <img src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/38/38'; ?>" alt="Admin">
                    <span>Admin</span>
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
        
        <div class="add-user-section">
            <div class="section-header">
                <h2>Add New User</h2>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="section-header" style="margin-top: 20px;">
                    <h2>Car Information</h2>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="car_letters">Car Letters (Arabic)</label>
                        <input type="text" id="car_letters" name="car_letters" class="form-control" 
                               pattern="[\p{Arabic}]{2,3}"
                               title="Please enter 2-3 Arabic letters"
                               required>
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Enter 2-3 Arabic letters (e.g., هص)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_numbers">Car Numbers</label>
                        <input type="text" id="car_numbers" name="car_numbers" class="form-control" 
                               pattern="[\x{0660}-\x{0669}]{4}"
                               title="Please enter 4 Arabic numbers"
                               required>
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Enter 4 Arabic numbers (e.g., ٩٧٤١)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_image">Car Image</label>
                        <input type="file" id="car_image" name="car_image" class="form-control" accept="image/*">
                        <small class="form-text" style="color: var(--text-secondary); margin-top: 5px;">
                            Upload a clear image of the car (max 5MB)
                        </small>
                    </div>
                </div>
                
                <button type="submit" name="add_user" class="btn">
                    <i class="fas fa-user-plus"></i> Add User with Car Information
                </button>
            </form>
        </div>
        
        <div class="users-table">
            <div class="section-header">
                <h2>All Users</h2>
            </div>
            
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Car Letters</th>
                            <th>Car Numbers</th>
                            <th>Car Image</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <?php if ($user['letters']): ?>
                                        <span class="car-number"><?php echo htmlspecialchars($user['letters']); ?></span>
                                    <?php else: ?>
                                        <span class="no-car">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['numbers']): ?>
                                        <span class="car-number"><?php echo htmlspecialchars($user['numbers']); ?></span>
                                    <?php else: ?>
                                        <span class="no-car">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['vehicle_image']): ?>
                                        <img src="<?php echo htmlspecialchars($user['vehicle_image']); ?>" 
                                             alt="Car Image"
                                             class="car-image-preview"
                                             onclick="showCarImage('<?php echo htmlspecialchars($user['vehicle_image']); ?>')">
                                    <?php else: ?>
                                        <span class="no-image">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Car Image Modal -->
    <div id="carImageModal" class="modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script>
        // Mobile menu toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Modal functionality
        const modal = document.getElementById('carImageModal');
        const modalImg = document.getElementById('modalImage');
        const closeBtn = document.getElementsByClassName('close')[0];
        
        function showCarImage(src) {
            modal.style.display = 'block';
            modalImg.src = src;
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnMenuToggle = menuToggle.contains(event.target);
            
            if (!isClickInsideSidebar && !isClickOnMenuToggle && window.innerWidth <= 992) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>