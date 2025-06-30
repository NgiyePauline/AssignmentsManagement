<?php

// Define the base directory of the project
define('BASE_DIR', __DIR__);

// Error reporting configuration (set first to catch all errors)
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_DIR . '/logs/error.log');

// Create logs directory if it doesn't exist
if (!file_exists(BASE_DIR . '/logs')) {
    mkdir(BASE_DIR . '/logs', 0755, true);
}

// Create uploads directory if it doesn't exist
if (!file_exists(BASE_DIR . '/uploads')) {
    mkdir(BASE_DIR . '/uploads', 0755, true);
}

// Define DEBUG mode (set to false in production)
define('DEBUG', true);

// Require Composer's autoloader if it exists
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require_once BASE_DIR . '/vendor/autoload.php';
}

// Set up our custom autoloader
spl_autoload_register(function ($className) {
    // Convert namespace separators to directory separators
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    // Define possible locations for class files
    $locations = [
        BASE_DIR . '/includes/classes/' . $className . '.php',
        BASE_DIR . '/includes/' . $className . '.php',
        BASE_DIR . '/models/' . $className . '.php',
        BASE_DIR . '/controllers/' . $className . '.php',
    ];
    
    // Try each location
    foreach ($locations as $location) {
        if (file_exists($location)) {
            require_once $location;
            return;
        }
    }
});

// Verify and load configuration files
$required_configs = [
    '/config/database.php',
    '/config/email.php'
];

foreach ($required_configs as $config) {
    $config_path = BASE_DIR . $config;
    if (!file_exists($config_path)) {
        die("Configuration file missing: " . $config);
    }
    require_once $config_path;
}

// Include helper functions
require_once BASE_DIR . '/includes/functions.php';

// Session configuration (must be before session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Shutdown function for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
        
        if (!DEBUG) {
            http_response_code(500);
            if (file_exists(BASE_DIR . '/includes/error_pages/500.php')) {
                include BASE_DIR . '/includes/error_pages/500.php';
            } else {
                die('A fatal error occurred. Please try again later.');
            }
        }
    }
});

// Exception handler
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage() . 
              " in " . $exception->getFile() . 
              " on line " . $exception->getLine());
    
    if (!DEBUG) {
        http_response_code(500);
        if (file_exists(BASE_DIR . '/includes/error_pages/500.php')) {
            include BASE_DIR . '/includes/error_pages/500.php';
        } else {
            die('An unexpected error occurred. Please try again later.');
        }
    } else {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    }
    exit;
});

// Error handler for non-fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }
    
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    
    if (DEBUG) {
        echo "<div style='padding:10px;margin:10px;border:1px solid #f00;background:#fee;'>";
        echo "<strong>Error [$errno]:</strong> $errstr in <strong>$errfile</strong> on line <strong>$errline</strong>";
        echo "</div>";
    }
    return true;
});


require_once BASE_DIR . '/includes/auth.php';