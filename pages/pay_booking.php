<?php
// อันดับแรกเลย ดึงไฟล์ตั้งค่าระบบ (config) และไฟล์ API มาเตรียมใช้งาน
require_once '../config.php';
// เช็คทันทีว่าล็อกอินแล้วยัง? ถ้ายังไม่ล็อกอินก็ไม่ให้เข้ามาหน้านี้
requireLogin();
// เรียกใช้ไฟล์ช่วยเหลือสำหรับเชื่อมต่อ API ตัวนอก (Thunder API)
require_once '../api/thunder_api.php';

// 1. Get Booking ID
// ดึงเลข ID การจองจาก URL (?) ถ้าไม่มีก็ส่งกลับไปหน้ารายการจองเลย
 $bookingId = intval($_GET['id'] ?? 0);
if (!$bookingId) redirect('/pages/reservations.php');

// 2. Fetch Booking Details
// ไปดึงข้อมูลการจองมาแสดงผล โดยดึงมาหลายตารางเลย ทั้งสนาม, กีฬา, เวลา, และเจ้าของการจอง
 $stmt = $pdo->prepare("
    SELECT b.*, s.sport_name, s.duration_minutes, c.court_number, 
           ts.start_time, ts.end_time, u.username, u.email, u.phone, u.user_id as owner_id
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    JOIN sports s ON c.sport_id = s.sport_id
    JOIN time_slots ts ON b.slot_id = ts.slot_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.booking_id = ?
");
 $stmt->execute([$bookingId]);
 $booking = $stmt->fetch();

// เช็คว่า 1. มีข้อมูลการจองไหม? 2. เจ้าของการจองใช่คนที่ล็อกอินอยู่ไหม? ถ้าไม่ใช่ก็ไล่ออกไป
if (!$booking || $booking['user_id'] != $_SESSION['user_id']) {
    redirect('/pages/reservations.php');
}

// ถ้าสถานะการจองเป็น 'paid' (จ่ายแล้ว) ก็ไม่ต้องมาจ่ายซ้ำ ส่งไปหน้าสำเร็จเลย
if ($booking['payment_status'] === 'paid') {
    redirect('/pages/reservations.php?success=1');
}

// Get Equipment
// ดึงข้อมูลอุปกรณ์ที่เช่าเพิ่มมาด้วย (ถ้ามี)
 $eqStmt = $pdo->prepare("SELECT be.*, e.eq_name FROM booking_equipment be JOIN equipment e ON be.eq_id = e.eq_id WHERE be.booking_id = ?");
 $eqStmt->execute([$bookingId]);
 $equipment = $eqStmt->fetchAll();

// Get Settings
// ดึงการตั้งค่าระบบ เช่น ชื่อธนาคาร, เลขบัญชี, เบอร์พร้อมเพย์ มาเตรียมแสดงผล
 $settingsStmt = $pdo->query("SELECT * FROM settings");
 $settings = [];
while ($row = $settingsStmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

// Variables for View
// เอาข้อมูลจาก Settings มาเก็บใส่ตัวแปรง่ายๆ ไว้ใช้ในหน้าเว็บ
 $bankName = $settings['bank_name'] ?? 'N/A';
 $bankAccount = $settings['bank_account'] ?? 'N/A';
 $bankOwner = $settings['company_name'] ?? 'N/A';

// เตรียมตัวแปรไว้เก็บ Error และรูป QR Code
 $errors = [];
 $qrDisplayUrl = null; 
 $qrError = null;

// --- Generate QR ---
// ส่วนของการสร้าง QR Code พร้อมเพย์
 $promptpayNumber = $settings['promptpay_number'] ?? '';
 // ดึงยอดเงินที่ต้องจ่ายจากข้อมูลการจอง
 $amount = floatval($booking['total_price']);

// ถ้ามีเบอร์พร้อมเพย์ในระบบ
if (!empty($promptpayNumber)) {
    $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
    // ถ้ามี API Key
    if ($apiKey) {
        try {
            // ลองเรียก Thunder API มาสร้าง QR ให้ (มี ref code ด้วย)
            $client = new ThunderClient($apiKey);
            $result = $client->generateQR($amount, $booking['booking_code'], $promptpayNumber);
            $qrDisplayUrl = 'data:image/png;base64,' . $result['qr_image'];
        } catch (Exception $e) {
            // Ignore error, use fallback
            // ถ้า API ดึง ก็ข้ามไปใช้วิธีสำรอง
        }
    }
    // Fallback: ถ้าสร้างจาก API ไม่ได้ ก็ไปสร้างจาก promptpay.io (บริการฟรี) แทน
    if (empty($qrDisplayUrl)) {
        $qrDisplayUrl = "https://promptpay.io/" . $promptpayNumber . "/" . $amount . ".png";
    }
} else {
    // ถ้าไม่มีเบอร์พร้อมเพย์ในระบบ ก็แจ้ง Error
    $qrError = "PromptPay number not set.";
}

// --- Handle Payment ---
// ส่วนนี้คือตอนที่ผู้ใช้กดปุ่ม "ยืนยันการจ่ายเงิน" พร้อมอัปโหลดสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // เช็คว่าอัปโหลดไฟล์มาไหม
        if (empty($_FILES['slip_image']['name'])) throw new Exception("Please upload your payment slip");

        $file = $_FILES['slip_image'];
        // เช็คชนิดไฟล์ว่าเป็นรูปไหม
        if (!in_array($file['type'], ['image/jpeg', 'image/png'])) throw new Exception('Invalid file type');
        // เช็คขนาดไฟล์ ห้ามเกิน 5MB
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File too large');

        // เตรียมที่เก็บไฟล์ ถ้าไม่มีโฟลเดอร์ก็สร้างใหม่
        $uploadDir = UPLOAD_PATH . 'slips/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        // ตั้งชื่อไฟล์ใหม่ให้สวยงาม
        $filename = 'bk_' . $bookingId . '_' . time() . '.jpg';
        $targetFile = $uploadDir . $filename;
        
        // ย้ายไฟล์ไปเก็บ
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) throw new Exception('Failed to upload file.');

        // เรียก API เพื่ออ่านข้อมูลจากสลิป
        $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
        if (empty($apiKey)) throw new Exception("API Key missing.");

        $client = new ThunderClient($apiKey);
        $slipData = $client->verifyByImage($targetFile);
        
        // --- VALIDATION ---
        // ดึงยอดเงินที่อ่านได้จากสลิป กับยอดที่ต้องจ่ายจริง
        $paidAmount = floatval($slipData['amount']['amount'] ?? 0);
        $requiredAmount = floatval($booking['total_price']);
        
        // 1. Check Amount
        // เช็คว่ายอดตรงไหม? ถ้าโอนน้อยกว่าที่ต้องจ่าย ก็ Error
        if ($paidAmount < $requiredAmount) {
            throw new Exception("Amount mismatch. Required: {$requiredAmount}, Transferred: {$paidAmount}");
        }

        // 2. Check PromptPay Number (Logic เดียวกับ Membership)
        // เช็คว่าโอนมาบัญชีที่ถูกต้องไหม?
        // ทำการดึงค่าจากหลายๆ Key ที่เป็นไปได้ของ Thunder API (เผื่อมันชื่อต่างกัน)
        $slipAccountValue = 
            ($slipData['receiver']['account']['value'] ?? '') ?: 
            ($slipData['receiver']['account']['id'] ?? '') ?: 
            ($slipData['receiver']['account']['account_number'] ?? '') ?: 
            ($slipData['receiver']['account']['proxy_value'] ?? '');

        $expectedAccountValue = $settings['promptpay_number'] ?? '';

        // ตัดเครื่องหมายพิเศษออก เหลือแค่ตัวเลข เพื่อเปรียบเทียบง่ายๆ
        $cleanSlipAcc = preg_replace('/[^0-9]/', '', $slipAccountValue);
        $cleanExpectedAcc = preg_replace('/[^0-9]/', '', $expectedAccountValue);

        // ถ้า API อ่านหมายเลขไม่ได้จริงๆ (พบว่างเปล่า) ให้ข้ามการตรวจสอบนี้ไป เพื่อไม่ให้ Error
        // แต่ถ้าอ่านได้ และตัวเลขไม่ตรง ให้ Error (โอนผิดบัญชี)
        if (!empty($cleanSlipAcc) && ($cleanSlipAcc !== $cleanExpectedAcc)) {
             throw new Exception("Invalid recipient. Expected: '{$expectedAccountValue}', Found: '{$slipAccountValue}'");
        }
        
        // Optional: Log ถ้าหาก API ไม่ส่งค่าหมายเลขมา
        // if (empty($cleanSlipAcc)) { error_log("API did not return account number for booking $bookingId"); }

        // --- SUCCESS ---
        // ถ้าผ่านเงื่อนไขทั้งหมด ก็เริ่มอัปเดตฐานข้อมูล
        $pdo->beginTransaction();
        // 1. บันทึกประวัติการจ่ายเงิน
        $pdo->prepare("INSERT INTO payments (booking_id, payment_method, amount, slip_image, payment_status, verified_at) VALUES (?, 'promptpay', ?, ?, 'verified', NOW())")
            ->execute([$bookingId, $paidAmount, 'uploads/slips/' . $filename]);
        // 2. อัปเดตสถานะการจองเป็น 'paid'
        $pdo->prepare("UPDATE bookings SET payment_status = 'paid', expires_at = NULL WHERE booking_id = ?")->execute([$bookingId]);
        // 3. เพิ่มแต้มให้ User และนับจำนวนการจอง
        $pdo->prepare("UPDATE users SET points = points + 10, total_bookings = total_bookings + 1 WHERE user_id = ?")->execute([$booking['user_id']]);
        $pdo->commit();

        // ส่งไปหน้าสำเร็จ
        redirect('/pages/payment_success.php?booking=' . $booking['booking_code']);

    } catch (Exception $e) {
        // ถ้ามี Error ตรงไหน ก็เก็บไว้แสดงผล
        $errors['slip'] = $e->getMessage();
    }
}

 // จัดรูปแบบวันที่และเวลาให้อ่านง่าย
 $bookingDate = date('d M Y', strtotime($booking['booking_date']));
 $bookingTime = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Hit The Court</title>
    <!-- โหลด Font และ CSS มาแต่งหน้าตาเว็บ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <style>
        /* CSS สำหรับหน้าจ่ายเงิน แบ่งจอเป็น 2 ฝั่ง */
        .payment-split { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .payment-split { grid-template-columns: 1fr; } }
        /* กล่อง QR Code */
        .qr-box { background: white; padding: 2rem; border-radius: 1rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .qr-image { width: 256px; height: 256px; margin: 0 auto 1rem; background: #f3f4f6; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }
        /* ส่วนอัปโหลดไฟล์ */
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 1rem; padding: 2rem; text-align: center; transition: all 0.2s; cursor: pointer; }
        .upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
        /* กล่องข้อมูลบัญชี */
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
                <h1>Confirm & Pay</h1>
                <p class="text-muted">Scan QR to pay, then upload slip for auto-verification.</p>

            </div>
            
            <!-- ถ้ามี Error จากการตรวจสอบสลิป จะแสดงกล่องแดงตรงนี้ -->
            <?php if (!empty($errors)): ?>
            <div class="toast error mb-3" style="display: block;">
                <strong>Verification Failed:</strong> <?= $errors['slip'] ?? 'An error occurred.' ?>
            </div>
            <?php endif; ?>
            
            <!-- แบ่งหน้าจอเป็น 2 ฝั่ง -->
            <div class="payment-split">
                <!-- Left -->
                <!-- ฝั่งซ้าย: สรุปรายการจอง -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3" style="font-family: var(--font-display);">Order Summary</h3>
                        <!-- แสดงรายละเอียดต่างๆ -->
                        <div class="receipt-row"><span class="receipt-label">Booking ID</span><span class="receipt-value"><?= htmlspecialchars($booking['booking_code']) ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Sport</span><span class="receipt-value"><?= htmlspecialchars($booking['sport_name']) ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Date</span><span class="receipt-value"><?= $bookingDate ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Time</span><span class="receipt-value"><?= $bookingTime ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Court</span><span class="receipt-value">Court <?= $booking['court_number'] ?></span></div>
                        
                        <!-- ถ้ามีส่วนลด ก็แสดงสีเขียว -->
                        <?php if ($booking['discount_amount'] > 0): ?>
                        <div class="receipt-row" style="color: var(--success);">
                            <span class="receipt-label">Discount Applied</span>
                            <span class="receipt-value">-<?= number_format($booking['discount_amount']) ?> THB</span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- ถ้ามีอุปกรณ์ที่เช่าเพิ่ม -->
                        <?php if (!empty($equipment)): ?>
                            <hr style="margin: 1rem 0; border-style: dashed;">
                            <small class="text-muted">Equipment</small>
                            <!-- วนลูปแสดงรายการอุปกรณ์ -->
                            <?php foreach ($equipment as $eq): ?>
                            <div class="receipt-row" style="font-size: 0.9rem;">
                                <span class="receipt-label">
                                    <?= htmlspecialchars($eq['eq_name']) ?> x<?= $eq['quantity'] ?>
                                    <?php if($eq['subtotal'] == 0): ?>
                                        <span class="text-success">(Free)</span>
                                    <?php endif; ?>
                                </span>
                                <span class="receipt-value">
                                    <?= $eq['subtotal'] > 0 ? number_format($eq['subtotal']) . ' THB' : 'FREE' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- แสดงยอดรวมสุดท้าย -->
                        <div class="order-total" style="margin-top: 1.5rem;">
                            <span class="order-total-label">Grand Total</span>
                            <span class="order-total-value"><?= number_format($booking['total_price']) ?> THB</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right -->
                <!-- ฝั่งขวา: ส่วนจ่ายเงินและอัปโหลดสลิป -->
                <div>
                    <form method="POST" action="" enctype="multipart/form-data" class="card">
                        <div class="card-body">
                            <h3 class="mb-3" style="font-family: var(--font-display);">Payment</h3>
                            
                            <!-- กล่อง QR Code -->
                            <div class="qr-box mb-3">
                                <?php if ($qrDisplayUrl): ?>
                                    <!-- แสดง QR Code -->
                                    <div class="qr-image">
                                        <img src="<?= $qrDisplayUrl ?>" alt="QR Code">
                                    </div>
                                    <div class="text-success mb-2"><strong>Scan to Pay</strong></div>
                                    <p class="text-muted" style="font-size: 0.9rem;">
                                        Amount: <strong><?= number_format($booking['total_price']) ?> THB</strong><br>
                                        Ref: <?= $booking['booking_code'] ?>
                                    </p>
                                <?php else: ?>
                                    <!-- ถ้าสร้าง QR ไม่ได้ แสดง Error -->
                                    <div class="text-danger mb-2" style="font-weight: 600;">
                                        Cannot generate QR Code
                                    </div>
                                    <p class="text-muted small mb-3">Reason: <?= htmlspecialchars($qrError ?? 'Unknown') ?></p>
                                <?php endif; ?>

                                <!-- แสดงข้อมูลบัญชีธนาคารสำรอง -->
                                <div class="manual-info">
                                    <p class="mb-2"><strong>Bank Transfer Details:</strong></p>
                                    <p class="mb-1">Bank: <strong><?= htmlspecialchars($bankName) ?></strong></p>
                                    <p class="mb-1">Acc No: <strong><?= htmlspecialchars($bankAccount) ?></strong></p>
                                    <p class="mb-1">Name: <strong><?= htmlspecialchars($bankOwner) ?></strong></p>
                                </div>
                            </div>
                            
                            <!-- ส่วนอัปโหลดสลิป -->
                            <div class="mb-3">
                                <label class="form-label"><strong>Upload Payment Slip</strong></label>
                                <!-- พื้นที่กดอัปโหลด -->
                                <div class="upload-zone" onclick="document.getElementById('slip-upload').click()">
                                    <input type="file" name="slip_image" id="slip-upload" accept=".jpg,.jpeg,.png" required style="display: none;">
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
        // Script เล็กน้อยสำหรับเวลาเลือกไฟล์แล้ว ให้เปลี่ยนชื่อไฟล์มาแสดงในกล่อง
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