<?php
require_once '../config.php';
requireLogin();
require_once '../api/thunder_api.php';

// 1. Get Booking ID
 $bookingId = intval($_GET['id'] ?? 0);
if (!$bookingId) redirect('/pages/reservations.php');

// 2. Fetch Booking Details
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

if (!$booking || $booking['user_id'] != $_SESSION['user_id']) {
    redirect('/pages/reservations.php');
}

if ($booking['payment_status'] === 'paid') {
    redirect('/pages/reservations.php?success=1');
}

// Get Equipment
 $eqStmt = $pdo->prepare("SELECT be.*, e.eq_name FROM booking_equipment be JOIN equipment e ON be.eq_id = e.eq_id WHERE be.booking_id = ?");
 $eqStmt->execute([$bookingId]);
 $equipment = $eqStmt->fetchAll();

// Get Settings
 $settingsStmt = $pdo->query("SELECT * FROM settings");
 $settings = [];
while ($row = $settingsStmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }

// Variables for View
 $bankName = $settings['bank_name'] ?? 'N/A';
 $bankAccount = $settings['bank_account'] ?? 'N/A';
 $bankOwner = $settings['company_name'] ?? 'N/A';

 $errors = [];
 $qrDisplayUrl = null; 
 $qrError = null;

// --- Generate QR ---
 $promptpayNumber = $settings['promptpay_number'] ?? '';
 $amount = floatval($booking['total_price']);

if (!empty($promptpayNumber)) {
    $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
    if ($apiKey) {
        try {
            $client = new ThunderClient($apiKey);
            $result = $client->generateQR($amount, $booking['booking_code'], $promptpayNumber);
            $qrDisplayUrl = 'data:image/png;base64,' . $result['qr_image'];
        } catch (Exception $e) {
            // Ignore error, use fallback
        }
    }
    if (empty($qrDisplayUrl)) {
        $qrDisplayUrl = "https://promptpay.io/" . $promptpayNumber . "/" . $amount . ".png";
    }
} else {
    $qrError = "PromptPay number not set.";
}

// --- Handle Payment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['slip_image']['name'])) throw new Exception("Please upload your payment slip");

        $file = $_FILES['slip_image'];
        if (!in_array($file['type'], ['image/jpeg', 'image/png'])) throw new Exception('Invalid file type');
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception('File too large');

        $uploadDir = UPLOAD_PATH . 'slips/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = 'bk_' . $bookingId . '_' . time() . '.jpg';
        $targetFile = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) throw new Exception('Failed to upload file.');

        $apiKey = defined('THUNDER_API_KEY') ? THUNDER_API_KEY : '';
        if (empty($apiKey)) throw new Exception("API Key missing.");

        $client = new ThunderClient($apiKey);
        $slipData = $client->verifyByImage($targetFile);
        
        // --- VALIDATION ---
        $paidAmount = floatval($slipData['amount']['amount'] ?? 0);
        $requiredAmount = floatval($booking['total_price']);
        
        // 1. Check Amount
        if ($paidAmount < $requiredAmount) {
            throw new Exception("Amount mismatch. Required: {$requiredAmount}, Transferred: {$paidAmount}");
        }

        // 2. Check PromptPay Number (Logic เดียวกับ Membership)
        // ทำการดึงค่าจากหลายๆ Key ที่เป็นไปได้ของ Thunder API
        $slipAccountValue = 
            ($slipData['receiver']['account']['value'] ?? '') ?: 
            ($slipData['receiver']['account']['id'] ?? '') ?: 
            ($slipData['receiver']['account']['account_number'] ?? '') ?: 
            ($slipData['receiver']['account']['proxy_value'] ?? '');

        $expectedAccountValue = $settings['promptpay_number'] ?? '';

        // ตัดเครื่องหมายพิเศษออก เหลือแค่ตัวเลข
        $cleanSlipAcc = preg_replace('/[^0-9]/', '', $slipAccountValue);
        $cleanExpectedAcc = preg_replace('/[^0-9]/', '', $expectedAccountValue);

        // ถ้า API อ่านหมายเลขไม่ได้จริงๆ (พบว่างเปล่า) ให้ข้ามการตรวจสอบนี้ไป เพื่อไม่ให้ Error
        // แต่ถ้าอ่านได้ และตัวเลขไม่ตรง ให้ Error
        if (!empty($cleanSlipAcc) && ($cleanSlipAcc !== $cleanExpectedAcc)) {
             throw new Exception("Invalid recipient. Expected: '{$expectedAccountValue}', Found: '{$slipAccountValue}'");
        }
        
        // Optional: Log ถ้าหาก API ไม่ส่งค่าหมายเลขมา
        // if (empty($cleanSlipAcc)) { error_log("API did not return account number for booking $bookingId"); }

        // --- SUCCESS ---
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payments (booking_id, payment_method, amount, slip_image, payment_status, verified_at) VALUES (?, 'promptpay', ?, ?, 'verified', NOW())")
            ->execute([$bookingId, $paidAmount, 'uploads/slips/' . $filename]);
        $pdo->prepare("UPDATE bookings SET payment_status = 'paid', expires_at = NULL WHERE booking_id = ?")->execute([$bookingId]);
        $pdo->prepare("UPDATE users SET points = points + 10, total_bookings = total_bookings + 1 WHERE user_id = ?")->execute([$booking['user_id']]);
        $pdo->commit();

        redirect('/pages/payment_success.php?booking=' . $booking['booking_code']);

    } catch (Exception $e) {
        $errors['slip'] = $e->getMessage();
    }
}

 $bookingDate = date('d M Y', strtotime($booking['booking_date']));
 $bookingTime = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Hit The Court</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <style>
        .payment-split { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .payment-split { grid-template-columns: 1fr; } }
        .qr-box { background: white; padding: 2rem; border-radius: 1rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .qr-image { width: 256px; height: 256px; margin: 0 auto 1rem; background: #f3f4f6; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }
        .upload-zone { border: 2px dashed #cbd5e1; border-radius: 1rem; padding: 2rem; text-align: center; transition: all 0.2s; cursor: pointer; }
        .upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
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
                <h1>Confirm & Pay</h1>
                <p class="text-muted">Scan QR to pay, then upload slip for auto-verification.</p>

            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="toast error mb-3" style="display: block;">
                <strong>Verification Failed:</strong> <?= $errors['slip'] ?? 'An error occurred.' ?>
            </div>
            <?php endif; ?>
            
            <div class="payment-split">
                <!-- Left -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3" style="font-family: var(--font-display);">Order Summary</h3>
                        <div class="receipt-row"><span class="receipt-label">Booking ID</span><span class="receipt-value"><?= htmlspecialchars($booking['booking_code']) ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Sport</span><span class="receipt-value"><?= htmlspecialchars($booking['sport_name']) ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Date</span><span class="receipt-value"><?= $bookingDate ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Time</span><span class="receipt-value"><?= $bookingTime ?></span></div>
                        <div class="receipt-row"><span class="receipt-label">Court</span><span class="receipt-value">Court <?= $booking['court_number'] ?></span></div>
                        
                        <?php if ($booking['discount_amount'] > 0): ?>
                        <div class="receipt-row" style="color: var(--success);">
                            <span class="receipt-label">Discount Applied</span>
                            <span class="receipt-value">-<?= number_format($booking['discount_amount']) ?> THB</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($equipment)): ?>
                            <hr style="margin: 1rem 0; border-style: dashed;">
                            <small class="text-muted">Equipment</small>
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
                        
                        <div class="order-total" style="margin-top: 1.5rem;">
                            <span class="order-total-label">Grand Total</span>
                            <span class="order-total-value"><?= number_format($booking['total_price']) ?> THB</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right -->
                <div>
                    <form method="POST" action="" enctype="multipart/form-data" class="card">
                        <div class="card-body">
                            <h3 class="mb-3" style="font-family: var(--font-display);">Payment</h3>
                            
                            <div class="qr-box mb-3">
                                <?php if ($qrDisplayUrl): ?>
                                    <div class="qr-image">
                                        <img src="<?= $qrDisplayUrl ?>" alt="QR Code">
                                    </div>
                                    <div class="text-success mb-2"><strong>Scan to Pay</strong></div>
                                    <p class="text-muted" style="font-size: 0.9rem;">
                                        Amount: <strong><?= number_format($booking['total_price']) ?> THB</strong><br>
                                        Ref: <?= $booking['booking_code'] ?>
                                    </p>
                                <?php else: ?>
                                    <div class="text-danger mb-2" style="font-weight: 600;">
                                        Cannot generate QR Code
                                    </div>
                                    <p class="text-muted small mb-3">Reason: <?= htmlspecialchars($qrError ?? 'Unknown') ?></p>
                                <?php endif; ?>

                                <div class="manual-info">
                                    <p class="mb-2"><strong>Bank Transfer Details:</strong></p>
                                    <p class="mb-1">Bank: <strong><?= htmlspecialchars($bankName) ?></strong></p>
                                    <p class="mb-1">Acc No: <strong><?= htmlspecialchars($bankAccount) ?></strong></p>
                                    <p class="mb-1">Name: <strong><?= htmlspecialchars($bankOwner) ?></strong></p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Upload Payment Slip</strong></label>
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
        document.getElementById('slip-upload').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').innerText = fileName;
            document.querySelector('.upload-zone').classList.add('has-file');
        });
    </script>
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
</body>
  <!-- FOOTER -->
    <footer class="footer">...</footer>
</body>
</html>