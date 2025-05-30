<?php
/**
 * Mortgage calculation functions for Custom Mortgage Calculator
 * 
 * @package Custom_Mortgage_Calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main calculation dispatcher - routes to appropriate calculation method
 * 
 * @param array $data Form data
 * @param int $step Current step number
 * @return array Calculation results
 */
function perform_mortgage_calculations($data, $step) {
    // Use UVA calculations for this branch
    return perform_uva_mortgage_calculations($data, $step);
}

/**
 * Perform UVA mortgage calculations
 * 
 * @param array $data Form data containing loan details
 * @param int $step Current step number
 * @return array Calculation results with payment breakdowns and ratios
 */
function perform_uva_mortgage_calculations($data, $step) {
    $loan_amount = floatval($data['loan_amount'] ?? 0);
    $loan_term = intval($data['loan_term'] ?? 30);
    $home_value = floatval($data['home_value'] ?? 0);
    $down_payment = floatval($data['down_payment'] ?? 0);
    $monthly_income = floatval($data['monthly_income'] ?? 0);
    
    // Get current UVA data
    $uva_data = get_current_uva_data();
    $current_uva_value = $uva_data['value'];
    
    // For Step 1 calculations (no home value yet), estimate home value from loan amount
    if ($step == 1 && $home_value == 0 && $loan_amount > 0) {
        // Assume 80% LTV for initial estimate (UVA max is 80% for primary residence)
        $home_value = $loan_amount / 0.8;
    }
    
    // Get current mortgage rates from BCRA API or cache
    $rates_data = get_current_mortgage_rates();
    
    // UVA mortgage parameters
    $tna_rate = $rates_data['tna_rate']; // T.N.A. - Tasa Nominal Anual
    $tea_rate = $rates_data['tea_rate']; // T.E.A. - Tasa Efectiva Anual
    $cftea_rate = $rates_data['cftea_rate']; // C.F.T.E.A. - includes fees and insurance
    
    // Calculate loan-to-value ratio
    $ltv = $home_value > 0 ? ($loan_amount / $home_value) * 100 : 0;
    
    // Validate LTV (max 80% for primary residence)
    if ($ltv > 80) {
        $ltv = 80;
        $loan_amount = $home_value * 0.8;
    }
    
    // Convert loan amount to UVAs
    $loan_amount_uvas = pesos_to_uva($loan_amount);
    
    // Monthly interest rate based on T.N.A.
    $monthly_rate = $tna_rate / 100 / 12;
    $total_payments = $loan_term * 12;
    
    // Calculate monthly payment in UVAs (French amortization system)
    if ($monthly_rate > 0) {
        $monthly_payment_uvas = $loan_amount_uvas * ($monthly_rate * pow(1 + $monthly_rate, $total_payments)) / 
                               (pow(1 + $monthly_rate, $total_payments) - 1);
    } else {
        $monthly_payment_uvas = $loan_amount_uvas / $total_payments;
    }
    
    // Convert monthly payment to current pesos
    $monthly_payment_pesos = uva_to_pesos($monthly_payment_uvas);
    
    // Estimate property tax (1.2% annually) - stays in pesos
    $annual_property_tax = $home_value * 0.012;
    $monthly_property_tax = $annual_property_tax / 12;
    
    // Estimate home insurance (0.5% annually) - stays in pesos
    $annual_insurance = $home_value * 0.005;
    $monthly_insurance = $annual_insurance / 12;
    
    // No PMI for UVA mortgages (already limited to 80% LTV)
    $monthly_pmi = 0;
    
    // Total monthly payment in pesos
    $total_monthly_pesos = $monthly_payment_pesos + $monthly_property_tax + $monthly_insurance;
    
    // Total interest over life of loan (in UVAs)
    $total_interest_uvas = ($monthly_payment_uvas * $total_payments) - $loan_amount_uvas;
    $total_interest_pesos = uva_to_pesos($total_interest_uvas);
    
    // Calculate debt-to-income ratio (max 25% for UVA)
    $debt_to_income_ratio = 0;
    if ($monthly_income > 0) {
        $debt_to_income_ratio = ($total_monthly_pesos / $monthly_income) * 100;
    }
    
    return array(
        'monthly_payment' => round($total_monthly_pesos, 2),
        'principal_interest' => round($monthly_payment_pesos, 2),
        'property_tax' => round($monthly_property_tax, 2),
        'insurance' => round($monthly_insurance, 2),
        'pmi' => 0,
        'interest_rate' => $tna_rate,
        'tna_rate' => round($tna_rate, 2),
        'tea_rate' => round($tea_rate, 2),
        'cftea_rate' => round($cftea_rate, 2),
        'total_interest' => round($total_interest_pesos, 2),
        'loan_amount' => $loan_amount,
        'ltv_ratio' => round($ltv, 1),
        'debt_to_income_ratio' => round($debt_to_income_ratio, 1),
        'monthly_income' => $monthly_income,
        // UVA specific values
        'current_uva_value' => $current_uva_value,
        'loan_amount_uvas' => round($loan_amount_uvas, 2),
        'monthly_payment_uvas' => round($monthly_payment_uvas, 2),
        'uva_date' => date('d/m/Y'),
        'uva_update_time' => date('d/m/Y H:i', $uva_data['fetched_at']),
        'uva_source' => $uva_data['source'],
        'income_validation' => $debt_to_income_ratio <= 25 ? 'valid' : 'invalid',
        // Rate source information
        'rates_source' => $rates_data['source'],
        'rates_updated' => isset($rates_data['fetched_at']) ? $rates_data['fetched_at'] : null,
        'rates_date' => isset($rates_data['date']) ? $rates_data['date'] : date('Y-m-d')
    );
}

/**
 * Perform traditional mortgage calculations (alternative method)
 * 
 * @param array $data Form data containing loan details
 * @param int $step Current step number
 * @return array Calculation results with payment breakdowns and ratios
 */
function perform_traditional_mortgage_calculations($data, $step) {
    $loan_amount = floatval($data['loan_amount'] ?? 0);
    $loan_term = intval($data['loan_term'] ?? 30);
    $home_value = floatval($data['home_value'] ?? 0);
    $down_payment = floatval($data['down_payment'] ?? 0);
    $monthly_income = floatval($data['monthly_income'] ?? 0);
    
    // For Step 1 calculations (no home value yet), estimate home value from loan amount
    if ($step == 1 && $home_value == 0 && $loan_amount > 0) {
        // Assume 80% LTV for initial estimate
        $home_value = $loan_amount / 0.8;
    }
    
    // Base interest rate estimation (you can make this dynamic)
    $base_rate = 4.5;
    
    // Calculate loan-to-value ratio
    $ltv = $home_value > 0 ? ($loan_amount / $home_value) * 100 : 0;
    
    // Adjust interest rate based on LTV
    $interest_rate = $base_rate;
    if ($ltv > 80) {
        $interest_rate += 0.25; // Higher rate for high LTV
    }
    if ($ltv > 90) {
        $interest_rate += 0.25; // Even higher for very high LTV
    }
    
    // Monthly interest rate
    $monthly_rate = $interest_rate / 100 / 12;
    $total_payments = $loan_term * 12;
    
    // Calculate monthly principal and interest
    if ($monthly_rate > 0) {
        $monthly_pi = $loan_amount * ($monthly_rate * pow(1 + $monthly_rate, $total_payments)) / 
                     (pow(1 + $monthly_rate, $total_payments) - 1);
    } else {
        $monthly_pi = $loan_amount / $total_payments;
    }
    
    // Estimate property tax (1.2% annually)
    $annual_property_tax = $home_value * 0.012;
    $monthly_property_tax = $annual_property_tax / 12;
    
    // Estimate home insurance (0.5% annually)
    $annual_insurance = $home_value * 0.005;
    $monthly_insurance = $annual_insurance / 12;
    
    // Calculate PMI if applicable (0.5% annually if LTV > 80%)
    $monthly_pmi = 0;
    if ($ltv > 80) {
        $annual_pmi = $loan_amount * 0.005;
        $monthly_pmi = $annual_pmi / 12;
    }
    
    // Total monthly payment
    $total_monthly = $monthly_pi + $monthly_property_tax + $monthly_insurance + $monthly_pmi;
    
    // Total interest over life of loan
    $total_interest = ($monthly_pi * $total_payments) - $loan_amount;
    
    // Calculate debt-to-income ratio
    $debt_to_income_ratio = 0;
    if ($monthly_income > 0) {
        $debt_to_income_ratio = ($total_monthly / $monthly_income) * 100;
    }
    
    return array(
        'monthly_payment' => round($total_monthly, 2),
        'principal_interest' => round($monthly_pi, 2),
        'property_tax' => round($monthly_property_tax, 2),
        'insurance' => round($monthly_insurance, 2),
        'pmi' => round($monthly_pmi, 2),
        'interest_rate' => round($interest_rate, 3),
        'total_interest' => round($total_interest, 2),
        'loan_amount' => $loan_amount,
        'ltv_ratio' => round($ltv, 1),
        'debt_to_income_ratio' => round($debt_to_income_ratio, 1),
        'monthly_income' => $monthly_income
    );
}