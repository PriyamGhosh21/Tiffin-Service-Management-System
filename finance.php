<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Finance Menu
 */
function satguru_register_finance_menu()
{
    add_menu_page(
        'Finance Dashboard',
        'Finance',
        'manage_options',
        'satguru-finance',
        'satguru_finance_dashboard_page',
        'dashicons-chart-line',
        56 // Position
    );
}
add_action('admin_menu', 'satguru_register_finance_menu');

/**
 * Handle Export Action (CSV Generation)
 * Hooked to admin_init to process headers before output
 */
function satguru_handle_finance_export()
{
    if (isset($_POST['export_finance_data']) && current_user_can('manage_options')) {
        // Verify nonce for security
        check_admin_referer('satguru_finance_export', 'finance_export_nonce');

        $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        // Fetch Orders for Export
        $orders = satguru_get_finance_orders($start_date, $end_date);

        // Set Headers for Download (Excel .xls format to support colors)
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=finance-report-' . date('Y-m-d') . '.xls');

        // Start HTML Table
        echo '<table border="1">';
        echo '<thead><tr>';
        $headers = array('Order ID', 'Date', 'Status', 'Customer', 'Phone', 'Company', 'Total', 'Tax', 'Tax Status', 'Amount Received', 'Price Per Tiffin', 'Trial / Plan', 'Plan Type', 'Items', 'Number of Tiffins', 'Preferred Days');
        foreach ($headers as $header) {
            echo '<th style="background-color:#f0f0f0; font-weight:bold;">' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';

        // Rows
        foreach ($orders as $order_obj) {
            if (!$order_obj)
                continue;

            $items_list = array();
            $order_items = $order_obj->get_items();
            foreach ($order_items as $item) {
                $items_list[] = $item->get_name() . ' x ' . $item->get_quantity();
            }

            // Determine Row Color (Red if multiple distinct items are present)
            // User example: "5 Item Veg... , 5 Item Non-Veg..., Trial..." (Multiple items)
            $row_style = '';
            if (count($order_items) > 1) {
                $row_style = 'style="background-color: #ffcccc;"'; // Light red background
            }

            // Fetch Order Meta Data
            // Number of Tiffins
            $number_of_tiffins = $order_obj->get_meta('Number Of Tiffins');
            if (empty($number_of_tiffins)) {
                $number_of_tiffins = $order_obj->get_meta('number_of_tiffins');
            }

            // Preferred Days
            $preferred_days = $order_obj->get_meta('Prefered Days');
            if (empty($preferred_days)) {
                $preferred_days = $order_obj->get_meta('Preferred Days');
            }
            if (empty($preferred_days)) {
                $preferred_days = $order_obj->get_meta('preferred_days');
            }

            // Fallback: Check Item Meta (Fix for Direct Website Orders)
            if (empty($number_of_tiffins) || empty($preferred_days)) {
                $item_tiffins_total = 0;
                $item_days_list = array();
                $found_item_meta = false;

                foreach ($order_items as $item) {
                    // Tiffins Check
                    $t_meta = $item->get_meta('Number of Tiffins');
                    if (empty($t_meta))
                        $t_meta = $item->get_meta('number_of_tiffins');

                    if (!empty($t_meta)) {
                        $found_item_meta = true;
                        // Clean string "20 (x20)" -> 20
                        if (preg_match('/(\d+)/', (string) $t_meta, $matches)) {
                            $item_tiffins_total += floatval($matches[1]);
                        } else {
                            $item_tiffins_total += floatval($t_meta);
                        }
                    }

                    // Days Check
                    $d_meta = $item->get_meta('Preferred Days');
                    if (empty($d_meta))
                        $d_meta = $item->get_meta('preferred_days');

                    if (!empty($d_meta)) {
                        if (is_array($d_meta)) {
                            $item_days_list = array_merge($item_days_list, $d_meta);
                        } else {
                            $item_days_list[] = (string) $d_meta;
                        }
                    }
                }

                if (empty($number_of_tiffins) && $found_item_meta) {
                    $number_of_tiffins = $item_tiffins_total;
                }

                if (empty($preferred_days) && !empty($item_days_list)) {
                    $preferred_days = array_unique($item_days_list); // Avoid duplicates
                }
            }

            if (is_array($preferred_days)) {
                $preferred_days = implode(', ', $preferred_days);
            }

            // Company (Hardcoded to 'TiffinGrab')
            $company = 'TiffinGrab';

            // Calculate Price Per Tiffin
            // Calculate Price Per Tiffin
            $price_per_tiffin = '';

            // Extract number using regex to handle formats like "20 (x20)"
            $tiffin_count_val = 0;
            if (preg_match('/(\d+)/', $number_of_tiffins, $matches)) {
                $tiffin_count_val = floatval($matches[1]);
            } else {
                $tiffin_count_val = floatval(trim(str_replace(';', '', $number_of_tiffins)));
            }

            $total_val = floatval($order_obj->get_total());

            if ($tiffin_count_val > 0) {
                $price_per_tiffin = number_format($total_val / $tiffin_count_val, 2);
            }

            // Amount Received (Total - Tax)
            $amount_received = number_format($total_val - floatval($order_obj->get_total_tax()), 2);

            // Tax Status
            $tax_status = (floatval($order_obj->get_total_tax()) > 0) ? 'Tax' : 'NON TAX';

            // Trial / Plan Logic
            // Trial / Plan Logic
            $trial_plan_status = 'Trial'; // Default to Trial (<= 5 tiffins)

            if ($tiffin_count_val > 5) {
                // Check history by Customer ID, Email, and Phone for robustness
                $date_created = $order_obj->get_date_created()->date('Y-m-d H:i:s');
                $order_id = $order_obj->get_id();
                $has_history = false;

                // 1. Check by Customer ID (most reliable for registered users)
                if ($order_obj->get_customer_id() > 0) {
                    $past_by_id = wc_get_orders(array(
                        'customer_id' => $order_obj->get_customer_id(),
                        'date_before' => $date_created,
                        'exclude' => array($order_id),
                        'limit' => 1,
                        'return' => 'ids',
                        'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                    ));
                    if (!empty($past_by_id))
                        $has_history = true;
                }

                // 2. Check by Email
                if (!$has_history && $order_obj->get_billing_email()) {
                    $past_by_email = wc_get_orders(array(
                        'billing_email' => $order_obj->get_billing_email(),
                        'date_before' => $date_created,
                        'exclude' => array($order_id),
                        'limit' => 1,
                        'return' => 'ids',
                        'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                    ));
                    if (!empty($past_by_email))
                        $has_history = true;
                }

                // 3. Check by Phone
                if (!$has_history && $order_obj->get_billing_phone()) {
                    $past_by_phone = wc_get_orders(array(
                        'meta_key' => '_billing_phone',
                        'meta_value' => $order_obj->get_billing_phone(),
                        'date_before' => $date_created,
                        'exclude' => array($order_id),
                        'limit' => 1,
                        'return' => 'ids',
                        'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                    ));
                    if (!empty($past_by_phone))
                        $has_history = true;
                }

                if ($has_history) {
                    $trial_plan_status = 'Renewal';
                } else {
                    $trial_plan_status = 'New Plan';
                }
            }

            echo '<tr ' . $row_style . '>';
            echo '<td>' . htmlspecialchars((string) $order_obj->get_id()) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_date_created()->date('Y-m-d H:i:s')) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_status()) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_formatted_billing_full_name()) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_billing_phone()) . '</td>';
            echo '<td>' . htmlspecialchars($company) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_total()) . '</td>';
            echo '<td>' . htmlspecialchars($order_obj->get_total_tax()) . '</td>';
            echo '<td>' . htmlspecialchars($tax_status) . '</td>';
            echo '<td>' . htmlspecialchars($amount_received) . '</td>';
            echo '<td>' . htmlspecialchars($price_per_tiffin) . '</td>';
            echo '<td>' . htmlspecialchars($trial_plan_status) . '</td>'; // New Column Output
            echo '<td>' . htmlspecialchars(implode(', ', $items_list)) . '</td>'; // Plan Type column used for items list summary? Using same as before
            echo '<td>' . htmlspecialchars(count($items_list)) . '</td>'; // Items column used for count? Wait, previous code put count as separate column
            // Wait, let's map correctly to headers:
            // Headers: 'Order ID', 'Date', 'Status', 'Customer', 'Phone', 'Company', 'Total', 'Tax', 'Tax Status', 'Amount Received', 'Price Per Tiffin', 'Plan Type', 'Items', 'Number of Tiffins', 'Preferred Days'
            // Previous code:
            // ..., implode items, count items, tiffins, days
            // So: col 'Plan Type' => implode items
            // col 'Items' => count items

            echo '<td>' . htmlspecialchars(rtrim($number_of_tiffins, '; ')) . '</td>';
            echo '<td>' . htmlspecialchars(rtrim($preferred_days, '; ')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        exit;
    }
}
add_action('admin_init', 'satguru_handle_finance_export');

/**
 * Helper: Fetch Orders based on Date Range
 */
function satguru_get_finance_orders($start_date = '', $end_date = '')
{
    $args = array(
        'limit' => -1,
        'status' => array('completed', 'processing', 'on-hold', 'paused', 'wc-paused'),
        'type' => 'shop_order',
    );

    if ($start_date && $end_date) {
        $args['date_created'] = $start_date . '...' . $end_date;
    } elseif ($start_date) {
        $args['date_created'] = '>=' . $start_date;
    } elseif ($end_date) {
        $args['date_created'] = '<=' . $end_date;
    }

    return wc_get_orders($args);
}

/**
 * Render the Dashboard Page
 */
function satguru_finance_dashboard_page()
{
    // Get Filter Inputs
    $is_filtered = isset($_GET['start_date']) || isset($_GET['end_date']);

    if ($is_filtered) {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    } else {
        $start_date = current_time('Y-m-d');
        $end_date = current_time('Y-m-d');
    }

    // Fetch Data
    $orders = satguru_get_finance_orders($start_date, $end_date);

    // Calculate Metrics
    $total_sales = 0;
    $order_count = count($orders);
    $sales_by_month = array();
    $plan_counts = array();
    $plan_types = array(); // Categorize by simple logic if specific meta isn't defined
    $orders_by_user = array(); // Track orders taken by each user

    foreach ($orders as $order) {
        if (!$order)
            continue;

        $total = $order->get_total();
        $total_sales += $total;

        // Sales by Month
        $month_key = $order->get_date_created()->date('M Y');
        if (!isset($sales_by_month[$month_key])) {
            $sales_by_month[$month_key] = 0;
        }
        $sales_by_month[$month_key] += $total;

        // Plan Logic (Top 5 Plans & Types)
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $qty = $item->get_quantity();

            // Top Plans
            if (!isset($plan_counts[$product_name])) {
                $plan_counts[$product_name] = 0;
            }
            $plan_counts[$product_name] += $qty;

            // Plan Types - Try to get category or use logic
            $product_id = $item->get_product_id();
            $terms = get_the_terms($product_id, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (!isset($plan_types[$term->name])) {
                        $plan_types[$term->name] = 0;
                    }
                    $plan_types[$term->name] += $qty;
                }
            } else {
                // Fallback to "Uncategorized" or simple grouping
                $type_key = 'Standard';
                if (!isset($plan_types[$type_key])) {
                    $plan_types[$type_key] = 0;
                }
                $plan_types[$type_key] += $qty;
            }
        }

        // Track orders by user (_generated_by meta)
        $generated_by = $order->get_meta('_generated_by');
        if (!empty($generated_by)) {
            if (!isset($orders_by_user[$generated_by])) {
                $orders_by_user[$generated_by] = 0;
            }
            $orders_by_user[$generated_by]++;
        } else {
            // Track orders without _generated_by as 'Unknown'
            if (!isset($orders_by_user['Unknown'])) {
                $orders_by_user['Unknown'] = 0;
            }
            $orders_by_user['Unknown']++;
        }
    }

    $avg_ticket = $order_count > 0 ? $total_sales / $order_count : 0;

    // Sort Top Plans
    arsort($plan_counts);
    $top_5_plans = array_slice($plan_counts, 0, 5);

    // Sort Orders by User
    arsort($orders_by_user);

    ?>
    <div class="wrap finance-dashboard-wrapper">
        <div class="finance-header">
            <div>
                <h1 class="finance-title">Finance Overview</h1>
                <p class="finance-subtitle">Track your revenue, orders, and product performance.</p>
            </div>
            <div class="finance-actions">
                <form method="post" action="">
                    <?php wp_nonce_field('satguru_finance_export', 'finance_export_nonce'); ?>
                    <input type="hidden" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                    <button type="submit" name="export_finance_data" class="fin-btn fin-btn-outline">
                        <span class="dashicons dashicons-download"></span> Export CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Smart Filter Bar -->
        <div class="finance-card filter-card">
            <form method="get" action="" class="filter-form">
                <input type="hidden" name="page" value="satguru-finance">
                <div class="filter-group">
                    <label>Date Range</label>
                    <div class="date-inputs">
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="fin-input">
                        <span class="separator">to</span>
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="fin-input">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="fin-btn fin-btn-primary">Apply Filters</button>
                    <?php if ($is_filtered): ?>
                        <a href="<?php echo admin_url('admin.php?page=satguru-finance'); ?>" class="fin-btn fin-btn-text">Reset
                            to Today</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Hero Metrics -->
        <div class="finance-hero-grid">
            <div class="finance-metric-card primary-gradient">
                <div class="metric-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="metric-content">
                    <h3>Total Revenue</h3>
                    <div class="metric-value"><?php echo wc_price($total_sales); ?></div>
                    <div class="metric-meta"><?php echo $order_count; ?> Orders Total</div>
                </div>
            </div>
            <div class="finance-metric-card secondary-gradient">
                <div class="metric-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="metric-content">
                    <h3>Avg. Ticket Size</h3>
                    <div class="metric-value"><?php echo wc_price($avg_ticket); ?></div>
                    <div class="metric-meta">Revenue per Order</div>
                </div>
            </div>
            <div class="finance-metric-card glass-card">
                <div class="metric-icon text-indigo"><span class="dashicons dashicons-products"></span></div>
                <div class="metric-content">
                    <h3>Plans Sold</h3>
                    <div class="metric-value"><?php echo count($plan_counts); ?></div>
                    <div class="metric-meta">Unique Products</div>
                </div>
            </div>
        </div>

        <div class="finance-content-grid">
            <!-- Monthly Sales Trend -->
            <div class="finance-card">
                <div class="card-header">
                    <h3><span class="dashicons dashicons-calendar-alt"></span> Sales Breakdown</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($sales_by_month)): ?>
                        <div class="empty-state">No sales data for this period.</div>
                    <?php else: ?>
                        <div class="fin-table-wrapper">
                            <table class="fin-table">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="text-right">Revenue</th>
                                        <th class="text-right">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_by_month as $month => $amount): ?>
                                        <tr>
                                            <td class="font-medium"><?php echo esc_html($month); ?></td>
                                            <td class="text-right font-bold text-slate-800"><?php echo wc_price($amount); ?></td>
                                            <td class="text-right"><span class="trend-badge positive">Details</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Products -->
            <div class="finance-card">
                <div class="card-header">
                    <h3><span class="dashicons dashicons-star-filled"></span> Top Performers</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($top_5_plans)): ?>
                        <div class="empty-state">No top plans data.</div>
                    <?php else: ?>
                        <div class="top-products-list">
                            <?php
                            $max_val = max($top_5_plans);
                            $i = 1;
                            foreach ($top_5_plans as $name => $count):
                                $width_percent = ($count / $max_val) * 100;
                                ?>
                                <div class="product-item">
                                    <div class="product-rank">#<?php echo $i++; ?></div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo esc_html($name); ?></div>
                                        <div class="product-bar-bg">
                                            <div class="product-bar-fill" style="width: <?php echo $width_percent; ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="product-stats">
                                        <span class="stat-value"><?php echo esc_html($count); ?></span>
                                        <span class="stat-label">sold</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="finance-content-grid">
            <!-- Category Distribution -->
            <div class="finance-card">
                <div class="card-header">
                    <h3><span class="dashicons dashicons-category"></span> Plan Distribution</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($plan_types)): ?>
                        <div class="empty-state">No category data available.</div>
                    <?php else: ?>
                        <div class="categories-grid">
                            <?php foreach ($plan_types as $type => $count): ?>
                                <div class="category-pill">
                                    <span class="cat-name"><?php echo esc_html($type); ?></span>
                                    <span class="cat-count"><?php echo esc_html($count); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Orders Taken By -->
            <div class="finance-card">
                <div class="card-header">
                    <h3><span class="dashicons dashicons-admin-users"></span> Orders Taken By</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($orders_by_user)): ?>
                        <div class="empty-state">No order data available.</div>
                    <?php else: ?>
                        <div class="top-products-list">
                            <?php
                            $max_user_orders = max($orders_by_user);
                            $j = 1; // Rank counter
                            foreach ($orders_by_user as $user => $count):
                                $width_percent = ($count / $max_user_orders) * 100;
                                ?>
                                <div class="product-item">
                                    <div class="product-rank">#<?php echo $j++; ?></div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo esc_html($user); ?></div>
                                        <div class="product-bar-bg">
                                            <div class="product-bar-fill" style="width: <?php echo $width_percent; ?>%;"></div>
                                        </div>
                                    </div>
                                    <div class="product-stats">
                                        <span class="stat-value"><?php echo esc_html($count); ?></span>
                                        <span class="stat-label">orders</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


    </div>

    <style>
        :root {
            --fin-primary: #4f46e5;
            /* Indigo 600 */
            --fin-primary-dark: #4338ca;
            --fin-secondary: #10b981;
            /* Emerald 500 */
            --fin-bg: #f3f4f6;
            /* Slate 100 */
            --fin-card-bg: #ffffff;
            --fin-text-main: #1e293b;
            /* Slate 800 */
            --fin-text-muted: #64748b;
            /* Slate 500 */
            --fin-border: #e2e8f0;
            --fin-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --fin-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --fin-radius: 12px;
        }

        .finance-dashboard-wrapper {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--fin-text-main);
            max-width: 1280px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* Typography */
        .finance-title {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            margin: 0 !important;
            letter-spacing: -0.025em;
        }

        .finance-subtitle {
            color: var(--fin-text-muted);
            font-size: 14px;
            margin: 5px 0 0 0;
        }

        /* Header */
        .finance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        /* Buttons & Inputs */
        .fin-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            /* Vertical padding handled by height */
            height: 42px;
            /* Explicit height */
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: 1px solid transparent;
            gap: 6px;
            box-sizing: border-box;
        }

        .fin-btn-primary {
            background-color: var(--fin-primary);
            color: white;
        }

        .fin-btn-primary:hover {
            background-color: var(--fin-primary-dark);
            transform: translateY(-1px);
        }

        .fin-btn-outline {
            background-color: white;
            border-color: var(--fin-border);
            color: var(--fin-text-main);
        }

        .fin-btn-outline:hover {
            border-color: var(--fin-text-muted);
            background-color: #f8fafc;
        }

        .fin-btn-text {
            background: none;
            color: var(--fin-text-muted);
        }

        .fin-btn-text:hover {
            color: var(--fin-primary);
        }

        .fin-input {
            height: 42px;
            /* Explicit height matches buttons */
            padding: 0 12px;
            /* Vertical padding handled by flex/height */
            border: 1px solid var(--fin-border);
            border-radius: 8px;
            background: white;
            font-size: 14px;
            color: var(--fin-text-main);
            outline: none;
            box-shadow: none;
            box-sizing: border-box;
        }

        .fin-input:focus {
            border-color: var(--fin-primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        /* Filter Card */
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: var(--fin-radius);
            box-shadow: var(--fin-shadow);
            margin-bottom: 30px;
        }

        .filter-form {
            display: flex;
            align-items: center;
            /* Center align to prevent overlap */
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            width: 30%;
        }

        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--fin-text-muted);
            margin-bottom: 8px;
            letter-spacing: 0.05em;
            min-height: 16px;
            /* Reserve space for label */
        }

        .date-inputs {
            display: flex;
            align-items: center;
            gap: 12px;
            /* background: #f8fafc; Removed wrapper bg */
            /* padding: 4px; Removed wrapper padding */
            /* border: 1px solid var(--fin-border); Removed wrapper border */
            /* border-radius: 8px; Removed radius */
        }

        .separator {
            color: var(--fin-text-muted);
            font-size: 13px;
            font-weight: 500;
            padding: 0 4px;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
            /* Add top margin to account for missing label */
        }

        /* Hero Grid */
        .finance-hero-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .finance-metric-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--fin-shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .metric-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        /* Card Variants */
        .primary-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: white;
        }

        .primary-gradient .metric-icon {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .primary-gradient .metric-meta {
            color: rgba(255, 255, 255, 0.8);
        }

        .primary-gradient h3 {
            color: rgba(255, 255, 255, 0.9);
        }

        .secondary-gradient {
            background: white;
            border-left: 4px solid var(--fin-secondary);
        }

        .secondary-gradient .metric-icon {
            background: #d1fae5;
            color: #059669;
        }

        .glass-card {
            background: white;
            border: 1px solid var(--fin-border);
        }

        .glass-card .metric-icon {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .finance-metric-card h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--fin-text-muted);
        }

        .metric-value {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
            margin: 4px 0;
        }

        .metric-meta {
            font-size: 13px;
            color: var(--fin-text-muted);
        }

        /* Content Grid */
        .finance-content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media screen and (max-width: 1024px) {
            .finance-content-grid {
                grid-template-columns: 1fr;
            }
        }

        .finance-card {
            background: white;
            border-radius: var(--fin-radius);
            box-shadow: var(--fin-shadow);
            display: flex;
            flex-direction: column;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--fin-border);
        }

        .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--fin-text-main);
        }

        .card-body {
            padding: 24px;
        }

        /* Modern Table */
        .fin-table-wrapper {
            overflow-x: auto;
        }

        .fin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .fin-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--fin-text-muted);
            font-weight: 600;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--fin-border);
        }

        .fin-table td {
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: var(--fin-text-main);
        }

        .fin-table tr:last-child td {
            border-bottom: none;
        }

        .text-right {
            text-align: right;
        }

        .font-medium {
            font-weight: 500;
        }

        .font-bold {
            font-weight: 700;
        }

        .text-slate-800 {
            color: #1e293b;
        }

        .trend-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 600;
        }

        .trend-badge.positive {
            background: #ecfdf5;
            color: #059669;
        }

        /* Top Products List */
        .product-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .product-item:last-child {
            margin-bottom: 0;
        }

        .product-rank {
            width: 24px;
            height: 24px;
            background: #f1f5f9;
            color: #64748b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            color: #334155;
        }

        .product-bar-bg {
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            width: 100%;
            overflow: hidden;
        }

        .product-bar-fill {
            height: 100%;
            background: var(--fin-primary);
            border-radius: 3px;
        }

        .product-stats {
            text-align: right;
            min-width: 60px;
        }

        .stat-value {
            display: block;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-label {
            font-size: 11px;
            color: #94a3b8;
        }

        /* Category Pills */
        .categories-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .category-pill {
            background: #f8fafc;
            border: 1px solid var(--fin-border);
            padding: 8px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .category-pill:hover {
            border-color: var(--fin-primary);
            background: #eef2ff;
        }

        .cat-name {
            font-weight: 500;
            font-size: 13px;
        }

        .cat-count {
            background: white;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--fin-primary);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--fin-text-muted);
            font-style: italic;
        }
    </style>
    <?php
}

