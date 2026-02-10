<?php
// Create the customers database table on plugin activation
function create_customers_data_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'customers_data';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id varchar(100),  /* Changed to varchar to handle guest users */
        customer_name varchar(255) NOT NULL,
        phone varchar(20),
        email varchar(100),
        registered_on datetime DEFAULT CURRENT_TIMESTAMP,
        address text,
        city varchar(100),
        postal_code varchar(20),
        last_order_date datetime,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Debug output
    if ($wpdb->last_error) {
        error_log('Table creation error: ' . $wpdb->last_error);
    }
}

// Add menu item for Customers page
function add_customers_menu_item() {
    add_menu_page(
        'Customers',
        'Customers',
        'manage_options',
        'customers-list',
        'display_customers_page',
        'dashicons-groups',
        6
    );
}
add_action('admin_menu', 'add_customers_menu_item');

// Add manual import button to the customers page
function add_import_button() {
    if (isset($_POST['import_customers']) && check_admin_referer('import_customers_nonce')) {
        import_existing_customers();
        echo '<div class="notice notice-success"><p>Customers imported successfully!</p></div>';
    }
    
    ?>
    <form method="post" style="margin-top: 10px;">
        <?php wp_nonce_field('import_customers_nonce'); ?>
        <input type="submit" name="import_customers" class="button button-primary" value="Import Existing Customers">
    </form>
    <?php
}

// Display Customers page content
function display_customers_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'customers_data';

    // Debug output
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        echo '<div class="notice notice-info"><p>Table name: ' . $table_name . '</p>';
        echo '<p>Total rows: ' . $wpdb->get_var("SELECT COUNT(*) FROM $table_name") . '</p></div>';
    }

    // Get pagination parameters
    $items_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Get search parameters
    $search_query = isset($_GET['search_query']) ? sanitize_text_field($_GET['search_query']) : '';

    // Build query
    $where = '';
    if (!empty($search_query)) {
        $where = $wpdb->prepare(
            "WHERE customer_name LIKE %s 
            OR phone LIKE %s 
            OR email LIKE %s 
            OR address LIKE %s 
            OR city LIKE %s 
            OR postal_code LIKE %s",
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%'
        );
    }

    // Get total items
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_items / $items_per_page);

    // Get customers data
    $customers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name $where ORDER BY last_order_date DESC LIMIT %d OFFSET %d",
            $items_per_page,
            $offset
        )
    );

    ?>
    <div class="wrap">
        <h1>Customers</h1>
        
        <?php add_import_button(); ?>

        <!-- Search Form -->
        <div class="tablenav top">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="customers-list">
                <input type="text" name="search_query" 
                       value="<?php echo esc_attr($search_query); ?>" 
                       placeholder="Search customers...">
                <input type="submit" class="button" value="Search">
                <?php if (!empty($search_query)): ?>
                    <a href="<?php echo admin_url('admin.php?page=customers-list'); ?>" 
                       class="button">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Customers Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Registered On</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>Postal Code</th>
                    <th>Last Order Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8">No customers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo esc_html($customer->customer_name); ?></td>
                            <td><?php echo esc_html($customer->phone); ?></td>
                            <td><?php echo esc_html($customer->email); ?></td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($customer->registered_on))); ?></td>
                            <td><?php echo esc_html($customer->address); ?></td>
                            <td><?php echo esc_html($customer->city); ?></td>
                            <td><?php echo esc_html($customer->postal_code); ?></td>
                            <td><?php echo $customer->last_order_date ? esc_html(date('Y-m-d', strtotime($customer->last_order_date))) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo $total_items; ?> items
                    </span>
                    <span class="pagination-links">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Update customer data when user registers
function update_customer_data_on_registration($user_id) {
    $user = get_userdata($user_id);
    update_customer_data($user_id, array(
        'customer_name' => $user->display_name,
        'email' => $user->user_email,
        'registered_on' => $user->user_registered
    ));
}
add_action('user_register', 'update_customer_data_on_registration');

// Update customer data when order is placed
function update_customer_data_on_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if ($user_id == 0) {
        // Handle guest order
        $email = $order->get_billing_email();
        $user_id = 'guest_' . md5($email);
    }

    $data = array(
        'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'phone' => $order->get_shipping_phone(),
        'email' => $order->get_billing_email(),
        'address' => $order->get_shipping_address_1(),
        'city' => $order->get_shipping_city(),
        'postal_code' => $order->get_shipping_postcode(),
        'last_order_date' => current_time('mysql')
    );

    update_customer_data($user_id, $data);
}
add_action('woocommerce_checkout_order_processed', 'update_customer_data_on_order');

// Helper function to update customer data
function update_customer_data($user_id, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'customers_data';

    // Ensure required fields
    if (!isset($data['customer_name'])) {
        $data['customer_name'] = 'Unknown';
    }
    
    // Add registered_on if not set
    if (!isset($data['registered_on'])) {
        $data['registered_on'] = current_time('mysql');
    }

    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %s", $user_id)
    );

    if ($existing) {
        $result = $wpdb->update(
            $table_name,
            $data,
            array('user_id' => $user_id)
        );
    } else {
        $data['user_id'] = $user_id;
        $result = $wpdb->insert($table_name, $data);
    }

    if ($result === false) {
        error_log('Customer data update failed: ' . $wpdb->last_error);
    }
}

// Import existing WooCommerce customers
function import_existing_customers() {
    $args = array(
        'role' => 'customer',
        'fields' => 'ID'
    );
    $customer_ids = get_users($args);

    foreach ($customer_ids as $user_id) {
        $user = get_userdata($user_id);
        $last_order = wc_get_customer_last_order($user_id);

        $data = array(
            'customer_name' => $user->display_name,
            'email' => $user->user_email,
            'registered_on' => $user->user_registered,
            'last_order_date' => $last_order ? $last_order->get_date_created()->format('Y-m-d H:i:s') : null
        );

        if ($last_order) {
            $data['phone'] = $last_order->get_shipping_phone();
            $data['address'] = $last_order->get_shipping_address_1();
            $data['city'] = $last_order->get_shipping_city();
            $data['postal_code'] = $last_order->get_shipping_postcode();
        }

        update_customer_data($user_id, $data);
    }

    // Import guest orders
    $args = array(
        'customer_id' => 0, // Guest orders
        'limit' => -1
    );
    $guest_orders = wc_get_orders($args);

    foreach ($guest_orders as $order) {
        $email = $order->get_billing_email();
        if (!empty($email)) {
            $user_id = 'guest_' . md5($email);
            $data = array(
                'customer_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'phone' => $order->get_billing_phone(),
                'email' => $email,
                'address' => $order->get_shipping_address_1(),
                'city' => $order->get_shipping_city(),
                'postal_code' => $order->get_shipping_postcode(),
                'last_order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'registered_on' => $order->get_date_created()->format('Y-m-d H:i:s')
            );
            update_customer_data($user_id, $data);
        }
    }
}