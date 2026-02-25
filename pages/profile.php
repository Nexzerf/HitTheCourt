<?php
require_once '../config.php';
requireLogin();

 $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 $userStmt->execute([$_SESSION['user_id']]);
 $user = $userStmt->fetch();

 $success = '';
 $errors = [];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($phone)) $errors['phone'] = 'Phone is required';
    
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['password'] = 'Passwords do not match';
        }
    }
    
    if (empty($errors)) {
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$email, $phone, $hashedPassword, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$email, $phone, $_SESSION['user_id']]);
        }
        
        $success = 'Profile updated successfully!';
        $userStmt->execute([$_SESSION['user_id']]);
        $user = $userStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Hit The Court</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
</head>
<body style="background: #F1F5F9;">

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

    <!-- Main Content -->
    <div class="profile-container">
        
        <!-- Header Section -->
        <div class="profile-header">
            <div class="profile-avatar-large">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <p><?= htmlspecialchars($user['email']) ?></p>
                
                <?php if ($user['is_member'] && $user['member_expire'] > date('Y-m-d')): ?>
                    <div class="member-badge-profile">
                        ⭐ Premium Member (Until <?= date('d M Y', strtotime($user['member_expire'])) ?>)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="alert-success-profile">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?= $success ?>
        </div>
        <?php endif; ?>

        <!-- Grid Content -->
        <div class="profile-grid">
            
            <!-- Left Column: Stats -->
            <div>
                <div class="profile-card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"></path></svg>
                        <h3>My Stats</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-number"><?= $user['total_bookings'] ?? 0 ?></div>
                                <div class="stat-label">Total Bookings</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-number"><?= $user['points'] ?? 0 ?></div>
                                <div class="stat-label">Reward Points</div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- Right Column: Form -->
            <div>
                <div class="profile-card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <h3>Account Details</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group-profile">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input-profile" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            </div>
                            
                            <div class="form-group-profile">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input-profile" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <small style="color: var(--error);"><?= $errors['email'] ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group-profile">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input-profile" value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>

                            <hr style="margin: 2rem 0; border-color: var(--gray-100);">

                            <h4 style="margin-bottom: 1rem; font-size: 1rem;">Change Password</h4>
                            
                            <div class="form-group-profile">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input-profile" placeholder="Leave blank to keep current">
                            </div>
                            
                            <div class="form-group-profile">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input-profile" placeholder="Confirm new password">
                                <?php if (isset($errors['password'])): ?>
                                    <small style="color: var(--error);"><?= $errors['password'] ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="btn-save">Save Changes</button>
                        </form>
                    </div>
                </div>
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