<?php
require_once "config/database.php";
require_once "includes/session_check.php";

$database = new Database();
$db = $database->getConnection();

$message = "";
$error = "";

// Get user ID from URL or session
$user_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['user_id'];

// Get user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: dashboard.php");
    exit();
}

// Get user's cars
$query = "SELECT * FROM cars WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's login history
$query = "SELECT cl.*, c.car_letters, c.car_numbers 
          FROM car_logins cl 
          JOIN cars c ON cl.car_id = c.id 
          WHERE c.user_id = :user_id 
          ORDER BY cl.login_time DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - ANPR System</title>
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
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .back-btn:hover {
            color: var(--primary-color);
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        .profile-card {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 3px solid var(--primary-color);
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .info-list {
            list-style: none;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            color: var(--primary-color);
            font-size: 18px;
            width: 24px;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
        }
        
        .card {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table th {
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .car-image {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>User Profile</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="profile-grid">
            <div class="profile-card">
                <div class="profile-header">
                    <img src="<?php echo $user['profile_image'] ?: 'assets/default-profile.jpg'; ?>" 
                         alt="Profile Image" class="profile-image">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="profile-role"><?php echo ucfirst($user['role']); ?></p>
                </div>
                
                <ul class="info-list">
                    <li class="info-item">
                        <i class="fas fa-envelope info-icon"></i>
                        <div>
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </li>
                    <li class="info-item">
                        <i class="fas fa-phone info-icon"></i>
                        <div>
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['phone']); ?></div>
                        </div>
                    </li>
                    <li class="info-item">
                        <i class="fas fa-calendar info-icon"></i>
                        <div>
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></div>
                        </div>
                    </li>
                </ul>
            </div>
            
            <div>
                <div class="card">
                    <div class="card-header">
                        <h2>Registered Cars</h2>
                        <a href="add_car.php" class="btn">
                            <i class="fas fa-plus"></i> Add Car
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Car Number</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $car['car_image'] ?: 'assets/default-car.jpg'; ?>" 
                                                 alt="Car Image" class="car-image">
                                        </td>
                                        <td><?php echo htmlspecialchars($car['car_letters'] . '-' . $car['car_numbers']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($car['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            $query = "SELECT * FROM car_logins 
                                                     WHERE car_id = :car_id AND logout_time IS NULL";
                                            $stmt = $db->prepare($query);
                                            $stmt->bindParam(":car_id", $car['id']);
                                            $stmt->execute();
                                            $is_active = $stmt->rowCount() > 0;
                                            ?>
                                            <span class="status-badge <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                                                <i class="fas fa-circle"></i> 
                                                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Login History</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Car Number</th>
                                    <th>Login Time</th>
                                    <th>Logout Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($login_history as $login): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($login['car_letters'] . '-' . $login['car_numbers']); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($login['login_time'])); ?></td>
                                        <td>
                                            <?php 
                                            echo $login['logout_time'] 
                                                ? date('Y-m-d H:i:s', strtotime($login['logout_time'])) 
                                                : 'Still Active';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $login['logout_time'] ? 'status-inactive' : 'status-active'; ?>">
                                                <i class="fas fa-circle"></i> 
                                                <?php echo $login['logout_time'] ? 'Logged Out' : 'Active'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 