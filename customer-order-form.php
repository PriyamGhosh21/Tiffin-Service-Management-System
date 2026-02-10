<?php
/**
 * Customer Self-Order System
 * Allows customers to place orders via shared links from salespeople
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Customer_Self_Order_System {
    
    private $plan_data = null;
    private $switch_plan_tiffin_option = 'switch_plan_tiffin_packages';
    private $switch_plan_products_option = 'switch_plan_allowed_products';
    private $trial_meal_products_option = 'trial_meal_allowed_products';
    private $trial_meal_tiffin_option = 'trial_meal_tiffin_packages';
    private $switch_plan_tax_enabled_option = 'switch_plan_tax_enabled';
    private $trial_meal_tax_enabled_option = 'trial_meal_tax_enabled';
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_customer_scripts']);
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('template_redirect', [$this, 'handle_customer_order_form']);
        add_action('wp_ajax_generate_customer_order_link', [$this, 'ajax_generate_link']);
        add_action('wp_ajax_nopriv_submit_customer_order', [$this, 'ajax_submit_customer_order']);
        add_action('wp_ajax_submit_customer_order', [$this, 'ajax_submit_customer_order']);
        add_action('wp_ajax_approve_pending_order', [$this, 'ajax_approve_pending_order']);
        add_action('wp_ajax_reject_pending_order', [$this, 'ajax_reject_pending_order']);
        add_action('wp_ajax_get_pending_order_details', [$this, 'ajax_get_pending_order_details']);
        add_action('wp_ajax_refresh_pending_orders_table', [$this, 'ajax_refresh_pending_orders_table']);
        add_action('wp_ajax_refresh_activity_logs_table', [$this, 'ajax_refresh_activity_logs_table']);
        add_action('wp_ajax_create_logs_table', [$this, 'ajax_create_logs_table']);
        add_action('wp_ajax_refresh_logs_status', [$this, 'ajax_refresh_logs_status']);
    }
    
    public function init() {
        // Add rewrite rule for customer order form
        add_rewrite_rule(
            '^customer-order/([^/]+)/?$',
            'index.php?customer_order_token=$matches[1]',
            'top'
        );
        
        // Add query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'customer_order_token';
            return $vars;
        });
        
        // Register admin menu for both link generation and pending orders
        add_action('admin_menu', [$this, 'register_admin_menus']);
    }
    
    /**
     * Register admin menus
     */
    public function register_admin_menus() {
        // Get pending orders count for menu badge
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        $pending_count = 0;
        
        // Check if table exists before querying
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        }
        
        $menu_title = 'Generate Order Links';
        if ($pending_count > 0) {
            $menu_title .= ' <span class="awaiting-mod">' . $pending_count . '</span>';
        }
        
        // Main menu for order links and pending orders (combined)
        add_submenu_page(
            'woocommerce',
            'Generate Order Links',
            $menu_title,
            'manage_woocommerce',
            'generate-order-links',
            [$this, 'render_combined_orders_page']
        );

        add_submenu_page(
            'woocommerce',
            'Switch Plan Packages',
            'Switch Plan Packages',
            'manage_woocommerce',
            'switch-plan-packages',
            [$this, 'render_switch_plan_packages_page']
        );

        add_submenu_page(
            'woocommerce',
            'Link Addons',
            'Link Addons',
            'manage_woocommerce',
            'edit.php?post_type=generated_link_addon'
        );

        add_submenu_page(
            'woocommerce',
            'Trial Meal Products',
            'Trial Meal Products',
            'manage_woocommerce',
            'trial-meal-products',
            [$this, 'render_trial_meal_products_page']
        );

        add_submenu_page(
            'woocommerce',
            'Trial Meal Packages',
            'Trial Meal Packages',
            'manage_woocommerce',
            'trial-meal-packages',
            [$this, 'render_trial_meal_packages_page']
        );
    }
    
    /**
     * Render the combined orders management page
     */
    public function render_combined_orders_page() {
        ?>
        <div class="wrap">
            <h1>Generate Order Links</h1>
            
            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="#generate-links" class="nav-tab nav-tab-active" id="generate-links-tab">Generate Order Links</a>
                <a href="#pending-orders" class="nav-tab" id="pending-orders-tab">Pending Orders <span id="pending-count-badge" class="count"></span></a>
                <a href="#activity-logs" class="nav-tab" id="activity-logs-tab">Activity Logs</a>
            </h2>
            
            <!-- Generate Links Tab -->
            <div id="generate-links-content" class="tab-content">
                <?php $this->render_link_generator_content(); ?>
            </div>
            
            <!-- Pending Orders Tab -->
            <div id="pending-orders-content" class="tab-content" style="display: none;">
                <?php $this->render_pending_orders_content(); ?>
            </div>
            
            <!-- Activity Logs Tab -->
            <div id="activity-logs-content" class="tab-content" style="display: none;">
                <?php $this->render_activity_logs_content(); ?>
            </div>
            
            <!-- Sound Notification Audio Element -->
            <audio id="new-order-sound" preload="auto">
                <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJutt7HBxKqgn7S9xcbQ0tjTy8LBq7Kqh4Vxcn+Qlqivt76+t7msm4p+k5WJfGtniJKbo6unq6mfkYV3h4+LfXRwdoeRl52hoZ6VknFujYqIh4eDgIqRkYp/c29/hIiJgoGDg4mJio2Lhn9+foaNjo2KhoKChomLioiGhYeIiYmJh4WHhYaHh4eGhYSEhIWFhYWEhISEhYWFhYWEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYWFhYSEhISEhYSFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhIWFhYWEhISEhISFhYWFhISEhISFhYWFhISEhISFhYWFhISEhISFhYWFhISEhISFhYWFhISEhISFhYaGhYSEhISFhYaGhYSEhISFhYaGhYSEhISFhYaGhYSEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWEhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhISFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYSFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYWFhYWFhYaGhYaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYWFhYaGhoaFhYaGhYaGhoaFhYaGhYaGhoaFhYaGhYaGhoaFhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaGhoaGhYaGhYaG" type="audio/wav">
            </audio>
            
            <!-- Sound Control UI -->
            <div id="sound-notification-control" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; background: #fff; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); display: none;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="sound-enabled" checked>
                    <span> Sound Notifications</span>
                </label>
            </div>
        </div>
        
        <style>
        .tab-content {
            margin-top: 20px;
        }
        
        .nav-tab-wrapper {
            border-bottom: 1px solid #ccc;
            margin: 20px 0;
        }
        
        .nav-tab {
            position: relative;
            display: inline-block;
            padding: 8px 12px;
            margin: 0;
            text-decoration: none;
            border: 1px solid transparent;
            border-bottom: none;
            background: #f1f1f1;
            color: #666;
        }
        
        .nav-tab-active {
            background: #fff;
            border-color: #ccc;
            border-bottom-color: #fff;
            color: #000;
        }
        
        .nav-tab:hover {
            background: #fff;
            color: #000;
        }
        
        .count {
            background: #d54e21;
            border-radius: 10px;
            color: #fff;
            font-size: 9px;
            line-height: 17px;
            padding: 0 6px;
            margin-left: 5px;
            display: none;
        }
        
        .count.has-count {
            display: inline;
        }
        
        .auto-refresh-status {
            float: right;
            margin: 10px 0;
            font-size: 12px;
            color: #666;
        }
        
        .refresh-indicator {
            color: #0073aa;
            margin-left: 5px;
        }
        
        .refresh-indicator.spinning {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* New Order Notification Animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .new-order-notification {
            animation: slideInRight 0.5s ease-out, pulse 2s ease-in-out infinite;
        }
        
        #sound-notification-control {
            transition: all 0.3s ease;
        }
        
        #sound-notification-control:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        #sound-notification-control label {
            font-size: 13px;
            color: #333;
        }
        
        #sound-notification-control input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .sound-toast {
            animation: slideInRight 0.3s ease-out;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let autoRefreshInterval;
            let isAutoRefreshEnabled = true;
            let previousPendingCount = -1; // Track previous count (-1 means first load)
            let isSoundEnabled = localStorage.getItem('pendingOrderSoundEnabled') !== 'false'; // Default enabled
            let isFirstLoad = true;
            
            // Initialize sound checkbox and show control when on pending orders tab
            $('#sound-enabled').prop('checked', isSoundEnabled);
            
            // Sound toggle handler
            $('#sound-enabled').on('change', function() {
                isSoundEnabled = $(this).is(':checked');
                localStorage.setItem('pendingOrderSoundEnabled', isSoundEnabled);
                showSoundToast(isSoundEnabled ? '?? Sound notifications enabled' : '?? Sound notifications disabled');
            });
            
            // External sound file path (place your custom sound in theme folder as 'notification-sound.mp3')
            const externalSoundUrl = '<?php echo get_stylesheet_directory_uri(); ?>/assets/sounds/notification.mp3';
            let externalSoundAvailable = null; // null = not checked, true = available, false = not available
            let cachedAudio = null;
            
            // Pre-check if external sound exists
            function checkExternalSound() {
                return new Promise((resolve) => {
                    if (externalSoundAvailable !== null) {
                        resolve(externalSoundAvailable);
                        return;
                    }
                    
                    const audio = new Audio();
                    audio.addEventListener('canplaythrough', function() {
                        externalSoundAvailable = true;
                        cachedAudio = audio;
                        console.log('? External notification sound loaded:', externalSoundUrl);
                        resolve(true);
                    });
                    audio.addEventListener('error', function() {
                        externalSoundAvailable = false;
                        console.log('?? No external sound found, using default chime');
                        resolve(false);
                    });
                    audio.src = externalSoundUrl;
                    audio.load();
                    
                    // Timeout fallback
                    setTimeout(() => {
                        if (externalSoundAvailable === null) {
                            externalSoundAvailable = false;
                            resolve(false);
                        }
                    }, 3000);
                });
            }
            
            // Check for external sound on page load
            checkExternalSound();
            
            // Play notification sound - uses external file if available, otherwise default chime
            function playNotificationSound() {
                if (!isSoundEnabled) return;
                
                // Try external sound first
                if (externalSoundAvailable === true) {
                    playExternalSound();
                } else if (externalSoundAvailable === false) {
                    playDefaultChime();
                } else {
                    // Still checking, wait and try
                    checkExternalSound().then((available) => {
                        if (available) {
                            playExternalSound();
                        } else {
                            playDefaultChime();
                        }
                    });
                }
            }
            
            // Play external audio file
            function playExternalSound() {
                try {
                    const audio = new Audio(externalSoundUrl);
                    audio.volume = 0.7;
                    audio.play().catch(e => {
                        console.log('External sound play failed, using default:', e);
                        playDefaultChime();
                    });
                } catch (e) {
                    console.log('External audio error:', e);
                    playDefaultChime();
                }
            }
            
            // Default chime using Web Audio API
            function playDefaultChime() {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    
                    // First chime
                    const oscillator1 = audioContext.createOscillator();
                    const oscillator2 = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator1.connect(gainNode);
                    oscillator2.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator1.frequency.setValueAtTime(830, audioContext.currentTime);
                    oscillator2.frequency.setValueAtTime(1245, audioContext.currentTime);
                    oscillator1.type = 'sine';
                    oscillator2.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.8);
                    
                    oscillator1.start(audioContext.currentTime);
                    oscillator2.start(audioContext.currentTime);
                    oscillator1.stop(audioContext.currentTime + 0.8);
                    oscillator2.stop(audioContext.currentTime + 0.8);
                    
                    // Second chime (higher pitch)
                    setTimeout(function() {
                        const osc1 = audioContext.createOscillator();
                        const osc2 = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        
                        osc1.connect(gain);
                        osc2.connect(gain);
                        gain.connect(audioContext.destination);
                        
                        osc1.frequency.setValueAtTime(1046, audioContext.currentTime);
                        osc2.frequency.setValueAtTime(1568, audioContext.currentTime);
                        osc1.type = 'sine';
                        osc2.type = 'sine';
                        
                        gain.gain.setValueAtTime(0.25, audioContext.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);
                        
                        osc1.start(audioContext.currentTime);
                        osc2.start(audioContext.currentTime);
                        osc1.stop(audioContext.currentTime + 1);
                        osc2.stop(audioContext.currentTime + 1);
                    }, 200);
                    
                } catch (e) {
                    console.log('Audio playback failed:', e);
                }
            }
            
            // Show toast notification
            function showSoundToast(message) {
                const toast = $('<div class="sound-toast">' + message + '</div>');
                toast.css({
                    position: 'fixed',
                    bottom: '70px',
                    right: '20px',
                    background: '#333',
                    color: '#fff',
                    padding: '10px 20px',
                    borderRadius: '5px',
                    zIndex: 10000
                });
                $('body').append(toast);
                setTimeout(function() {
                    toast.fadeOut(300, function() { $(this).remove(); });
                }, 2000);
            }
            
            // Show new order notification banner
            function showNewOrderNotification(newOrderCount) {
                // Remove any existing notification
                $('.new-order-notification').remove();
                
                const notification = $('<div class="new-order-notification">?? ' + newOrderCount + ' New Order' + (newOrderCount > 1 ? 's' : '') + ' Received!</div>');
                notification.css({
                    position: 'fixed',
                    top: '50px',
                    right: '20px',
                    background: 'linear-gradient(135deg, #28a745, #20c997)',
                    color: '#fff',
                    padding: '15px 25px',
                    borderRadius: '8px',
                    zIndex: 10000,
                    boxShadow: '0 4px 15px rgba(40, 167, 69, 0.4)',
                    fontWeight: 'bold',
                    fontSize: '14px',
                    cursor: 'pointer'
                });
                $('body').append(notification);
                
                // Click to dismiss
                notification.on('click', function() {
                    $(this).fadeOut(300, function() { $(this).remove(); });
                });
                
                // Flash the browser tab title
                let originalTitle = document.title;
                let flashCount = 0;
                let flashInterval = setInterval(function() {
                    document.title = document.title === '?? NEW ORDER!' ? originalTitle : '?? NEW ORDER!';
                    flashCount++;
                    if (flashCount >= 10) {
                        clearInterval(flashInterval);
                        document.title = originalTitle;
                    }
                }, 1000);
                
                // Auto-dismiss after 8 seconds
                setTimeout(function() {
                    notification.fadeOut(500, function() { $(this).remove(); });
                    clearInterval(flashInterval);
                    document.title = originalTitle;
                }, 8000);
            }
            
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').hide();
                
                if ($(this).attr('href') === '#generate-links') {
                    $('#generate-links-content').show();
                    // Keep sound control visible on all tabs
                    $('#sound-notification-control').show();
                } else if ($(this).attr('href') === '#pending-orders') {
                    $('#pending-orders-content').show();
                    $('#sound-notification-control').show();
                    // Refresh the table when switching to pending orders tab
                    window.refreshPendingOrders(true); // true = update table
                } else if ($(this).attr('href') === '#activity-logs') {
                    $('#activity-logs-content').show();
                    $('#sound-notification-control').show();
                    // Load logs if not already loaded
                    if (!$('#activity-logs-content').hasClass('loaded')) {
                        window.refreshActivityLogs();
                        $('#activity-logs-content').addClass('loaded');
                    }
                }
            });
            
            // Background polling interval (always runs)
            let backgroundPollingInterval = null;
            
            // Start background polling for new orders (runs on ALL tabs)
            function startBackgroundPolling() {
                if (backgroundPollingInterval) clearInterval(backgroundPollingInterval);
                
                backgroundPollingInterval = setInterval(function() {
                    if (isSoundEnabled) {
                        checkForNewOrders();
                    }
                }, 10000); // Check every 10 seconds
                
                console.log('?? Background order polling started');
            }
            
            // Check for new orders (lightweight - just gets count)
            function checkForNewOrders() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'refresh_pending_orders_table',
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update count and check for new orders (this triggers sound)
                            updatePendingCount(response.data.count);
                            
                            // If on pending orders tab, also update the table
                            if ($('#pending-orders-content').is(':visible')) {
                                $('#pending-orders-table-container').html(response.data.html);
                                updateRefreshStatus('Auto-refresh: ON (last updated: ' + new Date().toLocaleTimeString() + ')');
                                $('.refresh-indicator').removeClass('spinning');
                            }
                        }
                    }
                });
            }
            
            function updateRefreshStatus(text) {
                $('.auto-refresh-status').text(text);
            }
            
            // Global function definitions - accessible from anywhere
            window.refreshPendingOrders = function(updateTable) {
                if (updateTable !== false) {
                    $('.refresh-indicator').addClass('spinning');
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'refresh_pending_orders_table',
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (updateTable !== false && $('#pending-orders-content').is(':visible')) {
                                $('#pending-orders-table-container').html(response.data.html);
                            }
                            updatePendingCount(response.data.count);
                            updateRefreshStatus('Auto-refresh: ON (last updated: ' + new Date().toLocaleTimeString() + ')');
                        }
                    },
                    error: function() {
                        updateRefreshStatus('Auto-refresh: ERROR');
                    },
                    complete: function() {
                        $('.refresh-indicator').removeClass('spinning');
                    }
                });
            };
            
            window.refreshActivityLogs = function(page_number) {
                var filterData = {
                    action: 'refresh_activity_logs_table',
                    nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>',
                    action_filter: $('#log-action-filter').val(),
                    date_filter: $('#log-date-filter').val(),
                    search_term: $('#log-search').val()
                };
                
                // Add page number if provided
                if (page_number) {
                    filterData.page_number = page_number;
                }
                
                console.log('Sending filter data:', filterData);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: filterData,
                    success: function(response) {
                        console.log('Filter response:', response);
                        if (response.success) {
                            $('#activity-logs-table-container').html(response.data.html);
                        }
                    },
                    error: function() {
                        console.log('Failed to load activity logs');
                    }
                });
            };
            
            window.refreshLogsStatus = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'refresh_logs_status',
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#logs-status-info').html(response.data.html);
                        }
                    },
                    error: function() {
                        console.log('Failed to refresh logs status');
                    }
                });
            };
            
            function updatePendingCount(count) {
                const badge = $('#pending-count-badge');
                const menuBadge = $('.awaiting-mod');
                
                // Check if there are new orders (count increased)
                if (previousPendingCount !== -1 && count > previousPendingCount) {
                    const newOrderCount = count - previousPendingCount;
                    console.log('?? New orders detected:', newOrderCount);
                    
                    // Play notification sound
                    playNotificationSound();
                    
                    // Show notification banner
                    showNewOrderNotification(newOrderCount);
                }
                
                // Update the previous count (but not on first load to avoid false positive)
                if (isFirstLoad) {
                    isFirstLoad = false;
                }
                previousPendingCount = count;
                
                if (count > 0) {
                    badge.text(count).addClass('has-count');
                    menuBadge.text(count);
                } else {
                    badge.removeClass('has-count');
                    menuBadge.text('');
                }
            }
            
            // Manual refresh button
            $(document).on('click', '.manual-refresh-btn', function() {
                window.refreshPendingOrders(true);
            });
            
            // Toggle auto-refresh (controls table updates, not sound notifications)
            $(document).on('click', '.toggle-auto-refresh', function() {
                isAutoRefreshEnabled = !isAutoRefreshEnabled;
                $(this).text(isAutoRefreshEnabled ? 'Disable Auto-refresh' : 'Enable Auto-refresh');
                
                if (isAutoRefreshEnabled) {
                    updateRefreshStatus('Auto-refresh: ON (every 10s)');
                } else {
                    updateRefreshStatus('Auto-refresh: OFF (sound notifications still active)');
                }
            });
            
            // Initialize with pending orders count and start background polling
            window.refreshPendingOrders(true);
            
            // Start background polling for new order notifications (ALWAYS runs)
            startBackgroundPolling();
            
            // Show sound control on page load
            $('#sound-notification-control').show();
            
            console.log('?? Pending order notifications initialized - sound will play on ANY tab');
        });
        </script>
        <?php
    }

    /**
     * Helper to get switch plan tiffin packages
     */
    private function get_switch_plan_tiffin_packages(): array {
        $packages = get_option($this->switch_plan_tiffin_option, []);

        if (!is_array($packages) || empty($packages)) {
            $packages = [5, 10, 15, 20];
        }

        $packages = array_values(array_unique(array_filter(array_map('intval', $packages), function ($value) {
            return $value > 0;
        })));

        if (empty($packages)) {
            $packages = [5, 10, 15, 20];
        }

        sort($packages, SORT_NUMERIC);
        return $packages;
    }

    /**
     * Helper to get switch plan allowed products
     */
    private function get_switch_plan_allowed_products(): array {
        $products = get_option($this->switch_plan_products_option, []);
        if (!is_array($products)) {
            return [];
        }

        $products = array_values(array_unique(array_filter(array_map('absint', $products), function ($value) {
            return $value > 0;
        })));

        return $products;
    }

    /**
     * Helper to get trial meal allowed products
     */
    private function get_trial_meal_allowed_products(): array {
        $products = get_option($this->trial_meal_products_option, []);
        if (!is_array($products)) {
            return [];
        }

        $products = array_values(array_unique(array_filter(array_map('absint', $products), function ($value) {
            return $value > 0;
        })));

        return $products;
    }

    /**
     * Helper to get trial meal tiffin packages
     */
    private function get_trial_meal_tiffin_packages(): array {
        $packages = get_option($this->trial_meal_tiffin_option, []);

        if (!is_array($packages) || empty($packages)) {
            $packages = [1, 3, 5]; // Default trial meal packages
        }

        $packages = array_values(array_unique(array_filter(array_map('intval', $packages), function ($value) {
            return $value > 0;
        })));

        if (empty($packages)) {
            $packages = [1, 3, 5];
        }

        sort($packages, SORT_NUMERIC);
        return $packages;
    }

    /**
     * Check if tax is enabled for switch plans
     */
    private function is_switch_plan_tax_enabled(): bool {
        return (bool) get_option($this->switch_plan_tax_enabled_option, false);
    }

    /**
     * Check if tax is enabled for trial meals
     */
    private function is_trial_meal_tax_enabled(): bool {
        return (bool) get_option($this->trial_meal_tax_enabled_option, false);
    }

    /**
     * Get tax rate from WooCommerce settings
     */
    private function get_tax_rate(): float {
        if (!class_exists('WC_Tax')) {
            return 0;
        }
        
        // Get standard tax rates
        $tax_rates = WC_Tax::get_rates();
        if (empty($tax_rates)) {
            return 0;
        }
        
        // Get the first tax rate
        $first_rate = reset($tax_rates);
        return isset($first_rate['rate']) ? floatval($first_rate['rate']) : 0;
    }

    /**
     * Render switch plan packages settings page
     */
    public function render_switch_plan_packages_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $notice = '';
        if (isset($_POST['switch_plan_packages_submit'])) {
            check_admin_referer('switch_plan_packages_settings');

            $raw_input = isset($_POST['switch_plan_packages']) ? wp_unslash($_POST['switch_plan_packages']) : '';
            $raw_input = str_replace(["\r\n", "\r"], "\n", $raw_input);
            $parts = array_filter(array_map('trim', preg_split('/[\n,]+/', $raw_input)));

            $packages = array_values(array_unique(array_filter(array_map('intval', $parts), function ($value) {
                return $value > 0;
            })));

            if (!empty($packages)) {
                sort($packages, SORT_NUMERIC);
                update_option($this->switch_plan_tiffin_option, $packages);
                $notice = '<div class="notice notice-success"><p>Switch plan packages updated successfully.</p></div>';
            } else {
                $notice = '<div class="notice notice-error"><p>Please enter at least one valid tiffin count.</p></div>';
            }

            $selected_products = isset($_POST['switch_plan_products']) ? array_map('absint', (array) $_POST['switch_plan_products']) : [];
            update_option($this->switch_plan_products_option, array_values(array_filter($selected_products)));
            
            // Save tax enabled option
            $tax_enabled = isset($_POST['switch_plan_tax_enabled']) ? 1 : 0;
            update_option($this->switch_plan_tax_enabled_option, $tax_enabled);
        }

        $current_packages = $this->get_switch_plan_tiffin_packages();
        $packages_string = implode(', ', $current_packages);
        $current_products = $this->get_switch_plan_allowed_products();
        $tax_enabled = $this->is_switch_plan_tax_enabled();
        $tax_rate = $this->get_tax_rate();
        $available_products = wc_get_products([
            'status' => 'publish',
            'type' => ['simple', 'variable'],
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1>Switch Plan Tiffin Packages</h1>
            <p>Define which tiffin counts customers can select when switching to a new plan from WhatsApp.</p>
            <?php echo wp_kses_post($notice); ?>
            <form method="post">
                <?php wp_nonce_field('switch_plan_packages_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="switch_plan_packages">Tiffin Counts</label></th>
                        <td>
                            <textarea
                                id="switch_plan_packages"
                                name="switch_plan_packages"
                                rows="5"
                                class="large-text code"
                            ><?php echo esc_textarea($packages_string); ?></textarea>
                            <p class="description">Enter numbers separated by commas or new lines. Example: <code>5, 10, 15, 20</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="switch_plan_products">Allowed Products</label></th>
                        <td>
                            <select
                                id="switch_plan_products"
                                name="switch_plan_products[]"
                                multiple
                                size="10"
                                style="width: 100%; max-width: 480px;"
                            >
                                <?php foreach ($available_products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected(in_array($product->get_id(), $current_products, true)); ?>>
                                        <?php echo esc_html($product->get_name()); ?> (ID: <?php echo esc_html($product->get_id()); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select which products appear in the switch plan form. Leave empty to allow all published products. Hold Ctrl (Windows) or Command (Mac) to select multiple.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="switch_plan_tax_enabled">Enable Tax</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="switch_plan_tax_enabled" id="switch_plan_tax_enabled" value="1" <?php checked($tax_enabled, true); ?>>
                                Add tax to switch plan orders
                            </label>
                            <?php if ($tax_rate > 0): ?>
                                <p class="description">Current tax rate: <strong><?php echo esc_html(number_format($tax_rate, 2)); ?>%</strong> (from WooCommerce settings)</p>
                            <?php else: ?>
                                <p class="description" style="color: #d63638;">No tax rate configured in WooCommerce. Please set up tax rates in WooCommerce ? Settings ? Tax.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Packages', 'primary', 'switch_plan_packages_submit'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render trial meal products settings page
     */
    public function render_trial_meal_products_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $notice = '';
        if (isset($_POST['trial_meal_products_submit'])) {
            check_admin_referer('trial_meal_products_settings');

            $selected_products = isset($_POST['trial_meal_products']) ? array_map('absint', (array) $_POST['trial_meal_products']) : [];
            update_option($this->trial_meal_products_option, array_values(array_filter($selected_products)));
            $notice = '<div class="notice notice-success"><p>Trial meal products updated successfully.</p></div>';
        }

        $current_products = $this->get_trial_meal_allowed_products();
        $available_products = wc_get_products([
            'status' => 'publish',
            'type' => ['simple', 'variable'],
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1>Trial Meal Products</h1>
            <p>Select which products appear in the trial meal order form. Only selected products will be available for customers to choose from when using WhatsApp API trial meal links.</p>
            <?php echo wp_kses_post($notice); ?>
            <form method="post">
                <?php wp_nonce_field('trial_meal_products_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="trial_meal_products">Trial Meal Products</label></th>
                        <td>
                            <select
                                id="trial_meal_products"
                                name="trial_meal_products[]"
                                multiple
                                size="15"
                                style="width: 100%; max-width: 600px;"
                            >
                                <?php foreach ($available_products as $product): ?>
                                    <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected(in_array($product->get_id(), $current_products, true)); ?>>
                                        <?php echo esc_html($product->get_name()); ?> (ID: <?php echo esc_html($product->get_id()); ?>) - <?php echo wc_price($product->get_price()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select which products appear in the trial meal form. Hold Ctrl (Windows) or Command (Mac) to select multiple. Leave empty to allow all published products.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Trial Meal Products', 'primary', 'trial_meal_products_submit'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render trial meal packages settings page
     */
    public function render_trial_meal_packages_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $notice = '';
        if (isset($_POST['trial_meal_packages_submit'])) {
            check_admin_referer('trial_meal_packages_settings');

            $raw_input = isset($_POST['trial_meal_packages']) ? wp_unslash($_POST['trial_meal_packages']) : '';
            $raw_input = str_replace(["\r\n", "\r"], "\n", $raw_input);
            $parts = array_filter(array_map('trim', preg_split('/[\n,]+/', $raw_input)));

            $packages = array_values(array_unique(array_filter(array_map('intval', $parts), function ($value) {
                return $value > 0;
            })));

            if (!empty($packages)) {
                sort($packages, SORT_NUMERIC);
                update_option($this->trial_meal_tiffin_option, $packages);
                $notice = '<div class="notice notice-success"><p>Trial meal packages updated successfully.</p></div>';
            } else {
                $notice = '<div class="notice notice-error"><p>Please enter at least one valid tiffin count.</p></div>';
            }
            
            // Save tax enabled option
            $tax_enabled = isset($_POST['trial_meal_tax_enabled']) ? 1 : 0;
            update_option($this->trial_meal_tax_enabled_option, $tax_enabled);
        }

        $current_packages = $this->get_trial_meal_tiffin_packages();
        $packages_string = implode(', ', $current_packages);
        $tax_enabled = $this->is_trial_meal_tax_enabled();
        $tax_rate = $this->get_tax_rate();
        ?>
        <div class="wrap">
            <h1>Trial Meal Tiffin Packages</h1>
            <p>Define which tiffin counts customers can select when ordering trial meals from WhatsApp.</p>
            <?php echo wp_kses_post($notice); ?>
            <form method="post">
                <?php wp_nonce_field('trial_meal_packages_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="trial_meal_packages">Tiffin Counts</label></th>
                        <td>
                            <textarea
                                id="trial_meal_packages"
                                name="trial_meal_packages"
                                rows="5"
                                class="large-text code"
                            ><?php echo esc_textarea($packages_string); ?></textarea>
                            <p class="description">Enter numbers separated by commas or new lines. Example: <code>1, 3, 5</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trial_meal_tax_enabled">Enable Tax</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="trial_meal_tax_enabled" id="trial_meal_tax_enabled" value="1" <?php checked($tax_enabled, true); ?>>
                                Add tax to trial meal orders
                            </label>
                            <?php if ($tax_rate > 0): ?>
                                <p class="description">Current tax rate: <strong><?php echo esc_html(number_format($tax_rate, 2)); ?>%</strong> (from WooCommerce settings)</p>
                            <?php else: ?>
                                <p class="description" style="color: #d63638;">No tax rate configured in WooCommerce. Please set up tax rates in WooCommerce ? Settings ? Tax.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Packages', 'primary', 'trial_meal_packages_submit'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render the link generator content (original page content)
     */
    public function render_link_generator_content() {
        ?>
            <h2>Generate Customer Order Links</h2>
            <div class="card" style="max-width: 800px; margin-bottom:500px"">
                <h2>Create a Shareable Order Link</h2>
                <form method="post" id="generate-link-form">
                    <?php wp_nonce_field('generate_customer_order_link'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Plan Type</label></th>
                            <td>
                                <fieldset>
                                    <label style="margin-right: 20px;">
                                        <input type="radio" name="plan_type" value="existing" checked> 
                                        Select from Existing Plans
                                    </label>
                                    <label>
                                        <input type="radio" name="plan_type" value="custom"> 
                                        Create Custom Plan
                                    </label>
                                </fieldset>
                                <p class="description">Choose whether to use an existing product or create a custom plan</p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Existing Plan Section -->
                    <div id="existing-plan-section">
                        <h3>Select Existing Plan</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="existing_product">Select Product</label></th>
                                <td>
                                    <select name="existing_product" id="existing_product" class="regular-text" style="width: 100%;">
                                        <option value="">-- Select Product --</option>
                                        <?php
                                        $products = wc_get_products([
                                            'status' => 'publish', 
                                            'limit' => -1,
                                            'type' => 'simple'
                                        ]);
                                        foreach ($products as $product) {
                                            $price = $product->get_price();
                                            echo '<option value="' . esc_attr($product->get_id()) . '" 
                                                    data-name="' . esc_attr($product->get_name()) . '"
                                                    data-price="' . esc_attr($price) . '"
                                                    data-description="' . esc_attr(wp_strip_all_tags($product->get_short_description())) . '">
                                                ' . esc_html($product->get_name()) . ' - ' . wc_price($price) . '
                                            </option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description">Select a published product to use as the plan</p>
                                </td>
                            </tr>
                            
                            <tr id="existing-tiffins-row" style="display: none;">
                                <th><label for="existing_number_of_tiffins">Number of Tiffins</label></th>
                                <td>
                                    <input type="number" name="existing_number_of_tiffins" id="existing_number_of_tiffins" min="1" max="100">
                                    <p class="description">Specify number of tiffins for this plan (will override product default if set)</p>
                                </td>
                            </tr>
                            
                            <tr id="existing-price-row" style="display: none;">
                                <th><label for="existing_custom_price">Custom Price (Optional)</label></th>
                                <td>
                                    <input type="number" name="existing_custom_price" id="existing_custom_price" step="0.01" min="0">
                                    <p class="description">Leave empty to use product price: <span id="product-price-display">?0.00</span></p>
                                </td>
                            </tr>
                            
                            <!-- Add-ons (structured components, mapped to meta key 'add-ons') -->
                            <tr id="existing-addons-row" style="display: none;">
                                <th><label>Add-ons (Optional)</label></th>
                                <td>
                                    <div class="addon-components">
                                        <div class="addon-component">
                                            <label>Non-Veg Add-ons:</label>
                                            <div class="component-inputs">
                                                <input type="number" name="existing_addon_nonveg_qty" id="existing_addon_nonveg_qty" min="0" max="10" placeholder="Qty" style="width:60px;">
                                                <select name="existing_addon_nonveg_size" id="existing_addon_nonveg_size" style="width:80px;">
                                                    <option value="">Size</option>
                                                    <option value="4oz">4oz</option>
                                                    <option value="6oz">6oz</option>
                                                    <option value="8oz">8oz</option>
                                                    <option value="10oz">10oz</option>
                                                    <option value="12oz">12oz</option>
                                                </select>
                                                <input type="text" name="existing_addon_nonveg_type" id="existing_addon_nonveg_type" placeholder="Type (e.g., Chicken, Mutton)" style="width:150px;">
                                            </div>
                                        </div>
                                        
                                        <div class="addon-component">
                                            <label>Veg Add-ons:</label>
                                            <div class="component-inputs">
                                                <input type="number" name="existing_addon_veg_qty" id="existing_addon_veg_qty" min="0" max="10" placeholder="Qty" style="width:60px;">
                                                <select name="existing_addon_veg_size" id="existing_addon_veg_size" style="width:80px;">
                                                    <option value="">Size</option>
                                                    <option value="4oz">4oz</option>
                                                    <option value="6oz">6oz</option>
                                                    <option value="8oz">8oz</option>
                                                    <option value="10oz">10oz</option>
                                                    <option value="12oz">12oz</option>
                                                </select>
                                                <input type="text" name="existing_addon_veg_type" id="existing_addon_veg_type" placeholder="Type (e.g., Dal, Sabzi)" style="width:150px;">
                                            </div>
                                        </div>
                                        
                                        <div class="addon-component">
                                            <label>Other Add-ons:</label>
                                            <div class="other-addons-grid">
                                                <div class="other-addon-item">
                                                    <label>Rotis:</label>
                                                    <input type="number" name="existing_addon_rotis_qty" id="existing_addon_rotis_qty" min="0" max="20" placeholder="Qty" style="width:60px;">
                                                </div>
                                                <div class="other-addon-item">
                                                    <label>Rice:</label>
                                                    <input type="number" name="existing_addon_rice_qty" id="existing_addon_rice_qty" min="0" max="20" placeholder="Qty" style="width:60px;">
                                                </div>
                                                <div class="other-addon-item">
                                                    <label>Salad:</label>
                                                    <input type="number" name="existing_addon_salad_qty" id="existing_addon_salad_qty" min="0" max="20" placeholder="Qty" style="width:60px;">
                                                </div>
                                                <div class="other-addon-item">
                                                    <label>Raita:</label>
                                                    <input type="number" name="existing_addon_raita_qty" id="existing_addon_raita_qty" min="0" max="20" placeholder="Qty" style="width:60px;">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="addon-preview" id="existing-addon-preview" style="margin-top:10px; padding:8px; background:#f8f9fa; border-radius:4px; font-size:12px; color:#666;">
                                            <strong>Add-ons Preview:</strong> <span id="existing-addon-preview-text">None</span>
                                        </div>
                                    </div>
                                    <p class="description">These values will be saved under the <code>add-ons</code> line item meta.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Custom Plan Section -->
                    <div id="custom-plan-section" style="display: none;">
                        <h3>Create Custom Meal Plan</h3>
                        <table class="form-table">
                            <tr>
                                <th><label>Custom Meal Configuration</label></th>
                                <td>
                                    <div class="custom-meal-builder">
                                        <div class="meal-component-row">
                                            <label class="component-label">Non-Veg Items:</label>
                                            <input type="number" name="nonveg_quantity" id="nonveg_quantity" min="0" max="10" value="0" style="width: 60px;">
                                            <span>x</span>
                                            <select name="nonveg_size" id="nonveg_size" style="width: 80px;">
                                                <option value="6oz">6oz</option>
                                                <option value="8oz" selected>8oz</option>
                                                <option value="10oz">10oz</option>
                                                <option value="12oz">12oz</option>
                                            </select>
                                            <input type="text" name="nonveg_type" id="nonveg_type" placeholder="e.g., Chicken, Mutton" style="width: 150px;">
                                        </div>
                                        
                                        <div class="meal-component-row">
                                            <label class="component-label">Veg Items:</label>
                                            <input type="number" name="veg_quantity" id="veg_quantity" min="0" max="10" value="0" style="width: 60px;">
                                            <span>x</span>
                                            <select name="veg_size" id="veg_size" style="width: 80px;">
                                                <option value="6oz">6oz</option>
                                                <option value="8oz" selected>8oz</option>
                                                <option value="10oz">10oz</option>
                                                <option value="12oz">12oz</option>
                                            </select>
                                            <input type="text" name="veg_type" id="veg_type" placeholder="e.g., Dal, Curry, Sabzi" style="width: 150px;">
                                        </div>
                                        
                                        <div class="meal-component-row">
                                            <label class="component-label">Rotis/Bread:</label>
                                            <input type="number" name="roti_quantity" id="roti_quantity" min="0" max="20" value="0" style="width: 60px;">
                                            <span>Rotis/Naan</span>
                                        </div>
                                        
                                        <div class="meal-component-row">
                                            <label class="component-label">Rice:</label>
                                            <input type="number" name="rice_quantity" id="rice_quantity" min="0" max="10" value="0" style="width: 60px;">
                                            <span>Portions</span>
                                        </div>
                                        
                                        <div class="meal-component-row">
                                            <label class="component-label">Raita:</label>
                                            <input type="number" name="raita_quantity" id="raita_quantity" min="0" max="10" value="0" style="width: 60px;">
                                            <span>Portions</span>
                                        </div>
                                        
                                        <div class="meal-component-row">
                                            <label class="component-label">Salad:</label>
                                            <input type="number" name="salad_quantity" id="salad_quantity" min="0" max="10" value="0" style="width: 60px;">
                                            <span>Portions</span>
                                        </div>
                                        
                                        <div class="meal-preview">
                                            <strong>Plan Name Preview:</strong>
                                            <div id="custom_meal_preview" style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 5px; font-style: italic; color: #666;">
                                                Custom Meal - Please configure items above
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden field to store the generated plan name -->
                                    <input type="hidden" name="plan_name" id="plan_name">
                                    
                                    <p class="description">Configure your custom meal components. The plan name will be automatically generated.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="number_of_tiffins">Number of Tiffins</label></th>
                                <td>
                                    <input type="number" name="number_of_tiffins" id="number_of_tiffins" min="1" max="100">
                                    <p class="description">Total number of tiffins in this plan</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="plan_price">Plan Price (?)</label></th>
                                <td>
                                    <input type="number" name="plan_price" id="plan_price" step="0.01" min="0">
                                    <p class="description">Total price for the entire plan</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="plan_description">Plan Description</label></th>
                                <td>
                                    <textarea name="plan_description" id="plan_description" rows="3" class="regular-text" style="width: 100%;"></textarea>
                                    <p class="description">Additional description or special instructions (optional)</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Common Settings -->
                    <table class="form-table">
                        <tr>
                            <th><label for="validity_days">Link Validity (Days)</label></th>
                            <td>
                                <input type="number" name="validity_days" id="validity_days" min="1" max="30" value="7">
                                <p class="description">Number of days this link will remain valid</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="upstairs_delivery_paid">Delivery Options</label></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="upstairs_delivery_paid" id="upstairs_delivery_paid" value="1">
                                        Upstairs Delivery Paid
                                    </label>
                                </fieldset>
                                <p class="description">
                                    If checked, customers will see "Upstairs Delivery" option along with all other delivery options. 
                                    If unchecked, customers will see only standard delivery options (without upstairs delivery).
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Generate Link</button>
                    </p>
                </form>
                
                <div id="generated-link-section" style="display: none; margin-top: 30px; padding: 20px; background: #f0f0f1; border-radius: 4px;">
                    <h3>Generated Link</h3>
                    <p><strong>Share this link with your customer:</strong></p>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="generated-link" readonly style="flex: 1; padding: 8px;">
                        <button type="button" id="copy-link" class="button">Copy Link</button>
                    </div>
                    <p class="description" style="margin-top: 10px;">
                        This link will expire in <span id="link-expiry"></span> days. 
                        The customer can use this link to place their order with the pre-configured plan details.
                    </p>
                </div>
            </div>
        </div>

        <style>
        .custom-meal-builder {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .meal-component-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .component-label {
            min-width: 100px;
            font-weight: bold;
            color: #333;
        }
        
        .meal-component-row input,
        .meal-component-row select {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 14px;
        }
        
        .meal-component-row span {
            color: #666;
            font-weight: 500;
        }
        
        .meal-preview {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        #custom_meal_preview {
            min-height: 40px;
            display: flex;
            align-items: center;
        }
        
        .meal-preview strong {
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .meal-component-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .component-label {
                min-width: auto;
                margin-bottom: 5px;
            }
            
            .meal-component-row input,
            .meal-component-row select {
                width: 100%;
                max-width: 200px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle plan type toggle
            $('input[name="plan_type"]').on('change', function() {
                const planType = $(this).val();
                
                if (planType === 'existing') {
                    $('#existing-plan-section').show();
                    $('#custom-plan-section').hide();
                    
                    // Clear custom plan fields
                    $('#plan_name, #number_of_tiffins, #plan_price, #plan_description').val('');
                    clearCustomMealInputs();
                    
                    // Update required fields
                    $('#existing_product').prop('required', true);
                    $('#number_of_tiffins, #plan_price').prop('required', false);
                } else {
                    $('#existing-plan-section').hide();
                    $('#custom-plan-section').show();
                    
                    // Clear existing plan fields
                    $('#existing_product').val('');
                    $('#existing_number_of_tiffins, #existing_custom_price').val('');
                    clearExistingAddonInputs();
                    $('#existing-tiffins-row, #existing-price-row, #existing-addons-row').hide();
                    
                    // Update required fields
                    $('#existing_product').prop('required', false);
                    $('#number_of_tiffins, #plan_price').prop('required', true);
                    
                    // Generate initial plan name
                    generateCustomMealName();
                }
            });
            
            // Handle existing product selection
            $('#existing_product').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                
                if (selectedOption.val()) {
                    const productPrice = selectedOption.data('price');
                    
                    // Show additional fields
                    $('#existing-tiffins-row, #existing-price-row, #existing-addons-row').show();
                    
                    // Update price display
                    $('#product-price-display').text('?' + parseFloat(productPrice).toFixed(2));
                    
                    // Clear custom price when product changes
                    $('#existing_custom_price').val('');
                } else {
                    $('#existing-tiffins-row, #existing-price-row, #existing-addons-row').hide();
                    $('#product-price-display').text('?0.00');
                    clearExistingAddonInputs();
                }
            });
            
            // Existing Addon Functions
            function clearExistingAddonInputs() {
                $('#existing_addon_nonveg_qty, #existing_addon_nonveg_size, #existing_addon_nonveg_type').val('');
                $('#existing_addon_veg_qty, #existing_addon_veg_size, #existing_addon_veg_type').val('');
                $('#existing_addon_rotis_qty, #existing_addon_rice_qty, #existing_addon_salad_qty, #existing_addon_raita_qty').val('');
                updateExistingAddonPreview();
            }
            
            function updateExistingAddonPreview() {
                const addons = [];
                
                // Non-veg addons
                const nonvegQty = parseInt($('#existing_addon_nonveg_qty').val()) || 0;
                const nonvegSize = $('#existing_addon_nonveg_size').val();
                const nonvegType = $('#existing_addon_nonveg_type').val().trim();
                if (nonvegQty > 0 && nonvegSize && nonvegType) {
                    addons.push(`${nonvegQty} Non-Veg(${nonvegSize}) ${nonvegType}`);
                }
                
                // Veg addons
                const vegQty = parseInt($('#existing_addon_veg_qty').val()) || 0;
                const vegSize = $('#existing_addon_veg_size').val();
                const vegType = $('#existing_addon_veg_type').val().trim();
                if (vegQty > 0 && vegSize && vegType) {
                    addons.push(`${vegQty} Veg(${vegSize}) ${vegType}`);
                }
                
                // Other addons
                const rotisQty = parseInt($('#existing_addon_rotis_qty').val()) || 0;
                const riceQty = parseInt($('#existing_addon_rice_qty').val()) || 0;
                const saladQty = parseInt($('#existing_addon_salad_qty').val()) || 0;
                const raitaQty = parseInt($('#existing_addon_raita_qty').val()) || 0;
                
                if (rotisQty > 0) addons.push(`${rotisQty} Rotis`);
                if (riceQty > 0) addons.push(`${riceQty} Rice`);
                if (saladQty > 0) addons.push(`${saladQty} Salad`);
                if (raitaQty > 0) addons.push(`${raitaQty} Raita`);
                
                const previewText = addons.length > 0 ? addons.join(' + ') : 'None';
                $('#existing-addon-preview-text').text(previewText);
            }
            
            // Add event listeners for existing addon inputs
            $('#existing_addon_nonveg_qty, #existing_addon_nonveg_size, #existing_addon_nonveg_type, #existing_addon_veg_qty, #existing_addon_veg_size, #existing_addon_veg_type, #existing_addon_rotis_qty, #existing_addon_rice_qty, #existing_addon_salad_qty, #existing_addon_raita_qty').on('input change', function() {
                updateExistingAddonPreview();
            });
            
            // Custom Meal Builder Functions
            function generateCustomMealName() {
                const nonvegQty = parseInt($('#nonveg_quantity').val()) || 0;
                const nonvegSize = $('#nonveg_size').val();
                const nonvegType = $('#nonveg_type').val().trim();
                
                const vegQty = parseInt($('#veg_quantity').val()) || 0;
                const vegSize = $('#veg_size').val();
                const vegType = $('#veg_type').val().trim();
                
                const rotiQty = parseInt($('#roti_quantity').val()) || 0;
                const riceQty = parseInt($('#rice_quantity').val()) || 0;
                const raitaQty = parseInt($('#raita_quantity').val()) || 0;
                const saladQty = parseInt($('#salad_quantity').val()) || 0;
                
                let mealParts = [];
                
                // Add non-veg component
                if (nonvegQty > 0) {
                    let nonvegPart = `${nonvegQty} Non-Veg(${nonvegSize})`;
                    if (nonvegType) {
                        nonvegPart += ` ${nonvegType}`;
                    }
                    mealParts.push(nonvegPart);
                }
                
                // Add veg component
                if (vegQty > 0) {
                    let vegPart = `${vegQty} Veg(${vegSize})`;
                    if (vegType) {
                        vegPart += ` ${vegType}`;
                    }
                    mealParts.push(vegPart);
                }
                
                // Add roti component
                if (rotiQty > 0) {
                    mealParts.push(`${rotiQty} Rotis`);
                }
                
                // Add rice component
                if (riceQty > 0) {
                    mealParts.push(`${riceQty} Rice`);
                }
                
                // Add raita component
                if (raitaQty > 0) {
                    mealParts.push(`${raitaQty} Raita`);
                }
                
                // Add salad component
                if (saladQty > 0) {
                    mealParts.push(`${saladQty} Salad`);
                }
                
                let planName = 'Custom Meal';
                if (mealParts.length > 0) {
                    planName += ' - ' + mealParts.join(' + ');
                }
                
                // Update the preview and hidden field
                $('#custom_meal_preview').text(planName);
                $('#plan_name').val(planName);
                
                return planName;
            }
            
            function clearCustomMealInputs() {
                $('#nonveg_quantity, #veg_quantity, #roti_quantity, #rice_quantity, #raita_quantity, #salad_quantity').val(0);
                $('#nonveg_type, #veg_type').val('');
                $('#nonveg_size, #veg_size').val('8oz');
                $('#custom_meal_preview').text('Custom Meal - Please configure items above');
                $('#plan_name').val('');
            }
            
            // Bind change events to custom meal inputs
            $('#nonveg_quantity, #nonveg_size, #nonveg_type, #veg_quantity, #veg_size, #veg_type, #roti_quantity, #rice_quantity, #raita_quantity, #salad_quantity').on('input change keyup', function() {
                generateCustomMealName();
            });
            
            // Form submission
            $('#generate-link-form').on('submit', function(e) {
                e.preventDefault();
                
                // Validate based on plan type
                const planType = $('input[name="plan_type"]:checked').val();
                let isValid = true;
                let errorMessage = '';
                
                if (planType === 'existing') {
                    if (!$('#existing_product').val()) {
                        isValid = false;
                        errorMessage = 'Please select a product.';
                    }
                } else {
                    // Validate custom meal components
                    const nonvegQty = parseInt($('#nonveg_quantity').val()) || 0;
                    const vegQty = parseInt($('#veg_quantity').val()) || 0;
                    const rotiQty = parseInt($('#roti_quantity').val()) || 0;
                    const riceQty = parseInt($('#rice_quantity').val()) || 0;
                    const raitaQty = parseInt($('#raita_quantity').val()) || 0;
                    const saladQty = parseInt($('#salad_quantity').val()) || 0;
                    
                    if (nonvegQty === 0 && vegQty === 0 && rotiQty === 0 && riceQty === 0 && raitaQty === 0 && saladQty === 0) {
                        isValid = false;
                        errorMessage = 'Please configure at least one meal component (Non-Veg, Veg, Rotis, Rice, Raita, or Salad).';
                    } else if (!$('#number_of_tiffins').val() || !$('#plan_price').val()) {
                        isValid = false;
                        errorMessage = 'Please fill in number of tiffins and plan price.';
                    }
                }
                
                if (!isValid) {
                    alert(errorMessage);
                    return false;
                }
                
                const formData = new FormData(this);
                formData.append('action', 'generate_customer_order_link');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#generated-link').val(response.data.link);
                            $('#link-expiry').text(response.data.validity_days);
                            
                            // Update the success message with plan details
                            const planDetails = `<br><strong>Plan:</strong> ${response.data.plan_name}<br><strong>Price:</strong> ?${parseFloat(response.data.plan_price).toFixed(2)}`;
                            $('#generated-link-section p.description').html(
                                'This link will expire in <span id="link-expiry">' + response.data.validity_days + '</span> days. ' +
                                'The customer can use this link to place their order with the pre-configured plan details.' +
                                planDetails
                            );
                            
                            $('#generated-link-section').show();
                            
                            // Scroll to generated link
                            $('#generated-link-section')[0].scrollIntoView({ 
                                behavior: 'smooth' 
                            });
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while generating the link.');
                    }
                });
            });
            
            $('#copy-link').on('click', function() {
                const linkInput = document.getElementById('generated-link');
                linkInput.select();
                linkInput.setSelectionRange(0, 99999);
                document.execCommand('copy');
                
                $(this).text('Copied!').addClass('button-secondary');
                setTimeout(() => {
                    $(this).text('Copy Link').removeClass('button-secondary');
                }, 2000);
            });
            
            // Initialize on page load
            $('input[name="plan_type"]:checked').trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Render the pending orders content
     */
    public function render_pending_orders_content() {
        ?>
        <div class="auto-refresh-controls">
            <button class="button manual-refresh-btn">Refresh Now</button>
            <button class="button toggle-auto-refresh">Disable Auto-refresh</button>
            <span class="auto-refresh-status">Auto-refresh: ON (every 10s)</span>
            <span class="refresh-indicator"></span>
        </div>
        
        <div id="pending-orders-table-container">
            <?php $this->render_pending_orders_table(); ?>
        </div>
        
        <!-- Order Details Modal -->
        <div id="order-details-modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div id="order-details-content"></div>
            </div>
        </div>
        
        <style>
        .auto-refresh-controls {
            margin-bottom: 20px;
            padding: 10px;
            background: #f1f1f1;
            border-radius: 5px;
        }
        
        .auto-refresh-controls .button {
            margin-right: 10px;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            position: relative;
            border-radius: 5px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        .close:hover {
            color: black;
        }
        
        #order-details-modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .order-detail-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        .order-detail-section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .pending-orders-empty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .pending-orders-empty .dashicons {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Order action handlers
            $(document).on('click', '.view-details', function() {
                const orderId = $(this).data('order-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_pending_order_details',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#order-details-content').html(response.data.html);
                            $('#order-details-modal').show();
                        } else {
                            alert('Error loading order details: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error loading order details');
                    }
                });
            });
            
            // Close modal
            $(document).on('click', '.close, #order-details-modal', function(e) {
                if (e.target === this) {
                    $('#order-details-modal').hide();
                }
            });
            
            // Approve order
            $(document).on('click', '.approve-order', function() {
                if (!confirm('Are you sure you want to approve this order? This will create a WooCommerce order.')) {
                    return;
                }
                
                const orderId = $(this).data('order-id');
                const button = $(this);
                const originalText = button.text();
                
                button.prop('disabled', true).text('Approving...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'approve_pending_order',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Order approved successfully! WooCommerce Order ID: #' + response.data.wc_order_id);
                            // Refresh the table
                            if (typeof refreshPendingOrders === 'function') {
                                refreshPendingOrders();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error approving order: ' + response.data);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Error approving order');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Reject order
            $(document).on('click', '.reject-order', function() {
                const reason = prompt('Please provide a reason for rejection:');
                if (!reason || reason.trim() === '') {
                    alert('A reason is required to reject an order.');
                    return;
                }
                
                const orderId = $(this).data('order-id');
                const button = $(this);
                const originalText = button.text();
                
                button.prop('disabled', true).text('Rejecting...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'reject_pending_order',
                        order_id: orderId,
                        reason: reason,
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Order rejected successfully.');
                            // Refresh the table
                            if (typeof refreshPendingOrders === 'function') {
                                refreshPendingOrders();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error rejecting order: ' + response.data);
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Error rejecting order');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render the pending orders table
     */
    public function render_pending_orders_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        // Get pending orders
        $pending_orders = $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE status = 'pending' 
            ORDER BY submitted_at DESC
        ");
        
        if (empty($pending_orders)) {
            ?>
            <div class="pending-orders-empty">
                <div class="dashicons dashicons-clock"></div>
                <h3>No Pending Orders</h3>
                <p>All submitted orders have been processed. New orders will appear here when customers submit them.</p>
            </div>
            <?php
            return;
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 200px;">Customer</th>
                    <th style="width: 250px;">Plan Details</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 120px;">Submitted</th>
                    <th style="width: 250px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_orders as $order): 
                    $customer_data = json_decode($order->customer_data, true);
                    $plan_data = json_decode($order->plan_data, true);
                    $time_ago = human_time_diff(strtotime($order->submitted_at), current_time('timestamp')) . ' ago';
                ?>
                    <tr>
                        <td><strong>#<?php echo $order->id; ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($customer_data['first_name'] . ' ' . $customer_data['last_name']); ?></strong><br>
                            <small><?php echo esc_html($customer_data['email']); ?></small><br>
                            <small><?php echo esc_html($customer_data['phone']); ?></small>
                        </td>
                                                 <td>
                             <strong><?php echo esc_html($plan_data['plan_name']); ?></strong><br>
                             <small>Tiffins: <?php echo esc_html($plan_data['number_of_tiffins']); ?></small><br>
                             <small>Start: <?php echo esc_html(date('M j, Y', strtotime($customer_data['start_date']))); ?></small><br>
                             <small>Type: <?php echo esc_html($customer_data['delivery_type'] ?? 'Not specified'); ?></small>
                         </td>
                                                 <td><strong><?php echo wc_price($plan_data['plan_price']); ?></strong></td>
                        <td>
                            <span title="<?php echo date('F j, Y g:i A', strtotime($order->submitted_at)); ?>">
                                <?php echo $time_ago; ?>
                            </span>
                        </td>
                        <td>
                            <button class="button button-small view-details" data-order-id="<?php echo $order->id; ?>" title="View full order details">
                                Details
                            </button>
                            <button class="button button-primary button-small approve-order" data-order-id="<?php echo $order->id; ?>" title="Approve and create WooCommerce order">
                                Approve
                            </button>
                            <button class="button button-secondary button-small reject-order" data-order-id="<?php echo $order->id; ?>" title="Reject this order">
                                Reject
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX handler for generating customer order links
     */
    public function ajax_generate_link() {
        check_ajax_referer('generate_customer_order_link');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $plan_type = sanitize_text_field($_POST['plan_type']);
        $validity_days = absint($_POST['validity_days']);
        $upstairs_delivery_paid = isset($_POST['upstairs_delivery_paid']) && $_POST['upstairs_delivery_paid'] == '1';
        
        $plan_data = [
            'plan_type' => $plan_type,
            'validity_days' => $validity_days,
            'upstairs_delivery_paid' => $upstairs_delivery_paid,
            'created_at' => current_time('timestamp'),
            'expires_at' => current_time('timestamp') + ($validity_days * 24 * 60 * 60)
        ];
        
        if ($plan_type === 'existing') {
            // Handle existing product
            $product_id = absint($_POST['existing_product']);
            $product = wc_get_product($product_id);
            
            if (!$product) {
                wp_send_json_error('Invalid product selected');
            }
            
            // Get product details
            $plan_name = $product->get_name();
            $plan_price = !empty($_POST['existing_custom_price']) ? 
                floatval($_POST['existing_custom_price']) : 
                $product->get_price();
            $plan_description = wp_strip_all_tags($product->get_short_description());
            
            // Get number of tiffins from product meta or form input
            $number_of_tiffins = !empty($_POST['existing_number_of_tiffins']) ? 
                absint($_POST['existing_number_of_tiffins']) : 
                get_post_meta($product_id, 'Number Of Tiffins', true);
            
            // If still no tiffins found, use default
            if (empty($number_of_tiffins)) {
                $number_of_tiffins = 1;
            }
            
            // Capture structured addons and combine into a formatted string
            $addons = [];
            
            // Non-veg addons
            $nonveg_qty = intval($_POST['existing_addon_nonveg_qty'] ?? 0);
            $nonveg_size = sanitize_text_field($_POST['existing_addon_nonveg_size'] ?? '');
            $nonveg_type = sanitize_text_field($_POST['existing_addon_nonveg_type'] ?? '');
            if ($nonveg_qty > 0 && $nonveg_size && $nonveg_type) {
                $addons[] = "{$nonveg_qty} Non-Veg({$nonveg_size}) {$nonveg_type}";
            }
            
            // Veg addons
            $veg_qty = intval($_POST['existing_addon_veg_qty'] ?? 0);
            $veg_size = sanitize_text_field($_POST['existing_addon_veg_size'] ?? '');
            $veg_type = sanitize_text_field($_POST['existing_addon_veg_type'] ?? '');
            if ($veg_qty > 0 && $veg_size && $veg_type) {
                $addons[] = "{$veg_qty} Veg({$veg_size}) {$veg_type}";
            }
            
            // Other addons
            $rotis_qty = intval($_POST['existing_addon_rotis_qty'] ?? 0);
            $rice_qty = intval($_POST['existing_addon_rice_qty'] ?? 0);
            $salad_qty = intval($_POST['existing_addon_salad_qty'] ?? 0);
            $raita_qty = intval($_POST['existing_addon_raita_qty'] ?? 0);
            
            if ($rotis_qty > 0) $addons[] = "{$rotis_qty} Rotis";
            if ($rice_qty > 0) $addons[] = "{$rice_qty} Rice";
            if ($salad_qty > 0) $addons[] = "{$salad_qty} Salad";
            if ($raita_qty > 0) $addons[] = "{$raita_qty} Raita";
            
            $addons_string = !empty($addons) ? implode(' + ', $addons) : '';
            
            $plan_data = array_merge($plan_data, [
                'plan_name' => $plan_name,
                'number_of_tiffins' => $number_of_tiffins,
                'plan_price' => $plan_price,
                'plan_description' => $plan_description,
                'product_id' => $product_id,
                'is_existing_product' => true,
                'addons' => $addons_string
            ]);
            
        } else {
            // Handle custom meal plan
            $nonveg_quantity = absint($_POST['nonveg_quantity'] ?? 0);
            $nonveg_size = sanitize_text_field($_POST['nonveg_size'] ?? '8oz');
            $nonveg_type = sanitize_text_field($_POST['nonveg_type'] ?? '');
            
            $veg_quantity = absint($_POST['veg_quantity'] ?? 0);
            $veg_size = sanitize_text_field($_POST['veg_size'] ?? '8oz');
            $veg_type = sanitize_text_field($_POST['veg_type'] ?? '');
            
            $roti_quantity = absint($_POST['roti_quantity'] ?? 0);
            $rice_quantity = absint($_POST['rice_quantity'] ?? 0);
            $raita_quantity = absint($_POST['raita_quantity'] ?? 0);
            $salad_quantity = absint($_POST['salad_quantity'] ?? 0);
            
            $number_of_tiffins = absint($_POST['number_of_tiffins']);
            $plan_price = floatval($_POST['plan_price']);
            $plan_description = sanitize_textarea_field($_POST['plan_description']);
            
            // Validate that at least one component is configured
            if ($nonveg_quantity === 0 && $veg_quantity === 0 && $roti_quantity === 0 && $rice_quantity === 0 && $raita_quantity === 0 && $salad_quantity === 0) {
                wp_send_json_error('Please configure at least one meal component');
            }
            
            // Validate required fields
            if (empty($number_of_tiffins) || empty($plan_price)) {
                wp_send_json_error('Please fill in number of tiffins and plan price');
            }
            
            // Generate plan name automatically
            $meal_parts = [];
            
            if ($nonveg_quantity > 0) {
                $nonveg_part = "$nonveg_quantity Non-Veg($nonveg_size)";
                if (!empty($nonveg_type)) {
                    $nonveg_part .= " $nonveg_type";
                }
                $meal_parts[] = $nonveg_part;
            }
            
            if ($veg_quantity > 0) {
                $veg_part = "$veg_quantity Veg($veg_size)";
                if (!empty($veg_type)) {
                    $veg_part .= " $veg_type";
                }
                $meal_parts[] = $veg_part;
            }
            
            if ($roti_quantity > 0) {
                $meal_parts[] = "$roti_quantity Rotis";
            }
            
            if ($rice_quantity > 0) {
                $meal_parts[] = "$rice_quantity Rice";
            }
            
            if ($raita_quantity > 0) {
                $meal_parts[] = "$raita_quantity Raita";
            }
            
            if ($salad_quantity > 0) {
                $meal_parts[] = "$salad_quantity Salad";
            }
            
            $plan_name = 'Custom Meal';
            if (!empty($meal_parts)) {
                $plan_name .= ' - ' . implode(' + ', $meal_parts);
            }
            
            $plan_data = array_merge($plan_data, [
                'plan_name' => $plan_name,
                'number_of_tiffins' => $number_of_tiffins,
                'plan_price' => $plan_price,
                'plan_description' => $plan_description,
                'is_existing_product' => false,
                'custom_meal_components' => [
                    'nonveg' => [
                        'quantity' => $nonveg_quantity,
                        'size' => $nonveg_size,
                        'type' => $nonveg_type
                    ],
                    'veg' => [
                        'quantity' => $veg_quantity,
                        'size' => $veg_size,
                        'type' => $veg_type
                    ],
                    'roti' => [
                        'quantity' => $roti_quantity
                    ],
                    'rice' => [
                        'quantity' => $rice_quantity
                    ],
                    'raita' => [
                        'quantity' => $raita_quantity
                    ],
                    'salad' => [
                        'quantity' => $salad_quantity
                    ]
                ]
            ]);
        }
        
        // Generate unique token
        $token = wp_generate_password(32, false);
        
        // Store plan data in database
        update_option('customer_order_plan_' . $token, $plan_data);
        
        // Log the link generation
        $current_user = wp_get_current_user();
        $this->log_activity('link_generated', [
            'plan_name' => $plan_data['plan_name'],
            'price' => $plan_data['plan_price'],
            'number_of_tiffins' => $plan_data['number_of_tiffins'],
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', $plan_data['expires_at']),
            'validity_days' => $validity_days
        ], [
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'role' => 'admin'
        ], "Generated order link for plan: {$plan_data['plan_name']} - Price: $" . number_format($plan_data['plan_price'], 2) . " - Valid for {$validity_days} days");
        
        // Generate link
        $link = home_url('/customer-order/' . $token);
        
        wp_send_json_success([
            'link' => $link,
            'validity_days' => $validity_days,
            'plan_name' => $plan_data['plan_name'],
            'plan_price' => $plan_data['plan_price']
        ]);
    }
    
    /**
     * Handle customer order form display
     */
    public function handle_customer_order_form() {
        $token = get_query_var('customer_order_token');
        
        if (!$token) {
            return;
        }
        
        // Check if this is a request for order confirmation page
        if (isset($_GET['order_success']) && $_GET['order_success'] === '1') {
            $this->render_order_success_page($token);
            exit;
        }
        
        // Get plan data
        $this->plan_data = get_option('customer_order_plan_' . $token);
        
        if (!$this->plan_data) {
            wp_die('Invalid or expired order link.');
        }
        
        // Check if link has expired
        if (current_time('timestamp') > $this->plan_data['expires_at']) {
            wp_die('This order link has expired. Please contact the salesperson for a new link.');
        }
        
        // Check if token has already been used
        if (isset($this->plan_data['used']) && $this->plan_data['used'] === true) {
            $this->render_order_already_placed_page($token);
            exit;
        }
        
        // Display the customer order form
        $this->render_customer_order_form($token);
        exit;
    }
    
    /**
     * Render the customer-facing order form
     */
    private function render_customer_order_form($token) {
        // Check if this is a switch plan (customer selects plan and tiffins)
        $is_switch_plan = isset($this->plan_data['plan_type']) && $this->plan_data['plan_type'] === 'switch_plan';
        
        // Check if this is a trial meal link (customer selects from trial meal products)
        $is_trial_meal_link = isset($this->plan_data['plan_type']) && $this->plan_data['plan_type'] === 'trial_meal';
        
        // Check if this is a trial meal with 1 tiffin (legacy check)
        $is_trial_meal = !$is_switch_plan && !$is_trial_meal_link ? $this->is_trial_meal() : false;
        $hide_preferred_days = !$is_switch_plan && !$is_trial_meal_link && $is_trial_meal && isset($this->plan_data['number_of_tiffins']) && $this->plan_data['number_of_tiffins'] == 1;
        
        $switch_plan_tiffins = $is_switch_plan ? $this->get_switch_plan_tiffin_packages() : [];
        $switch_plan_products = $is_switch_plan ? $this->get_switch_plan_allowed_products() : [];
        $trial_meal_products = $is_trial_meal_link ? $this->get_trial_meal_allowed_products() : [];
        $trial_meal_tiffins = $is_trial_meal_link ? $this->get_trial_meal_tiffin_packages() : [];
        
        // Tax settings
        $switch_plan_tax_enabled = $is_switch_plan ? $this->is_switch_plan_tax_enabled() : false;
        $trial_meal_tax_enabled = $is_trial_meal_link ? $this->is_trial_meal_tax_enabled() : false;
        $tax_rate = ($switch_plan_tax_enabled || $trial_meal_tax_enabled) ? $this->get_tax_rate() : 0;
        
        // Check if this is an auto-renewal with a calculated start date
        $is_auto_renewal = isset($this->plan_data['is_auto_renewal']) && $this->plan_data['is_auto_renewal'] === true;
        $calculated_start_date = isset($this->plan_data['calculated_start_date']) ? $this->plan_data['calculated_start_date'] : '';

        get_header();
        ?>
        
        <div class="customer-order-container">
            <div class="customer-order-form">
                <?php if ($is_switch_plan): ?>
                    <!-- Switch Plan Header -->
                    <div class="plan-info">
                        <h2>Switch to New Plan</h2>
                        <p class="plan-description">Select your preferred plan and number of tiffins. Price will be calculated automatically.</p>
                    </div>
                <?php elseif ($is_trial_meal_link): ?>
                    <!-- Trial Meal Header -->
                    <div class="plan-info">
                        <h2>Trial Meal Order</h2>
                        <p class="plan-description">Select a trial meal plan from the options below.</p>
                    </div>
                <?php else: ?>
                    <!-- Regular Plan Header -->
                    <div class="plan-info">
                        <h2><?php echo esc_html($this->plan_data['plan_name']); ?></h2>
                        <?php if (!empty($this->plan_data['plan_description'])): ?>
                            <p class="plan-description"><?php echo esc_html($this->plan_data['plan_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="plan-details">
                            <div class="plan-detail">
                                <span class="label">Number of Tiffins:</span>
                                <span class="value"><?php echo esc_html($this->plan_data['number_of_tiffins']); ?></span>
                            </div>
                            <div class="plan-detail">
                                <span class="label">Total Price:</span>
                                <span class="value price">$<?php echo number_format($this->plan_data['plan_price'], 2); ?></span>
                            </div>
                            <?php if (isset($this->plan_data['upstairs_delivery_paid']) && $this->plan_data['upstairs_delivery_paid']): ?>
                                <div class="plan-detail">
                                    <span class="label">Special Service:</span>
                                    <span class="value special">? Upstairs Delivery Included</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form id="customer-order-form" method="post">
                    <?php wp_nonce_field('submit_customer_order'); ?>
                    <input type="hidden" name="order_token" value="<?php echo esc_attr($token); ?>">
                    
                    <div class="form-section">
                        <h3>Your Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="first_name">First Name *</label>
                                <input type="text" name="first_name" id="first_name" required>
                            </div>
                            <div class="form-group half">
                                <label for="last_name">Last Name *</label>
                                <input type="text" name="last_name" id="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" name="phone" id="phone" required>
                            </div>
                            <div class="form-group half">
                                <label for="email">Email Address *</label>
                                <input type="email" name="email" id="email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="company">Company (Optional)</label>
                            <input type="text" name="company" id="company">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Delivery Address</h3>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="unit_number">Unit Number *</label>
                                <input type="text" name="unit_number" id="unit_number" required placeholder="e.g., 101, A-5">
                            </div>
                            <div class="form-group half">
                                <label for="street_name">Street Name *</label>
                                <input type="text" name="street_name" id="street_name" required placeholder="e.g., Main Street">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="city">City *</label>
                                <input type="text" name="city" id="city" required placeholder="e.g., Toronto">
                            </div>
                            <div class="form-group half">
                                <label for="postcode">Postal Code *</label>
                                <input type="text" name="postcode" id="postcode" required placeholder="e.g., M1M 1M1">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="state">State/Province *</label>
                                <select name="state" id="state" required>
                                    <option value="">Select State</option>
                                    <?php
                                    $countries_obj = new WC_Countries();
                                    $states = $countries_obj->get_states('CA');
                                    foreach ($states as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group half">
                                <label for="country">Country *</label>
                                <select name="country" id="country" required>
                                    <option value="CA" selected>Canada</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address_2">Address Line 2 (Optional)</label>
                            <input type="text" name="address_2" id="address_2" placeholder="Additional address information">
                        </div>
                        
                        <!-- Hidden field to store the combined address -->
                        <input type="hidden" name="address_1" id="address_1">
                    </div>
                    
                    <?php if ($is_switch_plan): ?>
                    <!-- Switch Plan Selection Section -->
                    <div class="form-section">
                        <h3>Select Your Plan</h3>
                        
                        <div class="form-group">
                            <label for="switch_plan_product">Select Plan *</label>
                            <select name="switch_plan_product" id="switch_plan_product" class="regular-text" required style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 16px;">
                                <option value="">-- Select a Plan --</option>
                                <?php
                                $products = wc_get_products([
                                    'status' => 'publish', 
                                    'limit' => -1,
                                    'type' => ['simple', 'variable'],
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                ]);
                                $restrict_products = $is_switch_plan && !empty($switch_plan_products);
                                $has_allowed_product = false;
                                foreach ($products as $product) {
                                    if ($restrict_products && !in_array($product->get_id(), $switch_plan_products, true)) {
                                        continue;
                                    }
                                    $price = $product->get_price();
                                    echo '<option value="' . esc_attr($product->get_id()) . '" 
                                            data-name="' . esc_attr($product->get_name()) . '"
                                            data-price="' . esc_attr($price) . '"
                                            data-description="' . esc_attr(wp_strip_all_tags($product->get_short_description())) . '">
                                        ' . esc_html($product->get_name()) . ' - ' . wc_price($price) . ' per tiffin
                                    </option>';
                                    $has_allowed_product = true;
                                }
                                ?>
                            </select>
                            <?php if ($is_switch_plan && !empty($switch_plan_products) && !$has_allowed_product): ?>
                                <p class="description" style="color:#d63638;">No products are enabled for switch plan links. Please select allowed products under WooCommerce ? Switch Plan Packages.</p>
                            <?php else: ?>
                            <p class="description">Select the plan you want to switch to</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group" id="switch_plan_tiffins_group" style="display: none;">
                            <label for="switch_plan_tiffins">Number of Tiffins *</label>
                            <select name="switch_plan_tiffins" id="switch_plan_tiffins" required 
                                    style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 16px;">
                                <option value="">-- Select Number of Tiffins --</option>
                                <?php foreach ($switch_plan_tiffins as $package): ?>
                                    <option value="<?php echo esc_attr($package); ?>">
                                        <?php echo esc_html($package); ?> Tiffins
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select one of the available tiffin packages.</p>
                        </div>
                        
                        <div class="form-group" id="switch_plan_addons_group" style="display: none; margin-top: 20px;">
                            <label>Add-ons (Optional)</label>
                            <div id="switch_plan_addons_list" style="display: grid; gap: 15px; margin-top: 10px;">
                                <?php
                                // Get active generated link addons (separate from meal addons)
                                if (function_exists('get_active_generated_link_addons')) {
                                    $addons = get_active_generated_link_addons();
                                    if (empty($addons)) {
                                        echo '<p style="color: #6c757d; font-style: italic;">No add-ons available at the moment. Please add addons from the "Link Addons" menu in WordPress admin.</p>';
                                    } else {
                                        foreach ($addons as $addon):
                                            $addon_id = $addon['id'];
                                            $addon_name = $addon['name'];
                                            $addon_price = floatval($addon['price']);
                                            $max_quantity = !empty($addon['max_quantity']) ? intval($addon['max_quantity']) : 20;
                                ?>
                                    <div class="switch-addon-item" style="display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px solid #e9ecef;">
                                        <div style="flex: 1;">
                                            <strong style="display: block; margin-bottom: 5px; color: #2c3e50;"><?php echo esc_html($addon_name); ?></strong>
                                            <small style="color: #6c757d;">Price: <?php echo wc_price($addon_price); ?> per unit</small>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <label style="margin: 0; font-size: 14px; color: #495057;">Qty:</label>
                                            <input 
                                                type="number" 
                                                class="switch-addon-qty" 
                                                data-addon-id="<?php echo esc_attr($addon_id); ?>"
                                                data-addon-name="<?php echo esc_attr($addon_name); ?>"
                                                data-addon-price="<?php echo esc_attr($addon_price); ?>"
                                                min="0" 
                                                max="<?php echo esc_attr($max_quantity); ?>" 
                                                value="0"
                                                style="width: 70px; padding: 8px; border: 2px solid #ced4da; border-radius: 4px; text-align: center;"
                                            >
                                        </div>
                                    </div>
                                <?php 
                                        endforeach;
                                    }
                                } else {
                                    echo '<p style="color: #6c757d; font-style: italic;">Addon system not available. Please ensure the customer order form is properly loaded.</p>';
                                }
                                ?>
                            </div>
                            <p class="description" style="margin-top: 10px;">Select add-ons to include with your order. Prices will be calculated per tiffin.</p>
                        </div>
                        
                        <div class="form-group" id="switch_plan_price_display" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #28a745;">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Price Calculation</h4>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span>Price per Tiffin:</span>
                                <strong id="switch_plan_unit_price">$0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span>Number of Tiffins:</span>
                                <strong id="switch_plan_tiffins_count">0</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                                <span>Plan Subtotal:</span>
                                <strong id="switch_plan_plan_subtotal">$0.00</strong>
                            </div>
                            <div id="switch_plan_addons_breakdown" style="display: none; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                                <div style="margin-bottom: 5px; font-size: 14px; color: #6c757d;">Add-ons:</div>
                                <div id="switch_plan_addons_list_display" style="font-size: 13px; color: #495057;"></div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                                    <span>Add-ons Total:</span>
                                    <strong id="switch_plan_addons_total">$0.00</strong>
                                </div>
                            </div>
                            <?php if ($switch_plan_tax_enabled && $tax_rate > 0): ?>
                            <div id="switch_plan_tax_breakdown" style="display: none; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Subtotal:</span>
                                    <strong id="switch_plan_subtotal_before_tax">$0.00</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                    <span>Tax (<?php echo esc_html(number_format($tax_rate, 2)); ?>%):</span>
                                    <strong id="switch_plan_tax_amount">$0.00</strong>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 2px solid #dee2e6; margin-top: 10px;">
                                <span style="font-size: 18px; font-weight: bold;">Total Price:</span>
                                <strong id="switch_plan_total_price" style="font-size: 24px; color: #28a745;">$0.00</strong>
                            </div>
                        </div>
                        
                        <!-- Hidden fields to store calculated values -->
                        <input type="hidden" name="switch_plan_name" id="switch_plan_name">
                        <input type="hidden" name="switch_plan_price" id="switch_plan_price">
                        <input type="hidden" name="switch_plan_description" id="switch_plan_description">
                        <input type="hidden" name="switch_plan_addons" id="switch_plan_addons" value="">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_trial_meal_link): ?>
                    <!-- Trial Meal Selection Section -->
                    <div class="form-section">
                        <h3>Select Trial Meal Plan</h3>
                        
                        <div class="form-group">
                            <label for="trial_meal_product">Select Trial Meal *</label>
                            <select name="trial_meal_product" id="trial_meal_product" class="regular-text" required style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 16px;">
                                <option value="">-- Select a Trial Meal --</option>
                                <?php
                                $products = wc_get_products([
                                    'status' => 'publish', 
                                    'limit' => -1,
                                    'type' => ['simple', 'variable'],
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                ]);
                                $restrict_products = $is_trial_meal_link && !empty($trial_meal_products);
                                $has_allowed_product = false;
                                foreach ($products as $product) {
                                    if ($restrict_products && !in_array($product->get_id(), $trial_meal_products, true)) {
                                        continue;
                                    }
                                    $price = $product->get_price();
                                    echo '<option value="' . esc_attr($product->get_id()) . '" 
                                            data-name="' . esc_attr($product->get_name()) . '"
                                            data-price="' . esc_attr($price) . '"
                                            data-description="' . esc_attr(wp_strip_all_tags($product->get_short_description())) . '">
                                        ' . esc_html($product->get_name()) . ' - ' . wc_price($price) . '
                                    </option>';
                                    $has_allowed_product = true;
                                }
                                ?>
                            </select>
                            <?php if ($is_trial_meal_link && !empty($trial_meal_products) && !$has_allowed_product): ?>
                                <p class="description" style="color:#d63638;">No trial meal products are configured. Please select trial meal products under WooCommerce ? Trial Meal Products.</p>
                            <?php else: ?>
                            <p class="description">Select the trial meal plan you want to order</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group" id="trial_meal_tiffins_group" style="display: none;">
                            <label for="trial_meal_tiffins">Number of Tiffins *</label>
                            <select name="trial_meal_tiffins" id="trial_meal_tiffins" required 
                                    style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 6px; font-size: 16px;">
                                <option value="">-- Select Number of Tiffins --</option>
                                <?php foreach ($trial_meal_tiffins as $package): ?>
                                    <option value="<?php echo esc_attr($package); ?>">
                                        <?php echo esc_html($package); ?> Tiffins
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select one of the available tiffin packages.</p>
                        </div>
                        
                        <div class="form-group" id="trial_meal_price_display" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #28a745;">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Price Calculation</h4>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span>Price per Tiffin:</span>
                                <strong id="trial_meal_unit_price">$0.00</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span>Number of Tiffins:</span>
                                <strong id="trial_meal_tiffins_count">0</strong>
                            </div>
                            <?php if ($trial_meal_tax_enabled && $tax_rate > 0): ?>
                            <div id="trial_meal_tax_breakdown" style="display: none; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Subtotal:</span>
                                    <strong id="trial_meal_subtotal_before_tax">$0.00</strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                    <span>Tax (<?php echo esc_html(number_format($tax_rate, 2)); ?>%):</span>
                                    <strong id="trial_meal_tax_amount">$0.00</strong>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 2px solid #dee2e6; margin-top: 10px;">
                                <span style="font-size: 18px; font-weight: bold;">Total Price:</span>
                                <strong id="trial_meal_total_price" style="font-size: 24px; color: #28a745;">$0.00</strong>
                            </div>
                        </div>
                        
                        <!-- Hidden fields to store selected values -->
                        <input type="hidden" name="trial_meal_name" id="trial_meal_name">
                        <input type="hidden" name="trial_meal_price" id="trial_meal_price">
                        <input type="hidden" name="trial_meal_description" id="trial_meal_description">
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-section">
                        <h3>Order Preferences</h3>
                        
                        <?php if ($is_auto_renewal && !empty($calculated_start_date)): ?>
                        <div class="auto-renewal-notice" style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                            <p style="margin: 0; color: #2e7d32;">
                                <strong>?? Renewal Start Date:</strong> Your new plan is scheduled to start on 
                                <strong><?php echo esc_html(date('F j, Y', strtotime($calculated_start_date))); ?></strong>, 
                                right after your current subscription ends. You can change this date if needed.
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="start_date">Preferred Start Date *</label>
                                <input type="text" name="start_date" id="start_date" required 
                                       placeholder="Select start date" readonly>
                            </div>
                            <div class="form-group half">
                                <label for="meal_type">Meal Type *</label>
                                <select name="meal_type" id="meal_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Veg">Vegetarian</option>
                                    <option value="Non-Veg">Non-Vegetarian</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="delivery_type">Delivery Options *</label>
                                <select name="delivery_type" id="delivery_type" required>
                                    <option value="">Select Delivery Options</option>
                                    <?php if ($is_switch_plan || (isset($this->plan_data['upstairs_delivery_paid']) && $this->plan_data['upstairs_delivery_paid'])): ?>
                                        <option value="Upstairs Delivery">Upstairs Delivery</option>
                                        <option value="Delivery at Reception/Concierge">Delivery at Reception/Concierge</option>
                                        <option value="Delivery at House">Delivery at House</option>
                                        <option value="Delivery at Basement/Back Door/Side Door">Delivery at Basement/Back Door/Side Door</option>
                                    <?php else: ?>
                                        <option value="Delivery at Reception/Concierge">Delivery at Reception/Concierge</option>
                                        <option value="Delivery at House">Delivery at House</option>
                                        <option value="Delivery at Basement/Back Door/Side Door">Delivery at Basement/Back Door/Side Door</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (!$hide_preferred_days): ?>
                        <div class="form-group" id="preferred_days_group" <?php echo $is_trial_meal_link ? 'style="display: none;"' : ''; ?>>
                            <label for="preferred_days">Prefered Days *</label>
                            <div class="day-picker" id="day-picker">
                                <div class="day-button" data-day="Monday">
                                    <span class="day-short">MON</span>
                                    <span class="day-full">Monday</span>
                                </div>
                                <div class="day-button" data-day="Tuesday">
                                    <span class="day-short">TUE</span>
                                    <span class="day-full">Tuesday</span>
                                </div>
                                <div class="day-button" data-day="Wednesday">
                                    <span class="day-short">WED</span>
                                    <span class="day-full">Wednesday</span>
                                </div>
                                <div class="day-button" data-day="Thursday">
                                    <span class="day-short">THU</span>
                                    <span class="day-full">Thursday</span>
                                </div>
                                <div class="day-button" data-day="Friday">
                                    <span class="day-short">FRI</span>
                                    <span class="day-full">Friday</span>
                                </div>
                                <div class="day-button" data-day="Saturday">
                                    <span class="day-short">SAT</span>
                                    <span class="day-full">Saturday</span>
                                </div>
                                <div class="day-button" data-day="Sunday">
                                    <span class="day-short">SUN</span>
                                    <span class="day-full">Sunday</span>
                                </div>
                            </div>
                            <input type="hidden" name="preferred_days_selected" id="preferred_days_selected" required>
                            <small>Tap days to select your preferred delivery days</small>
                        </div>
                        <?php if ($is_trial_meal_link): ?>
                        <!-- Hidden field for trial meals with 1 tiffin - auto-set to Monday-Friday -->
                        <input type="hidden" name="preferred_days_selected_hidden" id="preferred_days_selected_hidden" value="Monday - Friday">
                        <?php endif; ?>
                        <?php else: ?>
                        <!-- Hidden field for trial meals - auto-set to Monday-Friday -->
                        <input type="hidden" name="preferred_days_selected" id="preferred_days_selected" value="Monday - Friday">
                        <div class="trial-meal-notice">
                            <h4>?? Trial Meal Delivery</h4>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="delivery_notes">Special Instructions / Delivery Notes *</label>
                            <textarea name="delivery_notes" id="delivery_notes" rows="4" 
                                      placeholder="delivery preferences ........" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="order-summary">
                            <h3>Order Summary</h3>
                            <?php if ($is_switch_plan): ?>
                                <div class="summary-row" id="switch_summary_plan" style="display: none;">
                                    <span>Plan:</span>
                                    <span id="switch_summary_plan_name">-</span>
                                </div>
                                <div class="summary-row" id="switch_summary_tiffins" style="display: none;">
                                    <span>Number of Tiffins:</span>
                                    <span id="switch_summary_tiffins_count">-</span>
                                </div>
                                <div class="summary-row" id="switch_summary_addons" style="display: none;">
                                    <span>Add-ons:</span>
                                    <span id="switch_summary_addons_text">None</span>
                                </div>
                                <div class="summary-row total" id="switch_summary_total" style="display: none;">
                                    <span>Total Amount:</span>
                                    <span id="switch_summary_total_price">$0.00</span>
                                </div>
                                <div id="switch_summary_empty" style="text-align: center; padding: 20px; color: #6c757d;">
                                    <p>Please select a plan and enter number of tiffins above</p>
                                </div>
                            <?php elseif ($is_trial_meal_link): ?>
                                <div class="summary-row" id="trial_meal_summary_plan" style="display: none;">
                                    <span>Plan:</span>
                                    <span id="trial_meal_summary_plan_name">-</span>
                                </div>
                                <div class="summary-row" id="trial_meal_summary_tiffins" style="display: none;">
                                    <span>Number of Tiffins:</span>
                                    <span id="trial_meal_summary_tiffins_count">-</span>
                                </div>
                                <div class="summary-row total" id="trial_meal_summary_total" style="display: none;">
                                    <span>Total Amount:</span>
                                    <span id="trial_meal_summary_total_price">$0.00</span>
                                </div>
                                <div id="trial_meal_summary_empty" style="text-align: center; padding: 20px; color: #6c757d;">
                                    <p>Please select a trial meal plan and number of tiffins above</p>
                                </div>
                            <?php else: ?>
                                <div class="summary-row">
                                    <span>Plan:</span>
                                    <span><?php echo esc_html(isset($this->plan_data['plan_name']) ? $this->plan_data['plan_name'] : ''); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Number of Tiffins:</span>
                                    <span><?php echo esc_html(isset($this->plan_data['number_of_tiffins']) ? $this->plan_data['number_of_tiffins'] : ''); ?></span>
                                </div>
                                <?php if (!empty($this->plan_data['addons'])): ?>
                                    <div class="summary-row">
                                        <span>Add-ons:</span>
                                        <span><?php echo esc_html($this->plan_data['addons']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($this->plan_data['upstairs_delivery_paid']) && $this->plan_data['upstairs_delivery_paid']): ?>
                                    <div class="summary-row">
                                        <span>Special Service:</span>
                                        <span>? Upstairs Delivery Included</span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row total">
                                    <span>Total Amount:</span>
                                    <span>$<?php echo number_format(isset($this->plan_data['plan_price']) ? $this->plan_data['plan_price'] : 0, 2); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-order-btn" id="submit-order-btn">
                            Place Order
                        </button>
                    </div>
                </form>
                
                <div id="form-messages" class="form-messages"></div>
            </div>
        </div>
        
        <style>
        .customer-order-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .customer-order-form {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .plan-info {
            background: #f8f9fa;
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .plan-info h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }
        
        .plan-description {
            color: #6c757d;
            margin: 0 0 20px 0;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .plan-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .plan-detail {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .plan-detail .label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
        }
        
        .plan-detail .value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .plan-detail .value.price {
            color: #28a745;
            font-size: 24px;
        }
        
        .plan-detail .value.special {
            color: #007cba;
            font-weight: 600;
            background: #e3f2fd;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-section {
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        .form-group.half {
            flex: 0 0 calc(50% - 10px);
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        
                 .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007cba;
        }
        
        /* Error styling for invalid fields */
        .form-group.error input,
        .form-group.error select,
        .form-group.error textarea,
        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #dc3545 !important;
            background-color: #f8d7da;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-group.error label {
            color: #dc3545;
            font-weight: bold;
        }
        
        .form-group.error input::placeholder,
        .form-group.error textarea::placeholder {
            color: #dc3545;
            opacity: 0.7;
        }
        
        /* Error styling for day picker */
        .form-group.error .day-picker {
            border: 2px solid #dc3545;
            background-color: #f8d7da;
            border-radius: 8px;
            padding: 10px;
        }
        
        .form-group.error .day-button {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        
        /* Pulse animation for error fields */
        .form-group.error input,
        .form-group.error select,
        .form-group.error textarea,
        .form-group.error .day-picker {
            animation: errorPulse 0.5s ease-in-out;
        }
        
        @keyframes errorPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        /* Flatpickr custom styling */
        .flatpickr-input {
            cursor: pointer;
        }
        
        .flatpickr-input:focus {
            border-color: #007cba !important;
        }
        
        .flatpickr-calendar {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .form-group.error .day-picker {
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 10px;
            background: #f8d7da;
        }
        
        .form-group.error .day-button {
            border-color: #dc3545;
        }
        
        .trial-meal-notice {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #007cba;
        }
        
        .trial-meal-notice h4 {
            margin: 0 0 10px 0;
            color: #0c5460;
            font-size: 16px;
            font-weight: 600;
        }
        
        .trial-meal-notice p {
            margin: 0;
            color: #0c5460;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .addon-components {
            max-width: 600px;
        }
        
        .addon-component {
            margin-bottom: 20px;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .addon-component:hover {
            border-color: #007cba;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .addon-component label {
            display: block;
            font-weight: 700;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .component-inputs {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .component-inputs input,
        .component-inputs select {
            border: 2px solid #ced4da;
            border-radius: 8px;
            padding: 10px 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .component-inputs input:focus,
        .component-inputs select:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            outline: none;
        }
        
        .component-inputs input:hover,
        .component-inputs select:hover {
            border-color: #007cba;
        }
        
        .addon-preview {
            font-family: 'Courier New', monospace;
            border: 2px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .addon-preview:hover {
            border-color: #007cba;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .addon-preview strong {
            color: #2c3e50;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        #existing-addon-preview-text {
            color: #495057;
            font-weight: 500;
            font-size: 13px;
        }
        
        .other-addons-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .other-addon-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .other-addon-item:hover {
            border-color: #007cba;
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .other-addon-item label {
            min-width: 70px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            font-size: 14px;
            text-transform: capitalize;
        }
        
        .other-addon-item input[type="number"] {
            border: 2px solid #ced4da;
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .other-addon-item input[type="number"]:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            outline: none;
        }
        
        .other-addon-item input[type="number"]:hover {
            border-color: #007cba;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Day Picker Styles */
        .day-picker {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .day-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 4px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            min-height: 60px;
            position: relative;
        }
        
        .day-button:hover {
            border-color: #007cba;
            background: #f8f9fa;
        }
        
        .day-button.selected {
            border-color: #007cba;
            background: #007cba;
            color: white;
        }
        
        .day-button .day-short {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .day-button .day-full {
            font-size: 10px;
            margin-top: 2px;
            opacity: 0.8;
        }
        
        .day-button.selected .day-full {
            opacity: 1;
        }
        
        .day-button.disabled {
            pointer-events: none;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(5px); }
            20% { opacity: 1; transform: translateX(-50%) translateY(0); }
            80% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-5px); }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .day-picker {
                grid-template-columns: repeat(7, 1fr);
                gap: 6px;
            }
            
            .day-button {
                padding: 10px 2px;
                min-height: 55px;
            }
            
            .day-button .day-short {
                font-size: 11px;
            }
            
            .day-button .day-full {
                font-size: 9px;
            }
        }
        
        @media (max-width: 480px) {
            .day-picker {
                grid-template-columns: repeat(7, 1fr);
                gap: 4px;
            }
            
            .day-button {
                padding: 8px 1px;
                min-height: 50px;
            }
            
            .day-button .day-short {
                font-size: 10px;
            }
            
            .day-button .day-full {
                display: none; /* Hide full day names on very small screens */
            }
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .order-summary h3 {
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .summary-row.total {
            font-weight: 600;
            font-size: 18px;
            color: #28a745;
            border-top: 2px solid #28a745;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .form-actions {
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .submit-order-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            min-width: 200px;
        }
        
        .submit-order-btn:hover {
            background: #218838;
        }
        
        .submit-order-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .form-messages {
            margin: 20px 30px;
            padding: 15px;
            border-radius: 6px;
            display: none;
        }
        
        .form-messages.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-messages.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .customer-order-container {
                margin: 20px auto;
                padding: 0 10px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-group.half {
                flex: 1;
            }
            
            .plan-details {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        /* Addon Components Styling */
        .addon-components {
            max-width: 600px;
        }
        
        .addon-component {
            margin-bottom: 20px;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .addon-component:hover {
            border-color: #007cba;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .addon-component label {
            display: block;
            font-weight: 700;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .component-inputs {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .component-inputs input,
        .component-inputs select {
            border: 2px solid #ced4da;
            border-radius: 8px;
            padding: 10px 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .component-inputs input:focus,
        .component-inputs select:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            outline: none;
        }
        
        .component-inputs input:hover,
        .component-inputs select:hover {
            border-color: #007cba;
        }
        
        .addon-preview {
            font-family: 'Courier New', monospace;
            border: 2px solid #e9ecef;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .addon-preview:hover {
            border-color: #007cba;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .addon-preview strong {
            color: #2c3e50;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        #existing-addon-preview-text {
            color: #495057;
            font-weight: 500;
            font-size: 13px;
        }
        
        .other-addons-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .other-addon-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .other-addon-item:hover {
            border-color: #007cba;
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .other-addon-item label {
            min-width: 70px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            font-size: 14px;
            text-transform: capitalize;
        }
        
        .other-addon-item input[type="number"] {
            border: 2px solid #ced4da;
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .other-addon-item input[type="number"]:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
            outline: none;
        }
        
        .other-addon-item input[type="number"]:hover {
            border-color: #007cba;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('customer-order-form');
            const submitBtn = document.getElementById('submit-order-btn');
            const messages = document.getElementById('form-messages');
            let orderSubmitted = false;
            
            // Switch Plan functionality (if this is a switch plan form)
            const isSwitchPlan = <?php echo $is_switch_plan ? 'true' : 'false'; ?>;
            const switchPlanTaxEnabled = <?php echo ($switch_plan_tax_enabled && $tax_rate > 0) ? 'true' : 'false'; ?>;
            const switchPlanTaxRate = <?php echo floatval($tax_rate); ?>;
            
            if (isSwitchPlan) {
                const planSelect = document.getElementById('switch_plan_product');
                const tiffinsSelect = document.getElementById('switch_plan_tiffins');
                const tiffinsGroup = document.getElementById('switch_plan_tiffins_group');
                const addonsGroup = document.getElementById('switch_plan_addons_group');
                const priceDisplay = document.getElementById('switch_plan_price_display');
                const unitPriceDisplay = document.getElementById('switch_plan_unit_price');
                const tiffinsCountDisplay = document.getElementById('switch_plan_tiffins_count');
                const planSubtotalDisplay = document.getElementById('switch_plan_plan_subtotal');
                const addonsBreakdown = document.getElementById('switch_plan_addons_breakdown');
                const addonsListDisplay = document.getElementById('switch_plan_addons_list_display');
                const addonsTotalDisplay = document.getElementById('switch_plan_addons_total');
                const totalPriceDisplay = document.getElementById('switch_plan_total_price');
                const planNameInput = document.getElementById('switch_plan_name');
                const planPriceInput = document.getElementById('switch_plan_price');
                const planDescInput = document.getElementById('switch_plan_description');
                const addonsInput = document.getElementById('switch_plan_addons');
                
                // Summary elements
                const summaryPlan = document.getElementById('switch_summary_plan');
                const summaryPlanName = document.getElementById('switch_summary_plan_name');
                const summaryTiffins = document.getElementById('switch_summary_tiffins');
                const summaryTiffinsCount = document.getElementById('switch_summary_tiffins_count');
                const summaryTotal = document.getElementById('switch_summary_total');
                const summaryTotalPrice = document.getElementById('switch_summary_total_price');
                const summaryAddons = document.getElementById('switch_summary_addons');
                const summaryAddonsText = document.getElementById('switch_summary_addons_text');
                const summaryEmpty = document.getElementById('switch_summary_empty');
                
                const getTiffinsCount = () => parseInt(tiffinsSelect.value, 10) || 0;

                function calculateAddonsPrice() {
                    const tiffins = getTiffinsCount();
                    let addonsTotal = 0;
                    const selectedAddons = [];
                    const addonsList = [];
                    
                    // Get all addon quantity inputs
                    const addonInputs = document.querySelectorAll('.switch-addon-qty');
                    addonInputs.forEach(function(input) {
                        const quantity = parseInt(input.value) || 0;
                        if (quantity > 0) {
                            const addonId = input.dataset.addonId;
                            const addonName = input.dataset.addonName;
                            const addonPrice = parseFloat(input.dataset.addonPrice) || 0;
                            
                            // Calculate: price × quantity × tiffins
                            const addonTotal = addonPrice * quantity * tiffins;
                            addonsTotal += addonTotal;
                            
                            selectedAddons.push({
                                id: addonId,
                                name: addonName,
                                quantity: quantity,
                                price: addonPrice
                            });
                            
                            addonsList.push({
                                name: addonName,
                                quantity: quantity,
                                unitPrice: addonPrice,
                                total: addonTotal
                            });
                        }
                    });
                    
                    // Update addons breakdown display
                    if (addonsList.length > 0) {
                        let html = '';
                        addonsList.forEach(function(addon) {
                            html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                            html += '<span>' + addon.name + ' (Qty: ' + addon.quantity + ' × $' + addon.unitPrice.toFixed(2) + ' × ' + tiffins + ' tiffins):</span>';
                            html += '<strong>$' + addon.total.toFixed(2) + '</strong>';
                            html += '</div>';
                        });
                        addonsListDisplay.innerHTML = html;
                        addonsBreakdown.style.display = 'block';
                    } else {
                        addonsBreakdown.style.display = 'none';
                    }
                    
                    addonsTotalDisplay.textContent = '$' + addonsTotal.toFixed(2);
                    
                    // Save addons as JSON
                    addonsInput.value = JSON.stringify(selectedAddons);
                    
                    return addonsTotal;
                }
                
                function calculatePrice() {
                    if (!planSelect.value || !tiffinsSelect.value) {
                        priceDisplay.style.display = 'none';
                        summaryEmpty.style.display = 'block';
                        summaryPlan.style.display = 'none';
                        summaryTiffins.style.display = 'none';
                        summaryAddons.style.display = 'none';
                        summaryTotal.style.display = 'none';
                        return;
                    }
                    
                    const selectedOption = planSelect.options[planSelect.selectedIndex];
                    const unitPrice = parseFloat(selectedOption.dataset.price) || 0;
                    const tiffins = getTiffinsCount();
                    const planSubtotal = unitPrice * tiffins;
                    
                    // Calculate addons price
                    const addonsTotal = calculateAddonsPrice();
                    
                    // Subtotal before tax = plan subtotal + addons total
                    const subtotal = planSubtotal + addonsTotal;
                    
                    // Calculate tax if enabled
                    let tax = 0;
                    let totalPrice = subtotal;
                    if (switchPlanTaxEnabled && switchPlanTaxRate > 0) {
                        tax = subtotal * (switchPlanTaxRate / 100);
                        totalPrice = subtotal + tax;
                        
                        // Update tax breakdown display
                        const taxBreakdown = document.getElementById('switch_plan_tax_breakdown');
                        const subtotalBeforeTax = document.getElementById('switch_plan_subtotal_before_tax');
                        const taxAmount = document.getElementById('switch_plan_tax_amount');
                        if (taxBreakdown && subtotalBeforeTax && taxAmount) {
                            taxBreakdown.style.display = 'block';
                            subtotalBeforeTax.textContent = '$' + subtotal.toFixed(2);
                            taxAmount.textContent = '$' + tax.toFixed(2);
                        }
                    }
                    
                    if (tiffins > 0 && unitPrice > 0) {
                        // Update price display
                        unitPriceDisplay.textContent = '$' + unitPrice.toFixed(2);
                        tiffinsCountDisplay.textContent = tiffins;
                        planSubtotalDisplay.textContent = '$' + planSubtotal.toFixed(2);
                        totalPriceDisplay.textContent = '$' + totalPrice.toFixed(2);
                        priceDisplay.style.display = 'block';
                        
                        // Update hidden fields
                        planNameInput.value = selectedOption.dataset.name || '';
                        planPriceInput.value = totalPrice.toFixed(2); // Total including tax
                        planDescInput.value = selectedOption.dataset.description || '';
                        
                        // Update summary
                        summaryPlanName.textContent = selectedOption.dataset.name || '';
                        summaryTiffinsCount.textContent = tiffins;
                        summaryTotalPrice.textContent = '$' + totalPrice.toFixed(2);
                        
                        // Update addons in summary
                        const addonsList = JSON.parse(addonsInput.value || '[]');
                        if (addonsList.length > 0) {
                            const addonsText = addonsList.map(function(addon) {
                                return addon.name + ' (Qty: ' + addon.quantity + ')';
                            }).join(', ');
                            summaryAddonsText.textContent = addonsText;
                            summaryAddons.style.display = 'flex';
                        } else {
                            summaryAddonsText.textContent = 'None';
                            summaryAddons.style.display = 'flex';
                        }
                        
                        summaryEmpty.style.display = 'none';
                        summaryPlan.style.display = 'flex';
                        summaryTiffins.style.display = 'flex';
                        summaryTotal.style.display = 'flex';
                    } else {
                        priceDisplay.style.display = 'none';
                        summaryEmpty.style.display = 'block';
                        summaryPlan.style.display = 'none';
                        summaryTiffins.style.display = 'none';
                        summaryTotal.style.display = 'none';
                    }
                }
                
                // Handle plan selection
                planSelect.addEventListener('change', function() {
                    if (this.value) {
                        tiffinsGroup.style.display = 'block';
                        addonsGroup.style.display = 'block';
                        calculatePrice();
                    } else {
                        tiffinsGroup.style.display = 'none';
                        addonsGroup.style.display = 'none';
                        priceDisplay.style.display = 'none';
                        summaryEmpty.style.display = 'block';
                        summaryPlan.style.display = 'none';
                        summaryTiffins.style.display = 'none';
                        summaryAddons.style.display = 'none';
                        summaryTotal.style.display = 'none';
                    }
                });
                
                // Handle tiffin selection
                tiffinsSelect.addEventListener('change', calculatePrice);
                
                // Handle addon quantity changes
                document.querySelectorAll('.switch-addon-qty').forEach(function(input) {
                    input.addEventListener('input', calculatePrice);
                    input.addEventListener('change', calculatePrice);
                });
            }
            
            // Trial Meal functionality (if this is a trial meal link form)
            const isTrialMealLink = <?php echo $is_trial_meal_link ? 'true' : 'false'; ?>;
            const trialMealTaxEnabled = <?php echo ($trial_meal_tax_enabled && $tax_rate > 0) ? 'true' : 'false'; ?>;
            const trialMealTaxRate = <?php echo floatval($tax_rate); ?>;
            
            if (isTrialMealLink) {
                const trialMealSelect = document.getElementById('trial_meal_product');
                const trialMealTiffinsSelect = document.getElementById('trial_meal_tiffins');
                const trialMealTiffinsGroup = document.getElementById('trial_meal_tiffins_group');
                const trialMealPriceDisplay = document.getElementById('trial_meal_price_display');
                const trialMealUnitPriceDisplay = document.getElementById('trial_meal_unit_price');
                const trialMealTiffinsCountDisplay = document.getElementById('trial_meal_tiffins_count');
                const trialMealTotalPriceDisplay = document.getElementById('trial_meal_total_price');
                const trialMealNameInput = document.getElementById('trial_meal_name');
                const trialMealPriceInput = document.getElementById('trial_meal_price');
                const trialMealDescInput = document.getElementById('trial_meal_description');
                
                // Summary elements
                const summaryPlan = document.getElementById('trial_meal_summary_plan');
                const summaryPlanName = document.getElementById('trial_meal_summary_plan_name');
                const summaryTiffins = document.getElementById('trial_meal_summary_tiffins');
                const summaryTiffinsCount = document.getElementById('trial_meal_summary_tiffins_count');
                const summaryTotal = document.getElementById('trial_meal_summary_total');
                const summaryTotalPrice = document.getElementById('trial_meal_summary_total_price');
                const summaryEmpty = document.getElementById('trial_meal_summary_empty');
                
                const getTrialMealTiffinsCount = () => parseInt(trialMealTiffinsSelect.value, 10) || 0;
                
                function calculateTrialMealPrice() {
                    if (!trialMealSelect.value || !trialMealTiffinsSelect.value) {
                        trialMealPriceDisplay.style.display = 'none';
                        summaryEmpty.style.display = 'block';
                        summaryPlan.style.display = 'none';
                        summaryTiffins.style.display = 'none';
                        summaryTotal.style.display = 'none';
                        return;
                    }
                    
                    const selectedOption = trialMealSelect.options[trialMealSelect.selectedIndex];
                    const unitPrice = parseFloat(selectedOption.dataset.price) || 0;
                    const tiffins = getTrialMealTiffinsCount();
                    const subtotal = unitPrice * tiffins;
                    
                    // Calculate tax if enabled
                    let tax = 0;
                    let totalPrice = subtotal;
                    if (trialMealTaxEnabled && trialMealTaxRate > 0) {
                        tax = subtotal * (trialMealTaxRate / 100);
                        totalPrice = subtotal + tax;
                        
                        // Update tax breakdown display
                        const taxBreakdown = document.getElementById('trial_meal_tax_breakdown');
                        const subtotalBeforeTax = document.getElementById('trial_meal_subtotal_before_tax');
                        const taxAmount = document.getElementById('trial_meal_tax_amount');
                        if (taxBreakdown && subtotalBeforeTax && taxAmount) {
                            taxBreakdown.style.display = 'block';
                            subtotalBeforeTax.textContent = '$' + subtotal.toFixed(2);
                            taxAmount.textContent = '$' + tax.toFixed(2);
                        }
                    }
                    
                    if (tiffins > 0 && unitPrice > 0) {
                        // Update price display
                        trialMealUnitPriceDisplay.textContent = '$' + unitPrice.toFixed(2);
                        trialMealTiffinsCountDisplay.textContent = tiffins;
                        trialMealTotalPriceDisplay.textContent = '$' + totalPrice.toFixed(2);
                        trialMealPriceDisplay.style.display = 'block';
                        
                        // Update hidden fields
                        trialMealNameInput.value = selectedOption.dataset.name || '';
                        trialMealPriceInput.value = totalPrice.toFixed(2); // Total including tax
                        trialMealDescInput.value = selectedOption.dataset.description || '';
                        
                        // Update summary
                        summaryPlanName.textContent = selectedOption.dataset.name || '';
                        summaryTiffinsCount.textContent = tiffins;
                        summaryTotalPrice.textContent = '$' + totalPrice.toFixed(2);
                        
                        summaryEmpty.style.display = 'none';
                        summaryPlan.style.display = 'flex';
                        summaryTiffins.style.display = 'flex';
                        summaryTotal.style.display = 'flex';
                    } else {
                        trialMealPriceDisplay.style.display = 'none';
                        summaryEmpty.style.display = 'block';
                        summaryPlan.style.display = 'none';
                        summaryTiffins.style.display = 'none';
                        summaryTotal.style.display = 'none';
                    }
                }
                
                // Function to handle preferred days visibility and value for trial meals
                function autoSelectWeekdaysForTrialMeal() {
                    const tiffinCount = getTrialMealTiffinsCount();
                    const preferredDaysInput = document.getElementById('preferred_days_selected');
                    const preferredDaysGroup = document.getElementById('preferred_days_group');
                    const preferredDaysHiddenInput = document.getElementById('preferred_days_selected_hidden');
                    
                    if (tiffinCount === 1) {
                        // Hide the preferred days section
                        if (preferredDaysGroup) {
                            preferredDaysGroup.style.display = 'none';
                        }
                        
                        // Set the value to Monday - Friday in both inputs
                        if (preferredDaysHiddenInput) {
                            preferredDaysHiddenInput.value = 'Monday - Friday';
                        }
                        if (preferredDaysInput) {
                            preferredDaysInput.value = 'Monday - Friday';
                        }
                        
                        // Remove error styling if any
                        if (preferredDaysGroup) {
                            preferredDaysGroup.classList.remove('error');
                        }
                    } else if (tiffinCount > 1) {
                        // Show the preferred days section
                        if (preferredDaysGroup) {
                            preferredDaysGroup.style.display = 'block';
                        }
                        
                        // Clear the hidden input value (user will select manually)
                        if (preferredDaysHiddenInput) {
                            preferredDaysHiddenInput.value = '';
                        }
                        
                        // Clear the main input if it was set to Monday - Friday
                        if (preferredDaysInput && preferredDaysInput.value === 'Monday - Friday') {
                            preferredDaysInput.value = '';
                        }
                    }
                }
                
                // Handle product selection
                if (trialMealSelect) {
                    trialMealSelect.addEventListener('change', function() {
                        if (this.value) {
                            trialMealTiffinsGroup.style.display = 'block';
                            calculateTrialMealPrice();
                            
                            // Check if tiffin count is already 1 and handle preferred days
                            setTimeout(() => {
                                autoSelectWeekdaysForTrialMeal();
                            }, 100);
                        } else {
                            trialMealTiffinsGroup.style.display = 'none';
                            trialMealPriceDisplay.style.display = 'none';
                            trialMealNameInput.value = '';
                            trialMealPriceInput.value = '';
                            trialMealDescInput.value = '';
                            summaryEmpty.style.display = 'block';
                            summaryPlan.style.display = 'none';
                            summaryTiffins.style.display = 'none';
                            summaryTotal.style.display = 'none';
                            
                            // Show preferred days section if it was hidden
                            const preferredDaysGroup = document.getElementById('preferred_days_group');
                            if (preferredDaysGroup) {
                                preferredDaysGroup.style.display = 'block';
                            }
                        }
                    });
                }
                
                // Handle tiffin selection
                if (trialMealTiffinsSelect) {
                    trialMealTiffinsSelect.addEventListener('change', function() {
                        calculateTrialMealPrice();
                        // Auto-select Monday - Friday if tiffin count is 1
                        autoSelectWeekdaysForTrialMeal();
                    });
                }
            }
            
            // Initialize Flatpickr for start date
            if (typeof flatpickr !== 'undefined') {
                // Get current local date/time to ensure we're using customer's timezone
                var now = new Date();
                var tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
                
                // Check if this is an auto-renewal with a calculated start date
                var isAutoRenewal = <?php echo $is_auto_renewal ? 'true' : 'false'; ?>;
                var calculatedStartDate = '<?php echo esc_js($calculated_start_date); ?>';
                
                var flatpickrConfig = {
                    minDate: tomorrow,
                    dateFormat: "Y-m-d",
                    allowInput: true,
                    clickOpens: true,
                    theme: "airbnb",
                    disableMobile: true,
                    // Force local timezone usage (not UTC)
                    time_24hr: false,
                    enableTime: false,
                    noCalendar: false,
                    disable: [
                        function(date) {
                            // Disable all dates before tomorrow (using local timezone)
                            var localNow = new Date();
                            var localTomorrow = new Date(localNow.getFullYear(), localNow.getMonth(), localNow.getDate() + 1);
                            localTomorrow.setHours(0, 0, 0, 0);
                            if (date < localTomorrow) {
                                return true; // Disable past dates
                            }
                            
                            // Disable weekends (Saturday = 6, Sunday = 0)
                            var dayOfWeek = date.getDay();
                            return dayOfWeek === 0 || dayOfWeek === 6;
                        }
                    ],
                    onOpen: function(selectedDates, dateStr, instance) {
                        // Ensure no past dates are selectable when calendar opens (local timezone)
                        var localNow = new Date();
                        var localTomorrow = new Date(localNow.getFullYear(), localNow.getMonth(), localNow.getDate() + 1);
                        instance.set('minDate', localTomorrow);
                    },
                    onReady: function(selectedDates, dateStr, instance) {
                        // Log timezone info for debugging
                        console.log('Flatpickr timezone offset (minutes):', now.getTimezoneOffset());
                        console.log('Customer local time:', now.toLocaleString());
                        console.log('Tomorrow date (local):', tomorrow.toDateString());
                        
                        // If this is an auto-renewal, set the calculated start date
                        if (isAutoRenewal && calculatedStartDate) {
                            var startDate = new Date(calculatedStartDate + 'T00:00:00');
                            // Ensure the calculated date is not in the past
                            if (startDate >= tomorrow) {
                                instance.setDate(startDate, true);
                                console.log('Auto-renewal: Setting calculated start date to', calculatedStartDate);
                            } else {
                                console.log('Auto-renewal: Calculated start date is in the past, using tomorrow');
                            }
                        }
                    }
                };
                
                // If auto-renewal with calculated date, set it as minimum date if it's in the future
                if (isAutoRenewal && calculatedStartDate) {
                    var calcDate = new Date(calculatedStartDate + 'T00:00:00');
                    if (calcDate > tomorrow) {
                        flatpickrConfig.minDate = calcDate;
                    }
                }
                
                flatpickr("#start_date", flatpickrConfig);
            }
            
            // Day Picker Functionality
            const dayButtons = document.querySelectorAll('.day-button');
            const selectedDaysInput = document.getElementById('preferred_days_selected');
            let selectedDays = [];
            
            // Check if this is a trial meal (preferred days are pre-set) - but NOT for trial meal links
            // Trial meal links should have day picker functionality enabled
            const isTrialMeal = selectedDaysInput && selectedDaysInput.value === 'Monday - Friday' && !isTrialMealLink;
            
            // If trial meal (not trial meal link), we don't need day picker functionality
            if (isTrialMeal) {
                selectedDays = ['Monday - Friday'];
            }
            
            // For trial meal links, initialize selectedDays from the input if it exists
            if (isTrialMealLink && selectedDaysInput && selectedDaysInput.value === 'Monday - Friday') {
                selectedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            }
            
            // Function to sort days in proper weekly order
            function sortDaysInWeeklyOrder(days) {
                const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                return days.sort((a, b) => dayOrder.indexOf(a) - dayOrder.indexOf(b));
            }
            
            // Function to format days for display (e.g., "Monday - Friday" or "Monday, Wednesday, Friday")
            function formatSelectedDays(days) {
                if (days.length === 0) return '';
                
                const sortedDays = sortDaysInWeeklyOrder([...days]);
                
                // Check if it's a continuous range
                const dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                const dayIndices = sortedDays.map(day => dayOrder.indexOf(day));
                
                // Check if days are consecutive
                let isConsecutive = true;
                for (let i = 1; i < dayIndices.length; i++) {
                    if (dayIndices[i] !== dayIndices[i-1] + 1) {
                        isConsecutive = false;
                        break;
                    }
                }
                
                // If consecutive and more than 2 days, format as range (Monday - Friday)
                if (isConsecutive && sortedDays.length > 2) {
                    return sortedDays[0] + ' - ' + sortedDays[sortedDays.length - 1];
                } else {
                    // Otherwise, format as comma-separated list
                    return sortedDays.join(', ');
                }
            }

            // Handle day selection (only for non-trial meals, but allow for trial meal links)
            if (!isTrialMeal && dayButtons.length > 0) {
                dayButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const day = this.getAttribute('data-day');
                    const maxDays = 3;
                    
                    if (this.classList.contains('selected')) {
                        // Deselect day
                        this.classList.remove('selected');
                        selectedDays = selectedDays.filter(d => d !== day);
                        
                        // Re-enable all buttons when deselecting
                        dayButtons.forEach(btn => {
                            btn.classList.remove('disabled');
                            btn.style.opacity = '1';
                            btn.style.cursor = 'pointer';
                        });
                    } else {
                        // Check if maximum days already selected
                        if (selectedDays.length >= maxDays) {
                            // Show feedback that max is reached
                            this.style.transform = 'scale(0.95)';
                            this.style.background = '#f8d7da';
                            setTimeout(() => {
                                this.style.transform = 'scale(1)';
                                this.style.background = '#fff';
                            }, 200);
                            
                            // Show temporary message
                            showTemporaryMessage('You can select maximum 3 days only');
                            return;
                        }
                        
                        // Select day
                        this.classList.add('selected');
                        selectedDays.push(day);
                        
                        // If max reached, disable remaining buttons
                        if (selectedDays.length >= maxDays) {
                            dayButtons.forEach(btn => {
                                if (!btn.classList.contains('selected')) {
                                    btn.classList.add('disabled');
                                    btn.style.opacity = '0.5';
                                    btn.style.cursor = 'not-allowed';
                                }
                            });
                        }
                    }
                    
                    // Sort and format the selected days properly
                    const formattedDays = formatSelectedDays(selectedDays);
                    selectedDaysInput.value = formattedDays;
                    
                    // Remove custom validation error if any day is selected
                    if (selectedDays.length > 0) {
                        this.closest('.form-group').classList.remove('error');
                    }
                });
            });
            
            // Add touch feedback for mobile
            dayButtons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Initialize explanation text
            addExplanatoryText();
            } // End of non-trial meal day picker functionality
            
            // Function to show temporary message
            function showTemporaryMessage(message) {
                const existingMessage = document.querySelector('.temp-message');
                if (existingMessage) {
                    existingMessage.remove();
                }
                
                const tempMessage = document.createElement('div');
                tempMessage.className = 'temp-message';
                tempMessage.style.cssText = `
                    position: absolute;
                    top: -40px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #dc3545;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    white-space: nowrap;
                    z-index: 1000;
                    animation: fadeInOut 2s ease-in-out;
                `;
                tempMessage.textContent = message;
                
                const dayPicker = document.getElementById('day-picker');
                dayPicker.style.position = 'relative';
                dayPicker.appendChild(tempMessage);
                
                setTimeout(() => {
                    if (tempMessage && tempMessage.parentNode) {
                        tempMessage.remove();
                    }
                }, 2000);
            }
            
            // Function to add explanatory text
            function addExplanatoryText() {
                let explanationText = document.querySelector('.day-explanation');
                if (!explanationText) {
                    explanationText = document.createElement('div');
                    explanationText.className = 'day-explanation';
                    explanationText.style.cssText = `
                        font-size: 13px;
                        color: #495057;
                        margin-top: 10px;
                        padding: 12px;
                        background: #f8f9fa;
                        border-radius: 6px;
                        border-left: 4px solid #007cba;
                        line-height: 1.5;
                    `;
                    explanationText.innerHTML = `
                        <strong>How to select your Prefered days:</strong><br>
                        • Example: If You Want Your Tiffin From Monday to Friday, Select <strong>Monday</strong> and <strong>Friday</strong> for Monday-Friday delivery<br>
                        • Example: If You Want Your Tiffin On Specific Days, Select Specific Days Like -  <strong>Monday, Wednesday, Friday</strong><br>
                    `;
                    document.querySelector('.day-picker').parentNode.appendChild(explanationText);
                }
            }
            

            
            // Prevent back button and page refresh after order submission
            function preventNavigation() {
                if (orderSubmitted) {
                    history.pushState(null, null, location.href);
                    window.onpopstate = function() {
                        history.go(1);
                    };
                    
                    // Prevent page refresh
                    window.addEventListener('beforeunload', function(e) {
                        e.preventDefault();
                        e.returnValue = 'Your order has been placed. Refreshing this page may cause duplicate orders.';
                        return 'Your order has been placed. Refreshing this page may cause duplicate orders.';
                    });
                }
            }
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Prevent multiple submissions
                if (orderSubmitted) {
                    return false;
                }
                
                // Clear previous error styling
                document.querySelectorAll('.form-group.error, .form-group input.error, .form-group select.error, .form-group textarea.error').forEach(element => {
                    element.classList.remove('error');
                });
                
                // Combine address fields into address_1 before validation
                combineAddressFields();
                
                // Validate required fields
                const requiredFields = [
                    { name: 'first_name', label: 'First Name' },
                    { name: 'last_name', label: 'Last Name' },
                    { name: 'phone', label: 'Phone Number' },
                    { name: 'email', label: 'Email Address' },
                    { name: 'unit_number', label: 'Unit Number' },
                    { name: 'street_name', label: 'Street Name' },
                    { name: 'city', label: 'City' },
                    { name: 'state', label: 'State/Province' },
                    { name: 'postcode', label: 'Postal Code' },
                    { name: 'country', label: 'Country' },
                    { name: 'start_date', label: 'Preferred Start Date' },
                    { name: 'meal_type', label: 'Meal Type' },
                    { name: 'delivery_type', label: 'Delivery Options' },
                    { name: 'delivery_notes', label: 'Special Instructions / Delivery Notes' }
                ];
                
                let invalidFields = [];
                let firstInvalidElement = null;
                
                // Check each required field
                requiredFields.forEach(field => {
                    const input = document.querySelector(`[name="${field.name}"]`);
                    if (input) {
                        const value = input.value.trim();
                        if (!value) {
                            invalidFields.push(field.label);
                            
                            // Add error styling to input
                            input.classList.add('error');
                            
                            // Add error styling to form group
                            const formGroup = input.closest('.form-group');
                            if (formGroup) {
                                formGroup.classList.add('error');
                            }
                            
                            // Remember first invalid element for scrolling
                            if (!firstInvalidElement) {
                                firstInvalidElement = input;
                            }
                        }
                    }
                });
                
                // Validate switch plan fields (if this is a switch plan)
                if (isSwitchPlan) {
                const planSelect = document.getElementById('switch_plan_product');
                const tiffinsSelect = document.getElementById('switch_plan_tiffins');
                    
                    if (!planSelect || !planSelect.value) {
                        invalidFields.push('Plan Selection');
                        if (planSelect) {
                            planSelect.classList.add('error');
                            const formGroup = planSelect.closest('.form-group');
                            if (formGroup) {
                                formGroup.classList.add('error');
                            }
                            if (!firstInvalidElement) {
                                firstInvalidElement = planSelect;
                            }
                        }
                    }
                    
                if (!tiffinsSelect || !tiffinsSelect.value || parseInt(tiffinsSelect.value, 10) < 1) {
                        invalidFields.push('Number of Tiffins');
                    if (tiffinsSelect) {
                        tiffinsSelect.classList.add('error');
                        const formGroup = tiffinsSelect.closest('.form-group');
                            if (formGroup) {
                                formGroup.classList.add('error');
                            }
                            if (!firstInvalidElement) {
                            firstInvalidElement = tiffinsSelect;
                            }
                        }
                    }
                }
                
                // Validate trial meal fields (if this is a trial meal link)
                if (isTrialMealLink) {
                    const trialMealSelect = document.getElementById('trial_meal_product');
                    const trialMealTiffinsSelect = document.getElementById('trial_meal_tiffins');
                    
                    if (!trialMealSelect || !trialMealSelect.value) {
                        invalidFields.push('Trial Meal Selection');
                        if (trialMealSelect) {
                            trialMealSelect.classList.add('error');
                            const formGroup = trialMealSelect.closest('.form-group');
                            if (formGroup) {
                                formGroup.classList.add('error');
                            }
                            if (!firstInvalidElement) {
                                firstInvalidElement = trialMealSelect;
                            }
                        }
                    }
                    
                    if (!trialMealTiffinsSelect || !trialMealTiffinsSelect.value || parseInt(trialMealTiffinsSelect.value, 10) < 1) {
                        invalidFields.push('Number of Tiffins (Trial Meal)');
                        if (trialMealTiffinsSelect) {
                            trialMealTiffinsSelect.classList.add('error');
                            const formGroup = trialMealTiffinsSelect.closest('.form-group');
                            if (formGroup) {
                                formGroup.classList.add('error');
                            }
                            if (!firstInvalidElement) {
                                firstInvalidElement = trialMealTiffinsSelect;
                            }
                        }
                    }
                }
                
                // Validate preferred days (skip for trial meals and switch plans)
                if (!isTrialMeal && !isSwitchPlan && !isTrialMealLink && selectedDays.length === 0) {
                    invalidFields.push('Preferred Days');
                    const dayPickerGroup = document.querySelector('.day-picker')?.closest('.form-group');
                    if (dayPickerGroup) {
                        dayPickerGroup.classList.add('error');
                        if (!firstInvalidElement) {
                            firstInvalidElement = dayPickerGroup;
                        }
                    }
                }
                
                // If there are invalid fields, show error and stop submission
                if (invalidFields.length > 0) {
                    let errorMessage = '<strong>Please fill in the following required fields:</strong><br>';
                    errorMessage += '<ul style="margin: 10px 0; padding-left: 20px;">';
                    invalidFields.forEach(field => {
                        errorMessage += '<li>' + field + '</li>';
                    });
                    errorMessage += '</ul>';
                    
                    // Show error message
                    messages.className = 'form-messages error';
                    messages.innerHTML = errorMessage;
                    messages.style.display = 'block';
                    
                    // Scroll to first invalid field
                    if (firstInvalidElement) {
                        firstInvalidElement.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                        
                        // Focus on the first invalid field after scrolling
                        setTimeout(() => {
                            firstInvalidElement.focus();
                        }, 500);
                    }
                    
                    return false;
                }
                
                // Disable submit button
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';
                
                // Hide previous messages
                messages.style.display = 'none';
                messages.className = 'form-messages';
                
                // Prepare form data
                const formData = new FormData(form);
                formData.append('action', 'submit_customer_order');
                
                // Submit via AJAX
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        orderSubmitted = true;
                        preventNavigation();
                        
                        messages.className = 'form-messages success';
                        messages.innerHTML = data.data.message;
                        messages.style.display = 'block';
                        
                        // Hide form after successful submission
                        form.style.display = 'none';
                        
                        // Scroll to message
                        messages.scrollIntoView({ behavior: 'smooth' });
                        
                        // Redirect after 3 seconds to secure confirmation page
                        setTimeout(() => {
                            if (data.data.redirect_url) {
                                window.location.replace(data.data.redirect_url);
                            }
                        }, 3000);
                    } else {
                        messages.className = 'form-messages error';
                        messages.innerHTML = 'Error: ' + data.data;
                        messages.style.display = 'block';
                        messages.scrollIntoView({ behavior: 'smooth' });
                        
                        // Re-enable submit button only on error
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Place Order';
                    }
                })
                .catch(error => {
                    messages.className = 'form-messages error';
                    messages.innerHTML = 'An error occurred while processing your order. Please try again.';
                    messages.style.display = 'block';
                    messages.scrollIntoView({ behavior: 'smooth' });
                    
                    // Re-enable submit button only on error
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Place Order';
                });
            });
            
            // Function to combine address fields into address_1
            function combineAddressFields() {
                const unitNumber = document.getElementById('unit_number').value.trim();
                const streetName = document.getElementById('street_name').value.trim();
                const city = document.getElementById('city').value.trim();
                const state = document.getElementById('state').value.trim();
                const postcode = document.getElementById('postcode').value.trim();
                const country = document.getElementById('country').value.trim();
                
                // Combine fields with commas and spaces
                const addressParts = [];
                if (unitNumber) addressParts.push(unitNumber);
                if (streetName) addressParts.push(streetName);
                if (city) addressParts.push(city);
                if (state) addressParts.push(state);
                if (postcode) addressParts.push(postcode);
                if (country) addressParts.push(country);
                
                const combinedAddress = addressParts.join(', ');
                document.getElementById('address_1').value = combinedAddress;
            }
            
            // Clear error styling when user starts typing/selecting
            document.querySelectorAll('input, select, textarea').forEach(element => {
                element.addEventListener('input', function() {
                    this.classList.remove('error');
                    const formGroup = this.closest('.form-group');
                    if (formGroup && !formGroup.querySelector('.error')) {
                        formGroup.classList.remove('error');
                    }
                });
                
                element.addEventListener('change', function() {
                    this.classList.remove('error');
                    const formGroup = this.closest('.form-group');
                    if (formGroup && !formGroup.querySelector('.error')) {
                        formGroup.classList.remove('error');
                    }
                });
            });
            
            // Clear error for day picker when days are selected
            if (!isTrialMeal && dayButtons.length > 0) {
                dayButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const dayPickerGroup = document.querySelector('.day-picker')?.closest('.form-group');
                        if (dayPickerGroup && selectedDays.length > 0) {
                            dayPickerGroup.classList.remove('error');
                        }
                    });
                });
            }
            
            // Additional security: Disable right-click and keyboard shortcuts after order
            document.addEventListener('contextmenu', function(e) {
                if (orderSubmitted) {
                    e.preventDefault();
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (orderSubmitted) {
                    // Disable F5, Ctrl+R, Ctrl+F5
                    if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'F5')) {
                        e.preventDefault();
                        return false;
                    }
                    // Disable Ctrl+Z (undo)
                    if (e.ctrlKey && e.key === 'z') {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
        </script>
        
        <?php
        get_footer();
    }
    
    /**
     * Render order success page
     */
    private function render_order_success_page($token) {
        $pending_id = isset($_GET['pending_id']) ? absint($_GET['pending_id']) : 0;
        
        // Ensure plan data is loaded for success page
        if (!$this->plan_data) {
            $this->plan_data = get_option('customer_order_plan_' . $token);
        }
        
        get_header();
        ?>
        <div class="customer-order-container">
            <div class="customer-order-form">
                <div class="success-message">
                    <div class="success-icon pending">?</div>
                    <h2>Order Submitted for Review!</h2>
                    <p>Thank you for your order submission. Your order is now pending review by our sales team.</p>
                    
                    <?php if ($pending_id): ?>
                        <div class="order-details">
                            <h3>Submission Details</h3>
                            <p><strong>Submission ID:</strong> #<?php echo $pending_id; ?></p>
                            <?php if ($this->plan_data && isset($this->plan_data['plan_name'])): ?>
                                <p><strong>Plan:</strong> <?php echo esc_html($this->plan_data['plan_name']); ?></p>
                                <p><strong>Total:</strong> $<?php echo number_format($this->plan_data['plan_price'], 2); ?></p>
                            <?php else: ?>
                                <p><strong>Plan:</strong> Order Successfully Submitted</p>
                                <p><strong>Total:</strong> As per plan selected</p>
                            <?php endif; ?>
                            <p><strong>Status:</strong> <span class="status-pending">Pending Review</span></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="next-steps">
                        <h3>What's Next?</h3>
                        <ul>
                            <li>Our sales team will review your order within Few Mins</li>
                            <li>You'll receive an email confirmation once approved</li>
                            <li>If we need clarification, we'll contact you directly</li>
                            <li>Once approved, your tiffin delivery will start on your preferred date</li>
                        </ul>
                    </div>
                    
                    <div class="contact-info">
                        <p>If you have any questions, please contact our customer service team.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .success-message {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .success-icon.pending {
            background: #FFA500;
        }
        
        .status-pending {
            color: #FFA500;
            font-weight: bold;
        }
        
        .success-message h2 {
            color: #28a745;
            margin: 0 0 15px 0;
            font-size: 28px;
        }
        
        .success-message p {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        .order-details {
            background: white;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
        }
        
        .order-details h3,
        .next-steps h3 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        
        .next-steps {
            background: white;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: left;
        }
        
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 8px;
            color: #495057;
        }
        
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        </style>
        
        <script>
        // Prevent navigation away from success page
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
        
        // Prevent page refresh
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Your order has been confirmed. Leaving this page will not affect your order.';
            return 'Your order has been confirmed. Leaving this page will not affect your order.';
        });
        
        // Disable common navigation shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'F5')) {
                e.preventDefault();
                return false;
            }
        });
        </script>
        <?php
        get_footer();
    }
    
    /**
     * Render order already placed page
     */
    private function render_order_already_placed_page($token) {
        // Check the current status from pending orders table
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        $pending_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
        
        // Ensure plan data is loaded
        if (!$this->plan_data) {
            $this->plan_data = get_option('customer_order_plan_' . $token);
        }
        
        if ($pending_order && $pending_order->status === 'approved' && $pending_order->order_id) {
            // Order has been approved and WooCommerce order created
            $wc_order = wc_get_order($pending_order->order_id);
            $order_status = $wc_order ? $wc_order->get_status() : 'unknown';
            $order_date = $wc_order ? $wc_order->get_date_created()->format('F j, Y g:i A') : 'Unknown';
            
            get_header();
            ?>
            <div class="customer-order-container">
                <div class="customer-order-form">
                    <div class="success-message approved">
                        <div class="success-icon">?</div>
                        <h2>Order Approved & Created Successfully!</h2>
                        <p>Great news! Your order has been approved by our sales team and has been successfully created in our system.</p>
                        
                        <div class="order-details-box">
                            <h3>Order Information</h3>
                            <p><strong>Order ID:</strong> #<?php echo $pending_order->order_id; ?></p>
                            <p><strong>Status:</strong> <span class="status-badge status-<?php echo $order_status; ?>"><?php echo ucfirst($order_status); ?></span></p>
                            <p><strong>Order Date:</strong> <?php echo $order_date; ?></p>
                            <?php if ($pending_order->approved_at): ?>
                                <p><strong>Approved:</strong> <?php echo date('F j, Y g:i A', strtotime($pending_order->approved_at)); ?></p>
                            <?php endif; ?>
                            <?php if ($this->plan_data && isset($this->plan_data['plan_name'])): ?>
                                <p><strong>Plan:</strong> <?php echo esc_html($this->plan_data['plan_name']); ?></p>
                                <p><strong>Price:</strong> <?php echo wc_price($this->plan_data['plan_price']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="next-steps">
                            <h3>What's Next?</h3>
                            <ul>
                                <li>You'll receive an email confirmation with order details</li>
                                <li>Our team will prepare your order for delivery</li>
                                <li>You can track your order status in your email updates</li>
                                <li>Delivery will start on your scheduled date</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            .success-message.approved {
                text-align: center;
                padding: 40px;
                background: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 8px;
            }
            
            .success-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            
            .success-message.approved h2 {
                color: #155724;
                margin: 0 0 15px 0;
                font-size: 28px;
            }
            
            .success-message.approved p {
                color: #155724;
                font-size: 16px;
                margin-bottom: 30px;
            }
            
            .order-details-box {
                background: white;
                padding: 20px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: left;
                border: 1px solid #e9ecef;
            }
            
            .order-details-box h3 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 18px;
                text-align: center;
            }
            
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .status-processing {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .status-completed {
                background: #d4edda;
                color: #155724;
            }
            
            .status-on-hold {
                background: #fff3cd;
                color: #856404;
            }
            </style>
            <?php
            get_footer();
            
        } else if ($pending_order && $pending_order->status === 'pending') {
            // Order is still pending approval - show auto-refresh page
            get_header();
            ?>
            <div class="customer-order-container">
                <div class="customer-order-form">
                    <div class="pending-message">
                        <div class="pending-icon">?</div>
                        <h2>Order Under Review</h2>
                        <p>Your order has been submitted and is currently being reviewed by our sales team.</p>
                        
                        <div class="status-box">
                            <span class="status-badge status-pending">Pending Review</span>
                        </div>
                        
                        <div class="order-info">
                            <h3>Order Summary</h3>
                            <?php if ($this->plan_data && isset($this->plan_data['plan_name'])): ?>
                                <p><strong>Plan:</strong> <?php echo esc_html($this->plan_data['plan_name']); ?></p>
                                <p><strong>Price:</strong> <?php echo wc_price($this->plan_data['plan_price']); ?></p>
                            <?php endif; ?>
                            <p><strong>Submitted:</strong> <?php echo date('F j, Y g:i A', strtotime($pending_order->submitted_at)); ?></p>
                        </div>
                        
                        <div class="next-steps">
                            <h3>What's Next?</h3>
                            <ul>
                                <li>Our sales team will review your order within Few Mins</li>
                                <li>You'll receive an email confirmation once approved</li>
                                <li>If we need clarification, we'll contact you directly</li>
                                <li>This page will automatically update when your order is processed</li>
                            </ul>
                        </div>
                        
                        <button onclick="window.location.reload()" class="refresh-btn">?? Check Status Now</button>
                        
                        <div class="auto-refresh-info">
                            <small>This page automatically checks for updates every 30 seconds</small><br>
                            <small>Refresh count: <span id="refresh-counter">0</span></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            .pending-message {
                text-align: center;
                padding: 40px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
            }
            
            .pending-icon {
                font-size: 64px;
                margin-bottom: 20px;
                color: #856404;
            }
            
            .pending-message h2 {
                color: #856404;
                margin: 0 0 15px 0;
                font-size: 28px;
            }
            
            .pending-message p {
                color: #856404;
                font-size: 16px;
                margin-bottom: 30px;
            }
            
            .status-box {
                margin: 20px 0;
            }
            
            .status-pending {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            
            .order-info {
                background: white;
                padding: 20px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: left;
                border: 1px solid #e9ecef;
            }
            
            .order-info h3 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 18px;
                text-align: center;
            }
            
            .refresh-btn {
                background: #007cba;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin: 20px 0;
            }
            
            .refresh-btn:hover {
                background: #005a87;
            }
            
            .auto-refresh-info {
                margin-top: 20px;
                color: #666;
                font-size: 14px;
            }
            </style>
            
            <script>
            // Auto-refresh every 30 seconds to check for status updates
            let refreshCount = 0;
            const maxRefresh = 60; // Stop after 30 minutes
            
            function autoRefresh() {
                setTimeout(function() {
                    refreshCount++;
                    document.getElementById('refresh-counter').textContent = refreshCount;
                    
                    if (refreshCount >= maxRefresh) {
                        return; // Stop auto-refresh after 30 minutes
                    }
                    
                    // Check for status update
                    fetch(window.location.href)
                        .then(response => response.text())
                        .then(html => {
                            // If the page content has changed significantly (approved or rejected), reload
                            if (html.includes('Order Approved') || html.includes('Order Update Required')) {
                                window.location.reload();
                            } else {
                                autoRefresh(); // Continue checking
                            }
                        })
                        .catch(error => {
                            console.log('Auto-refresh failed:', error);
                            autoRefresh(); // Continue checking even on error
                        });
                }, 30000); // 30 seconds
            }
            
            // Start auto-refresh when page loads
            document.addEventListener('DOMContentLoaded', function() {
                autoRefresh();
            });
            </script>
            <?php
            get_footer();
            
        } else if ($pending_order && $pending_order->status === 'rejected') {
            // Order has been rejected
            get_header();
            ?>
            <div class="customer-order-container">
                <div class="customer-order-form">
                    <div class="rejected-message">
                        <div class="rejected-icon">?</div>
                        <h2>Order Update Required</h2>
                        <p>We've reviewed your order and need to discuss some adjustments with you.</p>
                        
                        <div class="status-box">
                            <span class="status-badge status-rejected">Needs Review</span>
                        </div>
                        
                        <?php if ($pending_order->notes): ?>
                            <div class="reason-box">
                                <h3>Message from our team:</h3>
                                <p><?php echo esc_html($pending_order->notes); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="next-steps">
                            <h3>Next Steps</h3>
                            <ul>
                                <li>Our sales team will contact you shortly to discuss the order</li>
                                <li>We'll work together to find the best solution</li>
                                <li>Once resolved, we'll process your updated order</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            .rejected-message {
                text-align: center;
                padding: 40px;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 8px;
            }
            
            .rejected-icon {
                font-size: 64px;
                margin-bottom: 20px;
                color: #721c24;
            }
            
            .rejected-message h2 {
                color: #721c24;
                margin: 0 0 15px 0;
                font-size: 28px;
            }
            
            .rejected-message p {
                color: #721c24;
                font-size: 16px;
                margin-bottom: 30px;
            }
            
            .status-rejected {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            
            .reason-box {
                background: white;
                padding: 20px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: left;
                border: 1px solid #e9ecef;
            }
            
            .reason-box h3 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 18px;
            }
            </style>
            <?php
            get_footer();
            
        } else {
            // Fallback for legacy orders or unknown status
            get_header();
            ?>
            <div class="customer-order-container">
                <div class="customer-order-form">
                    <div class="warning-message">
                        <div class="warning-icon">?</div>
                        <h2>Order Already Placed</h2>
                        <p>This order link has already been used to place an order.</p>
                        
                        <?php if ($this->plan_data && (isset($this->plan_data['order_id']) || isset($this->plan_data['pending_order_id']))): ?>
                            <div class="existing-order-details">
                                <?php if (isset($this->plan_data['order_id'])): ?>
                                    <h3>Previously Placed Order</h3>
                                    <p><strong>Order ID:</strong> #<?php echo $this->plan_data['order_id']; ?></p>
                                    <p><strong>Status:</strong> <span style="color: #28a745;">Approved & Created</span></p>
                                <?php elseif (isset($this->plan_data['pending_order_id'])): ?>
                                    <h3>Previously Submitted Order</h3>
                                    <p><strong>Submission ID:</strong> #<?php echo $this->plan_data['pending_order_id']; ?></p>
                                    <p><strong>Status:</strong> <span style="color: #FFA500;">Pending Review</span></p>
                                <?php endif; ?>
                                
                                <?php if (isset($this->plan_data['plan_name'])): ?>
                                    <p><strong>Plan:</strong> <?php echo esc_html($this->plan_data['plan_name']); ?></p>
                                <?php endif; ?>
                                <?php if (isset($this->plan_data['used_at'])): ?>
                                    <p><strong>Submitted on:</strong> <?php echo date('F j, Y g:i A', $this->plan_data['used_at']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="next-steps">
                            <h3>Need Help?</h3>
                            <ul>
                                <li>If you need to place a new order, contact your salesperson for a new link</li>
                                <li>If you have questions about your existing order, please contact customer service</li>
                                <li>Check your email for order confirmation details</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
            .warning-message {
                text-align: center;
                padding: 40px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 8px;
            }
            
            .warning-icon {
                width: 80px;
                height: 80px;
                background: #ffc107;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                font-weight: bold;
                margin: 0 auto 20px;
            }
            
            .warning-message h2 {
                color: #856404;
                margin: 0 0 15px 0;
                font-size: 28px;
            }
            
            .warning-message p {
                color: #856404;
                font-size: 16px;
                margin-bottom: 30px;
            }
            
            .existing-order-details {
                background: white;
                padding: 20px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: left;
                border: 1px solid #e9ecef;
            }
            
            .existing-order-details h3 {
                color: #2c3e50;
                margin: 0 0 15px 0;
                font-size: 18px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
            }
            </style>
            <?php
            get_footer();
        }
    }
    
    /**
     * AJAX handler for customer order submission
     */
    public function ajax_submit_customer_order() {
        check_ajax_referer('submit_customer_order');
        
        $token = sanitize_text_field($_POST['order_token']);
        $plan_data = get_option('customer_order_plan_' . $token);
        
        if (!$plan_data) {
            wp_send_json_error('Invalid order token');
        }
        
        // Check if link has expired
        if (current_time('timestamp') > $plan_data['expires_at']) {
            wp_send_json_error('This order link has expired');
        }
        
        // Handle switch plan (customer selects plan and tiffins)
        $is_switch_plan = isset($plan_data['plan_type']) && $plan_data['plan_type'] === 'switch_plan';
        
        // Handle trial meal link (customer selects from trial meal products)
        $is_trial_meal_link = isset($plan_data['plan_type']) && $plan_data['plan_type'] === 'trial_meal';
        
        if ($is_trial_meal_link) {
            // Get trial meal data from form
            $product_id = isset($_POST['trial_meal_product']) ? absint($_POST['trial_meal_product']) : 0;
            $number_of_tiffins = isset($_POST['trial_meal_tiffins']) ? absint($_POST['trial_meal_tiffins']) : 0;
            $plan_name = isset($_POST['trial_meal_name']) ? sanitize_text_field($_POST['trial_meal_name']) : '';
            $plan_price = isset($_POST['trial_meal_price']) ? floatval($_POST['trial_meal_price']) : 0;
            $plan_description = isset($_POST['trial_meal_description']) ? sanitize_textarea_field($_POST['trial_meal_description']) : '';
            
            // Validate trial meal data
            if (empty($product_id) || empty($number_of_tiffins) || empty($plan_name) || $plan_price <= 0) {
                wp_send_json_error('Please select a trial meal plan and number of tiffins');
            }
            
            // Validate tiffin package
            $allowed_packages = $this->get_trial_meal_tiffin_packages();
            if (!in_array($number_of_tiffins, $allowed_packages, true)) {
                wp_send_json_error('Please select a valid tiffin package.');
            }
            
            // Get product to verify
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error('Invalid product selected');
            }
            
            $allowed_products = $this->get_trial_meal_allowed_products();
            if (!empty($allowed_products) && !in_array($product_id, $allowed_products, true)) {
                wp_send_json_error('Selected product is not allowed for trial meal links.');
            }
            
            // Update plan_data with trial meal details
            $plan_data['plan_type'] = 'existing';
            $plan_data['plan_name'] = $plan_name;
            $plan_data['number_of_tiffins'] = $number_of_tiffins;
            $plan_data['plan_price'] = $plan_price;
            $plan_data['plan_description'] = $plan_description;
            $plan_data['product_id'] = $product_id;
            $plan_data['is_existing_product'] = true;
            $plan_data['is_trial_meal'] = true; // Keep flag for reference
        }
        
        if ($is_switch_plan) {
            // Get switch plan data from form
            $product_id = isset($_POST['switch_plan_product']) ? absint($_POST['switch_plan_product']) : 0;
            $number_of_tiffins = isset($_POST['switch_plan_tiffins']) ? absint($_POST['switch_plan_tiffins']) : 0;
            $plan_name = isset($_POST['switch_plan_name']) ? sanitize_text_field($_POST['switch_plan_name']) : '';
            $plan_price = isset($_POST['switch_plan_price']) ? floatval($_POST['switch_plan_price']) : 0;
            $plan_description = isset($_POST['switch_plan_description']) ? sanitize_textarea_field($_POST['switch_plan_description']) : '';
            
            // Validate switch plan data
            if (empty($product_id) || empty($number_of_tiffins) || empty($plan_name) || $plan_price <= 0) {
                wp_send_json_error('Please select a plan and number of tiffins');
            }

            $allowed_packages = $this->get_switch_plan_tiffin_packages();
            if (!in_array($number_of_tiffins, $allowed_packages, true)) {
                wp_send_json_error('Please select a valid tiffin package.');
            }
            
            // Get product to verify
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error('Invalid product selected');
            }

            $allowed_products = $this->get_switch_plan_allowed_products();
            if (!empty($allowed_products) && !in_array($product_id, $allowed_products, true)) {
                wp_send_json_error('Selected product is not allowed for switch plan links.');
            }
            
            // Process add-ons if provided
            $addons_string = '';
            $addons_array = [];
            if (isset($_POST['switch_plan_addons']) && !empty($_POST['switch_plan_addons'])) {
                $addons_json = stripslashes($_POST['switch_plan_addons']);
                $addons_data = json_decode($addons_json, true);
                
                if (is_array($addons_data) && !empty($addons_data)) {
                    $addons_parts = [];
                    foreach ($addons_data as $addon) {
                        $addon_id = isset($addon['id']) ? absint($addon['id']) : 0;
                        $addon_name = isset($addon['name']) ? sanitize_text_field($addon['name']) : '';
                        $addon_qty = isset($addon['quantity']) ? absint($addon['quantity']) : 0;
                        $addon_price = isset($addon['price']) ? floatval($addon['price']) : 0;
                        
                        if ($addon_id > 0 && $addon_qty > 0) {
                            // Format: "quantity name" (e.g., "2 Rotis", "1 Rice")
                            // This matches the format used in existing plan addons
                            $addons_parts[] = "{$addon_qty} {$addon_name}";
                            $addons_array[] = [
                                'id' => $addon_id,
                                'name' => $addon_name,
                                'quantity' => $addon_qty,
                                'price' => $addon_price
                            ];
                        }
                    }
                    
                    if (!empty($addons_parts)) {
                        $addons_string = implode(' + ', $addons_parts);
                    }
                }
            }
            
            // Update plan_data with switch plan details
            $plan_data['plan_type'] = 'existing';
            $plan_data['plan_name'] = $plan_name;
            $plan_data['number_of_tiffins'] = $number_of_tiffins;
            $plan_data['plan_price'] = $plan_price; // This already includes addons total
            $plan_data['plan_description'] = $plan_description;
            $plan_data['product_id'] = $product_id;
            $plan_data['is_existing_product'] = true;
            $plan_data['is_switch_plan'] = true; // Keep flag for reference
            $plan_data['addons'] = $addons_string; // Store addons as string
            $plan_data['addons_data'] = $addons_array; // Store addons as array for processing
        }
        
        // Sanitize customer data
        $customer_data = [
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'company' => sanitize_text_field($_POST['company']),
            'address_1' => sanitize_text_field($_POST['address_1']),
            'address_2' => sanitize_text_field($_POST['address_2']),
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'country' => sanitize_text_field($_POST['country']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'meal_type' => sanitize_text_field($_POST['meal_type']),
            'delivery_type' => sanitize_text_field($_POST['delivery_type']),
            'preferred_days' => isset($_POST['preferred_days_selected']) && !empty($_POST['preferred_days_selected']) ? 
                $this->parse_preferred_days($_POST['preferred_days_selected']) : 
                (isset($_POST['preferred_days_selected_hidden']) && !empty($_POST['preferred_days_selected_hidden']) ? 
                    $this->parse_preferred_days($_POST['preferred_days_selected_hidden']) : []),
            'delivery_notes' => sanitize_textarea_field($_POST['delivery_notes'])
        ];
        
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'phone', 'email', 'address_1', 'city', 'state', 'postcode', 'country', 'start_date', 'meal_type', 'delivery_type', 'delivery_notes'];
        foreach ($required_fields as $field) {
            if (empty($customer_data[$field])) {
                wp_send_json_error('Please fill in all required fields');
            }
        }
        
        // Validate preferred days (skip for switch plans and trial meal links - they can select days normally)
        if (!$is_switch_plan && !$is_trial_meal_link && empty($customer_data['preferred_days'])) {
            wp_send_json_error('Please select at least one preferred day');
        }
        
        // For switch plans and trial meal links, preferred days are still required but handled in form
        if (($is_switch_plan || $is_trial_meal_link) && empty($customer_data['preferred_days'])) {
            wp_send_json_error('Please select at least one preferred day');
        }
        
        try {
            // Save as pending order instead of creating immediately
            $pending_order_id = $this->save_pending_order($customer_data, $plan_data, $token);
            
            // Log the order submission
            $this->log_activity('order_submitted', [
                'plan_name' => $plan_data['plan_name'],
                'price' => $plan_data['plan_price'],
                'number_of_tiffins' => $plan_data['number_of_tiffins'],
                'token' => $token,
                'pending_id' => $pending_order_id,
                'start_date' => $customer_data['start_date'],
                'meal_type' => $customer_data['meal_type'],
                'delivery_type' => $customer_data['delivery_type'],
                'preferred_days' => $customer_data['preferred_days']
            ], [
                'name' => $customer_data['first_name'] . ' ' . $customer_data['last_name'],
                'email' => $customer_data['email'],
                'phone' => $customer_data['phone'],
                'role' => 'customer'
            ], "Customer submitted order for plan: {$plan_data['plan_name']} - Value: $" . number_format($plan_data['plan_price'], 2) . " - Start: {$customer_data['start_date']}");
            
            // Mark token as used to prevent duplicate orders
            $plan_data['used'] = true;
            $plan_data['pending_order_id'] = $pending_order_id;
            $plan_data['used_at'] = current_time('timestamp');
            update_option('customer_order_plan_' . $token, $plan_data);
            
            wp_send_json_success([
                'message' => 'Order submitted for review! Our sales team will review and approve your order within few mins.',
                'pending_order_id' => $pending_order_id,
                'redirect_url' => home_url('/customer-order/' . $token . '?order_success=1&pending_id=' . $pending_order_id)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to submit order: ' . $e->getMessage());
        }
    }
    
    /**
     * Create or update customer account
     */
    private function create_or_update_customer($customer_data) {
        $user_id = email_exists($customer_data['email']);
        
        if (!$user_id) {
            // Create new user
            $username = sanitize_user(current(explode('@', $customer_data['email'])), true);
            $counter = 1;
            $base_username = $username;
            
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
            
            $random_password = wp_generate_password(12, false);
            
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => $random_password,
                'user_email' => $customer_data['email'],
                'first_name' => $customer_data['first_name'],
                'last_name' => $customer_data['last_name'],
                'role' => 'customer'
            ]);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
            
            // Send password to customer
            wp_new_user_notification($user_id, null, 'user');
        }
        
        // Update user meta
        $meta_fields = [
            'phone' => $customer_data['phone'],
            'billing_first_name' => $customer_data['first_name'],
            'billing_last_name' => $customer_data['last_name'],
            'billing_company' => $customer_data['company'],
            'billing_address_1' => $customer_data['address_1'],
            'billing_address_2' => $customer_data['address_2'],
            'billing_city' => $customer_data['city'],
            'billing_state' => $customer_data['state'],
            'billing_postcode' => $customer_data['postcode'],
            'billing_country' => $customer_data['country'],
            'billing_email' => $customer_data['email'],
            'billing_phone' => $customer_data['phone'],
            'shipping_first_name' => $customer_data['first_name'],
            'shipping_last_name' => $customer_data['last_name'],
            'shipping_company' => $customer_data['company'],
            'shipping_address_1' => $customer_data['address_1'],
            'shipping_address_2' => $customer_data['address_2'],
            'shipping_city' => $customer_data['city'],
            'shipping_state' => $customer_data['state'],
            'shipping_postcode' => $customer_data['postcode'],
            'shipping_country' => $customer_data['country'],
            'shipping_phone' => $customer_data['phone']
        ];
        
        foreach ($meta_fields as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        
        return $user_id;
    }
    
    /**
     * Create customer order
     */
    private function create_customer_order($user_id, $customer_data, $plan_data) {
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            throw new Exception($order->get_error_message());
        }
        
        if ($plan_data['is_existing_product']) {
            // Use existing product
            $product = wc_get_product($plan_data['product_id']);
            
            if (!$product) {
                throw new Exception('Product not found');
            }
            
            // Add product to order
            $item_id = $order->add_product($product, 1);
            
            // For switch plans or if custom price was set, update the line item
            // Switch plans always need custom pricing since customer controls tiffins and addons
            $is_switch_plan = isset($plan_data['is_switch_plan']) && $plan_data['is_switch_plan'];
            if ($is_switch_plan || $product->get_price() != $plan_data['plan_price']) {
                $item = $order->get_item($item_id);
                $item->set_subtotal($plan_data['plan_price']);
                $item->set_total($plan_data['plan_price']);
                $item->add_meta_data('_custom_price', $plan_data['plan_price']);
                $item->save();
            }
            
        } else {
            // Create a virtual product for custom plan
            $product = new WC_Product_Simple();
            $product->set_name($plan_data['plan_name']);
            $product->set_regular_price($plan_data['plan_price']);
            $product->set_status('private');
            $product->set_catalog_visibility('hidden');
            $product->set_virtual(true);
            $product->set_meta_data([
                'customer_plan' => 'yes',
                'plan_description' => $plan_data['plan_description']
            ]);
            $product->save();
            
            // Add product to order
            $item_id = $order->add_product($product, 1);
        }
        
        // Add meta data to the line item
        $item = $order->get_item($item_id);
        $preferred_days = implode(' - ', $customer_data['preferred_days']);
        
        $item->add_meta_data('Start Date', $customer_data['start_date']);
        $item->add_meta_data('Prefered Days', $preferred_days);
        $item->add_meta_data('Veg / Non Veg', $customer_data['meal_type']);
        $item->add_meta_data('Delivery Type', $customer_data['delivery_type']);
        $item->add_meta_data('Number Of Tiffins', $plan_data['number_of_tiffins']);
        
        if (!empty($customer_data['delivery_notes'])) {
            $item->add_meta_data('Special Instructions', $customer_data['delivery_notes']);
        }
        
        // Map add-ons to line item meta for consistency
        // Format: "quantity name + quantity name" (e.g., "2 Rotis + 1 Rice")
        // This works for both existing plans (from salesperson) and switch plans (from customer)
        if (!empty($plan_data['addons']) && is_string($plan_data['addons'])) {
            $clean_addons = trim(sanitize_text_field($plan_data['addons']));
            if ($clean_addons !== '') {
                // Store in order item meta with key 'add-ons' for consistency with existing system
                $item->add_meta_data('add-ons', $clean_addons);
            }
        }
        
        $item->save();
        
        // Set customer
        $order->set_customer_id($user_id);
        
        // Set addresses
        $order->set_billing_first_name($customer_data['first_name']);
        $order->set_billing_last_name($customer_data['last_name']);
        $order->set_billing_email($customer_data['email']);
        $order->set_billing_phone($customer_data['phone']);
        $order->set_billing_company($customer_data['company']);
        $order->set_billing_address_1($customer_data['address_1']);
        $order->set_billing_address_2($customer_data['address_2']);
        $order->set_billing_city($customer_data['city']);
        $order->set_billing_state($customer_data['state']);
        $order->set_billing_postcode($customer_data['postcode']);
        $order->set_billing_country($customer_data['country']);
        
        // Copy to shipping
        $order->set_shipping_first_name($customer_data['first_name']);
        $order->set_shipping_last_name($customer_data['last_name']);
        $order->set_shipping_company($customer_data['company']);
        $order->set_shipping_address_1($customer_data['address_1']);
        $order->set_shipping_address_2($customer_data['address_2']);
        $order->set_shipping_city($customer_data['city']);
        $order->set_shipping_state($customer_data['state']);
        $order->set_shipping_postcode($customer_data['postcode']);
        $order->set_shipping_country($customer_data['country']);
        $order->set_shipping_phone($customer_data['phone']);
        
        // Add order meta
        $order->update_meta_data('Start Date', $customer_data['start_date']);
        $order->update_meta_data('Prefered Days', $preferred_days);
        $order->update_meta_data('Veg / Non Veg', $customer_data['meal_type']);
        $order->update_meta_data('Delivery Type', $customer_data['delivery_type']);
        $order->update_meta_data('Number Of Tiffins', $plan_data['number_of_tiffins']);
        $order->update_meta_data('_customer_order_source', 'customer_link');
        
        if (!empty($customer_data['delivery_notes'])) {
            $order->set_customer_note($customer_data['delivery_notes']);
            $order->add_order_note($customer_data['delivery_notes'], 1);
        }
        
        // Set order status and total
        $order->set_total($plan_data['plan_price']);
        $order->set_status('processing');
        
        // Save order
        $order->save();
        
        // Run tiffin counter if available
        if (class_exists('Satguru_Daily_Tiffin_Counter')) {
            $counter = new Satguru_Daily_Tiffin_Counter();
            $counter->save_daily_tiffin_count($order->get_id());
        }
        
        return $order->get_id();
    }
    
    /**
     * Save pending order for approval
     */
    private function save_pending_order($customer_data, $plan_data, $token) {
        global $wpdb;
        
        // Create the pending orders table if it doesn't exist
        $this->create_pending_orders_table();
        
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        $pending_order_data = [
            'token' => $token,
            'customer_data' => json_encode($customer_data),
            'plan_data' => json_encode($plan_data),
            'submitted_at' => current_time('mysql'),
            'status' => 'pending'
        ];
        
        $result = $wpdb->insert($table_name, $pending_order_data);
        
        if ($result === false) {
            throw new Exception('Failed to save pending order');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create pending orders table
     */
    private function create_pending_orders_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            token varchar(255) NOT NULL,
            customer_data longtext NOT NULL,
            plan_data longtext NOT NULL,
            submitted_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            approved_at datetime NULL,
            approved_by int(11) NULL,
            order_id int(11) NULL,
            notes text NULL,
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_submitted_at (submitted_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    

    
    /**
     * AJAX handler for approving pending orders
     */
    public function ajax_approve_pending_order() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $order_id = absint($_POST['order_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        // Get pending order
        $pending_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND status = 'pending'",
            $order_id
        ));
        
        if (!$pending_order) {
            wp_send_json_error('Pending order not found');
        }
        
        try {
            $customer_data = json_decode($pending_order->customer_data, true);
            $plan_data = json_decode($pending_order->plan_data, true);
            
            // Create customer account
            $user_id = $this->create_or_update_customer($customer_data);
            
            // Create the actual WooCommerce order
            $wc_order_id = $this->create_customer_order($user_id, $customer_data, $plan_data);
            
            // Update pending order status
            $wpdb->update(
                $table_name,
                [
                    'status' => 'approved',
                    'approved_at' => current_time('mysql'),
                    'approved_by' => get_current_user_id(),
                    'order_id' => $wc_order_id
                ],
                ['id' => $order_id]
            );
            
            // Send order confirmation email to customer
            $this->send_order_confirmation_email($customer_data, $plan_data, $wc_order_id);
            
            // Log the order approval
            $current_user = wp_get_current_user();
            $this->log_activity('order_approved', [
                'plan_name' => $plan_data['plan_name'],
                'price' => $plan_data['plan_price'],
                'number_of_tiffins' => $plan_data['number_of_tiffins'],
                'pending_id' => $order_id,
                'order_id' => $wc_order_id
            ], [
                'name' => $current_user->display_name,
                'email' => $current_user->user_email,
                'role' => 'admin'
            ], "Approved pending order #{$order_id} for {$customer_data['first_name']} {$customer_data['last_name']} - Created WC Order #{$wc_order_id}");
            

            wp_send_json_success([
                'message' => 'Order approved and created successfully',
                'wc_order_id' => $wc_order_id
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to approve order: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for rejecting pending orders
     */
    public function ajax_reject_pending_order() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $order_id = absint($_POST['order_id']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        // Get pending order for logging
        $pending_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $order_id
        ));
        
        if (!$pending_order) {
            wp_send_json_error('Pending order not found');
        }
        
        $customer_data = json_decode($pending_order->customer_data, true);
        $plan_data = json_decode($pending_order->plan_data, true);
        
        // Update pending order status
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'rejected',
                'approved_at' => current_time('mysql'),
                'approved_by' => get_current_user_id(),
                'notes' => $reason
            ],
            ['id' => $order_id]
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to reject order');
        }
        

        wp_send_json_success(['message' => 'Order rejected successfully']);
    }
    
    /**
     * AJAX handler for getting pending order details
     */
    public function ajax_get_pending_order_details() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $order_id = absint($_POST['order_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        
        // Get pending order
        $pending_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $order_id
        ));
        
        if (!$pending_order) {
            wp_send_json_error('Order not found');
        }
        
        $customer_data = json_decode($pending_order->customer_data, true);
        $plan_data = json_decode($pending_order->plan_data, true);
        
        $html = '<div>';
        $html .= '<h2>Order Details #' . $pending_order->id . '</h2>';
        
        // Customer Information
        $html .= '<div class="order-detail-section">';
        $html .= '<h3>Customer Information</h3>';
        $html .= '<p><strong>Name:</strong> ' . esc_html($customer_data['first_name'] . ' ' . $customer_data['last_name']) . '</p>';
        $html .= '<p><strong>Email:</strong> ' . esc_html($customer_data['email']) . '</p>';
        $html .= '<p><strong>Phone:</strong> ' . esc_html($customer_data['phone']) . '</p>';
        if (!empty($customer_data['company'])) {
            $html .= '<p><strong>Company:</strong> ' . esc_html($customer_data['company']) . '</p>';
        }
        $html .= '</div>';
        
        // Delivery Address
        $html .= '<div class="order-detail-section">';
        $html .= '<h3>Delivery Address</h3>';
        $html .= '<p>' . esc_html($customer_data['address_1']) . '</p>';
        if (!empty($customer_data['address_2'])) {
            $html .= '<p>' . esc_html($customer_data['address_2']) . '</p>';
        }
        $html .= '<p>' . esc_html($customer_data['city'] . ', ' . $customer_data['state'] . ' ' . $customer_data['postcode']) . '</p>';
        $html .= '<p>' . esc_html($customer_data['country']) . '</p>';
        $html .= '</div>';
        
        // Plan Details
        $html .= '<div class="order-detail-section">';
        $html .= '<h3>Plan Details</h3>';
        $html .= '<p><strong>Plan Name:</strong> ' . esc_html($plan_data['plan_name']) . '</p>';
        $html .= '<p><strong>Tiffins:</strong> ' . esc_html($plan_data['number_of_tiffins']) . '</p>';
        $html .= '<p><strong>Price:</strong> $' . number_format($plan_data['plan_price'], 2) . '</p>';
        if (!empty($plan_data['addons'])) {
            $html .= '<p><strong>Add-ons:</strong> ' . esc_html($plan_data['addons']) . '</p>';
        }
        if (!empty($plan_data['plan_description'])) {
            $html .= '<p><strong>Description:</strong> ' . esc_html($plan_data['plan_description']) . '</p>';
        }
        $html .= '</div>';
        
        // Order Preferences
        $html .= '<div class="order-detail-section">';
        $html .= '<h3>Order Preferences</h3>';
        $html .= '<p><strong>Start Date:</strong> ' . esc_html($customer_data['start_date']) . '</p>';
        $html .= '<p><strong>Preferred Days:</strong> ' . esc_html(implode(', ', $customer_data['preferred_days'])) . '</p>';
        $html .= '<p><strong>Meal Type:</strong> ' . esc_html($customer_data['meal_type']) . '</p>';
        $html .= '<p><strong>Service Type:</strong> ' . esc_html($customer_data['delivery_type']) . '</p>';
        if (!empty($customer_data['delivery_notes'])) {
            $html .= '<p><strong>Special Instructions:</strong> ' . esc_html($customer_data['delivery_notes']) . '</p>';
        }
        $html .= '</div>';
        
        // Submission Info
        $html .= '<div class="order-detail-section">';
        $html .= '<h3>Submission Information</h3>';
        $html .= '<p><strong>Submitted:</strong> ' . date('F j, Y g:i A', strtotime($pending_order->submitted_at)) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . ucfirst($pending_order->status) . '</p>';
        if ($pending_order->notes) {
            $html .= '<p><strong>Notes:</strong> ' . esc_html($pending_order->notes) . '</p>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * AJAX handler for refreshing pending orders table
     */
    public function ajax_refresh_pending_orders_table() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Capture the table HTML
        ob_start();
        $this->render_pending_orders_table();
        $table_html = ob_get_clean();
        
        // Get pending orders count
        global $wpdb;
        $table_name = $wpdb->prefix . 'customer_pending_orders';
        $pending_count = 0;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        }
        
        wp_send_json_success([
            'html' => $table_html,
            'count' => $pending_count
        ]);
    }
    
    /**
     * Render the activity logs content
     */
    public function render_activity_logs_content() {
        ?>
        <div class="logs-controls">
            <div class="logs-header">
                <h2>Activity Logs</h2>
                <div class="logs-actions">
                    <button class="button logs-refresh-btn">Refresh Logs</button>
                    <button class="button button-secondary create-logs-table-btn">Create Logs Table</button>
                </div>
            </div>
            
            <div class="logs-filters">
                <div class="filter-group">
                    <label for="log-action-filter">Action:</label>
                    <select id="log-action-filter" class="logs-filter">
                        <option value="">All Actions</option>
                        <option value="link_generated">Link Generated</option>
                        <option value="order_submitted">Order Submitted</option>
                        <option value="order_approved">Order Approved</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="log-date-filter">Date:</label>
                    <select id="log-date-filter" class="logs-filter">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="last_week">Last Week</option>
                        <option value="this_month">This Month</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="log-search">Search:</label>
                    <input type="text" id="log-search" placeholder="Search customer name, email, plan..." class="logs-filter">
                </div>
                
                <button class="button" id="apply-log-filters">Apply Filters</button>
                <button class="button" id="clear-log-filters">Clear</button>
                <button class="button button-secondary" id="show-all-logs">Show All</button>
            </div>
        </div>
        
        <div id="logs-status-info" class="logs-status-info">
            <?php $this->render_logs_status(); ?>
        </div>
        
        <div id="activity-logs-table-container">
            <?php $this->render_activity_logs_table([]); ?>
        </div>
        
        <style>
        .logs-controls {
            background: #f1f1f1;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logs-header h2 {
            margin: 0;
            color: #333;
        }
        
        .logs-filters {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }
        
        .logs-filter {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }
        
        .logs-actions {
            display: flex;
            gap: 10px;
        }
        
        .logs-refresh-btn {
            margin-left: 10px;
        }
        
        .log-entry-details {
            background: #f9f9f9;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
            font-size: 12px;
            color: #666;
        }
        
        .log-action-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .log-action-link_generated {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .log-action-order_submitted {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .log-action-order_approved {
            background: #e8f5e8;
            color: #388e3c;
        }
        

        
        .logs-status-info {
            background: #f0f6fc;
            border: 1px solid #d1ecf1;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .logs-status-info.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .logs-status-info.success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Refresh logs button
            $(document).on('click', '.logs-refresh-btn', function() {
                window.refreshActivityLogs();
            });
            
            // Create logs table button
            $(document).on('click', '.create-logs-table-btn', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'create_logs_table',
                        nonce: '<?php echo wp_create_nonce('pending_order_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Logs table created successfully!');
                            // Refresh both status and logs
                            window.refreshLogsStatus();
                            window.refreshActivityLogs();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to create logs table');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Create Logs Table');
                    }
                });
            });
            
            // Apply filters
            $('#apply-log-filters').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Filtering...');
                
                window.refreshActivityLogs();
                
                setTimeout(function() {
                    $btn.prop('disabled', false).text('Apply Filters');
                }, 1000);
            });
            
            // Clear filters
            $('#clear-log-filters').on('click', function() {
                $('.logs-filter').val('');
                window.refreshActivityLogs();
            });
            
            // Show all logs
            $('#show-all-logs').on('click', function() {
                $('.logs-filter').val('');
                window.refreshActivityLogs();
            });
            
            // Auto-filter on enter for search
            $('#log-search').on('keypress', function(e) {
                if (e.which === 13) {
                    window.refreshActivityLogs();
                }
            });
            
            // Handle pagination button clicks
            $(document).on('click', '.logs-page-btn:not(.disabled)', function() {
                var page = $(this).data('page');
                if (page) {
                    window.refreshActivityLogs(page);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render logs status information
     */
    public function render_logs_status() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'customer_order_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table;
        
        if (!$table_exists) {
            ?>
            <div class="logs-status-info error">
                <p><strong>?? Logs Database Table Not Found</strong></p>
                <p>The activity logs table doesn't exist yet. Click the "Create Logs Table" button above to create it and start logging activities.</p>
            </div>
            <?php
        } else {
            $log_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
            ?>
            <div class="logs-status-info success">
                <p><strong>? Logs Database Active</strong></p>
                <p>Activity logging is working properly. Total log entries: <strong><?php echo number_format($log_count); ?></strong></p>
            </div>
            <?php
        }
    }
    
    /**
     * Render the activity logs table
     */
    public function render_activity_logs_table($filters = []) {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'customer_order_logs';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") != $logs_table) {
            ?>
            <div class="logs-empty">
                <div class="dashicons dashicons-database"></div>
                <h3>Logs Table Not Created</h3>
                <p>Please click the "Create Logs Table" button above to create the database table for activity logs.</p>
            </div>
            <?php
            return;
        }
        
        // Build WHERE clause based on filters
        $where_conditions = [];
        $where_values = [];
        
        // Always limit to our main 3 actions, but if a specific action is selected, use only that
        if (!empty($filters['action_filter'])) {
            $where_conditions[] = 'action = %s';
            $where_values[] = $filters['action_filter'];
        } else {
            $where_conditions[] = "action IN ('link_generated', 'order_submitted', 'order_approved')";
        }
        
        if (!empty($filters['date_filter'])) {
            $date_filter = $filters['date_filter'];
            switch ($date_filter) {
                case 'today':
                    $where_conditions[] = 'DATE(created_at) = CURDATE()';
                    break;
                case 'yesterday':
                    $where_conditions[] = 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
                    break;
                case 'this_week':
                    $where_conditions[] = 'YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)';
                    break;
                case 'last_week':
                    $where_conditions[] = 'YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)';
                    break;
                case 'this_month':
                    $where_conditions[] = 'YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())';
                    break;
            }
        }
        
        if (!empty($filters['search_term'])) {
            $search = '%' . $filters['search_term'] . '%';
            $where_conditions[] = '(data LIKE %s OR user_info LIKE %s)';
            $where_values[] = $search;
            $where_values[] = $search;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get pagination parameters
        $per_page = 10;
        $current_page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Count total records for pagination
        if ($where_values) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table WHERE $where_clause",
                $where_values
            );
        } else {
            $count_query = "SELECT COUNT(*) FROM $logs_table WHERE $where_clause";
        }
        $total_items = $wpdb->get_var($count_query);
        $total_pages = ceil($total_items / $per_page);
        
        // Build the final query
        if ($where_values) {
            $query = $wpdb->prepare(
                "SELECT * FROM $logs_table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($where_values, [$per_page, $offset])
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $logs_table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            );
        }
        
        // Debug log the final query
        error_log('Activity Logs Query: ' . $query);
        
        $logs = $wpdb->get_results($query);
        
        if (empty($logs)) {
            ?>
            <div class="logs-empty">
                <div class="dashicons dashicons-clipboard"></div>
                <h3>No Activity Logs</h3>
                <p>No activity logs found for the selected filters.</p>
            </div>
            <?php
            return;
        }
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;">Date & Time</th>
                    <th style="width: 120px;">Action</th>
                    <th style="width: 200px;">User/Customer</th>
                    <th>Additional Information</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $data = json_decode($log->data, true);
                    $user_info = json_decode($log->user_info, true);
                    $action_labels = [
                        'link_generated' => 'Link Generated',
                        'order_submitted' => 'Order Submitted',
                        'order_approved' => 'Order Approved'
                    ];
                ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M j, Y', strtotime($log->created_at)); ?></strong><br>
                            <small><?php echo date('g:i A', strtotime($log->created_at)); ?></small>
                        </td>
                        <td>
                            <span class="log-action-badge log-action-<?php echo $log->action; ?>">
                                <?php echo $action_labels[$log->action] ?? ucfirst($log->action); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user_info): ?>
                                <strong><?php echo esc_html($user_info['name'] ?? 'N/A'); ?></strong><br>
                                <small><?php echo esc_html($user_info['email'] ?? 'N/A'); ?></small>
                            <?php else: ?>
                                <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($data): ?>
                                <?php if ($log->action === 'link_generated'): ?>
                                    <!-- Link Generated: Show price, plan name, number of tiffins -->
                                    <?php if (isset($data['plan_name'])): ?>
                                        <strong>Plan:</strong> <?php echo esc_html($data['plan_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['price'])): ?>
                                        <strong>Price:</strong> <?php echo wc_price($data['price']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['number_of_tiffins'])): ?>
                                        <strong>Tiffins:</strong> <?php echo esc_html($data['number_of_tiffins']); ?>
                                    <?php endif; ?>
                                    
                                <?php elseif ($log->action === 'order_submitted'): ?>
                                    <!-- Order Submitted: Show plan name, start date, delivery type, preferred days -->
                                    <?php if (isset($data['plan_name'])): ?>
                                        <strong>Plan:</strong> <?php echo esc_html($data['plan_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['start_date'])): ?>
                                        <strong>Start Date:</strong> <?php echo esc_html(date('M j, Y', strtotime($data['start_date']))); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['delivery_type'])): ?>
                                        <strong>Delivery:</strong> <?php echo esc_html($data['delivery_type']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['preferred_days'])): ?>
                                        <strong>Days:</strong> <?php echo esc_html(is_array($data['preferred_days']) ? implode(', ', $data['preferred_days']) : $data['preferred_days']); ?>
                                    <?php endif; ?>
                                    
                                <?php elseif ($log->action === 'order_approved'): ?>
                                    <!-- Order Approved: Show salesperson name -->
                                    <?php if ($user_info && isset($user_info['name'])): ?>
                                        <strong>Approved by:</strong> <?php echo esc_html($user_info['name']); ?>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <!-- For other actions, show basic info -->
                                    <?php if (isset($data['plan_name'])): ?>
                                        <strong>Plan:</strong> <?php echo esc_html($data['plan_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['order_id'])): ?>
                                        <strong>Order ID:</strong> #<?php echo esc_html($data['order_id']); ?><br>
                                    <?php endif; ?>
                                    <?php if (isset($data['pending_id'])): ?>
                                        <strong>Pending ID:</strong> #<?php echo esc_html($data['pending_id']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Display pagination info and controls
        if ($total_items > 0) {
            $start_item = ($current_page - 1) * $per_page + 1;
            $end_item = min($current_page * $per_page, $total_items);
            
            echo '<div class="logs-pagination-container">';
            echo '<div class="logs-pagination-info">';
            echo '<p>Showing ' . $start_item . ' to ' . $end_item . ' of ' . $total_items . ' entries</p>';
            echo '</div>';
            
            if ($total_pages > 1) {
                echo '<div class="logs-pagination-nav">';
                
                // Previous button
                if ($current_page > 1) {
                    $prev_page = $current_page - 1;
                    echo '<button type="button" class="button logs-page-btn" data-page="' . $prev_page . '">‹ Previous</button>';
                } else {
                    echo '<button type="button" class="button logs-page-btn disabled">‹ Previous</button>';
                }
                
                // Page numbers (show first, current-1, current, current+1, last)
                $pages_to_show = [];
                
                // Always show first page
                if ($total_pages > 1) {
                    $pages_to_show[] = 1;
                }
                
                // Show pages around current page
                for ($i = max(2, $current_page - 1); $i <= min($total_pages - 1, $current_page + 1); $i++) {
                    if (!in_array($i, $pages_to_show)) {
                        $pages_to_show[] = $i;
                    }
                }
                
                // Always show last page
                if ($total_pages > 1 && !in_array($total_pages, $pages_to_show)) {
                    $pages_to_show[] = $total_pages;
                }
                
                sort($pages_to_show);
                
                $prev_page_num = 0;
                foreach ($pages_to_show as $page_num) {
                    // Add ellipsis if there's a gap
                    if ($page_num - $prev_page_num > 1) {
                        echo '<span class="logs-page-ellipsis">...</span>';
                    }
                    
                    if ($page_num == $current_page) {
                        echo '<button type="button" class="button button-primary logs-page-btn current" data-page="' . $page_num . '">' . $page_num . '</button>';
                    } else {
                        echo '<button type="button" class="button logs-page-btn" data-page="' . $page_num . '">' . $page_num . '</button>';
                    }
                    
                    $prev_page_num = $page_num;
                }
                
                // Next button
                if ($current_page < $total_pages) {
                    $next_page = $current_page + 1;
                    echo '<button type="button" class="button logs-page-btn" data-page="' . $next_page . '">Next ›</button>';
                } else {
                    echo '<button type="button" class="button logs-page-btn disabled">Next ›</button>';
                }
                
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
        
        <style>
        .logs-empty {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        #footer-thankyou {
            display: none;
        }
        
        .logs-empty .dashicons {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .text-muted {
            color: #999;
            font-style: italic;
        }
        
        /* Pagination Styles */
        .logs-pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding: 10px 0;
            border-top: 1px solid #ddd;
        }
        
        .logs-pagination-info p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        
        .logs-pagination-nav {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .logs-page-btn {
            min-width: 35px;
            height: 30px;
            font-size: 12px;
            padding: 0 8px;
        }
        
        .logs-page-btn.current {
            background: #0073aa;
            border-color: #0073aa;
            color: white;
        }
        
        .logs-page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .logs-page-ellipsis {
            padding: 0 5px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .logs-pagination-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .logs-pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for refreshing activity logs table
     */
    public function ajax_refresh_activity_logs_table() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Pass filter data to the render method
        $filters = [
            'action_filter' => sanitize_text_field($_POST['action_filter'] ?? ''),
            'date_filter' => sanitize_text_field($_POST['date_filter'] ?? ''),
            'search_term' => sanitize_text_field($_POST['search_term'] ?? '')
        ];
        
        // Handle pagination - set the page parameter for the render method
        if (isset($_POST['page_number'])) {
            $_GET['logs_page'] = intval($_POST['page_number']);
        }
        
        // Debug log
        error_log('Activity Logs Filters: ' . print_r($filters, true));
        error_log('Activity Logs Page: ' . ($_GET['logs_page'] ?? 1));
        
        // Capture the table HTML
        ob_start();
        $this->render_activity_logs_table($filters);
        $table_html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $table_html
        ]);
    }
    
    /**
     * AJAX handler for creating logs table
     */
    public function ajax_create_logs_table() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $this->create_activity_logs_table();
            
            wp_send_json_success('Logs table created successfully!');
            
        } catch (Exception $e) {
            wp_send_json_error('Failed to create logs table: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for refreshing logs status
     */
    public function ajax_refresh_logs_status() {
        check_ajax_referer('pending_order_action', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Capture the status HTML
        ob_start();
        $this->render_logs_status();
        $status_html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $status_html
        ]);
    }
    
    /**
     * Create activity logs database table
     */
    private function create_activity_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'customer_order_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            data longtext,
            user_info longtext,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log activity to database
     */
    private function log_activity($action, $data = [], $user_info = [], $notes = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'customer_order_logs';
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_activity_logs_table();
        }
        
        $wpdb->insert(
            $table_name,
            [
                'action' => $action,
                'data' => json_encode($data),
                'user_info' => json_encode($user_info),
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Check if the current plan is a trial meal
     */
    private function is_trial_meal() {
        $plan_name = strtolower($this->plan_data['plan_name']);
        $trial_keywords = [
            'trial meal (non-veg) (5 item)',
            'trial meal (veg) (5item)',
            'trial meal'
        ];
        
        foreach ($trial_keywords as $keyword) {
            if (strpos($plan_name, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send order confirmation email to customer
     */
    private function send_order_confirmation_email($customer_data, $plan_data, $wc_order_id) {
        $customer_email = $customer_data['email'];
        $customer_name = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
        
        // Get WooCommerce order for additional details
        $wc_order = wc_get_order($wc_order_id);
        $order_date = $wc_order ? $wc_order->get_date_created()->format('F j, Y') : date('F j, Y');
        
        // Email subject
        $subject = 'Order Confirmed - Your Tiffin Service is Ready! Order #' . $wc_order_id;
        
        // Email headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Build email content
        $message = $this->build_confirmation_email_html($customer_data, $plan_data, $wc_order_id, $order_date);
        
        // Send email
        $email_sent = wp_mail($customer_email, $subject, $message, $headers);
        
        // Log email sending
        if ($email_sent) {
            error_log("Order confirmation email sent to: {$customer_email} for Order #{$wc_order_id}");
        } else {
            error_log("Failed to send order confirmation email to: {$customer_email} for Order #{$wc_order_id}");
        }
        
        return $email_sent;
    }
    
    /**
     * Build the HTML content for order confirmation email
     */
    private function build_confirmation_email_html($customer_data, $plan_data, $wc_order_id, $order_date) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $customer_name = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
        
        // Format preferred days
        $preferred_days = is_array($customer_data['preferred_days']) ? 
            implode(', ', $customer_data['preferred_days']) : 
            $customer_data['preferred_days'];
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Order Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .email-container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .email-header { background: #28a745; color: #ffffff; padding: 30px 20px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .email-header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.9; }
                .email-body { padding: 30px 20px; }
                .section { margin-bottom: 30px; }
                .section h2 { color: #28a745; font-size: 20px; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 5px; }
                .order-summary { background: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #28a745; }
                .info-grid { display: table; width: 100%; }
                .info-row { display: table-row; }
                .info-label, .info-value { display: table-cell; padding: 8px 0; vertical-align: top; }
                .info-label { font-weight: bold; width: 40%; color: #495057; }
                .info-value { color: #212529; }
                .price-highlight { font-size: 24px; font-weight: bold; color: #28a745; }
                .next-steps { background: #e3f2fd; padding: 20px; border-radius: 6px; border-left: 4px solid #2196f3; }
                .next-steps ul { margin: 10px 0; padding-left: 20px; }
                .next-steps li { margin-bottom: 8px; }
                .email-footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
                .email-footer a { color: #007cba; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>?? Order Confirmed!</h1>
                    <p>Your tiffin service is ready to begin</p>
                </div>
                
                <div class="email-body">
                    <div class="section">
                        <p>Dear <strong>' . esc_html($customer_name) . '</strong>,</p>
                        <p>Great news! Your tiffin service order has been <strong>approved and confirmed</strong> by our sales team. We\'re excited to start serving you delicious, fresh meals!</p>
                    </div>
                    
                    <div class="section">
                        <h2>?? Order Details</h2>
                        <div class="order-summary">
                            <div class="info-grid">
                                <div class="info-row">
                                    <div class="info-label">Order Number:</div>
                                    <div class="info-value"><strong>#' . $wc_order_id . '</strong></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Order Date:</div>
                                    <div class="info-value">' . $order_date . '</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Plan Name:</div>
                                    <div class="info-value"><strong>' . esc_html($plan_data['plan_name']) . '</strong></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Number of Tiffins:</div>
                                    <div class="info-value">' . esc_html($plan_data['number_of_tiffins']) . '</div>
                                </div>
                                ' . (!empty($plan_data['addons']) ? '<div class="info-row"><div class="info-label">Add-ons:</div><div class="info-value">' . esc_html($plan_data['addons']) . '</div></div>' : '') . '
                                <div class="info-row">
                                    <div class="info-label">Total Amount:</div>
                                    <div class="info-value"><span class="price-highlight">$' . number_format($plan_data['plan_price'], 2) . '</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>?? Delivery Information</h2>
                        <div class="info-grid">
                            <div class="info-row">
                                <div class="info-label">Start Date:</div>
                                <div class="info-value">' . esc_html(date('F j, Y', strtotime($customer_data['start_date']))) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Delivery Days:</div>
                                <div class="info-value">' . esc_html($preferred_days) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Meal Type:</div>
                                <div class="info-value">' . esc_html($customer_data['meal_type']) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Delivery Type:</div>
                                <div class="info-value">' . esc_html($customer_data['delivery_type']) . '</div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Delivery Address:</div>
                                <div class="info-value">
                                    ' . esc_html($customer_data['address_1']) . '<br>
                                    ' . (!empty($customer_data['address_2']) ? esc_html($customer_data['address_2']) . '<br>' : '') . '
                                    ' . esc_html($customer_data['city'] . ', ' . $customer_data['state'] . ' ' . $customer_data['postcode']) . '<br>
                                    ' . esc_html($customer_data['country']) . '
                                </div>
                            </div>';
                            
        if (!empty($customer_data['delivery_notes'])) {
            $html .= '
                            <div class="info-row">
                                <div class="info-label">Special Instructions:</div>
                                <div class="info-value">' . esc_html($customer_data['delivery_notes']) . '</div>
                            </div>';
        }
        
        $html .= '
                        </div>
                    </div>
                    
                    <div class="section">
                        <div class="next-steps">
                            <h2 style="color: #1976d2; margin-top: 0;">?? What Happens Next?</h2>
                            <ul>
                                <li><strong>Preparation:</strong> Our kitchen team will prepare your meals fresh daily</li>
                                <li><strong>Delivery Start:</strong> Your first tiffin will be delivered on ' . esc_html(date('F j, Y', strtotime($customer_data['start_date']))) . '</li>
                                <li><strong>Schedule:</strong> Deliveries will continue on your selected days: ' . esc_html($preferred_days) . '</li>
                                <li><strong>Quality Guarantee:</strong> We ensure fresh, and delicious meals every time</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h2>?? Need Help?</h2>
                        <p>If you have any questions about your order or need to make changes, please don\'t hesitate to contact us:</p>
                        <ul>
                            <li><strong>Phone:</strong> Contact your sales representative</li>
                        </ul>
                    </div>
                    
                    <div class="section">
                        <p><strong>Thank you for choosing ' . $site_name . '!</strong> We look forward to serving you delicious meals and making your dining experience exceptional.</p>
                    </div>
                </div>
                <div class="email-footer">
                    <p>&copy; ' . date('Y') . ' <a href="' . $site_url . '">' . $site_name . '</a>. All rights reserved.</p>
                    <p>This email was sent because your tiffin service order was approved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Parse preferred days from form input
     */
    private function parse_preferred_days($preferred_days_input) {
        $sanitized_input = sanitize_text_field($preferred_days_input);
        
        // If it's already in "Monday - Friday" format (trial meals or range format), return as single item array
        if (strpos($sanitized_input, ' - ') !== false) {
            return [$sanitized_input];
        }
        
        // If it's comma-separated days, return as array
        if (strpos($sanitized_input, ',') !== false) {
            return array_map('trim', array_map('sanitize_text_field', explode(',', $sanitized_input)));
        }
        
        // Single day selection
        return [$sanitized_input];
    }
    
    /**
     * Enqueue customer scripts
     */
    public function enqueue_customer_scripts() {
        if (get_query_var('customer_order_token')) {
            wp_enqueue_script('jquery');
            
            // Enqueue Flatpickr for date picker
            wp_enqueue_style(
                'flatpickr-css',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                array(),
                '4.6.13'
            );
            
            wp_enqueue_style(
                'flatpickr-theme',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css',
                array('flatpickr-css'),
                '4.6.13'
            );

            wp_enqueue_script(
                'flatpickr-js',
                'https://cdn.jsdelivr.net/npm/flatpickr',
                array('jquery'),
                '4.6.13',
                true
            );
        }
    }
}

// Initialize the system
new Customer_Self_Order_System();

// ============================================
// Generated Link Addons System
// Separate addons system for customer order forms
// ============================================

// Register Generated Link Addons Custom Post Type
function register_generated_link_addons_post_type() {
    $labels = array(
        'name'               => 'Generated Link Addons',
        'singular_name'      => 'Generated Link Addon',
        'menu_name'          => 'Link Addons',
        'add_new'           => 'Add New',
        'add_new_item'      => 'Add New Link Addon',
        'edit_item'         => 'Edit Link Addon',
        'new_item'          => 'New Link Addon',
        'view_item'         => 'View Link Addon',
        'search_items'      => 'Search Link Addons',
        'not_found'         => 'No link addons found',
        'not_found_in_trash'=> 'No link addons found in trash'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'menu_position'       => 58, // After WooCommerce
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'rewrite'             => array('slug' => 'generated-link-addon'),
        'supports'            => array('title'),
        'menu_icon'           => 'dashicons-cart',
        'show_in_rest'        => true
    );

    register_post_type('generated_link_addon', $args);
}
add_action('init', 'register_generated_link_addons_post_type');

// Add Meta Box for Generated Link Addon Details
function add_generated_link_addon_meta_boxes() {
    add_meta_box(
        'generated_link_addon_details',
        'Addon Details',
        'render_generated_link_addon_meta_box',
        'generated_link_addon',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_generated_link_addon_meta_boxes');

// Render Meta Box Content
function render_generated_link_addon_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('generated_link_addon_meta_box', 'generated_link_addon_meta_box_nonce');

    // Get existing values
    $price = get_post_meta($post->ID, '_gl_addon_price', true);
    $max_quantity = get_post_meta($post->ID, '_gl_max_quantity', true);
    $status = get_post_meta($post->ID, '_gl_addon_status', true);
    if (empty($status)) {
        $status = 'active'; // Default to active
    }

    ?>
    <div class="generated-link-addon-meta-box">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="gl_addon_price">Price per Unit ($):</label>
                </th>
                <td>
                    <input type="number" 
                           id="gl_addon_price" 
                           name="gl_addon_price" 
                           value="<?php echo esc_attr($price); ?>" 
                           step="0.01" 
                           min="0"
                           class="regular-text"
                           required>
                    <p class="description">Price per unit of this addon. Will be multiplied by quantity and number of tiffins.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="gl_max_quantity">Maximum Quantity per Tiffin:</label>
                </th>
                <td>
                    <input type="number" 
                           id="gl_max_quantity" 
                           name="gl_max_quantity" 
                           value="<?php echo esc_attr($max_quantity ?: 20); ?>" 
                           min="1"
                           class="regular-text"
                           required>
                    <p class="description">Maximum quantity a customer can select per tiffin.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="gl_addon_status">Status:</label>
                </th>
                <td>
                    <select id="gl_addon_status" name="gl_addon_status" class="regular-text">
                        <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>Inactive</option>
                    </select>
                    <p class="description">Only active addons will appear in the customer order form.</p>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

// Save Meta Box Data
function save_generated_link_addon_meta_box($post_id) {
    // Check if our nonce is set and verify it
    if (!isset($_POST['generated_link_addon_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['generated_link_addon_meta_box_nonce'], 'generated_link_addon_meta_box')) {
        return;
    }

    // If this is an autosave, don't do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the data
    if (isset($_POST['gl_addon_price'])) {
        update_post_meta($post_id, '_gl_addon_price', 
            sanitize_text_field($_POST['gl_addon_price']));
    }

    if (isset($_POST['gl_max_quantity'])) {
        update_post_meta($post_id, '_gl_max_quantity', 
            sanitize_text_field($_POST['gl_max_quantity']));
    }

    if (isset($_POST['gl_addon_status'])) {
        update_post_meta($post_id, '_gl_addon_status', 
            sanitize_text_field($_POST['gl_addon_status']));
    }
}
add_action('save_post_generated_link_addon', 'save_generated_link_addon_meta_box');

// Helper function to get all active generated link addons
function get_active_generated_link_addons() {
    $args = array(
        'post_type' => 'generated_link_addon',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_gl_addon_status',
                'value' => 'active',
                'compare' => '='
            )
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    );

    $addons = get_posts($args);
    $formatted_addons = array();

    foreach ($addons as $addon) {
        $price = get_post_meta($addon->ID, '_gl_addon_price', true);
        $max_quantity = get_post_meta($addon->ID, '_gl_max_quantity', true);
        
        $formatted_addons[] = array(
            'id' => $addon->ID,
            'name' => $addon->post_title,
            'price' => $price ? floatval($price) : 0,
            'max_quantity' => $max_quantity ? intval($max_quantity) : 20
        );
    }

    return $formatted_addons;
}

// Initialize database tables and setup on activation
function customer_order_system_activate() {
    flush_rewrite_rules();
    
    // Create pending orders table
    global $wpdb;
    $table_name = $wpdb->prefix . 'customer_pending_orders';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        token varchar(255) NOT NULL,
        customer_data longtext NOT NULL,
        plan_data longtext NOT NULL,
        submitted_at datetime NOT NULL,
        status varchar(20) DEFAULT 'pending',
        approved_at datetime NULL,
        approved_by int(11) NULL,
        order_id int(11) NULL,
        notes text NULL,
        PRIMARY KEY (id),
        INDEX idx_status (status),
        INDEX idx_submitted_at (submitted_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Create activity logs table
    $logs_table = $wpdb->prefix . 'customer_order_logs';
    
    $logs_sql = "CREATE TABLE $logs_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        action varchar(50) NOT NULL,
        data longtext,
        user_info longtext,
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    dbDelta($logs_sql);
}

// Flush rewrite rules on activation (for theme, we'll do this on init)
add_action('init', function() {
    // Flush rewrite rules once after theme activation
    if (get_option('customer_order_system_flush_rewrite_rules') !== 'done') {
        flush_rewrite_rules();
        update_option('customer_order_system_flush_rewrite_rules', 'done');
    }
}, 20);