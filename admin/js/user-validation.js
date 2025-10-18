/**
 * Universal User Validation JavaScript Library
 * Provides real-time validation for all user types
 */

// Validation CSS styles
const validationStyles = `
    .validation-message {
        font-size: 0.8rem;
        margin-top: 5px;
        padding: 5px 8px;
        border-radius: 4px;
        display: none;
    }
    
    .validation-message.checking {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }
    
    .validation-message.available {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .validation-message.unavailable {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .validation-message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;

// Inject styles if not already present
if (!document.getElementById('user-validation-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'user-validation-styles';
    styleSheet.textContent = validationStyles;
    document.head.appendChild(styleSheet);
}

/**
 * Generic validation function
 */
function validateField(fieldId, validationId, action, additionalData = {}) {
    const field = document.getElementById(fieldId);
    const validationDiv = document.getElementById(validationId);
    
    if (!field || !validationDiv) {
        console.error(`Field ${fieldId} or validation ${validationId} not found`);
        return;
    }
    
    const value = field.value.trim();
    
    // Clear previous validation
    validationDiv.innerHTML = '';
    validationDiv.className = 'validation-message';
    field.style.borderColor = '#ddd';
    field.style.backgroundColor = '';
    
    if (!value) {
        return;
    }
    
    // Show checking message
    validationDiv.innerHTML = '🔍 Checking...';
    validationDiv.className = 'validation-message checking';
    validationDiv.style.display = 'block';
    field.style.borderColor = '#ffc107';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', action);
    formData.append(getFieldName(action), value);
    
    // Add additional data (like exclude_id, user_type)
    Object.keys(additionalData).forEach(key => {
        formData.append(key, additionalData[key]);
    });
    
    // Make AJAX request
    fetch('validate_user_data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.available) {
            validationDiv.innerHTML = '✅ ' + data.message;
            validationDiv.className = 'validation-message available';
            field.style.borderColor = '#28a745';
            field.style.backgroundColor = '#f8fff9';
        } else {
            validationDiv.innerHTML = '❌ ' + data.message;
            validationDiv.className = 'validation-message unavailable';
            field.style.borderColor = '#dc3545';
            field.style.backgroundColor = '#fff5f5';
            field.style.animation = 'shake 0.5s ease-in-out';
        }
        validationDiv.style.display = 'block';
    })
    .catch(error => {
        console.error('Validation error:', error);
        validationDiv.innerHTML = '⚠️ Unable to validate';
        validationDiv.className = 'validation-message error';
        validationDiv.style.display = 'block';
        field.style.borderColor = '#dc3545';
    });
}

/**
 * Get field name based on action
 */
function getFieldName(action) {
    const fieldMap = {
        'check_username': 'username',
        'check_email': 'email',
        'check_phone': 'phone',
        'check_employee_id': 'employee_id',
        'check_license_number': 'license_number'
    };
    return fieldMap[action] || 'value';
}

/**
 * Debounce function to limit API calls
 */
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

/**
 * Specific validation functions for different field types
 */

// Username validation
function validateUsername(fieldId = 'username', validationId = 'username_validation', excludeId = 0) {
    const additionalData = excludeId > 0 ? { exclude_id: excludeId } : {};
    validateField(fieldId, validationId, 'check_username', additionalData);
}

// Email validation
function validateEmail(fieldId = 'email', validationId = 'email_validation', excludeId = 0) {
    const additionalData = excludeId > 0 ? { exclude_id: excludeId } : {};
    validateField(fieldId, validationId, 'check_email', additionalData);
}

// Phone validation
function validatePhone(fieldId = 'phone', validationId = 'phone_validation', excludeId = 0) {
    const additionalData = excludeId > 0 ? { exclude_id: excludeId } : {};
    validateField(fieldId, validationId, 'check_phone', additionalData);
}

// Employee ID validation
function validateEmployeeId(fieldId = 'employee_id', validationId = 'employee_id_validation', userType = 'staff', excludeId = 0) {
    const additionalData = { user_type: userType };
    if (excludeId > 0) additionalData.exclude_id = excludeId;
    validateField(fieldId, validationId, 'check_employee_id', additionalData);
}

// License number validation (for doctors)
function validateLicenseNumber(fieldId = 'license_number', validationId = 'license_validation', excludeId = 0) {
    const additionalData = excludeId > 0 ? { exclude_id: excludeId } : {};
    validateField(fieldId, validationId, 'check_license_number', additionalData);
}

/**
 * Debounced versions of validation functions
 */
const debouncedValidateUsername = debounce(validateUsername, 500);
const debouncedValidateEmail = debounce(validateEmail, 500);
const debouncedValidatePhone = debounce(validatePhone, 500);
const debouncedValidateEmployeeId = debounce(validateEmployeeId, 500);
const debouncedValidateLicenseNumber = debounce(validateLicenseNumber, 500);

/**
 * Form submission validation
 */
function validateFormSubmission(formId, validationIds = []) {
    let errors = [];
    
    validationIds.forEach(validationId => {
        const validationDiv = document.getElementById(validationId);
        if (validationDiv) {
            if (validationDiv.classList.contains('unavailable')) {
                const fieldName = validationId.replace('_validation', '').replace('_', ' ');
                errors.push(`❌ ${fieldName} has conflicts`);
            }
            if (validationDiv.classList.contains('checking')) {
                const fieldName = validationId.replace('_validation', '').replace('_', ' ');
                errors.push(`⏳ ${fieldName} validation is still in progress`);
            }
        }
    });
    
    if (errors.length > 0) {
        alert('Cannot submit form!\n\n' + errors.join('\n\n') + '\n\nPlease fix the issues above before submitting.');
        return false;
    }
    
    return true;
}

/**
 * Initialize validation for a form
 */
function initializeFormValidation(config) {
    // config should contain field mappings like:
    // {
    //   username: { fieldId: 'username', validationId: 'username_validation' },
    //   email: { fieldId: 'email', validationId: 'email_validation' },
    //   ...
    // }
    
    Object.keys(config).forEach(fieldType => {
        const { fieldId, validationId, userType, excludeId } = config[fieldType];
        const field = document.getElementById(fieldId);
        
        if (field) {
            switch (fieldType) {
                case 'username':
                    field.addEventListener('input', () => debouncedValidateUsername(fieldId, validationId, excludeId || 0));
                    break;
                case 'email':
                    field.addEventListener('input', () => debouncedValidateEmail(fieldId, validationId, excludeId || 0));
                    break;
                case 'phone':
                    field.addEventListener('input', () => debouncedValidatePhone(fieldId, validationId, excludeId || 0));
                    break;
                case 'employee_id':
                    field.addEventListener('input', () => debouncedValidateEmployeeId(fieldId, validationId, userType || 'staff', excludeId || 0));
                    break;
                case 'license_number':
                    field.addEventListener('input', () => debouncedValidateLicenseNumber(fieldId, validationId, excludeId || 0));
                    break;
            }
        }
    });
}
