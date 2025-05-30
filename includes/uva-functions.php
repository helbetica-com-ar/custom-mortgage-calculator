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

/**
 * Get current bank exchange rates from API or cache
 * 
 * @return array Bank exchange rates data
 */
function get_current_bank_rates() {
    // Check for cached data first (cache for 30 minutes)
    $cached_data = get_transient('current_bank_rates');
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    // Try to fetch from API
    $response = wp_remote_get('https://criptoya.com/api/bancostodos', array(
        'timeout' => 8 // 8 second timeout
    ));
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (is_array($data) && !empty($data)) {
            // Comprehensive bank name mapping
            $bank_names = array(
                'bna' => 'Banco Nación',
                'santander' => 'Santander',
                'galicia' => 'Banco Galicia',
                'bbva' => 'BBVA',
                'patagonia' => 'Banco Patagonia',
                'macro' => 'Banco Macro',
                'hsbc' => 'HSBC',
                'bapro' => 'Banco Provincia',
                'ciudad' => 'Banco Ciudad',
                'brubank' => 'Brubank',
                'supervielle' => 'Supervielle',
                'icbc' => 'ICBC',
                'hipotecario' => 'Banco Hipotecario',
                'balanz' => 'Balanz',
                'plus' => 'Plus Cambio',
                'cambioar' => 'Cambio.ar',
                'dolaria' => 'Dolaria',
                'buendolar' => 'Buen Dólar',
                'rebanking' => 'Rebanking',
                'cambiosroca' => 'Cambios Roca',
                'davsa' => 'Davsa',
                'naranjax' => 'Naranja X',
                'prex' => 'Prex',
                'globalcambio' => 'Global Cambio',
                'cambiodieza' => 'Cambio Diez A',
                'plazacambio' => 'Plaza Cambio',
                'triacambio' => 'Tria Cambio',
                'cambioposadas' => 'Cambio Posadas',
                'dolariol' => 'Dólar IOL'
            );
            
            $formatted_rates = array();
            $latest_time = 0;
            
            foreach ($data as $key => $bank_data) {
                if (isset($bank_data['ask']) && isset($bank_data['bid']) && isset($bank_data['time'])) {
                    // Skip if data seems too old (more than 7 days)
                    if ($bank_data['time'] > (time() - (7 * DAY_IN_SECONDS))) {
                        // Get bank display name
                        $bank_name = isset($bank_names[$key]) ? $bank_names[$key] : ucfirst($key);
                        
                        $formatted_rates[] = array(
                            'id' => $key,
                            'name' => $bank_name,
                            'buy' => floatval($bank_data['bid']),
                            'sell' => floatval($bank_data['ask']),
                            'time' => $bank_data['time']
                        );
                        
                        if ($bank_data['time'] > $latest_time) {
                            $latest_time = $bank_data['time'];
                        }
                    }
                }
            }
            
            // Sort by bank name for better organization
            usort($formatted_rates, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            if (!empty($formatted_rates)) {
                $bank_rates_data = array(
                    'rates' => $formatted_rates,
                    'fetched_at' => current_time('timestamp'),
                    'latest_update' => $latest_time,
                    'source' => 'api'
                );
                
                // Cache for 30 minutes
                set_transient('current_bank_rates', $bank_rates_data, 30 * MINUTE_IN_SECONDS);
                
                // Also save as permanent backup
                update_option('bank_rates_last_known_data', $bank_rates_data);
                
                return $bank_rates_data;
            }
        }
    }
    
    // If API fails, try to get last known rates
    $last_known = get_option('bank_rates_last_known_data');
    if ($last_known) {
        $last_known['source'] = 'cache';
        return $last_known;
    }
    
    // Return empty if no data available
    return array(
        'rates' => array(),
        'fetched_at' => current_time('timestamp'),
        'source' => 'unavailable'
    );
}