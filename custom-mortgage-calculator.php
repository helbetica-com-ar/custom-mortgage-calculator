<?php
/**
 * Plugin Name: Custom Mortgage Calculator
 * Description: Multi-step mortgage loan simulation form
 * Version: 1.0.0
 * Author: Your Name
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
                    <span class="step-label">Loan Details</span>
                </div>
                <div class="progress-step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label">Property Info</span>
                </div>
                <div class="progress-step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label">Personal Details</span>
                </div>
            </div>
        </div>
        
        <!-- Step 1: Initial Loan Information -->
        <div class="calculator-step active" id="step-1">
            <div class="step-container">
                <div class="left-panel">
                    <div class="call-to-action">
                        <h2>Get Your Mortgage Estimate</h2>
                        <p class="subtitle">Start your home buying journey with a personalized loan simulation</p>
                        <div class="benefits-list">
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span>No commitment required</span>
                            </div>
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span>Instant calculations</span>
                            </div>
                            <div class="benefit-item">
                                <span class="icon">✓</span>
                                <span>Multiple lender options</span>
                            </div>
                        </div>
                        <div class="trust-indicators">
                            <span class="trust-badge">🔒 Secure & Confidential</span>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <form class="step-form" data-step="1">
                        <div class="form-section">
                            <h3>Tell Us About Your Loan</h3>
                            <p>Provide basic information to get started with your mortgage estimate.</p>
                        </div>
                        <div class="form-group">
                            <label for="loan_amount">Loan Amount</label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="loan_amount" name="loan_amount" 
                                       class="form-control" placeholder="500,000" 
                                       min="50000" max="2000000" step="1000" required>
                            </div>
                            <div class="input-help">Amount you wish to borrow</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="loan_term">Loan Term</label>
                            <select id="loan_term" name="loan_term" class="form-control" required>
                                <option value="">Select term</option>
                                <option value="15">15 years</option>
                                <option value="20">20 years</option>
                                <option value="25">25 years</option>
                                <option value="30">30 years</option>
                            </select>
                            <div class="input-help">How long to repay the loan</div>
                        </div>
                        
                        <button type="button" class="btn-next" onclick="nextStep(1)">
                            Get Initial Estimate →
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
                        <h2>Your Estimated Monthly Payment</h2>
                        <div class="payment-amount">
                            <span class="currency">$</span>
                            <span id="monthly-payment">0</span>
                            <span class="period">/month</span>
                        </div>
                        
                        <div class="payment-breakdown">
                            <div class="breakdown-item">
                                <span class="label">Principal & Interest:</span>
                                <span class="value" id="principal-interest">$0</span>
                            </div>
                            <div class="breakdown-item">
                                <span class="label">Est. Property Tax:</span>
                                <span class="value" id="property-tax">$0</span>
                            </div>
                            <div class="breakdown-item">
                                <span class="label">Est. Insurance:</span>
                                <span class="value" id="insurance">$0</span>
                            </div>
                        </div>
                        
                        <div class="disclaimer">
                            <small>*This is a preliminary estimate. More accurate calculations will be provided in the next step.</small>
                        </div>
                    </div>
                </div>
                
                <div class="right-panel">
                    <form class="step-form" data-step="2">
                        <div class="form-section">
                            <h3>Property & Financial Information</h3>
                            <p>Help us refine your estimate with property details and income.</p>
                        </div>
                        <div class="form-group">
                            <label for="monthly_income">Monthly Income</label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="monthly_income" name="monthly_income" 
                                       class="form-control" placeholder="8,000" 
                                       min="1000" step="100" required>
                            </div>
                            <div class="input-help">Your gross monthly income</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="down_payment">Down Payment</label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="down_payment" name="down_payment" 
                                       class="form-control" placeholder="130,000" 
                                       min="0" step="1000" required>
                            </div>
                            <div class="input-help">Amount you'll pay upfront</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="home_value">Property Value</label>
                            <div class="input-wrapper">
                                <span class="currency-symbol">$</span>
                                <input type="number" id="home_value" name="home_value" 
                                       class="form-control" placeholder="650,000" 
                                       min="100000" max="5000000" step="1000" required>
                            </div>
                            <div class="input-help">Estimated market value of the property</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="property_use">Property Use</label>
                            <select id="property_use" name="property_use" class="form-control" required>
                                <option value="">Select property use</option>
                                <option value="primary">Primary Residence</option>
                                <option value="second">Second Home</option>
                                <option value="investment">Investment</option>
                            </select>
                            <div class="input-help">How you plan to use the property</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="property_location">Property Location</label>
                            <input type="text" id="property_location" name="property_location" 
                                   class="form-control" placeholder="City, State" required>
                            <div class="input-help">Location affects tax rates and insurance</div>
                        </div>
                        
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">
                                ← Previous
                            </button>
                            <button type="button" class="btn-next" onclick="nextStep(2)">
                                Get Detailed Estimate →
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
                        <h2>Your Personalized Loan Estimate</h2>
                        
                        <div class="loan-summary">
                            <div class="summary-card">
                                <h3>Monthly Payment</h3>
                                <div class="payment-final">
                                    <span class="currency">$</span>
                                    <span id="final-monthly-payment">0</span>
                                </div>
                            </div>
                            
                            <div class="detailed-breakdown">
                                <div class="breakdown-section">
                                    <h4>Payment Breakdown</h4>
                                    <div class="breakdown-item">
                                        <span>Principal & Interest:</span>
                                        <span id="final-pi">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Property Tax:</span>
                                        <span id="final-tax">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>Home Insurance:</span>
                                        <span id="final-insurance">$0</span>
                                    </div>
                                    <div class="breakdown-item">
                                        <span>PMI (if applicable):</span>
                                        <span id="final-pmi">$0</span>
                                    </div>
                                </div>
                                
                                <div class="loan-details">
                                    <h4>Loan Details</h4>
                                    <div class="detail-row">
                                        <span>Loan Amount:</span>
                                        <span id="summary-loan-amount">$0</span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Interest Rate:</span>
                                        <span id="estimated-rate">4.5%</span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Total Interest:</span>
                                        <span id="total-interest">$0</span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Debt-to-Income Ratio:</span>
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
                            <h3>Get Connected with Lenders</h3>
                            <p>Complete your profile to receive personalized offers from our partner lenders.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   class="form-control" placeholder="John Doe" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   class="form-control" placeholder="john@example.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   class="form-control" placeholder="(555) 123-4567" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Preferred Contact Method</label>
                            <div class="contact-method-group">
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_phone" value="phone">
                                    <span class="checkmark"></span>
                                    <span>Phone</span>
                                </label>
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_whatsapp" value="whatsapp">
                                    <span class="checkmark"></span>
                                    <span>WhatsApp</span>
                                </label>
                                <label class="checkbox-label inline-checkbox">
                                    <input type="checkbox" name="contact_email" value="email">
                                    <span class="checkmark"></span>
                                    <span>Email</span>
                                </label>
                            </div>
                            <div class="input-help">Select your preferred contact method(s)</div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms_accepted" required>
                                <span class="checkmark"></span>
                                <span class="checkbox-text">I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a></span>
                            </label>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="marketing_consent">
                                <span class="checkmark"></span>
                                <span class="checkbox-text">I consent to receive marketing communications about loan products</span>
                            </label>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn-prev" onclick="prevStep(3)">
                                ← Previous
                            </button>
                            <button type="submit" class="btn-submit" onclick="submitFinalForm(event)">
                                Get My Loan Offers →
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
                <p>Calculating your personalized estimate...</p>
            </div>
        </div>
        
        <!-- Success Message -->
        <div id="success-message" class="success-message" style="display: none;">
            <div class="success-content">
                <h2>Thank You!</h2>
                <p>Your loan application has been submitted successfully. Our partner lenders will contact you within 24 hours with personalized offers.</p>
                <div class="next-steps">
                    <h3>What happens next?</h3>
                    <ul>
                        <li>Review of your application by multiple lenders</li>
                        <li>Personalized loan offers sent to your email</li>
                        <li>Direct contact from qualified loan officers</li>
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
        wp_die('Security check failed');
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
        'message' => 'Step data saved successfully'
    ));
}

function handle_mortgage_final_submit() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce')) {
        wp_die('Security check failed');
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
        'message' => 'Application submitted successfully'
    ));
}

// ============================================================================
// 5. CALCULATION FUNCTIONS
// ============================================================================

function perform_mortgage_calculations($data, $step) {
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
    $applicant_subject = 'Your Mortgage Application - Confirmation #' . $submission_id;
    $applicant_message = generate_applicant_email($data, $submission_id);
    
    wp_mail(
        $data['email'],
        $applicant_subject,
        $applicant_message,
        array('Content-Type: text/html; charset=UTF-8')
    );
    
    // Send notification to admin
    $admin_email = get_option('admin_email');
    $admin_subject = 'New Mortgage Application Received - #' . $submission_id;
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
            <h2 style="color: #2c5aa0;">Thank You for Your Mortgage Application!</h2>
            
            <p>Dear <?php echo esc_html($data['full_name']); ?>,</p>
            
            <p>We've received your mortgage application (Confirmation #<?php echo $submission_id; ?>) and our partner lenders will review it shortly.</p>
            
            <div style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                <h3>Application Summary:</h3>
                <ul style="list-style: none; padding: 0;">
                    <li><strong>Loan Amount:</strong> $<?php echo number_format($data['loan_amount'], 2); ?></li>
                    <li><strong>Property Value:</strong> $<?php echo number_format($data['home_value'], 2); ?></li>
                    <li><strong>Down Payment:</strong> $<?php echo number_format($data['down_payment'], 2); ?></li>
                    <li><strong>Property Location:</strong> <?php echo esc_html($data['property_location']); ?></li>
                    <li><strong>Monthly Income:</strong> $<?php echo number_format($data['monthly_income'], 2); ?></li>
                </ul>
            </div>
            
            <h3>What happens next?</h3>
            <ol>
                <li>Our partner lenders will review your application within 24 hours</li>
                <li>You'll receive personalized loan offers via email</li>
                <li>A qualified loan officer will contact you to discuss your options</li>
            </ol>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
            
            <p>Best regards,<br>The Mortgage Team</p>
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
            <h2>New Mortgage Application Received</h2>
            
            <p><strong>Submission ID:</strong> <?php echo $submission_id; ?></p>
            <p><strong>Submission Date:</strong> <?php echo current_time('F j, Y g:i A'); ?></p>
            
            <h3>Applicant Information:</h3>
            <ul>
                <li><strong>Name:</strong> <?php echo esc_html($data['full_name']); ?></li>
                <li><strong>Email:</strong> <?php echo esc_html($data['email']); ?></li>
                <li><strong>Phone:</strong> <?php echo esc_html($data['phone']); ?></li>
                <li><strong>Monthly Income:</strong> $<?php echo number_format($data['monthly_income'], 2); ?></li>
            </ul>
            
            <h3>Loan Details:</h3>
            <ul>
                <li><strong>Loan Amount:</strong> $<?php echo number_format($data['loan_amount'], 2); ?></li>
                <li><strong>Loan Term:</strong> <?php echo $data['loan_term']; ?> years</li>
                <li><strong>Property Value:</strong> $<?php echo number_format($data['home_value'], 2); ?></li>
                <li><strong>Down Payment:</strong> $<?php echo number_format($data['down_payment'], 2); ?></li>
                <li><strong>Property Location:</strong> <?php echo esc_html($data['property_location']); ?></li>
            </ul>
            
            <h3>Consent Information:</h3>
            <ul>
                <li><strong>Terms Accepted:</strong> <?php echo isset($data['terms_accepted']) ? 'Yes' : 'No'; ?></li>
                <li><strong>Marketing Consent:</strong> <?php echo isset($data['marketing_consent']) ? 'Yes' : 'No'; ?></li>
            </ul>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>