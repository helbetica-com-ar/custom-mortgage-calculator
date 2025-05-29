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
// INCLUDE MODULAR FILES
// ============================================================================

// Include all modular components
require_once plugin_dir_path(__FILE__) . 'includes/uva-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/calculations.php';
require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/email-notifications.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/templates.php';

// ============================================================================
// MAIN PLUGIN INITIALIZATION
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
    
    // Add debug action for testing
    add_action('wp_ajax_mortgage_calc_debug', 'handle_mortgage_calc_debug');
    add_action('wp_ajax_nopriv_mortgage_calc_debug', 'handle_mortgage_calc_debug');
    
    // Clean up old transients periodically
    add_action('wp_scheduled_delete', 'mortgage_calculator_cleanup_transients');
}

// Schedule cleanup if not already scheduled
if (!wp_next_scheduled('mortgage_calculator_cleanup')) {
    wp_schedule_event(time(), 'daily', 'mortgage_calculator_cleanup');
}

// ============================================================================
// SCRIPTS AND STYLES
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
        'nonce' => wp_create_nonce('mortgage_calc_nonce'),
        'i18n' => array(
            'confirmReset' => __('Are you sure you want to clear all form data?', 'custom-mortgage-calculator'),
            'formReset' => __('Form cleared successfully', 'custom-mortgage-calculator')
        )
    ));
}