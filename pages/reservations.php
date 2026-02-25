<?php
// ดึงเอาไฟล์ตั้งค่าระบบ (config) เข้ามาก่อน
require_once '../config.php';
// เช็คเลยว่าล็อกอินแล้วยัง? ถ้ายังไม่ล็อกอินก็ไม่ให้เข้ามาหน้านี้
requireLogin();

// --- 1. AUTO CLEANUP EXPIRED BOOKINGS ---
// ตรวจสอบและยกเลิกการจองที่เลยเวลา 15 นาที โดยอัตโนมัติ
// ค้นหาการจองของ User คนนี้ ที่ยังไม่จ่ายเงิน (pending) และหมดเวลา (expires_at < NOW) ไปแล้ว
 $stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? AND payment_status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()");
 $stmt->execute([$_SESSION['user_id']]);
 $expiredBookings = $stmt->fetchAll();

// ถ้าเจอการจองที่หมดอายุแล้ว
if (!empty($expiredBookings)) {
    // วนลูปไปจัดการทีละอัน
    foreach ($expiredBookings as $exp) {
        // 1. Update Status to Expired/Cancelled
        // อัปเดตสถานะการจองเป็น 'expired' และ 'cancelled' เลย
        $pdo->prepare("UPDATE bookings SET payment_status = 'expired', booking_status = 'cancelled' WHERE booking_id = ?")->execute([$exp['booking_id']]);
        
        // 2. Return Equipment Stock
        // คืน Stock อุปกรณ์ที่เคยจองไว้กลับเข้าไปในระบบ
        $eqItems = $pdo->prepare("SELECT eq_id, quantity FROM booking_equipment WHERE booking_id = ?")->execute([$exp['booking_id']]);
        // Note: fetchAll needed if multiple items, but execute returns bool. Fixed below.
        // (หมายเหตุ: มีการแก้ไขโค้ดด้านล่างเพื่อดึงข้อมูลอุปกรณ์ออกมาให้ถูกต้อง)
        
        $itemsStmt = $pdo->prepare("SELECT eq_id, quantity FROM booking_equipment WHERE booking_id = ?");
        $itemsStmt->execute([$exp['booking_id']]);
        $items = $itemsStmt->fetchAll();
        
        // วนลูปเพิ่ม Stock อุปกรณ์ทีละชิ้น
        foreach ($items as $item) {
            $pdo->prepare("UPDATE equipment SET stock = stock + ? WHERE eq_id = ?")->execute([$item['quantity'], $item['eq_id']]);
        }
    }
}
// -----------------------------------------


// Get user's bookings (Fetch again after cleanup)
// ดึงข้อมูลการจองของ User มาแสดงผล (อีกครั้งหลังจากทำความสะอาดระบบเสร็จแล้ว)
// ดึงมาหลายตารางเลย ทั้ง bookings, courts, sports, time_slots และ payments
 $stmt = $pdo->prepare("
    SELECT b.*, s.sport_name, c.court_number, ts.start_time, ts.end_time, 
           p.payment_status as payment_verified, p.slip_image
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    JOIN sports s ON c.sport_id = s.sport_id
    JOIN time_slots ts ON b.slot_id = ts.slot_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC, ts.start_time DESC
");
 $stmt->execute([$_SESSION['user_id']]);
 $bookings = $stmt->fetchAll();

// Group bookings by status
// แบ่งการจองออกเป็น 2 กลุ่ม: กำลังจะมาถึง (Upcoming) กับ ผ่านไปแล้ว (Past)
 $upcoming = [];
 $past = [];

 $currentDateTime = new DateTime();

foreach ($bookings as $booking) {
    $bookingDateTime = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
    
    // Upcoming: Not cancelled AND (Future date OR (Today and not finished))
    // เงื่อนไขคือ: ต้องไม่ใช่การจองที่ถูกยกเลิก/หมดอายุ และต้องเป็นวันในอนาคตหรือวันนี้ที่ยังไม่หมดเวลา
    if ($booking['booking_status'] !== 'cancelled' && $booking['booking_status'] !== 'expired') {
        if ($bookingDateTime >= strtotime('today')) {
            $upcoming[] = $booking;
        } else {
            $past[] = $booking;
        }
    } else {
        // ถ้าเป็นการจองที่ยกเลิกไปแล้ว ก็ไปอยู่ในกลุ่ม Past
        $past[] = $booking;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Hit The Court</title>
    <!-- โหลด Font และ CSS มาแต่งหน้าตาเว็บ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <style>
        /* Custom Styles for Timer */
        /* CSS สำหรับตัวเลขนับถอยหลังเวลาจ่ายเงิน */
        .expiry-timer {
            font-size: 0.8rem;
            color: var(--error);
            font-weight: 600;
            margin-top: 0.25rem;
        }
    </style>
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

    <main class="section" style="padding-top: 7rem;">
        <div class="container">
            <!-- หัวข้อหน้ารายการจอง -->
            <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
                <h1>My Reservations</h1>
                <p class="text-muted">Track your bookings and history</p>
            </div>

            <!-- Tabs -->
            <!-- แถบเมนูย่อย (Tab) สำหรับสลับดู "กำลังจะมา" กับ "ประวัติ" -->
            <div class="reservations-tabs" data-tabs>
                <button class="reservation-tab active" data-tab="upcoming">Upcoming (<?= count($upcoming) ?>)</button>
                <button class="reservation-tab" data-tab="history">History (<?= count($past) ?>)</button>
            </div>

            <!-- Upcoming Bookings -->
            <!-- ส่วนแสดงรายการจองที่กำลังจะมาถึง -->
            <div class="reservations-grid" data-panel="upcoming">
                <?php if (empty($upcoming)): ?>
                <!-- ถ้าไม่มีการจอง จะแสดงกล่องว่างๆ พร้อมปุ่มให้ไปจองเลย -->
                <div class="card">
                    <div class="card-body text-center p-5">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.5" class="mb-3">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <h3 class="mb-2">No Upcoming Bookings</h3>
                        <p class="text-muted mb-4">Ready to play? Book a court now!</p>
                        <a href="<?= SITE_URL ?>/pages/courts.php" class="btn btn-primary">Book a Court</a>
                    </div>
                </div>
                <?php else: ?>
                    <!-- ถ้ามีการจอง ก็วนลูปแสดงทีละการ์ด -->
                    <?php foreach ($upcoming as $booking): ?>
                    <div class="reservation-card">
                        <div class="reservation-card-header">
                            <div>
                                <div class="reservation-card-id"><?= htmlspecialchars($booking['booking_code']) ?></div>
                                <div class="text-muted" style="font-size: 0.875rem;">
                                    Booked on <?= date('d M Y', strtotime($booking['created_at'])) ?>
                                </div>
                            </div>
                            <!-- แสดงสถานะการจอง (เช่น Confirmed, Verifying) -->
                            <span class="reservation-card-status status-<?= $booking['payment_status'] ?>">
                                <?php 
                                $statusText = ucfirst($booking['payment_status']);
                                // Logic เล็กน้อยเพื่อเปลี่ยนข้อความให้เข้าใจง่ายขึ้น
                                if ($booking['payment_status'] === 'pending' && $booking['payment_verified'] === 'pending') {
                                    $statusText = 'Verifying Payment';
                                } elseif ($booking['payment_status'] === 'paid') {
                                    $statusText = 'Confirmed';
                                }
                                echo $statusText;
                                ?>
                            </span>
                        </div>
                        <div class="reservation-card-body">
                            <!-- รายละเอียดการจอง -->
                            <div class="reservation-details">
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Sport</div>
                                    <div class="reservation-detail-value"><?= htmlspecialchars($booking['sport_name']) ?></div>
                                </div>
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Court</div>
                                    <div class="reservation-detail-value">Court <?= $booking['court_number'] ?></div>
                                </div>
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Date</div>
                                    <div class="reservation-detail-value"><?= date('d M Y', strtotime($booking['booking_date'])) ?></div>
                                </div>
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Time</div>
                                    <div class="reservation-detail-value">
                                        <?= date('g:i A', strtotime($booking['start_time'])) ?> - <?= date('g:i A', strtotime($booking['end_time'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="reservation-card-footer">
                            <!-- แสดงราคา -->
                            <span style="font-family: var(--font-display); font-weight: 600; font-size: 1.125rem;">
                                <?= number_format($booking['total_price']) ?> THB
                            </span>
                            <div class="d-flex gap-2">
                                <?php 
                                // Check expiry for pending payments
                                // เช็คว่าการจองนี้หมดอายุการจ่ายเงินหรือยัง?
                                $isExpired = false;
                                if ($booking['payment_status'] === 'pending' && !empty($booking['expires_at'])) {
                                    $expiryTime = strtotime($booking['expires_at']);
                                    if ($expiryTime < time()) {
                                        $isExpired = true;
                                    }
                                }
                                ?>
                                
                                <?php if ($booking['payment_status'] === 'pending' && !$isExpired): ?>
                                    <!-- ถ้ายังไม่จ่ายและยังไม่หมดอายุ แสดงปุ่ม "Pay Now" และตัวนับเวลา -->
                                    <div>
                                        <a href="<?= SITE_URL ?>/pages/pay_booking.php?id=<?= $booking['booking_id'] ?>" class="btn btn-primary btn-sm">Pay Now</a>
                                        <?php if (!empty($booking['expires_at'])): ?>
                                            <!-- ตรงนี้มี data attribute ไว้ให้ Javascript ดึงไปนับเวลาถอยหลัง -->
                                            <div class="expiry-timer" data-expires="<?= date('Y-m-d H:i:s', strtotime($booking['expires_at'])) ?>">
                                                <!-- JS will fill this -->
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($isExpired): ?>
                                    <!-- ถ้าหมดอายุแล้ว แสดงข้อความแจ้งเตือน -->
                                     <span class="text-error" style="font-size: 0.9rem; font-weight: 500;">Expired</span>
                                <?php endif; ?>
                                
                                <!-- Cancel Button Removed as requested -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- History -->
            <!-- ส่วนแสดงประวัติการจอง (ซ่อนไว้ก่อน) -->
            <div class="reservations-grid" data-panel="history" style="display: none;">
                <?php if (empty($past)): ?>
                <div class="card">
                    <div class="card-body text-center p-5">
                        <p class="text-muted">No past bookings found.</p>
                    </div>
                </div>
                <?php else: ?>
                    <!-- วนลูปแสดงการจองเก่าๆ -->
                    <?php foreach ($past as $booking): ?>
                    <div class="reservation-card">
                        <div class="reservation-card-header">
                            <div>
                                <div class="reservation-card-id"><?= htmlspecialchars($booking['booking_code']) ?></div>
                                <div class="text-muted" style="font-size: 0.875rem;">
                                    <?= date('d M Y', strtotime($booking['booking_date'])) ?>
                                </div>
                            </div>
                            <!-- สถานะจะเป็น Completed หรือ Cancelled -->
                            <span class="reservation-card-status status-<?= $booking['booking_status'] === 'cancelled' ? 'cancelled' : 'completed' ?>">
                                <?= ucfirst($booking['booking_status']) ?>
                            </span>
                        </div>
                        <div class="reservation-card-body">
                            <div class="reservation-details">
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Sport</div>
                                    <div class="reservation-detail-value"><?= htmlspecialchars($booking['sport_name']) ?></div>
                                </div>
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Court</div>
                                    <div class="reservation-detail-value">Court <?= $booking['court_number'] ?></div>
                                </div>
                                <div class="reservation-detail">
                                    <div class="reservation-detail-label">Total</div>
                                    <div class="reservation-detail-value"><?= number_format($booking['total_price']) ?> THB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- เรียกใช้ไฟล์ Javascript หลัก -->
    <script>
    // Tab Switching
    // สคริปต์สำหรับสลับ Tab ระหว่าง "Upcoming" กับ "History"
    document.querySelectorAll('.reservation-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // เอา class active ออกจากปุ่มเก่า
            document.querySelectorAll('.reservation-tab').forEach(t => t.classList.remove('active'));
            // ใส่ class active ให้ปุ่มที่กด
            this.classList.add('active');
            
            // ซ่อน Panel ทั้งหมด
            document.querySelectorAll('[data-panel]').forEach(p => p.style.display = 'none');
            // แสดง Panel ที่ตรงกับปุ่มที่กด
            document.querySelector(`[data-panel="${this.dataset.tab}"]`).style.display = 'grid';
        });
    });


    </script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.querySelector('.mobile-toggle');
    const navbar    = document.getElementById('navbar');
    const userMenu  = document.querySelector('.user-menu');
    const body      = document.body;

    /* ── Hamburger ── */
    if (toggleBtn && navbar) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            navbar.classList.toggle('menu-open');
            body.style.overflow = navbar.classList.contains('menu-open') ? 'hidden' : '';
        });
    }

    /* ── User Dropdown (mobile tap) ── */
    if (userMenu) {
        userMenu.querySelector('.user-btn').addEventListener('click', function (e) {
            if (window.innerWidth <= 768) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            }
        });
    }

    /* ── Click outside → ปิดทุกอย่าง ── */
    document.addEventListener('click', function (e) {
        if (navbar?.classList.contains('menu-open') && !navbar.contains(e.target)) {
            navbar.classList.remove('menu-open');
            body.style.overflow = '';
        }
        if (userMenu?.classList.contains('active') && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });

    /* ── Tab Switching ── */
    document.querySelectorAll('.reservation-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.reservation-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('[data-panel]').forEach(p => p.style.display = 'none');
            document.querySelector(`[data-panel="${this.dataset.tab}"]`).style.display = 'grid';
        });
    });
});
</script>
</body>
<!-- FOOTER -->
<!-- ส่วนท้ายเว็บไซต์ -->
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
                
                <!-- เมนูลิงก์ด้านล่าง -->
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
</body>
</html>