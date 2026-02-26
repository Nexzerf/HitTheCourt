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
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,400;0,600;0,700;0,800;1,700&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/index.css">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/images/favicon-48x48.png">
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="navbar-home" id="navbar">
    <div class="navbar-container">

        <a href="/" class="navbar-logo">HIT THE <span>COURT</span></a>

        <button class="mobile-toggle" aria-label="Toggle menu">
            <div class="hamburger-box">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </button>

        <ul class="nav-menu">
            <li class="nav-item"><a href="/courts"       class="nav-link">Courts</a></li>
            <li class="nav-item"><a href="/reservations" class="nav-link">Reservations</a></li>
            <li class="nav-item"><a href="/reports"      class="nav-link">Contact Us</a></li>
            <li class="nav-item"><a href="/guidebook"    class="nav-link">Guidebook</a></li>
        </ul>

        <div class="nav-auth">
            <?php if (isLoggedIn()): ?>
                <div class="user-menu">
                    <button class="user-btn">
                        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="user-dropdown">
                        <a href="/reservations" class="dropdown-link">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            My Bookings
                        </a>
                        <a href="/profile" class="dropdown-link">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            My Profile
                        </a>
                        <a href="/membership" class="dropdown-link">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Membership
                        </a>
                        <a href="/api/auth.php?action=logout" class="dropdown-link" style="color:#ef4444;">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login"    class="btn btn-ghost">Login</a>
                <a href="/register" class="btn btn-dark">Sign Up</a>
            <?php endif; ?>
        </div>

    </div>
</nav>

<!-- ===================== HERO ===================== -->
<section class="hero">
    <!-- Replace src with your actual hero image -->


    <!-- Title top-left -->
    <div class="hero-title-wrap">
        <h1 class="hero-title">HIT THE <span>COURT</span></h1>
        <p class="hero-subtitle">Play your best. The smartest way to find, book,<br>and manage your favorite sports courts.</p>
    </div>

    <!-- Book button bottom-left -->
    <div class="hero-content">
        <div class="hero-buttons">
            <a href="<?= SITE_URL ?>/courts" class="btn btn-primary" style="padding:0.875rem 2rem; font-size:0.95rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                Book a Court
            </a>
        </div>
    </div>
</section>

<!-- ===================== ABOUT ===================== -->
<section id="about">
    <div class="container" style="padding: 0 2rem;">
        <div class="about-card">
            <!-- Logo circle -->
            <div class="about-logo-wrap">
                <img src="/images/logo.png"
                     alt="Hit The Court Logo"
                     onerror="this.parentElement.innerHTML='<div class=\'about-logo-text\'>HIT THE<br>COURT<br>SPORT CLUB</div>'">
            </div>

            <!-- Text body -->
            <div class="about-body">
                <h2>About Us</h2>
                <p>
                    Hit The Court Sport Club is a youth-focused sports club offering standard-quality courts
                    at friendly prices. Designed for teens and young adults, we provide a fun, energetic
                    space to play, practice, and hang out with friends—without breaking the bank.
                </p>
                <div class="about-tagline">"Play hard. Pay less. Hit the Court"</div>
                <p>
                    Hit The Court Sport Club brings everything you need into one place—standard-quality courts,
                    friendly prices, on-site food and drinks, and sports equipment rentals.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ===================== SERVICES ===================== -->
<section id="services">
    <div class="container">
        <div class="section-header">
            <span class="section-eyebrow">Service</span>
            <h2>Our Service</h2>
        </div>
        <div class="services-grid">

            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <!-- Tennis/badminton court top view -->
                        <rect x="2" y="3" width="20" height="18" rx="1"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <line x1="12" y1="3"  x2="12" y2="21"/>
                        <line x1="2"  y1="7"  x2="22" y2="7"/>
                        <line x1="2"  y1="17" x2="22" y2="17"/>
                    </svg>
                </div>
                <h3 class="service-title">Standard-Quality Sports Courts</h3>
                <p class="service-text">A wide variety of sports courts built to standard specifications, safe and ready for all levels of play.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                        <line x1="6"  y1="1" x2="6"  y2="4"/>
                        <line x1="10" y1="1" x2="10" y2="4"/>
                        <line x1="14" y1="1" x2="14" y2="4"/>
                    </svg>
                </div>
                <h3 class="service-title">Food &amp; Beverages</h3>
                <p class="service-text">Snacks and drinks available to keep you energized before, during, and after the game.</p>
            </div>

            <div class="service-card">
                <div class="service-icon">
                    <!-- Badminton racket + shuttle -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="9" cy="9" r="6"/>
                        <line x1="14" y1="14" x2="21" y2="21"/>
                        <path d="M7 9a2 2 0 0 1 4 0"/>
                        <circle cx="19" cy="5" r="2"/>
                        <line x1="19" y1="7" x2="19" y2="12"/>
                    </svg>
                </div>
                <h3 class="service-title">Sports Equipment Rental</h3>
                <p class="service-text">A wide range of sports equipment for rent—convenient and affordable, just show up and play.</p>
            </div>

        </div>
    </div>
</section>

<!-- ===================== WHY US ===================== -->
<section id="why-us">
    <div class="container">
        <div class="why-us-grid">

            <!-- Left: image -->
            <div class="why-us-image-wrap">
                <img src="/images/why-us.png"
                     alt="Hit The Court"
                     onerror="this.parentElement.innerHTML='<div class=\'why-us-logo-placeholder\'>HIT THE<br>COURT</div>'">
            </div>

            <!-- Right: accordion -->
            <div class="why-us-content">
                <h2>Why Us?</h2>
                <p>
                    Hit The Court Sport Club brings everything you need into one place—standard-quality
                    courts, friendly prices, on-site food and drinks, and sports equipment rentals.
                    Designed for young players who want to play hard, chill easy, and enjoy the game without limits.
                </p>

                <div class="accordion">

                    <div class="accordion-item">
                        <div class="accordion-header">
                            Opening Hours
                            <span class="accordion-arrow">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </div>
                        <div class="accordion-body">
                            <ul>
                                <li>Monday – Friday: 09:00 – 21:00</li>
                                <li>Saturday – Sunday: 09:00 – 21:00</li>
                                <li>Public Holidays: 09:00 – 21:00</li>
                            </ul>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <div class="accordion-header">
                            Full Facilities
                            <span class="accordion-arrow">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </div>
                        <div class="accordion-body">
                            <ul>
                                <li>30 Standard Sports Courts</li>
                                <li>10 Restrooms &amp; changing rooms</li>
                                <li>Parking for 100 cars</li>
                                <li>24-Hour CCTV Security System</li>
                                <li>3 AED Devices on-site</li>
                            </ul>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <div class="accordion-header">
                            Member Benefits
                            <span class="accordion-arrow">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </div>
                        <div class="accordion-body">
                            <ul>
                                <li>7 Days Advance Booking</li>
                                <li>30% Discount on 1st & 16th</li>
                                <li>Free Equipment (4 items/month)</li>
                                <li>Point Rewards System</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== FACILITIES ===================== -->
<section class="facilities-section">
    <div class="container">
        <h2>Full Facilities &amp; Member Benefit</h2>
        <p class="sub">Everything you need, all in one place</p>

        <!-- Row 1: 3 items -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <p>30 Standard Courts</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </div>
                <p>10 Restrooms</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8"  y1="12" x2="16" y2="12"/>
                    </svg>
                </div>
                <p>Parking for 100 Cars</p>
            </div>
        </div>

        <!-- Row 2: 2 items centered -->
        <div class="stats-grid-row2">
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <line x1="12" y1="8" x2="12" y2="13"/>
                        <circle cx="12" cy="16" r="0.5" fill="currentColor"/>
                    </svg>
                </div>
                <p>3 AED Devices</p>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M23 7l-7 5 7 5V7z"/>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                    </svg>
                </div>
                <p>24-Hour CCTV System</p>
            </div>
        </div>

    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">

            <div>
                <div class="footer-brand">
                    <div class="footer-logo-icon">
                        <img src="/images/logo.png" alt="logo"
                             onerror="this.parentElement.innerHTML='<div class=\'footer-logo-icon-placeholder\'>HTC</div>'">
                    </div>
                    <span class="footer-logo-text">HIT THE COURT</span>
                </div>
                <p class="footer-text">
                    <br>
                    © 2026 Hit the Court. A Chiang Mai University Experimental Project.
                </p>
            </div>

            <div class="footer-links">
                <h4>Menu</h4>
                <ul>
                    <li><a href="<?= SITE_URL ?>/guidebook">Guidebook</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="<?= SITE_URL ?>/register">Register</a></li>
                    <li><a href="<?= SITE_URL ?>/reports">Contact Us</a></li>
                </ul>
            </div>

            <div class="footer-links">
                <h4>Contact Us</h4>
                <ul>
                    <li>
                        <a href="tel:111-222-33">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.21h3a2 2 0 0 1 2 1.72"></path></svg>
                            Phone: 111-222-33
                        </a>
                    </li>
                    <li>
                        <a href="mailto:peoplecmucamt@gmail.com">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            E-mail: peoplecmucamt@gmail.com
                        </a>
                    </li>
                </ul>
            </div>

        </div>
        <div class="footer-bottom">
            <p>© 2026 Hit the Court. A Chiang Mai University Experimental Project.</p>
        </div>
    </div>
</footer>

<!-- ===================== SCRIPTS ===================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const navbar    = document.getElementById('navbar');
    const toggleBtn = document.querySelector('.mobile-toggle');
    const userMenu  = document.querySelector('.user-menu');
    const body      = document.body;

    /* Scroll shadow */
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 30);
    });

    /* Hamburger */
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            navbar.classList.toggle('menu-open');
            body.style.overflow = navbar.classList.contains('menu-open') ? 'hidden' : '';
        });
    }

    /* User dropdown on mobile */
    if (userMenu) {
        userMenu.querySelector('.user-btn').addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            }
        });
    }

    /* Click outside → close */
    document.addEventListener('click', (e) => {
        if (navbar?.classList.contains('menu-open') && !navbar.contains(e.target)) {
            navbar.classList.remove('menu-open');
            body.style.overflow = '';
        }
        if (userMenu?.classList.contains('active') && !userMenu.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });

    /* ── Accordion ── */
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const item = header.parentElement;
            const isOpen = item.classList.contains('open');

            // Close all
            document.querySelectorAll('.accordion-item').forEach(i => i.classList.remove('open'));

            // Toggle clicked
            if (!isOpen) item.classList.add('open');
        });
    });
});
</script>

</body>
</html>
