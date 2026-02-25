<?php
require_once '../config.php';
requireLogin();

 $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 $userStmt->execute([$_SESSION['user_id']]);
 $user = $userStmt->fetch();

 $planStmt = $pdo->query("SELECT * FROM membership_plans WHERE status = 'active' LIMIT 1");
 $plan = $planStmt->fetch();

 $errors = [];

 if (isset($_GET['success'])) {
    $success_msg = "Payment successful! Your membership is now active.";
}
// Handle Purchase Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $plan) {
    if ($user['is_member'] && $user['member_expire'] > date('Y-m-d')) {
        $errors['general'] = 'You already have an active membership.';
    } else {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . $plan['duration_months'] . ' months'));
        
        try {
            // Insert Order
            $stmt = $pdo->prepare("INSERT INTO user_membership (user_id, plan_id, start_date, end_date, total_price, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $plan['plan_id'], $startDate, $endDate, $plan['price']]);
            
            $membershipId = $pdo->lastInsertId();
            
            // แก้ไขตรงนี้: เอา SITE_URL ออก
            redirect('/pages/membership_payment.php?id=' . $membershipId);
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - Hit The Court</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/membership.css">
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


    <div class="membership-page">
        <!-- Hero -->
        <section class="membership-hero">
            <h1>Unlock Your Full Potential</h1>
            <p>Get exclusive benefits, discounts, and priority booking.</p>
        </section>

        <!-- Content -->
        <div class="plan-container">
            
            <!-- Left -->
            <div>
                <?php if ($user['is_member'] && $user['member_expire'] > date('Y-m-d')): ?>
                <div class="active-member-alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <div>
                        <strong>You are a Premium Member!</strong>
                        <div style="font-size: 0.9rem; opacity: 0.8">Valid until: <?= date('d M Y', strtotime($user['member_expire'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($errors['general'])): ?>
                <div class="active-member-alert" style="background: #FEF2F2; border-color: #FECACA; color: #991B1B;">
                    <?= $errors['general'] ?>
                </div>
                <?php endif; ?>

                <?php if ($plan): ?>
                <div class="premium-card">
                    <div class="premium-header">
                        <div class="premium-badge">Best Value</div>
                        <h2 class="premium-title"><?= htmlspecialchars($plan['plan_name']) ?></h2>
                        <p class="premium-price">
                            <?= number_format($plan['price']) ?>
                            <span>THB / <?= $plan['duration_months'] ?> Months</span>
                        </p>
                    </div>
                    <div class="premium-body">
                        <ul class="feature-list">
                            <li>
                                <div class="feature-icon">✓</div>
                                <span><strong><?= $plan['advance_booking_days'] ?> Days</strong> Advance Booking</span>
                            </li>
                            <li>
                                <div class="feature-icon">✓</div>
                                <span><strong><?= $plan['discount_day1'] ?>% Discount</strong> on 1st & 16th</span>
                            </li>
                            <li>
                                <div class="feature-icon">✓</div>
                                <span><strong>Free Equipment</strong> (<?= $plan['free_equipment_limit'] ?> items/month)</span>
                            </li>
                             <li>
                                <div class="feature-icon">✓</div>
                                <span><strong>Point Rewards</strong> System</span>
                            </li>
                        </ul>

                        <form method="POST" action="">
                            <button type="submit" class="btn-get-premium" <?= ($user['is_member'] && $user['member_expire'] > date('Y-m-d')) ? 'disabled' : '' ?>>
                                <?php if ($user['is_member'] && $user['member_expire'] > date('Y-m-d')): ?>
                                    Already Active
                                <?php else: ?>
                                    Get Premium
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right -->
            <div class="comparison-card">
                <div class="comparison-header">
                    <h3>Why Go Premium?</h3>
                </div>
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th style="text-align: center;">Normal</th>
                            <th style="text-align: center;">Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Advance Booking</td>
                            <td style="text-align: center;">2 Days</td>
                            <td style="text-align: center;" class="highlight-text">7 Days</td>
                        </tr>
                        <tr>
                            <td>Slots per Booking</td>
                            <td style="text-align: center;">1 Slot</td>
                            <td style="text-align: center;" class="highlight-text">Unlimited</td>
                        </tr>
                        <tr>
                            <td>Special Discounts</td>
                            <td style="text-align: center;">-</td>
                            <td style="text-align: center;" class="highlight-text">Up to 30%</td>
                        </tr>
                        <tr>
                            <td>Free Equipment</td>
                            <td style="text-align: center;">-</td>
                            <td style="text-align: center;" class="highlight-text">4 items/mo</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

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