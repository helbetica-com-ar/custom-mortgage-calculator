<?php
/**
 * Template rendering functions for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main shortcode function - renders the complete calculator
 */
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
        
        <?php echo render_step_1(); ?>
        <?php echo render_step_2(); ?>
        <?php echo render_step_3(); ?>
        
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

/**
 * Render Step 1: Initial Loan Information
 */
function render_step_1() {
    ob_start();
    ?>
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
                    <div class="form-group form-group-row">
                        <label for="loan_amount">
                            <span><?php echo esc_html(__('Loan Amount', 'custom-mortgage-calculator')); ?></span>
                            <div class="input-help"><?php echo esc_html(__('Amount you wish to borrow', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <div class="input-wrapper">
                            <span class="currency-symbol">$</span>
                            <input type="number" id="loan_amount" name="loan_amount" 
                                   class="form-control" placeholder="35.000.000" 
                                   min="30000000" step="100000" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="loan_term"><?php echo esc_html(__('Loan Term', 'custom-mortgage-calculator')); ?></label>
                        <select id="loan_term" name="loan_term" class="form-control" required>
                            <option value=""><?php echo esc_html(__('Select term', 'custom-mortgage-calculator')); ?></option>
                            <option value="5"><?php echo esc_html(__('5 years', 'custom-mortgage-calculator')); ?></option>
                            <option value="10"><?php echo esc_html(__('10 years', 'custom-mortgage-calculator')); ?></option>
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
    <?php
    return ob_get_clean();
}

/**
 * Render Step 2: Property Information & Initial Calculation
 */
function render_step_2() {
    ob_start();
    ?>
    <!-- Step 2: Property Information & Initial Calculation -->
    <div class="calculator-step" id="step-2">
        <div class="step-container">
            <div class="left-panel">
                <div class="calculation-display">
                    <h2><?php echo esc_html(__('Value of Your First UVA Payment', 'custom-mortgage-calculator')); ?></h2>
                    <div class="payment-amount">
                        <span class="ars-label">ARS</span>
                        <span class="currency">$</span>
                        <span id="monthly-payment">0</span>
                        <span class="period"><?php echo esc_html(__('/month', 'custom-mortgage-calculator')); ?></span>
                    </div>
                    
                    <div class="uva-today-value">
                        <?php 
                            $uva_value = get_current_uva_value();
                            $update_time = get_uva_update_time();
                            $source = get_uva_source();
                            $time_diff = current_time('timestamp') - $update_time;
                            $hours_ago = round($time_diff / 3600, 1);
                        ?>
                        <small>
                            <?php echo esc_html(__('UVA value today:', 'custom-mortgage-calculator')); ?><br>
                            $<span id="current-uva-value-step2"><?php echo number_format($uva_value, 2, ',', '.'); ?></span> 
                            (<?php echo esc_html(__('Updated at', 'custom-mortgage-calculator')); ?> <?php echo date('d/m/Y H:i', $update_time); ?>)
                            <?php if ($source === 'cache' || $source === 'fallback'): ?>
                                <br><em><?php echo esc_html(__('Using cached value', 'custom-mortgage-calculator')); ?> - <?php echo sprintf(__('Updated %s hours ago', 'custom-mortgage-calculator'), $hours_ago); ?></em>
                            <?php endif; ?>
                        </small>
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
                    
                    <div class="rate-info">
                        <div class="rate-item">
                            <span class="label"><?php echo esc_html(__('Loan Term:', 'custom-mortgage-calculator')); ?></span>
                            <span class="value" id="selected-loan-term">-</span> <?php echo esc_html(__('years', 'custom-mortgage-calculator')); ?>
                        </div>
                        <div class="rate-item">
                            <span class="label">T.N.A.:</span>
                            <span class="value" id="tna-rate">9.50%</span>
                        </div>
                        <div class="rate-item">
                            <span class="label">T.E.A.:</span>
                            <span class="value" id="tea-rate">9.92%</span>
                        </div>
                        <div class="rate-item">
                            <span class="label">C.F.T.E.A.:</span>
                            <span class="value" id="cftea-rate">11.50%</span>
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
                    <div class="form-group form-group-row">
                        <label for="monthly_income">
                            <span><?php echo esc_html(__('Monthly Income', 'custom-mortgage-calculator')); ?></span>
                            <div class="input-help"><?php echo esc_html(__('Your gross monthly income', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <div class="input-wrapper">
                            <span class="currency-symbol">$</span>
                            <input type="number" id="monthly_income" name="monthly_income" 
                                   class="form-control" placeholder="1.030.000" 
                                   min="1030000" step="10000" required>
                        </div>
                    </div>
                    
                    <div class="form-group form-group-row">
                        <label for="down_payment">
                            <span><?php echo esc_html(__('Down Payment', 'custom-mortgage-calculator')); ?></span>
                            <div class="input-help"><?php echo esc_html(__('Amount you\'ll pay upfront', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <div class="input-wrapper">
                            <span class="currency-symbol">$</span>
                            <input type="number" id="down_payment" name="down_payment" 
                                   class="form-control" placeholder="8.750.000" 
                                   min="0" step="10000" required>
                        </div>
                    </div>
                    
                    <div class="form-group form-group-row">
                        <label for="home_value">
                            <span><?php echo esc_html(__('Property Value', 'custom-mortgage-calculator')); ?></span>
                            <div class="input-help"><?php echo esc_html(__('Estimated market value of the property', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <div class="input-wrapper">
                            <span class="currency-symbol">$</span>
                            <input type="number" id="home_value" name="home_value" 
                                   class="form-control" placeholder="43.750.000" 
                                   min="37500000" step="100000" required>
                        </div>
                    </div>
                    
                    <div class="form-group form-group-column">
                        <label for="property_use"><?php echo esc_html(__('Property Use', 'custom-mortgage-calculator')); ?>
                            <div class="input-help"><?php echo esc_html(__('How you plan to use the property', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <select id="property_use" name="property_use" class="form-control" required>
                            <option value=""><?php echo esc_html(__('Select property use', 'custom-mortgage-calculator')); ?></option>
                            <option value="primary"><?php echo esc_html(__('Primary Residence', 'custom-mortgage-calculator')); ?></option>
                            <option value="second"><?php echo esc_html(__('Second Home', 'custom-mortgage-calculator')); ?></option>
                            <option value="investment"><?php echo esc_html(__('Investment', 'custom-mortgage-calculator')); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group form-group-column">
                        <label for="property_location"><?php echo esc_html(__('Property Location', 'custom-mortgage-calculator')); ?>
                            <div class="input-help"><?php echo esc_html(__('Location affects tax rates and insurance', 'custom-mortgage-calculator')); ?></div>
                        </label>
                        <input type="text" id="property_location" name="property_location" 
                               class="form-control" placeholder="<?php echo esc_attr(__('City, State', 'custom-mortgage-calculator')); ?>" required>
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
    <?php
    return ob_get_clean();
}

/**
 * Render Step 3: Final Calculation & Personal Details
 */
function render_step_3() {
    ob_start();
    ?>
    <!-- Step 3: Final Calculation & Personal Details -->
    <div class="calculator-step" id="step-3">
        <div class="step-container">
            <div class="left-panel">
                <div class="final-calculation">
                    <h2><?php echo esc_html(__('Your Personalized Loan Estimate', 'custom-mortgage-calculator')); ?></h2>
                    
                    <div class="loan-summary">
                        <div class="summary-card">
                            <h3><?php echo esc_html(__('First Payment Value', 'custom-mortgage-calculator')); ?></h3>
                            <div class="payment-final">
                                <span class="currency">$</span>
                                <span id="final-monthly-payment">0</span>
                            </div>
                            <div class="uva-today-value">
                                <?php 
                                    $uva_value = get_current_uva_value();
                                    $update_time = get_uva_update_time();
                                    $source = get_uva_source();
                                    $time_diff = current_time('timestamp') - $update_time;
                                    $hours_ago = round($time_diff / 3600, 1);
                                ?>
                                <small>
                                    <?php echo esc_html(__('UVA value today:', 'custom-mortgage-calculator')); ?><br>
                                    $<span id="current-uva-value-step3"><?php echo number_format($uva_value, 2, ',', '.'); ?></span> 
                                    (<?php echo esc_html(__('Updated at', 'custom-mortgage-calculator')); ?> <?php echo date('d/m/Y H:i', $update_time); ?>)
                                    <?php if ($source === 'cache' || $source === 'fallback'): ?>
                                        <br><em><?php echo esc_html(__('Using cached value', 'custom-mortgage-calculator')); ?> - <?php echo sprintf(__('Updated %s hours ago', 'custom-mortgage-calculator'), $hours_ago); ?></em>
                                    <?php endif; ?>
                                </small>
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
                                    <span>T.N.A.:</span>
                                    <span id="final-tna-rate">9.50%</span>
                                </div>
                                <div class="detail-row">
                                    <span>T.E.A.:</span>
                                    <span id="final-tea-rate">9.92%</span>
                                </div>
                                <div class="detail-row">
                                    <span>C.F.T.E.A.:</span>
                                    <span id="final-cftea-rate">11.50%</span>
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
    <?php
    return ob_get_clean();
}