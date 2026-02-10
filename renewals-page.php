<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add the Renewals menu page
function satguru_add_renewals_menu() {
    add_menu_page(
        'Renewals Dashboard',
        'Renewals',
        'manage_options',
        'satguru-renewals',
        'satguru_renewals_page',
        'dashicons-update',
        7  // Position after Admin menu
    );
}
add_action('admin_menu', 'satguru_add_renewals_menu');

// Handle CSV Export
function satguru_handle_csv_export() {
    if (isset($_GET['download_csv']) && check_admin_referer('satguru_export_csv', 'export_nonce')) {
        // Get filter parameters
        $show_completed = isset($_GET['show_completed']);
        $show_future = isset($_GET['show_future']);
        $hide_trials = isset($_GET['hide_trials']);
        $completion_date = isset($_GET['completion_date']) ? sanitize_text_field($_GET['completion_date']) : '';
        $search_query = isset($_GET['search_query']) ? strtolower(sanitize_text_field($_GET['search_query'])) : '';
        
        // Re-run the main query logic
        if ($show_future) {
            $args = array(
                'post_type'      => 'shop_order',
                'post_status'    => array('wc-processing'),
                'posts_per_page' => -1,
            );
        } else {
            $args = array(
                'post_type'      => 'shop_order',
                'post_status'    => array($show_completed ? 'wc-completed' : 'wc-processing'),
                'posts_per_page' => -1, // Get all orders for CSV
            );
        }
        
        $orders = wc_get_orders($args);
        
        // Apply filters
        if ($show_future) {
            $filtered_orders = array_filter($orders, function($order) use ($completion_date, $hide_trials) {
                // Check if order has scheduled pauses - exclude if it does
                $scheduled_pauses = get_post_meta($order->get_id(), '_scheduled_pause_dates', true);
                if (!empty($scheduled_pauses) && is_array($scheduled_pauses)) {
                    return false;
                }
                
                $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
                $total_tiffins = 0;
                foreach ($order->get_items() as $item) {
                    foreach ($item->get_meta_data() as $meta) {
                        if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                            $total_tiffins = intval($meta->value);
                        }
                    }
                }
                
                $is_trial = ($total_tiffins == 1 && $remaining_tiffins == 1);
                if ($hide_trials && $is_trial) {
                    return false;
                }
                
                $completion_date_calculated = calculate_order_completion_date($order);
                if (!$completion_date_calculated) return false;
                
                if (!empty($completion_date)) {
                    return $completion_date_calculated === $completion_date;
                } else {
                    $today = strtotime(date('Y-m-d'));
                    $completion_timestamp = strtotime($completion_date_calculated);
                    $days_until_completion = ($completion_timestamp - $today) / (60 * 60 * 24);
                    return $days_until_completion >= 0 && $days_until_completion <= 30;
                }
            });
        } else {
            $filtered_orders = array_filter($orders, function($order) use ($show_completed) {
                $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
                $status_match = $show_completed ? $order->get_status() === 'completed' : $order->get_status() === 'processing';
                return $remaining_tiffins <= 5 && $status_match;
            });
        }
        
        // Apply Search Filter
        if (!empty($search_query)) {
            $filtered_orders = array_filter($filtered_orders, function($order) use ($search_query) {
                $searchable_data = array(
                    $order->get_id(),
                    strtolower($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    strtolower($order->get_billing_phone()),
                    strtolower($order->get_billing_email())
                );
                foreach ($searchable_data as $data) {
                    if (strpos(strtolower($data), $search_query) !== false) return true;
                }
                return false;
            });
        }
        
        // Set Headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=renewals-export-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        $headers = ['Order ID', 'Customer Name', 'Phone', 'Email', 'Products', 'Total Tiffins', 'Remaining Tiffins'];
        if ($show_future) {
            $headers[] = 'Expected Completion';
            $headers[] = 'Is Trial';
        } else {
            $headers[] = 'Status';
            if ($show_completed) $headers[] = 'Completion Date';
        }
        fputcsv($output, $headers);
        
        // Use a buffer to avoid memory issues with large datasets, though here we stream directly
        foreach ($filtered_orders as $order) {
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $phone = $order->get_billing_phone();
            $email = $order->get_billing_email();
            
            $total_tiffins = 0;
            $products = array();
            foreach ($order->get_items() as $item) {
                $products[] = $item->get_name();
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                        $total_tiffins = intval($meta->value);
                    }
                }
            }
            
            $row = [
                $order->get_id(),
                $customer_name,
                $phone,
                $email,
                implode(', ', $products),
                $total_tiffins,
                $remaining_tiffins
            ];
            
            if ($show_future) {
                $completion = calculate_order_completion_date($order);
                $row[] = $completion ? wp_date('F j, Y', strtotime($completion)) : 'N/A';
                $is_trial = ($total_tiffins == 1 && $remaining_tiffins == 1);
                $row[] = $is_trial ? 'Yes' : 'No';
            } else {
                $row[] = wc_get_order_status_name($order->get_status());
                if ($show_completed) {
                    // Logic for completion date same as display
                     $comp_date = '';
                     $order_notes = wc_get_order_notes(array('order_id' => $order->get_id()));
                     foreach ($order_notes as $note) {
                         if (strpos($note->content, 'Order status changed from Processing to Completed') !== false) {
                             $comp_date = wp_date('F j, Y, g:i a', strtotime($note->date_created));
                             break;
                         }
                     }
                     if (empty($comp_date)) {
                         $comp_date = wp_date('F j, Y, g:i a', strtotime($order->get_date_modified()));
                     }
                     $row[] = $comp_date;
                }
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'satguru_handle_csv_export');

// Function to calculate completion date for an order
function calculate_order_completion_date($order) {
    $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
    
    if ($remaining_tiffins <= 0) {
        return null; // Already completed
    }
    
    // Get order metadata
    $preferred_days = [];
    $start_date_value = '';

    foreach ($order->get_items() as $item) {
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, 'Prefered Days') !== false) {
                $preferred_days = parse_preferred_days_for_completion($meta->value);
            }
            // Check for Start Date or Delivery Date
            if (strpos($meta->key, 'Start Date') !== false || strpos($meta->key, 'Delivery Date') !== false) {
                $start_date_value = $meta->value;
            }
        }
    }
    
    if (empty($preferred_days)) {
        return null;
    }
    
    // Get delivery days
    $delivery_days = Satguru_Tiffin_Calculator::get_delivery_days();
    
    // Get last delivery day setting
    $end_day_name = get_option('satguru_delivery_end_day', 'friday');
    $days_map = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];
    $last_delivery_day_num = isset($days_map[$end_day_name]) ? $days_map[$end_day_name] : 5;
    
    // Calculate completion date
    $current_date = date('Y-m-d');
    $check_date = $current_date;
    
    // Use Start Date if it is in the future
    if (!empty($start_date_value)) {
        $start_ts = strtotime($start_date_value);
        if ($start_ts > strtotime($current_date)) {
            $check_date = date('Y-m-d', $start_ts);
        }
    }
    // We want to find the date the plan finishes (Last Tiffin Date)
    $tiffins_to_deliver = $remaining_tiffins;
    
    // Look forward up to 365 days to find completion date
    for ($i = 0; $i < 365 && $tiffins_to_deliver > 0; $i++) {
        $check_day_num = (int)date('w', strtotime($check_date));
        $daily_consumption = 0;
        
        // 1. Check if today represents a delivery
        if (in_array($check_day_num, $delivery_days) && in_array($check_day_num, $preferred_days)) {
            $daily_consumption++;
        }
        
        // 2. If it is the LAST delivery day, check for bundled weekend/gap deliveries
        if ($check_day_num === $last_delivery_day_num) {
            // Look ahead until we hit a delivery day again
            for ($k = 1; $k <= 7; $k++) {
                $next_day_num = ($check_day_num + $k) % 7;
                
                // If we hit a delivery day, stop looking ahead (cycle restarted)
                if (in_array($next_day_num, $delivery_days)) {
                    break;
                }
                
                // If it's a gap day AND the user wants it, add to today's consumption
                if (in_array($next_day_num, $preferred_days)) {
                    $daily_consumption++;
                }
            }
        }
        
        // Deduct combined consumption
        if ($daily_consumption > 0) {
            $tiffins_to_deliver -= $daily_consumption;
        }
        
        if ($tiffins_to_deliver <= 0) {
            return $check_date;
        }
        
        // Move to next day
        $check_date = date('Y-m-d', strtotime($check_date . ' +1 day'));
    }
    
    return null; // Could not calculate completion date
}

// Helper function to parse preferred days (simplified version)
function parse_preferred_days_for_completion($days_string) {
    $days_map = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];
    
    $days_string = strtolower($days_string);
    $preferred_days = [];

    if (strpos($days_string, ' - ') === false) {
        $day = trim($days_string);
        if (isset($days_map[$day])) {
            $preferred_days[] = $days_map[$day];
        }
    } else {
        $parts = explode(' - ', $days_string);
        
        if (count($parts) == 2) {
            $start_day = array_search(trim($parts[0]), array_keys($days_map));
            $end_day = array_search(trim($parts[1]), array_keys($days_map));
            
            if ($start_day !== false && $end_day !== false) {
                if ($end_day < $start_day) {
                    $end_day += 7;
                }
                
                for ($i = $start_day; $i <= $end_day; $i++) {
                    $preferred_days[] = $i % 7;
                }
            }
        } else {
            foreach ($parts as $day) {
                $day = trim($day);
                if (isset($days_map[$day])) {
                    $preferred_days[] = $days_map[$day];
                }
            }
        }
    }

    sort($preferred_days);
    return $preferred_days;
}

// Helper function to calculate deliveries for a day (simplified version)
function calculate_day_deliveries_for_completion($current_day_num, $preferred_days, $delivery_days) {
    $deliveries = 0;
    $last_delivery_day = max($delivery_days);
    $first_delivery_day = min($delivery_days);

    // If it's a preferred day and a delivery day
    if (in_array($current_day_num, $preferred_days) && in_array($current_day_num, $delivery_days)) {
        $deliveries++;
    }

    // If it's the last delivery day of the week (e.g., Friday)
    if ($current_day_num == $last_delivery_day) {
        foreach ($preferred_days as $pref_day) {
            if ($pref_day > $last_delivery_day || $pref_day < $first_delivery_day) {
                $deliveries++;
            }
        }
    }

    return $deliveries;
}

// Renewals page callback function
function satguru_renewals_page() {
    // Get filter parameters
    $show_completed = isset($_GET['show_completed']);
    $show_future = isset($_GET['show_future']);
    $hide_trials = isset($_GET['hide_trials']); // New Parameter
    $completion_date = isset($_GET['completion_date']) ? sanitize_text_field($_GET['completion_date']) : '';
    
    // Get orders based on current/future filter
    if ($show_future) {
        // For future renewals, get processing orders only
        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array('wc-processing'),
            'posts_per_page' => -1,
        );
    } else {
        // For current renewals, use existing logic
        $args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array($show_completed ? 'wc-completed' : 'wc-processing'),
            'posts_per_page' => -1,
        );
    }
    
    $orders = wc_get_orders($args);
    
    // Filter orders based on renewal criteria
    if ($show_future) {
        // Filter for future renewals based on completion date
        $filtered_orders = array_filter($orders, function($order) use ($completion_date, $hide_trials) {
            // Check if order has scheduled pauses - exclude if it does
            $scheduled_pauses = get_post_meta($order->get_id(), '_scheduled_pause_dates', true);
            if (!empty($scheduled_pauses) && is_array($scheduled_pauses)) {
                return false; // Exclude orders with scheduled pauses
            }
            
            // Check for Trial Order logic: Total=1, Remaining=1, Start Date=Today
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            $total_tiffins = 0;
            $start_date_value = '';
            
            foreach ($order->get_items() as $item) {
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                        $total_tiffins = intval($meta->value);
                    }
                    if (strpos($meta->key, 'Start Date') !== false || strpos($meta->key, 'Delivery Date') !== false) {
                        $start_date_value = $meta->value;
                    }
                }
            }
            
            $is_trial = ($total_tiffins == 1 && $remaining_tiffins == 1);
                         
            if ($hide_trials && $is_trial) {
                return false;
            }
            
            $completion_date_calculated = calculate_order_completion_date($order);
            
            if (!$completion_date_calculated) {
                return false;
            }
            
            if (!empty($completion_date)) {
                // Filter by exact date only (not ±7 days)
                return $completion_date_calculated === $completion_date;
            } else {
                // Show orders completing in the next 30 days
                $today = strtotime(date('Y-m-d'));
                $completion_timestamp = strtotime($completion_date_calculated);
                $days_until_completion = ($completion_timestamp - $today) / (60 * 60 * 24);
                
                return $days_until_completion >= 0 && $days_until_completion <= 30;
            }
        });
    } else {
        // Current renewals logic (5 or fewer remaining tiffins)
        $filtered_orders = array_filter($orders, function($order) use ($show_completed) {
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            $status_match = $show_completed ? 
                           $order->get_status() === 'completed' : 
                           $order->get_status() === 'processing';
            
            return $remaining_tiffins <= 5 && $status_match;
        });
    }

    // Get pagination parameters
    $items_per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    
    // Items per page dropdown options
    $per_page_options = array(10, 20, 50, 100, 200, 300, 500);
    ?>
    <div class="wrap">
        <h1>Renewals Dashboard</h1>
        
        <p><?php echo $show_future ? 'Orders completing in the future (excludes orders with scheduled pauses)' : 'Orders with 5 or fewer remaining tiffins'; ?></p>
        
        <!-- Status Toggle and Search Form -->
        <div class="order-filters">
            <form method="get" class="order-filter-form">
                <input type="hidden" name="page" value="satguru-renewals">
                <div class="filter-row">
                    <!-- Renewal Type Toggle -->
                    <div class="renewal-type-container">
                        <div class="renewal-type-toggle">
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       name="show_future" 
                                       <?php checked(isset($_GET['show_future']), true); ?>
                                       onchange="this.form.submit()">
                                <span class="toggle-slider round"></span>
                            </label>
                            <span class="toggle-label">
                                <?php echo isset($_GET['show_future']) ? 'Future Renewals' : 'Current Renewals'; ?>
                            </span>
                        </div>
                    </div>
                     
                    <?php if ($show_future): ?>
                    <!-- Hide Trials Toggle (only for Future Renewals) -->
                    <div class="status-toggle-container">
                        <div class="status-toggle">
                            <label class="switch">
                                <input type="checkbox" 
                                       name="hide_trials" 
                                       <?php checked(isset($_GET['hide_trials']), true); ?>
                                       onchange="this.form.submit()">
                                <span class="slider round"></span>
                            </label>
                            <span class="toggle-label">
                                Hide Trials (Today)
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$show_future): ?>
                    <!-- Status Toggle (only for current renewals) -->
                    <div class="status-toggle-container">
                        <div class="status-toggle">
                            <label class="switch">
                                <input type="checkbox" 
                                       name="show_completed" 
                                       <?php checked(isset($_GET['show_completed']), true); ?>
                                       onchange="this.form.submit()">
                                <span class="slider round"></span>
                            </label>
                            <span class="toggle-label">
                                <?php echo isset($_GET['show_completed']) ? 'Completed Orders' : 'Processing Orders'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($show_future): ?>
                    <!-- Date Picker for Future Renewals -->
                    <div class="date-filter">
                        <label for="completion_date">Exact Completion Date:</label>
                        <input type="date" 
                               id="completion_date"
                               name="completion_date" 
                               value="<?php echo esc_attr($completion_date); ?>"
                               min="<?php 
                                   $min_date = date('Y-m-d');
                                   if (current_time('H') >= 22) {
                                       $min_date = date('Y-m-d', strtotime('+1 day'));
                                   }
                                   echo $min_date; 
                               ?>"
                               max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <!-- Search Input -->
                    <div class="search-filter">
                        <div class="search-input-wrapper">
                            <span class="dashicons dashicons-search search-icon"></span>
                            <input type="text" 
                                   name="search_query" 
                                   placeholder="Search orders..." 
                                   value="<?php echo isset($_GET['search_query']) ? esc_attr($_GET['search_query']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Entries Per Page -->
                    <div class="entries-filter">
                        <select name="per_page" onchange="this.form.submit()" class="enhanced-select">
                            <?php foreach ($per_page_options as $option): ?>
                                <option value="<?php echo $option; ?>" 
                                        <?php selected($items_per_page, $option); ?>>
                                    <?php echo $option; ?> per page
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="filter-actions">
                        <button type="submit" class="button button-primary">Search</button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=satguru-renewals')); ?>" 
                           class="button button-secondary">Reset</a>
                        <!-- Export Button -->
                        <a href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['download_csv' => true, 'export_nonce' => wp_create_nonce('satguru_export_csv')]))); ?>" 
                           class="button button-primary" 
                           style="background-color: #2271b1; border-color: #2271b1;">
                            <span class="dashicons dashicons-download" style="line-height: 28px; font-size: 16px; margin-right: 4px;"></span>
                            Export to CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // Apply search filter if query exists
        $search_query = isset($_GET['search_query']) ? strtolower(sanitize_text_field($_GET['search_query'])) : '';
        
        if (!empty($search_query)) {
            $filtered_orders = array_filter($filtered_orders, function($order) use ($search_query) {
                $searchable_data = array(
                    $order->get_id(),
                    strtolower($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    strtolower($order->get_billing_phone()),
                    strtolower($order->get_billing_email())
                );
                
                foreach ($searchable_data as $data) {
                    if (strpos(strtolower($data), $search_query) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Calculate pagination
        $total_items = count($filtered_orders);
        $total_pages = ceil($total_items / $items_per_page);
        
        // Ensure current page doesn't exceed total pages
        $current_page = min($current_page, max(1, $total_pages));
        
        // Sort orders by completion date (for completed orders or future renewals)
        if ($show_completed || $show_future) {
            $orders_with_dates = [];
            
            foreach ($filtered_orders as $order) {
                $sort_timestamp = 0;
                
                if ($show_future) {
                    // Sort by predicted completion date
                    $completion_date_calculated = calculate_order_completion_date($order);
                    if ($completion_date_calculated) {
                        $sort_timestamp = strtotime($completion_date_calculated);
                    }
                } else {
                    // Sort by actual completion date
                    $order_notes = wc_get_order_notes(array('order_id' => $order->get_id()));
                    foreach ($order_notes as $note) {
                        if (strpos($note->content, 'Order status changed from Processing to Completed') !== false) {
                            $sort_timestamp = strtotime($note->date_created);
                            break;
                        }
                    }
                    
                    if (empty($sort_timestamp)) {
                        $sort_timestamp = strtotime($order->get_date_modified());
                    }
                }
                
                $orders_with_dates[] = [
                    'order' => $order,
                    'timestamp' => $sort_timestamp ?: 0
                ];
            }
            
            // Sort orders by date (newest first for completed, soonest first for future)
            usort($orders_with_dates, function($a, $b) use ($show_future) {
                if ($show_future) {
                    return $a['timestamp'] - $b['timestamp']; // ASC order (soonest first)
                } else {
                    return $b['timestamp'] - $a['timestamp']; // DESC order (newest first)
                }
            });
            
            // Rebuild the filtered_orders array with the sorted orders
            $filtered_orders = array_map(function($item) {
                return $item['order'];
            }, $orders_with_dates);
        }
        
        // Calculate the slice of orders to display
        $start = ($current_page - 1) * $items_per_page;
        $displayed_orders = array_slice($filtered_orders, $start, $items_per_page);
        
        // Calculate displayed items range
        $start_item = $start + 1;
        $end_item = min($start + $items_per_page, $total_items);
        ?>

        <!-- Pagination Info -->
        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    Showing <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo $total_items; ?> entries
                </span>
                <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php
                        // Build query args for pagination
                        $query_args = array_filter(array(
                            'per_page' => $items_per_page,
                            'show_completed' => isset($_GET['show_completed']) ? '1' : null,
                            'show_future' => isset($_GET['show_future']) ? '1' : null,
                            'hide_trials' => isset($_GET['hide_trials']) ? '1' : null,
                            'completion_date' => !empty($completion_date) ? $completion_date : null,
                            'search_query' => !empty($search_query) ? $_GET['search_query'] : null
                        ));
                        
                        $first_url = add_query_arg(array_merge($query_args, ['paged' => 1]));
                        $prev_url = add_query_arg(array_merge($query_args, ['paged' => max(1, $current_page - 1)]));
                        $next_url = add_query_arg(array_merge($query_args, ['paged' => min($total_pages, $current_page + 1)]));
                        $last_url = add_query_arg(array_merge($query_args, ['paged' => $total_pages]));
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

        <!-- Orders Table -->
        <div class="table-container">
            <div class="table-scroll">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Products</th>
                            <th>Total Tiffins</th>
                            <th>Remaining Tiffins</th>
                            <?php if ($show_future): ?>
                            <th>Expected Completion</th>
                            <?php else: ?>
                            <th>Status</th>
                            <?php if ($show_completed): ?>
                            <th>Completion Date</th>
                            <?php endif; ?>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($displayed_orders as $order) {
                            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
                            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                            $phone = $order->get_billing_phone();
                            $email = $order->get_billing_email();
                            
                            // Get total tiffins
                            $total_tiffins = 0;
                            $start_date_value = '';
                            $products = array();
                            foreach ($order->get_items() as $item) {
                                $products[] = $item->get_name();
                                foreach ($item->get_meta_data() as $meta) {
                                    if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                                        $total_tiffins = intval($meta->value);
                                    }
                                    if (strpos($meta->key, 'Start Date') !== false || strpos($meta->key, 'Delivery Date') !== false) {
                                        $start_date_value = $meta->value;
                                    }
                                }
                            }
                            
                            // Identify Trial Order
                            $is_trial = ($total_tiffins == 1 && $remaining_tiffins == 1);
                            
                            // Get completion date for completed orders or calculate for future orders
                            $completion_date = '';
                            if ($show_future) {
                                $completion_date_calculated = calculate_order_completion_date($order);
                                if ($completion_date_calculated) {
                                    $completion_date = wp_date('F j, Y', strtotime($completion_date_calculated));
                                    $days_until = floor((strtotime($completion_date_calculated) - time()) / (60 * 60 * 24));
                                    if ($days_until >= 0) {
                                        $completion_date .= ' (' . $days_until . ' days)';
                                    }
                                }
                            } elseif ($show_completed) {
                                $order_notes = wc_get_order_notes(array('order_id' => $order->get_id()));
                                foreach ($order_notes as $note) {
                                    if (strpos($note->content, 'Order status changed from Processing to Completed') !== false) {
                                        $completion_date = wp_date('F j, Y, g:i a', strtotime($note->date_created));
                                        break;
                                    }
                                }
                                
                                if (empty($completion_date)) {
                                    $completion_date = wp_date('F j, Y, g:i a', strtotime($order->get_date_modified()));
                                }
                            }
                            ?>
                            <tr class="<?php echo $is_trial ? 'is-trial' : ''; ?>">
                                <td>#<?php echo $order->get_id(); ?></td>
                                <td><?php echo esc_html($customer_name); ?></td>
                                <td><?php echo esc_html($phone); ?></td>
                                <td><?php echo esc_html($email); ?></td>
                                <td><?php echo esc_html(implode(', ', $products)); ?></td>
                                <td><?php echo $total_tiffins; ?></td>
                                <td class="remaining-tiffins <?php echo ($remaining_tiffins <= 5 && !$show_future) ? 'low' : ''; ?>">
                                    <?php echo $remaining_tiffins; ?>
                                </td>
                                <?php if ($show_future): ?>
                                <td class="completion-date">
                                    <?php echo $completion_date ?: 'Unable to calculate'; ?>
                                    <?php if ($is_trial): ?>
                                        <span class="trial-badge">Trial</span>
                                    <?php endif; ?>
                                </td>
                                <?php else: ?>
                                <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                                <?php if ($show_completed): ?>
                                <td><?php echo $completion_date; ?></td>
                                <?php endif; ?>
                                <?php endif; ?>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" 
                                       class="button">View Order</a>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Pagination -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <!-- Same pagination links as above -->
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
    </div>
    <?php
}

// Add custom CSS for the renewals page
function satguru_renewals_admin_styles() {
    if (isset($_GET['page']) && $_GET['page'] === 'satguru-renewals') {
        ?>
        <style>
            /* Main Container Styles */
            .wrap {
                margin: 20px 20px 0 2px;
            }

            .wrap h1 {
                font-size: 24px;
                margin-bottom: 10px;
            }

            /* Filters Container */
            .order-filters {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #e2e4e7;
            }

            .filter-row {
                display: flex;
                align-items: center;
                gap: 20px;
                flex-wrap: wrap;
            }

            /* Renewal Type Toggle Styles */
            .renewal-type-container {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 12px 18px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
            }

            .renewal-type-toggle {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 56px;
                height: 30px;
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
                background-color: rgba(255,255,255,0.3);
                transition: .3s;
                border-radius: 30px;
            }

            .toggle-slider:before {
                position: absolute;
                content: "";
                height: 24px;
                width: 24px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }

            input:checked + .toggle-slider {
                background-color: rgba(255,255,255,0.5);
            }

            input:checked + .toggle-slider:before {
                transform: translateX(26px);
            }

            .toggle-label {
                color: white;
                font-weight: 600;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }

            /* Status Toggle Styles */
            .status-toggle-container {
                background: #f8f9fa;
                padding: 10px 15px;
                border-radius: 6px;
                border: 1px solid #e2e4e7;
            }

            .status-toggle {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 26px;
            }

            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .3s;
                border-radius: 34px;
            }

            .slider:before {
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

            input:checked + .slider {
                background-color: #2271b1;
            }

            input:checked + .slider:before {
                transform: translateX(24px);
            }

            .status-toggle .toggle-label {
                font-weight: 500;
                color: #1d2327;
            }

            /* Date Filter Styles */
            .date-filter {
                background: #e8f4fd;
                padding: 12px 15px;
                border-radius: 6px;
                border: 1px solid #c3e6ff;
            }

            .date-filter label {
                display: block;
                font-weight: 500;
                color: #135e96;
                margin-bottom: 5px;
                font-size: 13px;
            }

            .date-filter input[type="date"] {
                padding: 6px 10px;
                border: 1px solid #c3e6ff;
                border-radius: 4px;
                background: white;
                font-size: 14px;
                color: #1d2327;
                min-width: 140px;
            }

            .date-filter input[type="date"]:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }

            /* Search Input Styles */
            .search-filter {
                flex-grow: 1;
                max-width: 400px;
            }

            .search-input-wrapper {
                position: relative;
            }

            .search-input-wrapper .search-icon {
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: #787c82;
            }

            .search-filter input[type="text"] {
                width: 100%;
                padding: 8px 12px 8px 35px;
                border: 1px solid #e2e4e7;
                border-radius: 6px;
                font-size: 14px;
                line-height: 2;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                transition: all 0.3s ease;
            }

            .search-filter input[type="text"]:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }

            /* Entries Filter Styles */
            .entries-filter select {
                min-width: 120px;
                padding: 8px 12px;
                border-radius: 6px;
                border: 1px solid #e2e4e7;
                background-color: #fff;
                font-size: 14px;
                line-height: 2;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }

            /* Table Styles */
            .table-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                margin-top: 20px;
                border: 1px solid #e2e4e7;
            }

            .table-scroll {
                overflow-x: auto;
                border-radius: 8px;
            }

            .widefat {
                border: none;
                margin: 0;
            }

            .widefat th {
                background: #f8f9fa;
                padding: 12px;
                font-weight: 600;
                color: #1d2327;
                border-bottom: 1px solid #e2e4e7;
            }

            .widefat td {
                padding: 12px;
                vertical-align: middle;
            }

            .widefat tr:hover {
                background-color: #f8f9fa;
            }

            /* Pagination Styles */
            .tablenav {
                padding: 15px;
                background: #fff;
                border-top: 1px solid #e2e4e7;
            }

            .tablenav-pages {
                float: right;
                margin: 0;
            }

            .pagination-links .button {
                padding: 4px 10px;
                margin: 0 2px;
                border-radius: 4px;
                border: 1px solid #e2e4e7;
                background: #fff;
                color: #2271b1;
                transition: all 0.2s ease;
            }
            
            /* Trial Order Styles */
            tr.is-trial {
                background-color: #e8f5e9 !important;
            }
            
            tr.is-trial:hover {
                background-color: #c8e6c9 !important;
            }
            
            .trial-badge {
                display: inline-block;
                background-color: #4caf50;
                color: white;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 8px;
                vertical-align: middle;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .pagination-links .button:hover:not(.disabled) {
                background: #f0f0f1;
                border-color: #2271b1;
            }

            .pagination-links .button.disabled {
                opacity: 0.5;
                cursor: default;
                color: #a7aaad;
            }

            .displaying-num {
                color: #646970;
                font-size: 13px;
                margin-right: 15px;
            }

            /* Status and Action Buttons */
            .remaining-tiffins.low {
                color: #d63638;
                font-weight: 600;
                background: #fcf0f1;
                padding: 4px 8px;
                border-radius: 4px;
            }

            .completion-date {
                color: #135e96;
                font-weight: 500;
                background: #e8f4fd;
                padding: 4px 8px;
                border-radius: 4px;
            }

            .button-primary {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
                padding: 6px 12px;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .button-primary:hover {
                background: #135e96;
                border-color: #135e96;
            }

            .button-secondary {
                background: #f6f7f7;
                border-color: #2271b1;
                color: #2271b1;
                padding: 6px 12px;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .button-secondary:hover {
                background: #f0f0f1;
                border-color: #135e96;
                color: #135e96;
            }

            /* Responsive Adjustments */
            @media screen and (max-width: 782px) {
                .filter-row {
                    flex-direction: column;
                    align-items: stretch;
                }

                .search-filter,
                .entries-filter,
                .status-toggle-container,
                .renewal-type-container,
                .date-filter {
                    max-width: 100%;
                    margin-bottom: 10px;
                }

                .filter-actions {
                    display: flex;
                    gap: 10px;
                }

                .button {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'satguru_renewals_admin_styles');