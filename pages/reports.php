<?php
// ดึงเอาไฟล์ตั้งค่าระบบ (config) เข้ามาก่อน
require_once '../config.php';
// เช็คเลยว่าล็อกอินแล้วยัง? ถ้ายังไม่ล็อกอินก็ไม่ให้เข้ามาหน้านี้
requireLogin();

// Fetch user's reports
// ไปดึงเอารายการรายงาน (Reports) ที่ User คนนี้เคยแจ้งเข้ามาทั้งหมด เรียงจากอันใหม่สุดก่อน
 $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
 $stmt->execute([$_SESSION['user_id']]);
 $reports = $stmt->fetchAll();

// เตรียมตัวแปรไว้เก็บข้อความสำเร็จและข้อผิดพลาด
 $success = '';
 $error = '';

// Check for success message from redirect
// ถ้าตอนเข้ามาหน้านี้แล้วเจอ ?success ต่อท้าย URL แสดงว่าเพิ่งส่งรายงานสำเร็จ
if (isset($_GET['success'])) {
    $success = 'Report submitted successfully!';
}

// Handle new report submission
// ส่วนนี้คือจัดการตอนที่ User กดปุ่ม "ส่งรายงาน" (Method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์มและทำความสะอาดข้อมูล (sanitize) ก่อน
    $topic = sanitize($_POST['topic'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $imagePath = '';
    
    // เช็คว่ากรอกข้อมูลครบไหม
    if (empty($topic) || empty($description)) {
        $error = 'Please fill in all required fields';
    } else {
        // Handle image upload
        // ถ้ามีการแนบรูปภาพมาด้วย
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png'];
            
            // เช็คว่าเป็นไฟล์รูปจริงไหม และขนาดไม่เกิน 5MB
            if (in_array($file['type'], $allowedTypes) && $file['size'] <= 5 * 1024 * 1024) {
                // เตรียมที่เก็บไฟล์
                $uploadDir = UPLOAD_PATH . 'reports/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                // ตั้งชื่อไฟล์ใหม่ให้ไม่ซ้ำ
                $filename = uniqid() . '_' . basename($file['name']);
                // ย้ายไฟล์ไปเก็บ
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = 'uploads/reports/' . $filename;
                }
            }
        }
        
        // สร้างรหัสรายงาน (Report Code) แบบอัตโนมัติ เช่น RP20240101...
        $reportCode = 'RP' . date('Ymd') . strtoupper(substr(uniqid(), -6));
        
        // บันทึกข้อมูลลงฐานข้อมูล สถานะตั้งเป็น 'new'
        $insertStmt = $pdo->prepare("INSERT INTO reports (report_code, user_id, topic, description, image_path, status) VALUES (?, ?, ?, ?, ?, 'new')");
        if ($insertStmt->execute([$reportCode, $_SESSION['user_id'], $topic, $description, $imagePath])) {
            // --- FIX: Redirect to prevent form resubmission on refresh ---
            // ใช้เทคนิค Redirect (PRG Pattern) เพื่อกันปัญหากด Refresh แล้วข้อมูลส่งซ้ำ
            redirect('/pages/reports.php?success=1');
        } else {
            $error = 'Failed to submit report. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Hit The Court</title>
    <!-- โหลด Font และ CSS มาแต่งหน้าตาเว็บ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- เพิ่ม style.css กลับเข้าไป -->
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/home.css">
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
            <!-- หัวข้อหน้ารายงาน -->
            <div class="section-header" style="text-align: left; margin-bottom: 2rem;">
                <h1>My Reports</h1>
                <p class="text-muted">Track your submitted issues and requests</p>
            </div>
            
            <!-- แบ่งหน้าจอเป็น 2 ฝั่ง: ซ้ายแสดงรายการ, ขวาเป็นฟอร์ม -->
            <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start;">
                <!-- Reports List -->
                <!-- ฝั่งซ้าย: แสดงรายการรายงานทั้งหมด -->
                <div>
                    <!-- แถบ Tab สำหรับกรองสถานะ (New, In Progress, Resolved) -->
                    <div class="reservations-tabs mb-4" data-tabs>
                        <button class="reservation-tab active" data-tab="new">New</button>
                        <button class="reservation-tab" data-tab="in_progress">In Progress</button>
                        <button class="reservation-tab" data-tab="resolved">Resolved</button>
                    </div>
                    
                    <!-- ส่วนแสดงรายการสถานะ "New" -->
                    <div class="reports-grid" data-panel="new">
                        <?php 
                        // กรองเอาเฉพาะรายการที่มีสถานะเป็น 'new'
                        $newReports = array_filter($reports, fn($r) => $r['status'] === 'new');
                        if (empty($newReports)): 
                        ?>
                        <!-- ถ้าไม่มีรายการ แสดงข้อความว่างๆ -->
                        <div class="card">
                            <div class="card-body text-center p-4">
                                <p class="text-muted mb-0">No new reports</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <!-- วนลูปแสดงรายงานทีละอัน -->
                            <?php foreach ($newReports as $report): ?>
                            <div class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <h4 class="report-card-title"><?= htmlspecialchars($report['topic']) ?></h4>
                                        <span class="report-card-date"><?= date('d M Y, g:i A', strtotime($report['created_at'])) ?></span>
                                    </div>
                                    <span class="report-status new">New</span>
                                </div>
                                <p class="report-card-content"><?= htmlspecialchars($report['description']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ส่วนแสดงรายการสถานะ "In Progress" (ซ่อนไว้ก่อน) -->
                    <div class="reports-grid" data-panel="in_progress" style="display: none;">
                        <?php 
                        // กรองเอาเฉพาะรายการที่มีสถานะเป็น 'in_progress'
                        $progressReports = array_filter($reports, fn($r) => $r['status'] === 'in_progress');
                        if (empty($progressReports)): 
                        ?>
                        <div class="card">
                            <div class="card-body text-center p-4">
                                <p class="text-muted mb-0">No reports in progress</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach ($progressReports as $report): ?>
                            <div class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <h4 class="report-card-title"><?= htmlspecialchars($report['topic']) ?></h4>
                                       <span class="report-card-date"><?= date('d M Y, g:i A', strtotime($report['created_at'])) ?></span>
                                    </div>
                                    <span class="report-status in_progress">In Progress</span>
                                </div>
                                <p class="report-card-content"><?= htmlspecialchars($report['description']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ส่วนแสดงรายการสถานะ "Resolved" (ซ่อนไว้ก่อน) -->
                    <div class="reports-grid" data-panel="resolved" style="display: none;">
                        <?php 
                        // กรองเอาเฉพาะรายการที่มีสถานะเป็น 'resolved'
                        $resolvedReports = array_filter($reports, fn($r) => $r['status'] === 'resolved');
                        if (empty($resolvedReports)): 
                        ?>
                        <div class="card">
                            <div class="card-body text-center p-4">
                                <p class="text-muted mb-0">No resolved reports</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php foreach ($resolvedReports as $report): ?>
                            <div class="report-card">
                                <div class="report-card-header">
                                    <div>
                                        <h4 class="report-card-title"><?= htmlspecialchars($report['topic']) ?></h4>
                                        <span class="report-card-date"><?= date('d M Y, g:i A', strtotime($report['created_at'])) ?></span>
                                    </div>
                                    <span class="report-status resolved">Resolved</span>
                                </div>
                                <p class="report-card-content"><?= htmlspecialchars($report['description']) ?></p>
                                <!-- ถ้า Admin มีการตอบกลับมา (admin_notes) ก็จะแสดงตรงนี้ -->
                                <?php if ($report['admin_notes']): ?>
                                <div class="bg-light p-3 mt-3" style="border-radius: 0.5rem;">
                                    <strong>Admin Response:</strong>
                                    <p class="mb-0 mt-1"><?= htmlspecialchars($report['admin_notes']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Submit Form -->
                <!-- ฝั่งขวา: ฟอร์มสำหรับส่งรายงานใหม่ -->
                <div class="card" style="position: sticky; top: 96px;">
                    <div class="card-body">
                        <h3 class="mb-4">Submit a Report</h3>
                        
                        <!-- แสดงข้อความสำเร็จ (ถ้ามี) -->
                        <?php if ($success): ?>
                        <div class="toast success mb-3" style="display: block;"><?= $success ?></div>
                        <?php endif; ?>
                        
                        <!-- แสดงข้อความผิดพลาด (ถ้ามี) -->
                        <?php if ($error): ?>
                        <div class="toast error mb-3" style="display: block;"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label class="form-label">Topic</label>
                                <input type="text" name="topic" class="form-control" placeholder="Brief description of the issue" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Provide detailed information about the issue..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Attach Image (Optional)</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png">
                                <span class="form-hint">JPG or PNG, max 5MB</span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">Submit Report</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- เรียกใช้ไฟล์ Javascript หลัก -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
    <script>
    // Script สำหรับจัดการการกด Tab สลับหมวดหมู่รายงาน (New, In Progress, Resolved)
    document.querySelectorAll('.reservation-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // เอา class active ออกจากทุกปุ่ม
            document.querySelectorAll('.reservation-tab').forEach(t => t.classList.remove('active'));
            // ใส่ class active ให้ปุ่มที่กด
            this.classList.add('active');
            // ซ่อนทุก Panel ที่เคยแสดงอยู่
            document.querySelectorAll('[data-panel]').forEach(p => p.style.display = 'none');
            // แสดง Panel ที่ตรงกับปุ่มที่กด
            document.querySelector(`[data-panel="${this.dataset.tab}"]`).style.display = 'grid';
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
</html>