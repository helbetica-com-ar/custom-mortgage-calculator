/**
 * Mortgage Calculator JavaScript
 * Vanilla JavaScript for complete functionality
 * Save as: /js/mortgage-calculator.js in your theme
 */

// Global variables
let currentStep = 1;
let formData = {};
let isSubmitting = false;
let isRestoringState = false;

/**
 * Remove thousand separators from a formatted string and return clean number
 */
function parseFormattedNumber(value) {
    if (!value) return '';
    
    // Remove all dots (thousand separators) and convert to number
    const cleanValue = value.toString().replace(/\./g, '');
    return cleanValue;
}

/**
 * Format number with thousand separators for display
 */
function addThousandSeparators(value) {
    if (!value) return '';
    
    // Remove any existing dots and non-digits
    const cleanValue = value.toString().replace(/[^\d]/g, '');
    
    if (!cleanValue) return '';
    
    // Manual formatting to ensure Argentine format (dots for thousands)
    const reversed = cleanValue.split('').reverse();
    const formatted = [];
    
    for (let i = 0; i < reversed.length; i++) {
        if (i > 0 && i % 3 === 0) {
            formatted.push('.');
        }
        formatted.push(reversed[i]);
    }
    
    return formatted.reverse().join('');
}

// Argentine currency formatting functions
function formatCurrency(amount) {
    // Convert to number if string
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    // Ensure Argentine formatting by using our custom function
    return '$' + addThousandSeparators(Math.round(num));
}

function formatNumber(amount) {
    // Convert to number if string
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    // Format number without currency symbol using Argentine format
    // es-AR uses period for thousands separator
    return new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
        useGrouping: true
    }).format(Math.round(num));
}

function formatDecimal(amount, decimals = 2) {
    // Convert to number if string
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    // Format with decimal places
    return new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeMortgageCalculator();
});

/**
 * Initialize the calculator
 */
function initializeMortgageCalculator() {
    const wrapper = document.querySelector('.mortgage-calculator-wrapper');
    
    // Add event listeners for real-time calculations
    addInputEventListeners();

    // Add form validation
    addFormValidation();

    // Format currency inputs
    formatCurrencyInputs();

    // Initialize tooltips or help text
    initializeHelpSystem();
    
    // Restore saved state if it exists (without interfering with scroll)
    const wasRestored = restoreFormState();
    
    // Show form after initialization
    if (wrapper) {
        if (wasRestored) {
            // If state was restored, wait a bit for calculations then show
            setTimeout(() => {
                wrapper.classList.add('initialized');
            }, 150);
        } else {
            // If no state to restore, show immediately
            setTimeout(() => {
                wrapper.classList.add('initialized');
            }, 50);
        }
    }
}

/**
 * Navigate to next step
 */
function nextStep(step) {
    if (isSubmitting) return;

    // Validate current step
    if (!validateStep(step)) {
        return;
    }

    // Collect form data
    const stepFormData = collectStepData(step);
    Object.assign(formData, stepFormData);

    // Show loading
    showLoading();

    // Send AJAX request
    sendStepData(step, stepFormData)
        .then(response => {
            hideLoading();

            if (response.success) {
                // Update calculations display
                updateCalculationsDisplay(response.data.calculations, step + 1);
                
                // Update loan term display when moving to step 2
                if (step === 1 && stepFormData.loan_term) {
                    updateLoanTermDisplay(stepFormData.loan_term);
                }

                // Move to next step
                showStep(step + 1);
                updateProgressBar(step + 1);
                currentStep = step + 1;
                
                // Update step clickability
                updateStepClickability();

                // Save form state
                saveFormState();

                // Don't scroll during state restoration
                if (!isRestoringState) {
                    scrollToTop();
                }
            } else {
                showError(mortgageAjax.i18n.generalError);
            }
        })
        .catch(error => {
            hideLoading();
            showError(mortgageAjax.i18n.networkError);
            console.error('Error:', error);
        });
}

/**
 * Navigate to previous step
 */
function prevStep(step) {
    console.log('prevStep called with step:', step);
    console.log('Current step before navigation:', currentStep);
    
    if (step > 1) {
        // Collect current step data before navigating away
        const currentStepData = collectStepData(step);
        Object.assign(formData, currentStepData);
        
        // Navigate to previous step
        const targetStep = step - 1;
        console.log('Target step:', targetStep);
        currentStep = targetStep;
        showStep(targetStep);
        updateProgressBar(targetStep);
        
        // Update step clickability
        updateStepClickability();
        
        // If navigating to step 2 or 3, update calculations
        if (targetStep > 1 && Object.keys(formData).length > 0) {
            // Show loading
            showLoading();
            
            // Send AJAX request to get calculations
            sendStepData(targetStep - 1, formData)
                .then(response => {
                    hideLoading();
                    if (response.success) {
                        updateCalculationsDisplay(response.data.calculations, targetStep);
                        
                        // Update loan term display when navigating to step 2
                        if (targetStep === 2 && formData.loan_term) {
                            updateLoanTermDisplay(formData.loan_term);
                        }
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error updating calculations:', error);
                });
        }
        
        // Save form state
        saveFormState();
        
        // Don't scroll during state restoration
        if (!isRestoringState) {
            scrollToTop();
        }
    }
}

/**
 * Show specific step
 */
function showStep(stepNumber) {
    // Hide all steps
    const steps = document.querySelectorAll('.calculator-step');
    steps.forEach(step => step.classList.remove('active'));

    // Show target step
    const targetStep = document.getElementById(`step-${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active');
    }
}

/**
 * Show step instantly without transitions for state restoration
 */
function showStepInstant(stepNumber) {
    const steps = document.querySelectorAll('.calculator-step');
    
    // Temporarily disable transitions
    steps.forEach(step => {
        step.style.transition = 'none';
        step.classList.remove('active');
    });

    // Show target step
    const targetStep = document.getElementById(`step-${stepNumber}`);
    if (targetStep) {
        targetStep.classList.add('active');
    }
    
    // Re-enable transitions after a brief moment
    setTimeout(() => {
        steps.forEach(step => {
            step.style.transition = '';
        });
    }, 50);
}

/**
 * Update progress bar
 */
function updateProgressBar(stepNumber) {
    const progressSteps = document.querySelectorAll('.progress-step');

    progressSteps.forEach((step, index) => {
        if (index < stepNumber) {
            step.classList.add('active');
            step.classList.add('completed');
        } else if (index === stepNumber - 1) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            step.classList.remove('active');
            step.classList.remove('completed');
        }
    });
}

/**
 * Update progress bar instantly without transitions for state restoration
 */
function updateProgressBarInstant(stepNumber) {
    const progressSteps = document.querySelectorAll('.progress-step');
    
    // Temporarily disable transitions
    progressSteps.forEach(step => {
        step.style.transition = 'none';
    });

    progressSteps.forEach((step, index) => {
        if (index < stepNumber) {
            step.classList.add('active');
            step.classList.add('completed');
        } else if (index === stepNumber - 1) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            step.classList.remove('active');
            step.classList.remove('completed');
        }
    });
    
    // Re-enable transitions after a brief moment
    setTimeout(() => {
        progressSteps.forEach(step => {
            step.style.transition = '';
        });
    }, 50);
}

/**
 * Navigate to a specific step (clickable navigation)
 */
function goToStep(targetStep) {
    if (isSubmitting) return;
    
    // Don't do anything if clicking on current step
    if (targetStep === currentStep) {
        return;
    }
    
    // Collect current step data before navigating away
    const currentStepData = collectStepData(currentStep);
    Object.assign(formData, currentStepData);
    
    // Check if we have the required data to access the target step
    if (targetStep > 1) {
        const hasRequiredData = checkStepData(targetStep);
        if (!hasRequiredData) {
            return;
        }
    }
    
    // For forward navigation, validate current step
    if (targetStep > currentStep) {
        if (!validateStep(currentStep)) {
            return;
        }
    }
    
    // Navigate to the target step
    currentStep = targetStep;
    showStep(targetStep);
    updateProgressBar(targetStep);
    updateStepClickability();
    
    // If navigating to step 2 or 3, update calculations
    if (targetStep > 1 && Object.keys(formData).length > 0) {
        // Show loading
        showLoading();
        
        // Send AJAX request to get calculations
        sendStepData(targetStep - 1, formData)
            .then(response => {
                hideLoading();
                if (response.success) {
                    updateCalculationsDisplay(response.data.calculations, targetStep);
                    
                    // Update loan term display when navigating to step 2
                    if (targetStep === 2 && formData.loan_term) {
                        updateLoanTermDisplay(formData.loan_term);
                    }
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error updating calculations:', error);
            });
    }
    
    // Save current state
    saveFormState();
    
    // Scroll to top
    scrollToTop();
}

/**
 * Check if we have the required data to access a step
 */
function checkStepData(step) {
    if (step === 1) return true; // Step 1 is always accessible
    
    if (step === 2) {
        // Step 2 requires step 1 data
        return formData.loan_amount && formData.loan_term;
    }
    
    if (step === 3) {
        // Step 3 requires step 1 and 2 data
        return formData.loan_amount && formData.loan_term && 
               formData.home_value && formData.down_payment;
    }
    
    return false;
}

/**
 * Update step clickability based on current state
 */
function updateStepClickability() {
    const progressSteps = document.querySelectorAll('.progress-step');
    
    progressSteps.forEach((step, index) => {
        const stepNumber = index + 1;
        
        // Remove all clickability classes first
        step.classList.remove('clickable', 'not-clickable');
        
        if (stepNumber === currentStep) {
            // Current step is not clickable
            step.style.cursor = 'default';
        } else if (stepNumber < currentStep || checkStepData(stepNumber)) {
            // Step is clickable if it's a previous step with data
            step.classList.add('clickable');
            step.style.cursor = 'pointer';
        } else {
            // Step is not clickable
            step.classList.add('not-clickable');
            step.style.cursor = 'default';
        }
    });
}

/**
 * Validate step data
 */
function validateStep(step) {
    // Try multiple selectors to find the form
    let form = document.querySelector(`form[data-step="${step}"]`);
    
    if (!form) {
        // Try with class selector
        form = document.querySelector(`.step-form[data-step="${step}"]`);
    }
    
    if (!form) {
        // Try within the active step
        const activeStep = document.querySelector('.calculator-step.active');
        if (activeStep) {
            form = activeStep.querySelector('form');
        }
    }
    
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    let firstInvalidField = null;

    requiredFields.forEach(field => {
        // Clear previous errors
        clearFieldError(field);

        if (!field.value.trim()) {
            showFieldError(field, mortgageAjax.i18n.fieldRequired);
            isValid = false;
            if (!firstInvalidField) firstInvalidField = field;
        } else {
            // Field-specific validation
            if (!validateFieldValue(field)) {
                isValid = false;
                if (!firstInvalidField) firstInvalidField = field;
            }
        }
    });

    // Focus first invalid field
    if (firstInvalidField) {
        firstInvalidField.focus();
    }

    return isValid;
}

/**
 * Validate individual field values
 */
function validateFieldValue(field) {
    const value = field.value.trim();
    const fieldType = field.type;
    const fieldName = field.name;

    switch (fieldType) {
        case 'email':
            if (!isValidEmail(value)) {
                showFieldError(field, mortgageAjax.i18n.validEmailRequired);
                return false;
            }
            break;

        case 'tel':
            if (!isValidPhone(value)) {
                showFieldError(field, mortgageAjax.i18n.validPhoneRequired);
                return false;
            }
            break;

        case 'text':
            // Handle numeric text inputs (inputmode="numeric")
            if (field.getAttribute('inputmode') === 'numeric') {
                // Get the raw numeric value from formatted input
                let rawValue = value;
                if (field.hasAttribute('data-raw-value')) {
                    rawValue = field.getAttribute('data-raw-value');
                } else {
                    // If no raw value, parse the formatted value
                    rawValue = parseFormattedNumber(value);
                }
                
                const numValue = parseFloat(rawValue);
                const min = parseFloat(field.getAttribute('data-min'));
                const max = parseFloat(field.getAttribute('data-max'));

                if (isNaN(numValue)) {
                    showFieldError(field, mortgageAjax.i18n.validNumberRequired);
                    return false;
                }

                if (min && numValue < min) {
                    showFieldError(field, mortgageAjax.i18n.valueMinimum.replace('%s', formatCurrency(min)));
                    return false;
                }

                if (max && numValue > max) {
                    showFieldError(field, mortgageAjax.i18n.valueMaximum.replace('%s', formatCurrency(max)));
                    return false;
                }
            }
            break;
            
        case 'number':
            // Legacy number inputs (if any remain)
            const numValue = parseFloat(value);
            const min = parseFloat(field.min);
            const max = parseFloat(field.max);

            if (isNaN(numValue)) {
                showFieldError(field, mortgageAjax.i18n.validNumberRequired);
                return false;
            }

            if (min && numValue < min) {
                showFieldError(field, mortgageAjax.i18n.valueMinimum.replace('%s', formatCurrency(min)));
                return false;
            }

            if (max && numValue > max) {
                showFieldError(field, mortgageAjax.i18n.valueMaximum.replace('%s', formatCurrency(max)));
                return false;
            }

            // Specific validation for certain fields
            if (fieldName === 'down_payment') {
                const homeValueField = document.getElementById('home_value');
                let homeValueRaw = homeValueField?.value || 0;
                
                // Get raw value if it's a formatted input
                if (homeValueField && homeValueField.hasAttribute('data-raw-value')) {
                    homeValueRaw = homeValueField.getAttribute('data-raw-value');
                } else if (homeValueField) {
                    homeValueRaw = parseFormattedNumber(homeValueField.value);
                }
                
                const homeValue = parseFloat(homeValueRaw);
                if (homeValue && numValue > homeValue) {
                    showFieldError(field, mortgageAjax.i18n.downPaymentExceedsHome);
                    return false;
                }
            }
            break;
    }

    return true;
}

/**
 * Show field error
 */
function showFieldError(field, message) {
    // Remove existing error
    clearFieldError(field);

    // Add error class
    field.classList.add('error');

    // Create error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;

    // Insert error message
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
}

/**
 * Clear field error
 */
function clearFieldError(field) {
    field.classList.remove('error');
    const errorMsg = field.parentNode.querySelector('.field-error');
    if (errorMsg) {
        errorMsg.remove();
    }
}

/**
 * Collect step data
 */
function collectStepData(step) {
    // Try multiple selectors to find the form
    let form = document.querySelector(`form[data-step="${step}"]`);
    
    if (!form) {
        // Try with class selector
        form = document.querySelector(`.step-form[data-step="${step}"]`);
    }
    
    if (!form) {
        // Try within the active step
        const activeStep = document.querySelector('.calculator-step.active');
        if (activeStep) {
            form = activeStep.querySelector('form');
        }
    }
    
    // Debug logging
    console.log('Looking for form with data-step:', step);
    console.log('Found form:', form);
    console.log('Form tagName:', form ? form.tagName : 'null');
    console.log('All forms on page:', document.querySelectorAll('form'));
    
    if (!form) {
        console.error('Form not found for step:', step);
        return {};
    }
    
    if (!(form instanceof HTMLFormElement)) {
        console.error('Element is not a form:', form);
        return {};
    }

    const formData = new FormData(form);
    const data = {};

    for (let [key, value] of formData.entries()) {
        if (form.querySelector(`[name="${key}"][type="checkbox"]`)) {
            data[key] = true; // Checkbox is checked if it appears in FormData
        } else {
            // Check if this is a formatted numeric input
            const input = form.querySelector(`[name="${key}"]`);
            if (input && input.getAttribute('inputmode') === 'numeric') {
                // Use the raw numeric value for calculations
                if (input.hasAttribute('data-raw-value')) {
                    data[key] = input.getAttribute('data-raw-value');
                } else {
                    // If no raw value set yet, parse the current formatted value
                    data[key] = parseFormattedNumber(input.value);
                }
            } else {
                data[key] = value;
            }
        }
    }

    return data;
}

/**
 * Send step data via AJAX
 */
function sendStepData(step, stepData) {
    const formData = new FormData();
    formData.append('action', 'mortgage_calc_step');
    formData.append('nonce', mortgageAjax.nonce);
    formData.append('step', step);
    formData.append('form_data', JSON.stringify(stepData));

    return fetch(mortgageAjax.ajaxurl, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json());
}

/**
 * Update calculations display
 */
function updateCalculationsDisplay(calculations, targetStep) {
    if (!calculations) return;

    // Update step 2 calculations (initial estimate based on Step 1 data only)
    if (targetStep === 2) {
        updateElement('monthly-payment', formatNumber(calculations.monthly_payment));
        updateElement('principal-interest', formatCurrency(calculations.principal_interest));
        updateElement('property-tax', formatCurrency(calculations.property_tax));
        updateElement('insurance', formatCurrency(calculations.insurance));
        
        // Update loan amount display
        updateElement('loan-amount-display', formatCurrency(calculations.loan_amount));
        
        // Update rates if available
        if (calculations.tna_rate) {
            updateElement('tna-rate', calculations.tna_rate + '%');
            updateElement('tea-rate', calculations.tea_rate + '%');
            updateElement('cftea-rate', calculations.cftea_rate + '%');
        }
        
        // Update rate source badge
        updateRateSourceBadge(calculations);
        
        // Add UVA specific info if available
        if (calculations.current_uva_value) {
            // Update UVA value display
            updateElement('current-uva-value-step2', formatDecimal(calculations.current_uva_value, 2));
            
            // Add UVA payment info
            const paymentDisplay = document.querySelector('.payment-amount');
            if (paymentDisplay && !document.getElementById('uva-info')) {
                const uvaInfo = document.createElement('div');
                uvaInfo.id = 'uva-info';
                uvaInfo.className = 'uva-info';
                uvaInfo.innerHTML = `
                    <small>Cuota en UVAs: ${formatDecimal(calculations.monthly_payment_uvas, 2)} UVAs</small>
                `;
                paymentDisplay.appendChild(uvaInfo);
            }
        }
    }

    // Update step 3 calculations (detailed estimate based on Step 1 + Step 2 data)
    if (targetStep === 3) {
        updateElement('final-monthly-payment', formatNumber(calculations.monthly_payment));
        updateElement('final-pi', formatCurrency(calculations.principal_interest));
        updateElement('final-tax', formatCurrency(calculations.property_tax));
        updateElement('final-insurance', formatCurrency(calculations.insurance));
        updateElement('final-pmi', formatCurrency(calculations.pmi || 0));
        updateElement('summary-loan-amount', formatCurrency(calculations.loan_amount));
        updateElement('total-interest', formatCurrency(calculations.total_interest));
        updateElement('debt-to-income', (calculations.debt_to_income_ratio || 0) + '%');
        
        // Update loan term
        if (formData.loan_term) {
            updateElement('final-loan-term', formData.loan_term);
        }
        
        // Update rates in step 3
        if (calculations.tna_rate) {
            updateElement('final-tna-rate', calculations.tna_rate + '%');
            updateElement('final-tea-rate', calculations.tea_rate + '%');
            updateElement('final-cftea-rate', calculations.cftea_rate + '%');
        }
        
        // Add UVA specific details
        if (calculations.current_uva_value) {
            // Update UVA value display
            updateElement('current-uva-value-step3', formatDecimal(calculations.current_uva_value, 2));
            
            // Add UVA loan details
            const loanDetails = document.querySelector('.loan-details');
            if (loanDetails && !document.getElementById('uva-details')) {
                const uvaDetails = document.createElement('div');
                uvaDetails.id = 'uva-details';
                uvaDetails.className = 'uva-details';
                uvaDetails.innerHTML = `
                    <h4>Detalles UVA</h4>
                    <div class="detail-row">
                        <span>Préstamo en UVAs:</span>
                        <span>${formatNumber(calculations.loan_amount_uvas)} UVAs</span>
                    </div>
                    <div class="detail-row">
                        <span>Cuota en UVAs:</span>
                        <span>${formatDecimal(calculations.monthly_payment_uvas, 2)} UVAs</span>
                    </div>
                    <div class="detail-row">
                        <span>Valor UVA actual:</span>
                        <span>${formatCurrency(calculations.current_uva_value)}</span>
                    </div>
                    <div class="detail-row ${calculations.income_validation === 'invalid' ? 'validation-error' : 'validation-success'}">
                        <span>Validación ingreso (25%):</span>
                        <span>${calculations.income_validation === 'valid' ? 'Aprobado' : 'Supera el 25%'}</span>
                    </div>
                `;
                loanDetails.appendChild(uvaDetails);
            }
        }
    }
}

/**
 * Update element content safely
 */
function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

/**
 * Update rate source badge with real-time information
 */
function updateRateSourceBadge(calculations) {
    if (!calculations.rates_source) return;
    
    const badge = document.getElementById('rate-source-badge');
    const badgeStep3 = document.getElementById('rate-source-badge-step3');
    const updateInfo = document.getElementById('rate-update-info');
    
    // Determine the badge text and styling based on source
    let badgeText = '';
    let tooltipText = '';
    let badgeClass = '';
    
    switch (calculations.rates_source) {
        case 'api':
            badgeText = mortgageAjax.i18n.realTimeRates || 'Real-time rates from BCRA';
            badgeClass = 'live';
            if (calculations.rates_updated) {
                const updateDate = new Date(calculations.rates_updated * 1000);
                const hoursAgo = Math.floor((Date.now() - updateDate) / (1000 * 60 * 60));
                tooltipText = (mortgageAjax.i18n.updatedAgo || 'Updated %s hours ago').replace('%s', hoursAgo);
            }
            break;
        case 'cache':
            badgeText = mortgageAjax.i18n.cachedRates || 'BCRA rates (cached)';
            badgeClass = 'cached';
            tooltipText = mortgageAjax.i18n.usingCachedRates || 'Using cached rates';
            break;
        case 'default':
            badgeText = mortgageAjax.i18n.standardRates || 'Standard rates';
            badgeClass = 'default';
            tooltipText = mortgageAjax.i18n.usingDefaultRates || 'Using default rates';
            break;
    }
    
    // Update main badge
    if (badge) {
        badge.className = 'rate-source-badge ' + badgeClass;
        const textSpan = badge.querySelector('span:not(#rate-update-info)');
        if (textSpan) textSpan.textContent = badgeText;
    }
    
    // Update step 3 mini badge
    if (badgeStep3) {
        badgeStep3.className = 'rate-source-badge mini ' + badgeClass;
        const textSpan = badgeStep3.querySelector('span');
        if (textSpan) textSpan.textContent = calculations.rates_source === 'api' ? 'BCRA' : badgeText;
    }
    
    // Update tooltip
    if (updateInfo) {
        updateInfo.textContent = tooltipText;
    }
}

/**
 * Submit final form
 */
function submitFinalForm(event) {
    event.preventDefault();

    if (isSubmitting) return;

    // Validate final step
    if (!validateStep(3)) {
        return;
    }

    // Check terms acceptance
    const termsCheckbox = document.querySelector('[name="terms_accepted"]');
    if (!termsCheckbox?.checked) {
        showError(mortgageAjax.i18n.termsRequired);
        termsCheckbox?.focus();
        return;
    }

    isSubmitting = true;

    // Collect final form data
    const finalFormData = collectStepData(3);
    Object.assign(formData, finalFormData);

    // Show loading
    showLoading('Submitting your application...');

    // Send final submission
    const formDataToSend = new FormData();
    formDataToSend.append('action', 'mortgage_calc_submit');
    formDataToSend.append('nonce', mortgageAjax.nonce);
    formDataToSend.append('form_data', JSON.stringify(finalFormData));

    fetch(mortgageAjax.ajaxurl, {
        method: 'POST',
        body: formDataToSend
    })
        .then(response => response.json())
        .then(response => {
            hideLoading();
            isSubmitting = false;

            if (response.success) {
                // Clear saved form state since form is completed
                clearFormState();
                showSuccessMessage();
            } else {
                showError(mortgageAjax.i18n.submissionError);
            }
        })
        .catch(error => {
            hideLoading();
            isSubmitting = false;
            showError(mortgageAjax.i18n.networkError);
            console.error('Error:', error);
        });
}

/**
 * Show success message
 */
function showSuccessMessage() {
    const calculator = document.querySelector('.mortgage-calculator-wrapper');
    const successMessage = document.getElementById('success-message');

    if (calculator && successMessage) {
        // Hide calculator
        calculator.querySelector('.progress-wrapper').style.display = 'none';
        calculator.querySelectorAll('.calculator-step').forEach(step => {
            step.style.display = 'none';
        });

        // Show success message
        successMessage.style.display = 'block';

        // Don't scroll during state restoration
        if (!isRestoringState) {
            scrollToTop();
        }
    }
}

/**
 * Add input event listeners for real-time updates
 */
function addInputEventListeners() {
    // Real-time calculation on input change
    document.addEventListener('input', function (e) {
        const target = e.target;

        // Format currency inputs as user types
        if (target.getAttribute('inputmode') === 'numeric' && target.closest('.input-wrapper')) {
            // Formatting is handled by formatCurrencyInputs event listeners
        }

        // Trigger real-time calculations based on current step
        if (currentStep === 1 && ['loan_amount', 'loan_term'].includes(target.name)) {
            // Step 1 inputs update Step 2 display
            // Trigger calculation (no delay needed for text inputs)
            debounce(performRealTimeCalculation, 500)();
        } else if (currentStep === 2 && ['home_value', 'down_payment', 'property_location', 'monthly_income', 'property_use'].includes(target.name)) {
            // Step 2 inputs update Step 3 display
            // Trigger calculation (no delay needed for text inputs)
            debounce(performRealTimeCalculation, 500)();
        }
    });
    
    // Also listen for change events on select elements
    document.addEventListener('change', function (e) {
        const target = e.target;
        
        if (target.tagName === 'SELECT') {
            if (currentStep === 1 && target.name === 'loan_term') {
                debounce(performRealTimeCalculation, 500)();
            } else if (currentStep === 2 && target.name === 'property_use') {
                debounce(performRealTimeCalculation, 500)();
            }
        }
    });
}

/**
 * Update loan term display in Step 2
 */
function updateLoanTermDisplay(loanTerm) {
    const loanTermElement = document.getElementById('selected-loan-term');
    if (loanTermElement && loanTerm) {
        loanTermElement.textContent = loanTerm;
    }
}

/**
 * Perform real-time calculation
 */
function performRealTimeCalculation() {
    const currentData = collectStepData(currentStep);
    const allData = { ...formData, ...currentData };
    
    // Update formData with current input
    Object.assign(formData, currentData);
    
    // Save form state
    saveFormState();
    
    // Determine which display to update based on current step
    const targetStep = currentStep === 1 ? 2 : 3;
    
    // Update loan term display if moving from step 1 to step 2
    if (currentStep === 1 && targetStep === 2) {
        updateLoanTermDisplay(allData.loan_term);
    }
    
    // Send AJAX request for server-side calculation
    sendStepData(currentStep, currentData)
        .then(response => {
            if (response.success && response.data.calculations) {
                updateCalculationsDisplay(response.data.calculations, targetStep);
            }
        })
        .catch(error => {
            console.error('Real-time calculation error:', error);
        });
}

/**
 * Format currency inputs
 */
function formatCurrencyInputs() {
    const currencyInputs = document.querySelectorAll('input[inputmode="numeric"]');
    
    currencyInputs.forEach(input => {
        // Store the original numeric value
        let rawValue = input.value;
        
        // Format initial value if it exists
        if (input.value) {
            const formatted = addThousandSeparators(input.value);
            input.setAttribute('data-raw-value', parseFormattedNumber(input.value));
            input.value = formatted;
        }
        
        // Function to format and update raw value
        function updateInputFormatting(inputElement) {
            const rawValue = parseFormattedNumber(inputElement.value);
            
            // Always set the raw value, even if empty
            inputElement.setAttribute('data-raw-value', rawValue);
            
            // Format the display value  
            const formattedValue = addThousandSeparators(rawValue);
            inputElement.value = formattedValue;
            
            return rawValue;
        }
        
        // Initialize with current value (even if empty)
        updateInputFormatting(input);
        
        // Real-time formatting on input
        input.addEventListener('input', function(e) {
            const cursorPosition = this.selectionStart;
            const oldValue = this.value;
            
            updateInputFormatting(this);
            
            // Calculate and restore cursor position
            const newValue = this.value;
            const dotsBeforeCursor = (oldValue.substring(0, cursorPosition).match(/\./g) || []).length;
            const rawCursorPos = cursorPosition - dotsBeforeCursor;
            const newDotsBeforeCursor = (newValue.substring(0, rawCursorPos + dotsBeforeCursor).match(/\./g) || []).length;
            const newCursorPosition = Math.min(rawCursorPos + newDotsBeforeCursor, newValue.length);
            
            // Restore cursor position
            setTimeout(() => {
                if (this === document.activeElement) {
                    this.setSelectionRange(newCursorPosition, newCursorPosition);
                }
            }, 0);
        });
        
        // Handle blur for final validation
        input.addEventListener('blur', function() {
            const rawValue = this.getAttribute('data-raw-value') || '';
            if (rawValue) {
                // Apply final formatting
                this.value = addThousandSeparators(rawValue);
            }
        });
    });
}


/**
 * Add form validation
 */
function addFormValidation() {
    // Real-time validation
    document.addEventListener('blur', function (e) {
        if (e.target.matches('input[required], select[required]')) {
            validateFieldValue(e.target);
        }
    }, true);

    // Clear errors on input
    document.addEventListener('input', function (e) {
        if (e.target.matches('input, select')) {
            clearFieldError(e.target);
        }
    });
}

/**
 * Initialize help system
 */
function initializeHelpSystem() {
    // Add click handlers for help tooltips
    const helpElements = document.querySelectorAll('.input-help');

    helpElements.forEach(element => {
        element.addEventListener('click', function () {
            // You can add tooltip or modal functionality here
        });
    });
}

/**
 * Utility Functions
 */

function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        const messageElement = overlay.querySelector('p');
        if (messageElement) {
            messageElement.textContent = message;
        }
        overlay.style.display = 'flex';
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function showError(message) {
    // Create or update error message
    let errorDiv = document.querySelector('.error-message');

    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = `
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: 500;
        `;

        const calculator = document.querySelector('.mortgage-calculator-wrapper');
        if (calculator) {
            calculator.insertBefore(errorDiv, calculator.firstChild);
        }
    }

    errorDiv.textContent = message;
    errorDiv.style.display = 'block';

    // Auto-hide after 5 seconds
    setTimeout(() => {
        errorDiv.style.display = 'none';
    }, 5000);

    // Don't scroll during state restoration
    if (!isRestoringState) {
        scrollToTop();
    }
}

function scrollToTop() {
    const calculator = document.querySelector('.mortgage-calculator-wrapper');
    if (calculator) {
        calculator.scrollIntoView({ behavior: 'smooth' });
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}


function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[\d\s\-\(\)]{10,}$/;
    return phoneRegex.test(phone);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Keyboard navigation support
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('.btn-next, .btn-prev')) {
        e.target.click();
    }
});

// Prevent form submission on Enter in input fields
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches('input:not([type="submit"])')) {
        e.preventDefault();

        // Move to next input or trigger next step
        const inputs = Array.from(document.querySelectorAll('input, select'));
        const currentIndex = inputs.indexOf(e.target);
        if (currentIndex < inputs.length - 1) {
            inputs[currentIndex + 1].focus();
        }
    }
});

// ============================================================================
// FORM STATE PERSISTENCE
// ============================================================================

/**
 * Save form state to localStorage
 */
function saveFormState() {
    const state = {
        currentStep: currentStep,
        formData: formData,
        timestamp: Date.now()
    };
    
    try {
        localStorage.setItem('mortgageCalculatorState', JSON.stringify(state));
    } catch (e) {
        console.warn('Unable to save form state to localStorage:', e);
    }
}

/**
 * Restore form state from localStorage
 * @return {boolean} Returns true if state was restored, false otherwise
 */
function restoreFormState() {
    try {
        const savedState = localStorage.getItem('mortgageCalculatorState');
        if (!savedState) return false;
        
        const state = JSON.parse(savedState);
        
        // Check if state is recent (less than 24 hours old)
        const maxAge = 24 * 60 * 60 * 1000; // 24 hours in milliseconds
        if (Date.now() - state.timestamp > maxAge) {
            localStorage.removeItem('mortgageCalculatorState');
            return false;
        }
        
        // Set restoration flag to prevent scroll interference
        isRestoringState = true;
        
        // Restore form data
        if (state.formData) {
            formData = state.formData;
            populateFormFields(state.formData);
        }
        
        // Restore current step immediately without animation
        if (state.currentStep && state.currentStep > 1) {
            currentStep = state.currentStep;
            
            // Show target step instantly without transitions
            showStepInstant(currentStep);
            
            // Update progress bar without animation
            updateProgressBarInstant(currentStep);
            
            // If we have calculations, update the display
            if (state.formData && Object.keys(state.formData).length > 0) {
                // Trigger calculation to update displays immediately
                const targetStep = currentStep === 2 ? 2 : 3;
                sendStepData(currentStep - 1, state.formData)
                    .then(response => {
                        if (response.success && response.data.calculations) {
                            updateCalculationsDisplay(response.data.calculations, targetStep);
                        }
                        // Update loan term display if on step 2 or 3
                        if ((currentStep === 2 || currentStep === 3) && state.formData.loan_term) {
                            updateLoanTermDisplay(state.formData.loan_term);
                        }
                        // Update step clickability and clear restoration flag
                        setTimeout(() => {
                            updateStepClickability();
                            isRestoringState = false;
                        }, 100);
                    })
                    .catch(error => {
                        console.error('Error restoring calculations:', error);
                        isRestoringState = false;
                    });
            } else {
                // Update step clickability and clear restoration flag
                setTimeout(() => {
                    updateStepClickability();
                    isRestoringState = false;
                }, 100);
            }
            
            return true;
        }
        
        // Clear restoration flag if no step restoration needed
        isRestoringState = false;
        
        // Update step clickability for initial load
        updateStepClickability();
        
        return false;
        
    } catch (e) {
        console.warn('Unable to restore form state from localStorage:', e);
        localStorage.removeItem('mortgageCalculatorState');
        
        // Update step clickability for initial load
        updateStepClickability();
        
        return false;
    }
}

/**
 * Populate form fields with saved data
 */
function populateFormFields(data) {
    Object.keys(data).forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            if (field.type === 'checkbox') {
                field.checked = !!data[fieldName];
            } else if (field.getAttribute('inputmode') === 'numeric') {
                // For formatted numeric inputs, set both raw and formatted values
                const rawValue = data[fieldName];
                field.setAttribute('data-raw-value', rawValue);
                field.value = addThousandSeparators(rawValue);
            } else {
                field.value = data[fieldName];
            }
        }
    });
}

/**
 * Clear saved form state
 */
function clearFormState() {
    try {
        localStorage.removeItem('mortgageCalculatorState');
    } catch (e) {
        console.warn('Unable to clear form state:', e);
    }
}

/**
 * Reset calculator form - clears all data and returns to step 1
 */
function resetCalculatorForm() {
    if (confirm(mortgageAjax.i18n.confirmReset)) {
        // Clear localStorage
        clearFormState();
        
        // Reset global variables
        currentStep = 1;
        formData = {};
        isSubmitting = false;
        isRestoringState = false;
        
        // Clear all form fields
        const forms = document.querySelectorAll('.step-form');
        forms.forEach(form => {
            form.reset();
            // Clear any validation errors
            const errorFields = form.querySelectorAll('.error');
            errorFields.forEach(field => {
                clearFieldError(field);
            });
        });
        
        // Clear all calculation displays
        const calculationElements = [
            'monthly-payment', 'principal-interest', 'property-tax', 'insurance',
            'selected-loan-term', 'tna-rate', 'tea-rate', 'cftea-rate',
            'final-monthly-payment', 'final-pi', 'final-tax', 'final-insurance',
            'final-pmi', 'summary-loan-amount', 'total-interest', 'debt-to-income',
            'final-tna-rate', 'final-tea-rate', 'final-cftea-rate'
        ];
        
        calculationElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = element.id.includes('rate') ? '-' : '0';
            }
        });
        
        // Remove any dynamic UVA info elements
        const uvaInfo = document.getElementById('uva-info');
        if (uvaInfo) uvaInfo.remove();
        
        const uvaDetails = document.getElementById('uva-details');
        if (uvaDetails) uvaDetails.remove();
        
        // Return to step 1
        showStep(1);
        updateProgressBar(1);
        
        // Scroll to top
        scrollToTop();
        
        // Show confirmation message
        showSuccess(mortgageAjax.i18n.formReset);
    }
}

/**
 * Show success message (brief notification)
 */
function showSuccess(message) {
    // Create or update success message
    let successDiv = document.querySelector('.success-notification');

    if (!successDiv) {
        successDiv = document.createElement('div');
        successDiv.className = 'success-notification';
        successDiv.style.cssText = `
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: 500;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        `;

        document.body.appendChild(successDiv);
    }

    successDiv.textContent = message;
    successDiv.style.display = 'block';

    // Auto-hide after 3 seconds
    setTimeout(() => {
        successDiv.style.display = 'none';
    }, 3000);
}