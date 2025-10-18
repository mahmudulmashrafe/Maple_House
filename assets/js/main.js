// Main JavaScript functionality for Maple House

document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('a[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Contact form handling
    const contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const formData = new FormData(this);
            const name = formData.get('name');
            const email = formData.get('email');
            const subject = formData.get('subject');
            const message = formData.get('message');
            
            // Basic validation
            if (!name || !email || !subject || !message) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
        });
    }
    
    // Display contact form messages
    const urlParams = new URLSearchParams(window.location.search);
    const contactStatus = urlParams.get('contact');
    const contactMsg = urlParams.get('msg');
    
    if (contactStatus && contactMsg) {
        const alertClass = contactStatus === 'success' ? 'alert-success' : 'alert-error';
        const icon = contactStatus === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass}`;
        alertDiv.innerHTML = `<i class="${icon}"></i> ${decodeURIComponent(contactMsg)}`;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.maxWidth = '400px';
        
        document.body.appendChild(alertDiv);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'background: none; border: none; font-size: 1.5rem; position: absolute; top: 5px; right: 10px; cursor: pointer;';
        closeBtn.addEventListener('click', () => alertDiv.remove());
        alertDiv.appendChild(closeBtn);
    }
    
    // Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.backdropFilter = 'blur(10px)';
            } else {
                navbar.style.background = '#fff';
                navbar.style.backdropFilter = 'none';
            }
        });
    }
    
    // Animate stats on scroll
    const statNumbers = document.querySelectorAll('.stat-number');
    const animateStats = () => {
        statNumbers.forEach(stat => {
            const rect = stat.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                const target = parseInt(stat.textContent);
                if (!isNaN(target) && !stat.dataset.animated) {
                    stat.dataset.animated = 'true';
                    animateNumber(stat, 0, target, 2000);
                }
            }
        });
    };
    
    window.addEventListener('scroll', animateStats);
    animateStats(); // Check on load
});

// Helper function to validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Animate number counting
function animateNumber(element, start, end, duration) {
    const startTime = performance.now();
    const originalText = element.textContent;
    
    function updateNumber(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const current = Math.floor(start + (end - start) * easeOutQuart(progress));
        
        // Preserve any non-numeric parts of the original text
        if (originalText.includes('%')) {
            element.textContent = current + '%';
        } else if (originalText.includes('৳')) {
            element.textContent = '৳' + current.toLocaleString();
        } else {
            element.textContent = current;
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        } else {
            element.textContent = originalText; // Restore original text
        }
    }
    
    requestAnimationFrame(updateNumber);
}

// Easing function
function easeOutQuart(t) {
    return 1 - Math.pow(1 - t, 4);
}

// Mobile menu toggle (if needed)
function toggleMobileMenu() {
    const navMenu = document.querySelector('.nav-menu');
    navMenu.classList.toggle('active');
}

// Show loading state for forms
function showLoading(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    button.disabled = true;
    
    return function() {
        button.innerHTML = originalText;
        button.disabled = false;
    };
}

// Format currency
function formatCurrency(amount, currency = '৳') {
    return currency + parseFloat(amount).toLocaleString();
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // Add toast styles if not already present
    if (!document.querySelector('#toast-styles')) {
        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 10px;
                max-width: 400px;
                animation: slideInRight 0.3s ease;
            }
            .toast-success { border-left: 4px solid #27ae60; }
            .toast-error { border-left: 4px solid #e74c3c; }
            .toast-info { border-left: 4px solid #3498db; }
            .toast-warning { border-left: 4px solid #f39c12; }
            .toast button {
                background: none;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                margin-left: auto;
            }
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Confirm dialog
function confirmAction(message, callback) {
    const result = confirm(message);
    if (result && typeof callback === 'function') {
        callback();
    }
    return result;
}
