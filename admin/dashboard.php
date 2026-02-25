<?php
// อันดับแรกเลย ดึงเอาไฟล์ตั้งค่าหลัก (config) เข้ามา เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะไม่ให้เข้าหน้านี้
requireAdmin();

// --- Sidebar Data ---
// เตรียมข้อมูลสำหรับแถบด้านข้าง (Sidebar) โดยเฉพาะตัวเลขแจ้งเตือน (Badge)
// นับจำนวนรายงานที่ยังไม่ได้ดำเนินการ (สถานะ new หรือ in_progress)
 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

// --- Summary Stats ---
// ส่วนนี้คือการ "เก็บตัวเลขสถิติ" มาไว้แสดงผลบนหน้า Dashboard
// 1. นับจำนวนการจองทั้งหมดในระบบ
 $totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
// 2. คำนวณรายได้รวม (ยอดเงินที่จ่ายแล้ว status 'paid') ใช้ COALESCE เพื่อกันกรณีไม่มีข้อมูลจะได้ไม่เป็น null
 $totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
// 3. นับจำนวนสมาชิกที่ยังมีสถานะ Active อยู่
 $activeMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_member = 1")->fetchColumn();

// --- Recent Bookings ---
// ดึงข้อมูลการจองล่าสุด 5 รายการ มาแสดงในตาราง
// ต้อง JOIN หลายตารางเพื่อดึงชื่อผู้ใช้, ชื่อกีฬา มาแสดงให้ครบ
 $stmt = $pdo->query("
    SELECT b.booking_code, b.total_price, b.payment_status, b.created_at, u.username, s.sport_name 
    FROM bookings b 
    JOIN users u ON b.user_id = u.user_id 
    JOIN courts c ON b.court_id = c.court_id 
    JOIN sports s ON c.sport_id = s.sport_id 
    ORDER BY b.created_at DESC 
    LIMIT 5
");
 $recentBookings = $stmt->fetchAll();

// --- Sport Popularity ---
// ดึงข้อมูลความนิยมของกีฬา ว่ากีฬาไหนถูกจองเยอะที่สุด 5 อันดับแรก
 $sportPop = $pdo->query("
    SELECT s.sport_name, COUNT(b.booking_id) as count 
    FROM bookings b 
    JOIN courts c ON b.court_id = c.court_id 
    JOIN sports s ON c.sport_id = s.sport_id 
    GROUP BY s.sport_name 
    ORDER BY count DESC 
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <!-- โหลดฟอนต์สวยๆ และไฟล์ CSS สำหรับหน้า Admin -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
</head>
<body>
    <!-- Mobile Sidebar Toggle -->
<button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" 
         fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
</button>
<div class="admin-sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-layout">
        
             <!-- Sidebar (Consistent with other pages) -->
        <!-- ส่วนของเมนูด้านข้าง (Sidebar) ที่ใช้งานร่วมกันทุกหน้า -->
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                Hit The Court
            </div>
            
            <nav class="admin-nav">
                <!-- เมนู Dashboard (หน้าปัจจุบัน ใส่ class active ไว้) -->
                <a href="dashboard.php" class="admin-nav-item active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <!-- เมนู Analytics -->
                <a href="analytics.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    Analytics
                </a>
                <!-- เมนู Sports & Courts -->
                <a href="sports.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                    Sports & Courts
                </a>
                <!-- เมนู Bookings -->
                <a href="bookings.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    Bookings
                </a>
                <!-- เมนู Payments -->
                <a href="payments.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Payments
                </a>
                <!-- เมนู Members -->
                <a href="members.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Members
                </a>
                <!-- เมนู Reports -->
                <a href="reports.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Reports
                    <!-- ตรงนี้คือมีเงื่อนไขว่า ถ้ามีรายงานที่รอดำเนินการ จะโชว์ป้ายแดงๆ ขึ้นมา -->
                    <?php if ($pendingReports > 0): ?>
                    <span style="background: #DC2626; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; margin-left: auto;"><?= $pendingReports ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            
            <!-- ปุ่ม Logout อยู่ด้านล่างสุดของ Sidebar -->
            <div style="margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <a href="<?= SITE_URL ?>/api/auth.php?action=admin_logout" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </aside>
        
        
        <!-- Main Content -->
        <!-- ส่วนเนื้อหาหลักทางด้านขวา -->
        <main class="admin-main">
            
            <div class="admin-header">
                <h1 class="admin-title">Dashboard</h1>
                <p style="color: var(--admin-muted);">Welcome back, Admin</p>
            </div>

            <!-- Summary Cards: ใช้ Class dashboard-grid และ stat-card -->
            <!-- ส่วนนี้คือแถวของ "การ์ดสรุป" ที่แสดงตัวเลขสำคัญๆ ที่ดึงมาจาก PHP ด้านบน -->
            <div class="dashboard-grid" style="margin-bottom: 1.5rem;">
                <!-- การ์ดแสดงรายได้รวม -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #DCFCE7; color: #166534;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Revenue</span>
                        <!-- แสดงตัวแปร $totalRevenue ที่คำนวณไว้ -->
                        <h3 class="stat-card-value">฿<?= number_format($totalRevenue) ?></h3>
                    </div>
                </div>
                
                <!-- การ์ดแสดงจำนวนการจองรวม -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #DBEAFE; color: #1E40AF;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Bookings</span>
                        <!-- แสดงตัวแปร $totalBookings -->
                        <h3 class="stat-card-value"><?= $totalBookings ?></h3>
                    </div>
                </div>
                
                <!-- การ์ดแสดงจำนวนสมาชิกที่ใช้งานอยู่ -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #FEE2E2; color: #991B1B;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Active Members</span>
                        <!-- แสดงตัวแปร $activeMembers -->
                        <h3 class="stat-card-value"><?= $activeMembers ?></h3>
                    </div>
                </div>
                
                <!-- การ์ดแสดงรายงานที่รอดำเนินการ -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #FEF3C7; color: #92400E;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Pending Reports</span>
                        <!-- แสดงตัวแปร $pendingReports -->
                        <h3 class="stat-card-value"><?= $pendingReports ?></h3>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <!-- ส่วนนี้จะแบ่ง Grid เป็น 2 คอลัมน์ (ซ้ายกว้าง 2 ส่วน, ขวาแคบ 1 ส่วน) -->
            <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
                
                <!-- Recent Bookings Table -->
                <!-- ส่วนซ้าย: ตารางแสดงรายการจองล่าสุด -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Recent Bookings</h3>
                    </div>
                    <div class="admin-card-body" style="padding: 0;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Sport</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- วนลูปแสดงข้อมูลการจองล่าสุดที่ดึงมา -->
                                <?php if (empty($recentBookings)): ?>
                                <tr>
                                    <!-- ถ้าไม่มีข้อมูลเลย ให้แสดงข้อความว่างๆ -->
                                    <td colspan="5" style="text-align: center; padding: 2rem;">No recent bookings.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $b): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($b['booking_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($b['username']) ?></td>
                                        <td><?= htmlspecialchars($b['sport_name']) ?></td>
                                        <td>฿<?= number_format($b['total_price']) ?></td>
                                        <td>
                                            <!-- กำหนดสีของ Badge ตามสถานะการจ่ายเงิน -->
                                            <?php 
                                            $statusClass = $b['payment_status'] == 'paid' ? 'badge-success' : 'badge-warning';
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= ucfirst($b['payment_status']) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Sport Popularity -->
                <!-- ส่วนขวา: แสดงอันดับกีฬายอดนิยม -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Popular Sports</h3>
                    </div>
                    <div class="admin-card-body">
                        <!-- วนลูปแสดงรายชื่อกีฬาและจำนวนการจอง -->
                        <?php foreach ($sportPop as $s): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--admin-border);">
                            <span style="font-weight: 500;"><?= htmlspecialchars($s['sport_name']) ?></span>
                            <!-- แสดงจำนวนการจองของกีฬานั้นๆ -->
                            <span class="badge badge-default"><?= $s['count'] ?> bookings</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>

        </main>
    </div>
    <script>
const toggle   = document.getElementById('sidebarToggle');
const sidebar  = document.querySelector('.admin-sidebar');
const overlay  = document.getElementById('sidebarOverlay');

toggle?.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
});

overlay?.addEventListener('click', () => {
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
});
</script>
</body>
</html>