<?php
declare(strict_types=1);

/**
 * devHire - Global bootstrap
 * Load Composer autoloader, set error reporting, start session helper
 */

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// Set timezone
date_default_timezone_set('Europe/Budapest');

// Error reporting - in production set display_errors = 0
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Helper to get app base URL
if (!function_exists('app_url')) {
    function app_url(string $path = ''): string {
        $configFile = __DIR__ . '/mail_config.php';
        if (file_exists($configFile)) {
            $mc = require $configFile;
            if (!empty($mc['app_url'])) {
                return rtrim($mc['app_url'], '/') . '/' . ltrim($path, '/');
            }
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Detect /devhire base
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = '';
        if (strpos($scriptName, '/devhire/') !== false) {
            $base = substr($scriptName, 0, strpos($scriptName, '/devhire/') + 8);
        }
        return $protocol . '://' . $host . rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
