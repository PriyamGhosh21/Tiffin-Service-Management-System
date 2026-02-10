<?php
if (!defined('ABSPATH')) {
    exit;
}
///////////////////////////////////////
// auto Resume Funtion starts here // 
//////////////////////////////////////

// Update cron schedule to run hourly instead of daily
function add_pause_management_cron_schedule() {
    if (!wp_next_scheduled('check_paused_orders_hourly')) {
        wp_schedule_event(time(), 'hourly', 'check_paused_orders_hourly');
    }
}
add_action('wp', 'add_pause_management_cron_schedule');

// Hook for cron job
add_action('check_paused_orders_daily', 'check_paused_orders');

//////////////////////////
// FILTER TABLE FUNTION //
/////////////////////////

// Include WP_List_Table if not already included
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Pause_Tiffin_List_Table
 */
class Pause_Tiffin_List_Table extends WP_List_Table {

    private $orders_data = array();

    public function __construct() {
        parent::__construct(array(
            'singular' => __('Order', 'pause-management'),
            'plural'   => __('Orders', 'pause-management'),
            'ajax'     => false,
        ));
    }

    /**
     * Prepare the items for the table to process
     */
    public function prepare_items() {
        // Get search term if exists
        $search_query = isset($_REQUEST['pause_search']) ? sanitize_text_field($_REQUEST['pause_search']) : '';
        
        // Capture the requested per page value (or use 10 if not provided)
        $per_page = !empty($_REQUEST['orders_per_page']) ? (int) $_REQUEST['orders_per_page'] : 10;
    
        // Prevent nonsensical values
        if ($per_page < 1) {
            $per_page = 10;
        }
    
        $current_page = $this->get_pagenum();
    
        // Handle sorting
    $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_text_field($_REQUEST['orderby']) : 'ID';
    $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

    // Get all orders first
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array('wc-processing'), // Only show Processing orders
        'posts_per_page' => -1, // Get all orders for filtering
    );

    $all_orders = wc_get_orders($args);
    $filtered_orders = array();

    // Filter orders based on search
    foreach ($all_orders as $order) {
        $include_order = true;

        if (!empty($search_query)) {
            $search_term = strtolower($search_query);
            $searchable_data = array(
                $order->get_id(), // Order ID
                strtolower($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()), // Customer name
                strtolower($order->get_billing_phone()), // Phone
                strtolower($order->get_billing_email()), // Email
            );

            $found = false;
            foreach ($searchable_data as $data) {
                if (strpos(strval($data), $search_term) !== false) {
                    $found = true;
                    break;
                }
            }

            $include_order = $found;
        }

        if ($include_order) {
            $filtered_orders[] = $order;
        }
    }


    // Handle pagination
    $total_items = count($filtered_orders);
    $offset = ($current_page - 1) * $per_page;
    
    // Slice the array for pagination
    $paged_orders = array_slice($filtered_orders, $offset, $per_page);

    // Set pagination args
    $this->set_pagination_args(array(
        'total_items' => $total_items,
        'per_page'    => $per_page,
    ));

    // Process the filtered and paged orders
    $this->orders_data = $this->get_orders_data($paged_orders);
    $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
    $this->items = $this->orders_data;
}

    /**
     * Get columns
     */
    public function get_columns() {
        $columns = array(
            'order_id'        => __('Order ID', 'pause-management'),
            'customer'        => __('Customer', 'pause-management'),
            'start_date'      => __('Start Date', 'pause-management'),
            'preferred_days'  => __('Preferred Days', 'pause-management'),
            'remaining_tiffins' => __('Remaining Tiffins', 'pause-management'),
            'status'          => __('Status', 'pause-management'),
            'action'          => __('Action', 'pause-management'),
        );
        return $columns;
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'order_id'        => array('ID', true),
            'customer'        => array('customer', false),
            'start_date'      => array('start_date', false),
            'remaining_tiffins' => array('remaining_tiffins', false),
            'status'          => array('status', false),
        );
        return $sortable_columns;
    }

    /**
     * Default column renderer
     */
    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? $item[$column_name] : '';
    }

    /**
     * Column Action
     */
    public function column_action($item) {
        return $item['action'];
    }

    /**
     * Fetch orders data
     */
    private function get_orders_data($orders) {
        $data = array();
        foreach ($orders as $order) {
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            if ($remaining_tiffins <= 0) continue;

            $start_date     = '';
            $preferred_days = '';

            foreach ($order->get_items() as $item) {
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, 'Start Date') !== false ||
                        strpos($meta->key, 'Delivery Date') !== false) {
                        $start_date = date('Y-m-d', strtotime($meta->value));
                    }
                    if (strpos($meta->key, 'Prefered Days') !== false) {
                        $preferred_days = $meta->value;
                    }
                }
            }

            // Validate preferred days before displaying the button
            $valid_days = validate_preferred_days($preferred_days);
            if (empty($valid_days)) {
            $action_button = '<span class="error">Invalid delivery days</span>';
            } else {
            $action_button = sprintf(
            '<button type="button" class="button pause-tiffin-btn"
            data-order-id="%d"
            data-start-date="%s"
            data-preferred-days="%s"
            data-remaining="%d">
            Pause Tiffin
            </button>',
            $order->get_id(),
            esc_attr($start_date),
            esc_attr($preferred_days),
            $remaining_tiffins
            );
}

            $data[] = array(
                'order_id'         => $order->get_id(),
                'customer'         => $order->get_formatted_billing_full_name(),
                'start_date'       => $start_date,
                'preferred_days'   => $preferred_days,
                'remaining_tiffins'=> $remaining_tiffins,
                'status'           => wc_get_order_status_name($order->get_status()),
                'action'           => $action_button,
            );
        }
        return $data;
    }
}


///////////////////////////////
// FILTER TABLE FUNTION ENDS //
//////////////////////////////


// Function to check and resume paused orders   ////////////////////////
function check_paused_orders() {
    $today = current_time('Y-m-d'); // Use WordPress system time instead of PHP date
    
    // Cache the previous check date to prevent multiple runs on same date
    $last_check_date = get_option('last_pause_check_date', '');
    
    // Only proceed if we haven't checked on this date yet
    if ($last_check_date !== $today) {
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-paused',
            'posts_per_page' => -1,
        );

        $orders = wc_get_orders($args);
        $resumed_count = 0;
    
    // Get cutoff time settings
    $cutoff_hour = (int)get_option('satguru_cutoff_hour', '13'); // 1 PM default
    $cutoff_minute = (int)get_option('satguru_cutoff_minute', '00');
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // Check if we're past cutoff time
    $past_cutoff = ($current_hour > $cutoff_hour || 
                    ($current_hour == $cutoff_hour && $current_minute >= $cutoff_minute));

    foreach ($orders as $order) {
        $paused_dates = get_post_meta($order->get_id(), '_paused_dates', true);
        
        if (!empty($paused_dates)) {
            // Sort dates to get the last pause date
            sort($paused_dates);
            $last_pause_date = end($paused_dates);
            
            // If last pause date has passed
            if (strtotime($last_pause_date) < strtotime($today)) {
                // Remove paused dates
                delete_post_meta($order->get_id(), '_paused_dates');
                
                // Update order status back to processing
                $order->update_status('processing');
                
                // Force recalculation of tiffin count
                $count_history = $order->get_meta('tiffin_count_history', true);
                if (!is_array($count_history)) {
                    $count_history = array();
                }
                
                // Calculate remaining tiffins for today
                $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
                
                // If we're past cutoff time and it's a delivery day, deduct today's tiffins
                if ($past_cutoff && Satguru_Tiffin_Calculator::should_display_order($order, $today)) {
                    $boxes_delivered = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $today);
                    $remaining_tiffins = max(0, $remaining_tiffins - $boxes_delivered);
                }
                
                // Update tiffin count history
                $count_history[$today] = array(
                    'remaining_tiffins' => $remaining_tiffins,
                    'delivery_days' => Satguru_Tiffin_Calculator::get_delivery_days(),
                    'boxes_delivered' => $boxes_delivered ?? 0
                );
                
                $order->update_meta_data('tiffin_count_history', $count_history);
                $order->save();
                
                $resumed_count++;
            }
            update_option('last_pause_check_date', $today);
        }
    }
        
}
    // Add admin notice if orders were resumed
    if ($resumed_count > 0) {
        add_action('admin_notices', function() use ($resumed_count) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php printf(__('%d orders have been automatically resumed and tiffin counts updated.', 'woocommerce'), $resumed_count); ?></p>
            </div>
            <?php
        });
    }
}
add_action('check_paused_orders_hourly', 'check_paused_orders');

// Add hourly check for cutoff time
function schedule_cutoff_check() {
    if (!wp_next_scheduled('check_tiffin_cutoff')) {
        wp_schedule_event(time(), 'hourly', 'check_tiffin_cutoff');
    }
}
add_action('wp', 'schedule_cutoff_check');

// Handle cutoff time check
function handle_tiffin_cutoff() {
    $cutoff_hour = (int)get_option('satguru_cutoff_hour', '13');
    $cutoff_minute = (int)get_option('satguru_cutoff_minute', '00');
    $current_hour = (int)date('H');
    $current_minute = (int)date('i');
    
    // If we're at or just past cutoff time
    if ($current_hour === $cutoff_hour && $current_minute >= $cutoff_minute && $current_minute < ($cutoff_minute + 5)) {
        // Trigger tiffin count save for all orders
        Satguru_Tiffin_Calculator::save_daily_tiffin_count();
    }
}
add_action('check_tiffin_cutoff', 'handle_tiffin_cutoff');


function remove_pause_management_cron() {
    wp_clear_scheduled_hook('check_paused_orders_hourly');
}

register_deactivation_hook(__FILE__, 'remove_pause_management_cron');

// Handle manual check from button click
function handle_manual_check() {
    if (isset($_GET['check_paused_orders']) && current_user_can('manage_options')) {
        check_paused_orders();
        wp_redirect(remove_query_arg('check_paused_orders'));
        exit;
    }
}
add_action('admin_init', 'handle_manual_check');


////////////////////////////////////
// auto Resume Funtion ends here // 
//////////////////////////////////

// Add this function to your pause-management.php
function enqueue_pause_management_assets() {
    // Only load on our plugin pages
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['toplevel_page_pause-management', 'pause-management_page_paused-tiffins', 'pause-management_page_scheduled-pauses'])) {
        return;
    }

    // Enqueue jQuery UI and its dependencies
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-datepicker');
    
    // Enqueue jQuery UI CSS
    wp_enqueue_style(
        'jquery-ui-style',
        '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css'
    );

    // Enqueue MultiDatesPicker
    wp_enqueue_script(
        'multidatespicker',
        get_stylesheet_directory_uri() . '/js/jquery-ui.multidatespicker.js',
        array('jquery-ui-datepicker'),
        '1.6.6',
        true
    );

    // Enqueue our custom JS
    wp_enqueue_script(
        'pause-management-js',
        get_stylesheet_directory_uri() . '/js/pause-management.js',
        array('jquery', 'multidatespicker'),
        '1.0',
        true
    );

    // Add ajaxurl to our script
    wp_localize_script('pause-management-js', 'pauseManagement', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pause_management_nonce')
    ));

    // Enqueue our custom CSS
    wp_enqueue_style(
        'pause-management-css',
        get_stylesheet_directory_uri() . '/css/pause-management.css',
        array(),
        '1.0'
    );
}
add_action('admin_enqueue_scripts', 'enqueue_pause_management_assets');

// Add menu pages
function add_pause_management_pages() {
    add_menu_page(
        'Pause Management',
        'Pause Management',
        'manage_options',
        'pause-management',
        'display_pause_management_page',
        'dashicons-controls-pause',
        30
    );

    add_submenu_page(
        'pause-management',
        'Pause Tiffin Management',
        'Pause Tiffin Management',
        'manage_options',
        'pause-management',
        'display_pause_management_page'
    );

    add_submenu_page(
        'pause-management',
        'Paused Tiffins',
        'Paused Tiffins',
        'manage_options',
        'paused-tiffins',
        'display_paused_tiffins_page'
    );
    
    // Add new submenu for scheduled pauses
    add_submenu_page(
        'pause-management',
        'Scheduled Pauses',
        'Scheduled Pauses',
        'manage_options',
        'scheduled-pauses',
        'display_scheduled_pauses_page'
    );
}
add_action('admin_menu', 'add_pause_management_pages');

///////////////////////////////
// New Prefered days validation//
//////////////////////////////



/**
 * Validate and convert preferred days string to array
 */
function validate_preferred_days($preferred_days_str) {
    $days_map = array(
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6
    );
    
    // Convert to lowercase and split by both hyphen and spaces
    $days_array = array_filter(
        preg_split('/\s*-\s*|\s+/', 
        strtolower(trim($preferred_days_str))
    ));
    
    $result = array();
    
    foreach ($days_array as $day) {
        if (isset($days_map[$day])) {
            $result[] = $days_map[$day];
        }
    }
    
    // Remove duplicates and sort
    $result = array_unique($result);
    sort($result);
    
    return $result;
}

// Display Pause Management Page

function display_pause_management_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Pause Tiffin Management', 'pause-management'); ?></h1>
        <?php
        // Instantiate the list table class
        $pause_tiffin_list_table = new Pause_Tiffin_List_Table();

        // Process bulk actions if any (not included in this example)
        $pause_tiffin_list_table->process_bulk_action();

        // Prepare the items
        $pause_tiffin_list_table->prepare_items();

        // Items per page options
        $per_page_options = array(10, 20, 50, 100);
        $current_per_page = isset($_REQUEST['orders_per_page']) ? (int) $_REQUEST['orders_per_page'] : 10;
        ?>

        <form method="get">
            <input type="hidden" name="page" value="pause-management" />

            <div class="tablenav top">
                <div class="alignleft actions">
                    <input type="search" 
                           id="pause-search" 
                           name="pause_search"
                           value="<?php echo isset($_REQUEST['pause_search']) ? esc_attr($_REQUEST['pause_search']) : ''; ?>"
                           placeholder="<?php esc_attr_e('Search by Order ID, Name, Email, Phone...', 'pause-management'); ?>"
                           style="padding: 3px; margin-right: 5px;">
                    <input type="submit" 
                           id="search-submit" 
                           class="button" 
                           value="<?php esc_attr_e('Search Orders', 'pause-management'); ?>">
                    <?php 
                    if (!empty($_REQUEST['pause_search'])) : 
                        $reset_url = remove_query_arg('pause_search');
                    ?>
                        <a href="<?php echo esc_url($reset_url); ?>" class="button">
                            <?php _e('Reset', 'pause-management'); ?>
                        </a>
                    <?php endif; ?>
                </div>

            <label for="orders_per_page"><?php _e('Show', 'pause-management'); ?></label>
            <select name="orders_per_page" id="orders_per_page">
                <?php foreach ($per_page_options as $option) : ?>
                    <option value="<?php echo $option; ?>" 
                        <?php selected($current_per_page, $option); ?>>
                        <?php echo $option; ?>
                    </option>
                <?php endforeach; ?>
                </select>
                <input type="submit" value="<?php _e('Apply', 'pause-management'); ?>" class="button" />

            <?php
            // Display the table
            $pause_tiffin_list_table = new Pause_Tiffin_List_Table();
            $pause_tiffin_list_table->prepare_items();
            $pause_tiffin_list_table->display();
            ?>
        </form>
    </div>


    <!-- Modal for Date Picker -->
    <div id="pause-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Select Pause Dates</h2>
            
            <!-- Pause Type Options -->
            <div class="pause-type-options">
                <label>
                    <input type="radio" name="pause_type" value="immediate" checked>
                    Immediate Pause
                    <div class="pause-type-description">
                        Order will be paused immediately after saving
                    </div>
                </label>
                <label>
                    <input type="radio" name="pause_type" value="scheduled">
                    Scheduled Pause
                    <div class="pause-type-description">
                        Order will be paused at 12:30 AM on the selected date(s)
                    </div>
                </label>
            </div>
            
            <div id="pause-datepicker"></div>
            <input type="hidden" id="selected-order-id">
            <button class="button button-primary" id="save-pause-dates">Save</button>
        </div>
    </div>
    <?php
}

// Display Paused Tiffins Page
function display_paused_tiffins_page() {
    ?>
    <div class="wrap">
        <h1>Paused Tiffins</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Paused Dates</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = array(
                    'post_type' => 'shop_order',
                    'post_status' => 'wc-paused',
                    'posts_per_page' => -1,
                );

                $orders = wc_get_orders($args);

                foreach ($orders as $order) {
                    $paused_dates = get_post_meta($order->get_id(), '_paused_dates', true);
                    ?>
                    <tr>
                        <td><?php echo $order->get_id(); ?></td>
                        <td><?php echo $order->get_formatted_billing_full_name(); ?></td>
                        <td><?php echo implode(', ', $paused_dates); ?></td>
                        <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                        <td>
                            <button class="button resume-tiffin-btn" 
                                    data-order-id="<?php echo $order->get_id(); ?>">
                                Resume Tiffin
                            </button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Register custom order status
function register_paused_order_status() {
    register_post_status('wc-paused', array(
        'label' => 'Paused',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Paused <span class="count">(%s)</span>',
            'Paused <span class="count">(%s)</span>')
    ));
}
add_action('init', 'register_paused_order_status');

// Add custom order status to WooCommerce
function add_paused_to_order_statuses($order_statuses) {
    $new_order_statuses = array();
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ($key === 'wc-processing') {
            $new_order_statuses['wc-paused'] = 'Paused';
        }
    }
    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'add_paused_to_order_statuses');

// Add a new scheduled event for checking orders that need to be paused
function add_scheduled_pause_cron() {
    if (!wp_next_scheduled('check_scheduled_pauses')) {
        // Schedule to run at 12:30 AM daily
        wp_schedule_event(strtotime('today 00:30:00'), 'daily', 'check_scheduled_pauses');
    }
}
add_action('wp', 'add_scheduled_pause_cron');

// Function to check and process scheduled pauses
function process_scheduled_pauses() {
    $today = current_time('Y-m-d');
    
    error_log("Running scheduled pause check for date: " . $today);
    
    // Get all processing orders
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array('wc-processing'),
        'posts_per_page' => -1,
    );
    
    $orders = wc_get_orders($args);
    $paused_count = 0;
    
    foreach ($orders as $order) {
        // Check if this order has scheduled pauses
        $scheduled_pauses = get_post_meta($order->get_id(), '_scheduled_pause_dates', true);
        
        if (!empty($scheduled_pauses) && is_array($scheduled_pauses)) {
            error_log("Order #" . $order->get_id() . " has scheduled pauses: " . print_r($scheduled_pauses, true));
            
            // Check if today is a scheduled pause date
            if (in_array($today, $scheduled_pauses)) {
                error_log("Today matches a scheduled pause date for order #" . $order->get_id());
                
                // Get existing paused dates or initialize empty array
                $paused_dates = get_post_meta($order->get_id(), '_paused_dates', true);
                if (!is_array($paused_dates)) {
                    $paused_dates = array();
                }
                
                // Add today to paused dates
                $paused_dates[] = $today;
                
                // Update paused dates
                update_post_meta($order->get_id(), '_paused_dates', $paused_dates);
                
                // Remove today from scheduled pauses
                $scheduled_pauses = array_diff($scheduled_pauses, array($today));
                update_post_meta($order->get_id(), '_scheduled_pause_dates', $scheduled_pauses);
                
                // Update order status to paused
                $order->update_status('paused');
                
                $paused_count++;
                error_log("Order #" . $order->get_id() . " has been paused for today");
            }
        }
    }
    
    // Log the result
    error_log("Scheduled pause process completed: $paused_count orders paused on $today");
}
add_action('check_scheduled_pauses', 'process_scheduled_pauses');

// Clean up scheduled hooks on plugin deactivation
function remove_scheduled_pause_cron() {
    wp_clear_scheduled_hook('check_scheduled_pauses');
}
register_deactivation_hook(__FILE__, 'remove_scheduled_pause_cron');

// Modify the AJAX handler to properly handle scheduled pauses
function save_pause_dates_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pause_management_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Get and validate data
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $dates = isset($_POST['dates']) ? (array) $_POST['dates'] : array();
    $pause_type = isset($_POST['pause_type']) ? sanitize_text_field($_POST['pause_type']) : 'immediate';
    
    if (empty($order_id) || empty($dates)) {
        wp_send_json_error('Missing required data');
    }
    
    // Get the order
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }
    
    // Process based on pause type
    if ($pause_type === 'immediate') {
        // Immediate pause - existing functionality
        update_post_meta($order_id, '_paused_dates', $dates);
        $order->update_status('paused');
        wp_send_json_success(array(
            'message' => 'Order has been paused immediately!',
            'type' => 'immediate'
        ));
    } else if ($pause_type === 'scheduled') {
        // Scheduled pause - store dates but don't change status
        update_post_meta($order_id, '_scheduled_pause_dates', $dates);
        
        // Do NOT update the order status or _paused_dates for scheduled pauses
        wp_send_json_success(array(
            'message' => 'Order pause has been scheduled successfully!',
            'type' => 'scheduled'
        ));
    } else {
        wp_send_json_error('Invalid pause type');
    }
}
add_action('wp_ajax_save_pause_dates', 'save_pause_dates_callback');
add_action('wp_ajax_nopriv_save_pause_dates', 'save_pause_dates_callback');

// Display Scheduled Pauses Page
function display_scheduled_pauses_page() {
    ?>
    <div class="wrap">
        <h1>Scheduled Pauses</h1>
        
        <a href="<?php echo add_query_arg('check_scheduled_pauses', '1'); ?>" class="button">
            Run Scheduled Pause Check Now
        </a>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Scheduled Pause Dates</th>
                    <th>Current Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = array(
                    'post_type' => 'shop_order',
                    'post_status' => 'wc-processing',
                    'posts_per_page' => -1,
                );

                $orders = wc_get_orders($args);
                $found_scheduled = false;

                foreach ($orders as $order) {
                    $scheduled_pauses = get_post_meta($order->get_id(), '_scheduled_pause_dates', true);
                    
                    if (!empty($scheduled_pauses) && is_array($scheduled_pauses)) {
                        $found_scheduled = true;
                        // Sort dates chronologically
                        sort($scheduled_pauses);
                        ?>
                        <tr>
                            <td><?php echo $order->get_id(); ?></td>
                            <td><?php echo $order->get_formatted_billing_full_name(); ?></td>
                            <td><?php echo implode(', ', $scheduled_pauses); ?></td>
                            <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                            <td>
                                <button class="button cancel-scheduled-pause-btn" 
                                        data-order-id="<?php echo $order->get_id(); ?>">
                                    Cancel Scheduled Pause
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                }
                
                if (!$found_scheduled) {
                    ?>
                    <tr>
                        <td colspan="5">No scheduled pauses found.</td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Add AJAX handler for canceling scheduled pauses
function cancel_scheduled_pause_callback() {
    error_log('cancel_scheduled_pause_callback called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pause_management_nonce')) {
        error_log('Nonce verification failed');
        wp_send_json_error('Security check failed');
    }
    
    // Get and validate order ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if (empty($order_id)) {
        error_log('Order ID is empty');
        wp_send_json_error('Missing order ID');
    }
    
    error_log('Attempting to delete scheduled pauses for order #' . $order_id);
    
    // Delete scheduled pauses
    $deleted = delete_post_meta($order_id, '_scheduled_pause_dates');
    
    if ($deleted) {
        error_log('Successfully deleted scheduled pauses for order #' . $order_id);
    } else {
        error_log('Failed to delete scheduled pauses for order #' . $order_id . ' (might not have existed)');
    }
    
    wp_send_json_success('Scheduled pauses canceled successfully');
}
add_action('wp_ajax_cancel_scheduled_pause', 'cancel_scheduled_pause_callback');
add_action('wp_ajax_nopriv_cancel_scheduled_pause', 'cancel_scheduled_pause_callback');

// Add AJAX handler for resuming tiffins
function resume_tiffin_callback() {
    error_log('resume_tiffin_callback called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pause_management_nonce')) {
        error_log('Nonce verification failed for resume tiffin');
        wp_send_json_error('Security check failed');
    }
    
    // Get and validate order ID
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if (empty($order_id)) {
        error_log('Order ID is empty for resume tiffin');
        wp_send_json_error('Missing order ID');
    }
    
    // Get the order
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('Order not found: #' . $order_id);
        wp_send_json_error('Order not found');
    }
    
    error_log('Attempting to resume order #' . $order_id);
    
    // Remove paused dates
    $deleted = delete_post_meta($order_id, '_paused_dates');
    
    // Update order status back to processing
    $order->update_status('processing');
    
    if ($deleted) {
        error_log('Successfully resumed order #' . $order_id);
    } else {
        error_log('Resumed order #' . $order_id . ' (no paused dates found)');
    }
    
    wp_send_json_success('Tiffin resumed successfully');
}
add_action('wp_ajax_resume_tiffin', 'resume_tiffin_callback');
add_action('wp_ajax_nopriv_resume_tiffin', 'resume_tiffin_callback');