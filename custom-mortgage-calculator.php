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
require_once plugin_dir_path(__FILE__) . 'includes/bcra-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/calculations.php';
require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/email-notifications.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/templates.php';
require_once plugin_dir_path(__FILE__) . 'includes/market-context.php';

// ============================================================================
// MAIN PLUGIN INITIALIZATION
// ============================================================================

add_action('init', 'mortgage_calculator_init');

function mortgage_calculator_init() {
    // Load text domain for translations
    load_plugin_textdomain('custom-mortgage-calculator', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    add_shortcode('mortgage_calculator', 'render_mortgage_calculator');
    add_shortcode('market-context', 'render_market_context_shortcode');
    
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
            'formReset' => __('Form cleared successfully', 'custom-mortgage-calculator'),
            'realTimeRates' => __('Real-time rates from BCRA', 'custom-mortgage-calculator'),
            'cachedRates' => __('BCRA rates (cached)', 'custom-mortgage-calculator'),
            'standardRates' => __('Standard rates', 'custom-mortgage-calculator'),
            'updatedAgo' => __('Updated %s hours ago', 'custom-mortgage-calculator'),
            'usingCachedRates' => __('Using cached rates', 'custom-mortgage-calculator'),
            'usingDefaultRates' => __('Using default rates', 'custom-mortgage-calculator'),
            
            // Form validation messages
            'fieldRequired' => __('Este campo es obligatorio', 'custom-mortgage-calculator'),
            'validEmailRequired' => __('Por favor ingrese un email válido', 'custom-mortgage-calculator'),
            'validPhoneRequired' => __('Por favor ingrese un teléfono válido', 'custom-mortgage-calculator'),
            'validNumberRequired' => __('Por favor ingrese un número válido', 'custom-mortgage-calculator'),
            'valueMinimum' => __('El valor debe ser al menos %s', 'custom-mortgage-calculator'),
            'valueMaximum' => __('El valor no puede exceder %s', 'custom-mortgage-calculator'),
            'downPaymentExceedsHome' => __('El pago inicial no puede exceder el valor de la propiedad', 'custom-mortgage-calculator'),
            
            // Error messages
            'generalError' => __('Ocurrió un error. Por favor intente nuevamente.', 'custom-mortgage-calculator'),
            'networkError' => __('Error de conexión. Por favor verifique su conexión e intente nuevamente.', 'custom-mortgage-calculator'),
            'termsRequired' => __('Por favor acepte los Términos de Servicio para continuar.', 'custom-mortgage-calculator'),
            'submissionError' => __('Hubo un error al enviar su solicitud. Por favor intente nuevamente.', 'custom-mortgage-calculator')
        )
    ));
}