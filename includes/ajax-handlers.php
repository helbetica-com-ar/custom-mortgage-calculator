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

/**
 * Handle final mortgage application submission
 */
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