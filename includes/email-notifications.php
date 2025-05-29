<?php
/**
 * Email notification functions for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send application notifications to applicant and admin
 * 
 * @param array $data Application data
 * @param int $submission_id Database submission ID
 */
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

/**
 * Generate confirmation email for applicant
 * 
 * @param array $data Application data
 * @param int $submission_id Database submission ID
 * @return string HTML email content
 */
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

/**
 * Generate admin notification email
 * 
 * @param array $data Application data
 * @param int $submission_id Database submission ID
 * @return string HTML email content
 */
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