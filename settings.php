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

// Get admin data for profile icon
$query = "SELECT username, profile_image FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$message = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $parking_rate = floatval($_POST['parking_rate']);
    $max_duration = intval($_POST['max_duration']);
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    
    try {
        $query = "INSERT INTO system_config (
                    parking_rate_per_hour, 
                    max_parking_duration_minutes, 
                    enable_email_notifications, 
                    enable_sms_notifications
                ) VALUES (
                    :parking_rate, 
                    :max_duration, 
                    :email_notifications, 
                    :sms_notifications
                )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":parking_rate", $parking_rate);
        $stmt->bindParam(":max_duration", $max_duration);
        $stmt->bindParam(":email_notifications", $email_notifications);
        $stmt->bindParam(":sms_notifications", $sms_notifications);
        
        if ($stmt->execute()) {
            $message = "System settings updated successfully!";
        } else {
            throw new Exception("Failed to update system settings");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current system configuration
$query = "SELECT * FROM system_config ORDER BY config_id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Get system statistics
$stats = array();

// Total users
$query = "SELECT COUNT(*) as count FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total vehicles
$query = "SELECT COUNT(*) as count FROM vehicles";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total parking zones
$query = "SELECT COUNT(*) as count FROM parking_zones";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_zones'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total parking sessions
$query = "SELECT COUNT(*) as count FROM parking_sessions";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total revenue
$query = "SELECT SUM(total_fee) as total FROM parking_sessions";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ANPR System</title>
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
            --sidebar-width: 280px;
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
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--secondary-color);
            color: var(--text-color);
            padding: 20px 0;
            width: var(--sidebar-width);
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
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--light-bg);
            transition: margin 0.3s ease;
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
        
        .date {
            color: var(--text-secondary);
            font-size: 14px;
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
        
        .success-message {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }
        
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
            border-left: 3px solid var(--accent-color);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .content-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
        }
        
        .content-card h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--text-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            background-color: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: var(--border-radius);
        }
        
        .stat-item label {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .stat-item span {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
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
        
        .form-group input[type="number"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 15px;
            transition: var(--transition);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            background-color: rgba(255, 255, 255, 0.08);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }
        
        .btn-primary {
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
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .table-responsive {
            overflow-x: auto;
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-badge.success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .status-badge.error {
            background-color: rgba(231, 76, 60, 0.1);
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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 20px;
            }
            
            .content-card h3 {
                font-size: 16px;
            }
            
            .stat-item span {
                font-size: 16px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group input {
                padding: 10px;
            }
            
            .btn-primary {
                padding: 10px 15px;
                font-size: 14px;
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
            <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
            <li><a href="parking_zones.php"><i class="fas fa-parking"></i> <span>Parking Zones</span></a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> <span>Settings</span></a></li> 
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>System Settings</h1>
            <div class="date"><?php echo date('l, F j, Y'); ?></div>
        </div>
        
        <?php if ($message): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div class="content-card">
                <h3>System Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <label>Total Users</label>
                        <span><?php echo $stats['total_users']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Total Vehicles</label>
                        <span><?php echo $stats['total_vehicles']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Total Parking Zones</label>
                        <span><?php echo $stats['total_zones']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Total Parking Sessions</label>
                        <span><?php echo $stats['total_sessions']; ?></span>
                    </div>
                    <div class="stat-item">
                        <label>Total Revenue</label>
                        <span><?php echo number_format($stats['total_revenue'], 2); ?> egy</span>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>System Configuration</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="parking_rate">Parking Rate per Hour (egy)</label>
                        <input type="number" id="parking_rate" name="parking_rate" 
                               value="<?php echo $config['parking_rate_per_hour'] ?? 0; ?>" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_duration">Maximum Parking Duration (minutes)</label>
                        <input type="number" id="max_duration" name="max_duration" 
                               value="<?php echo $config['max_parking_duration_minutes'] ?? 0; ?>" 
                               min="0" required>
                        <small style="color: var(--text-secondary);">Set to 0 for no limit</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="email_notifications" 
                                   <?php echo ($config['enable_email_notifications'] ?? false) ? 'checked' : ''; ?>>
                            Enable Email Notifications
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="sms_notifications" 
                                   <?php echo ($config['enable_sms_notifications'] ?? false) ? 'checked' : ''; ?>>
                            Enable SMS Notifications
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
        
        <div class="content-card">
            <h3>System Logs</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Plate Number</th>
                            <th>Status</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM access_logs ORDER BY log_time DESC LIMIT 50";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($logs as $log):
                        ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['log_time'])); ?></td>
                                <td><?php echo htmlspecialchars($log['plate_number']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $log['recognized'] ? 'success' : 'error'; ?>">
                                        <?php echo $log['recognized'] ? 'Recognized' : 'Not Recognized'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile menu toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
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