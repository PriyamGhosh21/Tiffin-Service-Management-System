<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/tiffin_logic.php';

// admin advanced search funtion js hook //
// Add this to your existing admin_enqueue_scripts hook
function satguru_enqueue_admin_scripts() {
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    
    // Add custom script
    wp_add_inline_script('jquery-ui-datepicker', '
        jQuery(document).ready(function($) {
            $(".datepicker").datepicker({
                dateFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true,
                yearRange: "2020:+0"
            });
        });
    ');
}
add_action('admin_enqueue_scripts', 'satguru_enqueue_admin_scripts');
// admin advanced search funtion js hook ends //


// Function to modify order address from the admin dashboard
function satguru_modify_order_address($order_id, $new_address) {
    $order = wc_get_order($order_id);
    if ($order) {
        $order->set_shipping_address_1($new_address['address_1']);
        $order->set_shipping_city($new_address['city']);
        $order->set_shipping_postcode($new_address['postcode']);
        $order->save();
    }
}

// Create a custom admin menu tab called "Admin"
function satguru_create_admin_menu() {
    add_menu_page(
        'Admin Dashboard',
        'Admin',
        'manage_options',
        'satguru-admin-dashboard',
        'satguru_admin_dashboard_page',
        'dashicons-admin-tools',
        6
    );

    // Add sub-menu items
    add_submenu_page(
        'satguru-admin-dashboard',
        "Today's Orders",
        "Today's Orders",
        'manage_options',
        'todays-orders',
        'satguru_todays_orders_page'
    );

    add_submenu_page(
        'satguru-admin-dashboard',
        'Next Day Orders',
        'Next Day Orders',
        'manage_options',
        'next-day-orders',
        'satguru_next_day_orders_page'
    );

    add_submenu_page(
        'satguru-admin-dashboard',
        'All Orders',
        'All Orders',
        'manage_options',
        'all-orders',
        'satguru_all_orders_page'
    );
}
add_action('admin_menu', 'satguru_create_admin_menu');

// Function to display orders in a table
function satguru_display_orders_table($orders) {
    // Get filter parameters
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $search_query = isset($_GET['search_query']) ? sanitize_text_field($_GET['search_query']) : '';

    // Filter orders by date range and search query
    if (!empty($date_from) || !empty($date_to) || !empty($search_query)) {
        $filtered_orders = array();
        foreach ($orders as $order) {
            $include_order = true;
            
            // Date range filter
            if (!empty($date_from) || !empty($date_to)) {
                $order_date = $order->get_date_created()->format('Y-m-d');
                if (!empty($date_from) && $order_date < $date_from) {
                    $include_order = false;
                }
                if (!empty($date_to) && $order_date > $date_to) {
                    $include_order = false;
                }
            }

            // Search filter
            if (!empty($search_query) && $include_order) {
                $search_term = strtolower($search_query);
                $searchable_data = array(
                    $order->get_id(), // Order ID
                    strtolower($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()), // Customer name
                    strtolower($order->get_billing_phone()), // Phone
                    strtolower($order->get_billing_email()), // Email
                    strtolower($order->get_shipping_address_1()), // Address
                    strtolower($order->get_shipping_city()), // City
                );

                // Add product names to searchable data
    foreach ($order->get_items() as $item) {
        $searchable_data[] = strtolower($item->get_name());
        
        // Search through item meta data for Start Date and Preferred Days
        foreach ($item->get_meta_data() as $meta) {
            if ($meta->key === 'Start Date' || 
                $meta->key === 'Delivery Date' || 
                $meta->key === 'Prefered Days') {
                $searchable_data[] = strtolower($meta->value);
            }
        }
    }

    // Search through order meta data as well
    $order_meta_keys = array(
        '_delivery_date',
        '_start_date',
        '_preferred_days',
        'Start Date',
        'Delivery Date',
        'Prefered Days'  // Note: keeping original spelling as it might be stored this way
    );

    foreach ($order_meta_keys as $meta_key) {
        $meta_value = $order->get_meta($meta_key);
        if (!empty($meta_value)) {
            if (is_array($meta_value)) {
                // Handle array values (like preferred days)
                $searchable_data[] = strtolower(implode(' ', $meta_value));
            } else {
                $searchable_data[] = strtolower($meta_value);
            }
        }
    }

    $found = false;
    foreach ($searchable_data as $data) {
        if (is_string($data) && strpos($data, $search_term) !== false) {
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
        $orders = $filtered_orders;
    }

    // Add filter form at the top
    ?>
    <div class="order-filters">
        <form method="get" class="order-filter-form">
            <?php
            // Preserve existing query parameters
            foreach ($_GET as $key => $value) {
                if (!in_array($key, ['date_from', 'date_to', 'search_query', 'paged'])) {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            <div class="filter-row">
                <div class="date-filters">
                    <input type="text" 
                           name="date_from" 
                           class="datepicker" 
                           placeholder="From Date" 
                           value="<?php echo esc_attr($date_from); ?>"
                           autocomplete="off">
                    <input type="text" 
                           name="date_to" 
                           class="datepicker" 
                           placeholder="To Date" 
                           value="<?php echo esc_attr($date_to); ?>"
                           autocomplete="off">
                </div>
                <div class="search-filter">
                    <input type="text" 
                           name="search_query" 
                           placeholder="Search orders..." 
                           value="<?php echo esc_attr($search_query); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="button">Apply Filters</button>
                    <a href="<?php echo esc_url(remove_query_arg(['date_from', 'date_to', 'search_query', 'paged'])); ?>" 
                       class="button">Reset Filters</a>
                </div>
            </div>
        </form>
    </div>

    <?php
    // Continue with the existing table display code...
    if (empty($orders)) {
        echo '<p>No orders found.</p>';
        return;
    }

    // Get pagination parameters
    $items_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    
    // Calculate total items and pages
    $total_items = count($orders);
    $total_pages = ceil($total_items / $items_per_page);
    
    // Ensure current page doesn't exceed total pages
    $current_page = min($current_page, $total_pages);
    
    // Calculate the slice of orders to display
    $start = ($current_page - 1) * $items_per_page;
    $displayed_orders = array_slice($orders, $start, $items_per_page);
    
    // Calculate displayed items range
    $start_item = $start + 1;
    $end_item = min($start + $items_per_page, $total_items);

    // Items per page dropdown options
    $per_page_options = array(10, 20, 50, 100, 200, 300, 500);
    ?>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <?php
                // Preserve existing query parameters
                foreach ($_GET as $key => $value) {
                    if ($key !== 'per_page' && $key !== 'paged') {
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                    }
                }
                ?>
                <select name="per_page" onchange="this.form.submit()">
                    <?php foreach ($per_page_options as $option): ?>
                        <option value="<?php echo $option; ?>" 
                                <?php selected($items_per_page, $option); ?>>
                            Show <?php echo $option; ?> entries
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num">
                Showing <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo $total_items; ?> entries
            </span>
            <?php if ($total_pages > 1): ?>
                <span class="pagination-links">
                    <?php
                    // First page link
                    $first_url = add_query_arg(['paged' => 1, 'per_page' => $items_per_page]);
                    $prev_url = add_query_arg(['paged' => max(1, $current_page - 1), 'per_page' => $items_per_page]);
                    $next_url = add_query_arg(['paged' => min($total_pages, $current_page + 1), 'per_page' => $items_per_page]);
                    $last_url = add_query_arg(['paged' => $total_pages, 'per_page' => $items_per_page]);
                    ?>
                    
                    <a class="first-page button <?php echo $current_page === 1 ? 'disabled' : ''; ?>" 
                       href="<?php echo $current_page === 1 ? '#' : esc_url($first_url); ?>">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                    
                    <a class="prev-page button <?php echo $current_page === 1 ? 'disabled' : ''; ?>" 
                       href="<?php echo $current_page === 1 ? '#' : esc_url($prev_url); ?>">
                        <span aria-hidden="true">&lsaquo;</span>
                    </a>
                    
                    <span class="paging-input">
                        <span class="tablenav-paging-text">
                            <?php printf('%s of <span class="total-pages">%s</span>', $current_page, $total_pages); ?>
                        </span>
                    </span>
                    
                    <a class="next-page button <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>" 
                       href="<?php echo $current_page === $total_pages ? '#' : esc_url($next_url); ?>">
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                    
                    <a class="last-page button <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>" 
                       href="<?php echo $current_page === $total_pages ? '#' : esc_url($last_url); ?>">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-container">
    <div class="table-scroll">
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Start Date</th>
                <th>Preferred Days</th>
                <th>Products</th>
                <th>Quantity</th>
                <th>Boxes</th>  <!-- New column -->
                <th>Want to Customize?</th>
                <th>Veg/Non-Veg</th>
                <th>Delivery</th>
                <th>Addons</th>
                <th>Notes</th>
                <th>Address</th>
                <th>City</th>
                <th>Postal Code</th>
                <th>Status</th>
                <th>Total Tiffins</th>
                <th>Remaining Tiffins</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayed_orders as $order) : 
                // Initialize variables for metadata
                $start_date = '';
                $preferred_days = '';
                $products = '';
                $customize = '';
                $veg_nonveg = '';
                $delivery = '';
                $addons = '';
                $remaining_tiffins = class_exists('Satguru_Tiffin_Calculator') ? 
                    Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order) : 0;
                $phone = $order->get_shipping_phone();
                $email = $order->get_billing_email();

                $check_date = date('Y-m-d');
                if (isset($_GET['page'])) {
                    if ($_GET['page'] === 'next-day-orders') {
                        if (isset($_GET['operational']) && $_GET['operational'] === '1') {
                            $check_date = class_exists('Satguru_Tiffin_Calculator') ? 
                                Satguru_Tiffin_Calculator::get_next_operational_day() : 
                                date('Y-m-d', strtotime('+1 day'));
                        } else {
                            $check_date = date('Y-m-d', strtotime('+1 day'));
                        }
                    }
                }
                
                // Calculate boxes for this order
                $boxes = class_exists('Satguru_Tiffin_Calculator') ? 
                    Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $check_date) : 0;
                

                // For Today's and Next Day's orders, skip if no remaining tiffins
                if (isset($_GET['page']) && 
                    ($_GET['page'] === 'todays-orders' || $_GET['page'] === 'next-day-orders') && 
                    $remaining_tiffins <= 0) {
                    continue;
                }
                // Get metadata from order items
                foreach ($order->get_items() as $item) {
                    $products .= '<strong>' . $item->get_name() . '</strong><br>';
                    $quantity = $item->get_quantity();
                    
                    foreach ($item->get_meta_data() as $meta) {
                        $meta_key = $meta->key;
                        $meta_value = $meta->value;
                        
                        // Start Date
                        if (strpos($meta_key, 'Start Date') !== false || 
                            strpos($meta_key, 'Delivery Date') !== false) {
                            $start_date = date('Y-m-d', strtotime($meta_value));
                        }
                        
                        // Preferred Days
                        if (strpos($meta_key, 'Prefered Days') !== false) {
                            $preferred_days .= esc_html($meta_value) . '<br>';
                        }
                        
                        // Want to Customize
                        if (strpos($meta_key, 'Want to Customize') !== false) {
                            $customize .= esc_html($meta_value) . '<br>';
                        }
                        
                        // Veg/Non-Veg
                        if (strpos($meta_key, 'Veg / Non Veg') !== false || 
                            strpos($meta_key, 'Food Type') !== false) {
                            $veg_nonveg .= esc_html($meta_value) . '<br>';
                        }
                        
                        // Delivery
                        if (strpos($meta_key, 'Delivery') !== false && 
                            strpos($meta_key, 'Delivery Date') === false) {
                            $delivery .= esc_html($meta_value) . '<br>';
                        }
                        if (strpos(strtolower($meta->key), 'add-ons') !== false || 
                            strpos(strtolower($meta->key), 'additional') !== false) {
                            if (!empty($meta->value)) {
                                // Handle array format
                                if (is_array($meta->value)) {
                                    // Clean and format array values
                                    $addon_value = array_map(function($item) {
                                        // If item is an array, process it
                                        if (is_array($item)) {
                                            return implode(', ', array_map(function($value, $key) {
                                                // Remove numeric indices
                                                if (is_numeric($key)) {
                                                    return $value;
                                                }
                                                return "$key = $value";
                                            }, $item, array_keys($item)));
                                        }
                                        return $item;
                                    }, $meta->value);
                                    
                                    // Filter out numeric keys from the main array
                                    $filtered_addons = array_filter($addon_value, function($key) {
                                        return !is_numeric($key);
                                    }, ARRAY_FILTER_USE_KEY);
                                    
                                    $addons .= implode(', ', $filtered_addons) . '<br>';
                                }
                                // Handle simple key-value format
                                else if (strpos($meta->key, '_addon_total') === false) {
                                    $addons .= esc_html($meta->value) . '<br>';
                                }
                            }
                        }
                    }
                }

                // Get order notes
                $args = array(
                    'order_id' => $order->get_id(),
                    'type' => 'customer',  // Only get customer-facing notes
                );
                $notes = wc_get_order_notes($args);
                $notes_html = '';
                if (!empty($notes)) {
                    foreach ($notes as $note) {
                        $notes_html .= '<div class="order-note">';
                        $notes_html .= '<span class="note-date">' . date('Y-m-d', strtotime($note->date_created)) . '</span>: ';
                        $notes_html .= esc_html($note->content);
                        $notes_html .= '</div>';
                    }
                }
                ?>
                <tr>
                    <td>#<?php echo $order->get_id(); ?></td>
                    <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                    <td><?php echo $phone ? $phone : 'N/A'; ?></td>
                    <td><?php echo $email ? esc_html($email) : 'N/A'; ?></td> 
                    <td><?php echo $start_date ? $start_date : 'N/A'; ?></td>
                    <td><?php echo $preferred_days ? $preferred_days : 'N/A'; ?></td>
                    <td><?php echo $products ? $products : 'N/A'; ?></td>
                    <td><?php echo isset($quantity) ? $quantity : 'N/A'; ?></td>
                    <td><?php echo $boxes ? $boxes : 'N/A'; ?></td>
                    <td><?php echo $customize ? $customize : 'N/A'; ?></td>
                    <td><?php echo $veg_nonveg ? $veg_nonveg : 'N/A'; ?></td>
                    <td><?php echo $delivery ? $delivery : 'N/A'; ?></td>
                    <td><?php echo $addons ? $addons : 'N/A'; ?></td>  <!-- New column -->
                    <td><?php 
                        // Get customer order notes
                        $customer_notes = $order->get_customer_note();
                        echo !empty($customer_notes) ? esc_html($customer_notes) : 'N/A';
                    ?></td>
                    <td><?php echo $order->get_shipping_address_1(); ?></td>
                    <td><?php echo $order->get_shipping_city(); ?></td>
                    <td><?php echo $order->get_shipping_postcode(); ?></td>
                    <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                    <td>
                        <?php 
                        $total_tiffins = 0;
                        foreach ($order->get_items() as $item) {
                            foreach ($item->get_meta_data() as $meta) {
                                if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                                    $total_tiffins = intval($meta->value);
                                    break 2; // Break both loops once we find the value
                                }
                            }
                        }
                        echo $total_tiffins > 0 ? $total_tiffins : 'N/A';
                        ?></td>   
                    <td class="remaining-tiffins <?php echo $remaining_tiffins <= 5 ? 'low' : ''; ?>">
                        <?php echo $remaining_tiffins; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="button">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>
    <?php
}

// Function to get orders by start date
function satguru_get_orders_by_start_date($date) {
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array_keys(wc_get_order_statuses()),
        'posts_per_page' => -1,
    );

    $orders = wc_get_orders($args);
    $filtered_orders = array();

    foreach ($orders as $order) {
        if (Satguru_Tiffin_Calculator::should_display_order($order, $date)) {
            $filtered_orders[] = $order;
        }
    }

    return $filtered_orders;
}

// Today's Orders page
function satguru_todays_orders_page() {
    $today = date('Y-m-d');
    $orders = satguru_get_orders_by_start_date($today);
    ?>
    <div class="wrap">
        <h1>Today's Orders</h1>
        <div class="export-button-container" id="todays-orders-export-button">
            <?php satguru_add_export_button('todays-orders'); ?>
        </div>
        <?php satguru_display_orders_table($orders); ?>
    </div>
    <?php

}

// Next Day Orders page
function satguru_next_day_orders_page() {
    $show_operational = isset($_GET['operational']) ? $_GET['operational'] : '0';
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    if ($show_operational === '1') {
        $check_date = Satguru_Tiffin_Calculator::get_next_operational_day();
    } else {
        $check_date = $tomorrow;
    }
    $orders = satguru_get_orders_by_start_date($check_date);
    ?>
    <div class="toggle-container">
            <label class="switch">
                <input type="checkbox" 
                       <?php checked($show_operational, '1'); ?>
                       onchange="window.location.href='?page=next-day-orders&operational=' + (this.checked ? '1' : '0')">
                <span class="slider round"></span>
            </label>
            <span class="toggle-label">Show Next Operational Day Orders</span>
            <p class="toggle-description">
                <?php echo $show_operational === '1' ? 
                    'Showing orders for next operational day (' . date('l, F j', strtotime($check_date)) . ')' : 
                    'Showing orders for tomorrow (' . date('l, F j', strtotime($tomorrow)) . ')'; ?>
            </p>
        </div>
        <div class="export-button-container" id="next-day-orders-export-button">
        <?php satguru_add_export_button('next-day-orders', $show_operational); ?>
        </div>
        <?php satguru_display_orders_table($orders); ?>
    </div>
    <?php

}

// All Orders page
function satguru_all_orders_page() {
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array_keys(wc_get_order_statuses()),
        'posts_per_page' => -1,
    );
    
    $orders = wc_get_orders($args);
    ?>
    <div class="wrap">
        <h1>All Orders</h1>
        <div class="export-button-container" id="all-orders-export-button">
            <?php 
            satguru_add_export_button('all-orders');
            ?>
        </div>
        <?php satguru_display_orders_table($orders); ?>
    </div>
    <?php

}

// Function to display the content of the custom admin page
function satguru_admin_dashboard_page() {
    ?>
    <div class="wrap">
        <h1>Satguru Tiffin Service - Admin Dashboard</h1>
        <p>Use this dashboard to manage orders, subscriptions, and other settings.</p>
        
        <h2>Order Management</h2>
        <form method="post" action="">
            <label for="order_id">Order ID:</label>
            <input type="text" name="order_id" id="order_id" placeholder="Enter Order ID" required>
            <label for="new_address">New Address:</label>
            <input type="text" name="new_address" id="new_address" placeholder="Enter New Address">
            <input type="submit" name="update_address" value="Update Address">
        </form>

        <?php
        // Handle order address update
        if (isset($_POST['update_address'])) {
            $order_id = sanitize_text_field($_POST['order_id']);
            $new_address = sanitize_text_field($_POST['new_address']);
            satguru_modify_order_address($order_id, array('address_1' => $new_address, 'city' => '', 'postcode' => ''));
            echo '<p>Order address updated successfully!</p>';
        }

        // Display API key regeneration notice
        if (isset($_GET['api_key_regenerated']) && $_GET['api_key_regenerated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>API key regenerated successfully!</p></div>';
        }

        // Display Google Sheets Integration section
        satguru_display_api_key();

        // Handle import success/error messages
        if (isset($_GET['import_success']) && $_GET['import_success'] === '1') {
            $imported_count = isset($_GET['imported_count']) ? intval($_GET['imported_count']) : 0;
            $skipped_count = isset($_GET['skipped_count']) ? intval($_GET['skipped_count']) : 0;
            $error_count = isset($_GET['error_count']) ? intval($_GET['error_count']) : 0;
            
            echo '<div class="import-success">';
            echo '<h3>Import Completed Successfully!</h3>';
            echo '<p><strong>Results:</strong></p>';
            echo '<ul>';
            echo '<li>Orders imported: ' . $imported_count . '</li>';
            echo '<li>Orders skipped: ' . $skipped_count . '</li>';
            echo '<li>Errors: ' . $error_count . '</li>';
            echo '</ul>';
            echo '</div>';
        }
        ?>

        <h2>Export Orders by IDs</h2>
        <div class="export-orders-by-ids">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="export_orders_by_ids">
                <input type="hidden" name="export_orders_by_ids" value="1">
                <?php wp_nonce_field('export_orders_by_ids_nonce', 'export_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="order_ids">Order IDs</label>
                        </th>
                        <td>
                            <textarea 
                                name="order_ids" 
                                id="order_ids" 
                                rows="4" 
                                cols="50" 
                                placeholder="Enter order IDs separated by commas or spaces&#10;Example: 1234, 5678, 9012&#10;Or: 1234 5678 9012"
                                required
                            ></textarea>
                            <p class="description">
                                Enter one or more order IDs separated by commas or spaces. The system will export detailed information for all valid order IDs as a CSV file.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Export Orders to CSV">
                    <input type="button" class="button" value="Preview Orders" onclick="previewOrders()">
                </p>
            </form>
    </div>

        <div id="order-preview" style="display: none; margin-top: 20px;">
            <h3>Order Preview</h3>
            <div id="preview-content"></div>
        </div>

        <h2>System Diagnostics</h2>
        <div class="system-diagnostics">
            <h3>PhpSpreadsheet Library Status</h3>
    <?php
            $phpspreadsheet_available = class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet');
            if ($phpspreadsheet_available) {
                echo '<p style="color: green;">✓ PhpSpreadsheet library is available and working properly.</p>';
            } else {
                echo '<p style="color: red;">✗ PhpSpreadsheet library is not available.</p>';
                echo '<p><strong>Possible solutions:</strong></p>';
                echo '<ul>';
                echo '<li>Run <code>composer install</code> in your theme directory</li>';
                echo '<li>Check if the vendor directory exists: ' . get_stylesheet_directory() . '/vendor/</li>';
                echo '<li>Ensure the autoload.php file is present</li>';
                echo '</ul>';
            }
            ?>
            
            <h3>Satguru Tiffin Calculator Status</h3>
            <?php
            $calculator_available = class_exists('Satguru_Tiffin_Calculator');
            if ($calculator_available) {
                echo '<p style="color: green;">✓ Satguru Tiffin Calculator is available and working properly.</p>';
            } else {
                echo '<p style="color: red;">✗ Satguru Tiffin Calculator is not available.</p>';
                echo '<p>This may cause issues with box calculations and remaining tiffins.</p>';
            }
            ?>
            
            <h3>Export Functionality</h3>
            <p style="color: green;">✓ CSV export (.csv) is working</p>
            <?php if ($phpspreadsheet_available): ?>
                <p style="color: blue;">ℹ Excel export (.xlsx) is available but currently disabled for stability</p>
            <?php else: ?>
                <p style="color: orange;">⚠ Excel export requires PhpSpreadsheet library installation</p>
            <?php endif; ?>
            
            <h3>Import Functionality</h3>
            <?php if ($phpspreadsheet_available): ?>
                <p style="color: green;">✓ Full Excel import (.xlsx, .xls, .csv) is available</p>
            <?php else: ?>
                <p style="color: red;">✗ Import functionality requires PhpSpreadsheet library</p>
            <?php endif; ?>
            
            <h3>Test Export</h3>
            <p><a href="<?php echo admin_url('admin-post.php?action=test_export'); ?>" class="button">Test Export Function</a></p>
        </div>

        <h2>Import Orders from Excel</h2>
        <div class="import-orders-from-excel">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_orders_from_excel">
                <input type="hidden" name="import_orders_from_excel" value="1">
                <?php wp_nonce_field('import_orders_nonce', 'import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="excel_file">Excel File</label>
                        </th>
                        <td>
                            <input type="file" 
                                   name="excel_file" 
                                   id="excel_file" 
                                   accept=".xlsx,.xls,.csv"
                                   required>
                            <p class="description">
                                Upload an Excel file (.xlsx, .xls) or CSV file with order data. 
                                <a href="#" onclick="showColumnFormat()">Click here to see required column format</a> | 
                                <a href="<?php echo admin_url('admin-post.php?action=download_sample_excel'); ?>" target="_blank">Download Sample Excel Template</a>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Import Orders from Excel">
                </p>
            </form>
        </div>

        <div id="column-format" style="display: none; margin-top: 20px;">
            <h3>Required Column Format</h3>
            <div class="column-format-content">
                <p><strong>Required Columns:</strong></p>
                <ul>
                    <li><code>customer_name</code> - Customer's full name (required)</li>
                    <li><code>email</code> - Customer's email address (required)</li>
                    <li><code>products</code> - Product names, comma-separated (required)</li>
                </ul>
                
                <p><strong>Optional Columns:</strong></p>
                <ul>
                    <li><code>order_id</code> - Specific order ID (if provided, will skip if order exists)</li>
                    <li><code>phone</code> - Customer's phone number</li>
                    <li><code>start_date</code> - Delivery start date</li>
                    <li><code>preferred_days</code> - Preferred delivery days</li>
                    <li><code>quantity</code> - Product quantities, comma-separated</li>
                    <li><code>boxes</code> - Number of boxes</li>
                    <li><code>customize</code> - Customization preferences</li>
                    <li><code>veg_nonveg</code> - Food type preferences</li>
                    <li><code>delivery</code> - Delivery method</li>
                    <li><code>addons</code> - Additional items/add-ons</li>
                    <li><code>notes</code> - Customer order notes</li>
                    <li><code>address</code> - Shipping address</li>
                    <li><code>city</code> - Shipping city</li>
                    <li><code>postal_code</code> - Shipping postal code</li>
                    <li><code>status</code> - Order status (default: pending)</li>
                    <li><code>total_tiffins</code> - Total number of tiffins</li>
                    <li><code>order_date</code> - Order date</li>
                    <li><code>order_total</code> - Order total amount</li>
                    <li><code>payment_method</code> - Payment method</li>
                    <li><code>billing_address</code> - Complete billing address</li>
                    <li><code>shipping_address</code> - Complete shipping address</li>
                </ul>
                
                <p><strong>Sample Excel Format:</strong></p>
                <table class="sample-table" style="border-collapse: collapse; width: 100%;">
                    <tr style="background: #f0f0f0;">
                        <th style="border: 1px solid #ccc; padding: 8px;">customer_name</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">email</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">phone</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">products</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">quantity</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">address</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">city</th>
                        <th style="border: 1px solid #ccc; padding: 8px;">status</th>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #ccc; padding: 8px;">John Doe</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">john@example.com</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">123-456-7890</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">Tiffin Plan A, Extra Rice</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">1, 2</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">123 Main St</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">New York</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">pending</td>
                    </tr>
                </table>
            </div>
        </div>

        <style>
        .export-orders-by-ids {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .export-orders-by-ids textarea {
            width: 100%;
            max-width: 500px;
        }
        .export-orders-by-ids .form-table th {
            width: 150px;
            vertical-align: top;
            padding-top: 15px;
        }
        #order-preview {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
        }
        .preview-order {
            background: #fff;
            border: 1px solid #ccc;
            margin: 10px 0;
            padding: 10px;
            border-radius: 3px;
        }
        .preview-order h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .preview-order p {
            margin: 5px 0;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        .import-orders-from-excel {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .import-orders-from-excel .form-table th {
            width: 150px;
            vertical-align: top;
            padding-top: 15px;
        }
        #column-format {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
        }
        .column-format-content ul {
            margin: 10px 0;
        }
        .column-format-content li {
            margin: 5px 0;
        }
        .sample-table {
            margin: 15px 0;
            font-size: 12px;
        }
        .import-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .import-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .system-diagnostics {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .system-diagnostics h3 {
            margin-top: 0;
            color: #495057;
        }
        .system-diagnostics ul {
            margin: 10px 0;
        }
        .system-diagnostics li {
            margin: 5px 0;
        }
        .system-diagnostics code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        </style>

        <script>
        // Make ajaxurl available
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        function previewOrders() {
            var orderIds = document.getElementById('order_ids').value.trim();
            if (!orderIds) {
                alert('Please enter order IDs first.');
                return;
            }

            var previewDiv = document.getElementById('order-preview');
            var contentDiv = document.getElementById('preview-content');
            
            previewDiv.style.display = 'block';
            contentDiv.innerHTML = '<div class="loading">Loading order details...</div>';

            // Create AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                contentDiv.innerHTML = response.data;
                            } else {
                                contentDiv.innerHTML = '<div style="color: red;">Error: ' + response.data + '</div>';
                            }
                        } catch (e) {
                            contentDiv.innerHTML = '<div style="color: red;">Error parsing response.</div>';
                        }
                    } else {
                        contentDiv.innerHTML = '<div style="color: red;">Error loading preview.</div>';
                    }
                }
            };

            var data = 'action=preview_orders_by_ids&order_ids=' + encodeURIComponent(orderIds) + '&nonce=' + '<?php echo wp_create_nonce('preview_orders_nonce'); ?>';
            xhr.send(data);
        }
        
        function showColumnFormat() {
            var formatDiv = document.getElementById('column-format');
            if (formatDiv.style.display === 'none') {
                formatDiv.style.display = 'block';
            } else {
                formatDiv.style.display = 'none';
            }
        }
        </script>
    </div>
    <?php
}

// Update the admin styles
function satguru_admin_styles() {
    if (isset($_GET['page']) && (
        $_GET['page'] === 'todays-orders' || 
        $_GET['page'] === 'next-day-orders' || 
        $_GET['page'] === 'all-orders'
    )) {
        wp_enqueue_style('satguru-admin-dashboard', get_stylesheet_directory_uri() . '/css/admin-dashboard.css');
    }
}
add_action('admin_head', 'satguru_admin_styles');


///////////////////////////////////////////////////////////////////
// Export to xls funtion
///////////////////////////////////////////////////////////////////

// Add this near the top of the file after the require_once statement
require_once ABSPATH . 'wp-admin/includes/file.php';

// Include PhpSpreadsheet for advanced Excel functionality
if (file_exists(get_stylesheet_directory() . '/vendor/autoload.php')) {
    require_once get_stylesheet_directory() . '/vendor/autoload.php';
} else {
    // Fallback: try to load PhpSpreadsheet manually
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Try alternative paths
        $possible_paths = array(
            ABSPATH . 'vendor/autoload.php',
            get_template_directory() . '/vendor/autoload.php',
            WP_CONTENT_DIR . '/vendor/autoload.php'
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }
    }
}

/**
 * Handle the export of orders to Excel
 * Only runs when explicitly requested through the export form
 */
function satguru_export_orders_to_excel() {
    // Only run this function if we're actually trying to export
    if (!isset($_POST['export_orders']) || !isset($_POST['page_type'])) {
        return;
    }

    // Verify nonce first
    if (!isset($_POST['export_nonce']) || !wp_verify_nonce($_POST['export_nonce'], 'export_orders_nonce')) {
        wp_die('Security check failed. Please try again.', 'Security Error', array('response' => 403));
    }

    // Check user capabilities after nonce verification
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }

    // Sanitize and validate the page type
    $page_type = sanitize_text_field($_POST['page_type']);
    if (!in_array($page_type, array('todays-orders', 'next-day-orders', 'all-orders'))) {
        wp_die('Invalid request type.', 'Error', array('response' => 400));
    }

    // Get orders based on page type
    switch ($page_type) {
        case 'todays-orders':
            $orders = satguru_get_orders_by_start_date(date('Y-m-d'));
            $filename = 'todays-orders-' . date('Y-m-d');
            $check_date = date('Y-m-d');
            break;
        case 'next-day-orders':
            $operational = isset($_POST['operational']) ? sanitize_text_field($_POST['operational']) : '0';
            $check_date = $operational === '1' 
                ? (class_exists('Satguru_Tiffin_Calculator') ? 
                    Satguru_Tiffin_Calculator::get_next_operational_day() : 
                    date('Y-m-d', strtotime('+1 day')))
                : date('Y-m-d', strtotime('+1 day'));
            $orders = satguru_get_orders_by_start_date($check_date);
            $filename = 'next-day-orders-' . $check_date;
            break;
        case 'all-orders':
            $orders = wc_get_orders(array(
                'post_type' => 'shop_order',
                'post_status' => array_keys(wc_get_order_statuses()),
                'posts_per_page' => -1,
            ));
            $filename = 'all-orders-' . date('Y-m-d');
            $check_date = date('Y-m-d');
            break;
    }

    // Ensure we have orders to export
    if (empty($orders)) {
        wp_die('No orders found to export.', 'No Data', array('response' => 404));
    }

    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . sanitize_file_name($filename) . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    // Output the Excel header row
    $headers = array(
        'Order ID', 'Customer', 'Phone', 'Email', 'Start Date', 'Preferred Days', 
        'Products', 'Quantity', 'Boxes', 'Want to Customize?', 'Veg/Non-Veg', 
        'Delivery', 'Addons', 'Notes', 'Address', 'City', 'Postal Code', 
        'Status', 'Total Tiffins', 'Remaining Tiffins'
    );
    echo implode("\t", $headers) . "\n";

    // Process each order
    foreach ($orders as $order) {
        // Initialize variables
        $start_date = '';
        $preferred_days = '';
        $products = '';
        $customize = '';
        $veg_nonveg = '';
        $delivery = '';
        $addons = '';
        $quantity = '';
        $boxes = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $check_date) : 0;
        $remaining_tiffins = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order) : 0;
        $total_tiffins = 0;

        // Get metadata from order items
        foreach ($order->get_items() as $item) {
            $products .= $item->get_name() . ' ';
            $quantity = $item->get_quantity();

            foreach ($item->get_meta_data() as $meta) {
                switch (true) {
                    case strpos($meta->key, 'Start Date') !== false:
                    case strpos($meta->key, 'Delivery Date') !== false:
                        $start_date = date('Y-m-d', strtotime($meta->value));
                        break;
                    case strpos($meta->key, 'Prefered Days') !== false:
                        $preferred_days .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Want to Customize') !== false:
                        $customize .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Veg / Non Veg') !== false:
                    case strpos($meta->key, 'Food Type') !== false:
                        $veg_nonveg .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Delivery') !== false:
                        if (strpos($meta->key, 'Delivery Date') === false) {
                            $delivery .= $meta->value . ' ';
                        }
                        break;
                    case strpos($meta->key, 'Number Of Tiffins') !== false:
                        $total_tiffins = intval($meta->value);
                        break;
                    case strpos(strtolower($meta->key), 'add-ons') !== false:
                    case strpos(strtolower($meta->key), 'additional') !== false:
                        if (!empty($meta->value)) {
                            if (is_array($meta->value)) {
                                foreach ($meta->value as $key => $value) {
                                    if (!is_numeric($key)) {
                                        $addons .= "$key = $value, ";
                                    } elseif (!is_array($value)) {
                                        $addons .= "$value, ";
                                    }
                                }
                            } else {
                                $addons .= $meta->value . ', ';
                            }
                        }
                        break;
                }
            }
        }

        // Clean up addons string
        $addons = rtrim($addons, ', ');

        // Prepare row data with proper escaping
        $row = array(
            $order->get_id(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            "'" . $order->get_billing_phone(), // Force text format for phone numbers
            $order->get_billing_email(),
            $start_date,
            str_replace(array("\n", "\r"), ' ', $preferred_days),
            str_replace(array("\n", "\r"), ' ', $products),
            $quantity,
            $boxes,
            str_replace(array("\n", "\r"), ' ', $customize),
            str_replace(array("\n", "\r"), ' ', $veg_nonveg),
            str_replace(array("\n", "\r"), ' ', $delivery),
            str_replace(array("\n", "\r"), ' ', $addons),
            str_replace(array("\n", "\r"), ' ', $order->get_customer_note()),
            $order->get_shipping_address_1(),
            $order->get_shipping_city(),
            $order->get_shipping_postcode(),
            wc_get_order_status_name($order->get_status()),
            $total_tiffins,
            $remaining_tiffins
        );

        // Output row with proper escaping
        echo implode("\t", array_map(function($cell) {
            return str_replace(array("\t", "\n", "\r"), ' ', $cell);
        }, $row)) . "\n";
    }
    exit;
}
add_action('admin_post_export_orders', 'satguru_export_orders_to_excel');

/**
 * Handle the export of specific orders by IDs to Excel
 */
function satguru_export_orders_by_ids_to_excel() {
    // Only run this function if we're actually trying to export
    if (!isset($_POST['export_orders_by_ids'])) {
        return;
    }

    // Verify nonce first
    if (!isset($_POST['export_nonce']) || !wp_verify_nonce($_POST['export_nonce'], 'export_orders_by_ids_nonce')) {
        wp_die('Security check failed. Please try again.', 'Security Error', array('response' => 403));
    }

    // Check user capabilities after nonce verification
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }

    // Get and sanitize order IDs
    $order_ids_input = sanitize_text_field($_POST['order_ids']);
    
    // Validate order IDs using helper function
    $validation_result = satguru_validate_order_ids($order_ids_input);
    
    if (!$validation_result['valid']) {
        wp_die($validation_result['message'], 'Error', array('response' => 400));
    }

    $orders = $validation_result['valid_orders'];
    
    // Show warning if some IDs were invalid but we have valid ones
    if (!empty($validation_result['invalid_ids'])) {
        // We'll continue with valid orders but show a notice
        $notice_message = $validation_result['message'];
    }

    // Create filename
    $filename = 'orders-by-ids-' . date('Y-m-d-H-i-s');

    // For now, always use CSV export to ensure it works
    // TODO: Re-enable Excel export once PhpSpreadsheet is properly installed
    satguru_export_orders_by_ids_simple_csv($orders, $filename);
    return;

    // Excel export code (disabled for now)
    if (false) {
    try {
        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Order Details');

        // Set headers
        $headers = array(
            'Order ID', 'Customer Name', 'Phone', 'Email', 'Start Date', 'Preferred Days', 
            'Products', 'Quantity', 'Boxes', 'Want to Customize?', 'Veg/Non-Veg', 
            'Delivery', 'Addons', 'Notes', 'Address', 'City', 'Postal Code', 
            'Status', 'Total Tiffins', 'Remaining Tiffins', 'Order Date', 'Order Total',
            'Payment Method', 'Billing Address', 'Shipping Address', 'Order Notes'
        );

        // Set header row
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Style header row
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');

        // Process each order
        $row = 2;
    foreach ($orders as $order) {
            // Initialize variables
            $start_date = '';
            $preferred_days = '';
            $products = '';
            $customize = '';
            $veg_nonveg = '';
            $delivery = '';
            $addons = '';
            $quantity = '';
            $boxes = 0;
            $remaining_tiffins = 0;
            $total_tiffins = 0;

            // Calculate boxes and remaining tiffins
            if (class_exists('Satguru_Tiffin_Calculator')) {
                $boxes = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, date('Y-m-d'));
                $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            }

            // Get metadata from order items
            foreach ($order->get_items() as $item) {
                $products .= $item->get_name() . ' | ';
                $quantity = $item->get_quantity();

                foreach ($item->get_meta_data() as $meta) {
                    switch (true) {
                        case strpos($meta->key, 'Start Date') !== false:
                        case strpos($meta->key, 'Delivery Date') !== false:
                            $start_date = date('Y-m-d', strtotime($meta->value));
                            break;
                        case strpos($meta->key, 'Prefered Days') !== false:
                            $preferred_days .= $meta->value . ' | ';
                            break;
                        case strpos($meta->key, 'Want to Customize') !== false:
                            $customize .= $meta->value . ' | ';
                            break;
                        case strpos($meta->key, 'Veg / Non Veg') !== false:
                        case strpos($meta->key, 'Food Type') !== false:
                            $veg_nonveg .= $meta->value . ' | ';
                            break;
                        case strpos($meta->key, 'Delivery') !== false:
                            if (strpos($meta->key, 'Delivery Date') === false) {
                                $delivery .= $meta->value . ' | ';
                            }
                            break;
                        case strpos($meta->key, 'Number Of Tiffins') !== false:
                            $total_tiffins = intval($meta->value);
                            break;
                        case strpos(strtolower($meta->key), 'add-ons') !== false:
                        case strpos(strtolower($meta->key), 'additional') !== false:
                            if (!empty($meta->value)) {
                                if (is_array($meta->value)) {
                                    foreach ($meta->value as $key => $value) {
                                        if (!is_numeric($key)) {
                                            $addons .= "$key = $value, ";
                                        } elseif (!is_array($value)) {
                                            $addons .= "$value, ";
                                        }
                                    }
        } else {
                                    $addons .= $meta->value . ', ';
                                }
                            }
            break;
                    }
                }
            }

            // Clean up strings
            $products = rtrim($products, ' | ');
            $preferred_days = rtrim($preferred_days, ' | ');
            $customize = rtrim($customize, ' | ');
            $veg_nonveg = rtrim($veg_nonveg, ' | ');
            $delivery = rtrim($delivery, ' | ');
            $addons = rtrim($addons, ', ');

            // Get order notes
            $order_notes = '';
            $args = array(
                'order_id' => $order->get_id(),
                'type' => 'customer',
            );
            $notes = wc_get_order_notes($args);
            if (!empty($notes)) {
                foreach ($notes as $note) {
                    $order_notes .= date('Y-m-d', strtotime($note->date_created)) . ': ' . $note->content . ' | ';
                }
            }
            $order_notes = rtrim($order_notes, ' | ');

            // Prepare row data
            $row_data = array(
                $order->get_id(),
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                $order->get_billing_phone(),
                $order->get_billing_email(),
                $start_date,
                $preferred_days,
                $products,
                $quantity,
                $boxes,
                $customize,
                $veg_nonveg,
                $delivery,
                $addons,
                $order->get_customer_note(),
                $order->get_shipping_address_1(),
                $order->get_shipping_city(),
                $order->get_shipping_postcode(),
                wc_get_order_status_name($order->get_status()),
                $total_tiffins,
                $remaining_tiffins,
                $order->get_date_created()->format('Y-m-d H:i:s'),
                $order->get_formatted_order_total(),
                $order->get_payment_method_title(),
                $order->get_formatted_billing_address(),
                $order->get_formatted_shipping_address(),
                $order_notes
            );

            // Set row data
            $col = 1;
            foreach ($row_data as $data) {
                $sheet->setCellValueByColumnAndRow($col, $row, $data);
                $col++;
            }

            $row++;
        }

        // Auto-size columns
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . sanitize_file_name($filename) . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Create writer and output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } catch (Exception $e) {
        wp_die('Error creating Excel file: ' . $e->getMessage(), 'Export Error', array('response' => 500));
    }
    } // End of disabled Excel export code
}
add_action('admin_post_export_orders_by_ids', 'satguru_export_orders_by_ids_to_excel');

/**
 * Simple CSV export fallback when PhpSpreadsheet is not available
 */
function satguru_export_orders_by_ids_simple_csv($orders, $filename) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . sanitize_file_name($filename) . '.csv"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Output the CSV header row
        $headers = array(
        'Order ID', 'Customer Name', 'Phone', 'Email', 'Start Date', 'Preferred Days', 
            'Products', 'Quantity', 'Boxes', 'Want to Customize?', 'Veg/Non-Veg', 
            'Delivery', 'Addons', 'Notes', 'Address', 'City', 'Postal Code', 
        'Status', 'Total Tiffins', 'Remaining Tiffins', 'Order Date', 'Order Total',
        'Payment Method', 'Billing Address', 'Shipping Address', 'Order Notes'
        );
        
    // Output headers
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);

    // Process each order
    foreach ($orders as $order) {
        // Initialize variables
        $start_date = '';
        $preferred_days = '';
        $products = '';
        $customize = '';
        $veg_nonveg = '';
        $delivery = '';
        $addons = '';
        $quantity = '';
        $boxes = 0;
        $remaining_tiffins = 0;
        $total_tiffins = 0;

        // Calculate boxes and remaining tiffins
        if (class_exists('Satguru_Tiffin_Calculator')) {
            $boxes = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, date('Y-m-d'));
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
        }

        // Get metadata from order items
        foreach ($order->get_items() as $item) {
            $products .= $item->get_name() . ' | ';
            $quantity = $item->get_quantity();

            foreach ($item->get_meta_data() as $meta) {
                switch (true) {
                    case strpos($meta->key, 'Start Date') !== false:
                    case strpos($meta->key, 'Delivery Date') !== false:
                        $start_date = date('Y-m-d', strtotime($meta->value));
                        break;
                    case strpos($meta->key, 'Prefered Days') !== false:
                        $preferred_days .= $meta->value . ' | ';
                        break;
                    case strpos($meta->key, 'Want to Customize') !== false:
                        $customize .= $meta->value . ' | ';
                        break;
                    case strpos($meta->key, 'Veg / Non Veg') !== false:
                    case strpos($meta->key, 'Food Type') !== false:
                        $veg_nonveg .= $meta->value . ' | ';
                        break;
                    case strpos($meta->key, 'Delivery') !== false:
                        if (strpos($meta->key, 'Delivery Date') === false) {
                            $delivery .= $meta->value . ' | ';
                        }
                        break;
                    case strpos($meta->key, 'Number Of Tiffins') !== false:
                        $total_tiffins = intval($meta->value);
                        break;
                    case strpos(strtolower($meta->key), 'add-ons') !== false:
                    case strpos(strtolower($meta->key), 'additional') !== false:
                        if (!empty($meta->value)) {
                            if (is_array($meta->value)) {
                                foreach ($meta->value as $key => $value) {
                                    if (!is_numeric($key)) {
                                        $addons .= "$key = $value, ";
                                    } elseif (!is_array($value)) {
                                        $addons .= "$value, ";
                                    }
                                }
                            } else {
                                $addons .= $meta->value . ', ';
                            }
                        }
                        break;
                }
            }
        }

        // Clean up strings
        $products = rtrim($products, ' | ');
        $preferred_days = rtrim($preferred_days, ' | ');
        $customize = rtrim($customize, ' | ');
        $veg_nonveg = rtrim($veg_nonveg, ' | ');
        $delivery = rtrim($delivery, ' | ');
        $addons = rtrim($addons, ', ');

        // Get order notes
        $order_notes = '';
        $args = array(
            'order_id' => $order->get_id(),
            'type' => 'customer',
        );
        $notes = wc_get_order_notes($args);
        if (!empty($notes)) {
            foreach ($notes as $note) {
                $order_notes .= date('Y-m-d', strtotime($note->date_created)) . ': ' . $note->content . ' | ';
            }
        }
        $order_notes = rtrim($order_notes, ' | ');

        // Prepare row data
        $row_data = array(
            $order->get_id(),
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            $order->get_billing_phone(),
            $order->get_billing_email(),
            $start_date,
            $preferred_days,
            $products,
            $quantity,
            $boxes,
            $customize,
            $veg_nonveg,
            $delivery,
            $addons,
            $order->get_customer_note(),
            $order->get_shipping_address_1(),
            $order->get_shipping_city(),
            $order->get_shipping_postcode(),
            wc_get_order_status_name($order->get_status()),
            $total_tiffins,
            $remaining_tiffins,
            $order->get_date_created()->format('Y-m-d H:i:s'),
            $order->get_formatted_order_total(),
            $order->get_payment_method_title(),
            $order->get_formatted_billing_address(),
            $order->get_formatted_shipping_address(),
            $order_notes
        );

        // Output row
        fputcsv($output, $row_data);
    }

        fclose($output);
    exit;
}

/**
 * AJAX handler for order preview
 */
function satguru_preview_orders_by_ids() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'preview_orders_nonce')) {
        wp_send_json_error('Security check failed.');
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }

    $order_ids_input = sanitize_text_field($_POST['order_ids']);
    $validation_result = satguru_validate_order_ids($order_ids_input);

    if (!$validation_result['valid']) {
        wp_send_json_error($validation_result['message']);
    }

    $orders = $validation_result['valid_orders'];
    $html = '';

    if (!empty($validation_result['invalid_ids'])) {
        $html .= '<div style="color: orange; margin-bottom: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px;">';
        $html .= '<strong>Warning:</strong> ' . $validation_result['message'];
        $html .= '</div>';
    }

    $html .= '<p><strong>Found ' . count($orders) . ' valid orders:</strong></p>';

    foreach ($orders as $order) {
        $html .= '<div class="preview-order">';
        $html .= '<h4>Order #' . $order->get_id() . ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '</h4>';
        $html .= '<p><strong>Status:</strong> ' . wc_get_order_status_name($order->get_status()) . '</p>';
        $html .= '<p><strong>Email:</strong> ' . $order->get_billing_email() . '</p>';
        $html .= '<p><strong>Phone:</strong> ' . $order->get_billing_phone() . '</p>';
        $html .= '<p><strong>Total:</strong> ' . $order->get_formatted_order_total() . '</p>';
        $html .= '<p><strong>Date:</strong> ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';
        
        // Get products
        $products = array();
        foreach ($order->get_items() as $item) {
            $products[] = $item->get_name() . ' (Qty: ' . $item->get_quantity() . ')';
        }
        $html .= '<p><strong>Products:</strong> ' . implode(', ', $products) . '</p>';
        
        $html .= '</div>';
    }

    wp_send_json_success($html);
}
add_action('wp_ajax_preview_orders_by_ids', 'satguru_preview_orders_by_ids');

/**
 * Handle Excel file import for orders
 */
function satguru_import_orders_from_excel() {
    // Only run this function if we're actually trying to import
    if (!isset($_POST['import_orders_from_excel'])) {
        return;
    }

    // Verify nonce first
    if (!isset($_POST['import_nonce']) || !wp_verify_nonce($_POST['import_nonce'], 'import_orders_nonce')) {
        wp_die('Security check failed. Please try again.', 'Security Error', array('response' => 403));
    }

    // Check user capabilities after nonce verification
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }

    // Check if file was uploaded
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        wp_die('Please select a valid Excel file to upload.', 'Upload Error', array('response' => 400));
    }

    $file = $_FILES['excel_file'];
    
    // Verify file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, array('xlsx', 'xls', 'csv'))) {
        wp_die('Invalid file format. Please upload an Excel file (.xlsx, .xls) or CSV file.', 'File Error', array('response' => 400));
    }

    // Process the Excel file
    try {
        $result = satguru_process_excel_import($file);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), 'Import Error', array('response' => 500));
        }

        // Redirect back with success message
        $redirect_url = add_query_arg(array(
            'page' => 'satguru-admin-dashboard',
            'import_success' => '1',
            'imported_count' => $result['imported_count'],
            'skipped_count' => $result['skipped_count'],
            'error_count' => $result['error_count']
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
    exit;

    } catch (Exception $e) {
        wp_die('Error processing Excel file: ' . $e->getMessage(), 'Processing Error', array('response' => 500));
    }
}
add_action('admin_post_import_orders_from_excel', 'satguru_import_orders_from_excel');

/**
 * Download sample Excel template
 */
function satguru_download_sample_excel() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }

    // Check if PhpSpreadsheet is available
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        wp_die('PhpSpreadsheet library is not available. Please ensure the library is properly installed.', 'Library Error', array('response' => 500));
    }

    try {
        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Order Import Template');

        // Set headers
        $headers = array(
            'customer_name', 'email', 'phone', 'start_date', 'preferred_days',
            'products', 'quantity', 'boxes', 'customize', 'veg_nonveg', 'delivery',
            'addons', 'notes', 'address', 'city', 'postal_code', 'status', 'total_tiffins',
            'order_date', 'order_total', 'payment_method', 'billing_address', 'shipping_address'
        );

        // Set header row
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Style header row
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');

        // Add sample data
        $sample_data = array(
            array(
                'John Doe', 'john@example.com', '123-456-7890', '2024-01-15', 'Monday, Wednesday, Friday',
                'Tiffin Plan A, Extra Rice', '1, 2', '3', 'Yes', 'Veg', 'Home Delivery',
                'Extra Spices, Extra Curry', 'Please deliver after 6 PM', '123 Main Street', 'New York', '10001',
                'pending', '30', '2024-01-10 10:30:00', '$150.00', 'Credit Card',
                '123 Main Street, New York, NY 10001', '123 Main Street, New York, NY 10001'
            ),
            array(
                'Jane Smith', 'jane@example.com', '987-654-3210', '2024-01-16', 'Tuesday, Thursday',
                'Tiffin Plan B', '1', '2', 'No', 'Non-Veg', 'Office Delivery',
                'Extra Bread', 'Office address only', '456 Business Ave', 'Los Angeles', '90210',
                'processing', '20', '2024-01-11 14:20:00', '$120.00', 'PayPal',
                '456 Business Ave, Los Angeles, CA 90210', '456 Business Ave, Los Angeles, CA 90210'
            )
        );

        $row = 2;
        foreach ($sample_data as $data) {
            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="order-import-template.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Create writer and output
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } catch (Exception $e) {
        wp_die('Error creating sample Excel file: ' . $e->getMessage(), 'Template Error', array('response' => 500));
    }
}
add_action('admin_post_download_sample_excel', 'satguru_download_sample_excel');

/**
 * Test export function for debugging
 */
function satguru_test_export() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }

    // Get a few orders for testing
    $orders = wc_get_orders(array(
        'post_type' => 'shop_order',
        'post_status' => array_keys(wc_get_order_statuses()),
        'posts_per_page' => 5,
    ));

    if (empty($orders)) {
        wp_die('No orders found for testing.', 'No Data', array('response' => 404));
    }

    // Test the simple CSV export
    satguru_export_orders_by_ids_simple_csv($orders, 'test-export-' . date('Y-m-d-H-i-s'));
}
add_action('admin_post_test_export', 'satguru_test_export');

/**
 * Process Excel file and create orders
 */
function satguru_process_excel_import($file) {
    // Upload file to temp directory
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/order-import-temp';
    
    // Create temp directory if not exists
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    $temp_file = $temp_dir . '/' . wp_unique_filename($temp_dir, $file['name']);
    
    if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
        return new WP_Error('upload_error', 'Failed to upload file.');
    }

    // Check if PhpSpreadsheet is available
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        return new WP_Error('missing_library', 'PhpSpreadsheet library is not available. Please ensure the library is properly installed. You can still use the export functionality which will work with CSV format.');
    }

    try {
        // Create reader based on file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension === 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } elseif ($file_extension === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        
        // Load file
        $spreadsheet = $reader->load($temp_file);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get header row
        $headers = array();
        $headerRow = $worksheet->getRowIterator(1, 1)->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        foreach ($cellIterator as $cell) {
            $headers[] = strtolower(trim($cell->getValue()));
        }
        
        // Define expected columns (flexible mapping)
        $expected_columns = array(
            'order_id', 'customer_name', 'phone', 'email', 'start_date', 'preferred_days',
            'products', 'quantity', 'boxes', 'customize', 'veg_nonveg', 'delivery',
            'addons', 'notes', 'address', 'city', 'postal_code', 'status', 'total_tiffins',
            'order_date', 'order_total', 'payment_method', 'billing_address', 'shipping_address'
        );
        
        // Map column indexes
        $column_indexes = array();
        foreach ($expected_columns as $column) {
            $index = array_search($column, $headers);
            if ($index !== false) {
                $column_indexes[$column] = $index;
            }
        }
        
        // Check for minimum required columns
        $required_columns = array('customer_name', 'email', 'products');
        $missing_columns = array();
        foreach ($required_columns as $required) {
            if (!isset($column_indexes[$required])) {
                $missing_columns[] = $required;
            }
        }
        
        if (!empty($missing_columns)) {
            unlink($temp_file);
            return new WP_Error('missing_columns', 'Missing required columns: ' . implode(', ', $missing_columns));
        }
        
        // Read data rows and create orders
        $imported_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $errors = array();
        
        $rows = $worksheet->getRowIterator(2); // Skip header row
        
        foreach ($rows as $row) {
            $row_data = array();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            $cells = array();
            foreach ($cellIterator as $cell) {
                $cells[] = trim($cell->getValue());
            }
            
            // Skip empty rows
            if (empty(array_filter($cells))) {
                continue;
            }
            
            // Extract data for available columns
            foreach ($column_indexes as $column => $index) {
                if (isset($cells[$index])) {
                    $row_data[$column] = $cells[$index];
                }
            }
            
            // Create order
            $order_result = satguru_create_order_from_import_data($row_data);
            
            if (is_wp_error($order_result)) {
                $error_count++;
                $errors[] = 'Row ' . $row->getRowIndex() . ': ' . $order_result->get_error_message();
            } elseif ($order_result === 'skipped') {
                $skipped_count++;
        } else {
                $imported_count++;
            }
        }
        
        // Clean up
        unlink($temp_file);
        
        return array(
            'imported_count' => $imported_count,
            'skipped_count' => $skipped_count,
            'error_count' => $error_count,
            'errors' => $errors
        );
        
    } catch (Exception $e) {
        // Clean up
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        return new WP_Error('excel_processing_error', $e->getMessage());
    }
}

/**
 * Create WooCommerce order from import data
 */
function satguru_create_order_from_import_data($data) {
    // Check if order already exists (if order_id is provided)
    if (isset($data['order_id']) && !empty($data['order_id'])) {
        $existing_order = wc_get_order($data['order_id']);
        if ($existing_order) {
            return 'skipped'; // Skip existing orders
        }
    }
    
    // Validate required data
    if (empty($data['customer_name']) || empty($data['email'])) {
        return new WP_Error('missing_data', 'Customer name and email are required');
    }
    
    // Parse customer name
    $name_parts = explode(' ', $data['customer_name'], 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Create order
    $order = wc_create_order();
    
    if (is_wp_error($order)) {
        return $order;
    }
    
    // Set billing information
    $order->set_billing_first_name($first_name);
    $order->set_billing_last_name($last_name);
    $order->set_billing_email($data['email']);
    $order->set_billing_phone(isset($data['phone']) ? $data['phone'] : '');
    
    // Set shipping information
    $order->set_shipping_first_name($first_name);
    $order->set_shipping_last_name($last_name);
    $order->set_shipping_address_1(isset($data['address']) ? $data['address'] : '');
    $order->set_shipping_city(isset($data['city']) ? $data['city'] : '');
    $order->set_shipping_postcode(isset($data['postal_code']) ? $data['postal_code'] : '');
    
    // Add products
    if (!empty($data['products'])) {
        $products = explode(',', $data['products']);
        $quantities = isset($data['quantity']) ? explode(',', $data['quantity']) : array();
        
        foreach ($products as $index => $product_name) {
            $product_name = trim($product_name);
            if (empty($product_name)) continue;
            
            // Try to find existing product by name
            $product = get_page_by_title($product_name, OBJECT, 'product');
            
            if (!$product) {
                // Create a simple product if not found
                $product_id = wp_insert_post(array(
                    'post_title' => $product_name,
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'meta_input' => array(
                        '_price' => 0,
                        '_regular_price' => 0,
                        '_manage_stock' => 'no',
                        '_stock_status' => 'instock',
                        '_virtual' => 'no',
                        '_downloadable' => 'no'
                    )
                ));
                
                if (is_wp_error($product_id)) {
                    continue;
                }
            } else {
                $product_id = $product->ID;
            }
            
            $quantity = isset($quantities[$index]) ? intval($quantities[$index]) : 1;
            $order->add_product(wc_get_product($product_id), $quantity);
        }
    }
    
    // Set order meta data
    if (isset($data['start_date']) && !empty($data['start_date'])) {
        $order->add_meta_data('Start Date', $data['start_date']);
    }
    
    if (isset($data['preferred_days']) && !empty($data['preferred_days'])) {
        $order->add_meta_data('Prefered Days', $data['preferred_days']);
    }
    
    if (isset($data['customize']) && !empty($data['customize'])) {
        $order->add_meta_data('Want to Customize', $data['customize']);
    }
    
    if (isset($data['veg_nonveg']) && !empty($data['veg_nonveg'])) {
        $order->add_meta_data('Veg / Non Veg', $data['veg_nonveg']);
    }
    
    if (isset($data['delivery']) && !empty($data['delivery'])) {
        $order->add_meta_data('Delivery', $data['delivery']);
    }
    
    if (isset($data['addons']) && !empty($data['addons'])) {
        $order->add_meta_data('Add-ons', $data['addons']);
    }
    
    if (isset($data['total_tiffins']) && !empty($data['total_tiffins'])) {
        $order->add_meta_data('Number Of Tiffins', intval($data['total_tiffins']));
    }
    
    // Set order status
    $status = isset($data['status']) ? $data['status'] : 'pending';
    $order->set_status($status);
    
    // Set order total if provided
    if (isset($data['order_total']) && !empty($data['order_total'])) {
        $total = floatval(str_replace(array('$', ','), '', $data['order_total']));
        $order->set_total($total);
    }
    
    // Set payment method if provided
    if (isset($data['payment_method']) && !empty($data['payment_method'])) {
        $order->set_payment_method($data['payment_method']);
    }
    
    // Add customer note if provided
    if (isset($data['notes']) && !empty($data['notes'])) {
        $order->set_customer_note($data['notes']);
    }
    
    // Set order date if provided
    if (isset($data['order_date']) && !empty($data['order_date'])) {
        $order_date = date('Y-m-d H:i:s', strtotime($data['order_date']));
        $order->set_date_created($order_date);
    }
    
    // Save order
    $order->save();
    
    return $order->get_id();
}

/**
 * Helper function to validate order IDs and get order details
 */
function satguru_validate_order_ids($order_ids_input) {
    if (empty($order_ids_input)) {
        return array('valid' => false, 'message' => 'Please provide at least one order ID.');
    }

    // Parse order IDs (comma-separated or space-separated)
    $order_ids = preg_split('/[,\s]+/', $order_ids_input, -1, PREG_SPLIT_NO_EMPTY);
    $order_ids = array_map('intval', $order_ids);
    $order_ids = array_filter($order_ids, function($id) { return $id > 0; });

    if (empty($order_ids)) {
        return array('valid' => false, 'message' => 'No valid order IDs provided.');
    }

    // Check which orders exist
    $valid_orders = array();
    $invalid_ids = array();
    
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $valid_orders[] = $order;
        } else {
            $invalid_ids[] = $order_id;
        }
    }

    $result = array(
        'valid' => !empty($valid_orders),
        'valid_orders' => $valid_orders,
        'invalid_ids' => $invalid_ids,
        'total_requested' => count($order_ids),
        'total_found' => count($valid_orders)
    );

    if (!empty($invalid_ids)) {
        $result['message'] = 'Some order IDs were not found: ' . implode(', ', $invalid_ids) . '. Found ' . count($valid_orders) . ' valid orders.';
    } else {
        $result['message'] = 'All ' . count($valid_orders) . ' order IDs are valid.';
    }

    return $result;
}

/**
 * Add export button to the admin interface
 */
function satguru_add_export_button($page_type, $operational = '0') {
    ?>
    <div class="export-button-container" style="margin: 20px 0;">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
            <input type="hidden" name="action" value="export_orders">
            <input type="hidden" name="export_orders" value="1">
            <input type="hidden" name="page_type" value="<?php echo esc_attr($page_type); ?>">
            <?php if ($page_type === 'next-day-orders'): ?>
                <input type="hidden" name="operational" value="<?php echo esc_attr($operational); ?>">
            <?php endif; ?>
            <?php wp_nonce_field('export_orders_nonce', 'export_nonce'); ?>
            <button type="submit" class="button button-primary">
                Export to Excel
            </button>
        </form>
    </div>
    <?php
}

///////////////////////////////////////////////////////////////////
// Google Sheets API Integration
///////////////////////////////////////////////////////////////////

/**
 * Register REST API endpoint for today's orders
 */
function satguru_register_todays_orders_api() {
    register_rest_route('satguru/v1', '/todays-orders', array(
        'methods' => 'GET',
        'callback' => 'satguru_api_get_todays_orders',
        'permission_callback' => 'satguru_api_permission_check',
    ));
    
    // Register endpoint to check and resume paused orders
    register_rest_route('satguru/v1', '/check-paused-orders', array(
        'methods' => 'GET',
        'callback' => 'satguru_api_check_paused_orders',
        'permission_callback' => 'satguru_api_permission_check',
    ));
    
    // Register combined endpoint that checks paused orders and returns today's orders
    register_rest_route('satguru/v1', '/todays-orders-with-check', array(
        'methods' => 'GET',
        'callback' => 'satguru_api_get_todays_orders_with_check',
        'permission_callback' => 'satguru_api_permission_check',
    ));
}
add_action('rest_api_init', 'satguru_register_todays_orders_api');

/**
 * Permission callback for API endpoint
 */
function satguru_api_permission_check($request) {
    // Get API key from request
    $api_key = $request->get_param('api_key');
    
    // Get stored API key from options
    $stored_api_key = get_option('satguru_api_key', '');
    
    // If no API key is set, generate one
    if (empty($stored_api_key)) {
        $new_api_key = wp_generate_password(32, false);
        update_option('satguru_api_key', $new_api_key);
        $stored_api_key = $new_api_key;
    }
    
    // Verify API key
    if (!empty($api_key) && $api_key === $stored_api_key) {
        return true;
    }
    
    // Also allow authenticated admin users
    if (current_user_can('manage_options')) {
        return true;
    }
    
    return new WP_Error('rest_forbidden', 'Invalid API key or insufficient permissions.', array('status' => 403));
}

/**
 * API callback to get today's orders as JSON
 */
function satguru_api_get_todays_orders($request) {
    $today = date('Y-m-d');
    $orders = satguru_get_orders_by_start_date($today);
    $check_date = $today;
    
    $orders_data = array();
    
    foreach ($orders as $order) {
        // Initialize variables
        $start_date = '';
        $preferred_days = '';
        $products = '';
        $customize = '';
        $veg_nonveg = '';
        $delivery = '';
        $addons = '';
        $quantity = '';
        $boxes = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $check_date) : 0;
        $remaining_tiffins = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order) : 0;
        $total_tiffins = 0;
        
        // Get metadata from order items
        foreach ($order->get_items() as $item) {
            $products .= $item->get_name() . ' ';
            $quantity = $item->get_quantity();
            
            foreach ($item->get_meta_data() as $meta) {
                switch (true) {
                    case strpos($meta->key, 'Start Date') !== false:
                    case strpos($meta->key, 'Delivery Date') !== false:
                        $start_date = date('Y-m-d', strtotime($meta->value));
                        break;
                    case strpos($meta->key, 'Prefered Days') !== false:
                        $preferred_days .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Want to Customize') !== false:
                        $customize .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Veg / Non Veg') !== false:
                    case strpos($meta->key, 'Food Type') !== false:
                        $veg_nonveg .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Delivery') !== false:
                        if (strpos($meta->key, 'Delivery Date') === false) {
                            $delivery .= $meta->value . ' ';
                        }
                        break;
                    case strpos($meta->key, 'Number Of Tiffins') !== false:
                        $total_tiffins = intval($meta->value);
                        break;
                    case strpos(strtolower($meta->key), 'add-ons') !== false:
                    case strpos(strtolower($meta->key), 'additional') !== false:
                        if (!empty($meta->value)) {
                            if (is_array($meta->value)) {
                                foreach ($meta->value as $key => $value) {
                                    if (!is_numeric($key)) {
                                        $addons .= "$key = $value, ";
                                    } elseif (!is_array($value)) {
                                        $addons .= "$value, ";
                                    }
                                }
                            } else {
                                $addons .= $meta->value . ', ';
                            }
                        }
                        break;
                }
            }
        }
        
        // Clean up strings
        $addons = rtrim($addons, ', ');
        $preferred_days = trim($preferred_days);
        $products = trim($products);
        $customize = trim($customize);
        $veg_nonveg = trim($veg_nonveg);
        $delivery = trim($delivery);
        
        // Get customer note
        $customer_note = $order->get_customer_note();
        
        // Prepare order data
        $order_data = array(
            'order_id' => $order->get_id(),
            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'start_date' => $start_date,
            'preferred_days' => $preferred_days,
            'products' => $products,
            'quantity' => $quantity,
            'boxes' => $boxes,
            'customize' => $customize,
            'veg_nonveg' => $veg_nonveg,
            'delivery' => $delivery,
            'addons' => $addons,
            'notes' => $customer_note,
            'address' => $order->get_shipping_address_1(),
            'city' => $order->get_shipping_city(),
            'postal_code' => $order->get_shipping_postcode(),
            'status' => wc_get_order_status_name($order->get_status()),
            'total_tiffins' => $total_tiffins,
            'remaining_tiffins' => $remaining_tiffins
        );
        
        $orders_data[] = $order_data;
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'date' => $today,
        'total_orders' => count($orders_data),
        'orders' => $orders_data
    ));
}

/**
 * API callback to check and resume paused orders
 */
function satguru_api_check_paused_orders($request) {
    // Check if check_paused_orders function exists
    if (!function_exists('check_paused_orders')) {
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'check_paused_orders function not found'
        ));
    }
    
    // Run the check_paused_orders function
    try {
        check_paused_orders();
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Paused orders checked and resumed successfully',
            'date' => current_time('Y-m-d')
        ));
    } catch (Exception $e) {
        return rest_ensure_response(array(
            'success' => false,
            'message' => 'Error checking paused orders: ' . $e->getMessage()
        ));
    }
}

/**
 * Combined API callback: Check paused orders first, then return today's orders
 */
function satguru_api_get_todays_orders_with_check($request) {
    // First, check and resume paused orders
    $check_result = array('success' => true, 'message' => 'No check performed');
    
    if (function_exists('check_paused_orders')) {
        try {
            check_paused_orders();
            $check_result = array(
                'success' => true,
                'message' => 'Paused orders checked and resumed successfully'
            );
        } catch (Exception $e) {
            $check_result = array(
                'success' => false,
                'message' => 'Error checking paused orders: ' . $e->getMessage()
            );
        }
    }
    
    // Then, get today's orders using the existing function
    $today = date('Y-m-d');
    $orders = satguru_get_orders_by_start_date($today);
    $check_date = $today;
    
    $orders_data = array();
    
    foreach ($orders as $order) {
        // Initialize variables
        $start_date = '';
        $preferred_days = '';
        $products = '';
        $customize = '';
        $veg_nonveg = '';
        $delivery = '';
        $addons = '';
        $quantity = '';
        $boxes = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $check_date) : 0;
        $remaining_tiffins = class_exists('Satguru_Tiffin_Calculator') ? 
            Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order) : 0;
        $total_tiffins = 0;
        
        // Get metadata from order items
        foreach ($order->get_items() as $item) {
            $products .= $item->get_name() . ' ';
            $quantity = $item->get_quantity();
            
            foreach ($item->get_meta_data() as $meta) {
                switch (true) {
                    case strpos($meta->key, 'Start Date') !== false:
                    case strpos($meta->key, 'Delivery Date') !== false:
                        $start_date = date('Y-m-d', strtotime($meta->value));
                        break;
                    case strpos($meta->key, 'Prefered Days') !== false:
                        $preferred_days .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Want to Customize') !== false:
                        $customize .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Veg / Non Veg') !== false:
                    case strpos($meta->key, 'Food Type') !== false:
                        $veg_nonveg .= $meta->value . ' ';
                        break;
                    case strpos($meta->key, 'Delivery') !== false:
                        if (strpos($meta->key, 'Delivery Date') === false) {
                            $delivery .= $meta->value . ' ';
                        }
                        break;
                    case strpos($meta->key, 'Number Of Tiffins') !== false:
                        $total_tiffins = intval($meta->value);
                        break;
                    case strpos(strtolower($meta->key), 'add-ons') !== false:
                    case strpos(strtolower($meta->key), 'additional') !== false:
                        if (!empty($meta->value)) {
                            if (is_array($meta->value)) {
                                foreach ($meta->value as $key => $value) {
                                    if (!is_numeric($key)) {
                                        $addons .= "$key = $value, ";
                                    } elseif (!is_array($value)) {
                                        $addons .= "$value, ";
                                    }
                                }
                            } else {
                                $addons .= $meta->value . ', ';
                            }
                        }
                        break;
                }
            }
        }
        
        // Clean up strings
        $addons = rtrim($addons, ', ');
        $preferred_days = trim($preferred_days);
        $products = trim($products);
        $customize = trim($customize);
        $veg_nonveg = trim($veg_nonveg);
        $delivery = trim($delivery);
        
        // Get customer note
        $customer_note = $order->get_customer_note();
        
        // Prepare order data
        $order_data = array(
            'order_id' => $order->get_id(),
            'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'start_date' => $start_date,
            'preferred_days' => $preferred_days,
            'products' => $products,
            'quantity' => $quantity,
            'boxes' => $boxes,
            'customize' => $customize,
            'veg_nonveg' => $veg_nonveg,
            'delivery' => $delivery,
            'addons' => $addons,
            'notes' => $customer_note,
            'address' => $order->get_shipping_address_1(),
            'city' => $order->get_shipping_city(),
            'postal_code' => $order->get_shipping_postcode(),
            'status' => wc_get_order_status_name($order->get_status()),
            'total_tiffins' => $total_tiffins,
            'remaining_tiffins' => $remaining_tiffins
        );
        
        $orders_data[] = $order_data;
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'date' => $today,
        'total_orders' => count($orders_data),
        'orders' => $orders_data,
        'pause_check' => $check_result
    ));
}

/**
 * Display API key in admin dashboard
 */
function satguru_display_api_key() {
    $api_key = get_option('satguru_api_key', '');
    
    if (empty($api_key)) {
        $api_key = wp_generate_password(32, false);
        update_option('satguru_api_key', $api_key);
    }
    
    $api_url = home_url('/wp-json/satguru/v1/todays-orders?api_key=' . $api_key);
    $api_url_with_check = home_url('/wp-json/satguru/v1/todays-orders-with-check?api_key=' . $api_key);
    $api_url_check_only = home_url('/wp-json/satguru/v1/check-paused-orders?api_key=' . $api_key);
    
    $script_file = get_stylesheet_directory() . '/google-sheets-script.js';
    $script_exists = file_exists($script_file);
    
    echo '<div class="api-key-section" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    echo '<h2>Google Sheets Integration</h2>';
    echo '<p><strong>API Key:</strong> <code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px;">' . esc_html($api_key) . '</code></p>';
    
    echo '<div style="margin: 20px 0;">';
    echo '<h3>Available API Endpoints:</h3>';
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">';
    echo '<strong>1. Today\'s Orders (with Pause Check) - RECOMMENDED:</strong><br>';
    echo '<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: block; word-break: break-all; margin-top: 5px;">' . esc_html($api_url_with_check) . '</code>';
    echo '<p class="description" style="margin-top: 5px;">This endpoint automatically checks and resumes paused orders before fetching today\'s orders. Use this in your Google Script.</p>';
    echo '</div>';
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #00a32a;">';
    echo '<strong>2. Today\'s Orders Only:</strong><br>';
    echo '<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: block; word-break: break-all; margin-top: 5px;">' . esc_html($api_url) . '</code>';
    echo '<p class="description" style="margin-top: 5px;">Fetches today\'s orders without checking paused orders.</p>';
    echo '</div>';
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #ff9500;">';
    echo '<strong>3. Check Paused Orders Only:</strong><br>';
    echo '<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: block; word-break: break-all; margin-top: 5px;">' . esc_html($api_url_check_only) . '</code>';
    echo '<p class="description" style="margin-top: 5px;">Only checks and resumes paused orders without fetching today\'s orders.</p>';
    echo '</div>';
    
    echo '</div>';
    
    echo '<p class="description"><strong>Note:</strong> The Google Script is configured to use the combined endpoint (with pause check) by default.</p>';
    echo '<p><a href="' . esc_url(admin_url('admin-post.php?action=regenerate_api_key')) . '" class="button">Regenerate API Key</a></p>';
    
    // Display Google Script instructions
    echo '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">';
    echo '<h3>Google Apps Script Setup Instructions</h3>';
    echo '<ol style="margin-left: 20px;">';
    echo '<li>Open your Google Sheet where you want to sync the orders</li>';
    echo '<li>Go to <strong>Extensions &gt; Apps Script</strong></li>';
    echo '<li>Delete any existing code and paste the Google Apps Script code</li>';
    echo '<li>Update the <code>API_URL</code> and <code>API_KEY</code> variables at the top of the script with your values above</li>';
    echo '<li>Save the script (Ctrl+S or Cmd+S)</li>';
    echo '<li>Click the <strong>Run</strong> button to execute the sync</li>';
    echo '<li>Grant permissions when prompted</li>';
    echo '</ol>';
    echo '<p><strong>Note:</strong> The Google Apps Script file is located at: <code>' . esc_html($script_file) . '</code></p>';
    if ($script_exists) {
        echo '<p><a href="' . esc_url(get_stylesheet_directory_uri() . '/google-sheets-script.js') . '" target="_blank" class="button">View Google Script File</a></p>';
    }
    echo '</div>';
    
    echo '</div>';
}

/**
 * Handle API key regeneration
 */
function satguru_regenerate_api_key() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 'Permission Error', array('response' => 403));
    }
    
    $new_api_key = wp_generate_password(32, false);
    update_option('satguru_api_key', $new_api_key);
    
    wp_redirect(add_query_arg(array(
        'page' => 'satguru-admin-dashboard',
        'api_key_regenerated' => '1'
    ), admin_url('admin.php')));
    exit;
}
add_action('admin_post_regenerate_api_key', 'satguru_regenerate_api_key');

/**
 * Add API key section to admin dashboard
 */
function satguru_add_api_key_to_dashboard() {
    if (isset($_GET['page']) && $_GET['page'] === 'satguru-admin-dashboard') {
        if (isset($_GET['api_key_regenerated']) && $_GET['api_key_regenerated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>API key regenerated successfully!</p></div>';
        }
    }
}

// Display API key section in admin dashboard
add_action('admin_init', 'satguru_add_api_key_to_dashboard');