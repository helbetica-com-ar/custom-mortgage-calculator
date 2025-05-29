<?php
/**
 * Test file for debugging AJAX issues
 * Access this file directly to test the AJAX endpoint
 * 
 * IMPORTANT: Delete this file after debugging!
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $debug_info = array(
        'wp_loaded' => defined('ABSPATH'),
        'plugin_functions' => array(
            'get_current_uva_data' => function_exists('get_current_uva_data'),
            'perform_mortgage_calculations' => function_exists('perform_mortgage_calculations'),
            'handle_mortgage_calc_ajax' => function_exists('handle_mortgage_calc_ajax')
        ),
        'ajax_actions' => array(
            'mortgage_calc_step' => has_action('wp_ajax_mortgage_calc_step'),
            'mortgage_calc_step_nopriv' => has_action('wp_ajax_nopriv_mortgage_calc_step')
        )
    );
    
    // Test UVA data
    if (function_exists('get_current_uva_data')) {
        try {
            $debug_info['uva_test'] = get_current_uva_data();
        } catch (Exception $e) {
            $debug_info['uva_error'] = $e->getMessage();
        }
    }
    
    // Test calculation
    if (function_exists('perform_mortgage_calculations')) {
        try {
            $test_data = array(
                'loan_amount' => 35000000,
                'loan_term' => 10,
                'home_value' => 43750000,
                'down_payment' => 8750000,
                'monthly_income' => 1030000
            );
            $debug_info['calculation_test'] = perform_mortgage_calculations($test_data, 2);
        } catch (Exception $e) {
            $debug_info['calculation_error'] = $e->getMessage();
        }
    }
    
    echo json_encode(array(
        'success' => true,
        'data' => $debug_info
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}