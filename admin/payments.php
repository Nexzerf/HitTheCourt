<?php
// อันดับแรกเลย ดึงเอาไฟล์ตั้งค่าหลัก (config.php) เข้ามา เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะไม่ให้เข้าหน้านี้
requireAdmin();

 $message = '';

// ไปนับจำนวนรายงานที่ยังไม่ได้ดำเนินการ เพื่อเอาไว้แสดงเป็นป้ายแจ้งเตือนสีแดงที่เมนู Reports
 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

// Handle Verification Actions
// ส่วนนี้คือ "สมองกล" ในการจัดการ action ต่างๆ ที่แอดมินกด เช่น กดยืนยัน หรือ ปฏิเสธ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า ID ที่จำเป็นต้องใช้
    $paymentId = intval($_POST['payment_id'] ?? 0);
    $bookingId = intval($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($paymentId && $bookingId) {
        try {
            // เปิด Transaction เพื่อให้มั่นใจว่าข้อมูลจะซิงค์กัน ถ้าผิดพลาดตรงไหนจะย้อนกลับหมด
            $pdo->beginTransaction();

            // ถ้า Action เป็น 'verify' (ยืนยันการชำระเงิน)
            if ($action === 'verify') {
                // 1. Update Payment Status: อัปเดตสถานะในตาราง payments ว่า "verified" พร้อมบันทึกผู้ตรวจสอบ
                $pdo->prepare("UPDATE payments SET payment_status = 'verified', verified_by = ?, verified_at = NOW() WHERE payment_id = ?")
                    ->execute([$_SESSION['admin_id'], $paymentId]);

                // 2. Update Booking Status: อัปเดตสถานะการจองเป็น "paid"
                $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?")
                    ->execute([$bookingId]);

                // 3. Update User Points: ดึง ID ของ User เจ้าของการจอง แล้วไปเพิ่มคะแนนให้เขา
                $userStmt = $pdo->prepare("SELECT user_id FROM bookings WHERE booking_id = ?");
                $userStmt->execute([$bookingId]);
                $userId = $userStmt->fetchColumn();
                
                if ($userId) {
                    // เพิ่ม points 1 คะแนน และนับจำนวนการจองรวมเพิ่มขึ้น 1
                    $pdo->prepare("UPDATE users SET points = points + 1, total_bookings = total_bookings + 1 WHERE user_id = ?")
                        ->execute([$userId]);
                }
                $message = 'Payment verified successfully. Points added to user.';
            }

            // ถ้า Action เป็น 'reject' (ปฏิเสธสลิป)
            if ($action === 'reject') {
                // เปลี่ยนสถานะการจ่ายเงินเป็น 'rejected' และการจองเป็น 'failed'
                $pdo->prepare("UPDATE payments SET payment_status = 'rejected' WHERE payment_id = ?")->execute([$paymentId]);
                $pdo->prepare("UPDATE bookings SET payment_status = 'failed' WHERE booking_id = ?")->execute([$bookingId]);
                $message = 'Payment rejected.';
            }

            // ยืนยันการทำธุรกรรม (Commit)
            $pdo->commit();

        } catch (Exception $e) {
            // ถ้ามีข้อผิดพลาดอะไรก็ตาม ให้ย้อนกลับ (Rollback) และแจ้ง Error
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch Payments
// ส่วนของการดึงข้อมูลมาแสดงผล
// รับค่า Filter จาก URL ว่าจะดู All, Pending หรือ Verified
 $filter = sanitize($_GET['filter'] ?? 'all');
 $whereSql = "1=1";
 $params = [];

if ($filter !== 'all') {
    $whereSql = "p.payment_status = ?";
    $params[] = $filter;
}

// SQL อลังการ์: ดึงข้อมูลมาเยอะแยะ ทั้งชื่อ user, ชื่อกีฬา, วันที่จอง
// และที่สำคัญคือเรียงลำดับให้ 'pending' ขึ้นมาก่อน เพื่อให้แอดมินเห็นเลยว่ามีอะไรรอตรวจสอบบ้าง
 $sql = "
    SELECT p.*, b.booking_code, b.total_price, b.booking_date, 
           u.username, u.phone, s.sport_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN users u ON b.user_id = u.user_id
    JOIN courts c ON b.court_id = c.court_id
    JOIN sports s ON c.sport_id = s.sport_id
    WHERE {$whereSql}
    ORDER BY 
        CASE p.payment_status 
            WHEN 'pending' THEN 1 
            WHEN 'verified' THEN 2 
            ELSE 3 
        END,
        p.created_at DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - Admin</title>
    <!-- โหลดฟอนต์และ CSS สำหรับหน้า Admin -->
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
                <!-- เมนู Dashboard -->
                <a href="dashboard.php" class="admin-nav-item">
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
                <!-- เมนู Payments (หน้าปัจจุบัน เลยใส่ class active) -->
                <a href="payments.php" class="admin-nav-item active">
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
                    <!-- แสดงป้ายแจ้งเตือนถ้ามีรายงานรอดำเนินการ -->
                    <?php if ($pendingReports > 0): ?>
                    <span style="background: #DC2626; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; margin-left: auto;"><?= $pendingReports ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            
            <!-- ปุ่ม Logout ด้านล่างสุด -->
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
                <h1 class="admin-title">Payments Verification</h1>
            </div>

            <!-- ส่วนแสดงข้อความ Success/Error (Toast) -->
            <?php if ($message): ?>
            <div class="admin-toast" style="background: #DCFCE7; border-color: #BBF7D0; color: #166534;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <!-- แท็บสำหรับกรองดูสถานะการชำระเงิน -->
            <div class="admin-tabs">
                <a href="?filter=all" class="admin-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=pending" class="admin-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                <a href="?filter=verified" class="admin-tab <?= $filter === 'verified' ? 'active' : '' ?>">Verified</a>
                <a href="?filter=rejected" class="admin-tab <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
            </div>

            <!-- Payments Grid -->
            <!-- ส่วนแสดงการ์ดรายการชำระเงินทั้งหมด -->
            <div class="payments-grid">
                <!-- ถ้าไม่มีข้อมูล จะแสดง Empty State -->
                <?php if (empty($payments)): ?>
                <div class="admin-card">
                    <div class="empty-state" style="padding: 3rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        <h3>No Payments Found</h3>
                        <p>There are no payments matching this filter.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- วนลูปแสดงการ์ดแต่ละใบ -->
                <?php foreach ($payments as $p): ?>
                <div class="payment-card">
                    <!-- Header -->
                    <!-- ส่วนหัวการ์ด: แสดงชื่อผู้ใช้ และสถานะปัจจุบัน -->
                    <div class="payment-header">
                        <div class="payment-user">
                            <h4><?= htmlspecialchars($p['username']) ?></h4>
                            <small><?= htmlspecialchars($p['phone']) ?></small>
                        </div>
                        <span class="status-pill status-<?= htmlspecialchars($p['payment_status']) ?>">
                            <?= ucfirst($p['payment_status']) ?>
                        </span>
                    </div>

                    <!-- Body -->
                    <!-- ส่วนเนื้อหา: รายละเอียดการจองและรูปสลิป -->
                    <div class="payment-body">
                        <div class="payment-details">
                            <div class="detail-row">
                                <span class="detail-label">Booking Code</span>
                                <span class="detail-value"><code style="background:none; padding:0; color:var(--admin-primary);"><?= $p['booking_code'] ?></code></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Sport / Date</span>
                                <span class="detail-value"><?= $p['sport_name'] ?> • <?= date('d M', strtotime($p['booking_date'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value" style="font-size: 1.1rem; font-weight: 700;">฿<?= number_format($p['amount']) ?></span>
                            </div>
                        </div>

                        <!-- Slip Preview -->
                        <!-- ส่วนแสดงรูปสลิปโอนเงิน -->
                        <div class="slip-preview">
                            <?php if ($p['slip_image']): ?>
                                <!-- ถ้ามีรูป ก็แสดงรูป พร้อมปุ่มกดดูภาพเต็ม -->
                                <img src="<?= SITE_URL ?>/<?= $p['slip_image'] ?>" alt="Slip">
                                <a href="<?= SITE_URL ?>/<?= $p['slip_image'] ?>" target="_blank" class="slip-overlay">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </a>
                            <?php else: ?>
                                <!-- ถ้าไม่มีรูป ก็แจ้งว่ายังไม่ได้อัปโหลด -->
                                <span style="font-size: 0.75rem; color: var(--admin-muted); text-align: center;">No Slip<br>Uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <!-- ปุ่ม Action: จะแสดงก็ต่อเมื่อสถานะเป็น 'pending' และมีรูปสลิปแล้วเท่านั้น -->
                    <?php if ($p['payment_status'] === 'pending' && $p['slip_image']): ?>
                    <div class="payment-footer">
                        <!-- ปุ่ม Reject -->
                        <form method="POST" onsubmit="return confirm('Are you sure you want to reject this payment?');">
                            <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                            <input type="hidden" name="booking_id" value="<?= $p['booking_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </form>
                        <!-- ปุ่ม Verify -->
                        <form method="POST">
                            <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                            <input type="hidden" name="booking_id" value="<?= $p['booking_id'] ?>">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn btn-success">Verify Payment</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
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