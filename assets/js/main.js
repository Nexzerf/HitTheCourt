// ===================================
// HIT THE COURT - Main JavaScript
// ===================================

document.addEventListener('DOMContentLoaded', function() {
    initNavbar();
    initMobileMenu();
    initDropdowns();
    initTabs();
    initBooking();
    initPayment();
    initForms();
    initModals();
    initAnimations();
});

// Navbar Scroll Effect
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// Mobile Menu Toggle
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.navbar-nav');
    
    if (!toggle || !nav) return;
    
    toggle.addEventListener('click', function() {
        this.classList.toggle('active');
        nav.classList.toggle('active');
        document.body.style.overflow = nav.classList.contains('active') ? 'hidden' : '';
    });
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!toggle.contains(e.target) && !nav.contains(e.target)) {
            toggle.classList.remove('active');
            nav.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

// Dropdown Menus
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth < 992) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            });
        }
    });
}

// Tabs
function initTabs() {
    const tabGroups = document.querySelectorAll('[data-tabs]');
    
    tabGroups.forEach(group => {
        const tabs = group.querySelectorAll('[data-tab]');
        const panels = group.querySelectorAll('[data-panel]');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.tab;
                
                // Update tabs
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update panels
                panels.forEach(p => {
                    p.classList.remove('active');
                    if (p.dataset.panel === target) {
                        p.classList.add('active');
                    }
                });
            });
        });
    });
}

// Booking System
function initBooking() {
    initCourtSelection();
    initTimeSlots();
    initEquipmentSelection();
    initDateSelection();
    updateOrderSummary();
}

// Date Selection
function initDateSelection() {
    const dateInput = document.getElementById('booking-date');
    if (!dateInput) return;
    
    // Set min date to today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    // Get max booking days from data attribute (for members)
    const maxDays = dateInput.dataset.maxDays || 2;
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + parseInt(maxDays));
    dateInput.max = maxDate.toISOString().split('T')[0];
    
    dateInput.addEventListener('change', function() {
        loadAvailableSlots();
        updateOrderSummary();
    });
}

// Court Selection
function initCourtSelection() {
    const courtGrid = document.querySelector('.court-grid');
    if (!courtGrid) return;
    
    const courtBtns = courtGrid.querySelectorAll('.court-btn:not(.disabled)');
    
    courtBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            courtBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selected-court').value = this.dataset.courtId;
            updateOrderSummary();
        });
    });
}

// Time Slots
function initTimeSlots() {
    const timeGrid = document.querySelector('.time-grid');
    if (!timeGrid) return;
    
    const timeSlots = timeGrid.querySelectorAll('.time-slot:not(.booked):not(.disabled)');
    
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            timeSlots.forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selected-slot').value = this.dataset.slotId;
            updateOrderSummary();
        });
    });
}

// Equipment Selection
function initEquipmentSelection() {
    const equipmentList = document.querySelector('.equipment-list');
    if (!equipmentList) return;
    
    equipmentList.querySelectorAll('.equipment-item').forEach(item => {
        const decreaseBtn = item.querySelector('.qty-decrease');
        const increaseBtn = item.querySelector('.qty-increase');
        const qtyDisplay = item.querySelector('.qty-value');
        const eqId = item.dataset.eqId;
        const maxQty = parseInt(item.dataset.maxQty) || 10;
        
        decreaseBtn.addEventListener('click', function() {
            let qty = parseInt(qtyDisplay.textContent);
            if (qty > 0) {
                qtyDisplay.textContent = qty - 1;
                updateEquipmentTotal(eqId, qty - 1);
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            let qty = parseInt(qtyDisplay.textContent);
            if (qty < maxQty) {
                qtyDisplay.textContent = qty + 1;
                updateEquipmentTotal(eqId, qty + 1);
            }
        });
    });
}

function updateEquipmentTotal(eqId, qty) {
    // Update hidden input for form submission
    const input = document.querySelector(`input[name="equipment[${eqId}]"]`);
    if (input) {
        input.value = qty;
    }
    
    updateOrderSummary();
}

// Update Order Summary
function updateOrderSummary() {
    const summaryEl = document.querySelector('.order-summary-body');
    if (!summaryEl) return;
    
    // Get selected values
    const courtPrice = parseFloat(document.querySelector('.court-btn.selected')?.dataset.price) || 0;
    const courtName = document.querySelector('.court-btn.selected')?.textContent.trim() || '-';
    
    const slotPrice = parseFloat(document.querySelector('.time-slot.selected')?.dataset.price) || courtPrice;
    const slotTime = document.querySelector('.time-slot.selected')?.querySelector('.time-slot-time')?.textContent || '-';
    
    // Calculate equipment total
    let equipmentTotal = 0;
    const equipmentItems = [];
    
    document.querySelectorAll('.equipment-item').forEach(item => {
        const qty = parseInt(item.querySelector('.qty-value')?.textContent) || 0;
        if (qty > 0) {
            const price = parseFloat(item.dataset.price);
            const name = item.querySelector('.equipment-name')?.textContent;
            equipmentTotal += price * qty;
            equipmentItems.push({ name, qty, price, total: price * qty });
        }
    });
    
    // Get discount
    const discount = parseFloat(document.getElementById('discount-amount')?.value) || 0;
    
    // Calculate total
    const total = courtPrice + equipmentTotal - discount;
    
    // Update summary display
    const dateValue = document.getElementById('booking-date')?.value;
    const formattedDate = dateValue ? new Date(dateValue).toLocaleDateString('en-GB', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric' 
    }) : '-';
    
    // Build summary HTML
    let html = `
        <div class="order-item">
            <span class="order-item-label">Date</span>
            <span class="order-item-value">${formattedDate}</span>
        </div>
        <div class="order-item">
            <span class="order-item-label">Time</span>
            <span class="order-item-value">${slotTime}</span>
        </div>
        <div class="order-item">
            <span class="order-item-label">Court</span>
            <span class="order-item-value">${courtName}</span>
        </div>
        <div class="order-item">
            <span class="order-item-label">Court Fee</span>
            <span class="order-item-value">${courtPrice.toFixed(0)} THB</span>
        </div>
    `;
    
    if (equipmentItems.length > 0) {
        equipmentItems.forEach(item => {
            html += `
                <div class="order-item">
                    <span class="order-item-label">${item.name} x${item.qty}</span>
                    <span class="order-item-value">${item.total.toFixed(0)} THB</span>
                </div>
            `;
        });
    }
    
    if (discount > 0) {
        html += `
            <div class="order-discount">
                <span class="order-discount-label">Discount</span>
                <span class="order-discount-value">-${discount.toFixed(0)} THB</span>
            </div>
        `;
    }
    
    html += `
        <div class="order-total">
            <span class="order-total-label">Total</span>
            <span class="order-total-value">${total.toFixed(0)} THB</span>
        </div>
    `;
    
    summaryEl.innerHTML = html;
    
    // Update hidden total input
    const totalInput = document.getElementById('total-amount');
    if (totalInput) {
        totalInput.value = total;
    }
}

// Load Available Slots via AJAX
function loadAvailableSlots() {
    const dateInput = document.getElementById('booking-date');
    const sportId = document.getElementById('sport-id')?.value;
    
    if (!dateInput?.value || !sportId) return;
    
    fetch(`/api/available_slots.php?sport_id=${sportId}&date=${dateInput.value}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTimeSlots(data.slots);
            }
        })
        .catch(error => console.error('Error loading slots:', error));
}

function updateTimeSlots(slots) {
    const timeGrid = document.querySelector('.time-grid');
    if (!timeGrid) return;
    
    timeGrid.querySelectorAll('.time-slot').forEach(slot => {
        const slotId = slot.dataset.slotId;
        const isBooked = slots.some(s => s.slot_id == slotId && s.booked);
        
        if (isBooked) {
            slot.classList.add('booked');
            slot.querySelector('.time-slot-status').textContent = 'Booked';
        } else {
            slot.classList.remove('booked');
            slot.querySelector('.time-slot-status').textContent = 'Available';
        }
    });
}

// Payment System
function initPayment() {
    initPaymentMethods();
    initFileUpload();
}

function initPaymentMethods() {
    const methods = document.querySelectorAll('.payment-method');
    
    methods.forEach(method => {
        method.addEventListener('click', function() {
            methods.forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            
            const methodType = this.dataset.method;
            document.getElementById('payment-method').value = methodType;
            
            // Show/hide relevant sections
            document.querySelectorAll('.payment-details').forEach(el => {
                el.style.display = el.dataset.method === methodType ? 'block' : 'none';
            });
        });
    });
}

function initFileUpload() {
    const uploadArea = document.querySelector('.file-upload');
    const fileInput = document.getElementById('slip-upload');
    const previewArea = document.querySelector('.file-preview');
    
    if (!uploadArea || !fileInput) return;
    
    // Drag and drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', function() {
        this.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });
}

function handleFileSelect(file) {
    const previewArea = document.querySelector('.file-preview');
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    
    if (!allowedTypes.includes(file.type)) {
        showToast('Invalid file type. Please upload JPG, PNG, or PDF.', 'error');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        showToast('File too large. Maximum size is 5MB.', 'error');
        return;
    }
    
    if (previewArea) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewArea.innerHTML = `
                    <img src="${e.target.result}" alt="Slip Preview">
                    <p class="mt-2 text-muted">${file.name}</p>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            previewArea.innerHTML = `
                <div class="file-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </div>
                <p class="mt-2 text-muted">${file.name}</p>
            `;
        }
    }
}

// Form Validation
function initForms() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    
    form.querySelectorAll('[required]').forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    let isValid = true;
    let message = '';
    
    // Required check
    if (field.required && !value) {
        isValid = false;
        message = 'This field is required';
    }
    
    // Email check
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            message = 'Please enter a valid email address';
        }
    }
    
    // Phone check
    if (type === 'tel' && value) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            message = 'Please enter a valid phone number';
        }
    }
    
    // Password check
    if (type === 'password' && value && field.dataset.minLength) {
        if (value.length < parseInt(field.dataset.minLength)) {
            isValid = false;
            message = `Password must be at least ${field.dataset.minLength} characters`;
        }
    }
    
    // Update UI
    const errorEl = field.parentElement.querySelector('.form-error');
    
    if (!isValid) {
        field.classList.add('error');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    } else {
        field.classList.remove('error');
        if (errorEl) {
            errorEl.style.display = 'none';
        }
    }
    
    return isValid;
}

// Modals
function initModals() {
    // Open modal
    document.querySelectorAll('[data-modal-open]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.dataset.modalOpen;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    // Close modal
    document.querySelectorAll('[data-modal-close]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modal = this.closest('.modal-backdrop');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.active').forEach(modal => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
}

// Animations
function initAnimations() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slideUp');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
}

// Toast Notifications
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Utility Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB'
    }).format(amount);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

// Export functions for use in other scripts
window.HitTheCourt = {
    showToast,
    formatCurrency,
    formatDate,
    updateOrderSummary,
    loadAvailableSlots
};