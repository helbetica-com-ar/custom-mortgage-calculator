<?php
/**
 * Database functions for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save mortgage application to database
 * 
 * @param array $data Application data from form
 * @return int|false Insert ID on success, false on failure
 */
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

/**
 * Create mortgage applications table if it doesn't exist
 */
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

/**
 * Cleanup old transients periodically
 */
function mortgage_calculator_cleanup_transients() {
    global $wpdb;
    
    // Delete transients older than 1 day
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_mortgage_calc_%' 
        OR option_name LIKE '_transient_timeout_mortgage_calc_%'"
    );
}