<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hit The Court - Sport Club</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/index.css">
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar-home" id="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">HIT THE <span>COURT</span></a>

            <!-- Hamburger Button (mobile only) -->
            <button class="mobile-toggle" aria-label="Toggle menu">
                <div class="hamburger-box">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </button>

            <ul class="nav-menu">
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/courts.php" class="nav-link">Courts</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/reservations.php" class="nav-link">Reservations</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/reports.php" class="nav-link">Contact Us</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/guidebook.php" class="nav-link">Guidebook</a></li>
            </ul>

            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <div class="user-menu">
                        <button class="user-btn">
                            <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="user-dropdown">
                            <div style="padding: 1rem; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                <small style="color: #6c757d;">Signed in as</small>
                                <p style="font-weight: 600; color: #222;"><?= htmlspecialchars($_SESSION['username']) ?></p>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l3 6-9 12L3 9l3-6z"></path><path d="M3 9h18"></path><path d="M9 3l3 6 3-6"></path></svg>
                                    Membership
                                </a>
                                <div style="border-top: 1px solid rgba(0,0,0,0.1); margin-top: 0.5rem; padding-top: 0.5rem;">
                                    <a href="<?= SITE_URL ?>/api/auth.php?action=logout" class="dropdown-link" style="color: #dc3545;">
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="hero-content">
            <div class="hero-buttons">
                <a href="<?= SITE_URL ?>/pages/courts.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Book a Court
                </a>
                <a href="#about" class="btn btn-outline" style="padding: 1rem 2rem; font-size: 1rem;">Learn More</a>
            </div>
        </div>
    </section>

    <!-- ABOUT US SECTION -->
    <section id="about" class="section">
        <div class="container">
            <div class="section-header">
                <h2>About Us</h2>
            </div>
            <p style="text-align: center; max-width: 800px; margin: 0 auto; color: var(--gray-500); font-size: 1.1rem;">
                Hit The Court Sport Club is a youth-focused sports club offering standard-quality courts 
                at friendly prices. Designed for teens and young adults, we provide a fun, energetic 
                space to play, practice, and hang out with friends—without breaking the bank.
            </p>
            <div class="about-highlight">
                "Play hard. Pay less. Hit the Court"
            </div>
            <p style="text-align: center; max-width: 800px; margin: 0 auto; color: var(--gray-500); font-size: 1.1rem;">
                Hit The Court Sport Club brings everything you need into one place—standard-quality courts, 
                friendly prices, on-site food and drinks, and sports equipment rentals.
            </p>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section id="services" class="section" style="background: var(--gray-100);">
        <div class="container">
            <div class="section-header">
                <h2>Our Services</h2>
                <p>Why choose us?</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    </div>
                    <h3 class="service-title">Standard-Quality Sports Court</h3>
                    <p class="service-text">A wide variety of sports courts built to standard specifications, safe and ready for all levels of play.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                    </div>
                    <h3 class="service-title">Food & Beverage</h3>
                    <p class="service-text">Snacks and drinks available to keep you energized before, during, and after the game.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M4.93 4.93l14.14 14.14"></path></svg>
                    </div>
                    <h3 class="service-title">Sports Equipment Rental</h3>
                    <p class="service-text">A wide range of sports equipment for rent—convenient and affordable, just show up and play.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FACILITIES SECTION -->
    <section class="facilities-section">
        <div class="container">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-family: var(--font-display); color: white; font-size: 2rem;">Full Facilities & Member Benefit</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </div>
                    <p>Standard Courts</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                    </div>
                    <p>CCTV Security</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    </div>
                    <p>Restrooms</p>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    </div>
                    <p>Parking Spaces</p>
                </div>
            </div>
        </div>
    </section>

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
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.querySelector('.mobile-toggle');
        const navbar    = document.getElementById('navbar');
        const userMenu  = document.querySelector('.user-menu');
        const body      = document.body;

        /* Scroll effect */
        window.addEventListener('scroll', function () {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });

        /* Hamburger */
        if (toggleBtn && navbar) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                navbar.classList.toggle('menu-open');
                body.style.overflow = navbar.classList.contains('menu-open') ? 'hidden' : '';
            });
        }

        /* User dropdown (mobile tap) */
        if (userMenu) {
            userMenu.querySelector('.user-btn').addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    e.stopPropagation();
                    userMenu.classList.toggle('active');
                }
            });
        }

        /* Click outside → close */
        document.addEventListener('click', function (e) {
            if (navbar?.classList.contains('menu-open') && !navbar.contains(e.target)) {
                navbar.classList.remove('menu-open');
                body.style.overflow = '';
            }
            if (userMenu?.classList.contains('active') && !userMenu.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });
    });
    </script>

</body>
</html>