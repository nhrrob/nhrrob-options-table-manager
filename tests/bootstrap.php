<?php

if (!defined('ABSPATH')) {
    exit;
}
// First, load Composer's autoloader
if (!defined('ABSPATH')) {
    exit;
}
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Set up WP_Mock
WP_Mock::bootstrap();