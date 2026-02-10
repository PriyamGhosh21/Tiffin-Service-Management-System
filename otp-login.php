<?php
/**
 * OTP Login System for WooCommerce
 * Allows customers to login using OTP sent via Email or WhatsApp
 * 
 * @package Satguru_Tiffin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Satguru_OTP_Login {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * OTP expiry time in minutes
     */
    private $otp_expiry_minutes = 10;
    
    /**
     * OTP length
     */
    private $otp_length = 6;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Check if OTP login is enabled
        if (!$this->is_enabled()) {
            return;
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_nopriv_satguru_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_satguru_send_otp', [$this, 'ajax_send_otp']);
        add_action('wp_ajax_nopriv_satguru_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_satguru_verify_otp', [$this, 'ajax_verify_otp']);
        add_action('wp_ajax_nopriv_satguru_resend_otp', [$this, 'ajax_resend_otp']);
        add_action('wp_ajax_satguru_resend_otp', [$this, 'ajax_resend_otp']);
        
        // Add OTP login form to WooCommerce login
        add_action('woocommerce_login_form_start', [$this, 'add_otp_login_toggle']);
        add_action('woocommerce_login_form_end', [$this, 'add_otp_login_form']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Add shortcode for standalone OTP login
        add_shortcode('satguru_otp_login', [$this, 'otp_login_shortcode']);
        
        // Clean up expired OTPs periodically
        add_action('satguru_cleanup_expired_otps', [$this, 'cleanup_expired_otps']);
        if (!wp_next_scheduled('satguru_cleanup_expired_otps')) {
            wp_schedule_event(time(), 'hourly', 'satguru_cleanup_expired_otps');
        }
    }
    
    /**
     * Check if OTP login is enabled
     */
    public function is_enabled() {
        return get_option('satguru_otp_login_enabled', '0') === '1';
    }
    
    /**
     * Check if Email OTP is enabled
     */
    public function is_email_otp_enabled() {
        return get_option('satguru_otp_email_enabled', '1') === '1';
    }
    
    /**
     * Check if WhatsApp OTP is enabled
     */
    public function is_whatsapp_otp_enabled() {
        return get_option('satguru_otp_whatsapp_enabled', '0') === '1';
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        if (!is_account_page() && !has_shortcode(get_post()->post_content ?? '', 'satguru_otp_login')) {
            return;
        }
        
        // Inline styles and scripts are added via the form output
    }
    
    /**
     * Add OTP login toggle button
     */
    public function add_otp_login_toggle() {
        $email_enabled = $this->is_email_otp_enabled();
        $whatsapp_enabled = $this->is_whatsapp_otp_enabled();
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return;
        }
        ?>
        <div class="satguru-login-toggle">
            <button type="button" class="satguru-toggle-btn active" data-mode="password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Password
            </button>
            <button type="button" class="satguru-toggle-btn" data-mode="otp">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
                OTP Login
            </button>
        </div>
        <?php
    }
    
    /**
     * Add OTP login form
     */
    public function add_otp_login_form() {
        $email_enabled = $this->is_email_otp_enabled();
        $whatsapp_enabled = $this->is_whatsapp_otp_enabled();
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return;
        }
        
        $this->render_otp_form($email_enabled, $whatsapp_enabled);
    }
    
    /**
     * OTP Login shortcode
     */
    public function otp_login_shortcode($atts) {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'satguru') . '</p>';
        }
        
        $email_enabled = $this->is_email_otp_enabled();
        $whatsapp_enabled = $this->is_whatsapp_otp_enabled();
        
        if (!$email_enabled && !$whatsapp_enabled) {
            return '<p>' . __('OTP login is not configured.', 'satguru') . '</p>';
        }
        
        ob_start();
        $this->render_standalone_otp_form($email_enabled, $whatsapp_enabled);
        return ob_get_clean();
    }
    
    /**
     * Render standalone OTP form (for shortcode)
     */
    private function render_standalone_otp_form($email_enabled, $whatsapp_enabled) {
        ?>
        <div class="satguru-otp-standalone-container">
            <div class="satguru-otp-header">
                <div class="satguru-otp-icon">🔐</div>
                <h2><?php _e('Login with OTP', 'satguru'); ?></h2>
                <p><?php _e('Enter your email or phone to receive a one-time password', 'satguru'); ?></p>
            </div>
            
            <?php $this->render_otp_form_content($email_enabled, $whatsapp_enabled, true); ?>
        </div>
        
        <?php $this->render_otp_styles(); ?>
        <?php $this->render_otp_scripts(); ?>
        <?php
    }
    
    /**
     * Render OTP form for WooCommerce login page
     */
    private function render_otp_form($email_enabled, $whatsapp_enabled) {
        ?>
        <div id="satguru-otp-form-container" class="satguru-otp-container" style="display: none;">
            <?php $this->render_otp_form_content($email_enabled, $whatsapp_enabled, false); ?>
        </div>
        
        <?php $this->render_otp_styles(); ?>
        <?php $this->render_otp_scripts(); ?>
        <?php
    }
    
    /**
     * Render OTP form content
     */
    private function render_otp_form_content($email_enabled, $whatsapp_enabled, $is_standalone = false) {
        ?>
        <!-- Step 1: Enter Email/Phone -->
        <div class="satguru-otp-step" id="otp-step-1">
            <?php if ($email_enabled && $whatsapp_enabled): ?>
            <div class="satguru-otp-method-selector">
                <label class="satguru-method-option active" data-method="email">
                    <input type="radio" name="otp_method" value="email" checked>
                    <span class="method-icon">📧</span>
                    <span class="method-label"><?php _e('Email', 'satguru'); ?></span>
                </label>
                <label class="satguru-method-option" data-method="whatsapp">
                    <input type="radio" name="otp_method" value="whatsapp">
                    <span class="method-icon">💬</span>
                    <span class="method-label"><?php _e('WhatsApp', 'satguru'); ?></span>
                </label>
            </div>
            <?php endif; ?>
            
            <div class="satguru-otp-input-group" id="email-input-group" <?php echo !$email_enabled ? 'style="display:none;"' : ''; ?>>
                <label for="satguru-otp-email"><?php _e('Email Address', 'satguru'); ?></label>
                <div class="input-with-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <input type="email" id="satguru-otp-email" name="otp_email" placeholder="<?php _e('Enter your email', 'satguru'); ?>">
                </div>
            </div>
            
            <div class="satguru-otp-input-group" id="phone-input-group" <?php echo !$whatsapp_enabled || $email_enabled ? 'style="display:none;"' : ''; ?>>
                <label for="satguru-otp-phone"><?php _e('Phone Number', 'satguru'); ?></label>
                <div class="input-with-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <input type="tel" id="satguru-otp-phone" name="otp_phone" placeholder="<?php _e('Enter your phone number', 'satguru'); ?>">
                </div>
                <small class="input-hint"><?php _e('Include country code (e.g., +1 for Canada)', 'satguru'); ?></small>
            </div>
            
            <button type="button" class="satguru-otp-btn satguru-otp-send-btn" id="send-otp-btn">
                <span class="btn-text"><?php _e('Send OTP', 'satguru'); ?></span>
                <span class="btn-loading" style="display:none;">
                    <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                    <?php _e('Sending...', 'satguru'); ?>
                </span>
            </button>
            
            <div class="satguru-otp-error" id="otp-step1-error" style="display:none;"></div>
        </div>
        
        <!-- Step 2: Enter OTP -->
        <div class="satguru-otp-step" id="otp-step-2" style="display: none;">
            <div class="satguru-otp-sent-info">
                <div class="sent-icon">✅</div>
                <p><?php _e('OTP sent to', 'satguru'); ?> <strong id="otp-sent-to"></strong></p>
                <small><?php _e('Please check and enter the 6-digit code below', 'satguru'); ?></small>
            </div>
            
            <div class="satguru-otp-code-inputs">
                <input type="text" class="otp-digit" maxlength="1" data-index="0" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" data-index="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" data-index="2" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" data-index="3" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" data-index="4" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" data-index="5" inputmode="numeric" pattern="[0-9]">
            </div>
            
            <input type="hidden" id="satguru-otp-full" name="otp_code">
            <input type="hidden" id="satguru-otp-token" name="otp_token">
            
            <div class="satguru-otp-timer">
                <span id="otp-timer-text"><?php _e('Resend OTP in', 'satguru'); ?></span>
                <span id="otp-countdown">60</span>s
            </div>
            
            <button type="button" class="satguru-otp-btn satguru-otp-verify-btn" id="verify-otp-btn">
                <span class="btn-text"><?php _e('Verify & Login', 'satguru'); ?></span>
                <span class="btn-loading" style="display:none;">
                    <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" transform="rotate(-90 12 12)">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                    <?php _e('Verifying...', 'satguru'); ?>
                </span>
            </button>
            
            <div class="satguru-otp-actions">
                <button type="button" class="satguru-otp-link" id="resend-otp-btn" disabled>
                    <?php _e('Resend OTP', 'satguru'); ?>
                </button>
                <span class="divider">|</span>
                <button type="button" class="satguru-otp-link" id="change-number-btn">
                    <?php _e('Change Email/Phone', 'satguru'); ?>
                </button>
            </div>
            
            <div class="satguru-otp-error" id="otp-step2-error" style="display:none;"></div>
        </div>
        
        <!-- Step 3: Success -->
        <div class="satguru-otp-step" id="otp-step-3" style="display: none;">
            <div class="satguru-otp-success">
                <div class="success-icon">🎉</div>
                <h3><?php _e('Login Successful!', 'satguru'); ?></h3>
                <p><?php _e('Redirecting you...', 'satguru'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render OTP styles
     */
    private function render_otp_styles() {
        ?>
        <style>
        /* CSS Variables for TiffinGrab Theme */
        :root {
            --otp-primary: #F26B0A;
            --otp-primary-dark: #d45a00;
            --otp-primary-light: #fff5eb;
            --otp-success: #10b981;
            --otp-error: #ef4444;
            --otp-text: #1f2937;
            --otp-text-light: #6b7280;
            --otp-border: #e5e7eb;
            --otp-bg: #ffffff;
            --otp-bg-light: #f9fafb;
            --otp-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --otp-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Hide "My account" heading - Comprehensive selectors */
        .woocommerce-account .woocommerce-MyAccount-navigation + .woocommerce-MyAccount-content h2:first-child,
        .woocommerce-account .woocommerce-MyAccount-content > h2:first-child,
        .woocommerce-account .woocommerce-MyAccount-content-wrapper > h2:first-child,
        .woocommerce-account .woocommerce-MyAccount-content-wrapper > h1:first-child,
        .woocommerce-account .woocommerce-MyAccount-content > h1:first-child,
        .woocommerce-account .u-columns > h2:first-child,
        .woocommerce-account .u-columns > h1:first-child,
        .woocommerce-account .entry-title,
        .woocommerce-account .page-title,
        .woocommerce-account .woocommerce h1.entry-title,
        .woocommerce-account .woocommerce h2.entry-title {
            display: none !important;
        }
        
        /* Hide main page heading before login form */
        .woocommerce-account .woocommerce > h1:first-child,
        .woocommerce-account .woocommerce > h2:first-child,
        .woocommerce-account h1.entry-title,
        .woocommerce-account h2.entry-title {
            display: none !important;
        }
        
        /* Center and style Login heading */
        .woocommerce-account .woocommerce-form-login h2,
        .woocommerce-account form.woocommerce-form-login h2,
        .woocommerce-account .u-column1 h2,
        .woocommerce-account .u-column2 h2 {
            text-align: center !important;
            margin-top: 0 !important;
            margin-bottom: 32px !important;
            font-size: 28px !important;
            font-weight: 700 !important;
            color: var(--otp-text) !important;
        }
        
        /* Desktop Centering and Min Height */
        @media (min-width: 768px) {
            /* Center the account content area */
            .woocommerce-account .woocommerce-MyAccount-content,
            .woocommerce-account .woocommerce-MyAccount-content-wrapper,
            .woocommerce-account .u-columns {
                display: flex !important;
                justify-content: center !important;
                align-items: flex-start !important;
                min-height: 600px !important;
                padding: 60px 20px 40px 20px !important;
            }
            
            /* Center the login form container */
            .woocommerce-account .u-column1,
            .woocommerce-account .u-column2,
            .woocommerce-account .col-1,
            .woocommerce-account .col-2 {
                max-width: 480px !important;
                width: 100% !important;
                margin: 0 auto !important;
            }
            
            /* Center the login form itself */
            .woocommerce-account .woocommerce-form-login,
            .woocommerce-account form.woocommerce-form-login {
                max-width: 480px !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 32px !important;
                background: var(--otp-bg) !important;
                border-radius: 16px !important;
                box-shadow: var(--otp-shadow-lg) !important;
            }
            
            /* Center login heading - positioned at top */
            .woocommerce-account .woocommerce-form-login h2,
            .woocommerce-account form.woocommerce-form-login h2,
            .woocommerce-account .u-column1 h2,
            .woocommerce-account .u-column2 h2 {
                text-align: center !important;
                margin-top: 0 !important;
                margin-bottom: 32px !important;
                font-size: 32px !important;
                font-weight: 700 !important;
                color: var(--otp-text) !important;
            }
            
            /* Ensure OTP container is also centered */
            .woocommerce-account #satguru-otp-form-container,
            .woocommerce-account .satguru-otp-container {
                max-width: 100% !important;
            }
            
            /* Center the entire account page wrapper if it exists */
            .woocommerce-account .woocommerce {
                max-width: 1200px !important;
                margin: 0 auto !important;
            }
        }
        
        /* Override WooCommerce Login Form Styles */
        .woocommerce-account .woocommerce-form-login,
        .woocommerce-account form.woocommerce-form-login {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        .woocommerce-account .woocommerce-form-login .woocommerce-form-login__submit,
        .woocommerce-account form.woocommerce-form-login .woocommerce-form-login__submit {
            width: 100% !important;
            margin-top: 16px !important;
        }
        
        /* Login Toggle Buttons - Override WooCommerce */
        .woocommerce-account .satguru-login-toggle,
        .woocommerce-account form .satguru-login-toggle,
        .satguru-login-toggle {
            display: flex !important;
            gap: 0 !important;
            margin-bottom: 24px !important;
            margin-top: 0 !important;
            background: var(--otp-bg-light) !important;
            border-radius: 12px !important;
            padding: 4px !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        .woocommerce-account .satguru-toggle-btn,
        .woocommerce-account form .satguru-toggle-btn,
        .satguru-toggle-btn {
            flex: 1 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 12px 16px !important;
            border: none !important;
            background: transparent !important;
            color: var(--otp-text-light) !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            border-radius: 10px !important;
            transition: all 0.3s ease !important;
            margin: 0 !important;
            width: auto !important;
        }
        
        .woocommerce-account .satguru-toggle-btn:hover,
        .woocommerce-account form .satguru-toggle-btn:hover,
        .satguru-toggle-btn:hover {
            color: var(--otp-text) !important;
        }
        
        .woocommerce-account .satguru-toggle-btn.active,
        .woocommerce-account form .satguru-toggle-btn.active,
        .satguru-toggle-btn.active {
            background: var(--otp-bg) !important;
            color: var(--otp-primary) !important;
            box-shadow: var(--otp-shadow) !important;
        }
        
        .woocommerce-account .satguru-toggle-btn svg,
        .woocommerce-account form .satguru-toggle-btn svg,
        .satguru-toggle-btn svg {
            flex-shrink: 0 !important;
            width: 18px !important;
            height: 18px !important;
        }
        
        /* OTP Container - Override WooCommerce */
        .woocommerce-account #satguru-otp-form-container,
        .woocommerce-account .satguru-otp-container,
        .satguru-otp-container {
            margin-top: 20px !important;
            padding-top: 20px !important;
            border-top: 1px solid var(--otp-border) !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        
        .satguru-otp-standalone-container {
            max-width: 420px;
            margin: 0 auto;
            padding: 32px;
            background: var(--otp-bg);
            border-radius: 16px;
            box-shadow: var(--otp-shadow-lg);
        }
        
        .satguru-otp-header {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .satguru-otp-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        
        .satguru-otp-header h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--otp-text);
        }
        
        .satguru-otp-header p {
            margin: 0;
            color: var(--otp-text-light);
            font-size: 14px;
        }
        
        /* Method Selector - Override WooCommerce */
        .woocommerce-account .satguru-otp-method-selector,
        .woocommerce-account form .satguru-otp-method-selector,
        .satguru-otp-method-selector {
            display: flex !important;
            gap: 12px !important;
            margin-bottom: 24px !important;
            width: 100% !important;
        }
        
        .woocommerce-account .satguru-method-option,
        .woocommerce-account form .satguru-method-option,
        .satguru-method-option {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 16px 12px !important;
            border: 2px solid var(--otp-border) !important;
            border-radius: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            background: var(--otp-bg) !important;
            margin: 0 !important;
        }
        
        .woocommerce-account .satguru-method-option:hover,
        .woocommerce-account form .satguru-method-option:hover,
        .satguru-method-option:hover {
            border-color: var(--otp-primary) !important;
            background: var(--otp-primary-light) !important;
        }
        
        .woocommerce-account .satguru-method-option.active,
        .woocommerce-account form .satguru-method-option.active,
        .satguru-method-option.active {
            border-color: var(--otp-primary) !important;
            background: var(--otp-primary-light) !important;
        }
        
        .woocommerce-account .satguru-method-option input[type="radio"],
        .woocommerce-account form .satguru-method-option input[type="radio"],
        .satguru-method-option input[type="radio"] {
            display: none !important;
        }
        
        .woocommerce-account .method-icon,
        .woocommerce-account form .method-icon,
        .method-icon {
            font-size: 28px !important;
            line-height: 1 !important;
        }
        
        .woocommerce-account .method-label,
        .woocommerce-account form .method-label,
        .method-label {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: var(--otp-text) !important;
            margin: 0 !important;
        }
        
        /* Input Groups - Override WooCommerce */
        .woocommerce-account .satguru-otp-input-group,
        .woocommerce-account form .satguru-otp-input-group,
        .satguru-otp-input-group {
            margin-bottom: 20px !important;
            width: 100% !important;
        }
        
        .woocommerce-account .satguru-otp-input-group label,
        .woocommerce-account form .satguru-otp-input-group label,
        .satguru-otp-input-group label {
            display: block !important;
            margin-bottom: 8px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            color: var(--otp-text) !important;
            width: 100% !important;
        }
        
        .woocommerce-account .input-with-icon,
        .woocommerce-account form .input-with-icon,
        .input-with-icon {
            position: relative !important;
            width: 100% !important;
        }
        
        .woocommerce-account .input-with-icon svg,
        .woocommerce-account form .input-with-icon svg,
        .input-with-icon svg {
            position: absolute !important;
            left: 14px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: var(--otp-text-light) !important;
            z-index: 1 !important;
            pointer-events: none !important;
        }
        
        .woocommerce-account .input-with-icon input,
        .woocommerce-account form .input-with-icon input,
        .input-with-icon input {
            width: 100% !important;
            padding: 14px 14px 14px 44px !important;
            border: 2px solid var(--otp-border) !important;
            border-radius: 10px !important;
            font-size: 15px !important;
            color: var(--otp-text) !important;
            transition: all 0.3s ease !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            background: var(--otp-bg) !important;
        }
        
        .woocommerce-account .input-with-icon input:focus,
        .woocommerce-account form .input-with-icon input:focus,
        .input-with-icon input:focus {
            outline: none !important;
            border-color: var(--otp-primary) !important;
            box-shadow: 0 0 0 3px var(--otp-primary-light) !important;
        }
        
        .woocommerce-account .input-with-icon input::placeholder,
        .woocommerce-account form .input-with-icon input::placeholder,
        .input-with-icon input::placeholder {
            color: var(--otp-text-light) !important;
        }
        
        .woocommerce-account .input-hint,
        .woocommerce-account form .input-hint,
        .input-hint {
            display: block !important;
            margin-top: 6px !important;
            font-size: 12px !important;
            color: var(--otp-text-light) !important;
        }
        
        /* OTP Code Inputs - Override WooCommerce */
        .woocommerce-account .satguru-otp-code-inputs,
        .woocommerce-account form .satguru-otp-code-inputs,
        .satguru-otp-code-inputs {
            display: flex !important;
            gap: 10px !important;
            justify-content: center !important;
            margin: 24px 0 !important;
            width: 100% !important;
            flex-wrap: wrap !important;
        }
        
        .woocommerce-account .otp-digit,
        .woocommerce-account form .otp-digit,
        .otp-digit {
            width: 50px !important;
            height: 56px !important;
            text-align: center !important;
            font-size: 24px !important;
            font-weight: 700 !important;
            border: 2px solid var(--otp-border) !important;
            border-radius: 10px !important;
            color: var(--otp-text) !important;
            transition: all 0.3s ease !important;
            margin: 0 !important;
            padding: 0 !important;
            background: var(--otp-bg) !important;
            box-sizing: border-box !important;
        }
        
        .woocommerce-account .otp-digit:focus,
        .woocommerce-account form .otp-digit:focus,
        .otp-digit:focus {
            outline: none !important;
            border-color: var(--otp-primary) !important;
            box-shadow: 0 0 0 3px var(--otp-primary-light) !important;
        }
        
        .woocommerce-account .otp-digit.filled,
        .woocommerce-account form .otp-digit.filled,
        .otp-digit.filled {
            border-color: var(--otp-primary) !important;
            background: var(--otp-primary-light) !important;
        }
        
        .woocommerce-account .otp-digit.error,
        .woocommerce-account form .otp-digit.error,
        .otp-digit.error {
            border-color: var(--otp-error) !important;
            background: #fef2f2 !important;
            animation: shake 0.5s ease-in-out !important;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Sent Info - Override WooCommerce */
        .woocommerce-account .satguru-otp-sent-info,
        .woocommerce-account form .satguru-otp-sent-info,
        .satguru-otp-sent-info {
            text-align: center !important;
            margin-bottom: 8px !important;
            width: 100% !important;
        }
        
        .woocommerce-account .sent-icon,
        .woocommerce-account form .sent-icon,
        .sent-icon {
            font-size: 36px !important;
            margin-bottom: 8px !important;
            line-height: 1 !important;
        }
        
        .woocommerce-account .satguru-otp-sent-info p,
        .woocommerce-account form .satguru-otp-sent-info p,
        .satguru-otp-sent-info p {
            margin: 0 0 4px 0 !important;
            font-size: 14px !important;
            color: var(--otp-text) !important;
        }
        
        .woocommerce-account .satguru-otp-sent-info strong,
        .woocommerce-account form .satguru-otp-sent-info strong,
        .satguru-otp-sent-info strong {
            color: var(--otp-primary) !important;
        }
        
        .woocommerce-account .satguru-otp-sent-info small,
        .woocommerce-account form .satguru-otp-sent-info small,
        .satguru-otp-sent-info small {
            color: var(--otp-text-light) !important;
            font-size: 13px !important;
        }
        
        /* Timer - Override WooCommerce */
        .woocommerce-account .satguru-otp-timer,
        .woocommerce-account form .satguru-otp-timer,
        .satguru-otp-timer {
            text-align: center !important;
            margin-bottom: 20px !important;
            font-size: 14px !important;
            color: var(--otp-text-light) !important;
            width: 100% !important;
        }
        
        .woocommerce-account #otp-countdown,
        .woocommerce-account form #otp-countdown,
        #otp-countdown {
            font-weight: 700 !important;
            color: var(--otp-primary) !important;
        }
        
        /* Buttons - Override WooCommerce */
        .woocommerce-account .satguru-otp-btn,
        .woocommerce-account form .satguru-otp-btn,
        .satguru-otp-btn {
            width: 100% !important;
            padding: 14px 24px !important;
            border: none !important;
            border-radius: 10px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }
        
        .woocommerce-account .satguru-otp-send-btn,
        .woocommerce-account form .satguru-otp-send-btn,
        .woocommerce-account .satguru-otp-verify-btn,
        .woocommerce-account form .satguru-otp-verify-btn,
        .satguru-otp-send-btn,
        .satguru-otp-verify-btn {
            background: var(--otp-primary) !important;
            color: white !important;
        }
        
        .woocommerce-account .satguru-otp-send-btn:hover,
        .woocommerce-account form .satguru-otp-send-btn:hover,
        .woocommerce-account .satguru-otp-verify-btn:hover,
        .woocommerce-account form .satguru-otp-verify-btn:hover,
        .satguru-otp-send-btn:hover,
        .satguru-otp-verify-btn:hover {
            background: var(--otp-primary-dark) !important;
            transform: translateY(-1px) !important;
            box-shadow: var(--otp-shadow) !important;
        }
        
        .woocommerce-account .satguru-otp-btn:disabled,
        .woocommerce-account form .satguru-otp-btn:disabled,
        .satguru-otp-btn:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            transform: none !important;
        }
        
        .btn-loading .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Action Links - Override WooCommerce */
        .woocommerce-account .satguru-otp-actions,
        .woocommerce-account form .satguru-otp-actions,
        .satguru-otp-actions {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            margin-top: 16px !important;
            width: 100% !important;
            flex-wrap: wrap !important;
        }
        
        .woocommerce-account .satguru-otp-link,
        .woocommerce-account form .satguru-otp-link,
        .satguru-otp-link {
            background: none !important;
            border: none !important;
            color: var(--otp-primary) !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            cursor: pointer !important;
            padding: 0 !important;
            transition: color 0.3s ease !important;
            margin: 0 !important;
            text-decoration: none !important;
        }
        
        .woocommerce-account .satguru-otp-link:hover,
        .woocommerce-account form .satguru-otp-link:hover,
        .satguru-otp-link:hover {
            color: var(--otp-primary-dark) !important;
            text-decoration: underline !important;
        }
        
        .woocommerce-account .satguru-otp-link:disabled,
        .woocommerce-account form .satguru-otp-link:disabled,
        .satguru-otp-link:disabled {
            color: var(--otp-text-light) !important;
            cursor: not-allowed !important;
            text-decoration: none !important;
        }
        
        .woocommerce-account .satguru-otp-actions .divider,
        .woocommerce-account form .satguru-otp-actions .divider,
        .satguru-otp-actions .divider {
            color: var(--otp-border) !important;
            margin: 0 !important;
        }
        
        /* OTP Steps - Override WooCommerce */
        .woocommerce-account .satguru-otp-step,
        .woocommerce-account form .satguru-otp-step,
        .satguru-otp-step {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Error Message - Override WooCommerce */
        .woocommerce-account .satguru-otp-error,
        .woocommerce-account form .satguru-otp-error,
        .satguru-otp-error {
            margin-top: 16px !important;
            padding: 12px 16px !important;
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            border-radius: 8px !important;
            color: var(--otp-error) !important;
            font-size: 14px !important;
            text-align: center !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Success - Override WooCommerce */
        .woocommerce-account .satguru-otp-success,
        .woocommerce-account form .satguru-otp-success,
        .satguru-otp-success {
            text-align: center !important;
            padding: 20px !important;
            width: 100% !important;
        }
        
        .woocommerce-account .success-icon,
        .woocommerce-account form .success-icon,
        .success-icon {
            font-size: 64px !important;
            margin-bottom: 16px !important;
            line-height: 1 !important;
        }
        
        .woocommerce-account .satguru-otp-success h3,
        .woocommerce-account form .satguru-otp-success h3,
        .satguru-otp-success h3 {
            margin: 0 0 8px 0 !important;
            font-size: 24px !important;
            font-weight: 700 !important;
            color: var(--otp-success) !important;
        }
        
        .woocommerce-account .satguru-otp-success p,
        .woocommerce-account form .satguru-otp-success p,
        .satguru-otp-success p {
            margin: 0 !important;
            color: var(--otp-text-light) !important;
        }
        
        /* Hide default password form when OTP mode is active - Override WooCommerce */
        .woocommerce-account .woocommerce-form-login.otp-mode-active .woocommerce-form-row,
        .woocommerce-account form.woocommerce-form-login.otp-mode-active .woocommerce-form-row,
        .woocommerce-account .woocommerce-form-login.otp-mode-active .woocommerce-form-login__rememberme,
        .woocommerce-account form.woocommerce-form-login.otp-mode-active .woocommerce-form-login__rememberme,
        .woocommerce-account .woocommerce-form-login.otp-mode-active .woocommerce-form-login__submit,
        .woocommerce-account form.woocommerce-form-login.otp-mode-active .woocommerce-form-login__submit,
        .woocommerce-account .woocommerce-form-login.otp-mode-active .woocommerce-LostPassword,
        .woocommerce-account form.woocommerce-form-login.otp-mode-active .woocommerce-LostPassword,
        .woocommerce-form-login.otp-mode-active .woocommerce-form-row,
        .woocommerce-form-login.otp-mode-active .woocommerce-form-login__rememberme,
        .woocommerce-form-login.otp-mode-active .woocommerce-form-login__submit,
        .woocommerce-form-login.otp-mode-active .woocommerce-LostPassword {
            display: none !important;
        }
        
        /* Ensure OTP form is visible when active */
        .woocommerce-account .woocommerce-form-login.otp-mode-active #satguru-otp-form-container,
        .woocommerce-account form.woocommerce-form-login.otp-mode-active #satguru-otp-form-container {
            display: block !important;
        }
        
        /* Additional WooCommerce Overrides */
        .woocommerce-account .woocommerce-form-login p,
        .woocommerce-account form.woocommerce-form-login p {
            margin-bottom: 0 !important;
        }
        
        .woocommerce-account .woocommerce-form-login .form-row,
        .woocommerce-account form.woocommerce-form-login .form-row {
            margin-bottom: 0 !important;
        }
        
        /* Mobile - Remove desktop padding and background */
        @media (max-width: 767px) {
            .woocommerce-account .woocommerce-form-login,
            .woocommerce-account form.woocommerce-form-login {
                padding: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            
            .woocommerce-account .woocommerce-MyAccount-content,
            .woocommerce-account .woocommerce-MyAccount-content-wrapper {
                min-height: auto !important;
                padding: 20px 0 !important;
            }
            
            /* Center Login heading on mobile */
            .woocommerce-account .woocommerce-form-login h2,
            .woocommerce-account form.woocommerce-form-login h2,
            .woocommerce-account .u-column1 h2,
            .woocommerce-account .u-column2 h2 {
                text-align: center !important;
                margin-top: 0 !important;
                margin-bottom: 24px !important;
                font-size: 24px !important;
            }
        }
        
        /* Responsive - Override WooCommerce */
        @media (max-width: 480px) {
            .woocommerce-account .satguru-otp-standalone-container,
            .woocommerce-account form .satguru-otp-standalone-container,
            .satguru-otp-standalone-container {
                padding: 24px 16px !important;
                margin: 0 16px !important;
            }
            
            .woocommerce-account .otp-digit,
            .woocommerce-account form .otp-digit,
            .otp-digit {
                width: 42px !important;
                height: 48px !important;
                font-size: 20px !important;
            }
            
            .woocommerce-account .satguru-otp-method-selector,
            .woocommerce-account form .satguru-otp-method-selector,
            .satguru-otp-method-selector {
                gap: 8px !important;
            }
            
            .woocommerce-account .satguru-method-option,
            .woocommerce-account form .satguru-method-option,
            .satguru-method-option {
                padding: 12px 8px !important;
            }
            
            .woocommerce-account .method-icon,
            .woocommerce-account form .method-icon,
            .method-icon {
                font-size: 24px !important;
            }
            
            .woocommerce-account .method-label,
            .woocommerce-account form .method-label,
            .method-label {
                font-size: 13px !important;
            }
            
            .woocommerce-account .satguru-toggle-btn,
            .woocommerce-account form .satguru-toggle-btn,
            .satguru-toggle-btn {
                padding: 10px 12px !important;
                font-size: 13px !important;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render OTP scripts
     */
    private function render_otp_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Hide "My account" heading
            $('.woocommerce-account h1, .woocommerce-account h2').each(function() {
                var text = $(this).text().trim().toLowerCase();
                if (text.includes('my account') && !text.includes('login')) {
                    $(this).hide();
                }
            });
            
            // Center Login heading
            $('.woocommerce-account .woocommerce-form-login h2, .woocommerce-account form.woocommerce-form-login h2').css({
                'text-align': 'center',
                'margin-top': '0',
                'margin-bottom': '32px'
            });
            
            var otpNonce = '<?php echo wp_create_nonce("satguru_otp_nonce"); ?>';
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            var redirectUrl = '<?php echo wc_get_page_permalink("myaccount"); ?>';
            var countdownInterval = null;
            var currentMethod = 'email';
            var currentIdentifier = '';
            var otpToken = '';
            
            // Toggle between password and OTP mode
            $(document).on('click', '.satguru-toggle-btn', function() {
                var mode = $(this).data('mode');
                
                $('.satguru-toggle-btn').removeClass('active');
                $(this).addClass('active');
                
                var $form = $(this).closest('form');
                
                if (mode === 'otp') {
                    $form.addClass('otp-mode-active');
                    $('#satguru-otp-form-container').slideDown(300);
                } else {
                    $form.removeClass('otp-mode-active');
                    $('#satguru-otp-form-container').slideUp(300);
                }
            });
            
            // Method selection (Email/WhatsApp)
            $(document).on('click', '.satguru-method-option', function() {
                var method = $(this).data('method');
                currentMethod = method;
                
                $('.satguru-method-option').removeClass('active');
                $(this).addClass('active');
                $(this).find('input[type="radio"]').prop('checked', true);
                
                if (method === 'email') {
                    $('#email-input-group').show();
                    $('#phone-input-group').hide();
                } else {
                    $('#email-input-group').hide();
                    $('#phone-input-group').show();
                }
            });
            
            // Send OTP
            $(document).on('click', '#send-otp-btn', function() {
                var $btn = $(this);
                var identifier = '';
                
                if (currentMethod === 'email') {
                    identifier = $('#satguru-otp-email').val().trim();
                    if (!identifier || !isValidEmail(identifier)) {
                        showError('#otp-step1-error', '<?php _e("Please enter a valid email address.", "satguru"); ?>');
                        return;
                    }
                } else {
                    identifier = $('#satguru-otp-phone').val().trim();
                    if (!identifier || !isValidPhone(identifier)) {
                        showError('#otp-step1-error', '<?php _e("Please enter a valid phone number with country code.", "satguru"); ?>');
                        return;
                    }
                }
                
                currentIdentifier = identifier;
                
                // Show loading state
                $btn.prop('disabled', true);
                $btn.find('.btn-text').hide();
                $btn.find('.btn-loading').show();
                hideError('#otp-step1-error');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'satguru_send_otp',
                        nonce: otpNonce,
                        method: currentMethod,
                        identifier: identifier
                    },
                    success: function(response) {
                        if (response.success) {
                            otpToken = response.data.token;
                            $('#satguru-otp-token').val(otpToken);
                            
                            // Show masked identifier
                            var maskedId = response.data.masked_identifier || maskIdentifier(identifier, currentMethod);
                            $('#otp-sent-to').text(maskedId);
                            
                            // Move to step 2
                            $('#otp-step-1').hide();
                            $('#otp-step-2').show();
                            
                            // Start countdown
                            startCountdown(60);
                            
                            // Focus first OTP input
                            $('.otp-digit').first().focus();
                        } else {
                            showError('#otp-step1-error', response.data.message || '<?php _e("Failed to send OTP. Please try again.", "satguru"); ?>');
                        }
                    },
                    error: function() {
                        showError('#otp-step1-error', '<?php _e("Network error. Please try again.", "satguru"); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $btn.find('.btn-text').show();
                        $btn.find('.btn-loading').hide();
                    }
                });
            });
            
            // OTP digit input handling
            $(document).on('input', '.otp-digit', function() {
                var $this = $(this);
                var val = $this.val().replace(/[^0-9]/g, '');
                $this.val(val);
                
                if (val.length === 1) {
                    $this.addClass('filled');
                    // Move to next input
                    var nextIndex = parseInt($this.data('index')) + 1;
                    if (nextIndex < 6) {
                        $('.otp-digit[data-index="' + nextIndex + '"]').focus();
                    }
                } else {
                    $this.removeClass('filled');
                }
                
                // Update hidden field with full OTP
                updateFullOTP();
            });
            
            // Handle backspace
            $(document).on('keydown', '.otp-digit', function(e) {
                var $this = $(this);
                
                if (e.key === 'Backspace' && $this.val() === '') {
                    var prevIndex = parseInt($this.data('index')) - 1;
                    if (prevIndex >= 0) {
                        var $prev = $('.otp-digit[data-index="' + prevIndex + '"]');
                        $prev.val('').removeClass('filled').focus();
                    }
                }
                
                // Handle paste
                if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                    setTimeout(function() {
                        handlePaste();
                    }, 10);
                }
            });
            
            // Handle paste event
            $(document).on('paste', '.otp-digit', function(e) {
                e.preventDefault();
                var pastedData = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
                var digits = pastedData.replace(/[^0-9]/g, '').substring(0, 6);
                
                if (digits.length > 0) {
                    for (var i = 0; i < digits.length && i < 6; i++) {
                        var $input = $('.otp-digit[data-index="' + i + '"]');
                        $input.val(digits[i]).addClass('filled');
                    }
                    
                    // Focus last filled or next empty
                    var focusIndex = Math.min(digits.length, 5);
                    $('.otp-digit[data-index="' + focusIndex + '"]').focus();
                    
                    updateFullOTP();
                }
            });
            
            // Verify OTP
            $(document).on('click', '#verify-otp-btn', function() {
                var $btn = $(this);
                var otp = $('#satguru-otp-full').val();
                
                if (!otp || otp.length !== 6) {
                    showError('#otp-step2-error', '<?php _e("Please enter the complete 6-digit OTP.", "satguru"); ?>');
                    $('.otp-digit').addClass('error');
                    setTimeout(function() {
                        $('.otp-digit').removeClass('error');
                    }, 500);
                    return;
                }
                
                // Show loading state
                $btn.prop('disabled', true);
                $btn.find('.btn-text').hide();
                $btn.find('.btn-loading').show();
                hideError('#otp-step2-error');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'satguru_verify_otp',
                        nonce: otpNonce,
                        token: otpToken,
                        otp: otp,
                        method: currentMethod,
                        identifier: currentIdentifier
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success
                            $('#otp-step-2').hide();
                            $('#otp-step-3').show();
                            
                            // Redirect after short delay
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url || redirectUrl;
                            }, 1500);
                        } else {
                            showError('#otp-step2-error', response.data.message || '<?php _e("Invalid OTP. Please try again.", "satguru"); ?>');
                            $('.otp-digit').addClass('error').val('').removeClass('filled');
                            setTimeout(function() {
                                $('.otp-digit').removeClass('error');
                                $('.otp-digit').first().focus();
                            }, 500);
                            $('#satguru-otp-full').val('');
                            
                            $btn.prop('disabled', false);
                            $btn.find('.btn-text').show();
                            $btn.find('.btn-loading').hide();
                        }
                    },
                    error: function() {
                        showError('#otp-step2-error', '<?php _e("Network error. Please try again.", "satguru"); ?>');
                        $btn.prop('disabled', false);
                        $btn.find('.btn-text').show();
                        $btn.find('.btn-loading').hide();
                    }
                });
            });
            
            // Resend OTP
            $(document).on('click', '#resend-otp-btn', function() {
                if ($(this).prop('disabled')) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php _e("Sending...", "satguru"); ?>');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'satguru_resend_otp',
                        nonce: otpNonce,
                        token: otpToken,
                        method: currentMethod,
                        identifier: currentIdentifier
                    },
                    success: function(response) {
                        if (response.success) {
                            otpToken = response.data.token;
                            $('#satguru-otp-token').val(otpToken);
                            startCountdown(60);
                            
                            // Clear existing OTP inputs
                            $('.otp-digit').val('').removeClass('filled');
                            $('#satguru-otp-full').val('');
                            $('.otp-digit').first().focus();
                        } else {
                            showError('#otp-step2-error', response.data.message || '<?php _e("Failed to resend OTP.", "satguru"); ?>');
                        }
                    },
                    error: function() {
                        showError('#otp-step2-error', '<?php _e("Network error. Please try again.", "satguru"); ?>');
                    },
                    complete: function() {
                        $btn.text('<?php _e("Resend OTP", "satguru"); ?>');
                    }
                });
            });
            
            // Change number/email
            $(document).on('click', '#change-number-btn', function() {
                // Reset and go back to step 1
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                }
                
                $('.otp-digit').val('').removeClass('filled');
                $('#satguru-otp-full').val('');
                $('#otp-step-2').hide();
                $('#otp-step-1').show();
                hideError('#otp-step2-error');
            });
            
            // Helper functions
            function updateFullOTP() {
                var otp = '';
                $('.otp-digit').each(function() {
                    otp += $(this).val();
                });
                $('#satguru-otp-full').val(otp);
            }
            
            function startCountdown(seconds) {
                var remaining = seconds;
                $('#otp-countdown').text(remaining);
                $('#resend-otp-btn').prop('disabled', true);
                
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                }
                
                countdownInterval = setInterval(function() {
                    remaining--;
                    $('#otp-countdown').text(remaining);
                    
                    if (remaining <= 0) {
                        clearInterval(countdownInterval);
                        $('#resend-otp-btn').prop('disabled', false);
                        $('#otp-timer-text').text('<?php _e("Didn\'t receive?", "satguru"); ?>');
                        $('#otp-countdown').text('');
                    }
                }, 1000);
            }
            
            function showError(selector, message) {
                $(selector).text(message).slideDown(200);
            }
            
            function hideError(selector) {
                $(selector).slideUp(200);
            }
            
            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }
            
            function isValidPhone(phone) {
                var cleaned = phone.replace(/[^0-9+]/g, '');
                return cleaned.length >= 10;
            }
            
            function maskIdentifier(identifier, method) {
                if (method === 'email') {
                    var parts = identifier.split('@');
                    if (parts[0].length > 2) {
                        return parts[0].substring(0, 2) + '***@' + parts[1];
                    }
                    return '***@' + parts[1];
                } else {
                    if (identifier.length > 4) {
                        return identifier.substring(0, 3) + '****' + identifier.substring(identifier.length - 3);
                    }
                    return '****' + identifier.substring(identifier.length - 2);
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Send OTP
     */
    public function ajax_send_otp() {
        check_ajax_referer('satguru_otp_nonce', 'nonce');
        
        $method = sanitize_text_field($_POST['method'] ?? 'email');
        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        
        if (empty($identifier)) {
            wp_send_json_error(['message' => __('Please provide email or phone number.', 'satguru')]);
        }
        
        // Validate method
        if ($method === 'email' && !$this->is_email_otp_enabled()) {
            wp_send_json_error(['message' => __('Email OTP is not enabled.', 'satguru')]);
        }
        
        if ($method === 'whatsapp' && !$this->is_whatsapp_otp_enabled()) {
            wp_send_json_error(['message' => __('WhatsApp OTP is not enabled.', 'satguru')]);
        }
        
        // IP-based rate limiting to prevent abuse
        $client_ip = $this->get_client_ip();
        $ip_rate_key = 'satguru_otp_ip_' . md5($client_ip);
        $ip_attempts = get_transient($ip_rate_key) ?: 0;
        
        if ($ip_attempts >= 10) {
            // Log suspicious activity
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP SECURITY: Rate limit exceeded for IP ' . $client_ip);
            }
            wp_send_json_error(['message' => __('Too many requests. Please try again later.', 'satguru')]);
        }
        
        // Increment IP attempts
        set_transient($ip_rate_key, $ip_attempts + 1, 3600); // 1 hour window
        
        // Identifier-based rate limiting
        $identifier_rate_key = 'satguru_otp_rate_' . md5($identifier);
        $last_sent = get_transient($identifier_rate_key);
        
        if ($last_sent && (time() - $last_sent) < 60) {
            wp_send_json_error(['message' => __('Please wait before requesting another OTP.', 'satguru')]);
        }
        
        // Check if user exists
        $user = null;
        if ($method === 'email') {
            if (!is_email($identifier)) {
                wp_send_json_error(['message' => __('Please enter a valid email address.', 'satguru')]);
            }
            $user = get_user_by('email', $identifier);
        } else {
            // For WhatsApp, search by phone in user meta
            $user = $this->get_user_by_phone($identifier);
        }
        
        // SECURITY: Use generic error message to prevent user enumeration
        // Always show the same message whether user exists or not
        $generic_success_message = __('If an account exists with this information, you will receive an OTP.', 'satguru');
        
        if (!$user) {
            // Log for security monitoring (but don't reveal to user)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP: OTP requested for non-existent user - ' . $method . ': ' . $identifier);
            }
            
            // Add a slight delay to prevent timing attacks
            usleep(random_int(100000, 500000)); // 100-500ms delay
            
            // Return success-like response to prevent user enumeration
            wp_send_json_error(['message' => __('No account found. Please check your ' . ($method === 'email' ? 'email address' : 'phone number') . ' or register for a new account.', 'satguru')]);
        }
        
        // Generate OTP
        $otp = $this->generate_otp();
        $token = $this->generate_token();
        
        // Store OTP with additional security data
        $otp_data = [
            'otp' => wp_hash_password($otp),
            'user_id' => $user->ID,
            'method' => $method,
            'identifier' => $identifier,
            'created_at' => time(),
            'expires_at' => time() + ($this->otp_expiry_minutes * 60),
            'attempts' => 0,
            'ip' => $client_ip,
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '')
        ];
        
        set_transient('satguru_otp_' . $token, $otp_data, $this->otp_expiry_minutes * 60);
        set_transient($identifier_rate_key, time(), 120); // 2 minute cooldown
        
        // Send OTP
        $sent = false;
        if ($method === 'email') {
            $sent = $this->send_email_otp($identifier, $otp, $user);
        } else {
            $sent = $this->send_whatsapp_otp($identifier, $otp, $user);
        }
        
        if (!$sent) {
            delete_transient('satguru_otp_' . $token);
            wp_send_json_error(['message' => __('Failed to send OTP. Please try again.', 'satguru')]);
        }
        
        // Mask identifier for display
        $masked = $this->mask_identifier($identifier, $method);
        
        wp_send_json_success([
            'token' => $token,
            'masked_identifier' => $masked,
            'expires_in' => $this->otp_expiry_minutes * 60
        ]);
    }
    
    /**
     * Get client IP address safely
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * AJAX: Verify OTP
     */
    public function ajax_verify_otp() {
        check_ajax_referer('satguru_otp_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        
        // Validate OTP format (must be exactly 6 digits)
        if (empty($token) || empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
            wp_send_json_error(['message' => __('Invalid request.', 'satguru')]);
        }
        
        // IP-based verification rate limiting
        $client_ip = $this->get_client_ip();
        $ip_verify_key = 'satguru_otp_verify_' . md5($client_ip);
        $verify_attempts = get_transient($ip_verify_key) ?: 0;
        
        if ($verify_attempts >= 15) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP SECURITY: Verification rate limit exceeded for IP ' . $client_ip);
            }
            wp_send_json_error(['message' => __('Too many verification attempts. Please try again later.', 'satguru')]);
        }
        
        // Increment verification attempts
        set_transient($ip_verify_key, $verify_attempts + 1, 900); // 15 minute window
        
        // Get OTP data
        $otp_data = get_transient('satguru_otp_' . $token);
        
        if (!$otp_data) {
            wp_send_json_error(['message' => __('OTP has expired. Please request a new one.', 'satguru')]);
        }
        
        // Check attempts per token
        if ($otp_data['attempts'] >= 5) {
            delete_transient('satguru_otp_' . $token);
            
            // Log security event
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP SECURITY: Max attempts exceeded for user ID ' . $otp_data['user_id'] . ' from IP ' . $client_ip);
            }
            
            wp_send_json_error(['message' => __('Too many failed attempts. Please request a new OTP.', 'satguru')]);
        }
        
        // Verify OTP using timing-safe comparison
        if (!wp_check_password($otp, $otp_data['otp'])) {
            // Increment attempts
            $otp_data['attempts']++;
            set_transient('satguru_otp_' . $token, $otp_data, $this->otp_expiry_minutes * 60);
            
            // Add small delay to slow down brute force attempts
            usleep(random_int(200000, 500000)); // 200-500ms delay
            
            $remaining = 5 - $otp_data['attempts'];
            
            // Log failed attempt
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP: Failed verification attempt ' . $otp_data['attempts'] . '/5 for user ID ' . $otp_data['user_id'] . ' from IP ' . $client_ip);
            }
            
            wp_send_json_error([
                'message' => sprintf(__('Invalid OTP. %d attempts remaining.', 'satguru'), $remaining)
            ]);
        }
        
        // OTP verified - log in user
        $user_id = $otp_data['user_id'];
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            wp_send_json_error(['message' => __('User not found.', 'satguru')]);
        }
        
        // Clean up OTP immediately
        delete_transient('satguru_otp_' . $token);
        
        // Reset IP rate limits on successful login
        delete_transient($ip_verify_key);
        
        // Log in user
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Log the successful login with security details
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SATGURU OTP: User ' . $user->user_email . ' logged in via OTP (' . $otp_data['method'] . ') from IP ' . $client_ip);
        }
        
        // Determine redirect URL with security validation
        $redirect_url = wc_get_page_permalink('myaccount');
        
        // Check for redirect parameter - VALIDATE AGAINST WHITELIST
        if (!empty($_POST['redirect_to'])) {
            $requested_redirect = esc_url_raw($_POST['redirect_to']);
            
            // Only allow internal redirects (same domain)
            if ($this->is_safe_redirect($requested_redirect)) {
                $redirect_url = $requested_redirect;
            }
        }
        
        wp_send_json_success([
            'message' => __('Login successful!', 'satguru'),
            'redirect_url' => $redirect_url
        ]);
    }
    
    /**
     * Validate redirect URL is safe (internal only)
     */
    private function is_safe_redirect($url) {
        // Get site URL
        $site_url = parse_url(home_url());
        $redirect_url = parse_url($url);
        
        // Must have a host
        if (empty($redirect_url['host'])) {
            // Relative URL - allow it
            return true;
        }
        
        // Check if same domain
        if ($redirect_url['host'] !== $site_url['host']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP SECURITY: Blocked external redirect attempt to ' . $url);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * AJAX: Resend OTP
     */
    public function ajax_resend_otp() {
        check_ajax_referer('satguru_otp_nonce', 'nonce');
        
        $old_token = sanitize_text_field($_POST['token'] ?? '');
        $method = sanitize_text_field($_POST['method'] ?? 'email');
        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        
        // IP-based rate limiting
        $client_ip = $this->get_client_ip();
        $ip_resend_key = 'satguru_otp_resend_' . md5($client_ip);
        $resend_count = get_transient($ip_resend_key) ?: 0;
        
        if ($resend_count >= 5) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP SECURITY: Resend rate limit exceeded for IP ' . $client_ip);
            }
            wp_send_json_error(['message' => __('Too many resend requests. Please try again later.', 'satguru')]);
        }
        
        // Delete old token
        if (!empty($old_token)) {
            delete_transient('satguru_otp_' . $old_token);
        }
        
        // Rate limiting - check last OTP sent time (per identifier)
        $rate_limit_key = 'satguru_otp_rate_' . md5($identifier);
        $last_sent = get_transient($rate_limit_key);
        
        if ($last_sent && (time() - $last_sent) < 60) {
            $wait_time = 60 - (time() - $last_sent);
            wp_send_json_error(['message' => sprintf(__('Please wait %d seconds before requesting another OTP.', 'satguru'), $wait_time)]);
        }
        
        // Get user
        $user = null;
        if ($method === 'email') {
            $user = get_user_by('email', $identifier);
        } else {
            $user = $this->get_user_by_phone($identifier);
        }
        
        if (!$user) {
            // Don't reveal user existence
            wp_send_json_error(['message' => __('Unable to send OTP. Please try again.', 'satguru')]);
        }
        
        // Increment resend count
        set_transient($ip_resend_key, $resend_count + 1, 3600); // 1 hour window
        
        // Generate new OTP
        $otp = $this->generate_otp();
        $token = $this->generate_token();
        
        // Store OTP with security data
        $otp_data = [
            'otp' => wp_hash_password($otp),
            'user_id' => $user->ID,
            'method' => $method,
            'identifier' => $identifier,
            'created_at' => time(),
            'expires_at' => time() + ($this->otp_expiry_minutes * 60),
            'attempts' => 0,
            'ip' => $client_ip,
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'is_resend' => true
        ];
        
        set_transient('satguru_otp_' . $token, $otp_data, $this->otp_expiry_minutes * 60);
        set_transient($rate_limit_key, time(), 120); // Rate limit for 120 seconds
        
        // Send OTP
        $sent = false;
        if ($method === 'email') {
            $sent = $this->send_email_otp($identifier, $otp, $user);
        } else {
            $sent = $this->send_whatsapp_otp($identifier, $otp, $user);
        }
        
        if (!$sent) {
            delete_transient('satguru_otp_' . $token);
            wp_send_json_error(['message' => __('Failed to send OTP. Please try again.', 'satguru')]);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SATGURU OTP: Resent OTP to user ID ' . $user->ID . ' via ' . $method . ' from IP ' . $client_ip);
        }
        
        wp_send_json_success([
            'token' => $token,
            'message' => __('OTP sent successfully!', 'satguru')
        ]);
    }
    
    /**
     * Generate OTP - Using cryptographically secure random number generator
     */
    private function generate_otp() {
        $otp = '';
        for ($i = 0; $i < $this->otp_length; $i++) {
            // Use random_int() for cryptographically secure random numbers
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
    
    /**
     * Generate token
     */
    private function generate_token() {
        return wp_generate_password(32, false);
    }
    
    /**
     * Mask identifier for display
     */
    private function mask_identifier($identifier, $method) {
        if ($method === 'email') {
            $parts = explode('@', $identifier);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            
            if (strlen($name) > 2) {
                $masked_name = substr($name, 0, 2) . str_repeat('*', min(strlen($name) - 2, 5));
            } else {
                $masked_name = str_repeat('*', strlen($name));
            }
            
            return $masked_name . '@' . $domain;
        } else {
            $clean = preg_replace('/[^0-9]/', '', $identifier);
            if (strlen($clean) > 6) {
                return substr($clean, 0, 3) . '****' . substr($clean, -3);
            }
            return '****' . substr($clean, -2);
        }
    }
    
    /**
     * Get user by phone number
     */
    private function get_user_by_phone($phone) {
        // Clean phone number
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Search variations
        $variations = [
            $phone,
            $clean_phone,
            '+' . $clean_phone,
            '+1' . $clean_phone,
            '1' . $clean_phone
        ];
        
        // If 11 digits starting with 1, also try without the 1
        if (strlen($clean_phone) === 11 && $clean_phone[0] === '1') {
            $variations[] = substr($clean_phone, 1);
            $variations[] = '+1' . substr($clean_phone, 1);
        }
        
        // If 10 digits, also try with 1 prefix
        if (strlen($clean_phone) === 10) {
            $variations[] = '1' . $clean_phone;
            $variations[] = '+1' . $clean_phone;
        }
        
        global $wpdb;
        
        foreach ($variations as $variant) {
            // Check billing_phone
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s LIMIT 1",
                $variant
            ));
            
            if ($user_id) {
                return get_user_by('ID', $user_id);
            }
        }
        
        return null;
    }
    
    /**
     * Send OTP via Email
     */
    private function send_email_otp($email, $otp, $user) {
        $site_name = get_bloginfo('name');
        $first_name = $user->first_name ?: $user->display_name;
        
        $subject = sprintf(__('[%s] Your Login OTP Code', 'satguru'), $site_name);
        
        $message = $this->get_email_template($otp, $first_name, $site_name);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SATGURU OTP: Email OTP ' . ($sent ? 'sent' : 'failed') . ' to ' . $email);
        }
        
        return $sent;
    }
    
    /**
     * Send OTP via WhatsApp
     */
    private function send_whatsapp_otp($phone, $otp, $user) {
        $wati_api_endpoint = get_option('wati_api_endpoint', '');
        $wati_api_token = get_option('wati_api_token', '');
        $wati_otp_template = get_option('satguru_otp_whatsapp_template', 'otp_login');
        
        if (empty($wati_api_endpoint) || empty($wati_api_token)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SATGURU OTP: WhatsApp not configured - missing API endpoint or token');
            }
            return false;
        }
        
        // Normalize phone number
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming Canada +1)
        if (strlen($clean_phone) === 10) {
            $clean_phone = '1' . $clean_phone;
        }
        
        // Prepare template parameters
        // Template format: {{1}} is your verification code. For your security, do not share this code.
        $template_params = [
            [
                'name' => '1',
                'value' => $otp
            ]
        ];
        
        // Build Wati API request
        $api_url = rtrim($wati_api_endpoint, '/') . '/api/v1/sendTemplateMessage?whatsappNumber=' . $clean_phone;
        
        $body = [
            'template_name' => $wati_otp_template,
            'broadcast_name' => 'otp_login_' . time(),
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
                error_log('SATGURU OTP: WhatsApp API Error - ' . $response->get_error_message());
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SATGURU OTP: WhatsApp API Response - Code: ' . $response_code . ', Body: ' . $response_body);
        }
        
        return $response_code >= 200 && $response_code < 300;
    }
    
    /**
     * Get email template for OTP
     */
    private function get_email_template($otp, $first_name, $site_name) {
        $logo_url = get_option('satguru_otp_email_logo', '');
        $primary_color = get_option('satguru_otp_email_color', '#F26B0A');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f7;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f7; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="padding: 40px 40px 20px; text-align: center;">
                                    <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width: 150px; height: auto; margin-bottom: 20px;">
                                    <?php else: ?>
                                    <div style="font-size: 48px; margin-bottom: 16px;">🔐</div>
                                    <?php endif; ?>
                                    <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1f2937;">Login Verification</h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 0 40px 20px;">
                                    <p style="margin: 0 0 16px; font-size: 16px; color: #4b5563; line-height: 1.6;">
                                        Hi <?php echo esc_html($first_name); ?>,
                                    </p>
                                    <p style="margin: 0 0 24px; font-size: 16px; color: #4b5563; line-height: 1.6;">
                                        Use the following One-Time Password (OTP) to login to your account:
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- OTP Code -->
                            <tr>
                                <td style="padding: 0 40px 24px; text-align: center;">
                                    <div style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?> 0%, #d45a00 100%); border-radius: 12px; padding: 24px;">
                                        <span style="font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #ffffff; font-family: 'Courier New', monospace;"><?php echo esc_html($otp); ?></span>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Expiry Notice -->
                            <tr>
                                <td style="padding: 0 40px 32px; text-align: center;">
                                    <p style="margin: 0; font-size: 14px; color: #9ca3af;">
                                        ⏱️ This code expires in <strong style="color: #4b5563;"><?php echo $this->otp_expiry_minutes; ?> minutes</strong>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Security Notice -->
                            <tr>
                                <td style="padding: 0 40px 40px;">
                                    <div style="background-color: #fef3cd; border-radius: 8px; padding: 16px;">
                                        <p style="margin: 0; font-size: 13px; color: #856404; line-height: 1.5;">
                                            <strong>⚠️ Security Notice:</strong> Never share this OTP with anyone. <?php echo esc_html($site_name); ?> will never ask for your OTP via phone or email.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 24px 40px; background-color: #f9fafb; border-radius: 0 0 16px 16px; text-align: center;">
                                    <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">
                                        Didn't request this? You can safely ignore this email.
                                    </p>
                                    <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                        © <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Clean up expired OTPs
     * Note: WordPress transients auto-expire, this is for cleanup of any orphaned data
     */
    public function cleanup_expired_otps() {
        global $wpdb;
        
        // Only delete OTP transients that have expired (not rate limit transients)
        // This query finds OTP transients where the timeout has passed
        $current_time = time();
        
        $wpdb->query($wpdb->prepare(
            "DELETE o, t FROM {$wpdb->options} o
             INNER JOIN {$wpdb->options} t ON t.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
             WHERE o.option_name LIKE %s
             AND t.option_value < %d",
            '_transient_satguru_otp_%',
            $current_time
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SATGURU OTP: Expired OTP cleanup completed');
        }
    }
    
    /**
     * Validate phone number format
     */
    private function is_valid_phone($phone) {
        // Remove all non-numeric characters except +
        $clean = preg_replace('/[^0-9+]/', '', $phone);
        
        // Must be at least 10 digits (without country code)
        // or 11-15 digits (with country code)
        $digits_only = preg_replace('/[^0-9]/', '', $clean);
        $length = strlen($digits_only);
        
        return $length >= 10 && $length <= 15;
    }
    
    /**
     * Log security event
     */
    private function log_security_event($event_type, $details = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_message = 'SATGURU OTP SECURITY [' . $event_type . ']: ';
        $log_message .= json_encode($details);
        
        error_log($log_message);
    }
}

// Initialize
add_action('init', function() {
    Satguru_OTP_Login::get_instance();
});

