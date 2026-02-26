<?php
// อันดับแรกเลย คือดึงเอาไฟล์ตั้งค่าระบบ (config) เข้ามา
require_once '../config.php';
// เช็คเลยว่า "เข้าสู่ระบบแล้วหรือยัง?" ถ้ายังไม่ล็อกอินก็ไม่ให้เข้าหน้านี้
requireLogin();
// เรียกใช้ Thunder API Helper
// ตัวนี้คือไฟล์ช่วยเหลือสำหรับเรียก API ตัวนอก (เจ้า Thunder) มาใช้งาน
require_once '../api/thunder_api.php';

 // ดึงเลข ID ของสมาชิกที่ส่งมาทาง URL (GET) แล้วแปลงเป็นตัวเลขให้เรียบร้อย
 $membershipId = intval($_GET['id'] ?? 0);

// เช็คว่ามี ID ส่งมาไหม ถ้าไม่มี (เท่ากับ 0) ก็ส่งกลับไปหน้าสมาชิกเลย
if (!$membershipId) {
    // แก้ไข: ใช้ path สัมพัทธ์
    redirect('/pages/membership.php');
}

// Fetch Membership
// ไปดึงข้อมูลการสมัครสมาชิกจากฐานข้อมูล โดยดึงทั้งข้อมูลตาราง user_membership และ membership_plans มา JOIN กัน
// เพื่อเอาชื่อแพ็กเกจ ราคา มาแสดงผล
 $stmt = $pdo->prepare("
    SELECT um.*, mp.plan_name, mp.duration_months, mp.price 
    FROM user_membership um 
    JOIN membership_plans mp ON um.plan_id = mp.plan_id 
    WHERE um.id = ? AND um.user_id = ?
");
 // รันคำสั่ง SQL โดยใส่ ID และ User ID เพื่อกันคนอื่นมาแอบดูของเรา
 $stmt->execute([$membershipId, $_SESSION['user_id']]);
 $membership = $stmt->fetch();

// ถ้าหาข้อมูลไม่เจอ (อาจจะโดนลบแล้ว หรือไม่ใช่ของเรา) ก็ส่งกลับไปหน้าสมาชิก
if (!$membership) {
    // แก้ไข: ใช้ path สัมพัทธ์
    redirect('/pages/membership.php');
}

// Get Settings (สำหรับดึงเบอร์ PromptPay)
// ดึงการตั้งค่าอื่นๆ ของระบบ โดยเฉพาะเบอร์พร้อมเพย์ที่จะเอาไว้รับเงิน
 $settingsStmt = $pdo->query("SELECT * FROM settings");
 $settings = [];
while ($row = $settingsStmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

 // เตรียมตัวแปรไว้เก็บ Error และข้อความสำเร็จ
 $errors = [];
 $successMsg = '';

// --- กำหนดค่าคงที่ตาม Request ---
// ตรงนี้เขาฟิคยอดเงินไว้ที่ 499 บาท (อาจจะเป็นค่าสมัครพิเศษ) ตามโค้ดเดิม
 $fixedAmount = 499; // ฟิกยอดเงินไว้ตามโค้ดเดิม
 // ข้อมูลบัญชีธนาคารสำรอง (กรณีไม่ใช้พร้อมเพย์)
 $bankName = "KBANK";
 $bankAccount = "1261900617";
 $bankOwner = "HIT THE COURT, LTD";

// --- Generate QR Code (Using logic from pay_booking.php) ---
// ส่วนนี้คือการสร้าง QR Code สำหรับจ่ายเงิน
 $qrDisplayUrl = null;
 $qrError = null;
 // เอาเบอร์พร้อมเพย์จาก Setting ที่ดึงมาเมื่อกี้
 $promptpayNumber = $settings['promptpay_number'] ?? '';

// ถ้ามีเบอร์พร้อมเพย์นะ
if (!empty($promptpayNumber)) {
    // เช็คว่ามี API Key สำหรับเรียก Thunder API ไหม
    $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
    
    // ถ้ามี Key
    if ($apiKey) {
        try {
            // เรียกใช้งาน Thunder Client
            $client = new ThunderClient($apiKey);
            // ใช้ยอด 49 ในการสร้าง QR โดยใส่ ref ว่า MEM ตามด้วย ID
            $result = $client->generateQR($fixedAmount, 'MEM' . $membershipId, $promptpayNumber);
            // แปลงรูป QR ที่ได้มาเป็น base64 เพื่อแสดงผลได้เลย
            $qrDisplayUrl = 'data:image/png;base64,' . $result['qr_image'];
        } catch (Exception $e) {
            // ถ้าเรียก API ไม่สำเร็จก็เก็บ Error ไว้
            $qrError = "Thunder API Error: " . $e->getMessage();
        }
    }

    // Fallback: promptpay.io
    // ถ้าไม่มี API หรือสร้าง QR ไม่ได้ ก็สลับไปใช้บริการฟรีอย่าง promptpay.io แทน
    if (empty($qrDisplayUrl)) {
        $qrDisplayUrl = "https://promptpay.io/" . $promptpayNumber . "/" . $fixedAmount . ".png";
    }
} else {
    // Fallback ถ้าไม่มีเบอร์ PromptPay ในระบบ
    // ก็จะสร้าง QR แบบ Generic ขึ้นมาแทน (เผื่อใช้นะ)
    $qrDisplayUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=00020101021129370016A0000006770101110113006612345678905802TH53037645403" . $fixedAmount . "6304ABCD";
}

// --- Handle Upload & Verification ---
// ส่วนนี้คือตอนที่ผู้ใช้กดปุ่ม "ยืนยัน" พร้อมอัปโหลดสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // เช็คก่อนว่าอัปโหลดไฟล์มาไหม ถ้าไม่มีก็โยน Error ทิ้ง
        if (empty($_FILES['slip']['name'])) throw new Exception("Please upload your payment slip");

        $file = $_FILES['slip'];
        $allowedTypes = ['image/jpeg', 'image/png'];
        
        // เช็คชนิดไฟล์ รับแค่ JPG, PNG
        if (!in_array($file['type'], $allowedTypes)) throw new Exception('Invalid file type. Please upload JPG or PNG');
        // เช็คขนาดไฟล์ ห้ามเกิน 5MB
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File too large. Maximum 5MB');

        // หาที่เก็บไฟล์ ถ้าโฟลเดอร์ไม่มีก็สร้างใหม่
        $uploadDir = UPLOAD_PATH . 'slips/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        // ตั้งชื่อไฟล์ใหม่ให้สวยงาม มี ID และเวลา
        $filename = 'mem_' . $membershipId . '_' . time() . '.jpg';
        $targetFile = $uploadDir . $filename;
        
        // ย้ายไฟล์จาก temp ไปเก็บในโฟลเดอร์จริง ถ้าย้ายไม่สำเร็จก็โยน Error
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) throw new Exception('Failed to upload file.');

        // --- CALL VERIFY API ---
        // ตอนนี้ถึงเวลาเอาสลิปไปให้ AI อ่านแล้ว (Verify)
        $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
        if (empty($apiKey)) throw new Exception("API Key not configured.");

        $client = new ThunderClient($apiKey);
        // ส่งรูปภาพไปให้ API ตรวจสอบ
        $slipData = $client->verifyByImage($targetFile);
        
        // ดึงยอดเงินที่ API อ่านได้ออกมา
        $paidAmount = floatval($slipData['amount']['amount'] ?? 0);
        
        // Logic: ตรวจสอบยอดเงินกับยอดที่ฟิกไว้ (49)
        // ถ้ายอดที่โอนน้อยกว่ายอดที่กำหนด ก็ถือว่าผิดพลาด
        if ($paidAmount < $fixedAmount) {
            throw new Exception("Amount mismatch. Required: {$fixedAmount}, Transferred: {$paidAmount}");
        }

               // --- SUCCESS: Update Database ---
       // ถ้าผ่านเงื่อนไขมาได้หมด ก็เริ่มอัปเดตฐานข้อมูล (Transaction เพื่อความปลอดภัย)
        $pdo->beginTransaction();
        
        // 1. Update Membership Status
        // อัปเดตสถานะตาราง user_membership ว่า "verified" และเก็บที่อยู่รูปสลิป
        $stmt = $pdo->prepare("UPDATE user_membership SET slip_image = ?, payment_status = 'verified' WHERE id = ?");
        $stmt->execute(['uploads/slips/' . $filename, $membershipId]);

        // 2. Update User to Premium (is_member = 1)
        // FIX: ใช้ค่า end_date จากตาราง user_membership โดยตรง จะแม่นยำกว่าคำนวณใหม่
        // ตรวจสอบให้แน่ใจว่า $membership['end_date'] มีค่า
        $expireDate = !empty($membership['end_date']) ? $membership['end_date'] : date('Y-m-d', strtotime('+3 months'));
        
        // อัปเดตตาราง users ให้เป็นสมาชิก (is_member = 1) และบันทึกวันหมดอายุ
        $stmt = $pdo->prepare("UPDATE users SET is_member = 1, member_expire = ? WHERE user_id = ?");
        $stmt->execute([$expireDate, $_SESSION['user_id']]);

        // Update Session
        // อัปเดตตัวแปร Session ด้วยว่า "คนนี้เป็นสมาชิกแล้วนะ"
        $_SESSION['is_member'] = 1;

        // ยืนยันการเปลี่ยนแปลงทั้งหมด
        $pdo->commit();

        // แก้ไข: ใช้ path สัมพัทธ์ และส่ง success=1 เพื่อแจ้งเตือน
        redirect('/pages/membership.php?success=1');

    } catch (Exception $e) {
        // ถ้ามี Error ตรงไหน ก็เก็บข้อความ Error ไว้แสดงผล
        $errors['slip'] = $e->getMessage();
        
        // Log failure
        // และอัปเดตสถานะในฐานข้อมูลว่า "failed"
        $stmt = $pdo->prepare("UPDATE user_membership SET payment_status = 'failed' WHERE id = ?");
        $stmt->execute([$membershipId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- ตั้งชื่อหน้าเว็บ -->
    <title>Payment - Membership</title>
    <!-- โหลด Font สวยๆ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- โหลดไฟล์ CSS ที่ใช้ตกแต่งเว็บ -->
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <style>
        /* Reuse styles from pay_booking.php */
        /* CSS สำหรับหน้าจ่ายเงินโดยเฉพาะ แบ่งจอเป็น 2 ฝั่ง */
        .payment-split { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .payment-split { grid-template-columns: 1fr; } }
        /* กล่อง QR Code */
        .qr-box { background: white; padding: 2rem; border-radius: 1rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .qr-image { width: 256px; height: 256px; margin: 0 auto 1rem; background: #f3f4f6; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
        /* ส่วนอัปโหลดไฟล์ */
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 1rem; padding: 2rem; text-align: center; transition: all 0.2s; cursor: pointer; }
        .upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
        .upload-zone.has-file { border-color: var(--success); background: #f0fdf4; }
        /* กล่องข้อมูลบัญชีธนาคาร */
        .manual-info { background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; text-align: left; font-size: 0.9rem; border: 1px solid #e2e8f0; margin-top: 1rem; }
        .manual-info strong { color: var(--secondary); }
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
            <!-- หัวข้อหน้าจ่ายเงิน -->
            <div class="text-center mb-4">
                <h1>Membership Payment</h1>
                <p class="text-muted">Scan QR to pay, then upload slip for auto-verification.</p>
            </div>
            
            <!-- ถ้ามี Error จากการตรวจสอบสลิป จะแสดงขึ้นมาตรงนี้ -->
            <?php if (!empty($errors)): ?>
            <div class="toast error mb-3" style="display: block;">
                <strong>Verification Failed:</strong> <?= $errors['slip'] ?? 'An error occurred.' ?>
            </div>
            <?php endif; ?>
            
            <!-- แบ่งหน้าจอเป็น 2 คอลัมน์ -->
            <div class="payment-split">
                <!-- Left: Order Summary -->
                <!-- ฝั่งซ้าย: แสดงสรุปรายการที่ต้องจ่าย -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3" style="font-family: var(--font-display);">Order Summary</h3>
                        <!-- แสดงเลขที่ออเดอร์ -->
                        <div class="receipt-row"><span class="receipt-label">Order ID</span><span class="receipt-value">#<?= $membershipId ?></span></div>
                        <!-- แสดงชื่อแพ็กเกจ -->
                        <div class="receipt-row"><span class="receipt-label">Plan</span><span class="receipt-value"><?= htmlspecialchars($membership['plan_name']) ?></span></div>
                        <!-- แสดงระยะเวลา -->
                        <div class="receipt-row"><span class="receipt-label">Duration</span><span class="receipt-value"><?= $membership['duration_months'] ?> Months</span></div>
                        
                        <!-- แสดงยอดรวม (ที่ฟิกไว้ 49 บาท) -->
                        <div class="order-total" style="margin-top: 1.5rem;">
                            <span class="order-total-label">Total Amount</span>
                           
                            <span class="order-total-value"><?= number_format($fixedAmount) ?> THB</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Payment Method -->
                <!-- ฝั่งขวา: ส่วนจ่ายเงินและอัปโหลดสลิป -->
                <div>
                    <form method="POST" action="" enctype="multipart/form-data" class="card">
                        <div class="card-body">
                            <h3 class="mb-3" style="font-family: var(--font-display);">Payment</h3>
                            
                            <!-- QR Code -->
                            <!-- กล่องแสดง QR Code ให้แสกนจ่ายเงิน -->
                            <div class="qr-box mb-3">
                                <?php if ($qrDisplayUrl): ?>
                                    <div class="qr-image">
                                        <img src="<?= $qrDisplayUrl ?>" alt="QR Code">
                                    </div>
                                    <div class="text-success mb-2"><strong>Scan to Pay</strong></div>
                                    <p class="text-muted" style="font-size: 0.9rem;">
                                        Amount: <strong><?= number_format($fixedAmount) ?> THB</strong><br>
                                    </p>
                                <?php else: ?>
                                    <!-- ถ้าสร้าง QR ไม่ได้จะแจ้งเตือน -->
                                    <div class="text-danger">Cannot generate QR Code</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Bank Info (เพิ่มเติมจาก pay_booking.php) -->
                            <!-- แสดงข้อมูลบัญชีธนาคารสำรอง กรณีแสกนไม่ได้ -->
                            <div class="manual-info">
                                <p class="mb-2"><strong>Bank Transfer Details:</strong></p>
                                <p class="mb-1">Bank: <strong><?= $bankName ?></strong></p>
                                <p class="mb-1">Acc No: <strong><?= $bankAccount ?></strong></p>
                                <p class="mb-1">Name: <strong><?= $bankOwner ?></strong></p>
                            </div>

                            <!-- Upload Slip -->
                            <!-- ส่วนอัปโหลดสลิปโอนเงิน -->
                            <div class="mb-3" style="margin-top: 1.5rem;">
                                <label class="form-label"><strong>Upload Payment Slip</strong></label>
                                <!-- พื้นที่กดอัปโหลด (แหงนๆ คือกดแล้วเลือกไฟล์) -->
                                <div class="upload-zone" onclick="document.getElementById('slip-upload').click()">
                                    <input type="file" name="slip" id="slip-upload" accept=".jpg,.jpeg,.png" required style="display: none;">
                                    <div class="upload-icon" style="color: var(--primary);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                    </div>
                                    <p class="mb-0 mt-2" id="file-name" style="font-weight: 500;">Click to upload slip</p>
                                    <small class="text-muted">JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                            
                            <!-- ปุ่มกดยืนยัน -->
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                Confirm Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Script สำหรับแสดงชื่อไฟล์เมื่อเลือกรูป
        // ตรงนี้คือตอนเราเลือกรูปแล้ว จะเปลี่ยนข้อความในกล่องให้เป็นชื่อไฟล์นั้นๆ
        document.getElementById('slip-upload').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').innerText = fileName;
            document.querySelector('.upload-zone').classList.add('has-file');
        });

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
</body>
</html>