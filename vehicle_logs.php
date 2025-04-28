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

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$zone_id = isset($_GET['zone_id']) ? $_GET['zone_id'] : '';

// Build the query
$query = "SELECT vl.*, v.vehicle_id, p.letters, p.numbers, u.username, pz.name as zone_name 
          FROM vehicle_logs vl 
          JOIN vehicles v ON vl.vehicle_id = v.vehicle_id 
          JOIN plates p ON v.plate_id = p.plate_id 
          LEFT JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id 
          LEFT JOIN users u ON uv.user_id = u.user_id 
          LEFT JOIN parking_zones pz ON vl.zone_id = pz.zone_id 
          WHERE 1=1";

$params = array();

if ($status !== 'all') {
    if ($status === 'active') {
        $query .= " AND vl.check_out IS NULL";
    } else {
        $query .= " AND vl.check_out IS NOT NULL";
    }
}

if (!empty($search)) {
    $query .= " AND (p.letters LIKE :search OR p.numbers LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($date)) {
    $query .= " AND DATE(vl.check_in) = :date";
    $params[':date'] = $date;
}

if (!empty($zone_id)) {
    $query .= " AND vl.zone_id = :zone_id";
    $params[':zone_id'] = $zone_id;
}

$query .= " ORDER BY vl.check_in DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parking zones for filter
$query = "SELECT * FROM parking_zones";
$stmt = $db->prepare($query);
$stmt->execute();
$parking_zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = array();

// Total logins today
$query = "SELECT COUNT(*) as count FROM vehicle_logs WHERE DATE(check_in) = :date";
$stmt = $db->prepare($query);
$stmt->bindParam(":date", $date);
$stmt->execute();
$stats['total_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active logins
$query = "SELECT COUNT(*) as count FROM vehicle_logs WHERE check_out IS NULL";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Logs - ANPR System</title>
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
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
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

        /* Filter Section */
        .filter-section {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
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
        }

        .stat-card h3 {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Table Styles */
        .table-container {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow-x: auto;
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

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .status-badge.completed {
            background-color: rgba(127, 140, 141, 0.1);
            color: var(--text-secondary);
        }

        .btn {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
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
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 20px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 13px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .logout {
                width: 100%;
                justify-content: center;
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
            <li><a href="vehicle_logs.php" class="active"><i class="fas fa-car"></i> <span>Vehicle Logs</span></a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> <span>Users</span></a></li>
            <li><a href="parking_zones.php"><i class="fas fa-parking"></i> <span>Parking Zones</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li> 
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Vehicle Logs</h1>
            <div class="header-actions">
                <div class="date"><?php echo date('l, F j, Y'); ?></div>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="zone_id">Parking Zone</label>
                    <select name="zone_id" id="zone_id">
                        <option value="">All Zones</option>
                        <?php foreach ($parking_zones as $zone): ?>
                            <option value="<?php echo $zone['zone_id']; ?>" 
                                    <?php echo $zone_id == $zone['zone_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($zone['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo $date; ?>">
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search by plate or username" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </div>
        
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Logs Today</h3>
                <div class="stat-number"><?php echo $stats['total_logins']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Vehicles</h3>
                <div class="stat-number"><?php echo $stats['active_logins']; ?></div>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Plate</th>
                        <th>User</th>
                        <th>Zone</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['letters'] . ' ' . $log['numbers']); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($log['zone_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($log['check_in'])); ?></td>
                                <td><?php echo $log['check_out'] ? date('Y-m-d H:i', strtotime($log['check_out'])) : '-'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $log['check_out'] ? 'completed' : 'active'; ?>">
                                        <?php echo $log['check_out'] ? 'Completed' : 'Active'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <?php if (!$log['check_out']): ?>
                                            <form method="POST" action="process_checkout.php" style="display: inline;">
                                                <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-sign-out-alt"></i> Check Out
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="view_log.php?id=<?php echo $log['log_id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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