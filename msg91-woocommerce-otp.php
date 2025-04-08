<?php
/*
Plugin Name: MSG91 OTP for WooCommerce
Description: Integrates MSG91 OTP verification with WooCommerce login/registration
Version: 1.0
Author: Your Name
Text Domain: msg91-woocommerce-otp
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MSG91_WooCommerce_OTP {
    
    private $auth_key;
    private $template_id;
    private $otp_expiry;
    
    public function __construct() {
        // Initialize plugin settings
        $this->auth_key = get_option('msg91_auth_key');
        $this->template_id = get_option('msg91_template_id');
        $this->otp_expiry = get_option('msg91_otp_expiry', 10);
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize hooks
        $this->initialize_hooks();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'msg91-woocommerce-otp',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    private function initialize_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // WooCommerce form hooks
        add_action('woocommerce_login_form', array($this, 'add_otp_fields'));
        add_action('woocommerce_register_form', array($this, 'add_otp_fields'));
        add_action('woocommerce_edit_account_form', array($this, 'add_phone_field_account'));
        
        // AJAX handlers
        add_action('wp_ajax_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_nopriv_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_verify_otp', array($this, 'verify_otp'));
        add_action('wp_ajax_nopriv_verify_otp', array($this, 'verify_otp'));
        
        // Validation hooks
        add_filter('woocommerce_process_login_errors', array($this, 'validate_otp_login'));
        add_filter('woocommerce_registration_errors', array($this, 'validate_otp_registration'));
        
        // User data hooks
        add_action('woocommerce_created_customer', array($this, 'save_phone_number'));
        add_action('woocommerce_save_account_details', array($this, 'update_phone_number'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('MSG91 OTP Settings', 'msg91-woocommerce-otp'),
            __('MSG91 OTP', 'msg91-woocommerce-otp'),
            'manage_options',
            'msg91-otp-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('msg91_otp_settings', 'msg91_auth_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('msg91_otp_settings', 'msg91_template_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('msg91_otp_settings', 'msg91_otp_expiry', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 10
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add a check for required settings
        $auth_key = get_option('msg91_auth_key');
        if (empty($auth_key)) {
            add_settings_error(
                'msg91_messages',
                'msg91_message',
                __('MSG91 Auth Key is required for the plugin to work.', 'msg91-woocommerce-otp'),
                'error'
            );
        }
        
        settings_errors('msg91_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('msg91_otp_settings');
                do_settings_sections('msg91_otp_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="msg91_auth_key">
                                <?php esc_html_e('Auth Key', 'msg91-woocommerce-otp'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="msg91_auth_key" 
                                   id="msg91_auth_key" 
                                   value="<?php echo esc_attr($this->auth_key); ?>" 
                                   class="regular-text" 
                                   required />
                            <p class="description">
                                <?php esc_html_e('Your MSG91 API authentication key', 'msg91-woocommerce-otp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="msg91_template_id">
                                <?php esc_html_e('Template ID', 'msg91-woocommerce-otp'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="msg91_template_id" 
                                   id="msg91_template_id" 
                                   value="<?php echo esc_attr($this->template_id); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Your MSG91 OTP template ID', 'msg91-woocommerce-otp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="msg91_otp_expiry">
                                <?php esc_html_e('OTP Expiry (minutes)', 'msg91-woocommerce-otp'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="msg91_otp_expiry" 
                                   id="msg91_otp_expiry" 
                                   value="<?php echo esc_attr($this->otp_expiry); ?>" 
                                   class="small-text" 
                                   min="1" 
                                   max="60" />
                            <p class="description">
                                <?php esc_html_e('OTP validity period in minutes', 'msg91-woocommerce-otp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
  
  
  
  
  
    public function enqueue_scripts() {
    if (is_account_page()) {
        // Enqueue CSS
        wp_enqueue_style(
            'msg91-otp-style',
            plugins_url('assets/msg91-otp.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/msg91-otp.css')
        );
        
        // Enqueue JS without jQuery dependency
        wp_enqueue_script(
            'msg91-otp-script',
            plugins_url('assets/msg91-otp.js', __FILE__),
            array(), // No dependencies
            filemtime(plugin_dir_path(__FILE__) . 'assets/msg91-otp.js'),
            true // Load in footer
        );
        
        // Localize the script with required data
        wp_localize_script(
            'msg91-otp-script', 
            'msg91_otp_vars', 
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('msg91_otp_nonce'),
                'sending_text' => __('Sending OTP...', 'msg91-woocommerce-otp'),
                'send_otp_text' => __('Send OTP', 'msg91-woocommerce-otp'),
                'verify_text' => __('Verifying...', 'msg91-woocommerce-otp'),
                'verify_otp_text' => __('Verify OTP', 'msg91-woocommerce-otp'),
                'otp_sent_text' => __('OTP sent successfully!', 'msg91-woocommerce-otp'),
                'otp_error_text' => __('Error sending OTP. Please try again.', 'msg91-woocommerce-otp'),
                'otp_verified_text' => __('OTP verified successfully!', 'msg91-woocommerce-otp'),
                'otp_invalid_text' => __('Invalid OTP. Please try again.', 'msg91-woocommerce-otp')
            )
        );
    }
}






    public function add_otp_fields() {
        $form_type = current_action() === 'woocommerce_login_form' ? 'login' : 'register';
        ?>
        
        <div class="msg91-otp-section" style="margin: 20px 0;">
            <p class="form-row form-row-wide">
                <label for="msg91_phone_number_<?php echo esc_attr($form_type); ?>">
                    <?php esc_html_e('Phone Number (10-digit)', 'msg91-woocommerce-otp'); ?> <span class="required">*</span>
                </label>
                <div class="phone-input-wrapper" style="display: flex; align-items: center;">
                    <span style="padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-right: none; border-radius: 4px 0 0 4px;">+91</span>
                    <input 
                        type="tel" 
                        class="input-text" 
                        name="msg91_phone_number" 
                        id="msg91_phone_number_<?php echo esc_attr($form_type); ?>" 
                        placeholder="9760666886" 
                        pattern="[0-9]{10}" 
                        maxlength="10" 
                        required 
                        style="border-radius: 0 4px 4px 0;"
                    />
                </div>
            </p>
            
            <div class="msg91-otp-wrapper" style="display: none;">
                <p class="form-row form-row-wide">
                    <label for="msg91_otp_code_<?php echo esc_attr($form_type); ?>">
                        <?php esc_html_e('OTP Code', 'msg91-woocommerce-otp'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" class="input-text" name="msg91_otp_code" id="msg91_otp_code_<?php echo esc_attr($form_type); ?>" maxlength="6" />
                </p>
            </div>
            
            <p class="form-row">
                <button type="button" id="msg91_send_otp_<?php echo esc_attr($form_type); ?>" class="button msg91-send-otp" style="width: 100%;">
                    <?php esc_html_e('Send OTP', 'msg91-woocommerce-otp'); ?>
                </button>
                
                <button type="button" id="msg91_verify_otp_<?php echo esc_attr($form_type); ?>" class="button msg91-verify-otp" style="width: 100%; display: none;">
                    <?php esc_html_e('Verify OTP', 'msg91-woocommerce-otp'); ?>
                </button>
            </p>
            
            <div id="msg91_otp_message_<?php echo esc_attr($form_type); ?>" class="woocommerce-message" style="display: none;"></div>
        </div>
        <?php
    }
    
    public function send_otp() {
        check_ajax_referer('msg91_otp_nonce', 'security');
        
        $phone_number = sanitize_text_field($_POST['phone_number']);
        
        // Remove +91 if present
        $phone_number = str_replace('+91', '', $phone_number);
        
        if (empty($phone_number) || !preg_match('/^[0-9]{10}$/', $phone_number)) {
            wp_send_json_error(array('message' => __('Please enter a valid 10-digit phone number.', 'msg91-woocommerce-otp')));
        }
        
        // Auto-prepend +91 for MSG91 API
        $phone_number = '+91' . $phone_number;
        
        $auth_key = $this->auth_key;
        $template_id = $this->template_id;
        $otp_expiry = get_option('msg91_otp_expiry', 10);
        
        $url = "https://control.msg91.com/api/v5/otp?otp_expiry=$otp_expiry&template_id=$template_id&mobile=$phone_number&authkey=$auth_key&realTimeResponse=1";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => json_encode(array(
                'Param1' => 'value1',
                'Param2' => 'value2',
                'Param3' => 'value3'
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['type']) && $body['type'] == 'success') {
            wp_send_json_success(array('message' => __('OTP sent successfully!', 'msg91-woocommerce-otp')));
        } else {
            $message = isset($body['message']) ? $body['message'] : __('Error sending OTP. Please try again.', 'msg91-woocommerce-otp');
            wp_send_json_error(array('message' => $message));
        }
    }

    public function verify_otp() {
        check_ajax_referer('msg91_otp_nonce', 'security');
        
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $otp_code = sanitize_text_field($_POST['otp_code']);
        
        if (empty($phone_number) || empty($otp_code)) {
            wp_send_json_error(array('message' => __('Phone number and OTP code are required.', 'msg91-woocommerce-otp')));
        }
        
        $this->init_wc_session();
        
        $auth_key = $this->auth_key;
        $url = "https://control.msg91.com/api/v5/otp/verify?otp=$otp_code&mobile=$phone_number";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'authkey' => $auth_key
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['type']) && $body['type'] == 'success') {
            // Store verification in session
            WC()->session->set('msg91_otp_verified', true);
            WC()->session->set('msg91_phone_number', $phone_number);
            
            wp_send_json_success(array('message' => __('OTP verified successfully!', 'msg91-woocommerce-otp')));
        } else {
            $message = isset($body['message']) ? $body['message'] : __('Invalid OTP. Please try again.', 'msg91-woocommerce-otp');
            wp_send_json_error(array('message' => $message));
        }
    }
  
    private function init_wc_session() {
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
    }

    public function validate_otp_login($validation_errors) {
        $this->init_wc_session();
        
        if (empty($_POST['msg91_phone_number'])) {
            $validation_errors->add('otp_verification_error', __('Phone number is required.', 'msg91-woocommerce-otp'));
            return $validation_errors;
        }
        
        if (!WC()->session->get('msg91_otp_verified') || 
            WC()->session->get('msg91_phone_number') !== sanitize_text_field($_POST['msg91_phone_number'])) {
            $validation_errors->add('otp_verification_error', __('Please verify your phone number with OTP first.', 'msg91-woocommerce-otp'));
        }
        
        return $validation_errors;
    }

    public function validate_otp_registration($errors) {
        $this->init_wc_session();
        
        if (empty($_POST['msg91_phone_number'])) {
            $errors->add('otp_verification_error', __('Phone number is required.', 'msg91-woocommerce-otp'));
            return $errors;
        }
        
        if (!WC()->session->get('msg91_otp_verified') || 
            WC()->session->get('msg91_phone_number') !== sanitize_text_field($_POST['msg91_phone_number'])) {
            $errors->add('otp_verification_error', __('Please verify your phone number with OTP first.', 'msg91-woocommerce-otp'));
        }
        
        // Check if phone number already exists
        $phone_exists = $this->check_phone_exists(sanitize_text_field($_POST['msg91_phone_number']));
        if ($phone_exists) {
            $errors->add('otp_verification_error', __('An account is already registered with this phone number. Please log in.', 'msg91-woocommerce-otp'));
        }
        
        return $errors;
    }

    private function check_phone_exists($phone_number) {
        $users = get_users(array(
            'meta_key' => 'billing_phone',
            'meta_value' => $phone_number,
            'number' => 1,
            'fields' => 'ids'
        ));
        
        return !empty($users);
    }

    public function save_phone_number($customer_id) {
        $this->init_wc_session();
        
        if (!empty($_POST['msg91_phone_number'])) {
            $phone_number = sanitize_text_field($_POST['msg91_phone_number']);
            update_user_meta($customer_id, 'billing_phone', $phone_number);
        }
        
        // Clear session data
        WC()->session->set('msg91_otp_verified', false);
        WC()->session->set('msg91_phone_number', '');
    }

    public function update_phone_number($user_id) {
        if (!empty($_POST['billing_phone'])) {
            update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
        }
    }

    public function add_phone_field_account() {
        $user = wp_get_current_user();
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="billing_phone"><?php esc_html_e('Phone number', 'msg91-woocommerce-otp'); ?> <span class="required">*</span></label>
            <input type="tel" class="woocommerce-Input woocommerce-Input--phone input-text" name="billing_phone" id="billing_phone" value="<?php echo esc_attr($phone); ?>" />
        </p>
        <?php
    }

    public static function activate() {
        // Add default options if they don't exist
        if (!get_option('msg91_otp_expiry')) {
            update_option('msg91_otp_expiry', 10);
        }
    }

    public static function deactivate() {
        // Clean up session data
        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset('msg91_otp_verified');
            WC()->session->__unset('msg91_phone_number');
        }
    }
}

// Initialize the plugin
new MSG91_WooCommerce_OTP();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('MSG91_WooCommerce_OTP', 'activate'));
register_deactivation_hook(__FILE__, array('MSG91_WooCommerce_OTP', 'deactivate'));