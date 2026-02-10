<?php
declare(strict_types=1);

/**
 * Example class to add a custom "Add Order" admin page and handle order creation.
 * Place this in your theme's functions.php or a custom plugin file.
 */

class My_Custom_Order_Admin_Page {

    /**
     * Constructor: hooks into WordPress admin_menu and admin_init
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_add_order_submenu']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('wp_ajax_search_wc_customer', [$this, 'ajax_search_customer']);
    }

    public function ajax_search_customer(): void {
        check_ajax_referer('search_customer_nonce', 'nonce');
    
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
    
        $search_term = sanitize_text_field($_POST['search_term']);
        
        // Search by email
        $user = get_user_by('email', $search_term);
        
        // If not found by email, try phone number
        if (!$user) {
            $users = get_users([
                'meta_key' => 'phone',
                'meta_value' => $search_term,
                'number' => 1
            ]);
            $user = $users[0] ?? null;
        }
    
        if (!$user) {
            wp_send_json_success(['customer' => null]);
            return;
        }
    
        // Get customer data
        $customer_data = [
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'email' => $user->user_email,
            'phone' => get_user_meta($user->ID, 'phone', true),
            'company' => get_user_meta($user->ID, 'billing_company', true),
            'address_1' => get_user_meta($user->ID, 'billing_address_1', true),
            'address_2' => get_user_meta($user->ID, 'billing_address_2', true),
            'city' => get_user_meta($user->ID, 'billing_city', true),
            'state' => get_user_meta($user->ID, 'billing_state', true),
            'postcode' => get_user_meta($user->ID, 'billing_postcode', true),
            'country' => get_user_meta($user->ID, 'billing_country', true),
        ];
    
        wp_send_json_success(['customer' => $customer_data]);
    }

    /**
     * Register our custom "Add Order" page under WooCommerce menu
     */
    public function register_add_order_submenu(): void {
        add_submenu_page(
            'woocommerce',                 // Parent slug (under WooCommerce)
            'Add Custom Order',            // Page title
            'Add Custom Order',            // Menu title
            'manage_woocommerce',          // Capability
            'my-custom-add-order',         // Menu slug
            [$this, 'render_add_order_page'] // Callback to render the page
        );
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ('woocommerce_page_my-custom-add-order' !== $hook) {
            return;
        }
    
        // Enqueue Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
    
        // Your existing style
        wp_enqueue_style(
            'add-order-admin-styles',
            get_stylesheet_directory_uri() . '/css/add-order-admin.css',
            ['select2'],
            '1.0.0'
        );
    }

    /**
     * Render the "Add Order" page form

     */
    public function render_add_order_page(): void {
        // Fetch dynamic fields (configure them as needed; see get_dynamic_fields() below)

        ?>
        <div class="wrap">
    <h1>Add a New Order</h1>
    <div class="order-form-container">
        <form method="post">
            <?php wp_nonce_field('my_add_order_nonce'); ?>
            
            <div class="customer-type-selector">
            <label>
            <input type="radio" name="customer_type" value="new" checked> New Customer
            </label>
        <label>
        <input type="radio" name="customer_type" value="existing"> Existing Customer
        </label>
    </div>
    <div id="customer-lookup" style="display: none;" class="customer-lookup-section">
    <table class="form-table">
        <tr>
            <th><label for="customer_search">Search Customer</label></th>
            <td>
                <input type="text" id="customer_search" placeholder="Enter email or phone number">
                <button type="button" id="search_customer" class="button">Search</button>
                <div id="customer_search_results"></div>
            </td>
        </tr>
    </table>
        </div>
            <div class="form-row">
                <!-- Left Column -->
                <div class="form-column">
                    <h2 class="section-title">Customer Information</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="first_name">First Name</label></th>
                            <td><input type="text" name="first_name" id="first_name" required></td>
                        </tr>
                        <tr>
                            <th><label for="last_name">Last Name</label></th>
                            <td><input type="text" name="last_name" id="last_name" required></td>
                        </tr>
                        <tr>
                            <th><label for="phone">Phone Number</label></th>
                            <td><input type="text" name="phone" id="phone" required></td>
                        </tr>
                        <tr>
                            <th><label for="email">Email Address</label></th>
                            <td><input type="email" name="email" id="email" required></td>
                        </tr>
                        <tr>
                            <th><label for="company">Company</label></th>
                            <td><input type="text" name="company" id="company"></td>
                        </tr>
                    </table>

                    <h2 class="section-title">Address Information</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="address_1">Address Line 1</label></th>
                            <td><input type="text" name="address_1" id="address_1"></td>
                        </tr>
                        <tr>
                            <th><label for="address_2">Address Line 2</label></th>
                            <td><input type="text" name="address_2" id="address_2"></td>
                        </tr>
                        <tr>
                            <th><label for="city">City</label></th>
                            <td><input type="text" name="city" id="city"></td>
                        </tr>
                        <tr>
                            <th><label for="state">State/Province</label></th>
                            <td>
                                <select name="state" id="state">
                                    <?php
                                    $countries_obj = new WC_Countries();
                                    $states = $countries_obj->get_states('CA');
                                    foreach ($states as $code => $name) {
                                        $selected = ($code === 'ON') ? 'selected' : '';
                                        echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . 
                                             esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="postcode">Postcode</label></th>
                            <td><input type="text" name="postcode" id="postcode"></td>
                        </tr>
                        <tr>
                            <th><label for="country">Country</label></th>
                            <td>
                                <select name="country" id="country">
                                    <?php
                                    $countries = $countries_obj->get_countries();
                                    foreach ($countries as $code => $name) {
                                        $selected = ($code === 'CA') ? 'selected' : '';
                                        echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . 
                                             esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Right Column -->
                <div class="form-column">
                    <h2 class="section-title">Order Details</h2>
                    <table class="form-table">
                        <tr>
                            <th><label>Product Type</label></th>
                            <td>
                                <label style="margin-right: 15px;">
                                    <input type="radio" name="product_type" value="existing" checked> Existing Product
                                </label>
                                <label>
                                    <input type="radio" name="product_type" value="custom"> Custom Meal
                                </label>
                            </td>
                        </tr>

                        <tr id="existing-product-row">
                            <th><label for="product_id">Product</label></th>
                            <td>
                                <select name="product_id" id="product_id" required>
                                    <option value="">-- Select Product --</option>
                                    <?php
                                    $products = wc_get_products([
                                        'status' => 'publish', 
                                        'limit' => -1,
                                        'type' => 'simple'
                                    ]);
                                    foreach ($products as $product) {
                                        echo '<option value="' . esc_attr($product->get_id()) . '">' . 
                                             esc_html($product->get_name()) . ' - ' . 
                                             wc_price($product->get_price()) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>

                        <tr id="custom-product-rows" style="display: none;">
                            <th><label>Custom Meal Configuration</label></th>
                            <td>
                                <div class="custom-meal-builder">
                                    <div class="meal-component-row">
                                        <label class="component-label">Non-Veg Items:</label>
                                        <input type="number" name="nonveg_quantity" id="nonveg_quantity" min="0" max="10" value="0" style="width: 60px;">
                                        <span>x</span>
                                        <select name="nonveg_size" id="nonveg_size" style="width: 80px;">
                                            <option value="6oz">6oz</option>
                                            <option value="8oz" selected>8oz</option>
                                            <option value="10oz">10oz</option>
                                            <option value="12oz">12oz</option>
                                        </select>
                                        <input type="text" name="nonveg_type" id="nonveg_type" placeholder="e.g., Chicken, Mutton" style="width: 150px;">
                                    </div>
                                    
                                    <div class="meal-component-row">
                                        <label class="component-label">Veg Items:</label>
                                        <input type="number" name="veg_quantity" id="veg_quantity" min="0" max="10" value="0" style="width: 60px;">
                                        <span>x</span>
                                        <select name="veg_size" id="veg_size" style="width: 80px;">
                                            <option value="6oz">6oz</option>
                                            <option value="8oz" selected>8oz</option>
                                            <option value="10oz">10oz</option>
                                            <option value="12oz">12oz</option>
                                        </select>
                                        <input type="text" name="veg_type" id="veg_type" placeholder="e.g., Dal, Curry, Sabzi" style="width: 150px;">
                                    </div>
                                    
                                    <div class="meal-component-row">
                                        <label class="component-label">Rotis/Bread:</label>
                                        <input type="number" name="roti_quantity" id="roti_quantity" min="0" max="20" value="0" style="width: 60px;">
                                        <span>Rotis/Naan</span>
                                    </div>
                                    
                                    <div class="meal-component-row">
                                        <label class="component-label">Rice:</label>
                                        <input type="number" name="rice_quantity" id="rice_quantity" min="0" max="10" value="0" style="width: 60px;">
                                        <span>Portions</span>
                                    </div>
                                    
                                    <div class="meal-component-row">
                                        <label class="component-label">Raita:</label>
                                        <input type="number" name="raita_quantity" id="raita_quantity" min="0" max="10" value="0" style="width: 60px;">
                                        <span>Portions</span>
                                    </div>
                                    
                                    <div class="meal-component-row">
                                        <label class="component-label">Salad:</label>
                                        <input type="number" name="salad_quantity" id="salad_quantity" min="0" max="10" value="0" style="width: 60px;">
                                        <span>Portions</span>
                                    </div>
                                    
                                    <div class="meal-preview">
                                        <strong>Product Name Preview:</strong>
                                        <div id="custom_meal_preview" style="background: #f0f0f1; padding: 10px; border-radius: 4px; margin-top: 5px; font-style: italic; color: #666;">
                                            Custom Meal - Please configure items above
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Hidden field to store the generated product name -->
                                <input type="hidden" name="custom_product_name" id="custom_product_name">
                                
                                <p class="description">Configure your custom meal components. The product name will be automatically generated.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="quantity">Quantity</label></th>
                            <td>
                                <select name="quantity" id="quantity" required>
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="start_date">Start Date</label></th>
                            <td><input type="date" name="start_date" id="start_date" required></td>
                        </tr>
                        <tr>
                        <th><label for="preferred_days">Preferred Days</label></th>
                        <td>
                        <select name="preferred_days[]" id="preferred_days" multiple="multiple" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <p class="description">Hold Ctrl/Cmd to select multiple days</p>
                    </td>
                    </tr>
                        <tr>
                            <th><label for="meal_type">Veg / Non Veg</label></th>
                            <td>
                                <select name="meal_type" id="meal_type" required>
                                    <option value="Veg">Veg</option>
                                    <option value="Non-Veg">Non-Veg</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="delivery_type">Delivery</label></th>
                            <td>
                                <select name="delivery_type" id="delivery_type" required>
                                    <option value="Delivery">Delivery</option>
                                    <option value="Pickup">Pickup</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="number_of_tiffins">Number Of Tiffins</label></th>
                            <td><input type="number" name="number_of_tiffins" id="number_of_tiffins" 
                                 min="1" max="100" required></td>
                        </tr>
                        <tr>
                            <th><label for="remaining_tiffins">Remaining Tiffins</label></th>
                            <td><input type="number" name="remaining_tiffins" id="remaining_tiffins" 
                                 min="0" max="100" placeholder="Leave empty to calculate automatically"></td>
                        </tr>
                        <tr>
                            <th><label for="addons">Addons</label></th>
                            <td><input type="text" name="addons" id="addons" 
                                 placeholder="e.g., Extra roti = 1"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_amount">Custom Amount</label></th>
                            <td>
                                <input type="number" name="custom_amount" id="custom_amount" 
                                       step="0.01" min="0" 
                                       placeholder="Enter custom total amount (optional)">
                                <p class="description">If set, this will override the product's default price</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="order_notes">Notes</label></th>
                            <td>
                            <textarea name="order_notes" id="order_notes" rows="4" 
                            class="regular-text" style="width: 100%;" 
                            placeholder="Enter any special instructions or notes for this order"></textarea>
                            <p class="description">These notes will be visible to both admin and customer.</p>
                        </td>
                    </tr>                       
                    </table>
                </div>
            </div>

            <div class="submit-section">
                <input type="submit" name="my_add_order_submit" class="button button-primary" value="Create Order">
            </div>
            
            <style>
            .custom-meal-builder {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #ddd;
            }
            
            .meal-component-row {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }
            
            .component-label {
                min-width: 100px;
                font-weight: bold;
                color: #333;
            }
            
            .meal-component-row input,
            .meal-component-row select {
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 6px 8px;
                font-size: 14px;
            }
            
            .meal-component-row span {
                color: #666;
                font-weight: 500;
            }
            
            .meal-preview {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            
            #custom_meal_preview {
                min-height: 40px;
                display: flex;
                align-items: center;
            }
            
            .meal-preview strong {
                color: #333;
                margin-bottom: 8px;
                display: block;
            }
            
            @media (max-width: 768px) {
                .meal-component-row {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                }
                
                .component-label {
                    min-width: auto;
                    margin-bottom: 5px;
                }
                
                .meal-component-row input,
                .meal-component-row select {
                    width: 100%;
                    max-width: 200px;
                }
            }
            </style>
            <script type="text/javascript">
jQuery(document).ready(function($) {
    // Optional: Add Select2 for better UX if you want
    if (typeof $.fn.select2 !== 'undefined') {
        $('#preferred_days').select2({
            placeholder: 'Select delivery days',
            width: '100%'
        });
    }
});
</script>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize Select2 for preferred days
    if (typeof $.fn.select2 !== 'undefined') {
        $('#preferred_days').select2({
            placeholder: 'Select delivery days',
            width: '100%'
        });
    }

    // Customer type toggle
    $('input[name="customer_type"]').on('change', function() {
        if ($(this).val() === 'existing') {
            $('#customer-lookup').show();
            $('#customer-form-fields').addClass('disabled-fields');
        } else {
            $('#customer-lookup').hide();
            $('#customer-form-fields').removeClass('disabled-fields');
            clearFormFields();
        }
    });

    // Customer search
    $('#search_customer').on('click', function() {
        const searchTerm = $('#customer_search').val();
        if (!searchTerm) return;

        $(this).prop('disabled', true).text('Searching...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'search_wc_customer',
                nonce: '<?php echo wp_create_nonce("search_customer_nonce"); ?>',
                search_term: searchTerm
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.customer) {
                        fillCustomerData(response.data.customer);
                        $('#customer_search_results').html('<div class="notice notice-success"><p>Customer found! Form has been filled with their details.</p></div>');
                    } else {
                        $('#customer_search_results').html('<div class="notice notice-error"><p>No customer found with those details.</p></div>');
                    }
                } else {
                    $('#customer_search_results').html('<div class="notice notice-error"><p>Error searching for customer.</p></div>');
                }
            },
            error: function() {
                $('#customer_search_results').html('<div class="notice notice-error"><p>Error searching for customer.</p></div>');
            },
            complete: function() {
                $('#search_customer').prop('disabled', false).text('Search');
            }
        });
    });

    function fillCustomerData(customer) {
        $('#first_name').val(customer.first_name);
        $('#last_name').val(customer.last_name);
        $('#email').val(customer.email);
        $('#phone').val(customer.phone);
        $('#company').val(customer.company);
        $('#address_1').val(customer.address_1);
        $('#address_2').val(customer.address_2);
        $('#city').val(customer.city);
        $('#state').val(customer.state);
        $('#postcode').val(customer.postcode);
        $('#country').val(customer.country);
    }

    function clearFormFields() {
        const fields = ['first_name', 'last_name', 'email', 'phone', 'company', 
                       'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];
        fields.forEach(field => $(`#${field}`).val(''));
        $('#customer_search').val('');
        $('#customer_search_results').empty();
    }

    // Product type toggle
    $('input[name="product_type"]').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#existing-product-row').hide();
            $('#custom-product-rows').show();
            $('#product_id').prop('required', false);
            // Generate initial meal name
            generateCustomMealName();
        } else {
            $('#existing-product-row').show();
            $('#custom-product-rows').hide();
            $('#product_id').prop('required', true);
            clearCustomMealInputs();
        }
    });
    
    // Custom Meal Builder Functions
    function generateCustomMealName() {
        const nonvegQty = parseInt($('#nonveg_quantity').val()) || 0;
        const nonvegSize = $('#nonveg_size').val();
        const nonvegType = $('#nonveg_type').val().trim();
        
        const vegQty = parseInt($('#veg_quantity').val()) || 0;
        const vegSize = $('#veg_size').val();
        const vegType = $('#veg_type').val().trim();
        
        const rotiQty = parseInt($('#roti_quantity').val()) || 0;
        const riceQty = parseInt($('#rice_quantity').val()) || 0;
        const raitaQty = parseInt($('#raita_quantity').val()) || 0;
        const saladQty = parseInt($('#salad_quantity').val()) || 0;
        
        let mealParts = [];
        
        // Add non-veg component
        if (nonvegQty > 0) {
            let nonvegPart = `${nonvegQty} Non-Veg(${nonvegSize})`;
            if (nonvegType) {
                nonvegPart += ` ${nonvegType}`;
            }
            mealParts.push(nonvegPart);
        }
        
        // Add veg component
        if (vegQty > 0) {
            let vegPart = `${vegQty} Veg(${vegSize})`;
            if (vegType) {
                vegPart += ` ${vegType}`;
            }
            mealParts.push(vegPart);
        }
        
        // Add roti component
        if (rotiQty > 0) {
            mealParts.push(`${rotiQty} Rotis`);
        }
        
        // Add rice component
        if (riceQty > 0) {
            mealParts.push(`${riceQty} Rice`);
        }
        
        // Add raita component
        if (raitaQty > 0) {
            mealParts.push(`${raitaQty} Raita`);
        }
        
        // Add salad component
        if (saladQty > 0) {
            mealParts.push(`${saladQty} Salad`);
        }
        
        let productName = 'Custom Meal';
        if (mealParts.length > 0) {
            productName += ' - ' + mealParts.join(' + ');
        }
        
        // Update the preview and hidden field
        $('#custom_meal_preview').text(productName);
        $('#custom_product_name').val(productName);
        
        return productName;
    }
    
    function clearCustomMealInputs() {
        $('#nonveg_quantity, #veg_quantity, #roti_quantity, #rice_quantity, #raita_quantity, #salad_quantity').val(0);
        $('#nonveg_type, #veg_type').val('');
        $('#nonveg_size, #veg_size').val('8oz');
        $('#custom_meal_preview').text('Custom Meal - Please configure items above');
        $('#custom_product_name').val('');
    }
    
    // Bind change events to custom meal inputs
    $('#nonveg_quantity, #nonveg_size, #nonveg_type, #veg_quantity, #veg_size, #veg_type, #roti_quantity, #rice_quantity, #raita_quantity, #salad_quantity').on('input change keyup', function() {
        generateCustomMealName();
    });
});
</script>

            </form>
        </div>
    </div>
    <?php
    }

    /**
     * Handle form submission to create user (if needed) and create the order
     */
    public function handle_form_submission(): void {
        if (!isset($_POST['my_add_order_submit'])) {
            return;
        }

        // Verify nonce
        check_admin_referer('my_add_order_nonce');

        // Basic sanitization
        $first_name  = sanitize_text_field($_POST['first_name']);
        $last_name   = sanitize_text_field($_POST['last_name']);
        $phone       = sanitize_text_field($_POST['phone']);
        $email       = sanitize_email($_POST['email']);
        $company     = sanitize_text_field($_POST['company'] ?? '');
        $address_1   = sanitize_text_field($_POST['address_1'] ?? '');
        $address_2   = sanitize_text_field($_POST['address_2'] ?? '');
        $city        = sanitize_text_field($_POST['city'] ?? '');
        $state       = sanitize_text_field($_POST['state'] ?? 'ON');
        $postcode    = sanitize_text_field($_POST['postcode'] ?? '');
        $country     = sanitize_text_field($_POST['country'] ?? 'CA');
        $product_type = sanitize_text_field($_POST['product_type']);
        $quantity    = absint($_POST['quantity']);
        $start_date = sanitize_text_field($_POST['start_date']);
        // In the handle_form_submission method, update the preferred_days sanitization
        $preferred_days_array = isset($_POST['preferred_days']) ? (array)$_POST['preferred_days'] : [];
        $preferred_days_array = array_map('sanitize_text_field', $preferred_days_array);
        // Sort days in correct order (Monday to Sunday)
        $days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $preferred_days_array = array_intersect($days_order, $preferred_days_array);
        $preferred_days = implode(' - ', $preferred_days_array);
        $meal_type = sanitize_text_field($_POST['meal_type']);
        $delivery_type = sanitize_text_field($_POST['delivery_type']);
        $addons = sanitize_text_field($_POST['addons']);
        $number_of_tiffins = absint($_POST['number_of_tiffins']);
        $remaining_tiffins = isset($_POST['remaining_tiffins']) && $_POST['remaining_tiffins'] !== '' 
            ? absint($_POST['remaining_tiffins']) 
            : null;
        $custom_amount = isset($_POST['custom_amount']) && $_POST['custom_amount'] !== '' 
            ? floatval($_POST['custom_amount']) 
            : null;

        if ($product_type === 'custom') {
            // Get custom meal details
            $nonveg_quantity = absint($_POST['nonveg_quantity'] ?? 0);
            $nonveg_size = sanitize_text_field($_POST['nonveg_size'] ?? '8oz');
            $nonveg_type = sanitize_text_field($_POST['nonveg_type'] ?? '');
            
            $veg_quantity = absint($_POST['veg_quantity'] ?? 0);
            $veg_size = sanitize_text_field($_POST['veg_size'] ?? '8oz');
            $veg_type = sanitize_text_field($_POST['veg_type'] ?? '');
            
            $roti_quantity = absint($_POST['roti_quantity'] ?? 0);
            $rice_quantity = absint($_POST['rice_quantity'] ?? 0);
            $raita_quantity = absint($_POST['raita_quantity'] ?? 0);
            $salad_quantity = absint($_POST['salad_quantity'] ?? 0);
            
            // Validate that at least one component is configured
            if ($nonveg_quantity === 0 && $veg_quantity === 0 && $roti_quantity === 0 && $rice_quantity === 0 && $raita_quantity === 0 && $salad_quantity === 0) {
                wp_die('Please configure at least one meal component for the custom meal.');
            }
            
            // Generate product name automatically (but also accept from hidden field if available)
            $product_name = sanitize_text_field($_POST['custom_product_name'] ?? '');
            
            if (empty($product_name)) {
                // Generate product name from components
                $meal_parts = [];
                
                if ($nonveg_quantity > 0) {
                    $nonveg_part = "$nonveg_quantity Non-Veg($nonveg_size)";
                    if (!empty($nonveg_type)) {
                        $nonveg_part .= " $nonveg_type";
                    }
                    $meal_parts[] = $nonveg_part;
                }
                
                if ($veg_quantity > 0) {
                    $veg_part = "$veg_quantity Veg($veg_size)";
                    if (!empty($veg_type)) {
                        $veg_part .= " $veg_type";
                    }
                    $meal_parts[] = $veg_part;
                }
                
                if ($roti_quantity > 0) {
                    $meal_parts[] = "$roti_quantity Rotis";
                }
                
                if ($rice_quantity > 0) {
                    $meal_parts[] = "$rice_quantity Rice";
                }
                
                if ($raita_quantity > 0) {
                    $meal_parts[] = "$raita_quantity Raita";
                }
                
                if ($salad_quantity > 0) {
                    $meal_parts[] = "$salad_quantity Salad";
                }
                
                $product_name = 'Custom Meal';
                if (!empty($meal_parts)) {
                    $product_name .= ' - ' . implode(' + ', $meal_parts);
                }
            }
            
            // Create custom product
            $product = new WC_Product_Simple();
            $product->set_name($product_name);
            $product->set_regular_price($custom_amount ?: 0); // Use custom amount if provided
            $product->set_status('private'); // Makes it invisible in shop
            $product->set_catalog_visibility('hidden'); // Additional hiding
            $product->set_meta_data([
                'custom_meal' => 'yes',
                'custom_meal_components' => [
                    'nonveg' => [
                        'quantity' => $nonveg_quantity,
                        'size' => $nonveg_size,
                        'type' => $nonveg_type
                    ],
                    'veg' => [
                        'quantity' => $veg_quantity,
                        'size' => $veg_size,
                        'type' => $veg_type
                    ],
                    'roti' => [
                        'quantity' => $roti_quantity
                    ],
                    'rice' => [
                        'quantity' => $rice_quantity
                    ],
                    'raita' => [
                        'quantity' => $raita_quantity
                    ],
                    'salad' => [
                        'quantity' => $salad_quantity
                    ]
                ]
            ]);
            $product->save();
            
            $product_id = $product->get_id();
        } else {
            $product_id = absint($_POST['product_id']);
        }

        // Check if user exists
        $user_id = email_exists($email);
        $is_new_user = false;

        if (!$user_id) {
            // Create a unique username from email
            $username = sanitize_user(current(explode('@', $email)), true);
            $counter = 1;
            $base_username = $username;
            
            // Ensure username is unique
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }
            
            $random_password = wp_generate_password(12, false);
            
            // Create new user
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => $random_password,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'customer'
            ]);
        
            if (is_wp_error($user_id)) {
                wp_die(sprintf(
                    'Error creating user: %s. Please try again or contact support.',
                    esc_html($user_id->get_error_message())
                ));
            }
        
            // Save user meta data
            update_user_meta($user_id, 'phone', $phone);

            // Save billing information
    update_user_meta($user_id, 'billing_first_name', $first_name);
    update_user_meta($user_id, 'billing_last_name', $last_name);
    update_user_meta($user_id, 'billing_company', $company);
    update_user_meta($user_id, 'billing_address_1', $address_1);
    update_user_meta($user_id, 'billing_address_2', $address_2);
    update_user_meta($user_id, 'billing_city', $city);
    update_user_meta($user_id, 'billing_state', $state);
    update_user_meta($user_id, 'billing_postcode', $postcode);
    update_user_meta($user_id, 'billing_country', $country);
    update_user_meta($user_id, 'billing_email', $email);
    update_user_meta($user_id, 'billing_phone', $phone);

    // Save shipping information
    update_user_meta($user_id, 'shipping_first_name', $first_name);
    update_user_meta($user_id, 'shipping_last_name', $last_name);
    update_user_meta($user_id, 'shipping_company', $company);
    update_user_meta($user_id, 'shipping_address_1', $address_1);
    update_user_meta($user_id, 'shipping_address_2', $address_2);
    update_user_meta($user_id, 'shipping_city', $city);
    update_user_meta($user_id, 'shipping_state', $state);
    update_user_meta($user_id, 'shipping_postcode', $postcode);
    update_user_meta($user_id, 'shipping_country', $country);
    update_user_meta($user_id, 'shipping_phone', $phone);

    $is_new_user = true;
}  else {
    // Update existing user's information
    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'user_email' => $email
    ]);

    // Update phone
    update_user_meta($user_id, 'phone', $phone);

    // Update billing information
    update_user_meta($user_id, 'billing_first_name', $first_name);
    update_user_meta($user_id, 'billing_last_name', $last_name);
    update_user_meta($user_id, 'billing_company', $company);
    update_user_meta($user_id, 'billing_address_1', $address_1);
    update_user_meta($user_id, 'billing_address_2', $address_2);
    update_user_meta($user_id, 'billing_city', $city);
    update_user_meta($user_id, 'billing_state', $state);
    update_user_meta($user_id, 'billing_postcode', $postcode);
    update_user_meta($user_id, 'billing_country', $country);
    update_user_meta($user_id, 'billing_email', $email);
    update_user_meta($user_id, 'billing_phone', $phone);

    // Update shipping information
    update_user_meta($user_id, 'shipping_first_name', $first_name);
    update_user_meta($user_id, 'shipping_last_name', $last_name);
    update_user_meta($user_id, 'shipping_company', $company);
    update_user_meta($user_id, 'shipping_address_1', $address_1);
    update_user_meta($user_id, 'shipping_address_2', $address_2);
    update_user_meta($user_id, 'shipping_city', $city);
    update_user_meta($user_id, 'shipping_state', $state);
    update_user_meta($user_id, 'shipping_postcode', $postcode);
    update_user_meta($user_id, 'shipping_country', $country);
    update_user_meta($user_id, 'shipping_phone', $phone);
}

        

// Create the order
$order = wc_create_order();
if (is_wp_error($order)) {
    wp_die('Error creating order: ' . $order->get_error_message());
}

// Add product line item with meta data
$product = wc_get_product($product_id);
if (!$product) {
    wp_die('Invalid product selected.');
}


// Add the product with meta data
$item_id = $order->add_product($product, $quantity);


// Add customer note if provided
if (!empty($_POST['order_notes'])) {
    $customer_note = sanitize_textarea_field($_POST['order_notes']);
    
    // Add the note to the order
    $order->add_order_note($customer_note, 1); // 1 means it's a customer note (visible to customer)
    
    // Also set it as the customer note that appears in emails
    $order->set_customer_note($customer_note);
}

// Add meta data to the line item regardless of custom amount
$item = $order->get_item($item_id);
$item->add_meta_data('Start Date', $start_date);
$item->add_meta_data('Prefered Days', $preferred_days);
$item->add_meta_data('Veg / Non Veg', $meal_type);
$item->add_meta_data('Delivery Type', $delivery_type);
$item->add_meta_data('Number Of Tiffins', $number_of_tiffins);
$item->add_meta_data('add-ons', $addons);

// If custom amount is set, update the line item total
if ($custom_amount !== null && $item_id) {
    $unit_price = $custom_amount / $quantity;

    // Update line item prices
    $item->set_subtotal($custom_amount);
    $item->set_total($custom_amount);
    $item->set_subtotal_tax(0);
    $item->set_total_tax(0);
    $item->add_meta_data('_custom_price', $unit_price);
}

$item->save(); // Save the item meta

 // Initialize tiffin count history
 $today = date('Y-m-d');
 $count_history = array();

// If remaining tiffins was specified, use that value
if ($remaining_tiffins !== null) {
    $count_history[$today] = array(
        'remaining_tiffins' => $remaining_tiffins,
        'delivery_days' => Satguru_Tiffin_Calculator::get_delivery_days(),
        'boxes_delivered' => Satguru_Tiffin_Calculator::calculate_boxes_for_date($order, $today)
    );
}

// Save the tiffin count history
if (!empty($count_history)) {
    $order->update_meta_data('tiffin_count_history', $count_history);
} 

// Set customer ID if user exists
if ($user_id && !is_wp_error($user_id)) {
    $order->set_customer_id($user_id);
}

        // Set both billing and shipping details
        // Set both billing and shipping details
        // Billing
        $order->set_billing_first_name($first_name);
        $order->set_billing_last_name($last_name);
        $order->set_billing_email($email);
        $order->set_billing_phone($phone);
        $order->set_billing_company($company);
        $order->set_billing_address_1($address_1);
        $order->set_billing_address_2($address_2);
        $order->set_billing_city($city);
        $order->set_billing_state($state);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);

        // Shipping (copy from billing by default)
        $order->set_shipping_first_name($first_name);
        $order->set_shipping_last_name($last_name);
        $order->set_shipping_company($company);
        $order->set_shipping_address_1($address_1);
        $order->set_shipping_address_2($address_2);
        $order->set_shipping_city($city);
        $order->set_shipping_state($state);
        $order->set_shipping_postcode($postcode);
        $order->set_shipping_country($country);
        $order->set_shipping_phone($phone);

        // add the custom meta data
        $order->update_meta_data('Start Date', $start_date);
        $order->update_meta_data('Prefered Days', $preferred_days);
        $order->update_meta_data('Veg / Non Veg', $meal_type);
        $order->update_meta_data('Delivery Type', $delivery_type);
        $order->update_meta_data('Number Of Tiffins', $number_of_tiffins);
        $order->update_meta_data('addons', $addons);  // Add this line



        // Calculate totals & save
        if ($custom_amount !== null) {
            // Set custom total
            $order->set_total($custom_amount);
        } else {
            // Calculate normal totals
            $order->calculate_totals();
        }

        $order->set_status('processing');
        
        // Final save to ensure all meta is stored
        $order->save();

        // Run save_daily_tiffin_count for the new order
        if (class_exists('Satguru_Daily_Tiffin_Counter')) {
            $counter = new Satguru_Daily_Tiffin_Counter();
            $counter->save_daily_tiffin_count($order->get_id());
        }

        // Log the meta data for debugging
        error_log('Order ID: ' . $order->get_id());
        

        // Redirect or show success message
        wp_redirect(add_query_arg([
            'page' => 'my-custom-add-order',
            'order_created' => 1,
            'new_user' => $is_new_user ? 1 : 0,
        ], admin_url('admin.php')));
        exit;
    }
    

    /**
     * Example function to retrieve dynamic fields from some theme or plugin option.
     * Admin can manage these fields in theme options, plugin settings, etc.
     */
}



// Instantiate our class
new My_Custom_Order_Admin_Page();



/**
 * Example of how admin could configure dynamic fields via code (or use a settings page).
 * This snippet can be placed in the same file or in a separate admin settings section.
 *
 * add_action('admin_init', function() {
 *     // Example: Hard-coded, but ideally you'd have a real settings page for the admin
 *     // update_option('my_dynamic_fields', [
 *     //     'special_instructions' => 'Special Instructions',
 *     //     'preferred_delivery_time' => 'Preferred Delivery Time'
 *     // ]);
 * });
 */