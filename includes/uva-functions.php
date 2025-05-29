<?php
/**
 * UVA (Unidad de Valor Adquisitivo) functions for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current UVA data from API or cache
 * 
 * @return array UVA data with value, timestamp, source
 */
function get_current_uva_data() {
    // Check for cached data first (cache for 1 hour)
    $cached_data = get_transient('current_uva_data');
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    // Try to fetch from API
    $response = wp_remote_get('https://criptoya.com/api/uva', array(
        'timeout' => 5 // 5 second timeout
    ));
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['value']) && isset($data['time'])) {
            $uva_data = array(
                'value' => floatval($data['value']),
                'timestamp' => $data['time'],
                'fetched_at' => current_time('timestamp'),
                'source' => 'api'
            );
            
            // Cache for 1 hour
            set_transient('current_uva_data', $uva_data, HOUR_IN_SECONDS);
            
            // Also save as permanent backup
            update_option('uva_last_known_data', $uva_data);
            
            return $uva_data;
        }
    }
    
    // If API fails, try to get last known value
    $last_known = get_option('uva_last_known_data');
    if ($last_known && isset($last_known['value'])) {
        $last_known['source'] = 'cache';
        return $last_known;
    }
    
    // Ultimate fallback
    return array(
        'value' => 1484.82,
        'timestamp' => current_time('timestamp'),
        'fetched_at' => current_time('timestamp'),
        'source' => 'fallback'
    );
}

/**
 * Get the current UVA value
 * 
 * @return float Current UVA value
 */
function get_current_uva_value() {
    $data = get_current_uva_data();
    return $data['value'];
}

/**
 * Get the UVA data update timestamp
 * 
 * @return int Timestamp when UVA data was last updated
 */
function get_uva_update_time() {
    $data = get_current_uva_data();
    return isset($data['fetched_at']) ? $data['fetched_at'] : current_time('timestamp');
}

/**
 * Get the source of UVA data (api, cache, or fallback)
 * 
 * @return string Source of UVA data
 */
function get_uva_source() {
    $data = get_current_uva_data();
    return isset($data['source']) ? $data['source'] : 'unknown';
}

/**
 * Convert pesos to UVAs
 * 
 * @param float $pesos Amount in pesos
 * @return float Amount in UVAs
 */
function pesos_to_uva($pesos) {
    $uva_value = get_current_uva_value();
    return $pesos / $uva_value;
}

/**
 * Convert UVAs to pesos
 * 
 * @param float $uvas Amount in UVAs
 * @return float Amount in pesos
 */
function uva_to_pesos($uvas) {
    $uva_value = get_current_uva_value();
    return $uvas * $uva_value;
}