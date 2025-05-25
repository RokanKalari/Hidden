<?php
/**
 * DATABASE CONFIGURATION FILE
 * File: config/database.php
 * Purpose: Handles database connection and configuration
 * 
 * SETUP INSTRUCTIONS:
 * 1. Update the database credentials below with your MySQL details
 * 2. Make sure your MySQL server is running
 * 3. Import the erp_database.sql file to create the database structure
 */

// Database Configuration
define('DB_HOST', 'localhost');           // Database host (usually localhost)
define('DB_NAME', 'erp_system');          // Database name
define('DB_USER', 'root');                // Database username
define('DB_PASS', 'softexa2025');                    // Database password (empty for XAMPP default)
define('DB_CHARSET', 'utf8mb4');          // Database charset

// PDO Options for better security and performance
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

// Global database connection variable
$pdo = null;

/**
 * Get database connection
 * Returns PDO connection instance
 */
function getDBConnection() {
    global $pdo, $pdo_options;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
            
            // Set timezone
            $pdo->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            // Log error and show user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

/**
 * Execute a query with parameters
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function executeQuery($query, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch single row
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @return array|false
 */
function fetchRow($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 * @param string $query SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

/**
 * Get last insert ID
 * @return string
 */
function getLastInsertId() {
    $pdo = getDBConnection();
    return $pdo->lastInsertId();
}

/**
 * Begin database transaction
 */
function beginTransaction() {
    $pdo = getDBConnection();
    return $pdo->beginTransaction();
}

/**
 * Commit database transaction
 */
function commitTransaction() {
    $pdo = getDBConnection();
    return $pdo->commit();
}

/**
 * Rollback database transaction
 */
function rollbackTransaction() {
    $pdo = getDBConnection();
    return $pdo->rollBack();
}

/**
 * Check database connection
 * @return bool
 */
function testDatabaseConnection() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Initialize connection on file include
try {
    getDBConnection();
} catch (Exception $e) {
    // Connection will be attempted when needed
}
?>