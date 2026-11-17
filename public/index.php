<?php

/**
 * Application Entry Point (Front Controller)
 * 
 * All HTTP requests are routed through this file via .htaccess URL rewriting.
 */

// Register Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap and run the application
\App\Core\App::run();
