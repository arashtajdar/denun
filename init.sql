-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS user_visits;

USE user_visits;

-- Create user_visits table
CREATE TABLE IF NOT EXISTS user_visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- IP Information
    ip_address VARCHAR(45) NOT NULL,
    real_ip VARCHAR(45) NOT NULL,
    
    -- VPN/Proxy Detection
    is_likely_vpn BOOLEAN DEFAULT FALSE,
    vpn_indicators TEXT,
    vpn_confidence ENUM('low', 'medium', 'high') DEFAULT 'low',
    
    -- Device Information
    user_agent TEXT,
    browser VARCHAR(50),
    operating_system VARCHAR(50),
    device_type VARCHAR(20),
    
    -- Request Information
    referrer TEXT,
    language VARCHAR(100),
    screen_width INT,
    screen_height INT,
    timezone VARCHAR(100),
    request_method VARCHAR(10),
    request_uri TEXT,
    protocol VARCHAR(10),
    port VARCHAR(10),
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for common queries
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address),
    INDEX idx_real_ip (real_ip),
    INDEX idx_is_likely_vpn (is_likely_vpn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
