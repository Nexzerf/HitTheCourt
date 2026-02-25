<?php
// เรียกไฟล์ config มาก่อน เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันระบบ
require_once '../config.php';

// --- แก้ไขส่วนนี้: อนุญาตทั้ง User และ Admin ---
// เช็คสิทธิ์ว่าใครเข้ามาได้บ้าง
if (isLoggedIn()) {
    // ผ่านเลย ถ้าเป็น User ทั่วไป
} elseif (isAdmin()) {
    // ผ่านเลย ถ้าเป็น Admin (แอดมินก็อยากมาจองบ้างได้)
} else {
    // ถ้าไม่ใช่ทั้งสอง (ยังไม่ได้ล็อกอิน) ให้ดีดไปหน้า Login ทันที
    redirect('/pages/login.php');
}
// ------------------------------------------

// --- LOGIC ---
// รับค่า sport_id จาก URL ถ้าไม่มีให้ดีดกลับไปหน้าเลือกกีฬา
 $sportId = intval($_GET['sport_id'] ?? 0);
if (!$sportId) redirect('/pages/courts.php');

// Get Sport Info
// ไปดึงข้อมูลกีฬาที่เลือกมา เช่น ชื่อกีฬา, ราคา, ระยะเวลา
 $stmt = $pdo->prepare("SELECT * FROM sports WHERE sport_id = ?");
 $stmt->execute([$sportId]);
 $sport = $stmt->fetch();
if (!$sport) redirect('/pages/courts.php');

// Date Logic
// จัดการเรื่องวันที่ วันนี้คือวันไหน ถ้ามีการกดเลือกวันอื่นมาก็เอาค่านั้น และคำนวณวันก่อน/หลัง สำหรับปุ่มเลื่อนวัน
 $selectedDate = $_GET['date'] ?? date('Y-m-d');
 $prevDate = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
 $nextDate = date('Y-m-d', strtotime($selectedDate . ' +1 day'));

// Get Courts
// ดึงรายการสนาม (Court) ทั้งหมดของกีฬานี้ออกมาแสดงเป็นหัวตาราง
 $stmt = $pdo->prepare("SELECT * FROM courts WHERE sport_id = ?");
 $stmt->execute([$sportId]);
 $courts = $stmt->fetchAll();

// Get Slots
// ดึงช่วงเวลา (Time Slots) สำหรับจอง เช่น 09:00-10:00, 10:00-11:00 เรียงจากน้อยไปมาก
 $stmt = $pdo->prepare("SELECT * FROM time_slots WHERE sport_id = ? ORDER BY start_time ASC");
 $stmt->execute([$sportId]);
 $timeSlots = $stmt->fetchAll();

// Get Equipment
// ดึงรายการอุปกรณ์ที่สามารถเช่าได้ โดยเอาเฉพาะตัวที่ยังมี Stock เหลืออยู่
 $stmt = $pdo->prepare("SELECT * FROM equipment WHERE sport_id = ? AND stock > 0");
 $stmt->execute([$sportId]);
 $equipments = $stmt->fetchAll();

// Get Bookings Map (Key: "courtId_slotId")
// สร้าง "แผนที่การจอง" ขึ้นมา เพื่อเอาไว้เช็คว่าช่องไหนถูกจองไปแล้วบ้าง
// เช็คเฉพาะสถานะ 'pending' (กำลังจะจ่าย) และ 'paid' (จ่ายแล้ว) เพื่อไม่ให้คนอื่นจองซ้ำ
 $bookingsMap = [];
 $stmt = $pdo->prepare("SELECT court_id, slot_id FROM bookings WHERE booking_date = ? AND payment_status IN ('pending', 'paid')");
 $stmt->execute([$selectedDate]);
foreach($stmt->fetchAll() as $b) {
    $bookingsMap[$b['court_id'].'_'.$b['slot_id']] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?= htmlspecialchars($sport['sport_name']) ?></title>
    <!-- โหลดฟอนต์และ CSS มาแต่งหน้าตาเว็บ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
</head>
<body class='bodyy'>

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

    <!-- CONTENT -->
    <!-- ส่วนเนื้อหาหลักของหน้าจอง -->
    <div class="booking-page-container">
        
        <!-- LEFT: Selection -->
        <!-- ฝั่งซ้าย: ตารางเลือกเวลาและอุปกรณ์ -->
        <div class="booking-left">
            <div class="page-header">
                <h1>Booking: <?= htmlspecialchars($sport['sport_name']) ?></h1>
            </div>

            <!-- Date Selector -->
            <!-- ส่วนเลือกวันที่: มีปุ่มย้อนหลัง, แสดงวันปัจจุบัน, และปุ่มถัดไป -->
            <div class="date-selector">
                <a href="?sport_id=<?= $sportId ?>&date=<?= $prevDate ?>" class="date-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </a>
                <div class="current-date"><?= date('D, d M Y', strtotime($selectedDate)) ?></div>
                <a href="?sport_id=<?= $sportId ?>&date=<?= $nextDate ?>" class="date-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>

            <!-- Form Start -->
            <!-- เริ่มฟอร์มการจอง จะส่งข้อมูลไปที่ api/book.php -->
            <form action="<?= SITE_URL ?>/api/book.php" method="POST" id="bookingForm">
                <input type="hidden" name="sport_id" value="<?= $sportId ?>">
                <input type="hidden" name="booking_date" value="<?= $selectedDate ?>">
                <!-- ตัวแปรซ่อนสำหรับเก็บค่า "court_id_slot_id" ที่ผู้ใช้กดเลือก -->
                <input type="hidden" name="slot_court" id="inputSlotCourt" value="">

                <!-- Grid Table -->
                <!-- ตารางแสดงช่วงเวลาและสนาม -->
                <table class="booking-grid-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <?php foreach($courts as $c): ?>
                                <!-- แสดงหัวตารางเป็นเลขที่สนาม (C1, C2...) -->
                                <th>C<?= preg_replace('/[^0-9]/', '', $c['court_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timeSlots as $slot): ?>
                        <tr>
                            <!-- แสดงเวลาเริ่มต้นของแถวนั้นๆ -->
                            <td class="time-label">
                                <?= date('g:i A', strtotime($slot['start_time'])) ?>
                            </td>
                            <?php foreach($courts as $c): 
                                $key = $c['court_id'].'_'.$slot['slot_id'];
                                
                                // --- Logic ตรวจสอบ ---
                                // 1. ถูกจองแล้ว? (เช็คจาก Map ที่สร้างไว้ด้านบน)
                                $isBookedBySomeone = isset($bookingsMap[$key]);
                                // 2. สถานะคอร์ทไม่ว่าง (Maintenance)? (เช็คจากตาราง courts)
                                $isCourtUnavailable = ($c['status'] !== 'available');
                                
                                // สรุปสถานะว่าปุ่มนี้จะกดไม่ได้หรือเปล่า
                                $isDisabled = $isBookedBySomeone || $isCourtUnavailable;
                                
                                // ข้อความแสดงผลและสีของปุ่ม
                                $btnText = '🟢'; // ว่าง
                                $btnClass = '';
                                
                                if ($isCourtUnavailable) {
                                    $btnText = '🟡'; // แสดง "Maintenance"
                                    $btnClass = 'is-maintenance'; // CSS Class สีเหลือง
                                } elseif ($isBookedBySomeone) {
                                    $btnText = '🔴'; // แสดง "ถูกจอง"
                                    $btnClass = 'is-booked'; // CSS Class สีแดง
                                }
                            ?>
                            <td>
                                <!-- ปุ่มเลือกช่องเวลา จะมีข้อมูล data-attributes ติดมาด้วย เช่น ราคา, เวลา, ชื่อสนาม -->
                                <button type="button" 
                                        class="slot-btn <?= $btnClass ?>" 
                                        data-court-id="<?= $c['court_id'] ?>"
                                        data-slot-id="<?= $slot['slot_id'] ?>"
                                        data-price="<?= $sport['price'] ?>"
                                        data-time="<?= date('g:i A', strtotime($slot['start_time'])) ?>"
                                        data-court-name="C<?= preg_replace('/[^0-9]/', '', $c['court_name']) ?>"
                                        <?= $isDisabled ? 'disabled' : '' ?>>
                                    <?= $btnText ?>
                                </button>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Equipment -->
                <!-- ส่วนเลือกอุปกรณ์เสริม -->
                <?php if(!empty($equipments)): ?>
                <div class="equipment-section">
                    <h3 style="font-family:var(--font-display); margin-bottom:1rem;">Add Equipment</h3>
                    <div class="equipment-grid">
                        <?php foreach($equipments as $eq): ?>
                        <div class="eq-item">
                            <div class="eq-info">
                                <h4><?= htmlspecialchars($eq['eq_name']) ?></h4>
                                <p><?= number_format($eq['price']) ?> THB (Stock: <?= $eq['stock'] ?>)</p>
                            </div>
                            <!-- ปุ่มกดเพิ่ม/ลดจำนวนอุปกรณ์ -->
                            <div class="eq-qty-control">
                                <button type="button" class="eq-qty-btn" onclick="changeQty('eq<?= $eq['eq_id'] ?>', -1, <?= $eq['stock'] ?>)">-</button>
                                <input type="number" name="equipment[<?= $eq['eq_id'] ?>]" id="eq<?= $eq['eq_id'] ?>" value="0" max="<?= $eq['stock'] ?>" data-price="<?= $eq['price'] ?>" data-name="<?= htmlspecialchars($eq['eq_name']) ?>" class="eq-qty-val" readonly>
                                <button type="button" class="eq-qty-btn" onclick="changeQty('eq<?= $eq['eq_id'] ?>', 1, <?= $eq['stock'] ?>)">+</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </form>
        </div>

        <!-- RIGHT: Summary -->
        <!-- ฝั่งขวา: กล่องสรุปรายการและราคา -->
        <div class="booking-right">
            <div class="summary-box">
                <h3 style="font-family:var(--font-display); margin-bottom:1.5rem;">Summary</h3>
                
                <div class="sum-row">
                    <span>Date</span>
                    <span style="font-weight:600; color:var(--secondary)"><?= date('d M Y', strtotime($selectedDate)) ?></span>
                </div>
                <div class="sum-row">
                    <span>Time</span>
                    <!-- ตรงนี้จะถูกเปลี่ยนโดย JavaScript ตอนกดปุ่ม -->
                    <span id="sumTime" style="font-weight:600; color:var(--secondary)">-</span>
                </div>
                <div class="sum-row">
                    <span>Court</span>
                    <!-- ตรงนี้จะถูกเปลี่ยนโดย JavaScript ตอนกดปุ่ม -->
                    <span id="sumCourt" style="font-weight:600; color:var(--secondary)">-</span>
                </div>
                
                <div id="equipSummary" style="display:none;" class="sum-row">
                    <span>Equipment</span>
                    <!-- แสดงรายการอุปกรณ์ที่เลือก -->
                    <span id="sumEquip" style="font-weight:600; color:var(--secondary); text-align:right;"></span>
                </div>

                <div class="sum-row total">
                    <span>Total</span>
                    <!-- แสดงราคารวม -->
                    <span id="sumTotal">0 THB</span>
                </div>

                <!-- ปุ่มยืนยันการจอง (จะใช้งานได้ก็ต่อเมื่อเลือกเวลาแล้ว) -->
                <button type="submit" form="bookingForm" id="btnSubmit" class="btn-submit" disabled>
                    Proceed to Payment
                </button>
            </div>
        </div>

    </div>

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
                
                <div class="footer-links">
                    <h4>Menu</h4>
                    <ul>
                        <li><a href="<?= SITE_URL ?>/pages/courts.php">Court Reservation</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/guidebook.php">Guidebook</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/reports.php">Contact Us</a></li>
                    </ul>
                </div>
                
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

    <!-- JAVASCRIPT -->
    <script>
        // 1. State
        // เก็บตัวแปรสถานะต่างๆ เช่น ปุ่มที่กดเลือกอยู่ และราคารวมปัจจุบัน
        let selectedBtn = null;
        let currentTotal = 0;

        // 2. Slot Selection
        // จับกลุ่มปุ่มที่ "กดได้" (ไม่ใช่ปุ่มที่ถูกจองหรือซ่อมบำรุง) มาไว้ในตัวแปร buttons
        const buttons = document.querySelectorAll('.slot-btn:not(.is-booked):not(.is-maintenance)');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                // Reset previous
                // เมื่อกดปุ่มใหม่ ให้รีเซ็ตปุ่มเก่าก่อน (เอาสีเลือกออก, เปลี่ยนข้อความกลับเป็น Available)
                if (selectedBtn) {
                    selectedBtn.classList.remove('is-selected');
                    selectedBtn.innerText = '🟢';
                }

                // Set new
                // แล้วมาจัดการปุ่มใหม่ที่กด (เปลี่ยนสี, เปลี่ยนข้อความเป็น Selected)
                this.classList.add('is-selected');
                this.innerText = '⚪️';
                selectedBtn = this;

                // Update Hidden Input
                // เอาค่า court_id กับ slot_id มาต่อกัน แล้วยัดใส่ input hidden เพื่อส่งไป Backend
                const value = this.dataset.courtId + '_' + this.dataset.slotId;
                document.getElementById('inputSlotCourt').value = value;

                // Update UI
                // อัปเดตข้อความในกล่อง Summary ทางด้านขวา
                document.getElementById('sumTime').innerText = this.dataset.time;
                document.getElementById('sumCourt').innerText = this.dataset.courtName;
                document.getElementById('btnSubmit').disabled = false; // ให้ปุ่มจองใช้งานได้

                calculateTotal(); // เรียกคำนวณราคาใหม่
            });
        });

        // 3. Equipment Quantity
        // ฟังก์ชันสำหรับปุ่มกดเพิ่ม/ลดจำนวนอุปกรณ์
        function changeQty(id, change, max) {
            const input = document.getElementById(id);
            let val = parseInt(input.value) + change;
            // เช็คขอบเขต ไม่ให้ติดลบ และไม่เกิน Stock
            if (val < 0) val = 0;
            if (val > max) val = max;
            input.value = val;
            calculateTotal(); // คำนวณราคาใหม่ทุกครั้งที่เปลี่ยนจำนวน
        }

        // 4. Calculate Total
        // ฟังก์ชันคำนวณราคารวมแบบ Real-time
        function calculateTotal() {
            let total = 0;
            
            // Court Price
            // ถ้ามีการเลือกสนามแล้ว ให้เอาราคาสนามบวกเข้าไปก่อน
            if (selectedBtn) {
                total += parseFloat(selectedBtn.dataset.price);
            }

            // Equipment Price
            // วนลูปเช็คทุกช่อง input ของอุปกรณ์ ว่ามีการกรอกจำนวนหรือไม่
            let equipDetails = [];
            document.querySelectorAll('.eq-qty-val').forEach(input => {
                if (input.value > 0) {
                    const qty = parseInt(input.value);
                    const price = parseFloat(input.dataset.price);
                    const name = input.dataset.name || 'Item'; 
                    
                    total += (qty * price); // บวกราคาอุปกรณ์
                    equipDetails.push(name + ' x ' + qty); // สร้างข้อความสรุป
                }
            });

            // Update Display
            // อัปเดตตัวเลขราคารวม
            document.getElementById('sumTotal').innerText = total.toLocaleString() + ' THB';
            
            const eDiv = document.getElementById('equipSummary');
            const sumEquip = document.getElementById('sumEquip');
            
            // ถ้ามีอุปกรณ์ ก็แสดงรายการอุปกรณ์ในกล่อง Summary
            if (equipDetails.length > 0) {
                eDiv.style.display = 'flex';
                sumEquip.innerHTML = equipDetails.join('<br>'); 
            } else {
                eDiv.style.display = 'none';
                sumEquip.innerText = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.mobile-toggle');
    const navbar = document.getElementById('navbar');
    const body = document.body;

    // Toggle Mobile Menu
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            navbar.classList.toggle('menu-open');
            
            // Toggle Icon (Hamburger to Close)
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
    </script>



</body>
</html>