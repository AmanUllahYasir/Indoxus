/**
 * INDOXUS COMMUNICATIONS - MAIN JAVASCRIPT
 * Author: Liminal Digital Services
 */

(function() {
    'use strict';

    // ==========================================
    // MOBILE MENU TOGGLE
    // ==========================================
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.nav');
    
    if (mobileMenuToggle && nav) {
        mobileMenuToggle.addEventListener('click', function() {
            nav.classList.toggle('active');
            this.classList.toggle('active');
            
            // Animate hamburger icon
            const spans = this.querySelectorAll('span');
            if (this.classList.contains('active')) {
                spans[0].style.transform = 'rotate(45deg) translateY(8px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translateY(-8px)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        });

        // Close mobile menu when clicking nav links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                nav.classList.remove('active');
                mobileMenuToggle.classList.remove('active');
                
                const spans = mobileMenuToggle.querySelectorAll('span');
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            });
        });
    }

    // ==========================================
    // SMOOTH SCROLLING FOR ANCHOR LINKS
    // ==========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href.length > 1) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const headerHeight = document.querySelector('.header').offsetHeight;
                    const targetPosition = target.offsetTop - headerHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // ==========================================
    // HEADER SCROLL EFFECT
    // ==========================================
    const header = document.querySelector('.header');
    let lastScroll = 0;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        
        // Add shadow on scroll
        if (currentScroll > 100) {
            header.style.boxShadow = '0 4px 16px rgba(0, 0, 0, 0.1)';
        } else {
            header.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.1)';
        }
        
        lastScroll = currentScroll;
    });

    // ==========================================
    // INTERSECTION OBSERVER FOR ANIMATIONS
    // ==========================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe service cards
    document.querySelectorAll('.service-card').forEach(card => {
        observer.observe(card);
    });

    // Observe category cards
    document.querySelectorAll('.category-card').forEach(card => {
        observer.observe(card);
    });

    // Observe client logos
    document.querySelectorAll('.client-logo').forEach(logo => {
        observer.observe(logo);
    });

    // ==========================================
    // CONTACT FORM HANDLING
    // ==========================================
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data
            const formData = new FormData(this);

            // Honeypot check - if filled, it's likely a bot
            const honeypot = formData.get('website') || '';
            if (honeypot !== '') {
                // Silently reject spam - don't show error to bot
                console.log('Spam detected via honeypot');
                return false;
            }

            const data = {
                name: formData.get('name') || '',
                email: formData.get('email') || '',
                job_title: formData.get('job_title') || '',
                company: formData.get('company') || '',
                country: formData.get('country') || '',
                message: formData.get('message') || '',
                service: formData.get('service') || 'General Inquiry',
                website: honeypot
            };
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            
            // Send to backend
            fetch('api/contact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.text())
            .then(text => {
                // Try to parse JSON; if parsing fails, log server output for debugging
                let result;
                try {
                    result = JSON.parse(text);
                } catch (err) {
                    console.error('Unexpected server response:', text);
                    showNotification('Server returned an unexpected response. Check console for details.', 'error');
                    return;
                }

                if (result.success) {
                    showNotification('Thank you! Your message has been sent successfully. We will contact you soon.', 'success');
                    contactForm.reset();
                } else {
                    // If server provided errors array, show first
                    if (result.errors && Array.isArray(result.errors) && result.errors.length) {
                        showNotification(result.errors[0], 'error');
                    } else {
                        showNotification(result.message || 'Sorry, something went wrong. Please try again.', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Sorry, something went wrong. Please try again or contact us directly.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // ==========================================
    // SERVICE CARD QUERY BUTTONS
    // Attach handlers to the actual button class used in markup: `.service-btn`.
    // Scrolls to the contact form and prefills the hidden service input + message textarea.
    // If a modal `openContactModal` exists it will be used as a fallback.
    // ==========================================
    function initServiceButtons() {
        const serviceButtons = document.querySelectorAll('.service-btn');
        console.log('Service buttons found:', serviceButtons.length);

        serviceButtons.forEach((btn, idx) => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Service button clicked:', idx);
                
                const card = this.closest('.service-card-exact') || this.closest('.service-card');
                const titleEl = card ? (card.querySelector('.service-name') || card.querySelector('h3')) : null;
                const serviceName = titleEl ? titleEl.textContent.trim() : 'Service Inquiry';
                console.log('Service name:', serviceName);

                const contactSection = document.getElementById('contact');
                const contactForm = document.getElementById('contactForm');

                // Prefill hidden service input if present
                if (contactForm) {
                    const svcInput = contactForm.querySelector('input[name="service"]');
                    if (svcInput) svcInput.value = serviceName;
                }

                if (contactSection) {
                    const headerHeight = document.querySelector('.header').offsetHeight;
                    const targetPosition = contactSection.offsetTop - headerHeight;

                    window.scrollTo({ top: targetPosition, behavior: 'smooth' });

                    // Focus and prefill textarea immediately (smooth scroll happens in background)
                    const messageField = document.querySelector('#contactForm textarea[name="message"]');
                    if (messageField) {
                        messageField.value = `I'm interested in learning more about your ${serviceName}.`;
                        messageField.focus();
                    }
                } else if (typeof openContactModal === 'function') {
                    // fallback to modal if page contact form not available
                    openContactModal(serviceName);
                } else {
                    console.warn('No contact form or modal available to send query.');
                }
            });
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServiceButtons);
    } else {
        initServiceButtons();
    }

    // ==========================================
    // NOTIFICATION SYSTEM
    // ==========================================
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '100px',
            right: '20px',
            padding: '20px 30px',
            background: type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6',
            color: '#ffffff',
            borderRadius: '10px',
            boxShadow: '0 8px 24px rgba(0, 0, 0, 0.2)',
            zIndex: '9999',
            fontSize: '16px',
            fontWeight: '600',
            maxWidth: '400px',
            animation: 'slideInRight 0.5s ease-out',
            transition: 'all 0.3s ease'
        });
        
        // Add to document
        document.body.appendChild(notification);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
    }

    // ==========================================
    // SCROLL TO TOP BUTTON
    // ==========================================
    function createScrollTopButton() {
        const scrollTop = document.createElement('button');
        scrollTop.className = 'scroll-top';
        scrollTop.innerHTML = '↑';
        scrollTop.setAttribute('aria-label', 'Scroll to top');
        document.body.appendChild(scrollTop);
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 500) {
                scrollTop.classList.add('active');
            } else {
                scrollTop.classList.remove('active');
            }
        });
        
        scrollTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    createScrollTopButton();

    // ==========================================
    // LAZY LOADING IMAGES
    // ==========================================
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // ==========================================
    // FORM VALIDATION
    // ==========================================
    const formInputs = document.querySelectorAll('.form-input');
    
    formInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateInput(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateInput(this);
            }
        });
    });

    function validateInput(input) {
        const value = input.value.trim();
        const type = input.type;
        
        let isValid = true;
        let errorMessage = '';
        
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        } else if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        } else if (type === 'tel' && value) {
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(value) || value.length < 10) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }
        
        if (!isValid) {
            input.classList.add('error');
            input.style.borderColor = '#EF4444';
            showInputError(input, errorMessage);
        } else {
            input.classList.remove('error');
            input.style.borderColor = '#E5E7EB';
            removeInputError(input);
        }
        
        return isValid;
    }

    function showInputError(input, message) {
        removeInputError(input);
        const error = document.createElement('span');
        error.className = 'input-error';
        error.textContent = message;
        error.style.color = '#EF4444';
        error.style.fontSize = '14px';
        error.style.marginTop = '5px';
        error.style.display = 'block';
        input.parentNode.appendChild(error);
    }

    function removeInputError(input) {
        const error = input.parentNode.querySelector('.input-error');
        if (error) {
            error.remove();
        }
    }

    // ==========================================
    // PERFORMANCE: Debounce Function
    // ==========================================
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

    // ==========================================
    // CLIENT LOGO MARQUEE EFFECT (Optional)
    // ==========================================
    const clientsGrid = document.querySelector('.clients-grid');
    if (clientsGrid && window.innerWidth > 768) {
        // Add subtle animation on hover
        const logos = clientsGrid.querySelectorAll('.client-logo');
        logos.forEach(logo => {
            logo.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(2deg)';
            });
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        });
    }

    // ==========================================
    // CONSOLE MESSAGE
    // ==========================================
    console.log('%c👋 Built with care by Liminal Digital Services', 'color: #2F6F78; font-size: 16px; font-weight: bold;');
    console.log('%cWebsite: https://liminal.com.pk', 'color: #666; font-size: 12px;');

})();
