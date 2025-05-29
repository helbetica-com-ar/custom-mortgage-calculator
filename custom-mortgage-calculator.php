<?php
/**
 * Plugin Name: Custom Mortgage Calculator
 * Description: Multi-step mortgage loan simulation form
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: custom-mortgage-calculator
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// 1. MAIN SHORTCODE REGISTRATION
// ============================================================================

add_action('init', 'mortgage_calculator_init');

function mortgage_calculator_init() {
    // Load text domain for translations
    load_plugin_textdomain('custom-mortgage-calculator', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    add_shortcode('mortgage_calculator', 'render_mortgage_calculator');
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'mortgage_calculator_scripts');
    
    // Register AJAX actions
    add_action('wp_ajax_mortgage_calc_step', 'handle_mortgage_calc_ajax');
    add_action('wp_ajax_nopriv_mortgage_calc_step', 'handle_mortgage_calc_ajax');
    add_action('wp_ajax_mortgage_calc_submit', 'handle_mortgage_final_submit');
    add_action('wp_ajax_nopriv_mortgage_calc_submit', 'handle_mortgage_final_submit');
    
    // Clean up old transients periodically
    add_action('wp_scheduled_delete', 'mortgage_calculator_cleanup_transients');
}

// Schedule cleanup if not already scheduled
if (!wp_next_scheduled('mortgage_calculator_cleanup')) {
    wp_schedule_event(time(), 'daily', 'mortgage_calculator_cleanup');
}

// Cleanup function for old transients
function mortgage_calculator_cleanup_transients() {
    global $wpdb;
    
    // Delete transients older than 1 day
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_mortgage_calc_%' 
        OR option_name LIKE '_transient_timeout_mortgage_calc_%'"
    );
}

// ============================================================================
// 2. SCRIPTS AND STYLES
// ============================================================================

function mortgage_calculator_scripts() {
    // Get plugin directory URL
    $plugin_url = plugin_dir_url(__FILE__);
    
    $css_file = plugin_dir_path(__FILE__) . 'css/mortgage-calculator.css';
    $js_file = plugin_dir_path(__FILE__) . 'js/mortgage-calculator.js';
    
    wp_enqueue_script('mortgage-calculator-js', $plugin_url . 'js/mortgage-calculator.js', array('jquery'), file_exists($js_file) ? filemtime($js_file) : null, true);
    wp_enqueue_style('mortgage-calculator-css', $plugin_url . 'css/mortgage-calculator.css', array(), file_exists($css_file) ? filemtime($css_file) : null);
    
    // Pass AJAX URL and nonce to JavaScript
    wp_localize_script('mortgage-calculator-js', 'mortgageAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mortgage_calc_nonce')
    ));
}

// ============================================================================
// 3. MAIN SHORTCODE FUNCTION
// ============================================================================

function render_mortgage_calculator($atts) {
    $atts = shortcode_atts(array(
        'theme' => 'default',
        'width' => '100%'
    ), $atts);
    
    // Generate unique form ID (will be used for transient key)
    $form_id = 'mortgage_calc_' . uniqid();
    
    ob_start();
    ?>
    
    <div id="<?php echo $form_id; ?>" class="mortgage-calculator-wrapper" style="width: <?php echo $atts['width']; ?>;">
        
        <!-- Progress Indicator -->
        <div class="progress-wrapper">
            <div class="progress-bar">
                <div class="progress-step active" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-label"><?php echo esc_html(__('Loan Details', 'custom-mortgage-calculator')); ?></span>
                </div>
                <div class="progress-step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label"><?php echo esc_html(__('Property Info', 'custom-mortgage-calculator')); ?></span>
                </div>
                <div class="progress-step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label"><?php echo esc_html(__('Personal Details', 'custom-mortgage-calculator')); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Step 1: Initial Loan Information -->
        <div class="calculator-step active" id="step-1">
            <div class="step-container">
                <div class="left-panel">
                    <div class="call-to-action">
                        <h2><?php echo esc_html(__('Get Your UVA Mortgage Estimate', 'custom-mortgage-calculator')); ?></h2>
                        <p class="subtitle"><?php echo esc_html(__('Simulate your UVA mortgage with current market values', 'custom-mortgage-calculator')); ?></p>
                        <div class="benefits-list">
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span><?php echo esc_html(__('No commitment required', 'custom-mortgage-calculator')); ?></span>
                            </div>
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span><?php echo esc_html(__('Instant calculations', 'custom-mortgage-calculator')); ?></span>
                            </div>
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span><?php echo esc_html(__('Multiple lender options', 'custom-mortgage-calculator')); ?></span>
                            </div>
                        </div>
                        <div class="trust-indicators">
                            <span class="trust-badge">🔒 <?php echo esc_html(__('Secure & Confidential', 'custom-mortgage-calculator')); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <form class="step-form" data-step="1">
                        <div class="form-section">
                            <h3><?php echo esc_html(__('Tell Us About Your Loan', 'custom-mortgage-calculator')); ?></h3>
                            <p><?php echo esc_html(__('Provide basic information to get started with your mortgage estimate.', 'custom-mortgage-calculator')); ?></p>
                        </div>
                        <div class="form-group">
                            <label for="loan_amount"><?php echo esc_html(__('Loan Amount', 'custom-mortgage-calculator')); ?></label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="loan_amount" name="loan_amount" 
                                       class="form-control" placeholder="35,000,000" 
                                       min="30000000" step="100000" required>
                            </div>
                            <div class="input-help"><?php echo esc_html(__('Amount you wish to borrow', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="loan_term"><?php echo esc_html(__('Loan Term', 'custom-mortgage-calculator')); ?></label>
                            <select id="loan_term" name="loan_term" class="form-control" required>
                                <option value=""><?php echo esc_html(__('Select term', 'custom-mortgage-calculator')); ?></option>
                                <option value="15"><?php echo esc_html(__('15 years', 'custom-mortgage-calculator')); ?></option>
                                <option value="20"><?php echo esc_html(__('20 years', 'custom-mortgage-calculator')); ?></option>
                                <option value="25"><?php echo esc_html(__('25 years', 'custom-mortgage-calculator')); ?></option>
                                <option value="30"><?php echo esc_html(__('30 years', 'custom-mortgage-calculator')); ?></option>
                            </select>
                            <div class="input-help"><?php echo esc_html(__('How long to repay the loan', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <button type="button" class="btn-next" onclick="nextStep(1)">
                            <?php echo esc_html(__('Get Initial Estimate', 'custom-mortgage-calculator')); ?> →
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Property Information & Initial Calculation -->
        <div class="calculator-step" id="step-2">
            <div class="step-container">
                <div class="left-panel">
                    <div class="calculation-display">
                        <h2><?php echo esc_html(__('Your Estimated UVA Monthly Payment', 'custom-mortgage-calculator')); ?></h2>
                        <div class="payment-amount">
                            <span class="currency">$</span>
                            <span id="monthly-payment">0</span>
                            <span class="period"><?php echo esc_html(__('/month', 'custom-mortgage-calculator')); ?></span>
                        </div>
                        
                        <div class="payment-breakdown">
                            <div class="breakdown-item">
                                <span class="label"><?php echo esc_html(__('Principal & Interest:', 'custom-mortgage-calculator')); ?></span>
                                <span class="value" id="principal-interest">$0</span>
                            </div>
                            <div class="breakdown-item">
                                <span class="label"><?php echo esc_html(__('Est. Property Tax:', 'custom-mortgage-calculator')); ?></span>
                                <span class="value" id="property-tax">$0</span>
                            </div>
                            <div class="breakdown-item">
                                <span class="label"><?php echo esc_html(__('Est. Insurance:', 'custom-mortgage-calculator')); ?></span>
                                <span class="value" id="insurance">$0</span>
                            </div>
                        </div>
                        
                        <div class="disclaimer">
                            <small><?php echo esc_html(__('*This is a preliminary estimate. More accurate calculations will be provided in the next step.', 'custom-mortgage-calculator')); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <form class="step-form" data-step="2">
                        <div class="form-section">
                            <h3><?php echo esc_html(__('Property & Financial Information', 'custom-mortgage-calculator')); ?></h3>
                            <p><?php echo esc_html(__('Help us refine your estimate with property details and income.', 'custom-mortgage-calculator')); ?></p>
                        </div>
                        <div class="form-group">
                            <label for="monthly_income"><?php echo esc_html(__('Monthly Income', 'custom-mortgage-calculator')); ?></label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="monthly_income" name="monthly_income" 
                                       class="form-control" placeholder="1,030,000" 
                                       min="1030000" step="10000" required>
                            </div>
                            <div class="input-help"><?php echo esc_html(__('Your gross monthly income', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="down_payment"><?php echo esc_html(__('Down Payment', 'custom-mortgage-calculator')); ?></label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="down_payment" name="down_payment" 
                                       class="form-control" placeholder="8,750,000" 
                                       min="0" step="10000" required>
                            </div>
                            <div class="input-help"><?php echo esc_html(__('Amount you\'ll pay upfront', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="home_value"><?php echo esc_html(__('Property Value', 'custom-mortgage-calculator')); ?></label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="home_value" name="home_value" 
                                       class="form-control" placeholder="43,750,000" 
                                       min="37500000" step="100000" required>
                            </div>
                            <div class="input-help"><?php echo esc_html(__('Estimated market value of the property', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="property_use"><?php echo esc_html(__('Property Use', 'custom-mortgage-calculator')); ?></label>
                            <select id="property_use" name="property_use" class="form-control" required>
                                <option value=""><?php echo esc_html(__('Select property use', 'custom-mortgage-calculator')); ?></option>
                                <option value="primary"><?php echo esc_html(__('Primary Residence', 'custom-mortgage-calculator')); ?></option>
                                <option value="second"><?php echo esc_html(__('Second Home', 'custom-mortgage-calculator')); ?></option>
                                <option value="investment"><?php echo esc_html(__('Investment', 'custom-mortgage-calculator')); ?></option>
                            </select>
                            <div class="input-help"><?php echo esc_html(__('How you plan to use the property', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="property_location"><?php echo esc_html(__('Property Location', 'custom-mortgage-calculator')); ?></label>
                            <input type="text" id="property_location" name="property_location" 
                                   class="form-control" placeholder="<?php echo esc_attr(__('City, State', 'custom-mortgage-calculator')); ?>" required>
                            <div class="input-help"><?php echo esc_html(__('Location affects tax rates and insurance', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">
                                ← <?php echo esc_html(__('Previous', 'custom-mortgage-calculator')); ?>
                            </button>
                            <button type="button" class="btn-next" onclick="nextStep(2)">
                                <?php echo esc_html(__('Get Detailed Estimate', 'custom-mortgage-calculator')); ?> →
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Step 3: Final Calculation & Personal Details -->
        <div class="calculator-step" id="step-3">
            <div class="step-container">
                <div class="left-panel">
                    <div class="final-calculation">
                        <h2><?php echo esc_html(__('Your Personalized Loan Estimate', 'custom-mortgage-calculator')); ?></h2>
                        
                        <div class="loan-summary">
                            <div class="summary-card">
                                <h3><?php echo esc_html(__('Monthly Payment', 'custom-mortgage-calculator')); ?></h3>
                                <div class="payment-final">
                                    <span class="currency">$</span>
                                    <span id="final-monthly-payment">0</span>
                                </div>
                            </div>
                            
                            <div class="detailed-breakdown">
                                <div class="breakdown-section">
                                    <h4><?php echo esc_html(__('Payment Breakdown', 'custom-mortgage-calculator')); ?></h4>
                                    <div class="breakdown-item">
                                        <span><?php echo esc_html(__('Principal & Interest:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="final-pi">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span><?php echo esc_html(__('Property Tax:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="final-tax">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span><?php echo esc_html(__('Home Insurance:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="final-insurance">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span><?php echo esc_html(__('PMI (if applicable):', 'custom-mortgage-calculator')); ?></span>
                                        <span id="final-pmi">$0</span>
                                    </div>
                                </div>
                                
                                <div class="loan-details">
                                    <h4><?php echo esc_html(__('Loan Details', 'custom-mortgage-calculator')); ?></h4>
                                    <div class="detail-row">
                                        <span><?php echo esc_html(__('Loan Amount:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="summary-loan-amount">$0</span>
                                    </div>
                                    <div class="detail-row">
                                        <span><?php echo esc_html(__('Interest Rate:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="estimated-rate">4.5%</span>
                                    </div>
                                    <div class="detail-row">
                                        <span><?php echo esc_html(__('Total Interest:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="total-interest">$0</span>
                                    </div>
                                    <div class="detail-row">
                                        <span><?php echo esc_html(__('Debt-to-Income Ratio:', 'custom-mortgage-calculator')); ?></span>
                                        <span id="debt-to-income">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <form class="step-form" id="final-form" data-step="3">
                        <div class="form-section">
                            <h3><?php echo esc_html(__('Get Connected with Lenders', 'custom-mortgage-calculator')); ?></h3>
                            <p><?php echo esc_html(__('Complete your profile to receive personalized offers from our partner lenders.', 'custom-mortgage-calculator')); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name"><?php echo esc_html(__('Full Name', 'custom-mortgage-calculator')); ?></label>
                            <input type="text" id="full_name" name="full_name" 
                                   class="form-control" placeholder="<?php echo esc_attr(__('John Doe', 'custom-mortgage-calculator')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email"><?php echo esc_html(__('Email Address', 'custom-mortgage-calculator')); ?></label>
                            <input type="email" id="email" name="email" 
                                   class="form-control" placeholder="<?php echo esc_attr(__('john@example.com', 'custom-mortgage-calculator')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone"><?php echo esc_html(__('Phone Number', 'custom-mortgage-calculator')); ?></label>
                            <input type="tel" id="phone" name="phone" 
                                   class="form-control" placeholder="<?php echo esc_attr(__('(555) 123-4567', 'custom-mortgage-calculator')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo esc_html(__('Preferred Contact Method', 'custom-mortgage-calculator')); ?></label>
                            <div class="contact-method-group">
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_phone" value="phone">
                                    <span class="checkmark"></span>
                                    <span><?php echo esc_html(__('Phone', 'custom-mortgage-calculator')); ?></span>
                                </label>
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_whatsapp" value="whatsapp">
                                    <span class="checkmark"></span>
                                    <span><?php echo esc_html(__('WhatsApp', 'custom-mortgage-calculator')); ?></span>
                                </label>
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_email" value="email">
                                    <span class="checkmark"></span>
                                    <span><?php echo esc_html(__('Email', 'custom-mortgage-calculator')); ?></span>
                                </label>
                            </div>
                            <div class="input-help"><?php echo esc_html(__('Select your preferred contact method(s)', 'custom-mortgage-calculator')); ?></div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms_accepted" required>
                                <span class="checkmark"></span>
                                <span class="checkbox-text"><?php echo sprintf(__('I agree to the %s and %s', 'custom-mortgage-calculator'), '<a href="#" target="_blank">' . esc_html(__('Terms of Service', 'custom-mortgage-calculator')) . '</a>', '<a href="#" target="_blank">' . esc_html(__('Privacy Policy', 'custom-mortgage-calculator')) . '</a>'); ?></span>
                            </label>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="marketing_consent">
                                <span class="checkmark"></span>
                                <span class="checkbox-text"><?php echo esc_html(__('I consent to receive marketing communications about loan products', 'custom-mortgage-calculator')); ?></span>
                            </label>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" onclick="prevStep(3)">
                                ← <?php echo esc_html(__('Previous', 'custom-mortgage-calculator')); ?>
                            </button>
                            <button type="submit" class="btn-submit" onclick="submitFinalForm(event)">
                                <?php echo esc_html(__('Get My Loan Offers', 'custom-mortgage-calculator')); ?> →
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Loading Overlay -->
        <div id="loading-overlay" class="loading-overlay" style="display: none;">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p><?php echo esc_html(__('Calculating your personalized estimate...', 'custom-mortgage-calculator')); ?></p>
            </div>
        </div>
        
        <!-- Success Message -->
        <div id="success-message" class="success-message" style="display: none;">
            <div class="success-content">
                <h2><?php echo esc_html(__('Thank You!', 'custom-mortgage-calculator')); ?></h2>
                <p><?php echo esc_html(__('Your loan application has been submitted successfully. Our partner lenders will contact you within 24 hours with personalized offers.', 'custom-mortgage-calculator')); ?></p>
                <div class="next-steps">
                    <h3><?php echo esc_html(__('What happens next?', 'custom-mortgage-calculator')); ?></h3>
                    <ul>
                        <li><?php echo esc_html(__('Review of your application by multiple lenders', 'custom-mortgage-calculator')); ?></li>
                        <li><?php echo esc_html(__('Personalized loan offers sent to your email', 'custom-mortgage-calculator')); ?></li>
                        <li><?php echo esc_html(__('Direct contact from qualified loan officers', 'custom-mortgage-calculator')); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

// ============================================================================
// 4. AJAX HANDLERS
// ============================================================================

function handle_mortgage_calc_ajax() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce')) {
        wp_die(__('Security check failed', 'custom-mortgage-calculator'));
    }
    
    $step = intval($_POST['step']);
    $form_data_json = stripslashes($_POST['form_data']);
    $form_data = json_decode($form_data_json, true);
    
    // Get user identifier (IP + User Agent hash for anonymous users)
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 'anon_' . md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    }
    
    // Create transient key
    $transient_key = 'mortgage_calc_' . $user_id;
    
    // Get existing data from transient
    $existing_data = get_transient($transient_key);
    if (!$existing_data) {
        $existing_data = array();
    }
    
    // Merge new data with existing
    $all_data = array_merge($existing_data, $form_data);
    
    // Store data in transient (expires in 1 hour)
    set_transient($transient_key, $all_data, HOUR_IN_SECONDS);
    
    // Perform calculations based on current step
    $calculations = perform_mortgage_calculations($all_data, $step);
    
    wp_send_json_success(array(
        'calculations' => $calculations,
        'step' => $step,
        'message' => __('Step data saved successfully', 'custom-mortgage-calculator')
    ));
}

function handle_mortgage_final_submit() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce')) {
        wp_die(__('Security check failed', 'custom-mortgage-calculator'));
    }
    
    // Get user identifier
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 'anon_' . md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    }
    
    // Create transient key
    $transient_key = 'mortgage_calc_' . $user_id;
    
    // Get existing data from transient
    $existing_data = get_transient($transient_key);
    if (!$existing_data) {
        $existing_data = array();
    }
    
    // Get final form data
    $final_data_json = stripslashes($_POST['form_data']);
    $final_data = json_decode($final_data_json, true);
    $all_data = array_merge($existing_data, $final_data);
    
    // Save to database
    $submission_id = save_mortgage_application($all_data);
    
    // Send notification emails
    send_application_notifications($all_data, $submission_id);
    
    // Clear transient data
    delete_transient($transient_key);
    
    wp_send_json_success(array(
        'submission_id' => $submission_id,
        'message' => __('Application submitted successfully', 'custom-mortgage-calculator')
    ));
}

// ============================================================================
// 5. UVA FUNCTIONS
// ============================================================================

function get_current_uva_value() {
    // Check for cached value first (cache for 1 hour)
    $cached_uva = get_transient('current_uva_value');
    if ($cached_uva !== false) {
        return $cached_uva;
    }
    
    // Fetch from API
    $response = wp_remote_get('https://criptoya.com/api/uva');
    
    if (is_wp_error($response)) {
        // Fallback value if API fails
        return 1484.82; 
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['value'])) {
        $uva_value = floatval($data['value']);
        // Cache for 1 hour
        set_transient('current_uva_value', $uva_value, HOUR_IN_SECONDS);
        return $uva_value;
    }
    
    return 1484.82; // Fallback value
}

function pesos_to_uva($pesos) {
    $uva_value = get_current_uva_value();
    return $pesos / $uva_value;
}

function uva_to_pesos($uvas) {
    $uva_value = get_current_uva_value();
    return $uvas * $uva_value;
}

// ============================================================================
// 6. CALCULATION FUNCTIONS
// ============================================================================

function perform_mortgage_calculations($data, $step) {
    // Use UVA calculations for this branch
    return perform_uva_mortgage_calculations($data, $step);
}

function perform_uva_mortgage_calculations($data, $step) {
    $loan_amount = floatval($data['loan_amount'] ?? 0);
    $loan_term = intval($data['loan_term'] ?? 30);
    $home_value = floatval($data['home_value'] ?? 0);
    $down_payment = floatval($data['down_payment'] ?? 0);
    $monthly_income = floatval($data['monthly_income'] ?? 0);
    
    // Get current UVA value
    $current_uva_value = get_current_uva_value();
    
    // For Step 1 calculations (no home value yet), estimate home value from loan amount
    if ($step == 1 && $home_value == 0 && $loan_amount > 0) {
        // Assume 80% LTV for initial estimate (UVA max is 80% for primary residence)
        $home_value = $loan_amount / 0.8;
    }
    
    // UVA mortgage parameters (based on Santander example)
    $base_rate = 9.5; // Fixed rate for UVA mortgages
    
    // Calculate loan-to-value ratio
    $ltv = $home_value > 0 ? ($loan_amount / $home_value) * 100 : 0;
    
    // Validate LTV (max 80% for primary residence)
    if ($ltv > 80) {
        $ltv = 80;
        $loan_amount = $home_value * 0.8;
    }
    
    // Convert loan amount to UVAs
    $loan_amount_uvas = pesos_to_uva($loan_amount);
    
    // Monthly interest rate
    $monthly_rate = $base_rate / 100 / 12;
    $total_payments = $loan_term * 12;
    
    // Calculate monthly payment in UVAs (French amortization system)
    if ($monthly_rate > 0) {
        $monthly_payment_uvas = $loan_amount_uvas * ($monthly_rate * pow(1 + $monthly_rate, $total_payments)) / 
                               (pow(1 + $monthly_rate, $total_payments) - 1);
    } else {
        $monthly_payment_uvas = $loan_amount_uvas / $total_payments;
    }
    
    // Convert monthly payment to current pesos
    $monthly_payment_pesos = uva_to_pesos($monthly_payment_uvas);
    
    // Estimate property tax (1.2% annually) - stays in pesos
    $annual_property_tax = $home_value * 0.012;
    $monthly_property_tax = $annual_property_tax / 12;
    
    // Estimate home insurance (0.5% annually) - stays in pesos
    $annual_insurance = $home_value * 0.005;
    $monthly_insurance = $annual_insurance / 12;
    
    // No PMI for UVA mortgages (already limited to 80% LTV)
    $monthly_pmi = 0;
    
    // Total monthly payment in pesos
    $total_monthly_pesos = $monthly_payment_pesos + $monthly_property_tax + $monthly_insurance;
    
    // Total interest over life of loan (in UVAs)
    $total_interest_uvas = ($monthly_payment_uvas * $total_payments) - $loan_amount_uvas;
    $total_interest_pesos = uva_to_pesos($total_interest_uvas);
    
    // Calculate debt-to-income ratio (max 25% for UVA)
    $debt_to_income_ratio = 0;
    if ($monthly_income > 0) {
        $debt_to_income_ratio = ($total_monthly_pesos / $monthly_income) * 100;
    }
    
    return array(
        'monthly_payment' => round($total_monthly_pesos, 2),
        'principal_interest' => round($monthly_payment_pesos, 2),
        'property_tax' => round($monthly_property_tax, 2),
        'insurance' => round($monthly_insurance, 2),
        'pmi' => 0,
        'interest_rate' => $base_rate,
        'total_interest' => round($total_interest_pesos, 2),
        'loan_amount' => $loan_amount,
        'ltv_ratio' => round($ltv, 1),
        'debt_to_income_ratio' => round($debt_to_income_ratio, 1),
        'monthly_income' => $monthly_income,
        // UVA specific values
        'current_uva_value' => $current_uva_value,
        'loan_amount_uvas' => round($loan_amount_uvas, 2),
        'monthly_payment_uvas' => round($monthly_payment_uvas, 2),
        'uva_date' => date('d/m/Y'),
        'income_validation' => $debt_to_income_ratio <= 25 ? 'valid' : 'invalid'
    );
}

function perform_traditional_mortgage_calculations($data, $step) {
    $loan_amount = floatval($data['loan_amount'] ?? 0);
    $loan_term = intval($data['loan_term'] ?? 30);
    $home_value = floatval($data['home_value'] ?? 0);
    $down_payment = floatval($data['down_payment'] ?? 0);
    $monthly_income = floatval($data['monthly_income'] ?? 0);
    
    // For Step 1 calculations (no home value yet), estimate home value from loan amount
    if ($step == 1 && $home_value == 0 && $loan_amount > 0) {
        // Assume 80% LTV for initial estimate
        $home_value = $loan_amount / 0.8;
    }
    
    // Base interest rate estimation (you can make this dynamic)
    $base_rate = 4.5;
    
    // Calculate loan-to-value ratio
    $ltv = $home_value > 0 ? ($loan_amount / $home_value) * 100 : 0;
    
    // Adjust interest rate based on LTV
    $interest_rate = $base_rate;
    if ($ltv > 80) {
        $interest_rate += 0.25; // Higher rate for high LTV
    }
    if ($ltv > 90) {
        $interest_rate += 0.25; // Even higher for very high LTV
    }
    
    // Monthly interest rate
    $monthly_rate = $interest_rate / 100 / 12;
    $total_payments = $loan_term * 12;
    
    // Calculate monthly principal and interest
    if ($monthly_rate > 0) {
        $monthly_pi = $loan_amount * ($monthly_rate * pow(1 + $monthly_rate, $total_payments)) / 
                     (pow(1 + $monthly_rate, $total_payments) - 1);
    } else {
        $monthly_pi = $loan_amount / $total_payments;
    }
    
    // Estimate property tax (1.2% annually)
    $annual_property_tax = $home_value * 0.012;
    $monthly_property_tax = $annual_property_tax / 12;
    
    // Estimate home insurance (0.5% annually)
    $annual_insurance = $home_value * 0.005;
    $monthly_insurance = $annual_insurance / 12;
    
    // Calculate PMI if applicable (0.5% annually if LTV > 80%)
    $monthly_pmi = 0;
    if ($ltv > 80) {
        $annual_pmi = $loan_amount * 0.005;
        $monthly_pmi = $annual_pmi / 12;
    }
    
    // Total monthly payment
    $total_monthly = $monthly_pi + $monthly_property_tax + $monthly_insurance + $monthly_pmi;
    
    // Total interest over life of loan
    $total_interest = ($monthly_pi * $total_payments) - $loan_amount;
    
    // Calculate debt-to-income ratio
    $debt_to_income_ratio = 0;
    if ($monthly_income > 0) {
        $debt_to_income_ratio = ($total_monthly / $monthly_income) * 100;
    }
    
    return array(
        'monthly_payment' => round($total_monthly, 2),
        'principal_interest' => round($monthly_pi, 2),
        'property_tax' => round($monthly_property_tax, 2),
        'insurance' => round($monthly_insurance, 2),
        'pmi' => round($monthly_pmi, 2),
        'interest_rate' => round($interest_rate, 3),
        'total_interest' => round($total_interest, 2),
        'loan_amount' => $loan_amount,
        'ltv_ratio' => round($ltv, 1),
        'debt_to_income_ratio' => round($debt_to_income_ratio, 1),
        'monthly_income' => $monthly_income
    );
}

// ============================================================================
// 6. DATABASE FUNCTIONS
// ============================================================================

function save_mortgage_application($data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mortgage_applications';
    
    // Create table if it doesn't exist
    create_mortgage_applications_table();
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'loan_amount' => $data['loan_amount'],
            'loan_term' => $data['loan_term'],
            'home_value' => $data['home_value'],
            'down_payment' => $data['down_payment'],
            'property_location' => sanitize_text_field($data['property_location']),
            'full_name' => sanitize_text_field($data['full_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'monthly_income' => $data['monthly_income'],
            'terms_accepted' => isset($data['terms_accepted']) ? 1 : 0,
            'marketing_consent' => isset($data['marketing_consent']) ? 1 : 0,
            'submission_date' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ),
        array(
            '%f', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s'
        )
    );
    
    return $wpdb->insert_id;
}

function create_mortgage_applications_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mortgage_applications';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        loan_amount decimal(12,2) NOT NULL,
        loan_term int(3) NOT NULL,
        home_value decimal(12,2) NOT NULL,
        down_payment decimal(12,2) NOT NULL,
        property_location varchar(255) NOT NULL,
        full_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        monthly_income decimal(10,2) NOT NULL,
        terms_accepted tinyint(1) DEFAULT 0,
        marketing_consent tinyint(1) DEFAULT 0,
        submission_date datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45),
        user_agent text,
        status varchar(20) DEFAULT 'pending',
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// ============================================================================
// 7. EMAIL NOTIFICATIONS
// ============================================================================

function send_application_notifications($data, $submission_id) {
    // Send confirmation email to applicant
    $applicant_subject = __('Your Mortgage Application - Confirmation #', 'custom-mortgage-calculator') . $submission_id;
    $applicant_message = generate_applicant_email($data, $submission_id);
    
    wp_mail(
        $data['email'],
        $applicant_subject,
        $applicant_message,
        array('Content-Type: text/html; charset=UTF-8')
    );
    
    // Send notification to admin
    $admin_email = get_option('admin_email');
    $admin_subject = __('New Mortgage Application Received - #', 'custom-mortgage-calculator') . $submission_id;
    $admin_message = generate_admin_email($data, $submission_id);
    
    wp_mail(
        $admin_email,
        $admin_subject,
        $admin_message,
        array('Content-Type: text/html; charset=UTF-8')
    );
}

function generate_applicant_email($data, $submission_id) {
    ob_start();
    ?>
    <html>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #2c5aa0;"><?php echo esc_html(__('Thank You for Your Mortgage Application!', 'custom-mortgage-calculator')); ?></h2>
            
            <p><?php echo esc_html(__('Dear', 'custom-mortgage-calculator')); ?> <?php echo esc_html($data['full_name']); ?>,</p>
            
            <p><?php echo sprintf(__('We\'ve received your mortgage application (Confirmation #%s) and our partner lenders will review it shortly.', 'custom-mortgage-calculator'), $submission_id); ?></p>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <h3><?php echo esc_html(__('Application Summary:', 'custom-mortgage-calculator')); ?></h3>
                <ul style="list-style: none; padding: 0;">
                    <li><strong><?php echo esc_html(__('Loan Amount:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['loan_amount'], 2); ?></li>
                    <li><strong><?php echo esc_html(__('Property Value:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['home_value'], 2); ?></li>
                    <li><strong><?php echo esc_html(__('Down Payment:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['down_payment'], 2); ?></li>
                    <li><strong><?php echo esc_html(__('Property Location:', 'custom-mortgage-calculator')); ?></strong> <?php echo esc_html($data['property_location']); ?></li>
                    <li><strong><?php echo esc_html(__('Monthly Income:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['monthly_income'], 2); ?></li>
                </ul>
            </div>
            
            <h3><?php echo esc_html(__('What happens next?', 'custom-mortgage-calculator')); ?></h3>
            <ol>
                <li><?php echo esc_html(__('Our partner lenders will review your application within 24 hours', 'custom-mortgage-calculator')); ?></li>
                <li><?php echo esc_html(__('You\'ll receive personalized loan offers via email', 'custom-mortgage-calculator')); ?></li>
                <li><?php echo esc_html(__('A qualified loan officer will contact you to discuss your options', 'custom-mortgage-calculator')); ?></li>
            </ol>
            
            <p><?php echo esc_html(__('If you have any questions, please don\'t hesitate to contact us.', 'custom-mortgage-calculator')); ?></p>
            
            <p><?php echo esc_html(__('Best regards,', 'custom-mortgage-calculator')); ?><br><?php echo esc_html(__('The Mortgage Team', 'custom-mortgage-calculator')); ?></p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function generate_admin_email($data, $submission_id) {
    ob_start();
    ?>
    <html>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2><?php echo esc_html(__('New Mortgage Application Received', 'custom-mortgage-calculator')); ?></h2>
            
            <p><strong><?php echo esc_html(__('Submission ID:', 'custom-mortgage-calculator')); ?></strong> <?php echo $submission_id; ?></p>
            <p><strong><?php echo esc_html(__('Submission Date:', 'custom-mortgage-calculator')); ?></strong> <?php echo current_time('F j, Y g:i A'); ?></p>
            
            <h3><?php echo esc_html(__('Applicant Information:', 'custom-mortgage-calculator')); ?></h3>
            <ul>
                <li><strong><?php echo esc_html(__('Name:', 'custom-mortgage-calculator')); ?></strong> <?php echo esc_html($data['full_name']); ?></li>
                <li><strong><?php echo esc_html(__('Email:', 'custom-mortgage-calculator')); ?></strong> <?php echo esc_html($data['email']); ?></li>
                <li><strong><?php echo esc_html(__('Phone:', 'custom-mortgage-calculator')); ?></strong> <?php echo esc_html($data['phone']); ?></li>
                <li><strong><?php echo esc_html(__('Monthly Income:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['monthly_income'], 2); ?></li>
            </ul>
            
            <h3><?php echo esc_html(__('Loan Details:', 'custom-mortgage-calculator')); ?></h3>
            <ul>
                <li><strong><?php echo esc_html(__('Loan Amount:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['loan_amount'], 2); ?></li>
                <li><strong><?php echo esc_html(__('Loan Term:', 'custom-mortgage-calculator')); ?></strong> <?php echo $data['loan_term']; ?> <?php echo esc_html(__('years', 'custom-mortgage-calculator')); ?></li>
                <li><strong><?php echo esc_html(__('Property Value:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['home_value'], 2); ?></li>
                <li><strong><?php echo esc_html(__('Down Payment:', 'custom-mortgage-calculator')); ?></strong> $<?php echo number_format($data['down_payment'], 2); ?></li>
                <li><strong><?php echo esc_html(__('Property Location:', 'custom-mortgage-calculator')); ?></strong> <?php echo esc_html($data['property_location']); ?></li>
            </ul>
            
            <h3><?php echo esc_html(__('Consent Information:', 'custom-mortgage-calculator')); ?></h3>
            <ul>
                <li><strong><?php echo esc_html(__('Terms Accepted:', 'custom-mortgage-calculator')); ?></strong> <?php echo isset($data['terms_accepted']) ? esc_html(__('Yes', 'custom-mortgage-calculator')) : esc_html(__('No', 'custom-mortgage-calculator')); ?></li>
                <li><strong><?php echo esc_html(__('Marketing Consent:', 'custom-mortgage-calculator')); ?></strong> <?php echo isset($data['marketing_consent']) ? esc_html(__('Yes', 'custom-mortgage-calculator')) : esc_html(__('No', 'custom-mortgage-calculator')); ?></li>
            </ul>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>