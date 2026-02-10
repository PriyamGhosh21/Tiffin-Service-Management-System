<?php
/**
 * Load Composer Autoloader
 *
 * This file loads the Composer autoloader for the theme.
 *
 * @package Hello_Elementor_Child
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define the path to the theme directory
if (!defined('HELLO_ELEMENTOR_CHILD_DIR')) {
    define('HELLO_ELEMENTOR_CHILD_DIR', get_stylesheet_directory());
}

// Check if the Composer autoloader exists
$composer_autoload = HELLO_ELEMENTOR_CHILD_DIR . '/vendor/autoload.php';

if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} 