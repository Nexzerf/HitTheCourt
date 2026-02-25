<?php
require_once '../config.php';
requireLogin();
// เรียกใช้ Thunder API Helper
require_once '../api/thunder_api.php';

 $membershipId = intval($_GET['id'] ?? 0);

if (!$membershipId) {
    // แก้ไข: ใช้ path สัมพัทธ์
    redirect('/pages/membership.php');
}

// Fetch Membership
 $stmt = $pdo->prepare("
    SELECT um.*, mp.plan_name, mp.duration_months, mp.price 
    FROM user_membership um 
    JOIN membership_plans mp ON um.plan_id = mp.plan_id 
    WHERE um.id = ? AND um.user_id = ?
");
 $stmt->execute([$membershipId, $_SESSION['user_id']]);
 $membership = $stmt->fetch();

if (!$membership) {
    // แก้ไข: ใช้ path สัมพัทธ์
    redirect('/pages/membership.php');
}

// Get Settings (สำหรับดึงเบอร์ PromptPay)
 $settingsStmt = $pdo->query("SELECT * FROM settings");
 $settings = [];
while ($row = $settingsStmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

 $errors = [];
 $successMsg = '';

// --- กำหนดค่าคงที่ตาม Request ---
 $fixedAmount = 49; // ฟิกยอดเงินไว้ตามโค้ดเดิม
 $bankName = "KBANK";
 $bankAccount = "1261900617";
 $bankOwner = "HIT THE COURT, LTD";

// --- Generate QR Code (Using logic from pay_booking.php) ---
 $qrDisplayUrl = null;
 $qrError = null;
 $promptpayNumber = $settings['promptpay_number'] ?? '';

if (!empty($promptpayNumber)) {
    $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
    
    if ($apiKey) {
        try {
            $client = new ThunderClient($apiKey);
            // ใช้ยอด 49 ในการสร้าง QR
            $result = $client->generateQR($fixedAmount, 'MEM' . $membershipId, $promptpayNumber);
            $qrDisplayUrl = 'data:image/png;base64,' . $result['qr_image'];
        } catch (Exception $e) {
            $qrError = "Thunder API Error: " . $e->getMessage();
        }
    }

    // Fallback: promptpay.io
    if (empty($qrDisplayUrl)) {
        $qrDisplayUrl = "https://promptpay.io/" . $promptpayNumber . "/" . $fixedAmount . ".png";
    }
} else {
    // Fallback ถ้าไม่มีเบอร์ PromptPay ในระบบ
    $qrDisplayUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=00020101021129370016A0000006770101110113006612345678905802TH53037645403" . $fixedAmount . "6304ABCD";
}

// --- Handle Upload & Verification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['slip']['name'])) throw new Exception("Please upload your payment slip");

        $file = $_FILES['slip'];
        $allowedTypes = ['image/jpeg', 'image/png'];
        
        if (!in_array($file['type'], $allowedTypes)) throw new Exception('Invalid file type. Please upload JPG or PNG');
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File too large. Maximum 5MB');

        $uploadDir = UPLOAD_PATH . 'slips/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = 'mem_' . $membershipId . '_' . time() . '.jpg';
        $targetFile = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) throw new Exception('Failed to upload file.');

        // --- CALL VERIFY API ---
        $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
        if (empty($apiKey)) throw new Exception("API Key not configured.");

        $client = new ThunderClient($apiKey);
        $slipData = $client->verifyByImage($targetFile);
        
        $paidAmount = floatval($slipData['amount']['amount'] ?? 0);
        
        // Logic: ตรวจสอบยอดเงินกับยอดที่ฟิกไว้ (49)
        if ($paidAmount < $fixedAmount) {
            throw new Exception("Amount mismatch. Required: {$fixedAmount}, Transferred: {$paidAmount}");
        }

               // --- SUCCESS: Update Database ---
        $pdo->beginTransaction();
        
        // 1. Update Membership Status
        $stmt = $pdo->prepare("UPDATE user_membership SET slip_image = ?, payment_status = 'verified' WHERE id = ?");
        $stmt->execute(['uploads/slips/' . $filename, $membershipId]);

        // 2. Update User to Premium (is_member = 1)
        // FIX: ใช้ค่า end_date จากตาราง user_membership โดยตรง จะแม่นยำกว่าคำนวณใหม่
        // ตรวจสอบให้แน่ใจว่า $membership['end_date'] มีค่า
        $expireDate = !empty($membership['end_date']) ? $membership['end_date'] : date('Y-m-d', strtotime('+3 months'));
        
        $stmt = $pdo->prepare("UPDATE users SET is_member = 1, member_expire = ? WHERE user_id = ?");
        $stmt->execute([$expireDate, $_SESSION['user_id']]);

        // Update Session
        $_SESSION['is_member'] = 1;

        $pdo->commit();

        // แก้ไข: ใช้ path สัมพัทธ์ และส่ง success=1 เพื่อแจ้งเตือน
        redirect('/pages/membership.php?success=1');

    } catch (Exception $e) {
        $errors['slip'] = $e->getMessage();
        
        // Log failure
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
    <title>Payment - Membership</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <style>
        /* Reuse styles from pay_booking.php */
        .payment-split { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .payment-split { grid-template-columns: 1fr; } }
        .qr-box { background: white; padding: 2rem; border-radius: 1rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .qr-image { width: 256px; height: 256px; margin: 0 auto 1rem; background: #f3f4f6; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1); }
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 1rem; padding: 2rem; text-align: center; transition: all 0.2s; cursor: pointer; }
        .upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
        .upload-zone.has-file { border-color: var(--success); background: #f0fdf4; }
        .manual-info { background: #f8fafc; padding: 1.5rem; border-radius: 0.75rem; text-align: left; font-size: 0.9rem; border: 1px solid #e2e8f0; margin-top: 1rem; }
        .manual-info strong { color: var(--secondary); }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-home" id="navbar">
    <div class="navbar-container">
        <a href="index.php" class="navbar-logo">HIT THE <span>COURT</span></a>
        
        <!-- Hamburger Button (Added) -->
        <button class="hamburger" id="hamburger-btn" aria-label="Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Menu with Dropdown -->
        <ul class="nav-menu" id="nav-menu"> <!-- เพิ่ม ID เข้าไป -->
            
            <li class="nav-item">
                <a href="<?= SITE_URL ?>/pages/courts.php" class="nav-link">Courts</a>
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
        <div class="nav-auth">
            <?php if (isLoggedIn()): ?>
                <div class="user-menu">
                    <button class="user-btn">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                        </div>
                        <span class="username-text"><?= htmlspecialchars($_SESSION['username']) ?></span>
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
                <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-ghost">Login</a>
                <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</nav>


    <main class="section" style="padding-top: 7rem;">
        <div class="container">
            <div class="text-center mb-4">
                <h1>Membership Payment</h1>
                <p class="text-muted">Scan QR to pay, then upload slip for auto-verification.</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="toast error mb-3" style="display: block;">
                <strong>Verification Failed:</strong> <?= $errors['slip'] ?? 'An error occurred.' ?>
            </div>
            <?php endif; ?>
            
            <div class="payment-split">
                <!-- Left: Order Summary -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3" style="font-family: var(--font-display);">Order Summary</h3>
                        <div class="receipt-row"><span class="receipt-label">Order ID</span><span class="receipt-value">#<?= $membershipId ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Plan</span><span class="receipt-value"><?= htmlspecialchars($membership['plan_name']) ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Duration</span><span class="receipt-value"><?= $membership['duration_months'] ?> Months</span></div>
                        
                        <div class="order-total" style="margin-top: 1.5rem;">
                            <span class="order-total-label">Total Amount</span>
                           
                            <span class="order-total-value"><?= number_format($fixedAmount) ?> THB</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Payment Method -->
                <div>
                    <form method="POST" action="" enctype="multipart/form-data" class="card">
                        <div class="card-body">
                            <h3 class="mb-3" style="font-family: var(--font-display);">Payment</h3>
                            
                            <!-- QR Code -->
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
                                    <div class="text-danger">Cannot generate QR Code</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Bank Info (เพิ่มเติมจาก pay_booking.php) -->
                            <div class="manual-info">
                                <p class="mb-2"><strong>Bank Transfer Details:</strong></p>
                                <p class="mb-1">Bank: <strong><?= $bankName ?></strong></p>
                                <p class="mb-1">Acc No: <strong><?= $bankAccount ?></strong></p>
                                <p class="mb-1">Name: <strong><?= $bankOwner ?></strong></p>
                            </div>

                            <!-- Upload Slip -->
                            <div class="mb-3" style="margin-top: 1.5rem;">
                                <label class="form-label"><strong>Upload Payment Slip</strong></label>
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
        document.getElementById('slip-upload').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').innerText = fileName;
            document.querySelector('.upload-zone').classList.add('has-file');
        });
    </script>
    
</body>
 <!-- FOOTER -->
    <footer class="footer">...</footer>
</body>
 <script>
        document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger-btn');
    const navMenu = document.getElementById('nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    // ปิดเมนูเมื่อคลิกข้างนอก (Optional)
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !hamburger.contains(e.target)) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    });
});
</script>
</html>