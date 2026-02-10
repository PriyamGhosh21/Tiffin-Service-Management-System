<?php
/**
 * Server Cron Script for Daily Tiffin Count
 * 
 * This script should be called by the server cron job to update the daily tiffin count.
 * It includes timezone settings, time window check, and email notifications.
 */

// Load WordPress environment
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-load.php');

// Set timezone to Eastern Time (Canada)
date_default_timezone_set('America/Toronto');

// Configuration
$security_key = '492223536651f3bad95342fc1a49adea82f352f53be63bcf4116b8cf2b9b1220'; // Change this to a unique value
$admin_email = get_option('admin_email'); // Get admin email from WordPress
$site_name = get_bloginfo('name');
$log_file = __DIR__ . '/wp-content/tiffin-cron.log';

// Logging function
function log_message($message) {
    global $log_file;
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($log_file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// Email notification function
function send_notification($subject, $message) {
    global $admin_email, $site_name;
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>'
    );
    
    wp_mail($admin_email, $subject, $message, $headers);
    log_message("Email notification sent: $subject");
}

// Check if current time is within the allowed window (9 PM to 11:59 PM EST)
function is_within_time_window() {
    $current_hour = (int)date('G'); // 24-hour format without leading zeros
    return ($current_hour >= 21 && $current_hour <= 23); // 9 PM to 11:59 PM
}

// Main execution
try {
    log_message('Cron job started');
    
    // Validate security key
    if (!isset($_GET['key']) || $_GET['key'] !== $security_key) {
        $error_message = 'ERROR: Invalid security key';
        log_message($error_message);
        send_notification(
            "[$site_name] Tiffin Count Cron - Security Error",
            "<p>The tiffin count cron job failed to run due to an invalid security key.</p>"
        );
        echo $error_message;
        exit;
    }
    
    // Check if we're in the allowed time window
    if (!is_within_time_window()) {
        $time_message = 'Cron job skipped - Current time is outside the allowed window (9 PM to 11:59 PM EST)';
        log_message($time_message);
        send_notification(
            "[$site_name] Tiffin Count Cron - Outside Time Window",
            "<p>The tiffin count cron job was triggered at " . date('h:i A') . " EST, which is outside the allowed window (9 PM to 11:59 PM EST).</p>"
        );
        echo $time_message;
        exit;
    }
    
    // Run the tiffin count calculation
    if (class_exists('Satguru_Tiffin_Calculator')) {
        Satguru_Tiffin_Calculator::save_daily_tiffin_count();
        
        $success_message = 'Daily tiffin count updated successfully at ' . date('Y-m-d h:i:s A');
        log_message($success_message);
        
        send_notification(
            "[$site_name] Tiffin Count Cron - Success",
            "<p>The daily tiffin count was successfully updated at " . date('h:i A') . " EST.</p>"
        );
        
        echo "Success: " . $success_message;
    } else {
        throw new Exception('Satguru_Tiffin_Calculator class not found');
    }
} catch (Exception $e) {
    $error_message = 'ERROR: ' . $e->getMessage();
    log_message($error_message);
    
    send_notification(
        "[$site_name] Tiffin Count Cron - Error",
        "<p>The tiffin count cron job failed with the following error:</p>" .
        "<p><strong>" . $e->getMessage() . "</strong></p>"
    );
    
    echo $error_message;
}