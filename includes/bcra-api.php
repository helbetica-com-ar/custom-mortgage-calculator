<?php
/**
 * BCRA API Integration for Dynamic Mortgage Rates
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current mortgage rates from BCRA or cache
 * 
 * @return array Mortgage rates data
 */
function get_current_mortgage_rates() {
    // Check for cached data first (cache for 24 hours)
    $cached_data = get_transient('bcra_mortgage_rates');
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    // Get API token from settings
    $api_token = get_option('bcra_api_token', '');
    
    if (empty($api_token)) {
        // Return default rates if no API token
        return get_default_mortgage_rates();
    }
    
    // Try to fetch from EstadisticasBCRA API
    $rates_data = fetch_bcra_rates($api_token);
    
    if ($rates_data) {
        // Cache for 24 hours
        set_transient('bcra_mortgage_rates', $rates_data, DAY_IN_SECONDS);
        
        // Also save as permanent backup
        update_option('bcra_last_known_rates', $rates_data);
        
        return $rates_data;
    }
    
    // If API fails, try to get last known rates
    $last_known = get_option('bcra_last_known_rates');
    if ($last_known) {
        $last_known['source'] = 'cache';
        return $last_known;
    }
    
    // Ultimate fallback to defaults
    return get_default_mortgage_rates();
}

/**
 * Fetch rates from EstadisticasBCRA API
 * 
 * @param string $api_token API authorization token
 * @return array|false Rates data or false on failure
 */
function fetch_bcra_rates($api_token) {
    try {
        // EstadisticasBCRA endpoints
        $base_url = 'https://api.estadisticasbcra.com/';
        
        // Headers with authorization
        $headers = array(
            'Authorization' => 'Bearer ' . $api_token,
            'Accept' => 'application/json'
        );
        
        // Fetch mortgage rates (using personal loans as proxy for now)
        $response = wp_remote_get($base_url . 'tasa_prestamos_personales', array(
            'headers' => $headers,
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('BCRA API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !is_array($data)) {
            error_log('BCRA API: Invalid response format');
            return false;
        }
        
        // Get the latest rate
        $latest_rate = end($data);
        
        if (!isset($latest_rate['v'])) {
            error_log('BCRA API: No rate value found');
            return false;
        }
        
        // For UVA mortgages, the TNA is typically lower than personal loans
        // This is a simplified calculation - in reality, banks have different rates
        $personal_loan_rate = floatval($latest_rate['v']);
        $mortgage_tna = $personal_loan_rate * 0.4; // Approximate ratio
        
        // Calculate derived rates
        $tea_rate = pow(1 + ($mortgage_tna / 100) / 12, 12) - 1;
        $tea_rate = $tea_rate * 100;
        $cftea_rate = $tea_rate + 1.5; // Adding estimated fees
        
        return array(
            'tna_rate' => round($mortgage_tna, 2),
            'tea_rate' => round($tea_rate, 2),
            'cftea_rate' => round($cftea_rate, 2),
            'personal_loan_rate' => round($personal_loan_rate, 2),
            'fetched_at' => current_time('timestamp'),
            'source' => 'api',
            'date' => isset($latest_rate['d']) ? $latest_rate['d'] : date('Y-m-d')
        );
        
    } catch (Exception $e) {
        error_log('BCRA API Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get default mortgage rates
 * 
 * @return array Default rates
 */
function get_default_mortgage_rates() {
    return array(
        'tna_rate' => 9.5,
        'tea_rate' => 9.92,
        'cftea_rate' => 11.42,
        'source' => 'default',
        'fetched_at' => current_time('timestamp')
    );
}

/**
 * Clear cached rates (useful for testing or manual refresh)
 */
function clear_mortgage_rates_cache() {
    delete_transient('bcra_mortgage_rates');
}

/**
 * Add admin menu for API settings
 */
add_action('admin_menu', 'bcra_api_admin_menu');

function bcra_api_admin_menu() {
    add_submenu_page(
        'options-general.php',
        __('BCRA API Settings', 'custom-mortgage-calculator'),
        __('BCRA API', 'custom-mortgage-calculator'),
        'manage_options',
        'bcra-api-settings',
        'bcra_api_settings_page'
    );
}

/**
 * Admin settings page
 */
function bcra_api_settings_page() {
    // Save settings
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['bcra_api_nonce'], 'bcra_api_settings')) {
        update_option('bcra_api_token', sanitize_text_field($_POST['bcra_api_token']));
        
        // Clear cache when settings are updated
        clear_mortgage_rates_cache();
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved and cache cleared.', 'custom-mortgage-calculator') . '</p></div>';
    }
    
    $api_token = get_option('bcra_api_token', '');
    $current_rates = get_current_mortgage_rates();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('BCRA API Settings', 'custom-mortgage-calculator'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('bcra_api_settings', 'bcra_api_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="bcra_api_token"><?php echo esc_html__('API Token', 'custom-mortgage-calculator'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="bcra_api_token" name="bcra_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__('Get your API token from', 'custom-mortgage-calculator'); ?> 
                            <a href="https://estadisticasbcra.com/api/registracion" target="_blank">EstadisticasBCRA.com</a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h2><?php echo esc_html__('Current Rates', 'custom-mortgage-calculator'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Rate Type', 'custom-mortgage-calculator'); ?></th>
                        <th><?php echo esc_html__('Value', 'custom-mortgage-calculator'); ?></th>
                        <th><?php echo esc_html__('Source', 'custom-mortgage-calculator'); ?></th>
                        <th><?php echo esc_html__('Last Updated', 'custom-mortgage-calculator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>T.N.A.</td>
                        <td><?php echo esc_html($current_rates['tna_rate']); ?>%</td>
                        <td><?php echo esc_html(ucfirst($current_rates['source'])); ?></td>
                        <td rowspan="3">
                            <?php 
                            if (isset($current_rates['fetched_at'])) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $current_rates['fetched_at']));
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>T.E.A.</td>
                        <td><?php echo esc_html($current_rates['tea_rate']); ?>%</td>
                        <td><?php echo esc_html(ucfirst($current_rates['source'])); ?></td>
                    </tr>
                    <tr>
                        <td>C.F.T.E.A.</td>
                        <td><?php echo esc_html($current_rates['cftea_rate']); ?>%</td>
                        <td><?php echo esc_html(ucfirst($current_rates['source'])); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2><?php echo esc_html__('Manual Actions', 'custom-mortgage-calculator'); ?></h2>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=bcra-api-settings&action=clear_cache'), 'clear_cache'); ?>" 
               class="button button-secondary">
                <?php echo esc_html__('Clear Rate Cache', 'custom-mortgage-calculator'); ?>
            </a>
        </p>
    </div>
    <?php
}

// Handle cache clearing
add_action('admin_init', 'handle_bcra_cache_clear');

function handle_bcra_cache_clear() {
    if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'clear_cache')) {
            clear_mortgage_rates_cache();
            wp_redirect(admin_url('options-general.php?page=bcra-api-settings&cache_cleared=1'));
            exit;
        }
    }
    
    // Show notice if cache was cleared
    if (isset($_GET['cache_cleared'])) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Rate cache cleared successfully.', 'custom-mortgage-calculator') . '</p></div>';
        });
    }
}