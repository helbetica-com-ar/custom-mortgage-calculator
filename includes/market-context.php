<?php
/**
 * Market Context Shortcode
 * 
 * Displays current UVA value and mortgage rates
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the market context shortcode
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function render_market_context_shortcode($atts) {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'show_uva' => 'true',
        'show_rates' => 'true',
        'show_banks' => 'true',
        'show_update_time' => 'true',
        'compact' => 'false',
        'theme' => 'light' // light or dark
    ), $atts, 'market-context');
    
    // Start output buffering
    ob_start();
    
    // Get current UVA data
    $uva_data = get_current_uva_data();
    $uva_value = isset($uva_data['value']) ? $uva_data['value'] : get_current_uva_value();
    $uva_timestamp = isset($uva_data['timestamp']) ? $uva_data['timestamp'] : null;
    $uva_source = get_uva_source();
    
    // Get mortgage rates
    $rates = get_current_mortgage_rates();
    
    // Get bank exchange rates
    $bank_rates = get_current_bank_rates();
    
    // Determine CSS classes
    $container_classes = array('market-context-widget');
    if ($atts['compact'] === 'true') {
        $container_classes[] = 'market-context-compact';
    }
    if ($atts['theme'] === 'dark') {
        $container_classes[] = 'market-context-dark';
    }
    
    ?>
    <div class="<?php echo esc_attr(implode(' ', $container_classes)); ?>">
        <?php if ($atts['show_uva'] === 'true'): ?>
        <div class="market-context-section market-context-uva">
            <h3 class="market-context-title"><?php _e('Valor UVA Actual', 'custom-mortgage-calculator'); ?></h3>
            <div class="market-context-value">
                <span class="market-context-currency">$</span>
                <span class="market-context-amount"><?php echo number_format($uva_value, 2, ',', '.'); ?></span>
            </div>
            
            <?php if ($atts['show_update_time'] === 'true' && $uva_timestamp): ?>
            <div class="market-context-meta">
                <span class="market-context-update-time">
                    <?php 
                    $fecha_actualizacion = new DateTime('@' . $uva_timestamp);
                    $fecha_actualizacion->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
                    echo sprintf(
                        __('Actualizado: %s', 'custom-mortgage-calculator'),
                        $fecha_actualizacion->format('d/m/Y H:i')
                    );
                    ?>
                </span>
                <?php if ($uva_source !== 'api'): ?>
                <span class="market-context-source market-context-source-<?php echo esc_attr($uva_source); ?>">
                    <?php
                    if ($uva_source === 'cache') {
                        echo __('(desde caché)', 'custom-mortgage-calculator');
                    } elseif ($uva_source === 'fallback') {
                        echo __('(valor de respaldo)', 'custom-mortgage-calculator');
                    }
                    ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_rates'] === 'true' && !empty($rates)): ?>
        <div class="market-context-section market-context-rates">
            <h3 class="market-context-title"><?php _e('Tasas de Préstamos Hipotecarios', 'custom-mortgage-calculator'); ?></h3>
            <div class="market-context-rates-grid">
                <?php if (isset($rates['tna_rate'])): ?>
                <div class="market-context-rate-item">
                    <span class="market-context-rate-label"><?php _e('TNA', 'custom-mortgage-calculator'); ?></span>
                    <span class="market-context-rate-value"><?php echo number_format($rates['tna_rate'], 2, ',', '.'); ?>%</span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($rates['tea_rate'])): ?>
                <div class="market-context-rate-item">
                    <span class="market-context-rate-label"><?php _e('TEA', 'custom-mortgage-calculator'); ?></span>
                    <span class="market-context-rate-value"><?php echo number_format($rates['tea_rate'], 2, ',', '.'); ?>%</span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($rates['cftea_rate'])): ?>
                <div class="market-context-rate-item">
                    <span class="market-context-rate-label"><?php _e('CFTEA', 'custom-mortgage-calculator'); ?></span>
                    <span class="market-context-rate-value"><?php echo number_format($rates['cftea_rate'], 2, ',', '.'); ?>%</span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($atts['show_update_time'] === 'true' && isset($rates['fetched_at'])): ?>
            <div class="market-context-meta">
                <span class="market-context-update-time">
                    <?php 
                    $fecha_rates = new DateTime('@' . $rates['fetched_at']);
                    $fecha_rates->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
                    echo sprintf(
                        __('Fuente BCRA: %s', 'custom-mortgage-calculator'),
                        $fecha_rates->format('d/m/Y H:i')
                    );
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($atts['show_rates'] === 'true'): ?>
        <div class="market-context-section market-context-rates">
            <p class="market-context-no-data"><?php _e('No hay datos de tasas disponibles en este momento.', 'custom-mortgage-calculator'); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($atts['show_banks'] === 'true' && isset($bank_rates['rates']) && !empty($bank_rates['rates'])): ?>
        <div class="market-context-section market-context-banks">
            <h3 class="market-context-title"><?php _e('Cotización USD en Bancos', 'custom-mortgage-calculator'); ?></h3>
            <div class="market-context-banks-grid">
                <?php foreach ($bank_rates['rates'] as $bank): ?>
                <div class="market-context-bank-item">
                    <div class="market-context-bank-name">
                        <?php echo esc_html($bank['name']); ?>
                        <?php if (isset($bank['age_days']) && $bank['age_days'] > 1): ?>
                        <span class="market-context-data-age">(<?php echo $bank['age_days']; ?>d)</span>
                        <?php endif; ?>
                    </div>
                    <div class="market-context-bank-rates">
                        <span class="market-context-bank-rate">
                            <span class="market-context-rate-type"><?php _e('Compra', 'custom-mortgage-calculator'); ?></span>
                            <span class="market-context-rate-amount">$<?php echo number_format($bank['buy'], 2, ',', '.'); ?></span>
                        </span>
                        <span class="market-context-bank-rate">
                            <span class="market-context-rate-type"><?php _e('Venta', 'custom-mortgage-calculator'); ?></span>
                            <span class="market-context-rate-amount">$<?php echo number_format($bank['sell'], 2, ',', '.'); ?></span>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($atts['show_update_time'] === 'true' && isset($bank_rates['latest_update'])): ?>
            <div class="market-context-meta">
                <span class="market-context-update-time">
                    <?php 
                    $fecha_banks = new DateTime('@' . $bank_rates['latest_update']);
                    $fecha_banks->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));
                    echo sprintf(
                        __('Última actualización: %s', 'custom-mortgage-calculator'),
                        $fecha_banks->format('d/m/Y H:i')
                    );
                    ?>
                </span>
                <?php if ($bank_rates['source'] !== 'api'): ?>
                <span class="market-context-source market-context-source-<?php echo esc_attr($bank_rates['source']); ?>">
                    <?php
                    if ($bank_rates['source'] === 'cache') {
                        echo __('(desde caché)', 'custom-mortgage-calculator');
                    } elseif ($bank_rates['source'] === 'unavailable') {
                        echo __('(datos no disponibles)', 'custom-mortgage-calculator');
                    }
                    ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($atts['show_banks'] === 'true'): ?>
        <div class="market-context-section market-context-banks">
            <h3 class="market-context-title"><?php _e('Cotización USD en Bancos', 'custom-mortgage-calculator'); ?></h3>
            <p class="market-context-no-data"><?php _e('No hay datos de cotizaciones bancarias disponibles en este momento.', 'custom-mortgage-calculator'); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}