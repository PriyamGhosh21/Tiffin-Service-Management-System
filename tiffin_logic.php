<?php
if (!defined('ABSPATH')) {
    exit;
}

class Satguru_Tiffin_Calculator {
    
    /**
     * Calculate remaining tiffins for an order
     */
    public static function calculate_initial_remaining_tiffins($order) {
        $total_tiffins = 0;
        $preferred_days = [];
        $start_date = '';

        // Get order metadata
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                // Get total tiffins from metadata
                if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                    $total_tiffins = intval($meta->value);
                }
                
                // Get start date
                if (strpos($meta->key, 'Start Date') !== false || 
                    strpos($meta->key, 'Delivery Date') !== false) {
                    $start_date = date('Y-m-d', strtotime($meta->value));
                }
                
                // Get preferred days
                if (strpos($meta->key, 'Prefered Days') !== false) {
                    $preferred_days = self::parse_preferred_days($meta->value);
                }
            }
        }
        
        // If we don't have all required data, return 0
        if (empty($start_date) || empty($preferred_days) || $total_tiffins <= 0) {
            return 0;
        }

        return self::calculate_remaining($total_tiffins, $preferred_days, $start_date);
    }


    /**
 * Calculate remaining tiffins based on history
 */
    public static function calculate_remaining_tiffins($order) {

    date_default_timezone_set('America/Toronto');
    // Get start date from order
    $start_date = '';
    foreach ($order->get_items() as $item) {
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, 'Start Date') !== false || 
                strpos($meta->key, 'Delivery Date') !== false) {
                $start_date = date('Y-m-d', strtotime($meta->value));
                break 2;
            }
        }
    }

    $today = date('Y-m-d');
    $current_time = time();
    $cutoff_hour = (int)get_option('satguru_cutoff_hour', '17');
    $cutoff_minute = (int)get_option('satguru_cutoff_minute', '00');
    $cutoff_time = strtotime($today . " {$cutoff_hour}:{$cutoff_minute}:00");
    
    // If we're before the start date, return initial count
    if (!empty($start_date) && strtotime($today) < strtotime($start_date)) {
        return self::calculate_initial_remaining_tiffins($order);
    }

    // Get count history
    $count_history = $order->get_meta('tiffin_count_history', true);

    // Check if order status is not 'processing'
    if ($order->get_status() !== 'processing') {
        $count_history = $order->get_meta('tiffin_count_history', true);
        if (is_array($count_history) && !empty($count_history)) {
            $dates = array_keys($count_history);
            rsort($dates);
            return $count_history[$dates[0]]['remaining_tiffins'];
        }
        return self::calculate_initial_remaining_tiffins($order);
    }

    $count_history = $order->get_meta('tiffin_count_history', true);

    // If we have history
    if (is_array($count_history) && !empty($count_history)) {
        $dates = array_keys($count_history);
        rsort($dates);
        $last_recorded_date = $dates[0];
        $last_count = $count_history[$last_recorded_date]['remaining_tiffins'];

        // Before cutoff time OR if last record is from today, use the last recorded count
        if ($current_time < $cutoff_time || $last_recorded_date === $today) {
            return $last_count;
        }
    }

    // If no history exists, calculate normally
    if (!is_array($count_history) || empty($count_history)) {
        return self::calculate_initial_remaining_tiffins($order);
    }

    // Get the last recorded count
    $dates = array_keys($count_history);
    rsort($dates);
    $last_recorded_date = $dates[0];
    $last_count = $count_history[$last_recorded_date]['remaining_tiffins'];

    // If last recorded date is today, return that count
    if ($last_recorded_date === $today) {
        return $last_count;
    }

    // Get preferred days
    $preferred_days = [];
    $delivery_days = self::get_delivery_days();
    
    foreach ($order->get_items() as $item) {
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, 'Prefered Days') !== false) {
                $preferred_days = self::parse_preferred_days($meta->value);
                break 2;
            }
        }
    }

    // Calculate deliveries between last recorded date and today
    $last_timestamp = strtotime($last_recorded_date);
    $today_timestamp = strtotime($today);
    $days_passed = floor(($today_timestamp - $last_timestamp) / (60 * 60 * 24));

    $deliveries = 0;
    for ($i = 1; $i <= $days_passed; $i++) {
        $check_date = date('Y-m-d', strtotime("+{$i} days", $last_timestamp));
        
        // Skip if check date is before start date
        if (!empty($start_date) && strtotime($check_date) < strtotime($start_date)) {
            continue;
        }
        
        $check_day_num = date('w', strtotime($check_date));
        
        if (in_array($check_day_num, $delivery_days) && in_array($check_day_num, $preferred_days)) {
            $deliveries += self::calculate_day_deliveries($check_day_num, $preferred_days, $delivery_days);
        }
    }

    return max(0, $last_count - $deliveries);
}

/**
 * Save daily tiffin count for orders
 */
public static function save_daily_tiffin_count() {
    // Set timezone to Toronto
    date_default_timezone_set('America/Toronto');
    $args = array(
        'post_type'      => 'shop_order',
        'post_status'    => array_keys(wc_get_order_statuses()),
        'posts_per_page' => -1,
    );

    $orders = wc_get_orders($args);
    $today = date('Y-m-d');
    $current_time = time();
    $cutoff_hour = (int)get_option('satguru_cutoff_hour', '17');
    $cutoff_minute = (int)get_option('satguru_cutoff_minute', '00');
    $cutoff_time = strtotime($today . " {$cutoff_hour}:{$cutoff_minute}:00");

    // Only proceed if we're after cutoff time
    if ($current_time < $cutoff_time) {
        return;
    }

    foreach ($orders as $order) {
        // Skip completed or cancelled orders
        if ($order->get_status() === 'completed' || $order->get_status() === 'cancelled') {
            continue;
        }

        // Get start date
        $start_date = '';
        $preferred_days = [];
        
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Start Date') !== false || 
                    strpos($meta->key, 'Delivery Date') !== false) {
                    $start_date = date('Y-m-d', strtotime($meta->value));
                }
                
                if (strpos($meta->key, 'Prefered Days') !== false) {
                    $preferred_days = self::parse_preferred_days($meta->value);
                }
            }
        }

        // Skip if today is before start date
        if (!empty($start_date) && strtotime($today) < strtotime($start_date)) {
            continue;
        }

        // Get existing history
        $history_key = 'tiffin_count_history';
        $count_history = $order->get_meta($history_key, true);
        if (!is_array($count_history)) {
            $count_history = array();
        }

        // Check if we already have today's count
        if (isset($count_history[$today])) {
            continue; // Skip if already saved today
        }

        $delivery_days = self::get_delivery_days();
        $remaining_tiffins = 0;
        
        // Check if order was paused during the delivery cutoff time
        $was_paused_during_delivery = self::was_order_paused_during_delivery($order, $today, $cutoff_time);
        
        // Handle orders with history differently from those without
        if (!empty($count_history)) {
            // Get the last recorded count
            $dates = array_keys($count_history);
            rsort($dates);
            $last_date = $dates[0];
            $last_count = $count_history[$last_date]['remaining_tiffins'];
            
            if ($was_paused_during_delivery) {
                // If order was paused during delivery time, keep the same count and set boxes_delivered to 0
                $remaining_tiffins = $last_count;
                $boxes_delivered = 0;
            } else {
                // Only deduct for today if it's both a delivery day and a preferred day
                $today_day_num = date('w', strtotime($today));
                
                if (in_array($today_day_num, $delivery_days) && in_array($today_day_num, $preferred_days)) {
                    $last_count -= self::calculate_day_deliveries($today_day_num, $preferred_days, $delivery_days);
                }
                
                $remaining_tiffins = max(0, $last_count);
                $boxes_delivered = self::calculate_boxes_for_date($order, $today);
            }
        } else {
            // No history - use current calculation directly
            $remaining_tiffins = self::calculate_remaining_tiffins($order);
            $boxes_delivered = $was_paused_during_delivery ? 0 : self::calculate_boxes_for_date($order, $today);
        }
        
        // Save today's count
        $count_history[$today] = array(
            'remaining_tiffins' => $remaining_tiffins,
            'delivery_days' => $delivery_days,
            'boxes_delivered' => $boxes_delivered
        );
        
        $order->update_meta_data($history_key, $count_history);
        
        // Only check for completion if order is not paused and has valid remaining count
        $current_status = $order->get_status();
        if ($current_status !== 'paused' && $remaining_tiffins <= 0 && $current_status !== 'completed') {
            $order->update_status('completed', 'Order automatically completed - all tiffins delivered.');
        }
        
        $order->save();
    }
}

/**
 * Check if order was paused during the delivery cutoff time
 */
private static function was_order_paused_during_delivery($order, $date, $cutoff_time) {
    // Get order status transitions for the given date
    $order_notes = wc_get_order_notes(array(
        'order_id' => $order->get_id(),
        'type' => 'internal'
    ));
    
    $date_start = strtotime($date . ' 00:00:00');
    $date_end = strtotime($date . ' 23:59:59');
    
    $status_changes = array();
    
    foreach ($order_notes as $note) {
        $note_time = strtotime($note->date_created);
        
        // Only look at notes from the given date
        if ($note_time >= $date_start && $note_time <= $date_end) {
            // Check if this is a status change note
            if (strpos($note->content, 'Order status changed') !== false) {
                if (strpos($note->content, 'to Paused') !== false) {
                    $status_changes[] = array('time' => $note_time, 'status' => 'paused');
                } elseif (strpos($note->content, 'to Processing') !== false) {
                    $status_changes[] = array('time' => $note_time, 'status' => 'processing');
                }
            }
        }
    }
    
    // Sort status changes by time
    usort($status_changes, function($a, $b) {
        return $a['time'] - $b['time'];
    });
    
    // If no status changes today, check current status
    if (empty($status_changes)) {
        return $order->get_status() === 'paused';
    }
    
    // Check status at cutoff time
    $status_at_cutoff = null;
    foreach ($status_changes as $change) {
        if ($change['time'] <= $cutoff_time) {
            $status_at_cutoff = $change['status'];
        }
    }
    
    // If no status change before cutoff, use the order's status from start of day
    if ($status_at_cutoff === null) {
        // Get the last status change before today
        $previous_notes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'type' => 'internal'
        ));
        
        foreach ($previous_notes as $note) {
            $note_time = strtotime($note->date_created);
            if ($note_time < $date_start && strpos($note->content, 'Order status changed') !== false) {
                if (strpos($note->content, 'to Paused') !== false) {
                    $status_at_cutoff = 'paused';
                    break;
                } elseif (strpos($note->content, 'to Processing') !== false) {
                    $status_at_cutoff = 'processing';
                    break;
                }
            }
        }
        
        // If still no status found, use current status
        if ($status_at_cutoff === null) {
            $status_at_cutoff = $order->get_status();
        }
    }
    
    return $status_at_cutoff === 'paused';
}

    /**
     * Get configured delivery days
     */
    public static function get_delivery_days() {
        $days_map = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];
        
        $start_day = get_option('satguru_delivery_start_day', 'monday');
        $end_day = get_option('satguru_delivery_end_day', 'friday');
        
        $start_num = $days_map[$start_day];
        $end_num = $days_map[$end_day];
        
        $delivery_days = [];
        
        // If end day is before start day (e.g., Sunday), adjust the range
        if ($end_num < $start_num) {
            $end_num += 7;
        }
        
        // Generate array of delivery days
        for ($i = $start_num; $i <= $end_num; $i++) {
            $delivery_days[] = $i % 7;
        }
        
        return $delivery_days;
    }
    
    /**
     * Parse preferred days string into array of day numbers
     */
    public static function parse_preferred_days($days_string) {
        $days_map = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];
        
        $days_string = strtolower($days_string);
        $preferred_days = [];

        // Check for single day case (no separator)
        if (strpos($days_string, ' - ') === false) {
            $day = trim($days_string);
            if (isset($days_map[$day])) {
                $preferred_days[] = $days_map[$day];
            }
        } else {
            $parts = explode(' - ', $days_string);
            
            // Handle ranges (e.g., "Monday - Friday")
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
            } 
            // Handle specific days (e.g., "Monday - Wednesday - Friday")
            else {
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

    /**
     * Calculate deliveries for a specific day
     */
    private static function calculate_day_deliveries($current_day_num, $preferred_days, $delivery_days) {
        $deliveries = 0;
        $last_delivery_day = max($delivery_days);
        $first_delivery_day = min($delivery_days);
    
        // If it's a preferred day and a delivery day
        if (in_array($current_day_num, $preferred_days) && in_array($current_day_num, $delivery_days)) {
            $deliveries++;
        }
    
        // If it's the last delivery day of the week (e.g., Friday)
        if ($current_day_num == $last_delivery_day) {
            // Count preferred days that fall after this day until next first delivery day
            foreach ($preferred_days as $pref_day) {
                // Check if preferred day is after last delivery day OR
                // if it's before first delivery day of next week
                if ($pref_day > $last_delivery_day || $pref_day < $first_delivery_day) {
                    $deliveries++;
                }
            }
        }
    
        // If it's the first delivery day of the week
        if ($current_day_num == $first_delivery_day) {
            // Count preferred days that fall before this day
            foreach ($preferred_days as $pref_day) {
                if ($pref_day < $first_delivery_day && $pref_day > $last_delivery_day) {
                    $deliveries++;
                }
            }
        }
    
        return $deliveries;
    }
    
    /**
     * Calculate remaining tiffins based on start date and preferred days
     */
    private static function calculate_remaining($total_tiffins, $preferred_days, $start_date) {
        // Set timezone from settings
        $timezone = get_option('satguru_timezone', 'America/Toronto');
        date_default_timezone_set($timezone);
        
        $start_timestamp = strtotime($start_date);
        $current_date = date('Y-m-d');
        $current_time = time();
        
        // Get delivery days configuration
        $delivery_days = self::get_delivery_days();
        
        // Get cutoff time from settings
        $cutoff_hour = (int)get_option('satguru_cutoff_hour', '17');
        $cutoff_minute = (int)get_option('satguru_cutoff_minute', '00');

        // Calculate cutoff timestamp for today
        $cutoff_time = strtotime($current_date . " {$cutoff_hour}:{$cutoff_minute}:00");
        
        // Determine which day to use for calculations
        $calculation_timestamp = $current_time;
        if ($current_time < $cutoff_time && in_array(date('w'), $delivery_days)) {
        $calculation_timestamp = strtotime('-1 day', $current_time);
        }
        
        // If start date is in future, return total tiffins
        if ($start_timestamp > $calculation_timestamp) {
        return $total_tiffins;
        }
    
        // Calculate days passed since start
        $days_passed = floor(($calculation_timestamp - $start_timestamp) / (60 * 60 * 24));
        
        // Calculate deliveries made
        $deliveries_made = 0;
        
        // Calculate complete weeks
        $complete_weeks = floor($days_passed / 7);
        
        // Calculate deliveries for complete weeks
        for ($week = 0; $week < $complete_weeks; $week++) {
            foreach ($delivery_days as $day) {
                $deliveries_made += self::calculate_day_deliveries($day, $preferred_days, $delivery_days);
            }
        }
        
        // Calculate remaining days
        $remaining_days = $days_passed % 7;
        $current_date = strtotime("+{$complete_weeks} weeks", $start_timestamp);
        
        // Count deliveries for remaining days
        for ($i = 0; $i <= $remaining_days; $i++) {
            $current_day_num = date('w', $current_date);
            $deliveries_made += self::calculate_day_deliveries($current_day_num, $preferred_days, $delivery_days);
            $current_date = strtotime('+1 day', $current_date);
        }
        
        // Calculate remaining tiffins
        $remaining = $total_tiffins - $deliveries_made;
        return max(0, $remaining); // Don't return negative numbers
    }
    
    /**
     * Check if order should be displayed in today's or next day's orders
     */
    public static function should_display_order($order, $check_date) {
        $remaining_tiffins = self::calculate_remaining_tiffins($order);
        
        if ($remaining_tiffins <= 0) {
            return false;
        }
        
        $start_date = '';
        $preferred_days = [];
        
        // Get order metadata
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                // Get start date
                if (strpos($meta->key, 'Start Date') !== false || 
                    strpos($meta->key, 'Delivery Date') !== false) {
                    $start_date = date('Y-m-d', strtotime($meta->value));
                }
                
                // Get preferred days
                if (strpos($meta->key, 'Prefered Days') !== false) {
                    $preferred_days = self::parse_preferred_days($meta->value);
                }
            }
        }
        
        // Check if start date is in future
        if (strtotime($start_date) > strtotime($check_date)) {
            return false;
        }
        
        // Check if the day is a preferred delivery day
        $check_day_num = date('w', strtotime($check_date));
        return in_array($check_day_num, $preferred_days);
    }
// Boxes Calculation starts here //
    /**
 * Calculate number of boxes to be delivered for a specific date
 */
    public static function calculate_boxes_for_date($order, $check_date) {
            $boxes = 0;
            $preferred_days = [];
            $quantity = 0;
    
            // Get delivery days configuration
            $delivery_days = self::get_delivery_days();
            $last_delivery_day = max($delivery_days);
            $first_delivery_day = min($delivery_days);
    
            // Get check date's day number (0-6)
            $check_day_num = date('w', strtotime($check_date));
    
            // Get order metadata
            foreach ($order->get_items() as $item) {
                $quantity = $item->get_quantity();
                foreach ($item->get_meta_data() as $meta) {
                    if (strpos($meta->key, 'Prefered Days') !== false) {
                $preferred_days = self::parse_preferred_days($meta->value);
                break;
            }
        }
    }
    
    // If it's not a delivery day or not a preferred day, return 0
    if (!in_array($check_day_num, $delivery_days) || !in_array($check_day_num, $preferred_days)) {
        return 0;
    }
    
    // Add base quantity for the day
    $boxes += $quantity;
    
    // If it's the last delivery day (e.g., Friday)
    if ($check_day_num == $last_delivery_day) {
        // Count preferred days that fall after this day until next first delivery day
        foreach ($preferred_days as $pref_day) {
            if ($pref_day > $last_delivery_day || $pref_day < $first_delivery_day) {
                $boxes += $quantity;
            }
        }
    }
    
        return $boxes;
    }
    // Boxes Calculation ends here //

    /**
 * Get next operational day
 */
public static function get_next_operational_day($current_date = null) {
    if ($current_date === null) {
        $current_date = date('Y-m-d');
    }
    
    $delivery_days = self::get_delivery_days();
    $current_day_num = date('w', strtotime($current_date));
    $next_day = strtotime($current_date . ' +1 day');
    
    // Keep incrementing the day until we find the next delivery day
    while (!in_array(date('w', $next_day), $delivery_days)) {
        $next_day = strtotime(date('Y-m-d', $next_day) . ' +1 day');
    }
    
    return date('Y-m-d', $next_day);
}


/**
     * Display delivery history in order details
     */
    public static function display_delivery_history($order) {
        // Get tiffin count history
        $count_history = $order->get_meta('tiffin_count_history', true);
        
        if (!is_array($count_history) || empty($count_history)) {
            echo '<p>' . esc_html__('No delivery history available.', 'satguru-tiffin') . '</p>';
            return;
        }
        
        echo '<h3>' . esc_html__('Tiffin Delivery History', 'satguru-tiffin') . '</h3>';
        echo '<table class="woocommerce-table tiffin-delivery-history">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Date', 'satguru-tiffin') . '</th>';
        echo '<th>' . esc_html__('Boxes Delivered', 'satguru-tiffin') . '</th>';
        echo '<th>' . esc_html__('Remaining Tiffins', 'satguru-tiffin') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        // Sort dates in descending order
        $dates = array_keys($count_history);
        rsort($dates);
        
        foreach ($dates as $date) {
            $entry = $count_history[$date];
            $formatted_date = date_i18n(get_option('date_format'), strtotime($date));
            $boxes_delivered = isset($entry['boxes_delivered']) ? intval($entry['boxes_delivered']) : 0;
            $remaining_tiffins = isset($entry['remaining_tiffins']) ? intval($entry['remaining_tiffins']) : 0;
            
            echo '<tr>';
            echo '<td>' . esc_html($formatted_date) . '</td>';
            echo '<td>' . esc_html($boxes_delivered) . '</td>';
            echo '<td>' . esc_html($remaining_tiffins) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * Add delivery history to order details page
     */
    /**
     * Add delivery history to order details page
     */
        /**
     * Add delivery history to order details page
     */
    /**
     * Add delivery history to order details page
     */
    public static function add_delivery_history_to_order_details() {
        // First, add the content with initial hidden state
        add_action('woocommerce_admin_order_data_after_shipping_address', function($order) {
            ?>
            <div id="delivery-history-box" class="postbox" style="display: none;"> <!-- Initially hidden -->
                <div class="postbox-header">
                    <h2 class="hndle ui-sortable-handle">
                        <?php _e('Delivery History', 'your-text-domain'); ?>
                    </h2>
                    <div class="handle-actions hide-if-no-js">
                        <button type="button" class="handle-order-higher" aria-disabled="false">
                            <span class="screen-reader-text">Move up</span>
                            <span class="order-higher-indicator" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="handle-order-lower" aria-disabled="false">
                            <span class="screen-reader-text">Move down</span>
                            <span class="order-lower-indicator" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="handlediv" aria-expanded="true">
                            <span class="screen-reader-text">Toggle panel: Delivery History</span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="inside">
                    <div class="delivery-history-wrapper wc-metaboxes-wrapper">
                        <?php self::display_delivery_history($order); ?>
                    </div>
                </div>
            </div>
            <?php
        });

        // Add JavaScript to move the box and show it
        add_action('admin_footer', function() {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Move the delivery history box after downloads and then show it
                $('#delivery-history-box')
                    .insertAfter('#woocommerce-order-downloads')
                    .show(); // Show only after moving
            });
            </script>
            <style>
                #delivery-history-box table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                #delivery-history-box table th,
                #delivery-history-box table td {
                    padding: 8px;
                    text-align: left;
                    border: 1px solid #ddd;
                }
                #delivery-history-box table th {
                    background-color: #f8f8f8;
                }
            </style>
            <?php
        });
    }

    /**
     * Check orders with 3 remaining tiffins and send renewal reminders
     * This should be called daily via cron or manually
     */
    public static function check_and_send_renewal_reminders() {
        date_default_timezone_set('America/Toronto');
        
        // Check if renewal reminders are enabled
        $reminder_enabled = get_option('renewal_reminder_enabled', '0');
        if ($reminder_enabled !== '1') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU RENEWAL REMINDERS: Skipped - Feature is disabled');
            }
            return [
                'reminders_sent' => 0,
                'errors' => [],
                'skipped' => true,
                'message' => 'Renewal reminders are disabled'
            ];
        }
        
        // Get all processing orders
        $orders = wc_get_orders([
            'status' => 'processing',
            'limit' => -1,
        ]);
        
        $reminders_sent = 0;
        $errors = [];
        
        foreach ($orders as $order) {
            try {
                $result = self::process_renewal_reminder_for_order($order);
                if ($result === true) {
                    $reminders_sent++;
                }
            } catch (Exception $e) {
                $errors[] = [
                    'order_id' => $order->get_id(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Log results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SATGURU RENEWAL REMINDERS: Sent %d reminders. Errors: %d',
                $reminders_sent,
                count($errors)
            ));
        }
        
        return [
            'reminders_sent' => $reminders_sent,
            'errors' => $errors
        ];
    }
    
    /**
     * Process renewal reminder for a single order
     * 
     * @param WC_Order $order The order to check
     * @return bool|null True if reminder sent, false if not needed, null if error
     */
    public static function process_renewal_reminder_for_order($order) {
        // Check if order is valid and processing
        if (!$order || $order->get_status() !== 'processing') {
            return false;
        }
        
        // Get remaining tiffins
        $remaining_tiffins = self::calculate_remaining_tiffins($order);
        
        // Get threshold from settings (default: 3)
        $threshold = intval(get_option('renewal_reminder_tiffin_threshold', 3));
        if ($threshold <= 0) {
            $threshold = 3;
        }
        
        // Check if remaining tiffins matches threshold
        if ($remaining_tiffins !== $threshold) {
            return false;
        }
        
        // Check if this order should be excluded (trial meals or specific products)
        if (self::should_exclude_from_renewal_reminder($order)) {
            return false;
        }
        
        // Check if we already sent a renewal reminder for this order
        $reminder_sent = $order->get_meta('renewal_reminder_sent', true);
        if ($reminder_sent === 'yes') {
            return false;
        }
        
        // Get customer phone number
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            return false;
        }
        
        // Calculate the estimated end date (when current tiffins will finish)
        $estimated_end_date = self::calculate_order_end_date($order, $remaining_tiffins);
        
        // Calculate the new start date (day after current order ends)
        $new_start_date = date('Y-m-d', strtotime($estimated_end_date . ' +1 day'));
        
        // Skip weekends for start date
        $new_start_date = self::get_next_business_day($new_start_date);
        
        // Generate renewal link with calculated start date
        $renewal_data = self::generate_renewal_link_with_start_date($order, $new_start_date);
        
        if (is_wp_error($renewal_data) || empty($renewal_data['link'])) {
            return null;
        }
        
        // Send WhatsApp message
        $message_sent = self::send_renewal_whatsapp_message($order, $renewal_data, $remaining_tiffins, $estimated_end_date);
        
        if ($message_sent) {
            // Mark reminder as sent
            $order->update_meta_data('renewal_reminder_sent', 'yes');
            $order->update_meta_data('renewal_reminder_sent_date', current_time('mysql'));
            $order->update_meta_data('renewal_link_token', $renewal_data['token']);
            $order->update_meta_data('renewal_calculated_start_date', $new_start_date);
            $order->save();
            
            // Add order note
            $order->add_order_note(sprintf(
                'Renewal reminder sent via WhatsApp. Remaining tiffins: %d. Estimated end date: %s. New plan start date: %s. Renewal link: %s',
                $remaining_tiffins,
                $estimated_end_date,
                $new_start_date,
                $renewal_data['link']
            ));
            
            return true;
        }
        
        return null;
    }
    
    /**
     * Calculate when an order will end based on remaining tiffins and preferred days
     * 
     * @param WC_Order $order The order
     * @param int $remaining_tiffins Number of tiffins remaining
     * @return string End date in Y-m-d format
     */
    public static function calculate_order_end_date($order, $remaining_tiffins) {
        date_default_timezone_set('America/Toronto');
        
        // Get preferred days from order
        $preferred_days = [];
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Prefered Days') !== false) {
                    $preferred_days = self::parse_preferred_days($meta->value);
                    break 2;
                }
            }
        }
        
        // Get delivery days (Mon-Fri typically)
        $delivery_days = self::get_delivery_days();
        
        // If no preferred days, use delivery days
        if (empty($preferred_days)) {
            $preferred_days = $delivery_days;
        }
        
        // Start from today
        $current_date = date('Y-m-d');
        $tiffins_to_deliver = $remaining_tiffins;
        $max_iterations = 365; // Safety limit
        $iterations = 0;
        
        while ($tiffins_to_deliver > 0 && $iterations < $max_iterations) {
            $day_of_week = date('w', strtotime($current_date));
            
            // Check if this is a delivery day and a preferred day
            if (in_array($day_of_week, $delivery_days) && in_array($day_of_week, $preferred_days)) {
                $tiffins_to_deliver--;
            }
            
            if ($tiffins_to_deliver > 0) {
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            $iterations++;
        }
        
        return $current_date;
    }
    
    /**
     * Get the next business day (skip weekends)
     * 
     * @param string $date Date in Y-m-d format
     * @return string Next business day in Y-m-d format
     */
    public static function get_next_business_day($date) {
        $day_of_week = date('w', strtotime($date));
        
        // If Saturday (6), move to Monday
        if ($day_of_week == 6) {
            return date('Y-m-d', strtotime($date . ' +2 days'));
        }
        // If Sunday (0), move to Monday
        if ($day_of_week == 0) {
            return date('Y-m-d', strtotime($date . ' +1 day'));
        }
        
        return $date;
    }
    
    /**
     * Generate renewal link with a specific start date
     * 
     * @param WC_Order $order The original order
     * @param string $start_date The calculated start date for the new order
     * @param int $validity_days Link validity in days
     * @return array|WP_Error Renewal link data or error
     */
    public static function generate_renewal_link_with_start_date($order, $start_date, $validity_days = 7) {
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order provided');
        }
        
        // Get order items
        $items = $order->get_items();
        if (empty($items)) {
            return new WP_Error('no_items', 'Order has no items');
        }
        
        // Get the first item
        $item = reset($items);
        $product = $item->get_product();
        
        // Extract plan details from order item meta
        $plan_name = $item->get_name();
        $number_of_tiffins = 0;
        $preferred_days = '';
        
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, 'Number Of Tiffins') !== false) {
                // Extract just the number from format like "5 (×5)"
                preg_match('/(\d+)/', $meta->value, $matches);
                $number_of_tiffins = isset($matches[1]) ? intval($matches[1]) : intval($meta->value);
            }
            if (strpos($meta->key, 'Prefered Days') !== false) {
                $preferred_days = $meta->value;
            }
        }
        
        if ($number_of_tiffins <= 0) {
            $number_of_tiffins = 1;
        }
        
        $plan_price = floatval($order->get_total());
        
        // Get add-ons if they exist
        $addons = $item->get_meta('add-ons', true) ?: '';
        
        // Get plan description from product if available
        $plan_description = '';
        if ($product) {
            $plan_description = wp_strip_all_tags($product->get_short_description());
        }
        
        // Check if upstairs delivery was used
        $delivery_type = $order->get_meta('Delivery Type', true) ?: '';
        $upstairs_delivery_paid = (strpos(strtolower($delivery_type), 'upstairs') !== false);
        
        // Build plan data structure
        $plan_data = [
            'plan_type' => 'existing',
            'plan_name' => $plan_name,
            'number_of_tiffins' => $number_of_tiffins,
            'plan_price' => $plan_price,
            'plan_description' => $plan_description,
            'validity_days' => $validity_days,
            'upstairs_delivery_paid' => $upstairs_delivery_paid,
            'created_at' => current_time('timestamp'),
            'expires_at' => current_time('timestamp') + ($validity_days * 24 * 60 * 60),
            'is_renewal' => true,
            'is_auto_renewal' => true,
            'original_order_id' => $order->get_id(),
            'calculated_start_date' => $start_date,
            'original_preferred_days' => $preferred_days,
        ];
        
        // If product exists, add product ID
        if ($product) {
            $plan_data['product_id'] = $product->get_id();
            $plan_data['is_existing_product'] = true;
        } else {
            $plan_data['is_existing_product'] = false;
        }
        
        // Add add-ons if they exist
        if (!empty($addons)) {
            $plan_data['addons'] = $addons;
        }
        
        // Generate unique token
        $token = wp_generate_password(32, false);
        
        // Store plan data in database
        update_option('customer_order_plan_' . $token, $plan_data);
        
        // Log the renewal link generation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SATGURU AUTO RENEWAL: Link Generated - Order ID: %d, Token: %s, Plan: %s, Start Date: %s',
                $order->get_id(),
                $token,
                $plan_name,
                $start_date
            ));
        }
        
        // Generate link
        $link = home_url('/customer-order/' . $token);
        
        return [
            'link' => $link,
            'token' => $token,
            'validity_days' => $validity_days,
            'expires_at' => $plan_data['expires_at'],
            'plan_name' => $plan_name,
            'plan_price' => $plan_price,
            'start_date' => $start_date,
            'number_of_tiffins' => $number_of_tiffins,
        ];
    }
    
    /**
     * Check if an order should be excluded from renewal reminders
     * 
     * @param WC_Order $order The order to check
     * @return bool True if order should be excluded, false otherwise
     */
    public static function should_exclude_from_renewal_reminder($order) {
        if (!$order) {
            return true;
        }
        
        // Check if trial meals should be excluded
        $exclude_trial_meals = get_option('renewal_reminder_exclude_trial_meals', '1') === '1';
        
        // Get excluded product IDs
        $excluded_products = get_option('renewal_reminder_excluded_products', []);
        if (!is_array($excluded_products)) {
            $excluded_products = [];
        }
        $excluded_products = array_map('intval', $excluded_products);
        
        // Get order items
        $items = $order->get_items();
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $product = $item->get_product();
            $product_name = $item->get_name();
            
            // Check if this product is in the excluded list
            if (!empty($excluded_products) && in_array($product_id, $excluded_products)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'SATGURU RENEWAL: Order #%d excluded - Product ID %d is in excluded list',
                        $order->get_id(),
                        $product_id
                    ));
                }
                return true;
            }
            
            // Check if this is a trial meal (if exclusion is enabled)
            if ($exclude_trial_meals) {
                // Check product name for trial keywords
                $trial_keywords = ['trial', 'sample', 'test meal', 'taster'];
                $product_name_lower = strtolower($product_name);
                
                foreach ($trial_keywords as $keyword) {
                    if (strpos($product_name_lower, $keyword) !== false) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf(
                                'SATGURU RENEWAL: Order #%d excluded - Product "%s" appears to be a trial meal',
                                $order->get_id(),
                                $product_name
                            ));
                        }
                        return true;
                    }
                }
                
                // Check order meta for trial meal flag
                $is_trial_order = $order->get_meta('is_trial_meal', true);
                if ($is_trial_order === 'yes' || $is_trial_order === '1' || $is_trial_order === true) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'SATGURU RENEWAL: Order #%d excluded - Order has trial meal flag',
                            $order->get_id()
                        ));
                    }
                    return true;
                }
                
                // Check item meta for trial meal flag
                $item_is_trial = $item->get_meta('is_trial_meal', true);
                if ($item_is_trial === 'yes' || $item_is_trial === '1' || $item_is_trial === true) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            'SATGURU RENEWAL: Order #%d excluded - Item has trial meal flag',
                            $order->get_id()
                        ));
                    }
                    return true;
                }
                
                // Check if product is in trial meal products list
                $trial_meal_products = get_option('trial_meal_allowed_products', []);
                if (!empty($trial_meal_products) && is_array($trial_meal_products)) {
                    if (in_array($product_id, array_map('intval', $trial_meal_products))) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log(sprintf(
                                'SATGURU RENEWAL: Order #%d excluded - Product ID %d is in trial meal products list',
                                $order->get_id(),
                                $product_id
                            ));
                        }
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Send WhatsApp renewal reminder message
     * 
     * @param WC_Order $order The order
     * @param array $renewal_data Renewal link data
     * @param int $remaining_tiffins Remaining tiffins count
     * @param string $estimated_end_date Estimated end date
     * @return bool True if message sent successfully
     */
    public static function send_renewal_whatsapp_message($order, $renewal_data, $remaining_tiffins, $estimated_end_date) {
        // Get Wati API configuration from options
        $wati_api_endpoint = get_option('wati_api_endpoint', '');
        $wati_api_token = get_option('wati_api_token', '');
        $wati_template_name = get_option('wati_renewal_template_name', 'renewal_reminder');
        
        // If Wati is not configured, try to log and return
        if (empty($wati_api_endpoint) || empty($wati_api_token)) {
            // Log the renewal data for manual follow-up
            self::log_renewal_reminder($order, $renewal_data, $remaining_tiffins, $estimated_end_date);
            return true; // Return true so the reminder is marked as sent
        }
        
        $phone = $order->get_billing_phone();
        $first_name = $order->get_billing_first_name();
        
        // Normalize phone number (remove spaces, dashes, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming Canada +1)
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        // Prepare template parameters
        $template_params = [
            [
                'name' => 'first_name',
                'value' => $first_name
            ],
            [
                'name' => 'remaining_tiffins',
                'value' => (string) $remaining_tiffins
            ],
            [
                'name' => 'end_date',
                'value' => date('F j, Y', strtotime($estimated_end_date))
            ],
            [
                'name' => 'new_start_date',
                'value' => date('F j, Y', strtotime($renewal_data['start_date']))
            ],
            [
                'name' => 'plan_name',
                'value' => $renewal_data['plan_name']
            ],
            [
                'name' => 'renewal_link',
                'value' => $renewal_data['link']
            ]
        ];
        
        // Build Wati API request
        $api_url = rtrim($wati_api_endpoint, '/') . '/api/v1/sendTemplateMessage?whatsappNumber=' . $phone;
        
        $body = [
            'template_name' => $wati_template_name,
            'broadcast_name' => 'renewal_reminder_' . $order->get_id(),
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU RENEWAL: WhatsApp API Error - ' . $response->get_error_message());
            }
            // Still log for manual follow-up
            self::log_renewal_reminder($order, $renewal_data, $remaining_tiffins, $estimated_end_date);
            return true;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code >= 200 && $response_code < 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'SATGURU RENEWAL: WhatsApp message sent successfully to %s for order #%d',
                    $phone,
                    $order->get_id()
                ));
            }
            return true;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'SATGURU RENEWAL: WhatsApp API returned %d - %s',
                $response_code,
                $response_body
            ));
        }
        
        // Log for manual follow-up even if API fails
        self::log_renewal_reminder($order, $renewal_data, $remaining_tiffins, $estimated_end_date);
        return true;
    }
    
    /**
     * Log renewal reminder for manual follow-up
     * 
     * @param WC_Order $order The order
     * @param array $renewal_data Renewal link data
     * @param int $remaining_tiffins Remaining tiffins
     * @param string $estimated_end_date Estimated end date
     */
    public static function log_renewal_reminder($order, $renewal_data, $remaining_tiffins, $estimated_end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'renewal_reminders_log';
        
        // Create table if not exists
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            customer_email varchar(255) NOT NULL,
            remaining_tiffins int(11) NOT NULL,
            estimated_end_date date NOT NULL,
            new_start_date date NOT NULL,
            renewal_link text NOT NULL,
            plan_name varchar(255) NOT NULL,
            plan_price decimal(10,2) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert log entry
        $wpdb->insert($table_name, [
            'order_id' => $order->get_id(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $order->get_billing_email(),
            'remaining_tiffins' => $remaining_tiffins,
            'estimated_end_date' => $estimated_end_date,
            'new_start_date' => $renewal_data['start_date'],
            'renewal_link' => $renewal_data['link'],
            'plan_name' => $renewal_data['plan_name'],
            'plan_price' => $renewal_data['plan_price'],
            'status' => 'pending'
        ], [
            '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s'
        ]);
    }
    
    /**
     * Reset renewal reminder flag for an order (useful for testing or re-sending)
     * 
     * @param int $order_id The order ID
     * @return bool True if reset successful
     */
    public static function reset_renewal_reminder($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $order->delete_meta_data('renewal_reminder_sent');
        $order->delete_meta_data('renewal_reminder_sent_date');
        $order->delete_meta_data('renewal_link_token');
        $order->delete_meta_data('renewal_calculated_start_date');
        $order->save();
        
        return true;
    }
    
}