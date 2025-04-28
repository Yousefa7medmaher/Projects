<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Redirect admin users to dashboard
if (isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user information
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

// Get recent vehicle logs
$query = "SELECT vl.*, v.vehicle_id, p.letters, p.numbers, pz.name as zone_name 
          FROM vehicle_logs vl 
          JOIN vehicles v ON vl.vehicle_id = v.vehicle_id 
          JOIN plates p ON v.plate_id = p.plate_id 
          JOIN parking_zones pz ON vl.zone_id = pz.zone_id 
          WHERE v.vehicle_id IN (SELECT vehicle_id FROM user_vehicles WHERE user_id = :user_id) 
          ORDER BY vl.check_in DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - ANPR System</title>
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
        
        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .logo i {
            font-size: 24px;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .logo span {
            font-size: 20px;
            font-weight: 700;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-link {
            color: var(--text-color);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .nav-link.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: var(--primary-color);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            flex: 1;
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
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
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
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.5);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .icon-vehicles {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .icon-logs {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .icon-profile {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .vehicle-list {
            list-style: none;
        }
        
        .vehicle-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.05);
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .vehicle-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .vehicle-image {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            object-fit: cover;
        }
        
        .vehicle-info {
            flex: 1;
        }
        
        .vehicle-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .vehicle-status {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .log-list {
            list-style: none;
        }
        
        .log-item {
            padding: 12px;
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.05);
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .log-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .log-vehicle {
            font-weight: 600;
        }
        
        .log-time {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .log-zone {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .profile-info {
            margin-bottom: 15px;
        }
        
        .profile-field {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.05);
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .profile-field:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .field-label {
            color: var(--text-secondary);
        }
        
        .field-value {
            font-weight: 500;
        }
        
        .btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
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
            .dashboard-grid {
                grid-template-columns: 1fr;
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
            
            .navbar-container, .footer-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
            }
            
            .footer-links {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="home.php" class="logo">
                <i class="fas fa-camera"></i>
                <span>ANPR System</span>
            </a>
            <div class="nav-links">
                <a href="home.php" class="nav-link active">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?></h1>
            <div class="header-actions">
                <a href="profile.php" class="user-profile">
                    <img src="<?php echo $user['profile_image'] ?: 'https://via.placeholder.com/38/38'; ?>" alt="Profile">
                    <span>Profile</span>
                </a>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>My Vehicles</h2>
                    <div class="card-icon icon-vehicles">
                        <i class="fas fa-car"></i>
                    </div>
                </div>
                <?php if (count($vehicles) > 0): ?>
                    <ul class="vehicle-list">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <li class="vehicle-item">
                                <?php if ($vehicle['vehicle_image']): ?>
                                    <img src="<?php echo htmlspecialchars($vehicle['vehicle_image']); ?>" alt="Vehicle" class="vehicle-image">
                                <?php else: ?>
                                    <div class="vehicle-image" style="background-color: rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-car"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="vehicle-info">
                                    <div class="vehicle-number">
                                        <?php echo htmlspecialchars($vehicle['letters'] . ' ' . $vehicle['numbers']); ?>
                                    </div>
                                    <div class="vehicle-status">
                                        <?php echo $vehicle['status'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: var(--text-secondary);">No vehicles registered yet.</p>
                <?php endif; ?>
                <a href="profile.php#vehicle-section" class="btn">
                    <i class="fas fa-plus"></i> Add Vehicle
                </a>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Recent Activity</h2>
                    <div class="card-icon icon-logs">
                        <i class="fas fa-history"></i>
                    </div>
                </div>
                <?php if (count($recent_logs) > 0): ?>
                    <ul class="log-list">
                        <?php foreach ($recent_logs as $log): ?>
                            <li class="log-item">
                                <div class="log-header">
                                    <span class="log-vehicle">
                                        <?php echo htmlspecialchars($log['letters'] . ' ' . $log['numbers']); ?>
                                    </span>
                                    <span class="log-time">
                                        <?php echo date('M d, Y H:i', strtotime($log['check_in'])); ?>
                                    </span>
                                </div>
                                <div class="log-zone">
                                    <?php echo htmlspecialchars($log['zone_name']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: var(--text-secondary);">No recent activity.</p>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Profile Information</h2>
                    <div class="card-icon icon-profile">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="profile-info">
                    <div class="profile-field">
                        <span class="field-label">Username</span>
                        <span class="field-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="field-label">Email</span>
                        <span class="field-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="field-label">Phone</span>
                        <span class="field-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="field-label">Role</span>
                        <span class="field-value"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                </div>
                <a href="profile.php" class="btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-info">
                &copy; <?php echo date('Y'); ?> ANPR System | All Rights Reserved
            </div>
            <div class="footer-links">
                <a href="#" class="footer-link">Terms of Service</a>
                <a href="#" class="footer-link">Privacy Policy</a>
                <a href="#" class="footer-link">Contact Us</a>
                <a href="#" class="footer-link">Help Center</a>
            </div>
        </div>
    </footer>
</body>
</html>