<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = "";
$error = "";

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // First, get all vehicles associated with the user
        $query = "SELECT v.vehicle_id, v.plate_id 
                 FROM user_vehicles uv 
                 JOIN vehicles v ON uv.vehicle_id = v.vehicle_id 
                 WHERE uv.user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Delete user_vehicles entries
        $query = "DELETE FROM user_vehicles WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        // Delete vehicles and their plates
        foreach ($vehicles as $vehicle) {
            // Delete vehicle
            $query = "DELETE FROM vehicles WHERE vehicle_id = :vehicle_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":vehicle_id", $vehicle['vehicle_id']);
            $stmt->execute();
            
            // Delete plate
            $query = "DELETE FROM plates WHERE plate_id = :plate_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":plate_id", $vehicle['plate_id']);
            $stmt->execute();
        }
        
        // Finally, delete the user
        $query = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        $message = "تم حذف المستخدم وجميع مركباته بنجاح";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "حدث خطأ أثناء حذف المستخدم: " . $e->getMessage();
    }
}

// Get all users except current admin
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM user_vehicles uv WHERE uv.user_id = u.user_id) as vehicle_count 
          FROM users u 
          WHERE u.user_id != :current_user_id 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":current_user_id", $_SESSION['user_id']);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - ANPR System</title>
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
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: right;
            border-bottom: 1px solid #333;
        }
        
        .users-table th {
            background-color: var(--navbar-bg);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .users-table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .btn-delete {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-delete:hover {
            background-color: #c0392b;
        }
        
        .success-message {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error-message {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--accent-color);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .vehicle-count {
            background-color: var(--primary-color);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .users-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>إدارة المستخدمين</h1>
            <?php if ($message): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th>اسم المستخدم</th>
                    <th>البريد الإلكتروني</th>
                    <th>رقم الهاتف</th>
                    <th>عدد المركبات</th>
                    <th>تاريخ التسجيل</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                        <td><span class="vehicle-count"><?php echo $user['vehicle_count']; ?></span></td>
                        <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                        <td>
                            <form method="POST" action="" onsubmit="return confirmDelete('<?php echo htmlspecialchars($user['username']); ?>')">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <button type="submit" name="delete_user" class="btn-delete">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function confirmDelete(username) {
        return confirm(`هل أنت متأكد من حذف المستخدم "${username}" وجميع مركباته؟`);
    }
    </script>
</body>
</html> 