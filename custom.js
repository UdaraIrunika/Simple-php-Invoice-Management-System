// Royal Travel & Tours - Custom JavaScript Enhancements

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Add loading states to buttons
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                submitBtn.disabled = true;
            }
        });
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Add fade-in animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });

    // Currency selector search enhancement
    const currencySelects = document.querySelectorAll('.currency-select');
    currencySelects.forEach(select => {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control mb-2';
        searchInput.placeholder = 'Type to search currencies...';
        
        select.parentNode.insertBefore(searchInput, select);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = select.options;
            
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.text.toLowerCase();
                option.style.display = text.includes(searchTerm) ? '' : 'none';
            }
        });
    });

    // Real-time form validation
    const formsToValidate = document.querySelectorAll('form[method="POST"]');
    formsToValidate.forEach(form => {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });

    function validateField(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
        } else {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
        }
    }

    // Table row selection
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            this.classList.toggle('table-active');
        });
    });

    // Print functionality enhancement
    const printButtons = document.querySelectorAll('[onclick*="print"]');
    printButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    });

    // Dynamic page title with notification count
    function updatePageTitle() {
        const pendingCount = document.querySelectorAll('.badge.bg-warning').length;
        const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
        if (pendingCount > 0) {
            document.title = `(${pendingCount}) ${baseTitle}`;
        } else {
            document.title = baseTitle;
        }
    }

    // Initialize page title
    updatePageTitle();
});

// Utility functions
const RoyalTravelUtils = {
    // Format currency
    formatCurrency: function(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    // Format date
    formatDate: function(dateString) {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Copy to clipboard
    copyToClipboard: function(text) {
        navigator.clipboard.writeText(text).then(() => {
            // Show success message
            this.showToast('Copied to clipboard!', 'success');
        });
    },

    // Show toast notification
    showToast: function(message, type = 'info') {
        // Implementation for toast notifications
        console.log(`[${type.toUpperCase()}] ${message}`);
    }
};