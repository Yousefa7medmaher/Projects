-- Create database
CREATE DATABASE IF NOT EXISTS anpr_system;
USE anpr_system;

 
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255),
    profile_image VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    auth_type ENUM('local', 'google', 'facebook') DEFAULT 'local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

 
CREATE TABLE plates (
    plate_id INT AUTO_INCREMENT PRIMARY KEY,
    letters VARCHAR(10) NOT NULL,
    numbers VARCHAR(10) NOT NULL,
    UNIQUE(letters, numbers)
);

 
CREATE TABLE vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_id INT NOT NULL,
    vehicle_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plate_id) REFERENCES plates(plate_id) ON DELETE CASCADE
);

 
CREATE TABLE user_vehicles (
    user_id INT,
    vehicle_id INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, vehicle_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE
);

 
CREATE TABLE parking_zones (
    zone_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location_description TEXT
);

 
CREATE TABLE vehicle_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    check_in TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    check_out TIMESTAMP,
    check_in_image VARCHAR(255),
    check_out_image VARCHAR(255),
    zone_id INT,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES parking_zones(zone_id)
);

 
CREATE TABLE parking_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    entry_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    exit_time TIMESTAMP,
    total_fee DECIMAL(10, 2) DEFAULT 0.00,
    zone_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES parking_zones(zone_id)
);

 
CREATE TABLE system_config (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    parking_rate_per_hour DECIMAL(10, 2) DEFAULT 0.00,
    max_parking_duration_minutes INT DEFAULT 0,
    enable_email_notifications BOOLEAN DEFAULT FALSE,
    enable_sms_notifications BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

 
CREATE TABLE access_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20),
    recognized BOOLEAN,
    message TEXT,
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 