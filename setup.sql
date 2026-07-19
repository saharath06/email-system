CREATE DATABASE IF NOT EXISTS email_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE email_system;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com',
    smtp_port INT DEFAULT 587,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    auto_send TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS receivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    admin_id INT NOT NULL,
    pair VARCHAR(20) NOT NULL,
    trade_type ENUM('BUY','SELL') NOT NULL,
    entry_price DECIMAL(15,5) NOT NULL,
    stop_loss DECIMAL(15,5),
    take_profit1 DECIMAL(15,5),
    take_profit2 DECIMAL(15,5),
    take_profit3 DECIMAL(15,5),
    chart_image VARCHAR(500),
    notes TEXT,
    status ENUM('pending','approved','sent','rejected','failed') 
        DEFAULT 'pending',
    sent_to TEXT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) 
        REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) 
        REFERENCES admins(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','employee') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);