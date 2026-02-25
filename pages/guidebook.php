<?php
// เริ่มต้นด้วยการดึงไฟล์ config.php เข้ามา เพื่อเชื่อมต่อฐานข้อมูลและใช้งานฟังก์ชันระบบ
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guidebook - Hit The Court</title>
    <!-- โหลดฟอนต์ Inter และ Space Grotesk จาก Google Fonts มาใช้ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- โหลดไฟล์ CSS หลักสำหรับตกแต่งหน้าเว็บ -->
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
</head>
<body>
  <!-- NAVBAR -->
    <!-- ส่วนของเมนูด้านบน (เหมือนหน้า Home ทุกอย่าง) -->
    <nav class="navbar-home" id="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">HIT THE <span>COURT</span></a>
                <button class="mobile-toggle" aria-label="Toggle menu">
                    <div class="hamburger-box">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </div>
                </button>
            
            <!-- Menu with Dropdown -->
            <ul class="nav-menu">
                <!-- Courts Dropdown -->
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/courts.php" class="nav-link">
                        Courts
                    </a>
                    
                </li>

                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/reservations.php" class="nav-link">Reservations</a>
                </li>
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/reports.php" class="nav-link">Contact Us</a>
                </li>
                <li class="nav-item">
                    <a href="<?= SITE_URL ?>/pages/guidebook.php" class="nav-link">Guidebook</a>
                </li>
            </ul>
            
            <!-- User Actions -->
            <!-- ส่วนแสดงข้อมูลผู้ใช้ด้านขวาบน -->
            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <!-- ถ้าล็อกอินแล้ว จะแสดงเมนู User (รูปโปรไฟล์, ชื่อ, Dropdown) -->
                    <div class="user-menu">
                        <button class="user-btn">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="user-dropdown">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--gray-200);">
                                <small style="color: var(--gray-500);">Signed in as</small>
                                <p style="font-weight: 600;"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            </div>
                            <div style="padding: 0.5rem;">
                                <a href="<?= SITE_URL ?>/pages/reservations.php" class="dropdown-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    My Bookings
                                </a>
                                 <a href="<?= SITE_URL ?>/pages/profile.php" class="dropdown-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    My Profile
                                </a>
                                    <a href="<?= SITE_URL ?>/pages/membership.php" class="dropdown-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 3h12l3 6-9 12L3 9l3-6z"></path>
                                            <path d="M3 9h18"></path>
                                            <path d="M9 3l3 6 3-6"></path>
                                        </svg>
                                        Membership
                                    </a>
                                <div style="border-top: 1px solid var(--gray-200); margin-top: 0.5rem; padding-top: 0.5rem;">
                                    <a href="<?= SITE_URL ?>/api/auth.php?action=logout" class="dropdown-link" style="color: var(--error);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- ถ้ายังไม่ได้ล็อกอิน ก็แสดงปุ่ม Login/Sign Up ธรรมดา -->
                    <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-ghost">Login</a>
                    <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <!-- ส่วนเนื้อหาหลักของหน้า Guidebook -->
    <main class="section" style="padding-top: 9rem;">
        <div class="container">
            <!-- หัวข้อหลักของหน้า -->
            <div class="section-header">
                <h1 class="section-title">Guidebook</h1>
                <p class="section-subtitle">Everything you need to know about using Hit The Court</p>
            </div>
            
            <div class="guidebook-content">
                <!-- How to Book -->
                <!-- หัวข้อที่ 1: วิธีการจองสนาม -->
                <div class="guidebook-section">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        How to Book a Court
                    </h2>
                    <!-- รายการแนะนำขั้นตอนการจอง -->
                    <ol>
                        <li><strong>Select Your Sport</strong> - Choose from Badminton, Football, Tennis, Basketball, Volleyball, Futsal, or Table Tennis.</li>
                        <li><strong>Pick a Date</strong> - Select your preferred date. Members can book up to 7 days in advance, non-members up to 2 days.</li>
                        <li><strong>Choose a Court & Time</strong> - Select an available court and time slot from the grid.</li>
                        <li><strong>Add Equipment (Optional)</strong> - Rent rackets, balls, or other equipment at affordable prices.</li>
                        <li><strong>Complete Payment</strong> - Pay via PromptPay or Bank Transfer and upload your slip.</li>
                        <li><strong>Receive Confirmation</strong> - Once verified, you'll receive a confirmation. Show the digital receipt at the front desk.</li>
                    </ol>
                </div>

                <!-- Editing Bookings -->
                <!-- หัวข้อที่ 2: การแก้ไขหรือยกเลิกการจอง -->
                <div class="guidebook-section">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Editing or Cancelling Bookings
                    </h2>
                    <h3>Cancellation Policy</h3>
                    <!-- เงื่อนไขการยกเลิก -->
                    <ul>
                        <li>Bookings can be cancelled up to <strong>24 hours</strong> before the scheduled time.</li>
                        <li>Refunds will be processed within 3-5 business days.</li>
                        <li>Cancellations within 24 hours are non-refundable.</li>
                    </ul>
                    <h3>How to Cancel</h3>
                    <!-- ขั้นตอนการยกเลิก -->
                    <ol>
                        <li>Go to <a href="<?= SITE_URL ?>/pages/reservations.php">My Reservations</a>.</li>
                        <li>Find your upcoming booking.</li>
                        <li>Click the "Cancel" button.</li>
                        <li>Confirm your cancellation.</li>
                    </ol>
                </div>

                <!-- Reporting Issues -->
                <!-- หัวข้อที่ 3: การรายงานปัญหา -->
                <div class="guidebook-section">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Reporting Issues
                    </h2>
                    <p>If you encounter any issues with facilities, equipment, or service, please report them:</p>
                    <!-- ขั้นตอนการรายงาน -->
                    <ol>
                        <li>Go to <a href="<?= SITE_URL ?>/pages/reports.php">Reports</a>.</li>
                        <li>Fill in the topic and detailed description.</li>
                        <li>Optionally attach a photo.</li>
                        <li>Submit the report.</li>
                    </ol>
                    <p>Our team will review and respond within 24-48 hours.</p>
                </div>

                <!-- Rules and Penalties -->
                <!-- หัวข้อที่ 4: กฎและบทลงโทษ -->
                <div class="guidebook-section">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        Rules and Penalties
                    </h2>
                    <!-- กล่องเตือนเรื่องค่าปรับ -->
                    <div class="penalty-box">
                        <h4>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            Equipment Damage or Loss
                        </h4>
                        <p style="margin-bottom: 0;">A <strong>fine of 50 THB</strong> applies for damaged equipment.</p>
                        <p style="margin-bottom: 0;"><strong>Stolen equipment will be charged at 10x the original price.</strong></p>
                    </div>
                    
                    <h3>General Rules</h3>
                    <!-- กฎทั่วไป -->
                    <ul>
                        <li>Please arrive at least <strong>15 minutes</strong> before your session.</li>
                        <li>Wear appropriate sports attire and non-marking shoes.</li>
                        <li>Food and drinks are only allowed in designated areas.</li>
                        <li>Return all rented equipment to the front desk after use.</li>
                        <li>Report any damage immediately to avoid penalties.</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

     <!-- FOOTER -->
     <!-- ส่วนท้ายเว็บไซต์ (Footer) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <span class="footer-logo">HIT THE COURT</span>
                    <p class="footer-text">
                        College of Arts, Media and Technology,<br>
                        Chiang Mai University<br>
                        © 2026 Hit the Court. A Chiang Mai University Experimental Project.
                    </p>
                </div>
                
                <!-- ลิงก์ในเมนู Footer -->
                <div class="footer-links">
                    <h4>Menu</h4>
                    <ul>
                        <li><a href="<?= SITE_URL ?>/pages/courts.php">Court Reservation</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/guidebook.php">Guidebook</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/reports.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <!-- ข้อมูลติดต่อ -->
                <div class="footer-links">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                            <a href="tel:111-222-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"></path></svg>
                                111-222-3
                            </a>
                        </li>
                        <li>
                            <a href="mailto:peoplecmucamt@gmail.com">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                peoplecmucamt@gmail.com
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>HIT THE COURT</p>
            </div>
        </div>
    </footer>


    <!-- โหลดไฟล์ JavaScript หลัก -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>

</body>
</html>
```