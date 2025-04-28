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

// Get statistics
$today = date('Y-m-d');
$stats = array();

// Total logins today
$query = "SELECT COUNT(*) as count FROM vehicle_logs WHERE DATE(check_in) = :today";
$stmt = $db->prepare($query);
$stmt->bindParam(":today", $today);
$stmt->execute();
$stats['total_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active logins
$query = "SELECT COUNT(*) as count FROM vehicle_logs WHERE check_out IS NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total unique vehicles today
$query = "SELECT COUNT(DISTINCT vehicle_id) as count 
          FROM vehicle_logs 
          WHERE DATE(check_in) = :today";
$stmt = $db->prepare($query);
$stmt->bindParam(":today", $today);
$stmt->execute();
$stats['unique_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total registered vehicles
$query = "SELECT COUNT(*) as count FROM vehicles";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_vehicles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent vehicle logs
$query = "SELECT vl.*, v.vehicle_id, p.letters, p.numbers, u.username 
          FROM vehicle_logs vl 
          JOIN vehicles v ON vl.vehicle_id = v.vehicle_id 
          JOIN plates p ON v.plate_id = p.plate_id 
          LEFT JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id 
          LEFT JOIN users u ON uv.user_id = u.user_id 
          ORDER BY vl.check_in DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parking zones
$query = "SELECT * FROM parking_zones";
$stmt = $db->prepare($query);
$stmt->execute();
$parking_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system configuration
$query = "SELECT * FROM system_config ORDER BY config_id DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$system_config = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ANPR System</title>
    <link rel="stylesheet" href="css/style.css">
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
            display: flex;
        }
        
        .dashboard-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
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
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
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
            color: var(--text-color);
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
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.4);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        
        .stat-icon i {
            font-size: 24px;
            color: var(--primary-color);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background-color: rgba(46, 204, 113, 0.1);
        }
        
        .stat-card:nth-child(2) .stat-icon i {
            color: var(--success-color);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background-color: rgba(241, 196, 15, 0.1);
        }
        
        .stat-card:nth-child(3) .stat-icon i {
            color: var(--warning-color);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background-color: rgba(155, 89, 182, 0.1);
        }
        
        .stat-card:nth-child(4) .stat-icon i {
            color: #9b59b6;
        }
        
        .stat-card h3 {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            margin: 8px 0 0;
            color: var(--text-color);
        }
        
        /* Recent Activity Section */
        .recent-activity {
            background-color: var(--card-bg);
            padding: 25px;
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
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
        }
        
        .status-completed {
            background-color: rgba(127, 140, 141, 0.1);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2 {
                display: none;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar-menu a i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-content {
                padding: 15px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px;
            }
            
            .responsive-hide {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards {
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
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/80'; ?>" alt="Admin Profile">
                <h2><?php echo htmlspecialchars($admin['username']); ?></h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i> <span>Dashboard</span></a></li>
                <li><a href="vehicle_logs.php"><i class="fas fa-car"></i> <span>Vehicle Logs</span></a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
                <li><a href="parking_zones.php"><i class="fas fa-parking"></i> <span>Parking Zones</span></a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li> 
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1>Dashboard</h1>
                <div class="date"><?php echo date('l, F j, Y'); ?></div>
                <div class="header-actions">
                    <a href="admin_profile.php" class="user-profile">
                        <img src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/38'; ?>" alt="Profile">
                        <span><?php echo htmlspecialchars($admin['username']); ?></span>
                    </a>
                    <a href="logout.php" class="logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div>
                        <h3>Total Logins Today</h3>
                        <div class="number"><?php echo $stats['total_logins']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div>
                        <h3>Active Vehicles</h3>
                        <div class="number"><?php echo $stats['active_logins']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-car-side"></i>
                    </div>
                    <div>
                        <h3>Unique Vehicles Today</h3>
                        <div class="number"><?php echo $stats['unique_vehicles']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <h3>Total Registered Vehicles</h3>
                        <div class="number"><?php echo $stats['total_vehicles']; ?></div>
                    </div>
                </div>
            </div>

            <div class="recent-activity">
                <div class="section-header">
                    <h2>Recent Vehicle Logs</h2>
                    <a href="vehicle_logs.php" class="view-all">View all <i class="fas fa-arrow-right"></i></a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Plate</th>
                            <th>User</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_logs) > 0): ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['letters'] . ' ' . $log['numbers']); ?></td>
                                    <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['check_in'])); ?></td>
                                    <td><?php echo $log['check_out'] ? date('Y-m-d H:i', strtotime($log['check_out'])) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $log['check_out'] ? 'completed' : 'active'; ?>">
                                            <?php echo $log['check_out'] ? 'Completed' : 'Active'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No recent logs</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Add current date to dashboard
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateString = today.toLocaleDateString('en-US', options);
            
            // Create date element
            const dateElement = document.createElement('div');
            dateElement.style.fontSize = '14px';
            dateElement.style.color = '#7f8c8d';
            dateElement.style.marginTop = '5px';
            dateElement.textContent = dateString;
            
            // Append to header
            document.querySelector('.header h1').appendChild(dateElement);
        });
    </script>
</body>
</html>