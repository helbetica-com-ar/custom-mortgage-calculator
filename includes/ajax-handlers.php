<?php
/**
 * AJAX handlers for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle AJAX requests for calculator steps
 */
function handle_mortgage_calc_ajax() {
    // Prevent any output before JSON response
    ob_start();
    
    try {
        // Check if nonce is set
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array(
                'message' => __('Missing security token', 'custom-mortgage-calculator'),
                'code' => 'missing_nonce'
            ), 400);
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'custom-mortgage-calculator'),
                'code' => 'invalid_nonce'
            ), 403);
            return;
        }
        
        // Validate required fields
        if (!isset($_POST['step']) || !isset($_POST['form_data'])) {
            wp_send_json_error(array(
                'message' => __('Missing required fields', 'custom-mortgage-calculator'),
                'code' => 'missing_fields'
            ), 400);
            return;
        }
        
        $step = intval($_POST['step']);
        $form_data_json = stripslashes($_POST['form_data']);
        $form_data = json_decode($form_data_json, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Invalid form data format', 'custom-mortgage-calculator'),
                'code' => 'invalid_json',
                'details' => json_last_error_msg()
            ), 400);
            return;
        }
        
        // Get user identifier (IP + User Agent hash for anonymous users)
        $user_id = get_current_user_id();
        if (!$user_id) {
            $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $user_id = 'anon_' . md5($remote_addr . $user_agent);
        }
        
        // Create transient key
        $transient_key = 'mortgage_calc_' . $user_id;
        
        // Get existing data from transient
        $existing_data = get_transient($transient_key);
        if (!$existing_data || !is_array($existing_data)) {
            $existing_data = array();
        }
        
        // Merge new data with existing
        $all_data = array_merge($existing_data, $form_data);
        
        // Store data in transient (expires in 1 hour)
        set_transient($transient_key, $all_data, HOUR_IN_SECONDS);
        
        // Perform calculations based on current step
        $calculations = perform_mortgage_calculations($all_data, $step);
        
        // Clear any output buffer
        ob_end_clean();
        
        wp_send_json_success(array(
            'calculations' => $calculations,
            'step' => $step,
            'message' => __('Step data saved successfully', 'custom-mortgage-calculator')
        ));
        
    } catch (Exception $e) {
        // Clear any output buffer
        ob_end_clean();
        // Log the error
        error_log('Mortgage Calculator AJAX Error: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => __('An error occurred processing your request', 'custom-mortgage-calculator'),
            'code' => 'processing_error',
            'details' => WP_DEBUG ? $e->getMessage() : null
        ), 500);
    }
}

/**
 * Debug handler to test AJAX functionality
 */
function handle_mortgage_calc_debug() {
    try {
        // Test basic functionality
        $debug_info = array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_active' => true,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_valid' => isset($_POST['nonce']) ? wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce') : false
        );
        
        // Test UVA data fetching
        try {
            $uva_data = get_current_uva_data();
            $debug_info['uva_data'] = $uva_data;
        } catch (Exception $e) {
            $debug_info['uva_error'] = $e->getMessage();
        }
        
        // Test basic calculation
        try {
            $test_data = array(
                'loan_amount' => 35000000,
                'loan_term' => 10,
                'home_value' => 43750000,
                'down_payment' => 8750000,
                'monthly_income' => 1030000
            );
            $calculations = perform_mortgage_calculations($test_data, 2);
            $debug_info['calculation_test'] = 'success';
            $debug_info['sample_calculation'] = $calculations;
        } catch (Exception $e) {
            $debug_info['calculation_error'] = $e->getMessage();
        }
        
        wp_send_json_success($debug_info);
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'Debug error: ' . $e->getMessage()
        ), 500);
    }
}

/**
 * Handle final mortgage application submission
 */
function handle_mortgage_final_submit() {
    // Prevent any output before JSON response
    ob_start();
    
    try {
        // Check if nonce is set
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(array(
                'message' => __('Missing security token', 'custom-mortgage-calculator'),
                'code' => 'missing_nonce'
            ), 400);
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'custom-mortgage-calculator'),
                'code' => 'invalid_nonce'
            ), 403);
            return;
        }
        
        // Validate required fields
        if (!isset($_POST['form_data'])) {
            wp_send_json_error(array(
                'message' => __('Missing form data', 'custom-mortgage-calculator'),
                'code' => 'missing_form_data'
            ), 400);
            return;
        }
        
        // Get user identifier
        $user_id = get_current_user_id();
        if (!$user_id) {
            $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $user_id = 'anon_' . md5($remote_addr . $user_agent);
        }
        
        // Create transient key
        $transient_key = 'mortgage_calc_' . $user_id;
        
        // Get existing data from transient
        $existing_data = get_transient($transient_key);
        if (!$existing_data || !is_array($existing_data)) {
            $existing_data = array();
        }
        
        // Get final form data
        $final_data_json = stripslashes($_POST['form_data']);
        $final_data = json_decode($final_data_json, true);
        
        // Check if JSON decode was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array(
                'message' => __('Invalid form data format', 'custom-mortgage-calculator'),
                'code' => 'invalid_json',
                'details' => json_last_error_msg()
            ), 400);
            return;
        }
        
        $all_data = array_merge($existing_data, $final_data);
        
        // Save to database
        $submission_id = save_mortgage_application($all_data);
        
        if (!$submission_id) {
            throw new Exception(__('Failed to save application', 'custom-mortgage-calculator'));
        }
        
        // Send notification emails
        send_application_notifications($all_data, $submission_id);
        
        // Clear transient data
        delete_transient($transient_key);
        
        // Clear any output buffer
        ob_end_clean();
        
        wp_send_json_success(array(
            'submission_id' => $submission_id,
            'message' => __('Application submitted successfully', 'custom-mortgage-calculator')
        ));
        
    } catch (Exception $e) {
        // Clear any output buffer
        ob_end_clean();
        // Log the error
        error_log('Mortgage Calculator Submit Error: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => __('An error occurred submitting your application', 'custom-mortgage-calculator'),
            'code' => 'submission_error',
            'details' => WP_DEBUG ? $e->getMessage() : null
        ), 500);
    }
}

/**
 * Debug handler to test AJAX functionality
 */
function handle_mortgage_calc_debug() {
    try {
        // Test basic functionality
        $debug_info = array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'plugin_active' => true,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_valid' => isset($_POST['nonce']) ? wp_verify_nonce($_POST['nonce'], 'mortgage_calc_nonce') : false
        );
        
        // Test UVA data fetching
        try {
            $uva_data = get_current_uva_data();
            $debug_info['uva_data'] = $uva_data;
        } catch (Exception $e) {
            $debug_info['uva_error'] = $e->getMessage();
        }
        
        // Test basic calculation
        try {
            $test_data = array(
                'loan_amount' => 35000000,
                'loan_term' => 10,
                'home_value' => 43750000,
                'down_payment' => 8750000,
                'monthly_income' => 1030000
            );
            $calculations = perform_mortgage_calculations($test_data, 2);
            $debug_info['calculation_test'] = 'success';
            $debug_info['sample_calculation'] = $calculations;
        } catch (Exception $e) {
            $debug_info['calculation_error'] = $e->getMessage();
        }
        
        wp_send_json_success($debug_info);
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => 'Debug error: ' . $e->getMessage()
        ), 500);
    }
}