/**
 * Mortgage Calculator JavaScript
 * Vanilla JavaScript for complete functionality
 * Save as: /js/mortgage-calculator.js in your theme
 */

// Global variables
let currentStep = 1;
let formData = {};
let isSubmitting = false;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeMortgageCalculator();
});

/**
 * Initialize the calculator
 */
function initializeMortgageCalculator() {
    // Hide form during restoration to prevent flickering
    const wrapper = document.querySelector('.mortgage-calculator-wrapper');
    if (wrapper) {
        wrapper.classList.add('loading');
    }
    
    // Restore saved state if it exists
    const wasRestored = restoreFormState();
    
    // Add event listeners for real-time calculations
    addInputEventListeners();

    // Add form validation
    addFormValidation();

    // Format currency inputs
    formatCurrencyInputs();

    // Initialize tooltips or help text
    initializeHelpSystem();
    
    // Show form after initialization with smooth transition
    if (wrapper) {
        if (wasRestored) {
            // If state was restored, wait a bit longer for calculations
            setTimeout(() => {
                wrapper.classList.remove('loading');
            }, 200);
        } else {
            // If no state to restore, show immediately
            setTimeout(() => {
                wrapper.classList.remove('loading');
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

                // Move to next step
                showStep(step + 1);
                updateProgressBar(step + 1);
                currentStep = step + 1;

                // Save form state
                saveFormState();

                // Scroll to top
                scrollToTop();
            } else {
                showError('An error occurred. Please try again.');
            }
        })
        .catch(error => {
            hideLoading();
            showError('Network error. Please check your connection and try again.');
            console.error('Error:', error);
        });
}

/**
 * Navigate to previous step
 */
function prevStep(step) {
    if (step > 1) {
        showStep(step - 1);
        updateProgressBar(step - 1);
        currentStep = step - 1;
        
        // Save form state
        saveFormState();
        
        scrollToTop();
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
            showFieldError(field, 'This field is required');
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
                showFieldError(field, 'Please enter a valid email address');
                return false;
            }
            break;

        case 'tel':
            if (!isValidPhone(value)) {
                showFieldError(field, 'Please enter a valid phone number');
                return false;
            }
            break;

        case 'number':
            const numValue = parseFloat(value);
            const min = parseFloat(field.min);
            const max = parseFloat(field.max);

            if (isNaN(numValue)) {
                showFieldError(field, 'Please enter a valid number');
                return false;
            }

            if (min && numValue < min) {
                showFieldError(field, `Value must be at least ${formatCurrency(min)}`);
                return false;
            }

            if (max && numValue > max) {
                showFieldError(field, `Value cannot exceed ${formatCurrency(max)}`);
                return false;
            }

            // Specific validation for certain fields
            if (fieldName === 'down_payment') {
                const homeValue = parseFloat(document.getElementById('home_value')?.value || 0);
                if (homeValue && numValue > homeValue) {
                    showFieldError(field, 'Down payment cannot exceed home value');
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
            data[key] = value;
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
    }

    // Update step 3 calculations (detailed estimate based on Step 1 + Step 2 data)
    if (targetStep === 3) {
        updateElement('final-monthly-payment', formatNumber(calculations.monthly_payment));
        updateElement('final-pi', formatCurrency(calculations.principal_interest));
        updateElement('final-tax', formatCurrency(calculations.property_tax));
        updateElement('final-insurance', formatCurrency(calculations.insurance));
        updateElement('final-pmi', formatCurrency(calculations.pmi || 0));
        updateElement('summary-loan-amount', formatCurrency(calculations.loan_amount));
        updateElement('estimated-rate', calculations.interest_rate + '%');
        updateElement('total-interest', formatCurrency(calculations.total_interest));
        updateElement('debt-to-income', (calculations.debt_to_income_ratio || 0) + '%');
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
        showError('Please accept the Terms of Service to continue.');
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
                showError('There was an error submitting your application. Please try again.');
            }
        })
        .catch(error => {
            hideLoading();
            isSubmitting = false;
            showError('Network error. Please check your connection and try again.');
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

        // Scroll to top
        scrollToTop();
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
        if (target.type === 'number' && target.closest('.input-wrapper')) {
            // You can add real-time formatting here if needed
        }

        // Trigger real-time calculations based on current step
        if (currentStep === 1 && ['loan_amount', 'loan_term'].includes(target.name)) {
            // Step 1 inputs update Step 2 display
            debounce(performRealTimeCalculation, 500)();
        } else if (currentStep === 2 && ['home_value', 'down_payment', 'property_location', 'monthly_income', 'property_use'].includes(target.name)) {
            // Step 2 inputs update Step 3 display
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
    const currencyInputs = document.querySelectorAll('input[type="number"]');

    currencyInputs.forEach(input => {
        // Add thousand separators on blur (optional)
        input.addEventListener('blur', function () {
            if (this.value) {
                // You can add formatting logic here
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

    scrollToTop();
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

function formatNumber(number) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(number);
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
        
        // Restore form data
        if (state.formData) {
            formData = state.formData;
            populateFormFields(state.formData);
        }
        
        // Restore current step immediately without animation
        if (state.currentStep && state.currentStep > 1) {
            currentStep = state.currentStep;
            
            // Hide all steps first
            const allSteps = document.querySelectorAll('.step');
            allSteps.forEach(step => {
                step.style.display = 'none';
            });
            
            // Show target step without transition
            const targetStepElement = document.getElementById(`step-${currentStep}`);
            if (targetStepElement) {
                targetStepElement.style.display = 'block';
            }
            
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
                    })
                    .catch(error => {
                        console.error('Error restoring calculations:', error);
                    });
            }
            
            return true;
        }
        
        return false;
        
    } catch (e) {
        console.warn('Unable to restore form state from localStorage:', e);
        localStorage.removeItem('mortgageCalculatorState');
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