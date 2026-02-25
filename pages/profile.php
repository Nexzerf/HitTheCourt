<?php
// ดึงเอาไฟล์ตั้งค่าระบบ (config) เข้ามาก่อน
require_once '../config.php';
// เช็คเลยว่าล็อกอินแล้วยัง? ถ้ายังไม่ล็อกอินก็ไม่ให้เข้าหน้านี้
requireLogin();

// ไปดึงข้อมูล "ฉัน" (User ที่ล็อกอินอยู่) จากฐานข้อมูลมาเก็บไว้ในตัวแปร $user
 $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
 $userStmt->execute([$_SESSION['user_id']]);
 $user = $userStmt->fetch();

// เตรียมตัวแปรไว้เก็บข้อความสำเร็จ และข้อผิดพลาด
 $success = '';
 $errors = [];

// ถ้ากดปุ่มบันทึก (Method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์มและทำความสะอาดข้อมูล (sanitize) ก่อน
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // เช็คเบื้องต้นว่ากรอก Email และ Phone หรือยัง
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($phone)) $errors['phone'] = 'Phone is required';

    // ถ้ามีการกรอกรหัสผ่านใหม่เข้ามา
    if (!empty($newPassword)) {
        // เช็คว่ารหัสผ่านสั้นไปไหม (ต้องมากกว่า 6 ตัว)
        if (strlen($newPassword) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            // เช็คว่ารหัสผ่านใหม่ กับ ช่องยืนยันรหัสผ่าน ตรงกันไหม
            $errors['password'] = 'Passwords do not match';
        }
    }

    // ถ้าผ่านการตรวจสอบทั้งหมด (ไม่มี error)
    if (empty($errors)) {
        // ถ้ามีการตั้งรหัสผ่านใหม่
        if (!empty($newPassword)) {
            // เข้ารหัสรหัสผ่านใหม่ให้ปลอดภัย
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            // อัปเดตข้อมูลทั้ง Email, Phone และ Password ใหม่
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$email, $phone, $hashedPassword, $_SESSION['user_id']]);
        } else {
            // ถ้าไม่ได้เปลี่ยนรหัสผ่าน อัปเดตแค่ Email กับ Phone
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$email, $phone, $_SESSION['user_id']]);
        }
        // ตั้งค่าข้อความสำเร็จ
        $success = 'Profile updated successfully!';
        // ดึงข้อมูล User ใหม่มาแสดงผล (Refresh ข้อมูล)
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
    <!-- โหลด Font และ CSS มาแต่งหน้าตาเว็บ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
</head>
<body style="background: #F1F5F9;">

    <!-- NAVBAR -->
    <!-- เมนูด้านบนของเว็บ -->
    <nav class="navbar-home" id="navbar">
        <div class="navbar-container">
            <!-- โลโก้ -->
            <a href="index.php" class="navbar-logo">HIT THE <span>COURT</span></a>

            <!-- ปุ่มเมนูสำหรับมือถือ (Hamburger) -->
            <button class="mobile-toggle" aria-label="Toggle menu">
                <div class="hamburger-box">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </button>

            <!-- เมนูหลักของเว็บ -->
            <ul class="nav-menu">
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/courts.php" class="nav-link">Courts</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/reservations.php" class="nav-link">Reservations</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/reports.php" class="nav-link">Contact Us</a></li>
                <li class="nav-item"><a href="<?= SITE_URL ?>/pages/guidebook.php" class="nav-link">Guidebook</a></li>
            </ul>

            <!-- ส่วนแสดงข้อมูล User ขวามือ -->
            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <!-- ถ้าล็อกอินแล้ว จะแสดง Avatar และชื่อ User -->
                    <div class="user-menu">
                        <button class="user-btn">
                            <!-- ดึงอักษรตัวแรกของชื่อมาทำเป็น Avatar -->
                            <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <!-- เมนู Dropdown เวลากดชื่อ -->
                        <div class="user-dropdown">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--gray-200);">
                                <small style="color: var(--gray-500);">Signed in as</small>
                                <p style="font-weight: 600;"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            </div>
                            <div style="padding: 0.5rem;">
                                <!-- ลิงก์ไปหน้าต่างๆ -->
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
                                <!-- ปุ่มล็อกเอาท์ -->
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
                    <!-- ถ้ายังไม่ได้ล็อกอิน จะแสดงปุ่ม Login/Sign Up -->
                    <a href="<?= SITE_URL ?>/pages/login.php" class="btn btn-ghost">Login</a>
                    <a href="<?= SITE_URL ?>/pages/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <!-- ส่วนเนื้อหาหลักของหน้าโปรไฟล์ -->
    <div class="profile-container">

        <!-- ส่วนหัวของโปรไฟล์: แสดงรูปใหญ่ ชื่อ และอีเมล -->
        <div class="profile-header">
            <div class="profile-avatar-large">
                <!-- แสดงอักษรตัวแรกของชื่อเป็น Avatar ขนาดใหญ่ -->
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($user['username']) ?></h1>
                <p><?= htmlspecialchars($user['email']) ?></p>
                <!-- ถ้าเป็นสมาชิก Premium และยังไม่หมดอายุ จะแสดงป้ายสถานะ -->
                <?php if ($user['is_member'] && $user['member_expire'] > date('Y-m-d')): ?>
                    <div class="member-badge-profile">
                        ⭐ Premium Member (Until <?= date('d M Y', strtotime($user['member_expire'])) ?>)
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ถ้ามีข้อความสำเร็จ (จากการกด Save) จะแสดงกล่องสีเขียวตรงนี้ -->
        <?php if ($success): ?>
        <div class="alert-success-profile">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?= $success ?>
        </div>
        <?php endif; ?>

        <!-- แบ่งเนื้อหาเป็น 2 คอลัมน์ -->
        <div class="profile-grid">

            <!-- ฝั่งซ้าย: แสดงสถิติต่างๆ -->
            <div>
                <div class="profile-card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"></path></svg>
                        <h3>My Stats</h3>
                    </div>
                    <div class="card-body">
                        <!-- แสดงจำนวนการจองและแต้มสะสม -->
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

            <!-- ฝั่งขวา: ฟอร์มแก้ไขข้อมูลส่วนตัว -->
            <div>
                <div class="profile-card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <h3>Account Details</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <!-- ช่อง Username ปิดไว้ไม่ให้แก้ (disabled) -->
                            <div class="form-group-profile">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input-profile" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            </div>
                            <!-- ช่อง Email -->
                            <div class="form-group-profile">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input-profile" value="<?= htmlspecialchars($user['email']) ?>" required>
                                <!-- ถ้ามี Error เรื่อง Email จะแสดงตรงนี้ -->
                                <?php if (isset($errors['email'])): ?>
                                    <small style="color: var(--error);"><?= $errors['email'] ?></small>
                                <?php endif; ?>
                            </div>
                            <!-- ช่องเบอร์โทร -->
                            <div class="form-group-profile">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input-profile" value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>
                            <hr style="margin: 2rem 0; border-color: var(--gray-100);">
                            <h4 style="margin-bottom: 1rem; font-size: 1rem;">Change Password</h4>
                            <!-- ช่องรหัสผ่านใหม่ (ถ้าไม่กรอกคือไม่เปลี่ยน) -->
                            <div class="form-group-profile">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-input-profile" placeholder="Leave blank to keep current">
                            </div>
                            <!-- ช่องยืนยันรหัสผ่านใหม่ -->
                            <div class="form-group-profile">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-input-profile" placeholder="Confirm new password">
                                <!-- ถ้ามี Error เรื่องรหัสผ่าน จะแสดงตรงนี้ -->
                                <?php if (isset($errors['password'])): ?>
                                    <small style="color: var(--error);"><?= $errors['password'] ?></small>
                                <?php endif; ?>
                            </div>
                            <!-- ปุ่มบันทึก -->
                            <button type="submit" class="btn-save">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

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
                <!-- เมนูลิงก์ด้านล่าง -->
                <div class="footer-links">
                    <h4>Menu</h4>
                    <ul>
                        <li><a href="<?= SITE_URL ?>/pages/courts.php">Court Reservation</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/guidebook.php">Guidebook</a></li>
                        <li><a href="<?= SITE_URL ?>/pages/reports.php">Contact Us</a></li>
                    </ul>
                </div>
                <!-- ข้อมูลติดต่อ -->
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

    <!-- Javascript สำหรับจัดการเมนูบนมือถือ -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.querySelector('.mobile-toggle');
        const navbar    = document.getElementById('navbar');
        const userMenu  = document.querySelector('.user-menu');
        const body      = document.body;

        // ถ้ากดปุ่ม Hamburger
        if (toggleBtn && navbar) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                // ให้เปิด/ปิดเมนู
                navbar.classList.toggle('menu-open');
                // ล็อค scroll ตัว body เวลาเมนูเปิดอยู่
                body.style.overflow = navbar.classList.contains('menu-open') ? 'hidden' : '';
            });
        }

        // จัดการเวลากดเมนู User (บนมือถือ)
        if (userMenu) {
            userMenu.querySelector('.user-btn').addEventListener('click', function (e) {
                // ถ้าหน้าจอแคบกว่า 768px
                if (window.innerWidth <= 768) {
                    e.stopPropagation();
                    // ให้เปิด/ปิด Dropdown
                    userMenu.classList.toggle('active');
                }
            });
        }

        // เวลากดพื้นที่นอกเมนู ให้ปิดเมนูทั้งหมด
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