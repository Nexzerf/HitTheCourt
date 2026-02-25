<?php
require_once '../config.php';

// Fetch all active sports
 $stmt = $pdo->query("SELECT * FROM sports ORDER BY sport_id ASC");
 $sports = $stmt->fetchAll();

// Image mapping for sports (fallback if no image in DB)
 $sportImages = [
    'Badminton' => 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80',
    'Football' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
    'Tennis' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=800&q=80',
    'Volleyball' => 'https://images.unsplash.com/photo-1547347298-4074fc3086f0?auto=format&fit=crop&w=800&q=80',
    'Basketball' => 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=80',
    'Table Tennis' => 'https://images.unsplash.com/photo-1534158914592-062992fbe900?auto=format&fit=crop&w=800&q=80',
    'Futsal' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=800&q=80',
];

// Duration formatting helper
function formatDuration($minutes) {
    if ($minutes >= 60) {
        $hrs = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hrs . ' Hour' . ($mins ? ' ' . $mins . ' Min' : '');
    }
    return $minutes . ' Min';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Sport - Hit The Court</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css"> <!-- Reuse Navbar/Footer styles -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/courts.css"> <!-- Page specific styles -->
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

    <!-- PAGE HERO -->
    <div class="main-content">
        <header class="page-hero">
            <div class="page-hero-content">
                <h1>SELECT YOUR SPORT COURT</h1>
                <p>Because every match matters. Start your journey here.</p>
            </div>
        </header>

        <!-- SPORTS GRID -->
        <section class="courts-section">
            <div class="courts-grid">
                <?php foreach ($sports as $sport): 
                    // Get image from mapping or default
                    $img = $sportImages[$sport['sport_name']] ?? 'https://images.unsplash.com/photo-1461896836934- voices?auto=format&fit=crop&w=800&q=80';
                ?>
                    <div class="sport-card" id="sport-<?= $sport['sport_id'] ?>">
                        <!-- Badge Example (Optional logic) -->
                        <?php if ($sport['sport_name'] == 'Badminton' || $sport['sport_name'] == 'Football'): ?>
                        <div class="sport-badge">Popular</div>
                        <?php endif; ?>

                        <div class="sport-card-image">
                            <img src="<?= $img ?>" alt="<?= htmlspecialchars($sport['sport_name']) ?>">
                        </div>
                        
                        <div class="sport-card-content">
                            <div class="sport-card-header">
                                <h2 class="sport-title"><?= htmlspecialchars($sport['sport_name']) ?></h2>
                                <div class="sport-price">
                                    <h3><?= number_format($sport['price_per_round']) ?></h3>
                                    <span>THB / Round</span>
                                </div>
                            </div>
                            
                            <div class="sport-details">
                                <div class="detail-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <?= formatDuration($sport['duration_minutes']) ?>
                                </div>
                                <div class="detail-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                    <?= $sport['total_courts'] ?> Courts
                                </div>
                            </div>

                            <div class="sport-card-footer">
                                <?php if (isLoggedIn()): ?>
                                    <a href="<?= SITE_URL ?>/pages/booking.php?sport_id=<?= $sport['sport_id'] ?>" class="btn-book">
                                        Book Now
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= SITE_URL ?>/pages/login.php" class="btn-book" style="background: var(--gray-500);">
                                        Login to Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <!-- FOOTER (Same as Homepage) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <span class="footer-logo">HIT THE COURT</span>
                    <p class="footer-text">
                        College of Arts, Media and Technology,<br>
                        Chiang Mai University<br>
                        © 2026 Hit the Court. All rights reserved.
                    </p>
                </div>
                
                <div class="footer-links">
                    <h4>Menu</h4>
                    <ul>
                        <li><a href="<?= SITE_URL ?>/pages/courts.php">Court Reservation</a></li>
                        <li><a href="<?= SITE_URL ?>/index.php#about">About Us</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/guidebook.php">Guidebook</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/reports.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Contact Us</h4>
                    <ul>
                        <li><a href="tel:111-222-3"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"></path></svg> 111-222-3</a></li>
                        <li><a href="mailto:peoplecmucamt@gmail.com"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> peoplecmucamt@gmail.com</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>HIT THE COURT</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Navbar Shadow on Scroll
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
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
</html>