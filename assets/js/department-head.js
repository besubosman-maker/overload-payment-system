// department-head.js - Enhanced dashboard functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dashboard components
    initDashboard();
    initRequestCards();
    initFormValidations();
    initNotifications();
    initStatsCards();
    initSidebarInteractions();
    initQuickActions();
});

function initDashboard() {
    console.log('Department Head Dashboard initialized');
    
    // Add active state to current page in sidebar
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.sidebar-menu a');
    
    menuLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage && linkPage.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    // Add ripple effect to buttons
    initRippleEffects();
    
    // Initialize tooltips
    initTooltips();
}

function initRequestCards() {
    const requestCards = document.querySelectorAll('.request-card');
    
    requestCards.forEach(card => {
        // Add hover effect
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
        
        // Add click animation
        card.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('textarea')) {
                this.classList.add('clicked');
                setTimeout(() => this.classList.remove('clicked'), 300);
            }
        });
    });
}

function initFormValidations() {
    const forms = document.querySelectorAll('.approval-form');
    
    forms.forEach(form => {
        const textarea = form.querySelector('.comment-input');
        const charCounter = document.createElement('div');
        charCounter.className = 'char-counter';
        charCounter.style.cssText = 'text-align: right; font-size: 12px; color: #6c757d; margin-top: 5px;';
        form.insertBefore(charCounter, textarea.nextSibling);
        
        // Character counter
        textarea.addEventListener('input', function() {
            const count = this.value.length;
            charCounter.textContent = `${count}/500 characters`;
            
            if (count > 500) {
                charCounter.style.color = 'var(--danger-color)';
            } else if (count > 400) {
                charCounter.style.color = 'var(--warning-color)';
            } else {
                charCounter.style.color = 'var(--success-color)';
            }
        });
        
        // Trigger initial count
        textarea.dispatchEvent(new Event('input'));
        
        // Auto-resize textarea
        textarea.addEventListener('input', autoResizeTextarea);
        
        // Submit validation
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const textarea = this.querySelector('.comment-input');
            const submitBtn = e.submitter;
            const action = submitBtn.value;
            
            if (!textarea.value.trim()) {
                showNotification('Please provide comments for your decision.', 'error');
                textarea.focus();
                return;
            }
            
            if (textarea.value.length > 500) {
                showNotification('Comments should not exceed 500 characters.', 'error');
                textarea.focus();
                return;
            }
            
            // Show confirmation for reject
            if (action === 'rejected') {
                if (!confirm('Are you sure you want to REJECT this request?\n\nThis action cannot be undone.')) {
                    return;
                }
            }
            
            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Submit form after delay (simulate API call)
            setTimeout(() => {
                this.submit();
            }, 1500);
        });
    });
}

function autoResizeTextarea() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
}

function initNotifications() {
    // Auto-remove notifications after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.notification').forEach(notification => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        });
    }, 5000);
    
    // Add close button functionality
    document.querySelectorAll('.notification-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const notification = this.closest('.notification');
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        });
    });
}

function initStatsCards() {
    // Animate stats cards on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
                
                // Animate numbers if present
                const numberElement = entry.target.querySelector('h3');
                if (numberElement && !isNaN(parseInt(numberElement.textContent))) {
                    animateNumber(numberElement);
                }
            }
        });
    }, { threshold: 0.2 });
    
    document.querySelectorAll('.stat-card').forEach(card => {
        observer.observe(card);
    });
}

function animateNumber(element) {
    const target = parseInt(element.textContent);
    const duration = 1500;
    const step = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 16);
}

function initSidebarInteractions() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.createElement('button');
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    toggleBtn.className = 'sidebar-toggle';
    toggleBtn.style.cssText = `
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--primary-color);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s;
    `;
    
    document.body.appendChild(toggleBtn);
    
    // Toggle sidebar on mobile
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        this.innerHTML = sidebar.classList.contains('open') ? 
            '<i class="fas fa-times"></i>' : 
            '<i class="fas fa-bars"></i>';
    });
    
    // Show/hide toggle button based on screen size
    function checkScreenSize() {
        if (window.innerWidth <= 768) {
            toggleBtn.style.display = 'flex';
            sidebar.classList.remove('open');
        } else {
            toggleBtn.style.display = 'none';
            sidebar.classList.add('open');
        }
    }
    
    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    
    // Add active states
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function() {
            document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // Close sidebar on mobile after click
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    });
}

function initQuickActions() {
    const quickActionBtns = document.querySelectorAll('.quick-actions button');
    
    quickActionBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const action = this.textContent.trim();
            
            if (action.includes('Refresh')) {
                e.preventDefault();
                refreshDashboard();
            }
        });
    });
}

function refreshDashboard() {
    const refreshBtn = document.querySelector('button[onclick*="refresh"]');
    if (refreshBtn) {
        const originalHTML = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        refreshBtn.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}

function initRippleEffects() {
    const buttons = document.querySelectorAll('button, .btn-primary, .btn-success, .btn-warning, .btn-danger');
    
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
            `;
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add ripple animation
    if (!document.getElementById('ripple-animation')) {
        const style = document.createElement('style');
        style.id = 'ripple-animation';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
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
            
            // Show tooltip
            setTimeout(() => {
                tooltip.style.opacity = '1';
                tooltip.style.transform = 'translateY(0)';
            }, 10);
            
            // Store reference
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

// Export functionality
function exportRequests(format = 'csv') {
    const requests = document.querySelectorAll('.request-card');
    let data = [];
    
    requests.forEach((card, index) => {
        const teacher = card.querySelector('.teacher-info h4').textContent;
        const department = card.querySelector('.department-badge').textContent;
        const course = card.querySelector('.info-item:nth-child(1) .info-value').textContent;
        const credits = card.querySelector('.info-item:nth-child(2) .info-value').textContent;
        
        data.push({
            'Request #': index + 1,
            'Teacher': teacher,
            'Department': department,
            'Course': course,
            'Credits': credits
        });
    });
    
    if (format === 'csv') {
        exportToCSV(data, 'pending-requests.csv');
    } else if (format === 'pdf') {
        // In real implementation, generate PDF
        showNotification('PDF export feature would be implemented here', 'info');
    }
}

function exportToCSV(data, filename) {
    const headers = Object.keys(data[0]);
    const csvRows = [
        headers.join(','),
        ...data.map(row => headers.map(header => JSON.stringify(row[header])).join(','))
    ];
    
    const csvString = csvRows.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.href = url;
    a.download = filename;
    a.click();
    
    window.URL.revokeObjectURL(url);
    showNotification('CSV file downloaded successfully!', 'success');
}

// Show notification function
function showNotification(message, type = 'success') {
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
        <button class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const container = document.querySelector('.main-content');
    container.insertBefore(notification, container.firstChild);
    
    // Initialize close button
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Dashboard statistics
function loadDashboardStats() {
    // In a real implementation, fetch from API
    const stats = [
        { icon: 'fa-clock', label: 'Pending Reviews', value: '12', color: '#ffc107' },
        { icon: 'fa-check-circle', label: 'Approved Today', value: '5', color: '#28a745' },
        { icon: 'fa-chart-line', label: 'Monthly Average', value: '42', color: '#004080' },
        { icon: 'fa-users', label: 'Teachers in Dept', value: '28', color: '#17a2b8' }
    ];
    
    const statsContainer = document.getElementById('dashboard-stats');
    if (statsContainer) {
        let statsHTML = '<div class="stats-grid">';
        stats.forEach(stat => {
            statsHTML += `
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, ${stat.color}20, ${stat.color}40); color: ${stat.color};">
                        <i class="fas ${stat.icon}"></i>
                    </div>
                    <div class="stat-info">
                        <h3>${stat.value}</h3>
                        <p>${stat.label}</p>
                    </div>
                </div>
            `;
        });
        statsHTML += '</div>';
        statsContainer.innerHTML = statsHTML;
        
        // Re-initialize stats cards
        initStatsCards();
    }
}

// Initialize dashboard stats on page load
if (document.getElementById('dashboard-stats')) {
    loadDashboardStats();
}

// Add global keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + R to refresh
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        refreshDashboard();
    }
    
    // Escape to close all notifications
    if (e.key === 'Escape') {
        document.querySelectorAll('.notification').forEach(notification => {
            notification.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => notification.remove(), 300);
        });
    }
});

// Theme switcher (optional)
function initThemeSwitcher() {
    const themeToggle = document.createElement('button');
    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    themeToggle.className = 'theme-toggle';
    themeToggle.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        background: var(--primary-color);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s;
    `;
    
    document.body.appendChild(themeToggle);
    
    themeToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-theme');
        this.innerHTML = document.body.classList.contains('dark-theme') ? 
            '<i class="fas fa-sun"></i>' : 
            '<i class="fas fa-moon"></i>';
    });
}

// Uncomment to enable theme switcher
// initThemeSwitcher();