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
    if (isset($_POST['add_zone'])) {
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        
        try {
            $query = "INSERT INTO parking_zones (name, location_description) VALUES (:name, :location)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":location", $location);
            
            if ($stmt->execute()) {
                $message = "Parking zone added successfully!";
            } else {
                throw new Exception("Failed to add parking zone");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['edit_zone'])) {
        $zone_id = $_POST['zone_id'];
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        
        try {
            $query = "UPDATE parking_zones SET name = :name, location_description = :location WHERE zone_id = :zone_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":location", $location);
            $stmt->bindParam(":zone_id", $zone_id);
            
            if ($stmt->execute()) {
                $message = "Parking zone updated successfully!";
            } else {
                throw new Exception("Failed to update parking zone");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['delete_zone'])) {
        $zone_id = $_POST['zone_id'];
        
        try {
            // Check if zone is in use
            $query = "SELECT COUNT(*) as count FROM vehicle_logs WHERE zone_id = :zone_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":zone_id", $zone_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                throw new Exception("Cannot delete zone that has active logs");
            }
            
            $query = "DELETE FROM parking_zones WHERE zone_id = :zone_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":zone_id", $zone_id);
            
            if ($stmt->execute()) {
                $message = "Parking zone deleted successfully!";
            } else {
                throw new Exception("Failed to delete parking zone");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all parking zones
$query = "SELECT * FROM parking_zones ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get zone statistics
$query = "SELECT pz.zone_id, pz.name, 
          COUNT(vl.log_id) as total_logs,
          COUNT(CASE WHEN vl.check_out IS NULL THEN 1 END) as active_logs
          FROM parking_zones pz
          LEFT JOIN vehicle_logs vl ON pz.zone_id = vl.zone_id
          GROUP BY pz.zone_id, pz.name";
$stmt = $db->prepare($query);
$stmt->execute();
$zone_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Zones - ANPR System</title>
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

        .container {
            flex: 1;
            max-width: 100%;
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

        .zones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .zone-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .zone-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .zone-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .zone-actions {
            display: flex;
            gap: 10px;
        }
        
        .zone-action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            transition: var(--transition);
        }
        
        .zone-action-btn:hover {
            color: var(--primary-color);
        }
        
        .zone-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            background-color: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .zone-capacity-bar {
            width: 100%;
            height: 6px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-top: 15px;
            overflow: hidden;
        }
        
        .capacity-fill {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        
        .add-zone-card {
            border: 2px dashed rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            min-height: 200px;
        }
        
        .add-zone-card:hover {
            border-color: var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .add-zone-icon {
            font-size: 32px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .add-zone-text {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 20px;
            padding: 5px;
        }
        
        .modal-close:hover {
            color: var(--danger-color);
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
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
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
            
            .zones-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 20px;
            }
            
            .zone-name {
                font-size: 16px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .zone-stats {
                grid-template-columns: 1fr;
                gap: 10px;
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
            
            .zones-grid {
                grid-template-columns: 1fr;
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
            <li><a href="parking_zones.php" class="active"><i class="fas fa-parking"></i> <span>Parking Zones</span></a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li> 
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1>Parking Zones</h1>
                <div class="header-actions">
                    <a href="admin_profile.php" class="user-profile">
                        <img src="<?php echo $admin['profile_image'] ?: 'https://via.placeholder.com/38/38'; ?>" alt="Admin">
                        <span>Admin</span>
                    </a>
                    <button class="btn" onclick="openAddZoneModal()" style="background-color: var(--primary-color); color: white; padding: 10px 15px; border-radius: var(--border-radius); border: none; cursor: pointer;">
                        <i class="fas fa-plus"></i> Add Zone
                    </button>
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
            
            <div class="zones-grid">
                <?php foreach ($zones as $zone): 
                    // Find the stats for this zone
                    $zone_stat = array_filter($zone_stats, function($stat) use ($zone) {
                        return $stat['zone_id'] == $zone['zone_id'];
                    });
                    $zone_stat = reset($zone_stat);
                    $total_logs = $zone_stat ? $zone_stat['total_logs'] : 0;
                    $active_logs = $zone_stat ? $zone_stat['active_logs'] : 0;
                    $occupancy_rate = $total_logs > 0 ? ($active_logs / $total_logs) * 100 : 0;
                    $status_color = $occupancy_rate >= 90 ? 'var(--accent-color)' : 
                                  ($occupancy_rate >= 70 ? 'var(--warning-color)' : 'var(--success-color)');
                ?>
                    <div class="zone-card">
                        <div class="zone-header">
                            <h3 class="zone-name"><?php echo htmlspecialchars($zone['name']); ?></h3>
                            <div class="zone-actions">
                                <button class="zone-action-btn" onclick="editZone(<?php echo $zone['zone_id']; ?>, '<?php echo htmlspecialchars($zone['name']); ?>', '<?php echo htmlspecialchars($zone['location_description']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="zone-action-btn" onclick="deleteZone(<?php echo $zone['zone_id']; ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="zone-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $total_logs; ?></div>
                                <div class="stat-label">Total Logs</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $active_logs; ?></div>
                                <div class="stat-label">Active Logs</div>
                            </div>
                        </div>
                        
                        <div class="zone-capacity-bar">
                            <div class="capacity-fill" style="width: <?php echo $occupancy_rate; ?>%; background-color: <?php echo $status_color; ?>"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="zone-card add-zone-card" onclick="openAddZoneModal()">
                    <i class="fas fa-plus-circle add-zone-icon"></i>
                    <span class="add-zone-text">Add New Zone</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Zone Modal -->
    <div class="modal" id="zoneModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New Zone</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="zoneForm" method="POST" action="">
                <input type="hidden" name="zone_id" id="zoneId">
                <input type="hidden" name="<?php echo isset($_POST['edit_zone']) ? 'edit_zone' : 'add_zone'; ?>" value="1">
                
                <div class="form-group">
                    <label for="zoneName">Zone Name</label>
                    <input type="text" class="form-control" id="zoneName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="zoneDescription">Location Description</label>
                    <textarea class="form-control" id="zoneDescription" name="location" rows="3" required></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()" style="background-color: var(--text-secondary); color: white; padding: 10px 15px; border-radius: var(--border-radius); border: none; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn" style="background-color: var(--primary-color); color: white; padding: 10px 15px; border-radius: var(--border-radius); border: none; cursor: pointer;">Save Zone</button>
                </div>
            </form>
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

        function openAddZoneModal() {
            document.getElementById('modalTitle').textContent = 'Add New Zone';
            document.getElementById('zoneForm').reset();
            document.getElementById('zoneId').value = '';
            document.getElementById('zoneForm').action = '';
            document.querySelector('input[name="add_zone"]').name = 'add_zone';
            document.querySelector('input[name="edit_zone"]')?.remove();
            document.getElementById('zoneModal').classList.add('active');
        }
        
        function editZone(zoneId, name, description) {
            document.getElementById('modalTitle').textContent = 'Edit Zone';
            document.getElementById('zoneId').value = zoneId;
            document.getElementById('zoneName').value = name;
            document.getElementById('zoneDescription').value = description;
            document.getElementById('zoneForm').action = '';
            document.querySelector('input[name="add_zone"]')?.remove();
            const editInput = document.createElement('input');
            editInput.type = 'hidden';
            editInput.name = 'edit_zone';
            editInput.value = '1';
            document.getElementById('zoneForm').appendChild(editInput);
            document.getElementById('zoneModal').classList.add('active');
        }
        
        function deleteZone(zoneId) {
            if (confirm('Are you sure you want to delete this zone?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const zoneIdInput = document.createElement('input');
                zoneIdInput.type = 'hidden';
                zoneIdInput.name = 'zone_id';
                zoneIdInput.value = zoneId;
                form.appendChild(zoneIdInput);
                
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_zone';
                deleteInput.value = '1';
                form.appendChild(deleteInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('zoneModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.getElementById('zoneModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>