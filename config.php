<?php
/**
 * HIT THE COURT - Main Configuration File
 * 
 * This file contains database settings, session management,
 * and global helper functions used across the application.
 */

// ============================================
// ERROR REPORTING (Development Mode)
// ============================================
// Turn off error display in production (set to 0)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'hit_the_court');
define('DB_USER', 'root');      // Change this in production
define('DB_PASS', '');          // Change this in production

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_NAME', 'Hit The Court');
// IMPORTANT: Change this to your actual domain/path
// Example: http://localhost/hit_the_court OR https://hitthecourt.com
define('SITE_URL', 'http://localhost/hit_the_court'); 

// File Upload Paths
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// THUNDER API
define('THUNDER_API_KEY', 'dab3a4df-3ef5-497c-aad7-753343644c2d');

// ============================================
// SESSION CONFIGURATION
// ============================================
// Secure session settings
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session ID
ini_set('session.use_only_cookies', 1); // Force session to use cookies
ini_set('session.cookie_secure', 0);   // Set to 1 if you have HTTPS (SSL)

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONNECTION (PDO)
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Log the error securely and show a generic message
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// ============================================
// SECURITY HELPER FUNCTIONS
// ============================================

/**
 * Generate a CSRF Token and store it in the session.
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF Token against the session token.
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// ============================================
// GENERAL HELPER FUNCTIONS
// ============================================

/**
 * Redirect to a specific URL relative to SITE_URL.
 * @param string $url
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

/**
 * Check if a user is logged in.
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if an admin is logged in.
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Force user login requirement.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/pages/login.php');
    }
}

/**
 * Force admin login requirement.
 */
function requireAdmin() {
    if (!isAdmin()) {
        redirect('/admin/login.php');
    }
}

/**
 * Sanitize user input.
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate a unique booking code.
 * @return string
 */
function generateBookingCode() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Format price with Thai Baht suffix.
 * @param float $price
 * @return string
 */
function formatPrice($price) {
    return number_format($price, 0) . ' THB';
}

/**
 * Format date to readable string.
 * @param string $date
 * @return string
 */
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

?>