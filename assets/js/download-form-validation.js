// Download Form Email Domain Validation
document.addEventListener('DOMContentLoaded', function() {
    // Find all download forms
    const downloadForms = document.querySelectorAll('.download-form');
    
    downloadForms.forEach(function(form) {
        const emailInput = form.querySelector('input[name="EMAIL"]');
        const submitButton = form.querySelector('input[type="submit"]');
        const noticeText = form.querySelector('.notice-text');
        
        if (!emailInput || !submitButton) return;
        
        // List of allowed domains
        const allowedDomains = [
            'gmail.com', 'googlemail.com',
            'hotmail.com', 'hotmail.co.uk', 'hotmail.fr', 'hotmail.de', 'hotmail.it', 'hotmail.es', 'hotmail.ca', 'hotmail.com.au',
            'outlook.com', 'outlook.co.uk', 'outlook.fr', 'outlook.de', 'outlook.it', 'outlook.es', 'outlook.ca', 'outlook.com.au',
            'live.com', 'live.co.uk', 'live.fr', 'live.de', 'live.it', 'live.ca', 'msn.com',
            'yahoo.com', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.it', 'yahoo.es', 'yahoo.ca', 'yahoo.com.au', 'yahoo.co.in', 'yahoo.com.br',
            'ymail.com', 'rocketmail.com'
        ];
        
        // Add notice about allowed domains
        if (noticeText) {
            noticeText.innerHTML = '<small style="color: #666; font-size: 12px;">Only Gmail, Hotmail, and Yahoo email addresses are accepted.</small>';
        }
        
        // Real-time validation
        emailInput.addEventListener('input', function() {
            const email = this.value.toLowerCase().trim();
            
            if (email.includes('@')) {
                const domain = email.split('@')[1];
                
                if (domain && !allowedDomains.includes(domain)) {
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.1)';
                    this.setCustomValidity('Please use a Gmail, Hotmail, or Yahoo email address.');
                    
                    if (noticeText) {
                        noticeText.innerHTML = '<small style="color: #dc3545;">Please use a Gmail, Hotmail, or Yahoo email address.</small>';
                    }
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                    this.setCustomValidity('');
                    
                    if (noticeText) {
                        noticeText.innerHTML = '<small style="color: #666; font-size: 12px;">Only Gmail, Hotmail, and Yahoo email addresses are accepted.</small>';
                    }
                }
            }
        });
        
        // Form submission validation
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.toLowerCase().trim();
            
            if (!email) {
                e.preventDefault();
                if (noticeText) {
                    noticeText.innerHTML = '<small style="color: #dc3545;">Please enter an email address.</small>';
                }
                return false;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                if (noticeText) {
                    noticeText.innerHTML = '<small style="color: #dc3545;">Please enter a valid email address.</small>';
                }
                return false;
            }
            
            const domain = email.split('@')[1];
            if (!domain || !allowedDomains.includes(domain)) {
                e.preventDefault();
                if (noticeText) {
                    noticeText.innerHTML = '<small style="color: #dc3545;">Only Gmail, Hotmail, and Yahoo email addresses are allowed for downloads.</small>';
                }
                return false;
            }
            
            // If validation passes, show loading state
            submitButton.disabled = true;
            submitButton.value = 'Processing...';
            
            if (noticeText) {
                noticeText.innerHTML = '<small style="color: #007cba;">Validating email...</small>';
            }
        });
    });
});