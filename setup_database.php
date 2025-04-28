<?php
require_once "config/database.php";

try {
    // Create database connection without database name
    $pdo = new PDO("mysql:host=localhost", "root", "1234");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read SQL file
    $sql = file_get_contents('database_setup.sql');
    
    // Execute SQL commands
    $pdo->exec($sql);
    
    echo "Database setup completed successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 