<?php
require_once '../config.php';
requireLogin();

 $bookingCode = sanitize($_GET['booking'] ?? '');

// Fetch booking
 $stmt = $pdo->prepare("
    SELECT b.*, s.sport_name, c.court_number, ts.start_time, ts.end_time
    FROM bookings b
    JOIN sports s ON b.court_id IN (SELECT court_id FROM courts WHERE sport_id = s.sport_id)
    JOIN courts c ON b.court_id = c.court_id
    JOIN time_slots ts ON b.slot_id = ts.slot_id
    WHERE b.booking_code = ? AND b.user_id = ?
");
 $stmt->execute([$bookingCode, $_SESSION['user_id']]);
 $booking = $stmt->fetch();

if (!$booking) {
    redirect('/pages/reservations.php');
}

// Get equipment
 $eqStmt = $pdo->prepare("SELECT be.*, e.eq_name FROM booking_equipment be JOIN equipment e ON be.eq_id = e.eq_id WHERE be.booking_id = ?");
 $eqStmt->execute([$booking['booking_id']]);
 $equipment = $eqStmt->fetchAll();

// Update user points if member
if ($booking['payment_status'] === 'paid') {
    // Already processed
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful - Hit The Court</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
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

    <main class="section">
        <div class="container">
            <div class="booking-success">
                <div class="success-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                
                <h1 class="success-title">Booking Submitted!</h1>
                <p class="success-message">
                    Your payment is being verified. We will confirm your booking shortly.<br>
                    Please show this digital receipt at the front desk upon arrival.
                </p>
                
                <div class="receipt-card">
                    <div class="receipt-header">
                        <h3 style="color: white; margin: 0;">Digital Receipt</h3>
                    </div>
                    <div class="receipt-body">
                        <div class="receipt-row">
                            <span class="receipt-label">Booking ID</span>
                            <span class="receipt-value"><?= htmlspecialchars($booking['booking_code']) ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Sport</span>
                            <span class="receipt-value"><?= htmlspecialchars($booking['sport_name']) ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Court</span>
                            <span class="receipt-value">Court <?= $booking['court_number'] ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Date & Time</span>
                            <span class="receipt-value">
                                <?= date('d M Y', strtotime($booking['booking_date'])) ?> 
                                (<?= date('g:i A', strtotime($booking['start_time'])) ?> - <?= date('g:i A', strtotime($booking['end_time'])) ?>)
                            </span>
                        </div>
                        
                        <?php if (!empty($equipment)): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Equipment</span>
                            <span class="receipt-value">
                                <?= implode(', ', array_map(function($e) { return $e['eq_name'] . ' (x' . $e['quantity'] . ')'; }, $equipment)) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="order-total" style="margin-top: 1rem;">
                            <span class="order-total-label">Total Paid</span>
                            <span class="order-total-value"><?= number_format($booking['total_price']) ?> THB</span>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2 mt-4" style="justify-content: center;">
                    <button onclick="window.print()" class="btn btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Print Receipt
                    </button>
                    <a href="<?= SITE_URL ?>/" class="btn btn-primary">Back to Homepage</a>
                </div>
                
                <div class="card mt-4" style="background: #FEF3C7; border: none;">
                    <div class="card-body text-center">
                        <p style="margin: 0; color: #92400E;">
                            <strong>Important:</strong> Please arrive at least 15 minutes before your session starts.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
 <!-- FOOTER -->
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