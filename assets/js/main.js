// ===================================
// HIT THE COURT - Main JavaScript
// ===================================
// ไฟล์หลักที่เอาไว้จัดการ Interaction ต่างๆ บนหน้าเว็บ ตั้งแต่เมนู, การจอง, จนถึง Animation

document.addEventListener('DOMContentLoaded', function() {
    // เมื่อหน้าเว็บโหลดเสร็จแล้ว ให้เริ่มเรียก Function สำหรับเตรียมระบบต่างๆ ทีละตัว
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
// จัดการ Effect ของ Navbar เวลาเลื่อนหน้าจอ
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    // ฟังก์ชันนี้คือ เวลาเลื่อนหน้าจอลงมาเกิน 50px จะเติม Class 'scrolled' เข้าไป
    // ปกติเวลาอยู่ด้านบนสุด Navbar อาจจะโปร่งใส พอเลื่อนลงมาก็จะเปลี่ยนเป็นสีทึบ (ตาม CSS ที่เขียนไว้)
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// Mobile Menu Toggle
// จัดการปุ่มเมนู Hamburger บนมือถือ
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.navbar-nav');
    
    if (!toggle || !nav) return;
    
    // เวลากดปุ่ม Toggle จะสลับ Class 'active' เพื่อแสดง/ซ่อนเมนู
    toggle.addEventListener('click', function() {
        this.classList.toggle('active');
        nav.classList.toggle('active');
        // อันนี้คือ Lock Scroll ตัว Body เวลาเมนูเปิดอยู่ ก็ไม่ให้เลื่อนหน้าจอได้
        document.body.style.overflow = nav.classList.contains('active') ? 'hidden' : '';
    });
    
    // Close menu when clicking outside
    // ปิดเมนูอัตโนมัติเวลากดพื้นที่นอกเมนู
    document.addEventListener('click', function(e) {
        if (!toggle.contains(e.target) && !nav.contains(e.target)) {
            toggle.classList.remove('active');
            nav.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

// Dropdown Menus
// จัดการเมนูแบบ Dropdown (เมนูย่อย) โดยเฉพาะบนมือถือที่ต้องกดเพื่อเปิด
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                // ถ้าหน้าจอกว้างน้อยกว่า 992px (มือถือ) ให้กดเปิด/ปิดได้
                if (window.innerWidth < 992) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            });
        }
    });
}

// Tabs
// จัดการระบบ Tab หรือแท็บสลับหน้า (เช่น แท็บ Upcoming / History)
function initTabs() {
    const tabGroups = document.querySelectorAll('[data-tabs]');
    
    tabGroups.forEach(group => {
        const tabs = group.querySelectorAll('[data-tab]');
        const panels = group.querySelectorAll('[data-panel]');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.tab;
                
                // Update tabs
                // เอา active ออกจากทุกแท็บ แล้วใส่ active ให้แท็บที่กด
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update panels
                // ซ่อนทุก Panel แล้วโชว์ Panel ที่ตรงกับแท็บที่เลือก
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
// ส่วนหัวใจสำคัญของระบบจอง จะเรียก Function ย่อยๆ มาเริ่มระบบทีละส่วน
function initBooking() {
    initCourtSelection();
    initTimeSlots();
    initEquipmentSelection();
    initDateSelection();
    updateOrderSummary(); // โหลดครั้งแรกให้คำนวณสรุปรายการเลย
}

// Date Selection
// จัดการเรื่องวันที่จอง
function initDateSelection() {
    const dateInput = document.getElementById('booking-date');
    if (!dateInput) return;
    
    // Set min date to today
    // กำหนดวันที่เริ่มต้นให้เป็น "วันนี้" จองย้อนหลังไม่ได้
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    
    // Get max booking days from data attribute (for members)
    // กำหนดวันที่สุดท้ายที่จองล่วงหน้าได้ (สมาชิกจองได้ไกลกว่าคนปกติ)
    const maxDays = dateInput.dataset.maxDays || 2;
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + parseInt(maxDays));
    dateInput.max = maxDate.toISOString().split('T')[0];
    
    // เวลาเปลี่ยนวันที่ ให้โหลดช่วงเวลาที่ว่างใหม่ และคำนวณราคาใหม่
    dateInput.addEventListener('change', function() {
        loadAvailableSlots();
        updateOrderSummary();
    });
}

// Court Selection
// จัดการตอนกดเลือกสนาม
function initCourtSelection() {
    const courtGrid = document.querySelector('.court-grid');
    if (!courtGrid) return;
    
    const courtBtns = courtGrid.querySelectorAll('.court-btn:not(.disabled)');
    
    courtBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // เอา Selection ตัวเก่าออก แล้วใส่ Selection ตัวใหม่
            courtBtns.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            // เก็บ ID สนามที่เลือกไว้ใน Input ซ่อน
            document.getElementById('selected-court').value = this.dataset.courtId;
            updateOrderSummary();
        });
    });
}

// Time Slots
// จัดการตอนกดเลือกช่วงเวลา
function initTimeSlots() {
    const timeGrid = document.querySelector('.time-grid');
    if (!timeGrid) return;
    
    const timeSlots = timeGrid.querySelectorAll('.time-slot:not(.booked):not(.disabled)');
    
    timeSlots.forEach(slot => {
        slot.addEventListener('click', function() {
            // เอา Selection ตัวเก่าออก แล้วใส่ Selection ตัวใหม่
            timeSlots.forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            // เก็บ ID Slot ที่เลือกไว้ใน Input ซ่อน
            document.getElementById('selected-slot').value = this.dataset.slotId;
            updateOrderSummary();
        });
    });
}

// Equipment Selection
// จัดการปุ่มเพิ่ม/ลดจำนวนอุปกรณ์เสริม
function initEquipmentSelection() {
    const equipmentList = document.querySelector('.equipment-list');
    if (!equipmentList) return;
    
    equipmentList.querySelectorAll('.equipment-item').forEach(item => {
        const decreaseBtn = item.querySelector('.qty-decrease');
        const increaseBtn = item.querySelector('.qty-increase');
        const qtyDisplay = item.querySelector('.qty-value');
        const eqId = item.dataset.eqId;
        const maxQty = parseInt(item.dataset.maxQty) || 10;
        
        // ปุ่มลดจำนวน
        decreaseBtn.addEventListener('click', function() {
            let qty = parseInt(qtyDisplay.textContent);
            if (qty > 0) {
                qtyDisplay.textContent = qty - 1;
                updateEquipmentTotal(eqId, qty - 1);
            }
        });
        
        // ปุ่มเพิ่มจำนวน
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
    // อัปเดตค่าใน Input ซ่อน เพื่อจะได้ส่งข้อมูลไป Server ได้ถูกต้อง
    const input = document.querySelector(`input[name="equipment[${eqId}]"]`);
    if (input) {
        input.value = qty;
    }
    
    updateOrderSummary();
}

// Update Order Summary
// อัปเดตสรุปรายการและยอดเงิน (ฟังก์ชันสำคัญมาก)
function updateOrderSummary() {
    const summaryEl = document.querySelector('.order-summary-body');
    if (!summaryEl) return;
    
    // Get selected values
    // ดึงราคาสนามจากปุ่มที่กดเลือก
    const courtPrice = parseFloat(document.querySelector('.court-btn.selected')?.dataset.price) || 0;
    const courtName = document.querySelector('.court-btn.selected')?.textContent.trim() || '-';
    
    // ดึงราคาช่วงเวลา (บางช่วงเวลาอาจราคาต่างกัน)
    const slotPrice = parseFloat(document.querySelector('.time-slot.selected')?.dataset.price) || courtPrice;
    const slotTime = document.querySelector('.time-slot.selected')?.querySelector('.time-slot-time')?.textContent || '-';
    
    // Calculate equipment total
    // วนลูปคำนวณราคาอุปกรณ์ที่เลือกทั้งหมด
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
    // ดึงค่าส่วนลด (ถ้ามี)
    const discount = parseFloat(document.getElementById('discount-amount')?.value) || 0;
    
    // Calculate total
    // สูตรคำนวณ: ราคาสนาม + อุปกรณ์ - ส่วนลด
    const total = courtPrice + equipmentTotal - discount;
    
    // Update summary display
    // จัดรูปแบบวันที่ให้อ่านง่าย
    const dateValue = document.getElementById('booking-date')?.value;
    const formattedDate = dateValue ? new Date(dateValue).toLocaleDateString('en-GB', { 
        weekday: 'short', 
        day: 'numeric', 
        month: 'short', 
        year: 'numeric' 
    }) : '-';
    
    // Build summary HTML
    // สร้างโค้ด HTML สำหรับแสดงผลสรุปรายการใหม่ทั้งหมด
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
    
    // ถ้ามีอุปกรณ์ ให้แสดงรายการอุปกรณ์เพิ่มเข้ามา
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
    
    // แสดงส่วนลดถ้ามี
    if (discount > 0) {
        html += `
            <div class="order-discount">
                <span class="order-discount-label">Discount</span>
                <span class="order-discount-value">-${discount.toFixed(0)} THB</span>
            </div>
        `;
    }
    
    // แสดงยอดรวมสุดท้าย
    html += `
        <div class="order-total">
            <span class="order-total-label">Total</span>
            <span class="order-total-value">${total.toFixed(0)} THB</span>
        </div>
    `;
    
    // ยัด HTML ที่สร้างขึ้นไปแสดงผล
    summaryEl.innerHTML = html;
    
    // Update hidden total input
    // อัปเดตค่า Total ใน Input ซ่อน เพื่อส่งไป Server
    const totalInput = document.getElementById('total-amount');
    if (totalInput) {
        totalInput.value = total;
    }
}

// Load Available Slots via AJAX
// โหลดช่วงเวลาที่ว่างผ่าน AJAX (ไม่ต้อง Refresh หน้า)
function loadAvailableSlots() {
    const dateInput = document.getElementById('booking-date');
    const sportId = document.getElementById('sport-id')?.value;
    
    if (!dateInput?.value || !sportId) return;
    
    // เรียก API ไปถามว่าวันนี้ Slot ไหน Booked ไปแล้วบ้าง
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
    
    // วนลูปปรับสถานะแต่ละ Slot
    timeGrid.querySelectorAll('.time-slot').forEach(slot => {
        const slotId = slot.dataset.slotId;
        const isBooked = slots.some(s => s.slot_id == slotId && s.booked);
        
        // ถ้าถูกจองแล้ว ก็เติม Class 'booked' และเปลี่ยนข้อความ
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
// จัดการระบบการชำระเงิน
function initPayment() {
    initPaymentMethods();
    initFileUpload();
}

function initPaymentMethods() {
    const methods = document.querySelectorAll('.payment-method');
    
    methods.forEach(method => {
        method.addEventListener('click', function() {
            // ตอนกดเลือกวิธีจ่ายเงิน จะสลับ Class 'selected'
            methods.forEach(m => m.classList.remove('selected'));
            this.classList.add('selected');
            
            const methodType = this.dataset.method;
            document.getElementById('payment-method').value = methodType;
            
            // Show/hide relevant sections
            // แสดง/ซ่อน Detail ที่เกี่ยวข้องกับวิธีนั้นๆ (เช่น แสดง QR Code หรือ แสดงเลขบัญชี)
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
    // รองรับการลากไฟล์มาวาง
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
    // รองรับการกดคลิกเลือกไฟล์ปกติ
    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });
}

function handleFileSelect(file) {
    const previewArea = document.querySelector('.file-preview');
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    
    // เช็คประเภทไฟล์
    if (!allowedTypes.includes(file.type)) {
        showToast('Invalid file type. Please upload JPG, PNG, or PDF.', 'error');
        return;
    }
    
    // เช็คขนาดไฟล์ (ห้ามเกิน 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showToast('File too large. Maximum size is 5MB.', 'error');
        return;
    }
    
    // ถ้าผ่านเงื่อนไข ก็แสดง Preview รูป
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
            // ถ้าเป็น PDF ก็แสดงไอคอนเอกสารแทนรูป
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
// ระบบตรวจสอบความถูกต้องของฟอร์ม
function initForms() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        // ตอนกด Submit ให้ตรวจสอบทุ่องฟิลด์
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault(); // ถ้าไม่ผ่าน ก็หยุดไม่ให้ส่ง
            }
        });
        
        // Real-time validation
        // ตรวจสอบทันทีที่ผู้ใช้เลิกพิมพ์ (blur)
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
    // เช็คก่อนว่าช่องที่ต้องกรอก มีข้อมูลไหม
    if (field.required && !value) {
        isValid = false;
        message = 'This field is required';
    }
    
    // Email check
    // เช็ค Format Email
    if (type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            message = 'Please enter a valid email address';
        }
    }
    
    // Phone check
    // เช็ค Format เบอร์โทร
    if (type === 'tel' && value) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            message = 'Please enter a valid phone number';
        }
    }
    
    // Password check
    // เช็คความยาวรหัสผ่านขั้นต่ำ
    if (type === 'password' && value && field.dataset.minLength) {
        if (value.length < parseInt(field.dataset.minLength)) {
            isValid = false;
            message = `Password must be at least ${field.dataset.minLength} characters`;
        }
    }
    
    // Update UI
    // อัปเดตหน้าจอว่าถูกต้องหรือผิดพลาด
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
// จัดการหน้าต่าง Modal (Popup)
function initModals() {
    // Open modal
    // ตอนกดปุ่มเปิด Modal
    document.querySelectorAll('[data-modal-open]').forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.dataset.modalOpen;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Lock scroll
            }
        });
    });
    
    // Close modal
    // ตอนกดปุ่มปิด Modal
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
    // ปิดตอนกดพื้นหลังดำๆ นอกกล่อง Modal
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Close on escape key
    // ปิดตอนกดปุ่ม Esc บนคีย์บอร์ด
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
// จัดการ Animation เวลาเลื่อนหน้าจอมาเจอ Element (Scroll Reveal)
function initAnimations() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            // เมื่อ Element โผล่เข้ามาในจอ ก็ให้เล่น Animation
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slideUp');
                observer.unobserve(entry.target); // เลิกสังเกตการณ์หลังจากเล่นแล้ว
            }
        });
    }, observerOptions);
    
    // เลือก Element ที่มี class 'animate-on-scroll' มาสังเกตการณ์
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
}

// Toast Notifications
// แสดงข้อความแจ้งเตือนแบบโผล่ขึ้นมาแล้วค่อยๆ จางหาย
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');
    
    // ถ้ายังไม่มี Container ก็สร้างขึ้นมาใหม่
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    // ตั้งเวลาให้จางหายไปใน 3 วินาที
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Utility Functions
// ฟังก์ชันช่วยเหลือทั่วไป
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
// ส่งออกฟังก์ชันไว้ให้ไฟล์ JS อื่นๆ เรียกใช้ได้ผ่านตัวแปร HitTheCourt
window.HitTheCourt = {
    showToast,
    formatCurrency,
    formatDate,
    updateOrderSummary,
    loadAvailableSlots
};

// ส่วนนี้เป็นการทำงานซ้ำซ้อนอีกทีกับ Navbar และ User Menu บนมือถือ
// เป็นการ Binding Event ให้ปุ่ม Toggle และ User Menu เพื่อให้แน่ใจว่าทำงาน
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.mobile-toggle');
    const navbar = document.getElementById('navbar');
    const body = document.body;

    // Toggle Mobile Menu
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            navbar.classList.toggle('menu-open');
            
            // Toggle Icon (Hamburger to Close)
            // เปลี่ยนไอคอนจาก Hamburger เป็น X
            const icon = this.querySelector('svg');
            if (navbar.classList.contains('menu-open')) {
                icon.innerHTML = '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>'; // X icon
                body.style.overflow = 'hidden'; // Prevent scroll
            } else {
                icon.innerHTML = '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>'; // Hamburger icon
                body.style.overflow = ''; // Enable scroll
            }
        });
    }

    // Handle Mobile Dropdowns (Click to open)
    // จัดการ Dropdown ในเมนูบนมือถือ
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        const dropdown = item.querySelector('.dropdown-menu');
        
        if (dropdown && window.innerWidth <= 768) {
            link.addEventListener('click', function(e) {
                if (navbar.classList.contains('menu-open')) {
                     e.preventDefault(); // Prevent link jump
                     item.classList.toggle('mobile-sub-open');
                }
            });
        }
    });

    // Handle User Menu Click on Mobile
    // จัดการเวลากดเมนู User บนมือถือ
    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        const userBtn = userMenu.querySelector('.user-btn');
        userBtn.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            }
        });
    }
    
// ส่วนนี้เป็นการทำงานซ้ำซ้อนอีกทีครับ (น่าจะเป็นโค้ดที่ Merge มาซ้ำ)
// แต่ฟังก์ชันการทำงานคือจัดการ Toggle และ Dropdown เหมือนด้านบน
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.mobile-toggle');
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const userMenu = document.querySelector('.user-menu');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navbar.classList.toggle('menu-open');
            body.style.overflow = navbar.classList.contains('menu-open') ? 'hidden' : '';
        });
    }

    if (userMenu) {
        userMenu.querySelector('.user-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('active');
        });
    }

    // ปิดเมนูเวลากดข้างนอก
    document.addEventListener('click', function(e) {
        if (navbar?.classList.contains('menu-open') && !navbar.contains(e.target)) {
            navbar.classList.remove('menu-open');
            body.style.overflow = '';
        }
        if (userMenu?.classList.contains('active') && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });
});
});