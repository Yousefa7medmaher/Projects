<?php
require_once "includes/session_check.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $car_number = trim($_POST['car_number']);
    $user_id = $_SESSION['user_id'];
    
    // Check if car is already parked
    $query = "SELECT id FROM parking_records WHERE car_number = :car_number AND exit_time IS NULL";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":car_number", $car_number);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "This car is already parked";
    } else {
        // Create new parking record
        $query = "INSERT INTO parking_records (user_id, car_number) VALUES (:user_id, :car_number)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":car_number", $car_number);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Parking entry created successfully";
        } else {
            $_SESSION['error'] = "Failed to create parking entry";
        }
    }
    
    header("Location: home.php");
    exit();
}
?> 