// submit-overload.js - Enhanced JavaScript for overload submission

document.addEventListener('DOMContentLoaded', function() {
    initSubmitForm();
    initFileUpload();
    initCreditCalculator();
    initFormValidation();
    initAutoSave();
    initFormAnimations();
});

function initSubmitForm() {
    console.log('Submit Overload Form initialized');
    
    // Add active state to current page in navigation
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(link => {
        if (link.getAttribute('href')?.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    // Add ripple effect to buttons
    initRippleEffects();
    
    // Initialize tooltips
    initTooltips();
}

function initFileUpload() {
    const fileInput = document.getElementById('document_file');
    const fileUploadWrapper = document.querySelector('.file-upload-wrapper');
    const fileList = document.querySelector('.file-list');
    const maxFiles = 5;
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!fileInput) return;
    
    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadWrapper.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadWrapper.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadWrapper.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        fileUploadWrapper.classList.add('dragover');
    }
    
    function unhighlight() {
        fileUploadWrapper.classList.remove('dragover');
    }
    
    // Handle dropped files
    fileUploadWrapper.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }
    
    // Handle selected files
    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });
    
    function handleFiles(files) {
        const currentFileCount = fileList.children.length;
        
        if (currentFileCount + files.length > maxFiles) {
            showNotification(`Maximum ${maxFiles} files allowed. You can only add ${maxFiles - currentFileCount} more files.`, 'error');
            return;
        }
        
        Array.from(files).forEach(file => {
            if (file.size > maxSize) {
                showNotification(`File "${file.name}" exceeds 10MB limit`, 'error');
                return;
            }
            
            addFileToList(file);
        });
        
        // Reset file input
        fileInput.value = '';
    }
    
    function addFileToList(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item fade-in';
        
        const fileSize = formatFileSize(file.size);
        
        fileItem.innerHTML = `
            <div class="file-info">
                <i class="fas fa-file-pdf file-icon"></i>
                <div>
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${fileSize}</div>
                </div>
            </div>
            <button type="button" class="file-remove" onclick="removeFile(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        fileList.appendChild(fileItem);
        updateFileCount();
    }
    
    function updateFileCount() {
        const count = fileList.children.length;
        const fileCountElement = document.getElementById('file-count');
        if (fileCountElement) {
            fileCountElement.textContent = `${count} file${count !== 1 ? 's' : ''} selected`;
        }
    }
    
    window.removeFile = function(button) {
        const fileItem = button.closest('.file-item');
        fileItem.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => {
            fileItem.remove();
            updateFileCount();
        }, 300);
    };
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function initCreditCalculator() {
    const creditHourInput = document.getElementById('credit_hour');
    const academicRankInput = document.getElementById('academic_rank');
    const calculatorElement = document.getElementById('credit-calculator');
    
    if (!creditHourInput || !academicRankInput || !calculatorElement) return;
    
    // Rate per credit based on academic rank (example rates)
    const rates = {
        'assistant_lecturer': 250,
        'lecturer': 165,
        'assistant_professor': 200,
        'associate_professor': 150,
        'professor': 300
    };
    
    function calculatePayment() {
        const creditHours = parseInt(creditHourInput.value) || 0;
        const rank = academicRankInput.value;
        const rate = rates[rank] || 0;
        const total = creditHours * rate;
        
        updateCalculatorDisplay(creditHours, rate, total);
    }
    
    function updateCalculatorDisplay(creditHours, rate, total) {
        calculatorElement.innerHTML = `
            <div class="credit-calculator">
                <div class="calculator-header">
                    <h5><i class="fas fa-calculator"></i> Payment Calculator</h5>
                    <span class="status-indicator status-pending">Estimate</span>
                </div>
                <div class="calculator-results">
                    <div class="calculator-item">
                        <div class="calculator-label">Credit Hours</div>
                        <div class="calculator-value">${creditHours}</div>
                    </div>
                    <div class="calculator-item">
                        <div class="calculator-label">Rate per Credit</div>
                        <div class="calculator-value">${rate.toLocaleString()} ETB</div>
                    </div>
                    <div class="calculator-item">
                        <div class="calculator-label">Estimated Payment</div>
                        <div class="calculator-value">${total.toLocaleString()} ETB</div>
                    </div>
                </div>
                <p style="margin-top: 15px; color: #666; font-size: 0.85rem; text-align: center;">
                    <i class="fas fa-info-circle"></i> This is an estimate. Final amount subject to approval.
                </p>
            </div>
        `;
        
        // Add animation
        calculatorElement.classList.add('fade-in');
        setTimeout(() => calculatorElement.classList.remove('fade-in'), 500);
    }
    
    // Calculate on input change
    creditHourInput.addEventListener('input', calculatePayment);
    academicRankInput.addEventListener('change', calculatePayment);
    
    // Initial calculation
    calculatePayment();
}

function initFormValidation() {
    const form = document.getElementById('overload-form');
    if (!form) return;
    
    // Real-time validation
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearError);
    });
    
    // Character counter for justification
    const justificationTextarea = form.querySelector('textarea[name="justification"]');
    if (justificationTextarea) {
        const charCounter = document.createElement('div');
        charCounter.className = 'char-counter';
        charCounter.textContent = '0/500 characters';
        justificationTextarea.parentNode.appendChild(charCounter);
        
        justificationTextarea.addEventListener('input', function() {
            const count = this.value.length;
            charCounter.textContent = `${count}/500 characters`;
            
            if (count > 500) {
                charCounter.classList.add('error');
                charCounter.classList.remove('warning');
            } else if (count > 400) {
                charCounter.classList.add('warning');
                charCounter.classList.remove('error');
            } else {
                charCounter.classList.remove('warning', 'error');
            }
        });
    }
    
    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            showNotification('Please correct the errors in the form.', 'error');
            return;
        }
        
        showLoading(true);
        
        // Simulate API call
        setTimeout(() => {
            showLoading(false);
            showNotification('Overload request submitted successfully!', 'success');
            
            // Redirect to dashboard after 2 seconds
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        }, 2000);
    });
}

function validateField() {
    const value = this.value.trim();
    const fieldName = this.getAttribute('name');
    let isValid = true;
    let message = '';
    
    switch (fieldName) {
        case 'course_name':
            if (value.length < 3) {
                message = 'Course name must be at least 3 characters';
                isValid = false;
            }
            break;
            
        case 'course_code':
            if (!/^[A-Z]{3}\d{3}$/.test(value)) {
                message = 'Course code must be in format ABC123';
                isValid = false;
            }
            break;
            
        case 'credit_hour':
            const creditHour = parseInt(value);
            if (isNaN(creditHour) || creditHour < 1 || creditHour > 6) {
                message = 'Credit hours must be between 1 and 6';
                isValid = false;
            }
            break;
            
        case 'justification':
            if (value.length < 20) {
                message = 'Please provide a more detailed justification (at least 20 characters)';
                isValid = false;
            } else if (value.length > 500) {
                message = 'Justification must not exceed 500 characters';
                isValid = false;
            }
            break;
            
        case 'semester':
            if (!value) {
                message = 'Please select a semester';
                isValid = false;
            }
            break;
            
        case 'academic_year':
            const yearRegex = /^\d{4}-\d{4}$/;
            if (!yearRegex.test(value)) {
                message = 'Academic year must be in format YYYY-YYYY';
                isValid = false;
            }
            break;
    }
    
    if (!isValid) {
        showFieldError(this, message);
    } else {
        clearFieldError(this);
        showFieldSuccess(this);
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('error');
    field.classList.remove('success');
    
    let errorElement = field.parentNode.querySelector('.error-message');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        field.parentNode.appendChild(errorElement);
    }
    errorElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
}

function showFieldSuccess(field) {
    field.classList.add('success');
    field.classList.remove('error');
    
    let successElement = field.parentNode.querySelector('.success-message');
    if (!successElement) {
        successElement = document.createElement('div');
        successElement.className = 'success-message';
        field.parentNode.appendChild(successElement);
    }
    successElement.innerHTML = '<i class="fas fa-check-circle"></i> Looks good!';
    
    setTimeout(() => {
        successElement.remove();
        field.classList.remove('success');
    }, 3000);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) errorElement.remove();
}

function clearError() {
    this.classList.remove('error');
    const errorElement = this.parentNode.querySelector('.error-message');
    if (errorElement) errorElement.remove();
}

function validateForm() {
    let isValid = true;
    const requiredFields = document.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        }
    });
    
    return isValid;
}

function initAutoSave() {
    const form = document.getElementById('overload-form');
    if (!form) return;
    
    // Auto-save every 30 seconds
    setInterval(() => {
        if (form.checkValidity()) {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Save to localStorage
            localStorage.setItem('overload_draft', JSON.stringify(data));
            console.log('Auto-saved draft');
        }
    }, 30000);
    
    // Load draft on page load
    const draft = localStorage.getItem('overload_draft');
    if (draft) {
        if (confirm('You have a saved draft. Would you like to load it?')) {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = data[key];
                }
            });
            showNotification('Draft loaded successfully!', 'info');
        }
    }
}

function initFormAnimations() {
    // Animate form sections on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('slide-in');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.form-group, .form-header, .file-upload-wrapper').forEach(el => {
        observer.observe(el);
    });
}

function showLoading(show) {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
        overlay.classList.toggle('active', show);
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                          type === 'error' ? 'fa-exclamation-circle' : 
                          type === 'warning' ? 'fa-exclamation-triangle' : 
                          'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add styles if not present
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-width: 300px;
                max-width: 400px;
                z-index: 10000;
                animation: slideInRight 0.3s ease;
            }
            .notification-success {
                background: linear-gradient(135deg, #d4edda, #c3e6cb);
                color: #155724;
                border-left: 4px solid #28a745;
            }
            .notification-error {
                background: linear-gradient(135deg, #f8d7da, #f5c6cb);
                color: #721c24;
                border-left: 4px solid #dc3545;
            }
            .notification-info {
                background: linear-gradient(135deg, #d1ecf1, #bee5eb);
                color: #0c5460;
                border-left: 4px solid #17a2b8;
            }
            .notification-warning {
                background: linear-gradient(135deg, #fff3cd, #ffeaa7);
                color: #856404;
                border-left: 4px solid #ffc107;
            }
            .notification-close {
                background: none;
                border: none;
                color: inherit;
                cursor: pointer;
                margin-left: 15px;
            }
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
    
    // Add slideOutRight animation
    if (!document.getElementById('slideOutRight-animation')) {
        const style = document.createElement('style');
        style.id = 'slideOutRight-animation';
        style.textContent = `
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function initRippleEffects() {
    const buttons = document.querySelectorAll('button, .btn-submit, .btn-cancel, .btn-reset');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: scale(0);
                animation: ripple 0.6s linear;
                width: 100px;
                height: 100px;
                left: ${x - 50}px;
                top: ${y - 50}px;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: fixed;
                background: var(--dark-color);
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                z-index: 10000;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transform: translateY(10px);
                transition: all 0.3s;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 10}px`;
            
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            }, 10);
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.style.opacity = '0';
                this._tooltip.style.transform = 'translateY(10px)';
                setTimeout(() => this._tooltip.remove(), 300);
            }
        });
    });
}

// Reset form
window.resetForm = function() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('overload-form').reset();
        const fileList = document.querySelector('.file-list');
        if (fileList) fileList.innerHTML = '';
        showNotification('Form reset successfully', 'info');
    }
};

// Save draft
window.saveDraft = function() {
    const form = document.getElementById('overload-form');
    if (form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        localStorage.setItem('overload_draft', JSON.stringify(data));
        showNotification('Draft saved successfully!', 'success');
    }
};

// Clear draft
window.clearDraft = function() {
    if (confirm('Are you sure you want to delete the saved draft?')) {
        localStorage.removeItem('overload_draft');
        showNotification('Draft deleted', 'info');
    }
};