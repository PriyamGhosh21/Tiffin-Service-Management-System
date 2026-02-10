<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/tiffin_logic.php';
require_once get_stylesheet_directory() . '/meal-plan-compositions.php';

/**
 * Order Dispatch Counter Class
 * Handles counting orders for tomorrow's dispatch with live updates
 */
class Satguru_Order_Dispatch_Counter {
    
    public function __construct() {
        add_action('wp_ajax_get_dispatch_count', array($this, 'ajax_get_dispatch_count'));
        add_action('wp_ajax_nopriv_get_dispatch_count', array($this, 'ajax_get_dispatch_count'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add admin menu for dispatch counter
     */
    public function add_admin_menu() {
            add_submenu_page(
        'satguru-admin-dashboard',
        'Dispatch Counter',
        'Dispatch Counter',
        'manage_options',
        'dispatch-counter',
        array($this, 'dispatch_counter_page')
    );

    add_submenu_page(
        'satguru-admin-dashboard',
        'Meal Composition Tester',
        'Meal Tester',
        'manage_options',
        'meal-composition-tester',
        array($this, 'meal_composition_tester_page')
    );
    }

    /**
     * Enqueue scripts for live updates
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'dispatch-counter') !== false || 
            strpos($hook, 'satguru-admin-dashboard') !== false) {
            
            wp_enqueue_script(
                'dispatch-counter-js',
                get_stylesheet_directory_uri() . '/js/dispatch-counter.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_localize_script('dispatch-counter-js', 'dispatchCounter', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dispatch_counter_nonce')
            ));
            
            wp_enqueue_style(
                'dispatch-counter-css',
                get_stylesheet_directory_uri() . '/css/dispatch-counter.css',
                array(),
                '1.0.0'
            );
        }
    }

    /**
     * Get dispatch count for a specific date
     */
    public function get_dispatch_count($date, $operational = false) {
        // Get only processing orders scheduled for this date
        $processing_orders = $this->get_orders_for_dispatch($date);
        
        $total_orders = count($processing_orders);
        $total_boxes = 0;
        
        foreach ($processing_orders as $order) {
            $boxes = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $date);
            $total_boxes += intval($boxes);
        }
        
        // Calculate items breakdown only for processing orders scheduled for this date
        $items_breakdown = Satguru_Meal_Plan_Compositions::calculate_items_for_date($processing_orders, $date);
        
        // Get counts for all orders (for informational purposes)
        $all_orders_args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array_keys(wc_get_order_statuses()),
            'posts_per_page' => -1,
        );
        $all_orders = wc_get_orders($all_orders_args);
        
        $all_processing = 0;
        $all_completed = 0;
        $all_cancelled = 0;
        
        foreach ($all_orders as $order) {
            // Only count orders that have remaining tiffins
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            if ($remaining_tiffins <= 0) {
                continue;
            }
            
            $status = $order->get_status();
            switch ($status) {
                case 'processing':
                    $all_processing++;
                    break;
                case 'completed':
                    $all_completed++;
                    break;
                case 'cancelled':
                case 'refunded':
                    $all_cancelled++;
                    break;
            }
        }
        
        return array(
            'total_orders' => $total_orders, // Only processing orders for this date
            'total_boxes' => $total_boxes,   // Only boxes for this date
            'processing_orders' => $all_processing, // All processing orders (informational)
            'completed_orders' => $all_completed,   // All completed orders (informational)
            'cancelled_orders' => $all_cancelled,   // All cancelled orders (informational)
            'dispatch_date' => $date,
            'is_operational' => $operational,
            'items_breakdown' => $items_breakdown // Only items for processing orders on this date
        );
    }

    /**
     * Get orders for dispatch using same logic as next day orders
     */
    private function get_orders_for_dispatch($date) {
        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array('wc-processing'), // Only processing orders
            'posts_per_page' => -1,
        );

        $orders = wc_get_orders($args);
        $filtered_orders = array();

        foreach ($orders as $order) {
            // Check if order should be displayed for this date
            if (Satguru_Tiffin_Calculator::should_display_order($order, $date)) {
                $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
                // Only include orders with remaining tiffins and that have boxes for this date
                $boxes_for_date = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $date);
                
                if ($remaining_tiffins > 0 && $boxes_for_date > 0) {
                    $filtered_orders[] = $order;
                }
            }
        }

        return $filtered_orders;
    }

    /**
     * AJAX handler for getting dispatch count
     */
    public function ajax_get_dispatch_count() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dispatch_counter_nonce')) {
            wp_die('Security check failed');
        }

        $operational = isset($_POST['operational']) ? sanitize_text_field($_POST['operational']) : '0';
        $force_refresh = isset($_POST['force_refresh']) ? sanitize_text_field($_POST['force_refresh']) : '0';
        $timestamp = isset($_POST['timestamp']) ? sanitize_text_field($_POST['timestamp']) : time();
        
        if ($operational === '1') {
            $dispatch_date = Satguru_Tiffin_Calculator::get_next_operational_day();
        } else {
            $dispatch_date = date('Y-m-d', strtotime('+1 day'));
        }

        // Add cache busting for forced refreshes
        if ($force_refresh === '1') {
            // Disable any caching mechanisms
            nocache_headers();
            
            // Add a small delay to ensure fresh data
            usleep(100000); // 0.1 second delay
        }

        $count_data = $this->get_dispatch_count($dispatch_date, $operational === '1');
        
        // Add timestamp to response for debugging
        $count_data['request_timestamp'] = $timestamp;
        $count_data['response_timestamp'] = time();
        $count_data['force_refresh'] = $force_refresh === '1';
        
        wp_send_json_success($count_data);
    }

    /**
     * Display dispatch counter page
     */
    public function dispatch_counter_page() {
        $show_operational = isset($_GET['operational']) ? $_GET['operational'] : '0';
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        if ($show_operational === '1') {
            $dispatch_date = Satguru_Tiffin_Calculator::get_next_operational_day();
        } else {
            $dispatch_date = $tomorrow;
        }

        $count_data = $this->get_dispatch_count($dispatch_date, $show_operational === '1');
        ?>
        <div class="wrap">
            <h1>Tomorrow's Dispatch Counter</h1>
            
            <div class="dispatch-toggle-container">
                <label class="dispatch-switch">
                    <input type="checkbox" 
                           id="operational-toggle"
                           <?php checked($show_operational, '1'); ?>
                           onchange="toggleOperationalDay(this.checked)">
                    <span class="dispatch-slider round"></span>
                </label>
                <span class="dispatch-toggle-label">Show Next Operational Day Orders</span>
                <p class="dispatch-toggle-description" id="dispatch-date-description">
                    <?php echo $show_operational === '1' ? 
                        'Showing dispatch count for next operational day (' . date('l, F j, Y', strtotime($dispatch_date)) . ')' : 
                        'Showing dispatch count for tomorrow (' . date('l, F j, Y', strtotime($tomorrow)) . ')'; ?>
                </p>
            </div>

            <div class="dispatch-counter-grid" id="dispatch-counter-grid">
                <div class="dispatch-card total-orders">
                    <div class="dispatch-card-header">
                        <h3>Orders for Dispatch</h3>
                        <span class="dispatch-icon">??</span>
                    </div>
                    <div class="dispatch-count" id="total-orders-count">
                        <?php echo $count_data['total_orders']; ?>
                    </div>
                    <div class="dispatch-subtitle">Processing orders for this date</div>
                </div>

                <div class="dispatch-card total-boxes">
                    <div class="dispatch-card-header">
                        <h3>Boxes to Prepare</h3>
                        <span class="dispatch-icon">??</span>
                    </div>
                    <div class="dispatch-count" id="total-boxes-count">
                        <?php echo $count_data['total_boxes']; ?>
                    </div>
                    <div class="dispatch-subtitle">Total boxes for this date</div>
                </div>

                <div class="dispatch-card processing-orders">
                    <div class="dispatch-card-header">
                        <h3>All Processing</h3>
                        <span class="dispatch-icon">?</span>
                    </div>
                    <div class="dispatch-count" id="processing-orders-count">
                        <?php echo $count_data['processing_orders']; ?>
                    </div>
                    <div class="dispatch-subtitle">Total processing orders</div>
                </div>

                <div class="dispatch-card completed-orders">
                    <div class="dispatch-card-header">
                        <h3>All Completed</h3>
                        <span class="dispatch-icon">?</span>
                    </div>
                    <div class="dispatch-count" id="completed-orders-count">
                        <?php echo $count_data['completed_orders']; ?>
                    </div>
                    <div class="dispatch-subtitle">Total completed orders</div>
                </div>
            </div>

            <div class="dispatch-actions">
                <button type="button" id="refresh-counter" class="button button-primary">
                    <span class="dashicons dashicons-update"></span> Refresh Count
                </button>
                <a href="<?php echo admin_url('admin.php?page=next-day-orders' . ($show_operational === '1' ? '&operational=1' : '')); ?>" 
                   class="button button-secondary">
                    View Detailed Orders
                </a>
            </div>

            <div class="dispatch-info">
                <h3>Dispatch Information</h3>
                <div class="dispatch-info-grid">
                    <div class="info-item">
                        <strong>Dispatch Date:</strong>
                        <span id="dispatch-date-info"><?php echo date('l, F j, Y', strtotime($dispatch_date)); ?></span>
                    </div>
                    <div class="info-item">
                        <strong>Last Updated:</strong>
                        <span id="last-updated-info"><?php echo current_time('F j, Y g:i a'); ?></span>
                        <small id="time-since-update" style="display: block; color: #666; margin-top: 2px;"></small>
                    </div>
                    <div class="info-item">
                        <strong>Auto Refresh:</strong>
                        <span id="auto-refresh-status">Starting in 3s...</span>
                        <button type="button" id="toggle-auto-refresh" class="button button-small">Pause</button>
                    </div>
                </div>
            </div>

            <div class="items-breakdown-section">
                <div class="items-breakdown-header">
                    <h3>Items Breakdown for Dispatch</h3>
                </div>
                <div class="items-breakdown-content" id="items-breakdown-content">
                    <?php echo Satguru_Meal_Plan_Compositions::display_items_breakdown($count_data['items_breakdown'], 'Items Required for ' . date('F j, Y', strtotime($dispatch_date))); ?>
                </div>
            </div>

            <!-- Loading indicator -->
            <div class="dispatch-loading" id="dispatch-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <span>Updating counts...</span>
            </div>
        </div>
        <?php
    }

    /**
     * Display meal composition tester page
     */
    public function meal_composition_tester_page() {
        ?>
        <div class="wrap">
            <h1>Meal Composition Tester</h1>
            <p>Test custom meal parsing and view composition breakdowns.</p>
            
            <div class="test-section">
                <h2>Test Custom Meal Parsing</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('test_meal_composition', 'test_meal_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="custom_meal_input">Custom Meal String</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="custom_meal_input" 
                                       name="custom_meal_input" 
                                       value="<?php echo isset($_POST['custom_meal_input']) ? esc_attr($_POST['custom_meal_input']) : 'Custom Meal - 1 Veg (12oz) + - 1 Non-Veg (12oz) Curry - 10 Rotis + 1 Rice'; ?>" 
                                       class="regular-text" 
                                       style="width: 500px;">
                                <p class="description">
                                                                        Supported formats (flexible with spaces, dashes, and separators):<br>
                                    <strong>Custom Meals:</strong><br>
                                    • Custom Meal - 3 Veg(8oz) Curries + 6 Rotis + 2 Rice<br>
                                    • Custom Meal - 1 Veg (12oz) + - 1 Non-Veg (12oz) Curry - 10 Rotis + 1 Rice<br>
                                    <strong>Pre-defined Plans:</strong><br>
                                    • 5 Item Veg Thali Meal (Large)<br>
                                    • Student Plan - 5 Item (Veg)<br>
                                    • Premium Quality Meal (Veg)<br>
                                    <strong>Note:</strong> Parser handles extra dashes, spaces, and "Curry/Curries" words automatically.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="test_parsing" class="button-primary" value="Test Parsing">
                        <input type="submit" name="debug_parsing" class="button-secondary" value="Debug Steps" style="margin-left: 10px;">
                    </p>
                </form>
                
                <?php
                if (isset($_POST['test_parsing']) && wp_verify_nonce($_POST['test_meal_nonce'], 'test_meal_composition')) {
                    $test_meal = sanitize_text_field($_POST['custom_meal_input']);
                    $composition = Satguru_Meal_Plan_Compositions::get_meal_composition($test_meal);
                    
                    echo '<div class="parsing-results">';
                    echo '<h3>Parsing Results</h3>';
                    echo '<div style="background: white; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">';
                    echo '<strong>Input:</strong> ' . esc_html($test_meal) . '<br><br>';
                    echo '<strong>Parsed Composition:</strong><br>';
                    
                    if (!empty($composition)) {
                        echo '<ul style="margin: 10px 0 0 20px;">';
                        foreach ($composition as $item => $quantity) {
                            echo '<li><strong>' . esc_html($quantity) . '</strong> ' . esc_html($item) . '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<em style="color: #d63638;">No composition found - parsing failed</em>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                
                if (isset($_POST['debug_parsing']) && wp_verify_nonce($_POST['test_meal_nonce'], 'test_meal_composition')) {
                    $test_meal = sanitize_text_field($_POST['custom_meal_input']);
                    echo '<div class="debug-results">';
                    echo '<h3>Debug Parsing Steps</h3>';
                    satguru_debug_custom_meal_parsing($test_meal);
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="predefined-examples">
                <h2>Pre-defined Examples</h2>
                <?php satguru_test_custom_meal_parsing(); ?>
            </div>
            
            <div class="all-compositions">
                <h2>All Defined Meal Compositions</h2>
                <?php
                $all_compositions = Satguru_Meal_Plan_Compositions::get_all_compositions();
                if (!empty($all_compositions)) {
                    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 15px;">';
                    foreach ($all_compositions as $meal_name => $composition) {
                        echo '<div style="background: white; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">';
                        echo '<h4 style="margin-top: 0; color: #0073aa;">' . esc_html($meal_name) . '</h4>';
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($composition as $item => $quantity) {
                            echo '<li>' . esc_html($quantity . ' ' . $item) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <style>
        .test-section, .predefined-examples, .all-compositions {
            margin: 30px 0;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .parsing-results {
            margin: 20px 0;
        }
        </style>
        <?php
    }
}

// Initialize the dispatch counter
new Satguru_Order_Dispatch_Counter();

/**
 * Function to get dispatch count data (for use in other parts of the system)
 */
function satguru_get_dispatch_count($date = null, $operational = false) {
    if ($date === null) {
        $date = $operational ? 
            Satguru_Tiffin_Calculator::get_next_operational_day() : 
            date('Y-m-d', strtotime('+1 day'));
    }
    
    $counter = new Satguru_Order_Dispatch_Counter();
    return $counter->get_dispatch_count($date, $operational);
}

/**
 * Widget function to display dispatch count in dashboard
 */
function satguru_dispatch_count_widget() {
    $count_data = satguru_get_dispatch_count();
    ?>
    <div class="dispatch-widget">
        <h4>Tomorrow's Dispatch</h4>
        <div class="dispatch-widget-content">
            <div class="dispatch-widget-item">
                <span class="count"><?php echo $count_data['total_orders']; ?></span>
                <span class="label">Orders</span>
            </div>
            <div class="dispatch-widget-item">
                <span class="count"><?php echo $count_data['total_boxes']; ?></span>
                <span class="label">Boxes</span>
            </div>
        </div>
        <a href="<?php echo admin_url('admin.php?page=dispatch-counter'); ?>" class="button button-small">
            View Details
        </a>
    </div>
    <?php
} 