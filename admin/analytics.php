<?php
// อันดับแรกเลย ดึงเอาไฟล์ตั้งค่าหลัก (config) มาใช้ เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะโดนไล่ออกไปหน้าล็อกอิน ป้องกันคนแอบเข้า
requireAdmin();

// --- Sidebar Data ---
// เตรียมข้อมูลสำหรับแถบด้านข้าง (Sidebar) โดยเฉพาะตัวเลขแจ้งเตือน (Badge)
// นับจำนวนรายงานที่ยังไม่ได้ดำเนินการ (สถานะ new หรือ in_progress) เพื่อไปแสดงเป็นวงกลมแดงๆ
 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

// --- Filter Logic ---
// รับค่าตัวกรอง (Filter) จาก URL ว่าผู้ใช้เลือกดูช่วงเวลาไหน (รายวัน, รายสัปดาห์ ฯลฯ)
// ใช้ฟังก์ชัน sanitize เพื่อทำความสะอาดข้อมูลก่อน เพื่อความปลอดภัย
 $period = sanitize($_GET['period'] ?? 'monthly');
 $startDate = $_GET['start_date'] ?? '';
 $endDate = $_GET['end_date'] ?? '';

// Set Date Range based on Period
// สร้างเงื่อนไขสำหรับการค้นหา (SQL WHERE Clause) เบื้องต้นว่าต้องเป็นการจองที่ "จ่ายเงินแล้ว" เท่านั้น
 $sqlWhere = "b.payment_status = 'paid'"; // ใช้ alias 'b' นำหน้า
 $dateFormat = '%Y-%m-%d'; 
 $title = "Overview";

// สวิตช์นี้ไว้ "ปรับช่วงเวลา" ตามที่ผู้ใช้เลือก ว่าจะดูข้อมูลย้อนหลังแค่ไหน
switch ($period) {
    case 'daily':
        // ถ้าเลือก Daily ก็ดูเฉพาะของ "วันนี้" และจัดรูปแบบวันที่ในกราฟเป็นชั่วโมง (14:00 น.)
        $sqlWhere .= " AND DATE(b.created_at) = CURDATE()";
        $dateFormat = '%H:00'; 
        $title = "Today's Overview (" . date('d M Y') . ")";
        break;
    case 'weekly':
        // ถ้าเลือก Weekly ก็ดูของ "สัปดาห์นี้" จัดรูปแบบวันที่เป็นชื่อวัน (Mon, Tue)
        $sqlWhere .= " AND YEARWEEK(b.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $dateFormat = '%a (%d)'; 
        $title = "This Week's Overview";
        break;
    case 'monthly':
        // ถ้าเลือก Monthly (ค่าตั้งต้น) ก็ดูของ "เดือนนี้" จัดรูปแบบเป็นวันที่ (01 Jan)
        $sqlWhere .= " AND YEAR(b.created_at) = YEAR(CURDATE()) AND MONTH(b.created_at) = MONTH(CURDATE())";
        $dateFormat = '%d %b'; 
        $title = "This Month's Overview (" . date('F Y') . ")";
        break;
    case 'quarterly':
        // ถ้าเลือก Quarterly ต้องคำนวณหาว่าไตรมาสนี้เริ่มเดือนไหน แล้วดูข้อมูลตั้งแต่ต้นไตรมาสจนถึงปัจจุบัน
        $currentMonth = date('n');
        $quarterStartMonth = floor(($currentMonth - 1) / 3) * 3 + 1;
        $startDateQ = date('Y-' . str_pad($quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
        $sqlWhere .= " AND b.created_at >= '$startDateQ'";
        $dateFormat = '%M'; 
        $title = "Quarterly Overview";
        break;
    case 'yearly':
        // ถ้าเลือก Yearly ก็ดูข้อมูลทั้งปีนี้
        $sqlWhere .= " AND YEAR(b.created_at) = YEAR(CURDATE())";
        $dateFormat = '%M'; 
        $title = "Yearly Overview (" . date('Y') . ")";
        break;
    case 'custom':
        // ถ้าเลือก Custom จะเช็คว่าผู้ใช้กรอกวันที่เริ่มและวันที่สิ้นสุดมาไหม แล้วไปดึงข้อมูลในช่วงนั้น
        if ($startDate && $endDate) {
            $sqlWhere .= " AND DATE(b.created_at) BETWEEN '$startDate' AND '$endDate'";
            $title = "Custom Overview ($startDate to $endDate)";
        } else {
            $title = "Custom Overview (Select Dates)";
        }
        break;
}

// --- Fetch Data for Charts ---

// 1. Revenue & Bookings Over Time
// ดึงข้อมูลสำหรับกราฟเส้นและกราฟแท่ง โดยจัดกลุ่มตามช่วงเวลา (time_label)
// คำนวณยอดเงินรวม (SUM) และนับจำนวนการจอง (COUNT) ในแต่ละช่วง
 $stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(b.created_at, '$dateFormat') as time_label,
        SUM(b.total_price) as total_revenue,
        COUNT(b.booking_id) as total_bookings
    FROM bookings b
    WHERE $sqlWhere
    GROUP BY time_label
    ORDER BY b.created_at ASC
");
 $stmt->execute();
 $timelineData = $stmt->fetchAll();

// แยกข้อมูลออกมาเป็น Array สำหรับส่งให้ JavaScript วาดกราฟ
 $labels = [];
 $revenueData = [];
 $bookingCountData = [];

foreach ($timelineData as $row) {
    $labels[] = $row['time_label'];
    $revenueData[] = $row['total_revenue'];
    $bookingCountData[] = $row['total_bookings'];
}

// 2. Bookings by Sport (Pie Chart)
// ดึงข้อมูลสำหรับกราฟวงกลม (Pie Chart) ว่ากีฬาแต่ละประเภทถูกจองไปเท่าไหร่
// ต้องไป Join ตาราง bookings, courts และ sports เพื่อเอาชื่อกีฬามาแสดง
 $stmtSport = $pdo->prepare("
    SELECT s.sport_name, COUNT(b.booking_id) as count
    FROM bookings b
    JOIN courts c ON b.court_id = c.court_id
    JOIN sports s ON c.sport_id = s.sport_id
    WHERE $sqlWhere
    GROUP BY s.sport_name
");
 $stmtSport->execute();
 $sportData = $stmtSport->fetchAll();

 $sportLabels = [];
 $sportCounts = [];
foreach ($sportData as $row) {
    $sportLabels[] = $row['sport_name'];
    $sportCounts[] = $row['count'];
}

// 3. Summary Cards
// คำนวณตัวเลขสรุป (KPI) สำหรับการ์ดด้านบน
 $totalRevenue = array_sum($revenueData); // รวมรายได้ทั้งหมด
 $totalBookings = array_sum($bookingCountData); // รวมการจองทั้งหมด
 $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); // นับผู้ใช้ทั้งหมดในระบบ
 $newUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(); // นับผู้ใช้ใหม่วันนี้

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin</title>
    <!-- ดึงฟอนต์สวยๆ มาใช้ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- ดึงไฟล์ CSS สำหรับหน้า Admin -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <!-- Chart.js Library -->
    <!-- โหลด Library ตัวนี้มาช่วยวาดกราฟสวยๆ แบบ Interactive -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <!-- ส่วนนี้คือแถบเมนูด้านข้าง (Sidebar) ที่ใช้สำหรับหน้า Admin ทุกหน้า -->
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
                <!-- เมนู Dashboard -->
                <a href="dashboard.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <!-- เมนู Analytics (หน้าปัจจุบัน เลยใส่ class active ไว้) -->
                <a href="analytics.php" class="admin-nav-item active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    Analytics
                </a>
                <!-- เมนูจัดการกีฬาและสนาม -->
                <a href="sports.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                    Sports & Courts
                </a>
                <!-- เมนูจัดการการจอง -->
                <a href="bookings.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    Bookings
                </a>
                <!-- เมนูดูประวัติการชำระเงิน -->
                <a href="payments.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Payments
                </a>
                <!-- เมนูจัดการสมาชิก -->
                <a href="members.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Members
                </a>
                <!-- เมนูรายงานปัญหา -->
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
                <h1 class="admin-title">Analytics & Statistics</h1>
                <!-- แสดงชื่อช่วงเวลาที่กำลังดูอยู่ เช่น "This Month's Overview" -->
                <p style="color: var(--admin-muted);"><?= $title ?></p>
            </div>

            <!-- Filter Controls -->
            <!-- ส่วนของฟอร์มกรองข้อมูล -->
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <div class="admin-card-body" style="padding: 1rem 1.5rem;">
                    <form method="GET" class="filter-bar">
                        <div class="filter-group">
                            <label class="filter-label">Period</label>
                            <!-- Dropdown สำหรับเลือกช่วงเวลา มี onchange เพื่อเปิดปิดช่องกรอกวันที่เอง -->
                            <select name="period" class="admin-input" onchange="toggleCustomDates(this.value)">
                                <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>Daily (Today)</option>
                                <option value="weekly" <?= $period == 'weekly' ? 'selected' : '' ?>>Weekly (This Week)</option>
                                <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>Monthly (This Month)</option>
                                <option value="quarterly" <?= $period == 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                <option value="yearly" <?= $period == 'yearly' ? 'selected' : '' ?>>Yearly</option>
                                <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom</option>
                            </select>
                        </div>
                        
                        <!-- ช่องเลือกวันที่เริ่ม-สิ้นสุด จะซ่อนไว้ก่อน จะแสดงก็ต่อเมื่อเลือก Custom -->
                        <div id="custom-dates" style="display: <?= $period == 'custom' ? 'flex' : 'none' ?>; gap: 1rem;">
                            <div class="filter-group">
                                <label class="filter-label">Start Date</label>
                                <input type="date" name="start_date" class="admin-input" value="<?= $startDate ?>">
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">End Date</label>
                                <input type="date" name="end_date" class="admin-input" value="<?= $endDate ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <!-- ส่วนแสดงการ์ดสรุปตัวเลข (KPI) -->
            <div class="dashboard-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.5rem;">
                <!-- การ์ดแสดงรายได้รวม -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #DCFCE7; color: #166534;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Revenue</span>
                        <!-- แสดงตัวเลขรายได้รวมที่คำนวณมาจาก PHP ด้านบน -->
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
                        <h3 class="stat-card-value"><?= $totalBookings ?></h3>
                    </div>
                </div>
                <!-- การ์ดแสดงจำนวนผู้ใช้งานทั้งหมด -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #FEE2E2; color: #991B1B;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Total Users</span>
                        <h3 class="stat-card-value"><?= $totalUsers ?></h3>
                    </div>
                </div>
                <!-- การ์ดแสดงค่าเฉลี่ยรายได้ต่อการจอง -->
                <div class="stat-card">
                    <div class="stat-card-icon" style="background: #FEF3C7; color: #92400E;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    </div>
                    <div class="stat-card-content">
                        <span class="stat-card-label">Avg. Check</span>
                        <!-- คำนวณโดยการเอารายได้รวมหารด้วยจำนวนการจอง (มีการกันหารด้วย 0 ด้วย) -->
                        <h3 class="stat-card-value">฿<?= $totalBookings > 0 ? number_format($totalRevenue / $totalBookings) : 0 ?></h3>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <!-- ส่วนแสดงกราฟต่างๆ -->
            <div class="dashboard-grid" style="margin-bottom: 1.5rem;">
                <!-- Revenue & Bookings Chart -->
                <!-- การ์ดใหญ่สำหรับกราฟแท่ง (รายได้) และกราฟเส้น (จำนวนจอง) -->
                <div class="admin-card" style="grid-column: span 2;">
                    <div class="admin-card-header">
                        <h3>Revenue & Bookings Trend</h3>
                    </div>
                    <div class="admin-card-body">
                        <!-- Canvas นี้คือพื้นที่วาดกราฟ -->
                        <canvas id="revenueChart" style="height: 300px;"></canvas>
                    </div>
                </div>
                
                <!-- Sport Distribution -->
                <!-- การ์ดเล็กสำหรับกราฟโดนัท แสดงสัดส่วนการจองแต่ละกีฬา -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h3>Bookings by Sport</h3>
                    </div>
                    <div class="admin-card-body" style="display: flex; justify-content: center; align-items: center;">
                        <canvas id="sportChart" style="max-height: 300px; max-width: 300px;"></canvas>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Toggle Custom Dates
        // ฟังก์ชันนี้ไว้ซ่อน/แสดงช่องกรอกวันที่ ถ้าเลือก Custom ก็จะโชว์ ถ้าเลือกอย่างอื่นก็ซ่อน
        function toggleCustomDates(val) {
            document.getElementById('custom-dates').style.display = val === 'custom' ? 'flex' : 'none';
        }

        // Chart.js Configuration
        // ส่วนของการวาดกราฟรายได้และแนวโน้ม (Bar + Line Chart)
        const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctxRevenue, {
            type: 'bar', // กำหนดให้กราฟหลักเป็นแบบแท่ง
            data: {
                // เอาข้อมูล Label (วัน/เดือน/ปี) ที่ PHP ส่งมาแปลงเป็น JSON
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Revenue (THB)',
                    // ข้อมูลยอดเงิน
                    data: <?= json_encode($revenueData) ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    yAxisID: 'y', // ใช้แกน Y ด้านซ้าย
                }, {
                    label: 'Bookings',
                    // ข้อมูลจำนวนการจอง (จะแสดงเป็นเส้นทับลงไป)
                    data: <?= json_encode($bookingCountData) ?>,
                    type: 'line', // กำหนดให้ชุดนี้เป็นกราฟเส้น
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y1', // ใช้แกน Y ด้านขวา (เพื่อไม่ให้สเกลชนกัน)
                }]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    // ตั้งค่าแกน Y ซ้าย (สำหรับรายได้)
                    y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Revenue (THB)' } },
                    // ตั้งค่าแกน Y ขวา (สำหรับจำนวนการจอง)
                    y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Bookings' } },
                }
            }
        });

        // ส่วนของการวาดกราฟวงกลม (Doughnut Chart)
        const ctxSport = document.getElementById('sportChart').getContext('2d');
        const sportChart = new Chart(ctxSport, {
            type: 'doughnut',
            data: {
                // ชื่อกีฬาที่ PHP ดึงมา
                labels: <?= json_encode($sportLabels) ?>,
                datasets: [{
                    label: 'Bookings',
                    // จำนวนการจองแต่ละกีฬา
                    data: <?= json_encode($sportCounts) ?>,
                    // กำหนดสีสันให้แต่ละกีฬาสดใสขึ้น
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(20, 184, 166, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } } // ให้คำอธิบายสีอยู่ด้านล่าง
            }
        });
    </script>
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