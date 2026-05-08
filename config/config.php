<?php

/**
 * config/config.php
 * Core configuration file for LuminaCare.
 * Includes database connection, session security, and global constants.
 */

// ---------------------------------------------------------
// 1. Application Constants
// ---------------------------------------------------------
define('APP_NAME', 'LuminaCare');
// Change this to your live domain when hosting (e.g., https://yourdomain.com/)
define('BASE_URL', 'http://localhost/MARTERNITY_PORTAL/');

// Set default timezone (Crucial for logging accurate vitals and messages)
date_default_timezone_set('Africa/Lagos');

// ---------------------------------------------------------
// 2. Database Credentials
// ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Replace with your DB username in production
define('DB_PASS', '');          // Replace with your DB password in production
define('DB_NAME', 'luminacare_db');

// ---------------------------------------------------------
// 3. Session Security Configuration
// ---------------------------------------------------------
// Prevent JavaScript from accessing session cookies (XSS protection)
ini_set('session.cookie_httponly', 1);
// Ensure sessions are only using cookies
ini_set('session.use_only_cookies', 1);
// NOTE: Change to 1 in production if you have an SSL (HTTPS) certificate
ini_set('session.cookie_secure', 0);

// Start the session safely if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------
// 4. Secure Database Connection (PDO)
// ---------------------------------------------------------
try {
    // Data Source Name (includes charset utf8mb4 for full unicode support, like emojis in chat)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    // PDO Security & Fetch Options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch data as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // True prepared statements for security
    ];

    // Create the PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Catch connection errors safely. 
    // In production, log the actual error ($e->getMessage()) to a file, don't show it to the user.
    error_log($e->getMessage());
    die("<h3>System Error</h3><p>We are currently unable to connect to the database. Please try again later or contact the system administrator.</p>");
}
