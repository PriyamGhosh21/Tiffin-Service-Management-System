<?php
/**
 * Tiffin Menu Management System
 * 
 * @package Hello_Elementor_Child
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Tiffin Menu System class
 */
class Tiffin_Menu_System {
    /**
     * Class instance
     * 
     * @var Tiffin_Menu_System
     */
    private static $instance = null;

    /**
     * Table names
     * 
     * @var array
     */
    private $tables = array();

    /**
     * Get class instance
     * 
     * @return Tiffin_Menu_System
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        
        // Set table names
        $this->tables = array(
            'weekly_menus' => $wpdb->prefix . 'tiffin_weekly_menus',
            'menu_items' => $wpdb->prefix . 'tiffin_menu_items',
            'product_menus' => $wpdb->prefix . 'tiffin_product_menus', // Legacy table - will be deprecated
            'product_configs' => $wpdb->prefix . 'tiffin_product_configs', // New dynamic config table
        );
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_save_weekly_menu', array($this, 'ajax_save_weekly_menu'));
        add_action('wp_ajax_save_menu_item', array($this, 'ajax_save_menu_item'));
        add_action('wp_ajax_save_product_menu_assignment', array($this, 'ajax_save_product_menu_assignment'));
        add_action('wp_ajax_delete_menu_item', array($this, 'ajax_delete_menu_item'));
        add_action('wp_ajax_save_product_configuration', array($this, 'ajax_save_product_configuration'));
        
        // Shortcode
        add_shortcode('tiffin_menu_display', array($this, 'render_menu_shortcode'));
        
        // WooCommerce integration
        add_action('woocommerce_single_product_summary', array($this, 'display_product_menu'), 25);
        
        // Create tables on activation
        add_action('after_switch_theme', array($this, 'create_tables'));
        add_action('init', array($this, 'maybe_create_tables'), 20);
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Weekly menus table
        $sql = "CREATE TABLE {$this->tables['weekly_menus']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            week_start_date date NOT NULL,
            week_end_date date NOT NULL,
            is_active tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY week_dates (week_start_date, week_end_date)
        ) $charset_collate;";

        // Menu items table - Modified to include day_of_week
        $sql .= "CREATE TABLE {$this->tables['menu_items']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            weekly_menu_id bigint(20) NOT NULL,
            day_of_week varchar(10) NOT NULL COMMENT 'monday, tuesday, wednesday, thursday, friday, weekends',
            item_name varchar(255) NOT NULL,
            item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
            description text,
            image_url varchar(500),
            is_available tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY weekly_menu_id (weekly_menu_id),
            KEY day_of_week (day_of_week),
            KEY item_type (item_type),
            KEY day_type (day_of_week, item_type),
            FOREIGN KEY (weekly_menu_id) REFERENCES {$this->tables['weekly_menus']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Product menu assignments table (Legacy)
        $sql .= "CREATE TABLE {$this->tables['product_menus']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            weekly_menu_id bigint(20) NOT NULL,
            day_of_week varchar(10) NOT NULL,
            item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
            quantity int(11) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY weekly_menu_id (weekly_menu_id),
            KEY day_of_week (day_of_week),
            KEY item_type (item_type),
            UNIQUE KEY product_week_day_type (product_id, weekly_menu_id, day_of_week, item_type),
            FOREIGN KEY (weekly_menu_id) REFERENCES {$this->tables['weekly_menus']}(id) ON DELETE CASCADE
        ) $charset_collate;";

        // New Product Configuration table - Dynamic dish type quantities per product
        $sql .= "CREATE TABLE {$this->tables['product_configs']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
            quantity int(11) NOT NULL DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY item_type (item_type),
            UNIQUE KEY product_item_type (product_id, item_type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Force table recreation by checking if the new table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tables['product_configs']}'") == $this->tables['product_configs'];
        
        if ($table_exists) {
            update_option('tiffin_menu_tables_created', 'yes');
            error_log('Tiffin menu tables created successfully');
        } else {
            error_log('Failed to create tiffin menu tables');
        }
    }

    /**
     * Maybe create tables if they don't exist
     */
    public function maybe_create_tables() {
        if (get_option('tiffin_menu_tables_created') !== 'yes') {
            $this->create_tables();
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Tiffin Menu', 'hello-elementor-child'),
            __('Tiffin Menu', 'hello-elementor-child'),
            'manage_options',
            'tiffin-menu',
            array($this, 'render_menu_management_page'),
            'dashicons-food',
            56
        );

        add_submenu_page(
            'tiffin-menu',
            __('Weekly Menu', 'hello-elementor-child'),
            __('Weekly Menu', 'hello-elementor-child'),
            'manage_options',
            'tiffin-menu',
            array($this, 'render_menu_management_page')
        );

        add_submenu_page(
            'tiffin-menu',
            __('Product Configuration', 'hello-elementor-child'),
            __('Product Configuration', 'hello-elementor-child'),
            'manage_options',
            'tiffin-product-configuration',
            array($this, 'render_product_configuration_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on any tiffin menu page
        if (strpos($hook, 'tiffin') === false) {
            return;
        }

        wp_enqueue_style(
            'tiffin-menu-admin',
            get_stylesheet_directory_uri() . '/css/tiffin-menu-admin.css',
            array(),
            '1.0.1' // Updated version
        );

        wp_enqueue_script(
            'tiffin-menu-admin',
            get_stylesheet_directory_uri() . '/js/tiffin-menu-admin.js',
            array('jquery', 'wp-util'),
            '1.0.1', // Updated version
            true
        );

        wp_localize_script(
            'tiffin-menu-admin',
            'tiffinMenuData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tiffin_menu_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG, // Add debug flag
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'hello-elementor-child'),
                    'saved' => __('Saved successfully!', 'hello-elementor-child'),
                    'error' => __('Error occurred. Please try again.', 'hello-elementor-child'),
                ),
            )
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on product pages or where shortcode might be used
        if (is_product() || is_shop() || is_page() || is_front_page()) {
            wp_enqueue_style(
                'tiffin-menu-frontend',
                get_stylesheet_directory_uri() . '/css/tiffin-menu-frontend.css',
                array(),
                '1.0.1'
            );
        }
    }

    /**
     * Render menu management page
     */
    public function render_menu_management_page() {
        $current_week = $this->get_current_weekly_menu();
        $menu_items = $current_week ? $this->get_menu_items($current_week['id']) : array();
        ?>
        <div class="wrap">
            <h1><?php _e('Tiffin Weekly Menu Management', 'hello-elementor-child'); ?></h1>
            
            <div class="tiffin-menu-container">
                <div class="week-selector">
                    <h2><?php _e('Current Week Menu', 'hello-elementor-child'); ?></h2>
                    
                    <?php if ($current_week): ?>
                        <p><strong><?php printf(__('Week: %s to %s', 'hello-elementor-child'), 
                            date('M j, Y', strtotime($current_week['week_start_date'])),
                            date('M j, Y', strtotime($current_week['week_end_date']))
                        ); ?></strong></p>
                    <?php else: ?>
                        <p><em><?php _e('No active weekly menu found. Create one below.', 'hello-elementor-child'); ?></em></p>
                    <?php endif; ?>
                    
                    <button id="create-new-week" class="button button-primary">
                        <?php _e('Create New Weekly Menu', 'hello-elementor-child'); ?>
                    </button>
                </div>

                <?php if ($current_week): ?>
                    <div class="menu-items-section">
                        <h3><?php _e('Daily Menu Items', 'hello-elementor-child'); ?></h3>
                        
                        <div class="daily-menu-grid">
                            <?php
                            $days_of_week = array(
                                'monday' => __('Monday', 'hello-elementor-child'),
                                'tuesday' => __('Tuesday', 'hello-elementor-child'),
                                'wednesday' => __('Wednesday', 'hello-elementor-child'),
                                'thursday' => __('Thursday', 'hello-elementor-child'),
                                'friday' => __('Friday', 'hello-elementor-child'),
                                'weekends' => __('Weekends', 'hello-elementor-child'),
                            );

                            $item_types = array(
                                'main_dish' => __('Main Dish', 'hello-elementor-child'),
                                'main_non_veg' => __('Main Non Veg', 'hello-elementor-child'),
                                'side_dish' => __('Side Dish', 'hello-elementor-child'),
                                'daal' => __('Daal', 'hello-elementor-child'),
                                'rice' => __('Rice', 'hello-elementor-child'),
                                'bread' => __('Bread', 'hello-elementor-child'),
                                'dessert' => __('Dessert', 'hello-elementor-child'),
                                'beverage' => __('Beverage', 'hello-elementor-child'),
                                'good_tiffin' => __('Good Tiffin', 'hello-elementor-child'),
                                'non_veg_good_tiffin' => __('Non Veg Good Tiffin', 'hello-elementor-child'),
                            );

                            foreach ($days_of_week as $day => $day_label):
                                $day_items = array_filter($menu_items, function($item) use ($day) {
                                    return $item['day_of_week'] === $day;
                                });
                            ?>
                                <div class="daily-menu-section" data-day="<?php echo esc_attr($day); ?>">
                                    <h3 class="day-header"><?php echo esc_html($day_label); ?></h3>
                                    
                                    <div class="day-items-grid">
                                        <?php foreach ($item_types as $type => $type_label):
                                            $day_type_items = array_filter($day_items, function($item) use ($type) {
                                                return $item['item_type'] === $type;
                                            });
                                        ?>
                                            <div class="menu-type-section" data-day="<?php echo esc_attr($day); ?>" data-type="<?php echo esc_attr($type); ?>">
                                                <h4><?php echo esc_html($type_label); ?></h4>
                                                
                                                <div class="items-list">
                                                    <?php foreach ($day_type_items as $item): ?>
                                                        <div class="menu-item" data-item-id="<?php echo esc_attr($item['id']); ?>">
                                                            <span class="item-name"><?php echo esc_html($item['item_name']); ?></span>
                                                            <?php if ($item['description']): ?>
                                                                <span class="item-description"><?php echo esc_html($item['description']); ?></span>
                                                            <?php endif; ?>
                                                            <button class="button button-small delete-item" data-item-id="<?php echo esc_attr($item['id']); ?>">
                                                                <?php _e('Delete', 'hello-elementor-child'); ?>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="add-item-form">
                                                    <input type="text" class="item-name-input" placeholder="<?php printf(__('Add %s for %s', 'hello-elementor-child'), strtolower($type_label), $day_label); ?>" />
                                                    <input type="text" class="item-description-input" placeholder="<?php _e('Description (optional)', 'hello-elementor-child'); ?>" />
                                                    <button class="button add-item-btn" 
                                                            data-day="<?php echo esc_attr($day); ?>"
                                                            data-type="<?php echo esc_attr($type); ?>" 
                                                            data-weekly-menu-id="<?php echo esc_attr($current_week['id']); ?>">
                                                        <?php _e('Add', 'hello-elementor-child'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create Week Modal -->
        <div id="create-week-modal" class="tiffin-modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3><?php _e('Create New Weekly Menu', 'hello-elementor-child'); ?></h3>
                <form id="create-week-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="week_start_date"><?php _e('Week Start Date', 'hello-elementor-child'); ?></label></th>
                            <td><input type="date" id="week_start_date" name="week_start_date" required /></td>
                        </tr>
                        <tr>
                            <th><label for="week_end_date"><?php _e('Week End Date', 'hello-elementor-child'); ?></label></th>
                            <td><input type="date" id="week_end_date" name="week_end_date" required /></td>
                        </tr>
                        <tr>
                            <th><label for="is_active"><?php _e('Set as Active', 'hello-elementor-child'); ?></label></th>
                            <td><input type="checkbox" id="is_active" name="is_active" value="1" checked /></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Create Weekly Menu', 'hello-elementor-child'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render product configuration page
     */
    public function render_product_configuration_page() {
        $products = $this->get_tiffin_products();
        
        // Handle manual table creation
        if (isset($_GET['create_tables']) && current_user_can('manage_options')) {
            delete_option('tiffin_menu_tables_created');
            $this->create_tables();
            echo '<div class="notice notice-success is-dismissible"><p>Tables recreated successfully!</p></div>';
        }
        
        // Check table status
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tables['product_configs']}'") == $this->tables['product_configs'];
        ?>
        <div class="wrap">
            <h1><?php _e('Tiffin Product Configuration', 'hello-elementor-child'); ?></h1>
            
            <?php if (!$table_exists): ?>
                <div class="notice notice-error">
                    <p><strong>Database Issue:</strong> The product configuration table does not exist.</p>
                    <p><a href="<?php echo add_query_arg('create_tables', '1'); ?>" class="button button-primary">Create Tables Now</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><strong>Debug Info:</strong> Tables exist. Table name: <?php echo esc_html($this->tables['product_configs']); ?></p>
                    <p><a href="<?php echo add_query_arg('create_tables', '1'); ?>" class="button button-secondary">Recreate Tables</a></p>
                </div>
            <?php endif; ?>
            
            <div class="configuration-description">
                <p><?php _e('Configure how many items of each dish type each tiffin product should include. The system will automatically use the current week\'s menu items based on these quantities.', 'hello-elementor-child'); ?></p>
                <p><strong><?php _e('Example:', 'hello-elementor-child'); ?></strong> <?php _e('If you set "Trial Plan" to have 1 Main Dish and 1 Side Dish, it will automatically show Monday\'s main dish on Monday, Tuesday\'s main dish on Tuesday, etc.', 'hello-elementor-child'); ?></p>
            </div>

            <div class="product-configuration-container">
                <?php foreach ($products as $product): 
                    $product_config = $this->get_product_configuration($product->ID);
                ?>
                    <div class="product-configuration-card" data-product-id="<?php echo esc_attr($product->ID); ?>">
                        <h3><?php echo esc_html($product->post_title); ?></h3>
                        
                        <div class="configuration-grid">
                            <?php
                            $item_types = array(
                                'main_dish' => __('Main Dish', 'hello-elementor-child'),
                                'main_non_veg' => __('Main Non Veg', 'hello-elementor-child'),
                                'side_dish' => __('Side Dish', 'hello-elementor-child'),
                                'daal' => __('Daal', 'hello-elementor-child'),
                                'rice' => __('Rice', 'hello-elementor-child'),
                                'bread' => __('Bread/Roti', 'hello-elementor-child'),
                                'dessert' => __('Dessert', 'hello-elementor-child'),
                                'beverage' => __('Beverage', 'hello-elementor-child'),
                                'good_tiffin' => __('Good Tiffin', 'hello-elementor-child'),
                                'non_veg_good_tiffin' => __('Non Veg Good Tiffin', 'hello-elementor-child'),
                            );

                            foreach ($item_types as $type => $type_label):
                                $current_quantity = isset($product_config[$type]) ? $product_config[$type]['quantity'] : 0;
                                $is_active = isset($product_config[$type]) ? $product_config[$type]['is_active'] : 0;
                            ?>
                                <div class="config-section">
                                    <h4><?php echo esc_html($type_label); ?></h4>
                                    
                                    <div class="config-controls">
                                        <label>
                                            <input type="checkbox" 
                                                   class="config-active" 
                                                   data-type="<?php echo esc_attr($type); ?>"
                                                   <?php checked($is_active, 1); ?>>
                                            <?php _e('Include in plan', 'hello-elementor-child'); ?>
                                        </label>
                                        
                                        <div class="quantity-control">
                                            <label><?php _e('Quantity:', 'hello-elementor-child'); ?></label>
                                            <input type="number" 
                                                   class="config-quantity" 
                                                   min="0" 
                                                   max="20" 
                                                   value="<?php echo esc_attr($current_quantity); ?>"
                                                   data-type="<?php echo esc_attr($type); ?>"
                                                   <?php disabled(!$is_active); ?> />
                                        </div>
                                        
                                        <button class="button save-config" 
                                                data-product-id="<?php echo esc_attr($product->ID); ?>"
                                                data-type="<?php echo esc_attr($type); ?>">
                                            <?php _e('Save', 'hello-elementor-child'); ?>
                                        </button>
                                    </div>
                                    
                                    <div class="config-preview">
                                        <?php if ($is_active && $current_quantity > 0): ?>
                                            <small class="preview-text">
                                                <?php printf(__('Daily: %d %s', 'hello-elementor-child'), 
                                                    $current_quantity, 
                                                    strtolower($type_label)
                                                ); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="product-summary">
                            <h4><?php _e('Current Configuration Summary:', 'hello-elementor-child'); ?></h4>
                            <div class="summary-content">
                                <?php 
                                $active_configs = array_filter($product_config, function($config) {
                                    return $config['is_active'] && $config['quantity'] > 0;
                                });
                                
                                if (empty($active_configs)): ?>
                                    <em><?php _e('No items configured for this product.', 'hello-elementor-child'); ?></em>
                                <?php else: ?>
                                    <ul class="summary-list">
                                        <?php foreach ($active_configs as $type => $config): ?>
                                            <li><?php printf('%d %s', $config['quantity'], $item_types[$type]); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get current weekly menu
     */
    public function get_current_weekly_menu() {
        global $wpdb;
        
        return $wpdb->get_row(
            "SELECT * FROM {$this->tables['weekly_menus']} 
             WHERE is_active = 1 
             ORDER BY created_at DESC 
             LIMIT 1",
            ARRAY_A
        );
    }

    /**
     * Get menu items for a weekly menu
     */
    public function get_menu_items($weekly_menu_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['menu_items']} 
                 WHERE weekly_menu_id = %d AND is_available = 1 
                 ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'weekends'), item_type, item_name",
                $weekly_menu_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get tiffin products (WooCommerce products)
     */
    public function get_tiffin_products() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_tiffin_product',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        return get_posts($args);
    }

    /**
     * Get product configuration
     */
    public function get_product_configuration($product_id) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tables['product_configs']} 
                 WHERE product_id = %d",
                $product_id
            ),
            ARRAY_A
        );
        
        // Format results by item_type for easier access
        $config = array();
        foreach ($results as $row) {
            $config[$row['item_type']] = array(
                'quantity' => $row['quantity'],
                'is_active' => $row['is_active']
            );
        }
        
        return $config;
    }

    /**
     * AJAX handler for saving weekly menu
     */
    public function ajax_save_weekly_menu() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tiffin_menu_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $week_start = sanitize_text_field($_POST['week_start_date']);
        $week_end = sanitize_text_field($_POST['week_end_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        global $wpdb;

        // If setting as active, deactivate other menus
        if ($is_active) {
            $wpdb->update(
                $this->tables['weekly_menus'],
                array('is_active' => 0),
                array(),
                array('%d'),
                array()
            );
        }

        $result = $wpdb->insert(
            $this->tables['weekly_menus'],
            array(
                'week_start_date' => $week_start,
                'week_end_date' => $week_end,
                'is_active' => $is_active,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            )
        );

        if ($result === false) {
            wp_send_json_error('Failed to create weekly menu');
        }

        wp_send_json_success(array('id' => $wpdb->insert_id));
    }

    /**
     * AJAX handler for saving menu items
     */
    public function ajax_save_menu_item() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tiffin_menu_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $weekly_menu_id = absint($_POST['weekly_menu_id']);
        $day_of_week = sanitize_text_field($_POST['day_of_week']);
        $item_name = sanitize_text_field($_POST['item_name']);
        $item_type = sanitize_text_field($_POST['item_type']);
        $description = sanitize_textarea_field($_POST['description']);

        // Validate day of week
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'weekends');
        if (!in_array($day_of_week, $valid_days)) {
            wp_send_json_error('Invalid day of week');
        }

        global $wpdb;

        $result = $wpdb->insert(
            $this->tables['menu_items'],
            array(
                'weekly_menu_id' => $weekly_menu_id,
                'day_of_week' => $day_of_week,
                'item_name' => $item_name,
                'item_type' => $item_type,
                'description' => $description,
                'is_available' => 1,
                'created_at' => current_time('mysql'),
            )
        );

        if ($result === false) {
            wp_send_json_error('Failed to save menu item');
        }

        wp_send_json_success(array('id' => $wpdb->insert_id));
    }

    /**
     * AJAX handler for saving product menu assignments
     */
    public function ajax_save_product_menu_assignment() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tiffin_menu_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = absint($_POST['product_id']);
        $weekly_menu_id = absint($_POST['weekly_menu_id']);
        $day_of_week = sanitize_text_field($_POST['day_of_week']);
        $item_type = sanitize_text_field($_POST['item_type']);
        $quantity = absint($_POST['quantity']);

        // Validate day of week
        $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'weekends');
        if (!in_array($day_of_week, $valid_days)) {
            wp_send_json_error('Invalid day of week');
        }

        global $wpdb;

        // Delete existing assignment
        $wpdb->delete(
            $this->tables['product_menus'],
            array(
                'product_id' => $product_id,
                'weekly_menu_id' => $weekly_menu_id,
                'day_of_week' => $day_of_week,
                'item_type' => $item_type,
            )
        );

        // Insert new assignment if quantity > 0
        if ($quantity > 0) {
            $result = $wpdb->insert(
                $this->tables['product_menus'],
                array(
                    'product_id' => $product_id,
                    'weekly_menu_id' => $weekly_menu_id,
                    'day_of_week' => $day_of_week,
                    'item_type' => $item_type,
                    'quantity' => $quantity,
                    'created_at' => current_time('mysql'),
                )
            );

            if ($result === false) {
                wp_send_json_error('Failed to save assignment');
            }
        }

        wp_send_json_success();
    }

    /**
     * AJAX handler for deleting menu items
     */
    public function ajax_delete_menu_item() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tiffin_menu_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $item_id = absint($_POST['item_id']);

        global $wpdb;

        $result = $wpdb->delete(
            $this->tables['menu_items'],
            array('id' => $item_id)
        );

        if ($result === false) {
            wp_send_json_error('Failed to delete menu item');
        }

        wp_send_json_success();
    }

    /**
     * AJAX handler for saving product configuration
     */
    public function ajax_save_product_configuration() {
        // Enable debugging
        error_log('AJAX save_product_configuration called');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tiffin_menu_nonce')) {
            error_log('Nonce verification failed');
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            error_log('User does not have manage_options capability');
            wp_send_json_error('Insufficient permissions');
        }

        // Validate required fields
        if (!isset($_POST['product_id']) || !isset($_POST['item_type'])) {
            error_log('Missing required fields: product_id or item_type');
            wp_send_json_error('Missing required fields');
        }

        $product_id = absint($_POST['product_id']);
        $item_type = sanitize_text_field($_POST['item_type']);
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        error_log("Parsed values - Product ID: $product_id, Item Type: $item_type, Quantity: $quantity, Is Active: $is_active");

        // Validate item type
        $valid_types = array('main_dish', 'main_non_veg', 'side_dish', 'daal', 'rice', 'bread', 'dessert', 'beverage', 'good_tiffin', 'non_veg_good_tiffin');
        if (!in_array($item_type, $valid_types)) {
            error_log("Invalid item type: $item_type");
            wp_send_json_error('Invalid item type');
        }

        // Validate product ID
        if ($product_id <= 0) {
            error_log("Invalid product ID: $product_id");
            wp_send_json_error('Invalid product ID');
        }

        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tables['product_configs']}'") == $this->tables['product_configs'];
        if (!$table_exists) {
            error_log("Table {$this->tables['product_configs']} does not exist. Creating tables...");
            $this->create_tables();
            
            // Check again
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tables['product_configs']}'") == $this->tables['product_configs'];
            if (!$table_exists) {
                error_log("Failed to create table {$this->tables['product_configs']}");
                wp_send_json_error('Database table does not exist and could not be created');
            }
        }

        // Use ON DUPLICATE KEY UPDATE for upsert operation
        $sql = $wpdb->prepare(
            "INSERT INTO {$this->tables['product_configs']} 
             (product_id, item_type, quantity, is_active, created_at, updated_at) 
             VALUES (%d, %s, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE 
             quantity = VALUES(quantity), 
             is_active = VALUES(is_active), 
             updated_at = VALUES(updated_at)",
            $product_id,
            $item_type,
            $quantity,
            $is_active,
            current_time('mysql'),
            current_time('mysql')
        );

        error_log("SQL Query: $sql");

        $result = $wpdb->query($sql);

        if ($result === false) {
            error_log("Database error: " . $wpdb->last_error);
            error_log("Failed query: " . $wpdb->last_query);
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }

        error_log("Configuration saved successfully. Affected rows: $result");
        wp_send_json_success(array(
            'message' => 'Configuration saved successfully',
            'affected_rows' => $result
        ));
    }

    /**
     * Render menu shortcode
     */
    public function render_menu_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID(),
            'layout' => 'table', // table, grid, inline, list
            'theme' => 'modern', // modern, classic
        ), $atts);

        $product_id = absint($atts['product_id']);
        if (!$product_id) {
            return '';
        }

        $current_week = $this->get_current_weekly_menu();
        if (!$current_week) {
            return '<div class="tiffin-menu-notice"><p>' . __('No weekly menu available.', 'hello-elementor-child') . '</p></div>';
        }

        $assignments = $this->get_product_menu_with_items($product_id, $current_week['id']);
        if (empty($assignments)) {
            return '<div class="tiffin-menu-notice"><p>' . __('No menu items assigned to this product.', 'hello-elementor-child') . '</p></div>';
        }

        // Sort assignments to always start with main dish
        $type_order = array('main_dish' => 1, 'main_non_veg' => 2, 'side_dish' => 3, 'daal' => 4, 'rice' => 5, 'bread' => 6, 'dessert' => 7, 'beverage' => 8, 'good_tiffin' => 9, 'non_veg_good_tiffin' => 10);
        usort($assignments, function($a, $b) use ($type_order) {
            $order_a = isset($type_order[$a['item_type']]) ? $type_order[$a['item_type']] : 99;
            $order_b = isset($type_order[$b['item_type']]) ? $type_order[$b['item_type']] : 99;
            return $order_a - $order_b;
        });

        // Organize assignments by day while maintaining sort order
        $daily_assignments = array();
        foreach ($assignments as $assignment) {
            $day = $assignment['day_of_week'];
            if (!isset($daily_assignments[$day])) {
                $daily_assignments[$day] = array();
            }
            $daily_assignments[$day][] = $assignment;
        }

        // Sort each day's assignments
        foreach ($daily_assignments as $day => &$day_assignments) {
            usort($day_assignments, function($a, $b) use ($type_order) {
                $order_a = isset($type_order[$a['item_type']]) ? $type_order[$a['item_type']] : 99;
                $order_b = isset($type_order[$b['item_type']]) ? $type_order[$b['item_type']] : 99;
                return $order_a - $order_b;
            });
        }

        $days_of_week = array(
            'monday' => __('Monday', 'hello-elementor-child'),
            'tuesday' => __('Tuesday', 'hello-elementor-child'),
            'wednesday' => __('Wednesday', 'hello-elementor-child'),
            'thursday' => __('Thursday', 'hello-elementor-child'),
            'friday' => __('Friday', 'hello-elementor-child'),
            'weekends' => __('Weekends', 'hello-elementor-child'),
        );

        // Helper function to format menu items with weekend prefix
        $format_menu_items = function($day_assignments, $day) {
            $formatted_items = array();
            
            // Add weekend prefix if this is weekend day
            if ($day === 'weekends') {
                $formatted_items[] = '<span class="weekend-prefix">' . __('If You Have Chosen Weekends In Your Preferred Days', 'hello-elementor-child') . '</span>';
            }
            
            foreach ($day_assignments as $assignment) {
                if ($assignment['item_type'] === 'good_tiffin') {
                    $formatted_items[] = '<span class="good-tiffin-item">Good Tiffin : ' . $assignment['item_name'] . '</span>';
                } elseif ($assignment['item_type'] === 'non_veg_good_tiffin') {
                    $formatted_items[] = '<span class="non-veg-good-tiffin-item">Non Veg Good Tiffin : ' . $assignment['item_name'] . '</span>';
                } else {
                    $formatted_items[] = $assignment['quantity'] . ' x ' . $assignment['item_name'];
                }
            }
            
            return $formatted_items;
        };

        ob_start();
        ?>
        <div class="tiffin-menu-display theme-<?php echo esc_attr($atts['theme']); ?> layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="menu-header">
                <h3 class="menu-title">
                    <span class="menu-icon">🍽️</span>
                    <?php _e('What You\'ll Get This Week In This Tiffin Plan', 'hello-elementor-child'); ?>
                </h3>
                <div class="menu-week-info">
                    <?php printf(__('Weekly Menu of %s - %s', 'hello-elementor-child'), 
                        date('M j', strtotime($current_week['week_start_date'])),
                        date('M j, Y', strtotime($current_week['week_end_date']))
                    ); ?>
                </div>
            </div>
            
            <?php if ($atts['layout'] === 'table'): ?>
                <div class="menu-table-container">
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th class="day-column"><?php _e('Day', 'hello-elementor-child'); ?></th>
                                <th class="items-column"><?php _e('Menu Items', 'hello-elementor-child'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days_of_week as $day => $day_label): 
                                if (!isset($daily_assignments[$day])) continue;
                            ?>
                                <tr class="menu-row" data-day="<?php echo esc_attr($day); ?>">
                                    <td class="day-cell">
                                        <div class="day-label">
                                            <span class="day-name"><?php echo esc_html($day_label); ?></span>
                                            <span class="day-date"><?php 
                                                if ($day === 'weekends') {
                                                    echo 'Sat-Sun';
                                                } else {
                                                    $week_start = new DateTime($current_week['week_start_date']);
                                                    $day_number = array_search($day, array_keys($days_of_week));
                                                    $current_date = clone $week_start;
                                                    $current_date->add(new DateInterval('P' . $day_number . 'D'));
                                                    echo $current_date->format('M j');
                                                }
                                            ?></span>
                                        </div>
                                    </td>
                                    <td class="items-cell">
                                        <div class="menu-items-list">
                                            <?php 
                                            $formatted_items = $format_menu_items($daily_assignments[$day], $day);
                                            echo implode(' <span class="menu-separator">|</span> ', $formatted_items);
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($atts['layout'] === 'grid'): ?>
                <div class="menu-grid-container">
                    <?php foreach ($days_of_week as $day => $day_label): 
                        if (!isset($daily_assignments[$day])) continue;
                    ?>
                        <div class="daily-menu-card" data-day="<?php echo esc_attr($day); ?>">
                            <div class="card-header">
                                <h4 class="day-title"><?php echo esc_html($day_label); ?></h4>
                                <span class="day-date"><?php 
                                    if ($day === 'weekends') {
                                        echo 'Sat-Sun';
                                    } else {
                                        $week_start = new DateTime($current_week['week_start_date']);
                                        $day_number = array_search($day, array_keys($days_of_week));
                                        $current_date = clone $week_start;
                                        $current_date->add(new DateInterval('P' . $day_number . 'D'));
                                        echo $current_date->format('M j');
                                    }
                                ?></span>
                            </div>
                            <div class="card-content">
                                <div class="menu-items-inline">
                                    <?php 
                                    $formatted_items = $format_menu_items($daily_assignments[$day], $day);
                                    echo implode(' <span class="menu-separator">|</span> ', $formatted_items);
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($atts['layout'] === 'inline'): ?>
                <div class="menu-inline-container">
                    <?php
                    $inline_items = array();
                    foreach ($days_of_week as $day => $day_label) {
                        if (!isset($daily_assignments[$day])) continue;
                        $formatted_items = $format_menu_items($daily_assignments[$day], $day);
                        if (!empty($formatted_items)) {
                            $inline_items[] = '<span class="day-group"><strong>' . $day_label . ':</strong> ' . implode(' <span class="menu-separator">|</span> ', $formatted_items) . '</span>';
                        }
                    }
                    echo implode(' <span class="separator">|</span> ', $inline_items);
                    ?>
                </div>

            <?php else: // Default list layout ?>
                <div class="menu-list-container">
                    <?php foreach ($days_of_week as $day => $day_label): 
                        if (!isset($daily_assignments[$day])) continue;
                    ?>
                        <div class="daily-menu-section" data-day="<?php echo esc_attr($day); ?>">
                            <div class="section-header">
                                <h4 class="day-header"><?php echo esc_html($day_label); ?></h4>
                                <span class="day-date"><?php 
                                    if ($day === 'weekends') {
                                        echo 'Sat-Sun';
                                    } else {
                                        $week_start = new DateTime($current_week['week_start_date']);
                                        $day_number = array_search($day, array_keys($days_of_week));
                                        $current_date = clone $week_start;
                                        $current_date->add(new DateInterval('P' . $day_number . 'D'));
                                        echo $current_date->format('M j');
                                    }
                                ?></span>
                            </div>
                            <div class="section-content">
                                <div class="menu-items-inline">
                                    <?php 
                                    $formatted_items = $format_menu_items($daily_assignments[$day], $day);
                                    echo implode(' <span class="menu-separator">|</span> ', $formatted_items);
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get product menu with item details (Dynamic - based on product configuration)
     */
    public function get_product_menu_with_items($product_id, $weekly_menu_id) {
        global $wpdb;
        
        // Get product configuration
        $product_config = $this->get_product_configuration($product_id);
        
        if (empty($product_config)) {
            return array();
        }
        
        // Get all menu items for the week
        $menu_items = $this->get_menu_items($weekly_menu_id);
        
        // Build dynamic assignments based on configuration
        $dynamic_assignments = array();
        
        foreach ($menu_items as $item) {
            $item_type = $item['item_type'];
            $day_of_week = $item['day_of_week'];
            
            // Check if this item type is configured for this product
            if (isset($product_config[$item_type]) && 
                $product_config[$item_type]['is_active'] && 
                $product_config[$item_type]['quantity'] > 0) {
                
                $dynamic_assignments[] = array(
                    'quantity' => $product_config[$item_type]['quantity'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'],
                    'item_type' => $item['item_type'],
                    'day_of_week' => $item['day_of_week']
                );
            }
        }
        
        return $dynamic_assignments;
    }

    /**
     * Display product menu on single product page
     */
    public function display_product_menu() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        // Check if this is a tiffin product
        if (get_post_meta($product->get_id(), '_tiffin_product', true) !== '1') {
            return;
        }

        echo $this->render_menu_shortcode(array('product_id' => $product->get_id()));
    }
}

// Initialize the system
function init_tiffin_menu_system() {
    Tiffin_Menu_System::get_instance();
}

// Hook into WordPress
add_action('init', 'init_tiffin_menu_system', 15);

/**
 * Create database tables on theme activation
 */
function tiffin_menu_create_tables_on_activation() {
    global $wpdb;
    
    // Get the table names
    $tables = array(
        'weekly_menus' => $wpdb->prefix . 'tiffin_weekly_menus',
        'menu_items' => $wpdb->prefix . 'tiffin_menu_items',
        'product_menus' => $wpdb->prefix . 'tiffin_product_menus',
        'product_configs' => $wpdb->prefix . 'tiffin_product_configs',
    );
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Weekly menus table
    $sql = "CREATE TABLE IF NOT EXISTS {$tables['weekly_menus']} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        week_start_date date NOT NULL,
        week_end_date date NOT NULL,
        is_active tinyint(1) DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY week_dates (week_start_date, week_end_date)
    ) $charset_collate;";

    // Menu items table - Modified to include day_of_week
    $sql .= "CREATE TABLE IF NOT EXISTS {$tables['menu_items']} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        weekly_menu_id bigint(20) NOT NULL,
        day_of_week varchar(10) NOT NULL COMMENT 'monday, tuesday, wednesday, thursday, friday, weekends',
        item_name varchar(255) NOT NULL,
        item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
        description text,
        image_url varchar(500),
        is_available tinyint(1) DEFAULT 1,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY weekly_menu_id (weekly_menu_id),
        KEY day_of_week (day_of_week),
        KEY item_type (item_type),
        KEY day_type (day_of_week, item_type),
        FOREIGN KEY (weekly_menu_id) REFERENCES {$tables['weekly_menus']}(id) ON DELETE CASCADE
    ) $charset_collate;";

    // Product menu assignments table (Legacy)
    $sql .= "CREATE TABLE IF NOT EXISTS {$tables['product_menus']} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        weekly_menu_id bigint(20) NOT NULL,
        day_of_week varchar(10) NOT NULL,
        item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
        quantity int(11) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY weekly_menu_id (weekly_menu_id),
        KEY day_of_week (day_of_week),
        KEY item_type (item_type),
        UNIQUE KEY product_week_day_type (product_id, weekly_menu_id, day_of_week, item_type),
        FOREIGN KEY (weekly_menu_id) REFERENCES {$tables['weekly_menus']}(id) ON DELETE CASCADE
    ) $charset_collate;";

    // Product Configuration table
    $sql .= "CREATE TABLE IF NOT EXISTS {$tables['product_configs']} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        item_type varchar(50) NOT NULL COMMENT 'main_dish, main_non_veg, side_dish, daal, rice, bread, dessert, beverage, good_tiffin, non_veg_good_tiffin',
        quantity int(11) NOT NULL DEFAULT 1,
        is_active tinyint(1) DEFAULT 1,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY product_id (product_id),
        KEY item_type (item_type),
        UNIQUE KEY product_item_type (product_id, item_type)
    ) $charset_collate;";

    // Include the upgrade script
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Execute the SQL
    $result = dbDelta($sql);
    
    // Log the results
    error_log('Tiffin Menu Tables Creation Results: ' . print_r($result, true));
    
    // Verify tables exist
    $tables_created = true;
    foreach ($tables as $table_name => $table_full_name) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_full_name}'") == $table_full_name;
        if (!$table_exists) {
            $tables_created = false;
            error_log("Tiffin Menu Error: Table {$table_full_name} was not created successfully");
        } else {
            error_log("Tiffin Menu Success: Table {$table_full_name} created successfully");
        }
    }
    
    // Set options to track creation
    if ($tables_created) {
        update_option('tiffin_menu_tables_created', 'yes');
        update_option('tiffin_menu_tables_version', '1.0.0');
        update_option('tiffin_menu_created_date', current_time('mysql'));
        error_log('Tiffin Menu: All tables created successfully on theme activation');
    } else {
        update_option('tiffin_menu_tables_created', 'error');
        error_log('Tiffin Menu: Some tables failed to create on theme activation');
    }
    
    return $tables_created;
}

// Hook the function to theme activation
add_action('after_switch_theme', 'tiffin_menu_create_tables_on_activation');

// Also run on admin init if tables don't exist
function tiffin_menu_check_tables_on_admin_init() {
    if (is_admin() && get_option('tiffin_menu_tables_created') !== 'yes') {
        tiffin_menu_create_tables_on_activation();
    }
}
add_action('admin_init', 'tiffin_menu_check_tables_on_admin_init');

// Add metabox to mark products as tiffin products
function add_tiffin_product_metabox() {
    add_meta_box(
        'tiffin_product_options',
        __('Tiffin Product Options', 'hello-elementor-child'),
        'render_tiffin_product_metabox',
        'product',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_tiffin_product_metabox');

function render_tiffin_product_metabox($post) {
    wp_nonce_field('tiffin_product_metabox', 'tiffin_product_nonce');
    
    $is_tiffin_product = get_post_meta($post->ID, '_tiffin_product', true);
    ?>
    <p>
        <label>
            <input type="checkbox" name="tiffin_product" value="1" <?php checked($is_tiffin_product, '1'); ?> />
            <?php _e('This is a tiffin product', 'hello-elementor-child'); ?>
        </label>
    </p>
    <p class="description">
        <?php _e('Check this to enable weekly menu assignment and display for this product.', 'hello-elementor-child'); ?>
    </p>
    <?php
}

function save_tiffin_product_metabox($post_id) {
    if (!isset($_POST['tiffin_product_nonce']) || !wp_verify_nonce($_POST['tiffin_product_nonce'], 'tiffin_product_metabox')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_tiffin_product = isset($_POST['tiffin_product']) ? '1' : '0';
    update_post_meta($post_id, '_tiffin_product', $is_tiffin_product);
}
add_action('save_post', 'save_tiffin_product_metabox'); 

 