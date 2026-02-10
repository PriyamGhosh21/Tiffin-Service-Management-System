<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meal Plan Compositions Manager
 * Handles the breakdown of items within each meal plan
 */
class Satguru_Meal_Plan_Compositions {
    
    private static $meal_compositions = array();
    
    public function __construct() {
        $this->init_meal_compositions();
    }
    
    /**
     * Initialize meal plan compositions
     */
    private function init_meal_compositions() {
        self::$meal_compositions = array(
            
            // Microwavable Meals
            '5 Item Microwavable Meal (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            '5 Item Microwavable Meal (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            '4 Item Microwavable Meal (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 2,
                'Rice' => 1
            ),
            '4 Item Microwavable Meal (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 2,
                'Rice' => 1
            ),
            '6 Item Microwavable Meal (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Dessert / Raita / Salad' => 1,
                'Rotis' => 5,
                'Rice' => 1
            ),
            '6 Item Microwavable Meal (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Dessert / Raita / Salad' => 1,
                'Rotis' => 5,
                'Rice' => 1
            ),

            // Student Plans
            'Student Plan - 5 Item (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'Student Plan - 5 Item (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'Student Plan - 4 Item (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 4,
                'Rice' => 1
            ),
            'Student Plan - 4 Item (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 4,
                'Rice' => 1
            ),

            // Trial Meals
            'Trial Meal (Veg) (5Item)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Spicy Sauce' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'Trial Meal (Non-Veg) (5 Item)' => array(
                'Main Non-Veg Dish' => 1,
                'Veg Side Dish' => 1,
                'Veg Side Dish' => 1,
                'Spicy Sauce' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'Trial Meal (Veg) (8oz)' => array(
                'Main Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Spicy Sauce' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            'Trial Meal (Non-Veg) (8oz)' => array(
                'Main Non-Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Spicy Sauce' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),

            // Budget Meals
            'Small Budget Meal ( Veg )' => array(
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 2,
                'Rice' => 1
            ),

            // Curry Only Plans
            'Only Curries (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1
            ),
            'Only Curries (Non-Veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1
            ),

            // Mini Meals
            '2 Item Mini Meal [1 8oz Curry + 4 Rotis]' => array(
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 4
            ),
            '2 Item Micro Meal [1 12oz Curry + 6 Rotis]' => array(
                'Main Veg Dish (12oz)' => 1,
                'Rotis' => 6
            ),

            // Thali Meals (Regular)
            '5 Item Veg Thali (Regular)' => array(
                'Main Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            '5 Item Non-Veg Thali (Regular)' => array(
                'Main Non-Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            '4 Item Veg Thali Meal (Regular)' => array(
                'Main Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 2,
                'Rice' => 1
            ),
            '4 Item Non-Veg Thali Meal (Regular)' => array(
                'Main Non-Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 2,
                'Rice' => 1
            ),

            // Thali Meals (Large)
            '5 Item Veg Thali Meal (Large)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            '5 Item Non-Veg Thali Meal (Large)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            '4 Item Veg Thali Meal (Large)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 4,
                'Rice' => 1
            ),
            '4 Item Non-Veg Thali Meal (Large)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 4,
                'Rice' => 1
            ),

            // Sabzi Only Plans
            'Sabzi Only Veg (Regular)' => array(
                'Main Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1
            ),
            'Sabzi Only Non-Veg (Regular)' => array(
                'Main Non-Veg Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1
            ),
            'Sabzi Only Veg (Large)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1
            ),
            'Sabzi Only Non-Veg (Large)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1
            ),

            // Maharaja Thali
            'Maharaja Thali (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Raita' => 1,
                'Salad' => 1,
                'Rotis' => 8,
                'Rice' => 1
            ),
            'Maharaja Thali (Non-veg)' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Raita' => 1,
                'Salad' => 1,
                'Rotis' => 8,
                'Rice' => 1
            ),

            // Satguru Plans
            'New Big Pack Plan' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 10,
                'Rice' => 1
            ),
            'Preferred Plan' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'Trial Meal' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Spicy Sauce' => 1,
                'Rotis' => 6,
                'Rice' => 1
            ),
            'New Student Tiffin' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Rotis' => 8,
                'Rice' => 1
            ),
            'Small Tiffin' => array(
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 3,
                'Rice' => 1
            ),
            'New Budget Meal' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 4,
                'Rice' => 1
            ),
            'Mini King [4 Rotis + 1 Curry(8oz)]' => array(
                'Veg Side Dish (8oz)' => 1,
                'Rotis' => 4
            ),
            'Mini Queen [6 Rotis + 1 Curry (12oz)]' => array(
                'Main Veg Dish (12oz)' => 1,
                'Rotis' => 6
            ),

            // Premium Quality Meals
            'Premium Quality Meal (Veg)' => array(
                'Main Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Dessert' => 1,
                'Salad' => 1,
                'Raita' => 1,
                'Rotis' => 8,
                'Rice' => 1
            ),
            'Premium Quality Meal ( Non - Veg )' => array(
                'Main Non-Veg Dish (12oz)' => 1,
                'Veg Side Dish (8oz)' => 1,
                'Veg Side Dish (12oz)' => 1,
                'Dessert' => 1,
                'Salad' => 1,
                'Raita' => 1,
                'Rotis' => 8,
                'Rice' => 1
            ),

            // Special Meals & Biryani
            'Non-Veg Biryani Meal' => array(
                'Non-Veg Biryani (Large)' => 1,
                'Raita' => 1,
                'Pickle' => 1
            ),
            'Veg Biryani Meal' => array(
                'Veg Biryani (Large)' => 1,
                'Raita' => 1,
                'Pickle' => 1
            ),
            'Dal Chawal Meal' => array(
                'Veg Side Dish (8oz)' => 1, // Dal replaced with Veg Side Dish
                'Rice (Large)' => 1,
                'Pickle' => 1,
                'Papad' => 2
            ),
            
            // Individual Items (for standalone orders)
            'Main Non-Veg Dish' => array(
                'Main Non-Veg Dish (12oz)' => 1
            ),
            'Main Veg Dish' => array(
                'Main Veg Dish (12oz)' => 1
            ),
            'Rice' => array(
                'Rice' => 1
            ),
            'Rotis (Pack of 6)' => array(
                'Rotis' => 6
            ),
            'Rotis (Pack of 4)' => array(
                'Rotis' => 4
            )
        );
        
        // Allow customization via WordPress filters
        self::$meal_compositions = apply_filters('satguru_meal_compositions', self::$meal_compositions);
    }
    
    /**
     * Get composition for a specific meal plan
     */
    public static function get_meal_composition($meal_name) {
        // Clean the meal name - remove extra spaces, normalize
        $clean_meal_name = trim($meal_name);
        
        // Check if it's a custom meal first
        if (stripos($clean_meal_name, 'custom meal') !== false) {
            return self::parse_custom_meal($clean_meal_name);
        }
        
        // Try exact match first
        if (isset(self::$meal_compositions[$clean_meal_name])) {
            return self::$meal_compositions[$clean_meal_name];
        }
        
        // Try fuzzy matching for similar names
        foreach (self::$meal_compositions as $composition_name => $items) {
            if (stripos($clean_meal_name, $composition_name) !== false || 
                stripos($composition_name, $clean_meal_name) !== false) {
                return $items;
            }
        }
        
        // If no match found, return empty array
        return array();
    }
    
    /**
     * Calculate total items needed for a date
     */
    public static function calculate_items_for_date($orders, $date) {
        $total_items = array();
        
        foreach ($orders as $order) {
            // Only process orders with 'processing' status
            if ($order->get_status() !== 'processing') {
                continue;
            }
            
            $remaining_tiffins = Satguru_Tiffin_Calculator::calculate_remaining_tiffins($order);
            
            // Only count orders with remaining tiffins
            if ($remaining_tiffins <= 0) {
                continue;
            }
            
            $boxes_for_date = Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $date);
            
            // Only calculate if there are boxes to be delivered on this date
            if ($boxes_for_date > 0) {
                foreach ($order->get_items() as $item) {
                    $product_name = $item->get_name();
                    $composition = self::get_meal_composition($product_name);
                    
                    if (!empty($composition)) {
                        foreach ($composition as $ingredient => $quantity_per_meal) {
                            $total_quantity = $quantity_per_meal * $boxes_for_date;
                            
                            if (isset($total_items[$ingredient])) {
                                $total_items[$ingredient] += $total_quantity;
                            } else {
                                $total_items[$ingredient] = $total_quantity;
                            }
                        }
                    } else {
                        // If no composition found, treat as single item
                        if (isset($total_items[$product_name])) {
                            $total_items[$product_name] += $boxes_for_date;
                        } else {
                            $total_items[$product_name] = $boxes_for_date;
                        }
                    }
                }
            }
        }
        
        return $total_items;
    }
    
    /**
     * Get all available meal compositions
     */
    public static function get_all_compositions() {
        return self::$meal_compositions;
    }
    
    /**
     * Add or update a meal composition
     */
    public static function add_meal_composition($meal_name, $composition) {
        self::$meal_compositions[$meal_name] = $composition;
        
        // Save to database option for persistence
        update_option('satguru_custom_meal_compositions', self::$meal_compositions);
    }
    
    /**
     * Remove a meal composition
     */
    public static function remove_meal_composition($meal_name) {
        if (isset(self::$meal_compositions[$meal_name])) {
            unset(self::$meal_compositions[$meal_name]);
            update_option('satguru_custom_meal_compositions', self::$meal_compositions);
            return true;
        }
        return false;
    }
    
    /**
     * Display items breakdown in HTML format
     */
    public static function display_items_breakdown($items, $title = "Items Breakdown") {
        if (empty($items)) {
            return '<div class="items-breakdown-empty"><p>No items to display.</p></div>';
        }
        
        $html = '<div class="items-breakdown-cards">';
        
        // Sort items by category
        $categorized_items = self::categorize_items($items);
        
        foreach ($categorized_items as $category => $category_items) {
            // Get category icon and color
            $category_info = self::get_category_styling($category);
            
            $html .= '<div class="breakdown-category-card" data-category="' . esc_attr(strtolower(str_replace(' ', '-', $category))) . '">';
            $html .= '<div class="category-card-header">';
            $html .= '<div class="category-icon">' . $category_info['icon'] . '</div>';
            $html .= '<h4 class="category-title">' . esc_html($category) . '</h4>';
            $html .= '<span class="category-count">(' . count($category_items) . ' items)</span>';
            $html .= '</div>';
            
            $html .= '<div class="category-items-grid">';
            
            foreach ($category_items as $item => $quantity) {
                $html .= '<div class="item-card">';
                $html .= '<div class="item-info">';
                $html .= '<span class="item-name">' . esc_html($item) . '</span>';
                $html .= '</div>';
                $html .= '<div class="item-quantity-badge">';
                $html .= '<span class="quantity-number">' . esc_html($quantity) . '</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // category-items-grid
            $html .= '</div>'; // breakdown-category-card
        }
        
        $html .= '</div>'; // items-breakdown-cards
        
        return $html;
    }
    
    /**
     * Get category styling information
     */
    private static function get_category_styling($category) {
        $styling = array(
            'Main Dishes' => array(
                'icon' => 'í ¼í½›',
                'color' => '#e74c3c'
            ),
            'Side Dishes' => array(
                'icon' => 'í ¾íµ˜',
                'color' => '#f39c12'
            ),
            'Bread & Rice' => array(
                'icon' => 'í ¼í½š',
                'color' => '#3498db'
            ),
            'Accompaniments' => array(
                'icon' => 'í ¾íµ—',
                'color' => '#27ae60'
            ),
            'Others' => array(
                'icon' => 'í ¼í½½ï¸',
                'color' => '#9b59b6'
            )
        );
        
        return isset($styling[$category]) ? $styling[$category] : $styling['Others'];
    }
    
    /**
     * Categorize items for better display
     */
    private static function categorize_items($items) {
        $categories = array(
            'Main Dishes' => array(),
            'Side Dishes' => array(),
            'Bread & Rice' => array(),
            'Accompaniments' => array(),
            'Others' => array()
        );
        
        foreach ($items as $item => $quantity) {
            if (stripos($item, 'main') !== false || stripos($item, 'biryani') !== false) {
                $categories['Main Dishes'][$item] = $quantity;
            } elseif (stripos($item, 'side') !== false || stripos($item, 'dal') !== false) {
                $categories['Side Dishes'][$item] = $quantity;
            } elseif (stripos($item, 'roti') !== false || stripos($item, 'rice') !== false) {
                $categories['Bread & Rice'][$item] = $quantity;
            } elseif (stripos($item, 'raita') !== false || stripos($item, 'pickle') !== false || stripos($item, 'papad') !== false) {
                $categories['Accompaniments'][$item] = $quantity;
            } else {
                $categories['Others'][$item] = $quantity;
            }
        }
        
        // Remove empty categories
        return array_filter($categories, function($category_items) {
            return !empty($category_items);
        });
    }
    
    /**
     * Parse custom meal string into composition
     */
    private static function parse_custom_meal($meal_string) {
        $composition = array();
        
        // Remove "Custom Meal -" prefix and clean up
        $content = preg_replace('/^custom\s*meal\s*-?\s*/i', '', $meal_string);
        $content = trim($content);
        
        // Clean up formatting issues - be smart about preserving "Non-Veg" compound words
        // First, protect "Non-Veg" by temporarily replacing it
        $content = preg_replace('/non\s*-\s*veg/i', 'NONVEG_PLACEHOLDER', $content);
        
        // Now normalize other dashes and spaces
        $content = preg_replace('/\s*-+\s*/', ' - ', $content); // Normalize other dashes
        $content = preg_replace('/\s+/', ' ', $content); // Multiple spaces to single
        $content = preg_replace('/^\s*-\s*/', '', $content); // Remove leading dash
        $content = preg_replace('/\s*-\s*$/', '', $content); // Remove trailing dash
        
        // Restore "Non-Veg" compound words
        $content = preg_replace('/NONVEG_PLACEHOLDER/i', 'Non-Veg', $content);
        
        // Use regex patterns similar to Google Script for better accuracy
        $veg_count = 0; // Track veg items for Main vs Side classification
        
        // Parse all veg/non-veg items first using comprehensive regex
        self::parse_all_dish_items($content, $composition, $veg_count);
        
        // Parse rotis, rice and other items
        self::parse_bread_and_rice($content, $composition);
        self::parse_accompaniments($content, $composition);
        
        return $composition;
    }
    
    /**
     * Parse all dish items (veg and non-veg) using comprehensive regex patterns
     */
    private static function parse_all_dish_items($content, &$composition, &$veg_count) {
        // Comprehensive regex pattern similar to Google Script
        // Matches: "1 Veg (12oz)", "1 Non-Veg (12oz) Curry", "2 Veg(8oz) Curries", "3 Veg Curries (12oz)", etc.
        $patterns = array(
            '/(\d+)\s*(non-veg|veg)\s*(?:curri?e?s?\s*)?\((\d+)oz\)(?:\s*curri?e?s?)?/i', // Standard pattern
            '/(\d+)\s*(non-veg|veg)\s*\((\d+)oz\)\s*curri?e?s?/i',                        // Curry after size
            '/(\d+)\s*(non-veg|veg)\s*curri?e?s?\s*\((\d+)oz\)/i'                         // Curry before size
        );
        
        $all_matches = array();
        
        // Try each pattern and collect all matches
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    // Avoid duplicates by checking if this match overlaps with existing ones
                    $match_start = strpos($content, $match[0]);
                    $duplicate = false;
                    foreach ($all_matches as $existing) {
                        $existing_start = strpos($content, $existing[0]);
                        if (abs($match_start - $existing_start) < 5) { // Close matches are likely duplicates
                            $duplicate = true;
                            break;
                        }
                    }
                    if (!$duplicate) {
                        $all_matches[] = $match;
                    }
                }
            }
        }
        
        // Process all unique matches
        foreach ($all_matches as $match) {
            $quantity = intval($match[1]);
            $type = strtolower($match[2]);
            $size = $match[3] . 'oz';
            
            for ($i = 0; $i < $quantity; $i++) {
                if ($type === 'non-veg') {
                    $key = "Main Non-Veg Dish ({$size})";
                    if (isset($composition[$key])) {
                        $composition[$key]++;
                    } else {
                        $composition[$key] = 1;
                    }
                } elseif ($type === 'veg') {
                    // First veg item becomes main, rest become sides
                    if ($veg_count === 0) {
                        $key = "Main Veg Dish ({$size})";
                        $composition[$key] = 1;
                    } else {
                        $key = "Veg Side Dish ({$size})";
                        if (isset($composition[$key])) {
                            $composition[$key]++;
                        } else {
                            $composition[$key] = 1;
                        }
                    }
                    $veg_count++;
                }
            }
        }
    }

    /**
     * Parse bread and rice items
     */
    private static function parse_bread_and_rice($content, &$composition) {
        // Parse rotis - handle various formats
        if (preg_match('/(\d+)\s*rotis?/i', $content, $matches)) {
            $quantity = intval($matches[1]);
            if (isset($composition['Rotis'])) {
                $composition['Rotis'] += $quantity;
            } else {
                $composition['Rotis'] = $quantity;
            }
        }
        
        // Parse rice
        if (preg_match('/(\d+)\s*rice/i', $content, $matches)) {
            $quantity = intval($matches[1]);
            if (isset($composition['Rice'])) {
                $composition['Rice'] += $quantity;
            } else {
                $composition['Rice'] = $quantity;
            }
        }
    }
    
    /**
     * Parse accompaniments (raita, salad, pickle, etc.)
     */
    private static function parse_accompaniments($content, &$composition) {
        $accompaniments = array(
            'raita' => 'Raita',
            'salad' => 'Salad', 
            'pickle' => 'Pickle',
            'papad' => 'Papad',
            'dal' => 'Dal (8oz)'
        );
        
        foreach ($accompaniments as $pattern => $standard_name) {
            if (preg_match("/(\d+)\s*{$pattern}/i", $content, $matches)) {
                $quantity = intval($matches[1]);
                if (isset($composition[$standard_name])) {
                    $composition[$standard_name] += $quantity;
                } else {
                    $composition[$standard_name] = $quantity;
                }
            }
        }
    }
    

    
    /**
     * Get composition display for admin
     */
    public static function get_composition_display($meal_name) {
        $composition = self::get_meal_composition($meal_name);
        
        if (empty($composition)) {
            return 'No composition defined';
        }
        
        $display = array();
        foreach ($composition as $item => $quantity) {
            $display[] = $quantity . ' ' . $item;
        }
        
        return implode(', ', $display);
    }
}

// Initialize the meal compositions
new Satguru_Meal_Plan_Compositions();

/**
 * Helper function to get meal composition
 */
function satguru_get_meal_composition($meal_name) {
    return Satguru_Meal_Plan_Compositions::get_meal_composition($meal_name);
}

/**
 * Helper function to calculate items for date
 */
function satguru_calculate_items_for_date($orders, $date) {
    return Satguru_Meal_Plan_Compositions::calculate_items_for_date($orders, $date);
}

/**
 * Test function for custom meal parsing (for debugging)
 */
function satguru_test_custom_meal_parsing() {
    $test_meals = array(
        // Custom meal examples
        'Custom Meal - 3 Veg(8oz) Curries + 6 Rotis + 2 Rice',
        'Custom Meal - 3 Veg(12oz) +1 Veg(8oz) + 6 Rotis',
        'Custom Meal - 1 Veg (12oz) + - 1 Non-Veg (12oz) Curry - 10 Rotis + 1 Rice',
        'Custom Meal - 2 Non-Veg(12oz) + 1 Veg(8oz) + 4 Rotis + 1 Rice',
        'Custom Meal - 1 Veg(12oz) + 2 Veg(8oz) + 6 Rotis + 1 Dal + 1 Raita',
        'Custom Meal - 4 Veg(8oz) + 8 Rotis + 2 Rice + 1 Pickle',
        'Custom Meal - 2 Veg (8oz) - 1 Non-Veg (10oz) Curry + 6 Rotis',
        'Custom Meal - 3 Veg Curries (12oz) + 1 Non-Veg (12oz) + 8 Rotis + 2 Rice + 1 Raita',
        
        // Pre-defined plan examples
        '5 Item Veg Thali Meal (Large)',
        '5 Item Non-Veg Thali Meal (Large)', 
        'Student Plan - 5 Item (Veg)',
        'Premium Quality Meal (Veg)',
        'Maharaja Thali (Veg)',
        'Trial Meal (Veg) (5Item)',
        'Mini King [4 Rotis + 1 Curry(8oz)]'
    );
    
    echo '<div style="padding: 20px; background: #f9f9f9; margin: 20px 0;">';
    echo '<h3>Custom Meal Parsing Test Results</h3>';
    
    foreach ($test_meals as $meal) {
        $composition = Satguru_Meal_Plan_Compositions::get_meal_composition($meal);
        echo '<div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 4px solid #0073aa;">';
        echo '<strong>Input:</strong> ' . esc_html($meal) . '<br>';
        echo '<strong>Parsed Composition:</strong><br>';
        
        if (!empty($composition)) {
            echo '<ul style="margin: 5px 0 0 20px;">';
            foreach ($composition as $item => $quantity) {
                echo '<li>' . esc_html($quantity . ' ' . $item) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<em>No composition found</em>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Debug function to show parsing steps (for troubleshooting)
 */
function satguru_debug_custom_meal_parsing($meal_string) {
    echo '<div style="padding: 15px; background: #f0f8ff; border: 1px solid #0073aa; margin: 10px 0;">';
    echo '<h4>Debug Parsing: ' . esc_html($meal_string) . '</h4>';
    
    // Step 1: Remove prefix
    $content = preg_replace('/^custom\s*meal\s*-?\s*/i', '', $meal_string);
    echo '<strong>Step 1 - Remove prefix:</strong> "' . esc_html($content) . '"<br>';
    
    // Step 2: Clean formatting - protect Non-Veg first
    $content = preg_replace('/non\s*-\s*veg/i', 'NONVEG_PLACEHOLDER', $content);
    echo '<strong>Step 2 - Protect Non-Veg:</strong> "' . esc_html($content) . '"<br>';
    
    $content = preg_replace('/\s*-+\s*/', ' - ', $content);
    echo '<strong>Step 3 - Normalize dashes:</strong> "' . esc_html($content) . '"<br>';
    
    $content = preg_replace('/\s+/', ' ', $content);
    echo '<strong>Step 4 - Single spaces:</strong> "' . esc_html($content) . '"<br>';
    
    $content = preg_replace('/^\s*-\s*/', '', $content);
    $content = preg_replace('/\s*-\s*$/', '', $content);
    echo '<strong>Step 5 - Remove edge dashes:</strong> "' . esc_html($content) . '"<br>';
    
    $content = preg_replace('/NONVEG_PLACEHOLDER/i', 'Non-Veg', $content);
    echo '<strong>Step 6 - Restore Non-Veg:</strong> "' . esc_html($content) . '"<br>';
    
    // Step 7: Show regex matches
    $patterns = array(
        '/(\d+)\s*(non-veg|veg)\s*(?:curri?e?s?\s*)?\((\d+)oz\)(?:\s*curri?e?s?)?/i',
        '/(\d+)\s*(non-veg|veg)\s*\((\d+)oz\)\s*curri?e?s?/i',
        '/(\d+)\s*(non-veg|veg)\s*curri?e?s?\s*\((\d+)oz\)/i'
    );
    
    $all_matches = array();
    foreach ($patterns as $i => $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            echo '<strong>Step 7.' . ($i+1) . ' - Pattern ' . ($i+1) . ' matches:</strong><br>';
            foreach ($matches as $match) {
                echo '&nbsp;&nbsp;"' . esc_html($match[0]) . '" â†’ ' . 
                     $match[1] . ' ' . $match[2] . ' (' . $match[3] . 'oz)<br>';
                $all_matches[] = $match;
            }
        }
    }
    
    if (!empty($all_matches)) {
        echo '<strong>Step 8 - Combined unique matches:</strong><br>';
        foreach ($all_matches as $i => $match) {
            echo '&nbsp;&nbsp;' . ($i+1) . ': ' . $match[1] . ' ' . $match[2] . ' (' . $match[3] . 'oz)<br>';
        }
    }
    
    // Step 4: Show final composition
    $composition = Satguru_Meal_Plan_Compositions::get_meal_composition($meal_string);
    echo '<strong>Final Composition:</strong><br>';
    if (!empty($composition)) {
        foreach ($composition as $item => $quantity) {
            echo '&nbsp;&nbsp;' . $quantity . ' ' . esc_html($item) . '<br>';
        }
    } else {
        echo '&nbsp;&nbsp;<em>No composition found</em><br>';
    }
    
    echo '</div>';
} 