// Dashboard specific JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard
    initializeDashboard();
    
    // Auto-refresh notifications every 5 minutes
    setInterval(checkNotifications, 5 * 60 * 1000);
    
    // Mark notifications as read when clicked
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.notificationId;
            if (notificationId && !this.classList.contains('read')) {
                markNotificationAsRead(notificationId);
                this.classList.add('read');
                this.style.opacity = '0.7';
            }
        });
    });
    
    // Service usage progress bars animation
    animateProgressBars();
    
    // Real-time clock
    updateClock();
    setInterval(updateClock, 1000);
});

function initializeDashboard() {
    // Check for any pending alerts
    checkPaymentStatus();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Setup keyboard shortcuts
    setupKeyboardShortcuts();
}

function animateProgressBars() {
    const progressBars = document.querySelectorAll('.usage-progress');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 500);
    });
}

function checkPaymentStatus() {
    const paymentStatus = document.querySelector('.stat-card.danger, .stat-card.warning');
    if (paymentStatus) {
        const statusText = paymentStatus.querySelector('.stat-number').textContent;
        if (statusText.includes('Overdue')) {
            showToast('Payment is overdue. Please contact administration.', 'error');
        } else if (statusText.includes('days')) {
            showToast('Payment due soon. Please prepare for payment.', 'warning');
        }
    }
}

function markNotificationAsRead(notificationId) {
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationCount();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function checkNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                updateNotificationsList(data.notifications);
                updateNotificationCount(data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
}

function updateNotificationsList(notifications) {
    const notificationsList = document.querySelector('.notifications-list');
    if (notificationsList && notifications.length > 0) {
        // Add new notifications that aren't already displayed
        notifications.forEach(notification => {
            const existingNotification = document.querySelector(`[data-notification-id="${notification.id}"]`);
            if (!existingNotification) {
                const notificationElement = createNotificationElement(notification);
                notificationsList.insertBefore(notificationElement, notificationsList.firstChild);
            }
        });
    }
}

function createNotificationElement(notification) {
    const div = document.createElement('div');
    div.className = `notification-item ${notification.type.toLowerCase()}`;
    div.dataset.notificationId = notification.id;
    div.innerHTML = `
        <div class="notification-content">
            <strong>${notification.title}</strong>
            <p>${notification.message}</p>
            <small>${formatDateTime(notification.created_at)}</small>
        </div>
    `;
    return div;
}

function updateNotificationCount(count = 0) {
    const countElement = document.querySelector('.stat-card .stat-number');
    if (countElement && countElement.closest('.stat-card').querySelector('.stat-label').textContent.includes('Notifications')) {
        countElement.textContent = count;
    }
}

function updateClock() {
    const clockElements = document.querySelectorAll('.current-time');
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit'
    });
    
    clockElements.forEach(element => {
        element.textContent = timeString;
    });
}

function initializeTooltips() {
    // Simple tooltip implementation
    const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', showTooltip);
        trigger.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(event) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = event.target.dataset.tooltip;
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
        z-index: 1000;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    setTimeout(() => tooltip.style.opacity = '1', 10);
    
    event.target._tooltip = tooltip;
}

function hideTooltip(event) {
    if (event.target._tooltip) {
        event.target._tooltip.remove();
        delete event.target._tooltip;
    }
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + H for Home/Dashboard
        if ((event.ctrlKey || event.metaKey) && event.key === 'h') {
            event.preventDefault();
            window.location.href = 'dashboard.php';
        }
        
        // Ctrl/Cmd + M for Messages
        if ((event.ctrlKey || event.metaKey) && event.key === 'm') {
            event.preventDefault();
            window.location.href = 'communication.php';
        }
        
        // Ctrl/Cmd + S for Services
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            window.location.href = 'services.php';
        }
        
        // Escape to close modals
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal, .popup');
    modals.forEach(modal => {
        if (modal.style.display === 'block' || modal.classList.contains('show')) {
            modal.style.display = 'none';
            modal.classList.remove('show');
        }
    });
}

// Service request functions
function requestService(serviceId, serviceName) {
    const modal = createServiceRequestModal(serviceId, serviceName);
    document.body.appendChild(modal);
    modal.style.display = 'block';
}

function createServiceRequestModal(serviceId, serviceName) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    modal.innerHTML = `
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3>Request ${serviceName}</h3>
            <form id="serviceRequestForm">
                <div class="form-group">
                    <label>Preferred Date:</label>
                    <input type="date" name="preferred_date" required min="${new Date().toISOString().split('T')[0]}">
                </div>
                <div class="form-group">
                    <label>Special Notes:</label>
                    <textarea name="notes" rows="3" placeholder="Any special requests or notes..."></textarea>
                </div>
                <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="this.closest('.modal').remove()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    `;
    
    const form = modal.querySelector('#serviceRequestForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitServiceRequest(serviceId, new FormData(form));
        modal.remove();
    });
    
    return modal;
}

function submitServiceRequest(serviceId, formData) {
    const requestData = {
        service_id: serviceId,
        preferred_date: formData.get('preferred_date'),
        notes: formData.get('notes')
    };
    
    fetch('api/service_request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Service request submitted successfully!', 'success');
            // Refresh service usage if on services page
            if (window.location.pathname.includes('services.php')) {
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            showToast(data.message || 'Failed to submit service request', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting service request:', error);
        showToast('Error submitting service request', 'error');
    });
}

// Utility functions
function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString('en-US', {
        hour12: true,
        hour: '2-digit',
        minute: '2-digit'
    });
}

function refreshPage() {
    location.reload();
}

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'dashboard.php';
    }
}

// Export functions for global use
window.requestService = requestService;
window.markNotificationAsRead = markNotificationAsRead;
window.refreshPage = refreshPage;
window.goBack = goBack;
