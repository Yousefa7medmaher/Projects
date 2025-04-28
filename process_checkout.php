<?php
require_once "config/database.php";
require_once "includes/session_check.php";

// Check if user is admin
if (!isAdmin()) {
    header("Location: home.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $log_id = $_POST['log_id'];
    
    try {
        $db->beginTransaction();
        
        // Get the log entry
        $query = "SELECT * FROM vehicle_logs WHERE log_id = :log_id AND check_out IS NULL";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":log_id", $log_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update check out time
            $query = "UPDATE vehicle_logs SET check_out = CURRENT_TIMESTAMP WHERE log_id = :log_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":log_id", $log_id);
            $stmt->execute();
            
            // Calculate parking duration and fee
            $check_in = new DateTime($log['check_in']);
            $check_out = new DateTime();
            $duration = $check_in->diff($check_out);
            $hours = $duration->h + ($duration->days * 24);
            
            // Get parking rate
            $query = "SELECT parking_rate_per_hour FROM system_config ORDER BY config_id DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            $rate = $config['parking_rate_per_hour'];
            
            $fee = $hours * $rate;
            
            // Create parking session
            $query = "INSERT INTO parking_sessions (user_id, plate_number, entry_time, exit_time, total_fee, zone_id) 
                     SELECT uv.user_id, CONCAT(p.letters, ' ', p.numbers), vl.check_in, CURRENT_TIMESTAMP, :fee, vl.zone_id 
                     FROM vehicle_logs vl 
                     JOIN vehicles v ON vl.vehicle_id = v.vehicle_id 
                     JOIN plates p ON v.plate_id = p.plate_id 
                     LEFT JOIN user_vehicles uv ON v.vehicle_id = uv.vehicle_id 
                     WHERE vl.log_id = :log_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":fee", $fee);
            $stmt->bindParam(":log_id", $log_id);
            $stmt->execute();
            
            // Log the checkout
            $query = "INSERT INTO access_logs (plate_number, recognized, message) 
                     SELECT CONCAT(p.letters, ' ', p.numbers), true, 'Vehicle checked out' 
                     FROM vehicle_logs vl 
                     JOIN vehicles v ON vl.vehicle_id = v.vehicle_id 
                     JOIN plates p ON v.plate_id = p.plate_id 
                     WHERE vl.log_id = :log_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":log_id", $log_id);
            $stmt->execute();
            
            $db->commit();
            $_SESSION['success'] = "Vehicle checked out successfully. Fee: " . number_format($fee, 2) . " SAR";
        } else {
            throw new Exception("Invalid log entry or already checked out");
        }
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

header("Location: vehicle_logs.php");
exit();
?> 