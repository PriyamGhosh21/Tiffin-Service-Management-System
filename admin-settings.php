<?php
if (!defined('ABSPATH')) {
    exit;
}

class Satguru_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        // Add top level menu page
        add_menu_page(
            'Satguru Settings', // Page title
            'Settings',         // Menu title
            'manage_options',   // Capability
            'satguru-settings', // Menu slug
            array($this, 'settings_page_html'), // Callback function
            'dashicons-admin-generic', // Icon (gear icon)
            59 // Position after appearance menu
        );
        
        // Add sub menu pages
        add_submenu_page(
            'satguru-settings', // Parent slug
            'Admin Settings',   // Page title
            'Admin Settings',   // Menu title
            'manage_options',   // Capability
            'satguru-settings', // Menu slug (same as parent for first submenu)
            array($this, 'settings_page_html') // Callback function
        );
        
        add_submenu_page(
            'satguru-settings', // Parent slug
            'Logic Cutoff Time',   // Page title
            'Logic Cutoff Time',   // Menu title
            'manage_options',      // Capability
            'logic-cutoff-time',   // Menu slug
            array($this, 'cutoff_time_page_html') // Callback function
        );

         // Add new submenu for tiffin history management
         add_submenu_page(
            'satguru-settings',
            'Tiffin History',
            'Tiffin History',
            'manage_options',
            'tiffin-history',
            array($this, 'tiffin_history_page_html')
        );
        
        // Add submenu for Wati API / Renewal Reminders
        add_submenu_page(
            'satguru-settings',
            'Renewal Reminders',
            'Renewal Reminders',
            'manage_options',
            'renewal-reminders',
            array($this, 'renewal_reminders_page_html')
        );
        
        // Add submenu for OTP Login
        add_submenu_page(
            'satguru-settings',
            'OTP Login',
            'OTP Login',
            'manage_options',
            'otp-login-settings',
            array($this, 'otp_login_page_html')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('satguru_cutoff_settings', 'satguru_timezone');
        register_setting('satguru_cutoff_settings', 'satguru_cutoff_hour');
        register_setting('satguru_cutoff_settings', 'satguru_cutoff_minute');
        register_setting('satguru_cutoff_settings', 'satguru_delivery_start_day');
        register_setting('satguru_cutoff_settings', 'satguru_delivery_end_day');
        
        // Wati API settings for renewal reminders
        register_setting('satguru_wati_settings', 'renewal_reminder_enabled');
        register_setting('satguru_wati_settings', 'wati_api_endpoint');
        register_setting('satguru_wati_settings', 'wati_api_token');
        register_setting('satguru_wati_settings', 'wati_renewal_template_name');
        register_setting('satguru_wati_settings', 'renewal_reminder_tiffin_threshold');
        register_setting('satguru_wati_settings', 'renewal_reminder_exclude_trial_meals');
        register_setting('satguru_wati_settings', 'renewal_reminder_excluded_products');
        
        // OTP Login settings
        register_setting('satguru_otp_settings', 'satguru_otp_login_enabled');
        register_setting('satguru_otp_settings', 'satguru_otp_email_enabled');
        register_setting('satguru_otp_settings', 'satguru_otp_whatsapp_enabled');
        register_setting('satguru_otp_settings', 'satguru_otp_whatsapp_template');
        register_setting('satguru_otp_settings', 'satguru_otp_email_logo');
        register_setting('satguru_otp_settings', 'satguru_otp_email_color');
    }
    
    /**
     * Main settings page HTML
     */
    public function settings_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Configure various settings for the Satguru Tiffin Service.</p>
            
            <h2>Available Settings</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=logic-cutoff-time'); ?>">Logic Cutoff Time</a> - Configure timezone, cutoff time, and delivery days for tiffin calculations</li>
                <li><a href="<?php echo admin_url('admin.php?page=whatsapp-webhook-settings'); ?>">WhatsApp Webhook</a> - Configure API keys and webhook settings for WhatsApp integration</li>
                <li><a href="<?php echo admin_url('admin.php?page=renewal-reminders'); ?>">Renewal Reminders</a> - Configure automatic renewal reminders via WhatsApp</li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Cutoff time settings page HTML
     */
    public function cutoff_time_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current settings or set defaults
        $current_timezone = get_option('satguru_timezone', 'America/Toronto');
        $current_hour = get_option('satguru_cutoff_hour', '18');
        $current_minute = get_option('satguru_cutoff_minute', '00');
        $current_start_day = get_option('satguru_delivery_start_day', 'monday');
        $current_end_day = get_option('satguru_delivery_end_day', 'friday');
        
        // Get list of all timezones
        $timezones = DateTimeZone::listIdentifiers();
        
        // Days of the week
        $days = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            if (isset($_GET['settings-updated'])) {
                add_settings_error(
                    'satguru_messages',
                    'satguru_message',
                    'Settings Saved',
                    'updated'
                );
            }
            settings_errors('satguru_messages');
            ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('satguru_cutoff_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Timezone</th>
                        <td>
                            <select name="satguru_timezone" id="satguru_timezone">
                                <?php foreach ($timezones as $timezone) : ?>
                                    <option value="<?php echo esc_attr($timezone); ?>" 
                                            <?php selected($current_timezone, $timezone); ?>>
                                        <?php echo esc_html($timezone); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the timezone for delivery cutoff calculations</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Cutoff Time</th>
                        <td>
                            <select name="satguru_cutoff_hour" id="satguru_cutoff_hour">
                                <?php for ($i = 0; $i < 24; $i++) : ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"
                                            <?php selected($current_hour, sprintf('%02d', $i)); ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            :
                            <select name="satguru_cutoff_minute" id="satguru_cutoff_minute">
                                <?php for ($i = 0; $i < 60; $i += 5) : ?>
                                    <option value="<?php echo sprintf('%02d', $i); ?>"
                                            <?php selected($current_minute, sprintf('%02d', $i)); ?>>
                                        <?php echo sprintf('%02d', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">Set the cutoff time in 24-hour format</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Delivery Days</th>
                        <td>
                            <select name="satguru_delivery_start_day" id="satguru_delivery_start_day">
                                <?php foreach ($days as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"
                                            <?php selected($current_start_day, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            to
                            <select name="satguru_delivery_end_day" id="satguru_delivery_end_day">
                                <?php foreach ($days as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"
                                            <?php selected($current_end_day, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the delivery days range</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <div class="current-time">
                <h3>Current Time</h3>
                <p>Current time in selected timezone: 
                    <strong>
                        <?php
                        $tz = new DateTimeZone($current_timezone);
                        $date = new DateTime('now', $tz);
                        echo $date->format('Y-m-d H:i:s T');
                        ?>
                    </strong>
                </p>
            </div>
        </div>
        <?php
    }
    // Tiffin History // 
        /**
     * Tiffin History management page HTML
     */
    public function tiffin_history_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle form submissions
        if (isset($_POST['action']) && check_admin_referer('tiffin_history_action')) {
            switch ($_POST['action']) {
                case 'edit_count':
                    $order_id = intval($_POST['order_id']);
                    $date = sanitize_text_field($_POST['date']);
                    $new_count = intval($_POST['new_count']);
                    $this->update_tiffin_count($order_id, $date, $new_count);
                    break;
                case 'edit_boxes':
                    $order_id = intval($_POST['order_id']);
                    $date = sanitize_text_field($_POST['date']);
                    $new_boxes = intval($_POST['new_boxes']);
                    $this->update_boxes_delivered($order_id, $date, $new_boxes);
                    break;
                case 'delete_history':
                    $order_id = intval($_POST['order_id']);
                    $date = sanitize_text_field($_POST['date']);
                    $this->delete_tiffin_history($order_id, $date);
                    break;
                case 'delete_date_history':
                    $date = sanitize_text_field($_POST['delete_date']);
                    $this->delete_date_history($date);
                    break;
                case 'delete_all_history':
                    $this->delete_all_tiffin_history();
                break;
                case 'bulk_update':
                    $this->process_bulk_update();
                break;
                case 'set_completion_datetime':
                    $order_id = intval($_POST['order_id']);
                    $completion_date = sanitize_text_field($_POST['completion_date']);
                    $completion_time = sanitize_text_field($_POST['completion_time']);
                    $this->set_order_completion_datetime($order_id, $completion_date, $completion_time);
                break;
            }
        }

        // Get selected date filter
        $selected_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';

        // Pagination settings
        $orders_per_page = 20; // Number of orders per page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $orders_per_page;

        // Get all orders with tiffin history (for filtering)
        $all_args = array(
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'meta_key' => 'tiffin_count_history',
            'meta_compare' => 'EXISTS'
        );
        $all_orders = wc_get_orders($all_args);

        // Build filtered data array
        $filtered_data = array();
        foreach ($all_orders as $order) {
            $history = $order->get_meta('tiffin_count_history', true);
            if (!is_array($history)) continue;
            
            // Get total tiffins for this order
            $total_tiffins = $order->get_meta('Number Of Tiffins', true);
            if (empty($total_tiffins)) {
                $total_tiffins = 'N/A';
            }
            
            foreach ($history as $date => $data) {
                // If date filter is applied, only include matching dates
                if ($selected_date && $date !== $selected_date) {
                    continue;
                }
                
                // Extract order start date for display
                $order_start_date_display = '';
                foreach ($order->get_items() as $item) {
                    foreach ($item->get_meta_data() as $meta) {
                        if (strpos($meta->key, 'Start Date') !== false || strpos($meta->key, 'Delivery Date') !== false) {
                            $order_start_date_display = date('Y-m-d', strtotime($meta->value));
                            break 2;
                        }
                    }
                }

                $filtered_data[] = array(
                    'order' => $order,
                    'date' => $date,
                    'data' => $data,
                    'total_tiffins' => $total_tiffins,
                    'start_date' => $order_start_date_display
                );
            }
        }

        // Apply pagination to filtered data
        $total_count = count($filtered_data);
        $total_pages = ceil($total_count / $orders_per_page);
        $paginated_data = array_slice($filtered_data, $offset, $orders_per_page);

        // Get unique dates for filter dropdown
        $all_dates = array();
        foreach ($all_orders as $order) {
            $history = $order->get_meta('tiffin_count_history', true);
            if (is_array($history)) {
                $all_dates = array_merge($all_dates, array_keys($history));
            }
        }
        $all_dates = array_unique($all_dates);
        rsort($all_dates); // Sort dates in descending order (newest first)
        ?>
        <div class="wrap">
            <h1>Tiffin Count History Management</h1>
            
            <?php
            // Display any success or error messages
            settings_errors('tiffin_messages');
            ?>
            
            <!-- Date Filter Form -->
            <div class="date-filter-container">
                <form method="get" action="" class="date-filter-form">
                    <input type="hidden" name="page" value="tiffin-history">
                    <div class="filter-controls">
                        <label for="filter_date" class="filter-label">Filter by Date:</label>
                        <select name="filter_date" id="filter_date" class="filter-select">
                            <option value="">All Dates</option>
                            <?php foreach ($all_dates as $date) : ?>
                                <option value="<?php echo esc_attr($date); ?>" 
                                        <?php selected($selected_date, $date); ?>>
                                    <?php echo date('F j, Y', strtotime($date)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button filter-button">Filter</button>
                                                <?php if ($selected_date) : ?>
                            <a href="<?php echo admin_url('admin.php?page=tiffin-history'); ?>" class="button clear-filter">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Delete Date History Button (only show when date is selected) -->
                <?php if ($selected_date) : ?>
                    <form method="post" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                        <?php wp_nonce_field('tiffin_history_action'); ?>
                        <input type="hidden" name="action" value="delete_date_history">
                        <input type="hidden" name="delete_date" value="<?php echo esc_attr($selected_date); ?>">
                        <button type="submit" class="button button-danger delete-date-button" 
                                onclick="return confirm('WARNING: This will delete ALL tiffin history entries for <?php echo date('F j, Y', strtotime($selected_date)); ?> across ALL orders. This action cannot be undone. Are you sure you want to continue?')">
                            Ì†ΩÌ∑ëÔ∏è Delete All Entries for <?php echo date('F j, Y', strtotime($selected_date)); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Bulk Update Section -->
            <div class="bulk-update-container" style="background: #fff; border: 1px solid #e1e5e9; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; color: #2c3e50;">Ì†ΩÌ≥ä Bulk Update Tiffin History</h2>
                <p style="color: #6c757d; margin-bottom: 20px;">Update tiffin history for a specific date across multiple orders.</p>
                
                <form method="post" id="bulk-update-form">
                    <?php wp_nonce_field('tiffin_history_action'); ?>
                    <input type="hidden" name="action" value="bulk_update">
                    <input type="hidden" name="bulk_update_action" value="1">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bulk_update_date">Target Date</label>
                            </th>
                            <td>
                                <input type="date" 
                                       name="bulk_update_date" 
                                       id="bulk_update_date" 
                                       value="<?php echo esc_attr($selected_date); ?>"
                                       required
                                       style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 200px;">
                                <p class="description">Select the date to update (Y-m-d format)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bulk_update_order_ids">Order IDs (Optional)</label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="bulk_update_order_ids" 
                                       id="bulk_update_order_ids" 
                                       placeholder="e.g., 123, 456, 789"
                                       style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 300px;">
                                <p class="description">Leave empty to update all orders for the selected date. Separate multiple IDs with commas.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bulk_filter_start_date">Filter by Start Date (Optional)</label>
                            </th>
                            <td>
                                <input type="date" 
                                       name="bulk_filter_start_date" 
                                       id="bulk_filter_start_date"
                                       style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 200px;">
                                <p class="description">Only update orders that have this specific start date. Leave empty to include all orders.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="bulk_filter_start_date_before">Start Date on or before (Optional)</label>
                            </th>
                            <td>
                                <input type="date" 
                                       name="bulk_filter_start_date_before" 
                                       id="bulk_filter_start_date_before"
                                       style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 200px;">
                                <p class="description">Include only orders whose Start Date is on or before this date. Example: select 2025-10-28 to target orders starting on/before Oct 28.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bulk_skip_one_remaining">Filter Options</label>
                            </th>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" 
                                               name="bulk_skip_one_remaining" 
                                               id="bulk_skip_one_remaining" 
                                               value="1"
                                               style="width: 18px; height: 18px; cursor: pointer;">
                                        <span style="font-weight: 500;">Skip orders with 1 remaining tiffin</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" 
                                               name="bulk_skip_total_one" 
                                               id="bulk_skip_total_one" 
                                               value="1"
                                               style="width: 18px; height: 18px; cursor: pointer;">
                                        <span style="font-weight: 500;">Skip orders with total tiffin = 1</span>
                                    </label>
                                </div>
                                <p class="description">Checked filters will skip matching orders during the bulk update.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Remaining Tiffins</th>
                            <td>
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="radio" 
                                               name="remaining_tiffins_mode" 
                                               id="remaining_direct" 
                                               value="direct" 
                                               checked 
                                               onchange="toggleRemainingMode()">
                                        <label for="remaining_direct">Set Value:</label>
                                        <input type="number" 
                                               name="bulk_remaining_tiffins" 
                                               id="bulk_remaining_tiffins" 
                                               placeholder="0" 
                                               min="0"
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="radio" 
                                               name="remaining_tiffins_mode" 
                                               id="remaining_offset" 
                                               value="offset"
                                               onchange="toggleRemainingMode()">
                                        <label for="remaining_offset">Add/Subtract:</label>
                                        <input type="number" 
                                               name="bulk_remaining_offset" 
                                               id="bulk_remaining_offset" 
                                               placeholder="0" 
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100px;">
                                    </div>
                                </div>
                                <p class="description">Choose to set a specific value or add/subtract from the current value</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Boxes Delivered</th>
                            <td>
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="radio" 
                                               name="boxes_delivered_mode" 
                                               id="boxes_direct" 
                                               value="direct" 
                                               checked
                                               onchange="toggleBoxesMode()">
                                        <label for="boxes_direct">Set Value:</label>
                                        <input type="number" 
                                               name="bulk_boxes_delivered" 
                                               id="bulk_boxes_delivered" 
                                               placeholder="0" 
                                               min="0"
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100px;">
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="radio" 
                                               name="boxes_delivered_mode" 
                                               id="boxes_offset" 
                                               value="offset"
                                               onchange="toggleBoxesMode()">
                                        <label for="boxes_offset">Add/Subtract:</label>
                                        <input type="number" 
                                               name="bulk_boxes_offset" 
                                               id="bulk_boxes_offset" 
                                               placeholder="0"
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100px;">
                                    </div>
                                </div>
                                <p class="description">Choose to set a specific value or add/subtract from the current value</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" 
                                class="button button-primary" 
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 24px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Update History
                        </button>
                    </p>
                </form>
            </div>

            <?php if (empty($paginated_data)) : ?>
                <p>No tiffin history found<?php echo $selected_date ? ' for the selected date' : ''; ?>.</p>
                <?php else : ?>
            <!-- Pagination Info -->
            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_count), number_format_i18n($total_count)); ?>
                    </span>
                    <?php if ($total_pages > 1) : ?>
                        <span class="pagination-links">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '‚Äπ',
                                'next_text' => '‚Ä∫',
                                'total' => $total_pages,
                                'current' => $current_page,
                                'type' => 'array',
                                'add_args' => array(
                                    'filter_date' => $selected_date,
                                    'page' => 'tiffin-history'
                                )
                            ));
                            
                            if ($page_links) {
                                echo implode("\n", $page_links);
                            }
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Delete All button at the top -->
            <form method="post" style="margin-bottom: 20px;">
                <?php wp_nonce_field('tiffin_history_action'); ?>
                <input type="hidden" name="action" value="delete_all_history">
                <button type="submit" class="button button-danger" 
                        onclick="return confirm('WARNING: This will delete ALL tiffin history for ALL orders. This action cannot be undone. Are you sure you want to continue?')">
                    Delete All History
                </button>
            </form>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Start Date</th>
                            <th>Total Tiffin</th>
                            <th>Remaining Tiffins</th>
                            <th>Boxes Delivered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginated_data as $data) :
                            $order = $data['order'];
                            $date = $data['date'];
                            $history_data = $data['data'];
                            $total_tiffins = $data['total_tiffins'];
                            $order_start_date_display = isset($data['start_date']) ? $data['start_date'] : '';
                            ?>
                                <tr>
                                    <td>#<?php echo $order->get_id(); ?></td>
                                    <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                                    <td><?php echo $date; ?></td>
                                    <td>
                                        <?php echo $order_start_date_display ? esc_html($order_start_date_display) : '‚Äî'; ?>
                                    </td>
                                    <td>
                                        <span class="total-tiffin">
                                            <?php echo $total_tiffins; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="tiffin-count" data-order="<?php echo $order->get_id(); ?>" data-date="<?php echo $date; ?>">
                                            <?php echo $history_data['remaining_tiffins']; ?>
                                        </span>
                                        <button class="button edit-count" onclick="editCount(this)">Edit</button>
                                    </td>
                                    <td>
                                        <span class="boxes-delivered" data-order="<?php echo $order->get_id(); ?>" data-date="<?php echo $date; ?>">
                                            <?php echo $history_data['boxes_delivered']; ?>
                                        </span>
                                        <button class="button edit-boxes" onclick="editBoxes(this)">Edit</button>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button class="button edit-completion" 
                                                    onclick="openCompletionModal(<?php echo $order->get_id(); ?>)"
                                                    style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);">
                                                Set Completion
                                            </button>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('tiffin_history_action'); ?>
                                                <input type="hidden" name="action" value="delete_history">
                                                <input type="hidden" name="order_id" value="<?php echo $order->get_id(); ?>">
                                                <input type="hidden" name="date" value="<?php echo $date; ?>">
                                                <button type="submit" class="button delete-history" onclick="return confirm('Are you sure you want to delete this history entry?')">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Bottom Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(_n('%s item', '%s items', $total_count), number_format_i18n($total_count)); ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '‚Äπ',
                                    'next_text' => '‚Ä∫',
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'type' => 'array',
                                    'add_args' => array(
                                        'filter_date' => $selected_date,
                                        'page' => 'tiffin-history'
                                    )
                                ));
                                
                                if ($page_links) {
                                    echo implode("\n", $page_links);
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Count Form Modal -->
                <div id="edit-count-modal" class="modal" style="display:none;">
                    <div class="modal-content">
                        <form method="post">
                            <?php wp_nonce_field('tiffin_history_action'); ?>
                            <input type="hidden" name="action" value="edit_count">
                            <input type="hidden" name="order_id" id="edit-order-id">
                            <input type="hidden" name="date" id="edit-date">
                            <label>New Count:
                                <input type="number" name="new_count" id="edit-count" min="0" required>
                            </label>
                            <button type="submit" class="button button-primary">Save</button>
                            <button type="button" class="button" onclick="closeModal()">Cancel</button>
                        </form>
                    </div>
                </div>

                <!-- Edit Boxes Delivered Form Modal -->
                <div id="edit-boxes-modal" class="modal" style="display:none;">
                    <div class="modal-content">
                        <form method="post">
                            <?php wp_nonce_field('tiffin_history_action'); ?>
                            <input type="hidden" name="action" value="edit_boxes">
                            <input type="hidden" name="order_id" id="edit-boxes-order-id">
                            <input type="hidden" name="date" id="edit-boxes-date">
                            <label>New Boxes Delivered:
                                <input type="number" name="new_boxes" id="edit-boxes-count" min="0" required>
                            </label>
                            <button type="submit" class="button button-primary">Save</button>
                            <button type="button" class="button" onclick="closeModal()">Cancel</button>
                        </form>
                    </div>
                </div>

                <!-- Set Completion Date/Time Modal -->
                <div id="completion-modal" class="modal" style="display:none;">
                    <div class="modal-content" style="width: 400px; max-width: 90vw;">
                        <h3 style="margin-top: 0; color: #2c3e50;">Set Order Completion Date/Time</h3>
                        <form method="post" id="completion-form">
                            <?php wp_nonce_field('tiffin_history_action'); ?>
                            <input type="hidden" name="action" value="set_completion_datetime">
                            <input type="hidden" name="order_id" id="completion-order-id">
                            
                            <table class="form-table" style="margin: 0;">
                                <tr>
                                    <th scope="row">
                                        <label for="completion_date">Completion Date</label>
                                    </th>
                                    <td>
                                        <input type="date" 
                                               name="completion_date" 
                                               id="completion_date" 
                                               required
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100%;">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="completion_time">Completion Time</label>
                                    </th>
                                    <td>
                                        <input type="time" 
                                               name="completion_time" 
                                               id="completion_time" 
                                               required
                                               style="padding: 8px 12px; border: 2px solid #e9ecef; border-radius: 6px; width: 100%;">
                                        <p class="description" style="margin-top: 5px; font-size: 12px; color: #6c757d;">24-hour format (HH:MM)</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit" style="margin-top: 20px; margin-bottom: 0;">
                                <button type="submit" class="button button-primary" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);">Set Completion</button>
                                <button type="button" class="button" onclick="closeCompletionModal()">Cancel</button>
                            </p>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.6);
                z-index: 999;
            }
            .modal-content {
                background: #fff;
                padding: 20px;
                width: 300px;
                margin: 100px auto;
                border-radius: 4px;
            }
            
            /* Modern Table Styling */
            .widefat {
                border-collapse: separate;
                border-spacing: 0;
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                overflow: hidden;
                margin-top: 20px;
            }
            
            .widefat thead th {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 0.5px;
                padding: 16px 12px;
                border: none;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            
            .widefat thead th:first-child {
                border-top-left-radius: 8px;
            }
            
            .widefat thead th:last-child {
                border-top-right-radius: 8px;
            }
            
            .widefat tbody tr {
                border-bottom: 1px solid #f0f0f1;
                transition: all 0.2s ease;
            }
            
            .widefat tbody tr:hover {
                background-color: #f8f9fa;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .widefat tbody tr:last-child {
                border-bottom: none;
            }
            
            .widefat tbody td {
                padding: 16px 12px;
                vertical-align: middle;
                border: none;
                font-size: 14px;
            }
            
            .widefat tbody td:first-child {
                font-weight: 600;
                color: #2271b1;
            }
            
            /* Modern Button Styling */
            .button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border: none;
                border-radius: 6px;
                padding: 8px 16px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                cursor: pointer;
                transition: all 0.3s ease;
                margin: 2px;
                text-decoration: none;
                display: inline-block;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .button:hover {
                background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                color: #fff;
            }
            
            .button.delete-history {
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            }
            
            .button.delete-history:hover {
                background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            }
            
            .button-danger {
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
                color: #fff;
                border: none;
                border-radius: 6px;
                padding: 12px 24px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                box-shadow: 0 3px 6px rgba(255,107,107,0.3);
            }
            
            .button-danger:hover {
                background: linear-gradient(135deg, #ff5252 0%, #d32f2f 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(255,107,107,0.4);
            }
            
            .delete-date-button {
                background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
                color: #fff;
                border: none;
                border-radius: 8px;
                padding: 14px 24px;
                font-weight: 600;
                font-size: 14px;
                text-transform: none;
                letter-spacing: 0.3px;
                box-shadow: 0 3px 6px rgba(255,152,0,0.3);
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            
            .delete-date-button:hover {
                background: linear-gradient(135deg, #f57c00 0%, #e65100 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(255,152,0,0.4);
                color: #fff;
            }
            
            .delete-date-button:active {
                transform: translateY(0);
                box-shadow: 0 2px 4px rgba(255,152,0,0.3);
            }
            
            /* Enhanced Data Display */
            .tiffin-count, .boxes-delivered {
                display: inline-block;
                background: #f8f9fa;
                border: 2px solid #e9ecef;
                border-radius: 20px;
                padding: 6px 12px;
                margin-right: 8px;
                font-weight: 600;
                color: #495057;
                min-width: 40px;
                text-align: center;
                transition: all 0.3s ease;
            }
            
            .tiffin-count:hover, .boxes-delivered:hover {
                background: #e9ecef;
                border-color: #dee2e6;
            }
            
            .total-tiffin {
                display: inline-block;
                background: #e3f2fd;
                border: 2px solid #2196f3;
                border-radius: 20px;
                padding: 6px 12px;
                font-weight: 700;
                color: #1565c0;
                min-width: 40px;
                text-align: center;
                font-size: 13px;
            }
            
            /* Page Header */
            .wrap h1 {
                color: #2c3e50;
                font-weight: 300;
                font-size: 28px;
                margin-bottom: 8px;
            }
            
            /* Status Badges */
            .status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .status-active {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .status-completed {
                background: #d4edda;
                color: #155724;
            }
            
            /* Tablenav styling */
            .tablenav {
                clear: both;
                margin: 6px 0 4px;
                height: 30px;
            }
            .tablenav-pages {
                float: right;
                display: block;
                cursor: default;
                height: 30px;
                color: #646970;
                font-size: 12px;
                line-height: 30px;
            }
            .pagination-links {
                white-space: nowrap;
            }
            .pagination-links a,
            .pagination-links span {
                display: inline-block;
                min-width: 17px;
                border: 1px solid #ddd;
                padding: 8px 12px;
                margin: 0 2px;
                border-radius: 6px;
                text-align: center;
                text-decoration: none;
                color: #667eea;
                background: #fff;
                font-weight: 500;
                transition: all 0.3s ease;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .pagination-links .current {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border-color: #667eea;
                box-shadow: 0 2px 4px rgba(102,126,234,0.3);
            }
            .pagination-links a:hover {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border-color: #667eea;
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(102,126,234,0.3);
            }
            .displaying-num {
                margin-right: 15px;
                font-style: italic;
                color: #6c757d;
                font-weight: 500;
            }
            
            /* Form styling */
            form input[type="number"] {
                border: 2px solid #e9ecef;
                border-radius: 6px;
                padding: 8px 12px;
                transition: border-color 0.3s ease;
            }
            
            form input[type="number"]:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            }

            /* Date Filter Styling */
            .date-filter-container {
                background: #fff;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .date-filter-form {
                margin: 0;
            }
            
            .filter-controls {
                display: flex;
                align-items: center;
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .filter-label {
                font-weight: 600;
                color: #2c3e50;
                margin: 0;
            }
            
            .filter-select {
                border: 2px solid #e9ecef;
                border-radius: 6px;
                padding: 10px 15px;
                font-size: 14px;
                background: #fff;
                color: #495057;
                min-width: 200px;
                transition: all 0.3s ease;
            }
            
            .filter-select:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            }
            
            .filter-button {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                border: none;
                color: #fff;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(40,167,69,0.3);
            }
            
            .filter-button:hover {
                background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(40,167,69,0.4);
                color: #fff;
            }
            
            .clear-filter {
                background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
                color: #fff;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(108,117,125,0.3);
            }
            
            .clear-filter:hover {
                background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(108,117,125,0.4);
                color: #fff;
                text-decoration: none;
            }
            
            @media (max-width: 768px) {
                .filter-controls {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .filter-select {
                    min-width: auto;
                    width: 100%;
                }
            }
        </style>

        <script>
            function editCount(button) {
                const row = button.closest('tr');
                const countSpan = row.querySelector('.tiffin-count');
                const orderId = countSpan.dataset.order;
                const date = countSpan.dataset.date;
                const currentCount = countSpan.textContent.trim();

                document.getElementById('edit-order-id').value = orderId;
                document.getElementById('edit-date').value = date;
                document.getElementById('edit-count').value = currentCount;
                document.getElementById('edit-count-modal').style.display = 'block';
            }

            function editBoxes(button) {
                const row = button.closest('tr');
                const boxesSpan = row.querySelector('.boxes-delivered');
                const orderId = boxesSpan.dataset.order;
                const date = boxesSpan.dataset.date;
                const currentBoxes = boxesSpan.textContent.trim();

                document.getElementById('edit-boxes-order-id').value = orderId;
                document.getElementById('edit-boxes-date').value = date;
                document.getElementById('edit-boxes-count').value = currentBoxes;
                document.getElementById('edit-boxes-modal').style.display = 'block';
            }

            function closeModal() {
                document.getElementById('edit-count-modal').style.display = 'none';
                document.getElementById('edit-boxes-modal').style.display = 'none';
            }

            // Set Completion Date/Time Modal Functions
            function openCompletionModal(orderId) {
                document.getElementById('completion-order-id').value = orderId;
                
                // Set default date to today and time to current time
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                document.getElementById('completion_date').value = `${year}-${month}-${day}`;
                document.getElementById('completion_time').value = `${hours}:${minutes}`;
                
                document.getElementById('completion-modal').style.display = 'block';
            }

            function closeCompletionModal() {
                document.getElementById('completion-modal').style.display = 'none';
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const completionModal = document.getElementById('completion-modal');
                if (event.target == completionModal) {
                    closeCompletionModal();
                }
                
                const editCountModal = document.getElementById('edit-count-modal');
                if (event.target == editCountModal) {
                    closeModal();
                }
                
                const editBoxesModal = document.getElementById('edit-boxes-modal');
                if (event.target == editBoxesModal) {
                    closeModal();
                }
            }

            // Toggle between direct value and offset mode for remaining tiffins
            function toggleRemainingMode() {
                const directMode = document.getElementById('remaining_direct').checked;
                const directInput = document.getElementById('bulk_remaining_tiffins');
                const offsetInput = document.getElementById('bulk_remaining_offset');
                
                if (directMode) {
                    directInput.disabled = false;
                    offsetInput.disabled = true;
                    offsetInput.value = '';
                } else {
                    directInput.disabled = true;
                    directInput.value = '';
                    offsetInput.disabled = false;
                }
            }

            // Toggle between direct value and offset mode for boxes delivered
            function toggleBoxesMode() {
                const directMode = document.getElementById('boxes_direct').checked;
                const directInput = document.getElementById('bulk_boxes_delivered');
                const offsetInput = document.getElementById('bulk_boxes_offset');
                
                if (directMode) {
                    directInput.disabled = false;
                    offsetInput.disabled = true;
                    offsetInput.value = '';
                } else {
                    directInput.disabled = true;
                    directInput.value = '';
                    offsetInput.disabled = false;
                }
            }

            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                toggleRemainingMode();
                toggleBoxesMode();
            });
        </script>
        <?php
    }

    /**
     * Update tiffin count for a specific date
     */
    private function update_tiffin_count($order_id, $date, $new_count) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $history = $order->get_meta('tiffin_count_history', true);
        if (!is_array($history) || !isset($history[$date])) return;

        $history[$date]['remaining_tiffins'] = $new_count;
        $order->update_meta_data('tiffin_count_history', $history);
        $order->save();

        add_settings_error(
            'tiffin_messages',
            'tiffin_updated',
            'Tiffin count updated successfully.',
            'updated'
        );
    }

    /**
     * Update boxes delivered for a specific date
     */
    private function update_boxes_delivered($order_id, $date, $new_boxes) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $history = $order->get_meta('tiffin_count_history', true);
        if (!is_array($history) || !isset($history[$date])) return;

        $history[$date]['boxes_delivered'] = $new_boxes;
        $order->update_meta_data('tiffin_count_history', $history);
        $order->save();

        add_settings_error(
            'tiffin_messages',
            'boxes_updated',
            'Boxes delivered count updated successfully.',
            'updated'
        );
    }

    /**
     * Delete tiffin history for a specific date
     */
    private function delete_tiffin_history($order_id, $date) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $history = $order->get_meta('tiffin_count_history', true);
        if (!is_array($history) || !isset($history[$date])) return;

        unset($history[$date]);
        $order->update_meta_data('tiffin_count_history', $history);
        $order->save();

        add_settings_error(
            'tiffin_messages',
            'tiffin_deleted',
            'Tiffin history entry deleted successfully.',
            'updated'
        );
    }

    /**
     * Delete all tiffin history for all orders
     */
    private function delete_all_tiffin_history() {
        $args = array(
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'meta_key' => 'tiffin_count_history',
            'meta_compare' => 'EXISTS'
        );
        
        $orders = wc_get_orders($args);
        
        foreach ($orders as $order) {
            $order->delete_meta_data('tiffin_count_history');
            $order->save();
        }

        add_settings_error(
            'tiffin_messages',
            'tiffin_all_deleted',
            'All tiffin history has been deleted successfully.',
            'updated'
        );
    }

    /**
     * Delete all tiffin history for a specific date across all orders
     */
    private function delete_date_history($date) {
        $args = array(
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'meta_key' => 'tiffin_count_history',
            'meta_compare' => 'EXISTS'
        );
        
        $orders = wc_get_orders($args);
        $deleted_count = 0;
        
        foreach ($orders as $order) {
            $history = $order->get_meta('tiffin_count_history', true);
            if (is_array($history) && isset($history[$date])) {
                unset($history[$date]);
                $order->update_meta_data('tiffin_count_history', $history);
                $order->save();
                $deleted_count++;
            }
        }

        add_settings_error(
            'tiffin_messages',
            'date_history_deleted',
            sprintf('Successfully deleted %d tiffin history entries for %s.', $deleted_count, date('F j, Y', strtotime($date))),
            'updated'
        );
    }

    /**
     * Bulk update tiffin history for a particular date
     * 
     * @param string $date Date in Y-m-d format
     * @param array $updates Array with keys:
     *   - 'remaining_tiffins' (optional) - Update remaining tiffins
     *   - 'boxes_delivered' (optional) - Update boxes delivered
     *   - 'remaining_tiffins_offset' (optional) - Add/subtract from remaining tiffins
     *   - 'boxes_delivered_offset' (optional) - Add/subtract from boxes delivered
     * @param array $order_ids (optional) - Specific order IDs to update, empty for all orders
     * @param string $start_date_filter (optional) - Filter by order start date in Y-m-d format (exact match)
     * @param bool $skip_one_remaining (optional) - Skip orders with exactly 1 remaining tiffin
     * @param bool $skip_total_one (optional) - Skip orders where total tiffins equals 1
     * @param string $start_date_before (optional) - Include only orders with start date on or before this date (Y-m-d)
     * @return array Result array with 'updated_count', 'skipped_count', and 'errors'
     */
    public static function bulk_update_tiffin_history($date, $updates = array(), $order_ids = array(), $start_date_filter = '', $skip_one_remaining = false, $skip_total_one = false, $start_date_before = '') {
        global $wpdb;
        
        // Validate date format
        if (!$date || !strtotime($date)) {
            return array(
                'success' => false,
                'message' => 'Invalid date format. Please use Y-m-d format.'
            );
        }
        
        // Validate that we have something to update
        if (empty($updates)) {
            return array(
                'success' => false,
                'message' => 'No updates specified. Please provide update values.'
            );
        }
        
        // Build query arguments
        $args = array(
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'meta_key' => 'tiffin_count_history',
            'meta_compare' => 'EXISTS'
        );
        
        // Filter by specific order IDs if provided
        if (!empty($order_ids) && is_array($order_ids)) {
            $args['include'] = array_map('intval', $order_ids);
        }
        
        // Get orders
        $orders = wc_get_orders($args);
        
        $result = array(
            'updated_count' => 0,
            'skipped_count' => 0,
            'errors' => array(),
            'success' => true
        );
        
        foreach ($orders as $order) {
            try {
                // Get existing history
                $history = $order->get_meta('tiffin_count_history', true);
                if (!is_array($history)) {
                    $history = array();
                }
                
                // Skip if the date doesn't exist in history
                if (!isset($history[$date])) {
                    $result['skipped_count']++;
                    continue;
                }
                
                // Retrieve order start date once (used by both exact match and before filters)
                $order_start_date = '';
                foreach ($order->get_items() as $item) {
                    foreach ($item->get_meta_data() as $meta) {
                        if (strpos($meta->key, 'Start Date') !== false || strpos($meta->key, 'Delivery Date') !== false) {
                            $order_start_date = date('Y-m-d', strtotime($meta->value));
                            break 2;
                        }
                    }
                }

                // Filter by exact start date if specified
                if (!empty($start_date_filter)) {
                    if ($order_start_date !== $start_date_filter) {
                        $result['skipped_count']++;
                        continue;
                    }
                }

                // Filter by start date on or before if specified
                if (!empty($start_date_before)) {
                    if (empty($order_start_date) || strtotime($order_start_date) > strtotime($start_date_before)) {
                        $result['skipped_count']++;
                        continue;
                    }
                }
                
                // Get current values
                $current_data = $history[$date];
                
                // Skip orders with exactly 1 remaining tiffin if filter is enabled
                if ($skip_one_remaining && isset($current_data['remaining_tiffins']) && intval($current_data['remaining_tiffins']) === 1) {
                    $result['skipped_count']++;
                    continue;
                }

                // Skip orders with total tiffins equal to 1 if filter is enabled
                if ($skip_total_one) {
                    $order_total_tiffins = intval($order->get_meta('Number Of Tiffins', true));
                    if ($order_total_tiffins === 1) {
                        $result['skipped_count']++;
                        continue;
                    }
                }
                
                // Update values based on provided options
                if (isset($updates['remaining_tiffins'])) {
                    // Direct assignment
                    $history[$date]['remaining_tiffins'] = intval($updates['remaining_tiffins']);
                } elseif (isset($updates['remaining_tiffins_offset'])) {
                    // Add/subtract offset
                    $current_count = isset($current_data['remaining_tiffins']) ? intval($current_data['remaining_tiffins']) : 0;
                    $history[$date]['remaining_tiffins'] = $current_count + intval($updates['remaining_tiffins_offset']);
                }
                
                if (isset($updates['boxes_delivered'])) {
                    // Direct assignment
                    $history[$date]['boxes_delivered'] = intval($updates['boxes_delivered']);
                } elseif (isset($updates['boxes_delivered_offset'])) {
                    // Add/subtract offset
                    $current_boxes = isset($current_data['boxes_delivered']) ? intval($current_data['boxes_delivered']) : 0;
                    $history[$date]['boxes_delivered'] = $current_boxes + intval($updates['boxes_delivered_offset']);
                }
                
                // Update timestamp
                $history[$date]['timestamp'] = current_time('timestamp');
                
                // Save updated history
                $order->update_meta_data('tiffin_count_history', $history);
                $order->save();
                
                $result['updated_count']++;
                
            } catch (Exception $e) {
                $result['errors'][] = sprintf('Error updating Order #%d: %s', $order->get_id(), $e->getMessage());
                $result['success'] = false;
            }
        }
        
        return $result;
    }

    /**
     * Wrapper function to bulk update tiffin history with admin feedback
     * This method handles form submissions and displays appropriate messages
     */
    public function process_bulk_update() {
        if (isset($_POST['bulk_update_action']) && check_admin_referer('tiffin_history_action')) {
            $date = sanitize_text_field($_POST['bulk_update_date']);
            $order_ids = isset($_POST['bulk_update_order_ids']) ? 
                array_map('intval', explode(',', sanitize_text_field($_POST['bulk_update_order_ids']))) : 
                array();
            
            // Get start date filter if provided
            $start_date_filter = isset($_POST['bulk_filter_start_date']) && !empty($_POST['bulk_filter_start_date']) 
                ? sanitize_text_field($_POST['bulk_filter_start_date']) 
                : '';
            // Get start date before filter if provided
            $start_date_before = isset($_POST['bulk_filter_start_date_before']) && !empty($_POST['bulk_filter_start_date_before']) 
                ? sanitize_text_field($_POST['bulk_filter_start_date_before']) 
                : '';
            
            // Get skip one remaining filter
            $skip_one_remaining = isset($_POST['bulk_skip_one_remaining']) && $_POST['bulk_skip_one_remaining'] === '1';
            // Get skip total one filter
            $skip_total_one = isset($_POST['bulk_skip_total_one']) && $_POST['bulk_skip_total_one'] === '1';
            
            $updates = array();
            
            // Process remaining tiffins update
            if (isset($_POST['bulk_remaining_tiffins']) && $_POST['bulk_remaining_tiffins'] !== '') {
                $updates['remaining_tiffins'] = intval($_POST['bulk_remaining_tiffins']);
            } elseif (isset($_POST['bulk_remaining_offset']) && $_POST['bulk_remaining_offset'] !== '') {
                $updates['remaining_tiffins_offset'] = intval($_POST['bulk_remaining_offset']);
            }
            
            // Process boxes delivered update
            if (isset($_POST['bulk_boxes_delivered']) && $_POST['bulk_boxes_delivered'] !== '') {
                $updates['boxes_delivered'] = intval($_POST['bulk_boxes_delivered']);
            } elseif (isset($_POST['bulk_boxes_offset']) && $_POST['bulk_boxes_offset'] !== '') {
                $updates['boxes_delivered_offset'] = intval($_POST['bulk_boxes_offset']);
            }
            
            // Perform the bulk update
            $result = self::bulk_update_tiffin_history($date, $updates, $order_ids, $start_date_filter, $skip_one_remaining, $skip_total_one, $start_date_before);
            
            // Display appropriate message
            if ($result['success']) {
                $message = sprintf(
                    'Bulk update completed. Updated %d order(s), skipped %d order(s).',
                    $result['updated_count'],
                    $result['skipped_count']
                );
                add_settings_error(
                    'tiffin_messages',
                    'bulk_update_success',
                    $message,
                    'updated'
                );
                
                // Show individual errors if any
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        add_settings_error(
                            'tiffin_messages',
                            'bulk_update_error',
                            $error,
                            'error'
                        );
                    }
                }
            } else {
                add_settings_error(
                    'tiffin_messages',
                    'bulk_update_failed',
                    $result['message'] ?? 'Bulk update failed. Please check your input and try again.',
                    'error'
                );
            }
        }
    }


    /**
     * Set manual completion date and time for an order
     * 
     * @param int $order_id Order ID
     * @param string $completion_date Date in Y-m-d format
     * @param string $completion_time Time in H:i format (24-hour)
     */
    private function set_order_completion_datetime($order_id, $completion_date, $completion_time) {
        $order = wc_get_order($order_id);
        if (!$order) {
            add_settings_error(
                'tiffin_messages',
                'order_not_found',
                'Order not found.',
                'error'
            );
            return;
        }

        // Validate date and time
        if (!$completion_date || !strtotime($completion_date)) {
            add_settings_error(
                'tiffin_messages',
                'invalid_date',
                'Invalid completion date format. Please use Y-m-d format.',
                'error'
            );
            return;
        }

        if (!$completion_time || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $completion_time)) {
            add_settings_error(
                'tiffin_messages',
                'invalid_time',
                'Invalid completion time format. Please use H:i format (24-hour).',
                'error'
            );
            return;
        }

        // Combine date and time
        $completion_datetime = $completion_date . ' ' . $completion_time . ':00';
        $completion_timestamp = strtotime($completion_datetime);

        if (!$completion_timestamp) {
            add_settings_error(
                'tiffin_messages',
                'invalid_datetime',
                'Invalid date/time combination.',
                'error'
            );
            return;
        }

        global $wpdb;
        
        // Store manual completion date/time in order meta
        $order->update_meta_data('_manual_completion_date', $completion_date);
        $order->update_meta_data('_manual_completion_time', $completion_time);
        $order->update_meta_data('_manual_completion_datetime', $completion_datetime);
        $order->update_meta_data('_manual_completion_timestamp', $completion_timestamp);

        // Get the completion datetime in WordPress format
        $completion_datetime_wp = date('Y-m-d H:i:s', $completion_timestamp);
        $completion_datetime_gmt = gmdate('Y-m-d H:i:s', $completion_timestamp);

        // Find the existing completion note in the database
        // WooCommerce stores order notes in wp_comments table
        $order_notes = wc_get_order_notes(array(
            'order_id' => $order_id,
            'type' => 'internal'
        ));

        $completion_note_id = null;
        foreach ($order_notes as $note) {
            // Look for the completion note - could be "Order automatically completed" or "Order status changed from Processing to Completed"
            if (strpos($note->content, 'Order automatically completed') !== false || 
                (strpos($note->content, 'Order status changed') !== false && strpos($note->content, 'to Completed') !== false)) {
                $completion_note_id = $note->id;
                break;
            }
        }

        // Update the completion note timestamp in the database if found
        if ($completion_note_id) {
            $wpdb->update(
                $wpdb->comments,
                array(
                    'comment_date' => $completion_datetime_wp,
                    'comment_date_gmt' => $completion_datetime_gmt
                ),
                array(
                    'comment_ID' => $completion_note_id
                ),
                array('%s', '%s'),
                array('%d')
            );

            // Clear cache for the comment
            clean_comment_cache($completion_note_id);
        }

        // If order is not completed, complete it with the manual date/time
        if ($order->get_status() !== 'completed') {
            // Update order status directly in the database to avoid hook triggers
            $wpdb->update(
                $wpdb->posts,
                array('post_status' => 'wc-completed'),
                array('ID' => $order_id),
                array('%s'),
                array('%d')
            );
            
            // Clear order cache
            clean_post_cache($order_id);
            
            // Set the date completed to our manual date/time
            $order->update_meta_data('_date_completed', $completion_datetime_wp);
            
            // Now create the completion note with our custom date/time
            if (!$completion_note_id) {
                // If no completion note exists, create one with the correct timestamp
                $note_id = wp_insert_comment(array(
                    'comment_post_ID' => $order_id,
                    'comment_author' => 'WooCommerce',
                    'comment_content' => 'Order automatically completed - all tiffins delivered.',
                    'comment_type' => 'order_note',
                    'comment_date' => $completion_datetime_wp,
                    'comment_date_gmt' => $completion_datetime_gmt,
                    'comment_approved' => 1
                ));
                
                // Add meta to indicate it's an internal note
                if ($note_id) {
                    add_comment_meta($note_id, 'is_customer_note', 0);
                    // Clear cache
                    clean_comment_cache($note_id);
                }
            }
        } else {
            // Order is already completed, update the _date_completed meta
            $order->update_meta_data('_date_completed', $completion_datetime_wp);
        }

        // Update date_modified to the completion datetime
        $order->set_date_modified($completion_datetime_wp);
        $order->save();

        add_settings_error(
            'tiffin_messages',
            'completion_datetime_set',
            sprintf(
                'Completion date/time set successfully for Order #%d: %s at %s',
                $order_id,
                date('F j, Y', $completion_timestamp),
                date('g:i A', $completion_timestamp)
            ),
            'updated'
        );
    }

    /**
     * Get manual completion date/time for an order
     * 
     * @param int $order_id Order ID
     * @return array|false Returns array with date, time, datetime, and timestamp, or false if not set
     */
    public static function get_order_completion_datetime($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $manual_date = $order->get_meta('_manual_completion_date', true);
        $manual_time = $order->get_meta('_manual_completion_time', true);

        if (empty($manual_date) || empty($manual_time)) {
            return false;
        }

        return array(
            'date' => $manual_date,
            'time' => $manual_time,
            'datetime' => $order->get_meta('_manual_completion_datetime', true),
            'timestamp' => $order->get_meta('_manual_completion_timestamp', true)
        );
    }

    // End of Tiffin History // 

    /**
     * Renewal Reminders settings page HTML
     */
    public function renewal_reminders_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle manual trigger
        $manual_result = null;
        if (isset($_POST['trigger_renewal_check']) && check_admin_referer('trigger_renewal_check')) {
            if (class_exists('Satguru_Tiffin_Calculator')) {
                $manual_result = Satguru_Tiffin_Calculator::check_and_send_renewal_reminders();
            }
        }
        
        // Handle reset reminder for specific order
        if (isset($_POST['reset_reminder_order_id']) && check_admin_referer('reset_renewal_reminder')) {
            $order_id = absint($_POST['reset_reminder_order_id']);
            if ($order_id && class_exists('Satguru_Tiffin_Calculator')) {
                Satguru_Tiffin_Calculator::reset_renewal_reminder($order_id);
                add_settings_error('satguru_wati_messages', 'satguru_message', 'Renewal reminder reset for order #' . $order_id, 'updated');
            }
        }
        
        // Handle test API connection
        $test_result = null;
        if (isset($_POST['test_wati_connection']) && check_admin_referer('test_wati_connection')) {
            $test_result = $this->test_wati_api_connection();
        }
        
        // Handle test message send
        $test_message_result = null;
        if (isset($_POST['send_test_message']) && check_admin_referer('send_test_message')) {
            $test_phone = isset($_POST['test_phone_number']) ? sanitize_text_field($_POST['test_phone_number']) : '';
            if (!empty($test_phone)) {
                $test_message_result = $this->send_test_wati_message($test_phone);
            } else {
                $test_message_result = ['success' => false, 'message' => 'Please enter a phone number'];
            }
        }
        
        // Get current settings
        $reminder_enabled = get_option('renewal_reminder_enabled', '0');
        $wati_endpoint = get_option('wati_api_endpoint', '');
        $wati_token = get_option('wati_api_token', '');
        $wati_template = get_option('wati_renewal_template_name', 'renewal_reminder');
        $tiffin_threshold = get_option('renewal_reminder_tiffin_threshold', 3);
        $exclude_trial_meals = get_option('renewal_reminder_exclude_trial_meals', '1');
        $excluded_products = get_option('renewal_reminder_excluded_products', []);
        if (!is_array($excluded_products)) {
            $excluded_products = [];
        }
        
        // Get pending renewal reminders from log
        global $wpdb;
        $log_table = $wpdb->prefix . 'renewal_reminders_log';
        $pending_reminders = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") == $log_table) {
            $pending_reminders = $wpdb->get_results("SELECT * FROM $log_table ORDER BY created_at DESC LIMIT 50");
        }
        
        // Get next scheduled cron run
        $next_scheduled = wp_next_scheduled('satguru_check_renewal_reminders');
        ?>
        <div class="wrap">
            <h1>Renewal Reminders</h1>
            <p>Configure automatic renewal reminders sent via WhatsApp when customers have <?php echo esc_html($tiffin_threshold); ?> tiffins remaining.</p>
            
            <?php settings_errors('satguru_wati_messages'); ?>
            
            <?php if ($manual_result !== null): ?>
                <?php if (isset($manual_result['skipped']) && $manual_result['skipped']): ?>
                    <div class="notice notice-warning">
                        <p>
                            <strong>‚è∏Ô∏è Renewal reminders are disabled.</strong><br>
                            Enable the feature above to send renewal reminders.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success">
                        <p>
                            <strong>Manual check completed!</strong><br>
                            Reminders sent: <?php echo esc_html($manual_result['reminders_sent']); ?><br>
                            <?php if (!empty($manual_result['errors'])): ?>
                                Errors: <?php echo count($manual_result['errors']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($test_result !== null): ?>
                <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?>">
                    <p>
                        <strong>API Connection Test:</strong> <?php echo esc_html($test_result['message']); ?>
                        <?php if (!empty($test_result['details'])): ?>
                            <br><code><?php echo esc_html($test_result['details']); ?></code>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if ($test_message_result !== null): ?>
                <div class="notice <?php echo $test_message_result['success'] ? 'notice-success' : 'notice-error'; ?>">
                    <p>
                        <strong>Test Message:</strong> <?php echo esc_html($test_message_result['message']); ?>
                        <?php if (!empty($test_message_result['details'])): ?>
                            <br><code style="word-break: break-all;"><?php echo esc_html($test_message_result['details']); ?></code>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="#wati-settings" class="nav-tab nav-tab-active" onclick="showTab('wati-settings', this)">Wati API Settings</a>
                <a href="#test-api" class="nav-tab" onclick="showTab('test-api', this)">Test API</a>
                <a href="#manual-trigger" class="nav-tab" onclick="showTab('manual-trigger', this)">Manual Trigger</a>
                <a href="#reminder-log" class="nav-tab" onclick="showTab('reminder-log', this)">Reminder Log</a>
            </h2>
            
            <!-- Wati API Settings Tab -->
            <div id="wati-settings" class="tab-content" style="display: block;">
                <form method="post" action="options.php">
                    <?php settings_fields('satguru_wati_settings'); ?>
                    
                    <!-- Master Toggle -->
                    <div style="background: <?php echo $reminder_enabled ? '#e8f5e9' : '#fff3e0'; ?>; border: 2px solid <?php echo $reminder_enabled ? '#4caf50' : '#ff9800'; ?>; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <label class="switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                                <input type="checkbox" name="renewal_reminder_enabled" id="renewal_reminder_enabled" value="1" 
                                       <?php checked($reminder_enabled, '1'); ?>
                                       style="opacity: 0; width: 0; height: 0;">
                                <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $reminder_enabled ? '#4caf50' : '#ccc'; ?>; transition: .4s; border-radius: 34px;">
                                    <span style="position: absolute; content: ''; height: 26px; width: 26px; left: <?php echo $reminder_enabled ? '30px' : '4px'; ?>; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                                </span>
                            </label>
                            <div>
                                <strong style="font-size: 16px; color: <?php echo $reminder_enabled ? '#2e7d32' : '#e65100'; ?>;">
                                    <?php echo $reminder_enabled ? '‚úÖ Renewal Reminders are ENABLED' : '‚è∏Ô∏è Renewal Reminders are DISABLED'; ?>
                                </strong>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    <?php echo $reminder_enabled 
                                        ? 'Automatic renewal reminders will be sent when customers have ' . esc_html($tiffin_threshold) . ' tiffins remaining.' 
                                        : 'No automatic reminders will be sent. Enable to start sending renewal reminders.'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="wati_api_endpoint">Wati API Endpoint</label></th>
                            <td>
                                <input type="url" name="wati_api_endpoint" id="wati_api_endpoint" 
                                       value="<?php echo esc_attr($wati_endpoint); ?>" class="regular-text" 
                                       placeholder="https://live-server-xxxxx.wati.io">
                                <p class="description">Your Wati API endpoint URL (e.g., https://live-server-xxxxx.wati.io)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wati_api_token">Wati API Token</label></th>
                            <td>
                                <input type="password" name="wati_api_token" id="wati_api_token" 
                                       value="<?php echo esc_attr($wati_token); ?>" class="regular-text">
                                <p class="description">Your Wati API authentication token</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wati_renewal_template_name">Template Name</label></th>
                            <td>
                                <input type="text" name="wati_renewal_template_name" id="wati_renewal_template_name" 
                                       value="<?php echo esc_attr($wati_template); ?>" class="regular-text">
                                <p class="description">
                                    The WhatsApp template name for renewal reminders. Template should include these variables:<br>
                                    <code>{{first_name}}</code>, <code>{{remaining_tiffins}}</code>, <code>{{end_date}}</code>, 
                                    <code>{{new_start_date}}</code>, <code>{{plan_name}}</code>, <code>{{renewal_link}}</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="renewal_reminder_tiffin_threshold">Tiffin Threshold</label></th>
                            <td>
                                <input type="number" name="renewal_reminder_tiffin_threshold" id="renewal_reminder_tiffin_threshold" 
                                       value="<?php echo esc_attr($tiffin_threshold); ?>" min="1" max="20" class="small-text">
                                <p class="description">Send renewal reminder when customer has this many tiffins remaining (default: 3)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <hr style="margin: 30px 0;">
                    
                    <h3>Ì†ΩÌ∫´ Exclusions</h3>
                    <p>Choose which orders should NOT receive renewal reminders:</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Exclude Trial Meals</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="renewal_reminder_exclude_trial_meals" value="1" 
                                           <?php checked($exclude_trial_meals, '1'); ?>>
                                    <strong>Don't send reminders for trial meal orders</strong>
                                </label>
                                <p class="description">Trial meals are typically one-time orders that don't need renewal reminders.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="renewal_reminder_excluded_products">Exclude Specific Plans</label></th>
                            <td>
                                <?php
                                // Get all WooCommerce products
                                $all_products = wc_get_products([
                                    'status' => 'publish',
                                    'limit' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                ]);
                                ?>
                                <select name="renewal_reminder_excluded_products[]" id="renewal_reminder_excluded_products" 
                                        multiple size="8" style="width: 100%; max-width: 500px;">
                                    <?php foreach ($all_products as $product): ?>
                                        <option value="<?php echo esc_attr($product->get_id()); ?>" 
                                                <?php selected(in_array($product->get_id(), $excluded_products)); ?>>
                                            <?php echo esc_html($product->get_name()); ?> (ID: <?php echo $product->get_id(); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    Hold Ctrl (Windows) or Command (Mac) to select multiple products.<br>
                                    Orders containing these products will NOT receive renewal reminders.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Settings'); ?>
                </form>
                
                <hr>
                
                <h3>Cron Status</h3>
                <p>
                    <strong>Next scheduled check:</strong> 
                    <?php 
                    if ($next_scheduled) {
                        $toronto_time = new DateTime('@' . $next_scheduled);
                        $toronto_time->setTimezone(new DateTimeZone('America/Toronto'));
                        echo esc_html($toronto_time->format('F j, Y g:i A T'));
                    } else {
                        echo '<span style="color: #d63638;">Not scheduled</span>';
                    }
                    ?>
                </p>
            </div>
            
            <!-- Test API Tab -->
            <div id="test-api" class="tab-content" style="display: none;">
                <h3>Test API Connection</h3>
                <p>Test if your Wati API credentials are configured correctly.</p>
                
                <form method="post">
                    <?php wp_nonce_field('test_wati_connection'); ?>
                    <p>
                        <button type="submit" name="test_wati_connection" class="button button-secondary">
                            Ì†ΩÌ¥å Test API Connection
                        </button>
                    </p>
                </form>
                
                <hr>
                
                <h3>Send Test Message</h3>
                <p>Send a test WhatsApp message using your configured template to verify everything is working.</p>
                
                <form method="post">
                    <?php wp_nonce_field('send_test_message'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_phone_number">Phone Number</label></th>
                            <td>
                                <input type="text" name="test_phone_number" id="test_phone_number" 
                                       class="regular-text" placeholder="e.g., 14165551234 or 4165551234"
                                       value="<?php echo isset($_POST['test_phone_number']) ? esc_attr($_POST['test_phone_number']) : ''; ?>">
                                <p class="description">Enter phone number with or without country code (will add +1 for 10-digit numbers)</p>
                            </td>
                        </tr>
                    </table>
                    <p>
                        <button type="submit" name="send_test_message" class="button button-primary">
                            Ì†ΩÌ≥± Send Test Message
                        </button>
                    </p>
                </form>
                
                <hr>
                
                <h3>Test Data Preview</h3>
                <p>The test message will use these sample values:</p>
                <table class="widefat" style="max-width: 600px;">
                    <tr><th>Variable</th><th>Test Value</th></tr>
                    <tr><td><code>{{first_name}}</code></td><td>Test Customer</td></tr>
                    <tr><td><code>{{remaining_tiffins}}</code></td><td>3</td></tr>
                    <tr><td><code>{{end_date}}</code></td><td><?php echo esc_html(date('F j, Y', strtotime('+3 days'))); ?></td></tr>
                    <tr><td><code>{{new_start_date}}</code></td><td><?php echo esc_html(date('F j, Y', strtotime('+4 days'))); ?></td></tr>
                    <tr><td><code>{{plan_name}}</code></td><td>Test Plan - 5 Tiffin Package</td></tr>
                    <tr><td><code>{{renewal_link}}</code></td><td><?php echo esc_html(home_url('/customer-order/test-token-12345')); ?></td></tr>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">
                    <strong>Ì†ΩÌ≤° Tips:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>Make sure you've saved your API settings before testing</li>
                        <li>The template name must match exactly what's configured in Wati</li>
                        <li>Check the Wati dashboard for message delivery status</li>
                        <li>If using a new template, ensure it's approved by WhatsApp</li>
                    </ul>
                </div>
            </div>
            
            <!-- Manual Trigger Tab -->
            <div id="manual-trigger" class="tab-content" style="display: none;">
                <h3>Manually Check Orders</h3>
                <p>Click the button below to manually check all processing orders and send renewal reminders to customers with <?php echo esc_html($tiffin_threshold); ?> tiffins remaining.</p>
                
                <form method="post">
                    <?php wp_nonce_field('trigger_renewal_check'); ?>
                    <p>
                        <button type="submit" name="trigger_renewal_check" class="button button-primary">
                            Check Orders & Send Reminders Now
                        </button>
                    </p>
                </form>
                
                <hr>
                
                <h3>Reset Reminder for Specific Order</h3>
                <p>If you need to re-send a renewal reminder for a specific order, enter the order ID below to reset its reminder status.</p>
                
                <form method="post">
                    <?php wp_nonce_field('reset_renewal_reminder'); ?>
                    <p>
                        <label for="reset_reminder_order_id">Order ID:</label>
                        <input type="number" name="reset_reminder_order_id" id="reset_reminder_order_id" min="1" class="small-text">
                        <button type="submit" class="button">Reset Reminder</button>
                    </p>
                </form>
            </div>
            
            <!-- Reminder Log Tab -->
            <div id="reminder-log" class="tab-content" style="display: none;">
                <h3>Recent Renewal Reminders</h3>
                
                <?php if (empty($pending_reminders)): ?>
                    <p>No renewal reminders have been logged yet.</p>
                <?php else: ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Remaining</th>
                                <th>End Date</th>
                                <th>New Start</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reminders as $reminder): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $reminder->order_id . '&action=edit'); ?>">
                                            #<?php echo esc_html($reminder->order_id); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($reminder->customer_name); ?></td>
                                    <td><?php echo esc_html($reminder->customer_phone); ?></td>
                                    <td><?php echo esc_html($reminder->remaining_tiffins); ?></td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($reminder->estimated_end_date))); ?></td>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($reminder->new_start_date))); ?></td>
                                    <td><?php echo esc_html($reminder->plan_name); ?></td>
                                    <td>
                                        <span class="status-<?php echo esc_attr($reminder->status); ?>">
                                            <?php echo esc_html(ucfirst($reminder->status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('M j, Y g:i A', strtotime($reminder->created_at))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .tab-content { padding: 20px 0; }
            .status-pending { color: #996800; background: #fcf0c3; padding: 2px 8px; border-radius: 3px; }
            .status-sent { color: #006505; background: #c3f0c8; padding: 2px 8px; border-radius: 3px; }
            .status-failed { color: #8b0000; background: #f0c3c3; padding: 2px 8px; border-radius: 3px; }
        </style>
        
        <script>
        function showTab(tabId, element) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.style.display = 'none';
            });
            // Remove active class from all nav tabs
            document.querySelectorAll('.nav-tab').forEach(function(tab) {
                tab.classList.remove('nav-tab-active');
            });
            // Show selected tab
            document.getElementById(tabId).style.display = 'block';
            // Add active class to clicked tab
            element.classList.add('nav-tab-active');
            return false;
        }
        
        // Toggle switch styling
        document.addEventListener('DOMContentLoaded', function() {
            var toggleInput = document.getElementById('renewal_reminder_enabled');
            if (toggleInput) {
                toggleInput.addEventListener('change', function() {
                    var container = this.closest('div[style*="background"]');
                    var slider = this.nextElementSibling;
                    var dot = slider.querySelector('span');
                    var textContainer = container.querySelector('div:last-child');
                    var strongText = textContainer.querySelector('strong');
                    var pText = textContainer.querySelector('p');
                    
                    if (this.checked) {
                        container.style.background = '#e8f5e9';
                        container.style.borderColor = '#4caf50';
                        slider.style.backgroundColor = '#4caf50';
                        dot.style.left = '30px';
                        strongText.style.color = '#2e7d32';
                        strongText.innerHTML = '‚úÖ Renewal Reminders are ENABLED';
                        pText.textContent = 'Automatic renewal reminders will be sent. Save settings to apply.';
                    } else {
                        container.style.background = '#fff3e0';
                        container.style.borderColor = '#ff9800';
                        slider.style.backgroundColor = '#ccc';
                        dot.style.left = '4px';
                        strongText.style.color = '#e65100';
                        strongText.innerHTML = '‚è∏Ô∏è Renewal Reminders are DISABLED';
                        pText.textContent = 'No automatic reminders will be sent. Save settings to apply.';
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Test Wati API connection
     * 
     * @return array Result with success status and message
     */
    private function test_wati_api_connection() {
        $wati_endpoint = get_option('wati_api_endpoint', '');
        $wati_token = get_option('wati_api_token', '');
        
        if (empty($wati_endpoint)) {
            return [
                'success' => false,
                'message' => 'Wati API endpoint is not configured',
                'details' => ''
            ];
        }
        
        if (empty($wati_token)) {
            return [
                'success' => false,
                'message' => 'Wati API token is not configured',
                'details' => ''
            ];
        }
        
        // Test API connection by getting templates list
        $api_url = rtrim($wati_endpoint, '/') . '/api/v1/getMessageTemplates';
        
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $wati_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
                'details' => ''
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            $template_count = isset($data['messageTemplates']) ? count($data['messageTemplates']) : 0;
            return [
                'success' => true,
                'message' => "Connection successful! Found {$template_count} templates in your account.",
                'details' => ''
            ];
        } elseif ($response_code === 401) {
            return [
                'success' => false,
                'message' => 'Authentication failed - Invalid API token',
                'details' => 'HTTP ' . $response_code
            ];
        } else {
            return [
                'success' => false,
                'message' => 'API returned error',
                'details' => 'HTTP ' . $response_code . ' - ' . substr($response_body, 0, 200)
            ];
        }
    }
    
    /**
     * Send test WhatsApp message
     * 
     * @param string $phone_number Phone number to send test to
     * @return array Result with success status and message
     */
    private function send_test_wati_message($phone_number) {
        $wati_endpoint = get_option('wati_api_endpoint', '');
        $wati_token = get_option('wati_api_token', '');
        $wati_template = get_option('wati_renewal_template_name', 'renewal_reminder');
        
        if (empty($wati_endpoint) || empty($wati_token)) {
            return [
                'success' => false,
                'message' => 'Wati API is not configured. Please save your settings first.',
                'details' => ''
            ];
        }
        
        // Normalize phone number
        $phone = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Add country code if not present (assuming Canada +1)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        if (strlen($phone) < 10) {
            return [
                'success' => false,
                'message' => 'Invalid phone number. Please enter at least 10 digits.',
                'details' => 'Normalized: ' . $phone
            ];
        }
        
        // Prepare test template parameters
        $template_params = [
            [
                'name' => 'first_name',
                'value' => 'Test Customer'
            ],
            [
                'name' => 'remaining_tiffins',
                'value' => '3'
            ],
            [
                'name' => 'end_date',
                'value' => date('F j, Y', strtotime('+3 days'))
            ],
            [
                'name' => 'new_start_date',
                'value' => date('F j, Y', strtotime('+4 days'))
            ],
            [
                'name' => 'plan_name',
                'value' => 'Test Plan - 5 Tiffin Package'
            ],
            [
                'name' => 'renewal_link',
                'value' => home_url('/customer-order/test-token-12345')
            ]
        ];
        
        // Build Wati API request
        $api_url = rtrim($wati_endpoint, '/') . '/api/v1/sendTemplateMessage?whatsappNumber=' . $phone;
        
        $body = [
            'template_name' => $wati_template,
            'broadcast_name' => 'test_message_' . time(),
            'parameters' => $template_params
        ];
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $wati_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message(),
                'details' => ''
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            $message_id = isset($response_data['messageId']) ? $response_data['messageId'] : 'N/A';
            return [
                'success' => true,
                'message' => "Test message sent successfully to +{$phone}!",
                'details' => "Template: {$wati_template} | Message ID: {$message_id}"
            ];
        } else {
            $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
            $error_details = isset($response_data['info']) ? $response_data['info'] : '';
            return [
                'success' => false,
                'message' => "Failed to send message: {$error_msg}",
                'details' => "HTTP {$response_code} | Template: {$wati_template} | Phone: +{$phone}" . ($error_details ? " | Info: {$error_details}" : '')
            ];
        }
    }
    
    /**
     * OTP Login settings page HTML
     */
    public function otp_login_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submission
        if (isset($_POST['satguru_otp_settings_submit']) && check_admin_referer('satguru_otp_settings_nonce')) {
            update_option('satguru_otp_login_enabled', isset($_POST['satguru_otp_login_enabled']) ? '1' : '0');
            update_option('satguru_otp_email_enabled', isset($_POST['satguru_otp_email_enabled']) ? '1' : '0');
            update_option('satguru_otp_whatsapp_enabled', isset($_POST['satguru_otp_whatsapp_enabled']) ? '1' : '0');
            update_option('satguru_otp_whatsapp_template', sanitize_text_field($_POST['satguru_otp_whatsapp_template'] ?? 'otp_login'));
            update_option('satguru_otp_email_logo', esc_url_raw($_POST['satguru_otp_email_logo'] ?? ''));
            update_option('satguru_otp_email_color', sanitize_hex_color($_POST['satguru_otp_email_color'] ?? '#F26B0A'));
            
            echo '<div class="notice notice-success is-dismissible"><p>OTP Login settings saved successfully!</p></div>';
        }
        
        // Handle test email OTP
        if (isset($_POST['test_email_otp']) && check_admin_referer('satguru_otp_settings_nonce')) {
            $test_email = sanitize_email($_POST['test_email_address'] ?? '');
            if ($test_email) {
                $result = $this->send_test_email_otp($test_email);
                if ($result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                }
            }
        }
        
        // Handle test WhatsApp OTP
        if (isset($_POST['test_whatsapp_otp']) && check_admin_referer('satguru_otp_settings_nonce')) {
            $test_phone = sanitize_text_field($_POST['test_phone_number'] ?? '');
            if ($test_phone) {
                $result = $this->send_test_whatsapp_otp($test_phone);
                if ($result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                }
            }
        }
        
        // Get current settings
        $otp_enabled = get_option('satguru_otp_login_enabled', '0');
        $email_enabled = get_option('satguru_otp_email_enabled', '1');
        $whatsapp_enabled = get_option('satguru_otp_whatsapp_enabled', '0');
        $whatsapp_template = get_option('satguru_otp_whatsapp_template', 'otp_login');
        $email_logo = get_option('satguru_otp_email_logo', '');
        $email_color = get_option('satguru_otp_email_color', '#F26B0A');
        
        // Check Wati configuration
        $wati_configured = !empty(get_option('wati_api_endpoint', '')) && !empty(get_option('wati_api_token', ''));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <style>
                .otp-settings-container {
                    max-width: 800px;
                    margin-top: 20px;
                }
                .otp-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 8px;
                    padding: 24px;
                    margin-bottom: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .otp-card h2 {
                    margin-top: 0;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .otp-card h2 .icon {
                    font-size: 24px;
                }
                .otp-toggle {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 16px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    margin-bottom: 16px;
                }
                .otp-toggle.enabled {
                    background: #e6f4ea;
                    border: 1px solid #34a853;
                }
                .otp-toggle.disabled {
                    background: #fce8e6;
                    border: 1px solid #ea4335;
                }
                .toggle-switch {
                    position: relative;
                    width: 50px;
                    height: 26px;
                }
                .toggle-switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                .toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .3s;
                    border-radius: 26px;
                }
                .toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 20px;
                    width: 20px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .3s;
                    border-radius: 50%;
                }
                input:checked + .toggle-slider {
                    background-color: #F26B0A;
                }
                input:checked + .toggle-slider:before {
                    transform: translateX(24px);
                }
                .toggle-label {
                    font-weight: 600;
                    font-size: 15px;
                }
                .toggle-description {
                    color: #666;
                    font-size: 13px;
                    margin-top: 4px;
                }
                .form-row {
                    margin-bottom: 20px;
                }
                .form-row label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 6px;
                }
                .form-row input[type="text"],
                .form-row input[type="url"],
                .form-row input[type="email"] {
                    width: 100%;
                    max-width: 400px;
                    padding: 10px 12px;
                    border: 1px solid #ccd0d4;
                    border-radius: 6px;
                    font-size: 14px;
                }
                .form-row input:focus {
                    border-color: #F26B0A;
                    outline: none;
                    box-shadow: 0 0 0 2px rgba(242, 107, 10, 0.1);
                }
                .form-row .description {
                    color: #666;
                    font-size: 13px;
                    margin-top: 6px;
                }
                .color-picker-row {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .color-picker-row input[type="color"] {
                    width: 50px;
                    height: 40px;
                    border: 1px solid #ccd0d4;
                    border-radius: 6px;
                    cursor: pointer;
                }
                .test-section {
                    background: #f0f6fc;
                    border: 1px solid #c8e1ff;
                    border-radius: 8px;
                    padding: 20px;
                    margin-top: 20px;
                }
                .test-section h3 {
                    margin-top: 0;
                    color: #0366d6;
                }
                .test-row {
                    display: flex;
                    gap: 12px;
                    align-items: flex-end;
                    margin-bottom: 12px;
                }
                .test-row .input-group {
                    flex: 1;
                }
                .test-row label {
                    display: block;
                    font-weight: 500;
                    margin-bottom: 6px;
                    font-size: 13px;
                }
                .test-row input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ccd0d4;
                    border-radius: 6px;
                }
                .btn-test {
                    background: #0366d6;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                    white-space: nowrap;
                }
                .btn-test:hover {
                    background: #0256b9;
                }
                .btn-save {
                    background: #F26B0A;
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 15px;
                }
                .btn-save:hover {
                    background: #d45a00;
                }
                .warning-box {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 16px;
                }
                .warning-box p {
                    margin: 0;
                    color: #856404;
                }
                .warning-box a {
                    color: #533f03;
                    font-weight: 600;
                }
                .method-card {
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 16px;
                }
                .method-card.active {
                    border-color: #F26B0A;
                    background: #fff8f3;
                }
                .method-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 16px;
                }
                .method-title {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-size: 16px;
                    font-weight: 600;
                }
                .method-title .icon {
                    font-size: 24px;
                }
                .status-badge {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status-badge.active {
                    background: #e6f4ea;
                    color: #137333;
                }
                .status-badge.inactive {
                    background: #f1f3f4;
                    color: #5f6368;
                }
                .shortcode-info {
                    background: #f8f9fa;
                    border: 1px dashed #ccd0d4;
                    border-radius: 8px;
                    padding: 16px;
                    margin-top: 20px;
                }
                .shortcode-info h4 {
                    margin: 0 0 8px 0;
                }
                .shortcode-code {
                    background: #1e1e1e;
                    color: #d4d4d4;
                    padding: 12px 16px;
                    border-radius: 6px;
                    font-family: monospace;
                    font-size: 14px;
                    margin-top: 8px;
                }
            </style>
            
            <div class="otp-settings-container">
                <form method="post" action="">
                    <?php wp_nonce_field('satguru_otp_settings_nonce'); ?>
                    
                    <!-- Master Toggle -->
                    <div class="otp-card">
                        <h2>OTP Login System</h2>
                        
                        <div class="otp-toggle <?php echo $otp_enabled === '1' ? 'enabled' : 'disabled'; ?>">
                            <label class="toggle-switch">
                                <input type="checkbox" name="satguru_otp_login_enabled" value="1" <?php checked($otp_enabled, '1'); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <div>
                                <div class="toggle-label">Enable OTP Login</div>
                                <div class="toggle-description">Allow customers to login using One-Time Password sent via Email or WhatsApp</div>
                            </div>
                        </div>
                        
                        <p>When enabled, customers will see an option to login with OTP on the My Account page. They can choose between password login and OTP login.</p>
                    </div>
                    
                    <!-- Email OTP Settings -->
                    <div class="otp-card">
                        <h2>Email OTP</h2>
                        
                        <div class="method-card <?php echo $email_enabled === '1' ? 'active' : ''; ?>">
                            <div class="method-header">
                                <div class="method-title">
                                    Email OTP Authentication
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="satguru_otp_email_enabled" value="1" <?php checked($email_enabled, '1'); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <p>Send OTP codes to customers via email. Uses WordPress mail system.</p>
                            
                            <div class="form-row">
                                <label for="satguru_otp_email_logo">Email Logo URL</label>
                                <input type="url" id="satguru_otp_email_logo" name="satguru_otp_email_logo" value="<?php echo esc_attr($email_logo); ?>" placeholder="https://yoursite.com/logo.png">
                                <p class="description">Logo to display in OTP emails. Leave empty to use a default icon.</p>
                            </div>
                            
                            <div class="form-row">
                                <label for="satguru_otp_email_color">Brand Color</label>
                                <div class="color-picker-row">
                                    <input type="color" id="satguru_otp_email_color" name="satguru_otp_email_color" value="<?php echo esc_attr($email_color); ?>">
                                    <input type="text" value="<?php echo esc_attr($email_color); ?>" style="width: 100px;" readonly>
                                </div>
                                <p class="description">Primary color used in OTP emails (default: TiffinGrab orange)</p>
                            </div>
                        </div>
                        
                        <div class="test-section">
                            <h3>Test Email OTP</h3>
                            <div class="test-row">
                                <div class="input-group">
                                    <label for="test_email_address">Email Address</label>
                                    <input type="email" id="test_email_address" name="test_email_address" placeholder="test@example.com">
                                </div>
                                <button type="submit" name="test_email_otp" class="btn-test">Send Test OTP</button>
                            </div>
                            <p style="margin: 0; font-size: 12px; color: #666;">Send a test OTP email to verify your email configuration.</p>
                        </div>
                    </div>
                    
                    <!-- WhatsApp OTP Settings -->
                    <div class="otp-card">
                        <h2>WhatsApp OTP</h2>
                        
                        <?php if (!$wati_configured): ?>
                        <div class="warning-box">
                            <p><strong>Wati API not configured!</strong> Please configure your Wati API settings in <a href="<?php echo admin_url('admin.php?page=renewal-reminders'); ?>">Renewal Reminders</a> first to enable WhatsApp OTP.</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="method-card <?php echo $whatsapp_enabled === '1' && $wati_configured ? 'active' : ''; ?>">
                            <div class="method-header">
                                <div class="method-title">
                                    WhatsApp OTP Authentication
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="satguru_otp_whatsapp_enabled" value="1" <?php checked($whatsapp_enabled, '1'); ?> <?php echo !$wati_configured ? 'disabled' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <p>Send OTP codes to customers via WhatsApp using Wati API. Requires a pre-approved message template.</p>
                            
                            <div class="form-row">
                                <label for="satguru_otp_whatsapp_template">WhatsApp Template Name</label>
                                <input type="text" id="satguru_otp_whatsapp_template" name="satguru_otp_whatsapp_template" value="<?php echo esc_attr($whatsapp_template); ?>" placeholder="otp_login">
                                <p class="description">The Wati template name for OTP messages. Template should have parameter: <code>{{1}}</code> for the OTP code</p>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-top: 16px;">
                                <h4 style="margin: 0 0 8px 0;">Template Format</h4>
                                <pre style="background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 6px; margin: 0; font-size: 13px; overflow-x: auto;">{{1}} is your verification code. For your security, do not share this code.</pre>
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Where <code>{{1}}</code> will be replaced with the 6-digit OTP code.</p>
                            </div>
                        </div>
                        
                        <?php if ($wati_configured): ?>
                        <div class="test-section">
                            <h3>Test WhatsApp OTP</h3>
                            <div class="test-row">
                                <div class="input-group">
                                    <label for="test_phone_number">Phone Number (with country code)</label>
                                    <input type="text" id="test_phone_number" name="test_phone_number" placeholder="+1234567890">
                                </div>
                                <button type="submit" name="test_whatsapp_otp" class="btn-test">Send Test OTP</button>
                            </div>
                            <p style="margin: 0; font-size: 12px; color: #666;">Send a test OTP via WhatsApp to verify your Wati configuration.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Shortcode Info -->
                    <div class="otp-card">
                        <h2>Usage</h2>
                        
                        <p>OTP login is automatically added to the WooCommerce My Account login form when enabled.</p>
                        
                        <div class="shortcode-info">
                            <h4>Standalone OTP Login Form</h4>
                            <p>Use this shortcode to add a standalone OTP login form anywhere on your site:</p>
                            <div class="shortcode-code">[satguru_otp_login]</div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <h4>How it works:</h4>
                            <ol>
                                <li>Customer enters their email or phone number</li>
                                <li>System sends a 6-digit OTP code via selected method</li>
                                <li>Customer enters the OTP code</li>
                                <li>If valid, customer is logged in automatically</li>
                            </ol>
                            
                            <h4>Security Features:</h4>
                            <ul>
                                <li>OTP expires after 10 minutes</li>
                                <li>Maximum 5 verification attempts per OTP</li>
                                <li>Rate limiting prevents OTP spam (30 second cooldown)</li>
                                <li>OTP codes are hashed and stored securely</li>
                            </ul>
                        </div>
                    </div>
                    
                    <p>
                        <button type="submit" name="satguru_otp_settings_submit" class="btn-save">Save Settings</button>
                    </p>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Update color text when color picker changes
                $('#satguru_otp_email_color').on('input', function() {
                    $(this).next('input').val($(this).val());
                });
                
                // Toggle card active state
                $('input[name="satguru_otp_email_enabled"]').on('change', function() {
                    $(this).closest('.method-card').toggleClass('active', this.checked);
                });
                
                $('input[name="satguru_otp_whatsapp_enabled"]').on('change', function() {
                    $(this).closest('.method-card').toggleClass('active', this.checked);
                });
                
                // Master toggle visual update
                $('input[name="satguru_otp_login_enabled"]').on('change', function() {
                    var $toggle = $(this).closest('.otp-toggle');
                    if (this.checked) {
                        $toggle.removeClass('disabled').addClass('enabled');
                    } else {
                        $toggle.removeClass('enabled').addClass('disabled');
                    }
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Send test email OTP
     */
    private function send_test_email_otp($email) {
        $site_name = get_bloginfo('name');
        $otp = sprintf('%06d', mt_rand(0, 999999));
        
        $subject = sprintf('[%s] Test OTP Code', $site_name);
        
        $logo_url = get_option('satguru_otp_email_logo', '');
        $primary_color = get_option('satguru_otp_email_color', '#F26B0A');
        
        $message = '<!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f7;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f7; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <tr>
                                <td style="padding: 40px; text-align: center;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">Ì†æÌ∑™</div>
                                    <h1 style="margin: 0 0 16px; font-size: 24px; color: #1f2937;">Test OTP Email</h1>
                                    <p style="margin: 0 0 24px; color: #4b5563;">This is a test OTP email to verify your configuration.</p>
                                    <div style="background: linear-gradient(135deg, ' . esc_attr($primary_color) . ' 0%, #d45a00 100%); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
                                        <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #ffffff; font-family: monospace;">' . $otp . '</span>
                                    </div>
                                    <p style="margin: 0; color: #10b981; font-weight: 600;">‚úÖ Email configuration is working!</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if ($sent) {
            return [
                'success' => true,
                'message' => 'Test OTP email sent successfully to ' . $email . '! Check your inbox.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send test email. Please check your WordPress mail configuration.'
            ];
        }
    }
    
    /**
     * Send test WhatsApp OTP
     */
    private function send_test_whatsapp_otp($phone) {
        $wati_api_endpoint = get_option('wati_api_endpoint', '');
        $wati_api_token = get_option('wati_api_token', '');
        $wati_template = get_option('satguru_otp_whatsapp_template', 'otp_login');
        
        if (empty($wati_api_endpoint) || empty($wati_api_token)) {
            return [
                'success' => false,
                'message' => 'Wati API is not configured. Please configure it in Renewal Reminders settings.'
            ];
        }
        
        // Clean phone number
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean_phone) === 10) {
            $clean_phone = '1' . $clean_phone;
        }
        
        $otp = sprintf('%06d', mt_rand(0, 999999));
        
        // Template format: {{1}} is your verification code. For your security, do not share this code.
        $template_params = [
            ['name' => '1', 'value' => $otp]
        ];
        
        $api_url = rtrim($wati_api_endpoint, '/') . '/api/v1/sendTemplateMessage?whatsappNumber=' . $clean_phone;
        
        $body = [
            'template_name' => $wati_template,
            'broadcast_name' => 'test_otp_' . time(),
            'parameters' => $template_params
        ];
        
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $wati_api_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Request failed: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'message' => 'Test OTP sent successfully to +' . $clean_phone . ' via WhatsApp!'
            ];
        } else {
            $error_msg = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp OTP: ' . $error_msg . ' (HTTP ' . $response_code . ')'
            ];
        }
    }

}

// Initialize the settings class
new Satguru_Admin_Settings();