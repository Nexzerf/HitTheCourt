<?php
// อันดับแรกเลย ดึงเอาไฟล์ตั้งค่าหลัก (config.php) เข้ามา เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะไม่ให้เข้าหน้านี้
requireAdmin();

 $message = '';

// 1. คำนวณจำนวน Reports ที่ค้างอยู่
// นับจำนวนรายงานที่ยังไม่ได้ดำเนินการ (สถานะ new หรือ in_progress) เพื่อเอาไว้แสดงเป็นป้ายแจ้งเตือนสีแดงที่ Sidebar
 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

// Handle Delete
// ส่วนนี้คือการจัดการ "การลบรายงาน"
// ถ้ามีการส่งฟอร์มแบบ POST และ action เป็น 'delete'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $reportId = intval($_POST['report_id'] ?? 0);
    if ($reportId) {
        // ลบรายงานออกจากฐานข้อมูลตาม ID ที่ส่งมา
        $stmt = $pdo->prepare("DELETE FROM reports WHERE report_id = ?");
        $stmt->execute([$reportId]);
        $message = 'Report deleted successfully.';
        // อัปเดตตัวเลขรายงานค้างใหม่อีกรอบ เผื่อลบไปแล้วเลขลดลง
        $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];
    }
}

// Handle Update Status
// ส่วนนี้คือการจัดการ "การอัปเดตสถานะรายงาน"
// ถ้ามีการส่งฟอร์มแบบ POST และ action เป็น 'update'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $reportId = intval($_POST['report_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');
    
    if ($reportId && $newStatus) {
        // อัปเดตสถานะ, โน้ตแอดมิน, และเวลาที่แก้ไข
        // มีเงื่อนไขพิเศษว่า ถ้าสถานะเป็น 'resolved' ให้เซ็ตเวลา resolved_at เป็นตอนนี้ ถ้าไม่ใช่ก็เป็น NULL
        $stmt = $pdo->prepare("UPDATE reports SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = IF(? = 'resolved', NOW(), NULL) WHERE report_id = ?");
        $stmt->execute([$newStatus, $adminNotes, $_SESSION['admin_id'], $newStatus, $reportId]);
        $message = 'Report updated successfully';
        // อัปเดตตัวเลขรายงานค้างใหม่อีกรอบ
        $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];
    }
}

// Handle Sort Logic
// ส่วนนี้จัดการเรื่องการเรียงลำดับ (Sorting) รายงาน
 $sort = sanitize($_GET['sort'] ?? 'status_priority'); // Default: Status Priority

// กำหนด Order By
// ถ้าเลือกเรียงจากเก่าสุด
if ($sort === 'oldest') {
    $orderBy = "r.created_at ASC";
} 
// ถ้าเลือกเรียงจากใหม่สุด
elseif ($sort === 'newest') {
    $orderBy = "r.created_at DESC";
} 
// ค่าตั้งต้น: เรียงตามความสำคัญของสถานะ (New มาก่อน, ตามด้วย In Progress, สุดท้ายคืออื่นๆ)
else {
    // Default: Status Priority, then Newest
    $orderBy = "
        CASE r.status 
            WHEN 'new' THEN 1 
            WHEN 'in_progress' THEN 2 
            ELSE 3 
        END,
        r.created_at DESC
    ";
}

// Fetch reports
// ดึงข้อมูลรายงานทั้งหมดมาแสดง โดย JOIN กับตาราง users เพื่อเอาชื่อคนที่รายงานมาแสดงด้วย
 $sql = "
    SELECT r.*, u.username, u.email, u.phone 
    FROM reports r 
    JOIN users u ON r.user_id = u.user_id 
    ORDER BY $orderBy
";

 $reports = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - Admin</title>
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
                <!-- เมนู Reports (หน้าปัจจุบัน เลยใส่ class active) -->
                <a href="reports.php" class="admin-nav-item active">
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
                <h1 class="admin-title">User Reports</h1>
            </div>

            <!-- ส่วนแสดงข้อความ Success (Toast) -->
            <?php if ($message): ?>
            <div class="admin-toast" style="background: #DCFCE7; border-color: #BBF7D0; color: #166534;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <!-- Sort Bar -->
            <!-- แท็บสำหรับเลือกวิธีเรียงลำดับรายงาน -->
            <div class="admin-tabs">
                <a href="?sort=status_priority" class="admin-tab <?= $sort === 'status_priority' ? 'active' : '' ?>">Priority (Default)</a>
                <a href="?sort=newest" class="admin-tab <?= $sort === 'newest' ? 'active' : '' ?>">Newest First</a>
                <a href="?sort=oldest" class="admin-tab <?= $sort === 'oldest' ? 'active' : '' ?>">Oldest First</a>
            </div>

            <!-- ส่วนแสดงการ์ดรายงานทั้งหมด (Grid) -->
            <div class="reports-grid">
                <!-- ถ้าไม่มีรายงานเลย ก็แสดง Empty State -->
                <?php if (empty($reports)): ?>
                <div class="admin-card">
                    <div class="empty-state" style="padding: 3rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <h3>No Reports Found</h3>
                        <p>There are no user reports to display.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- วนลูปแสดงการ์ดรายงานทีละใบ -->
                <?php foreach ($reports as $r): ?>
                <div class="report-card">
                    <!-- Header -->
                    <!-- ส่วนหัวการ์ด: หัวข้อรายงาน, ชื่อคนแจ้ง, และป้ายสถานะ -->
                    <div class="report-header">
                        <div>
                            <div class="report-title"><?= htmlspecialchars($r['topic']) ?></div>
                            <div class="report-meta">
                                Submitted by <strong><?= htmlspecialchars($r['username']) ?></strong> (<?= htmlspecialchars($r['phone']) ?>) • <?= date('d M Y, g:i A', strtotime($r['created_at'])) ?>
                            </div>
                        </div>
                        <!-- ป้ายแสดงสถานะปัจจุบัน (New, In Progress, Resolved) -->
                        <span class="report-status-badge status-<?= htmlspecialchars($r['status']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $r['status'])) ?>
                        </span>
                    </div>

                    <!-- Body -->
                    <!-- ส่วนเนื้อหา: รายละเอียดรายงานและไฟล์แนบ -->
                    <div class="report-body">
                        <div class="report-content">
                            <?= nl2br(htmlspecialchars($r['description'])) ?>
                        </div>
                        
                        <!-- ถ้ามีรูปแนบมาด้วย ให้แสดงลิงก์ให้กดดู -->
                        <?php if ($r['image_path']): ?>
                        <a href="<?= SITE_URL ?>/<?= $r['image_path'] ?>" target="_blank" class="attachment-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            View Attachment Image
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Footer / Form -->
                    <!-- ส่วนท้าย: เป็นฟอร์มสำหรับแอดมินจัดการรายงาน -->
                    <div class="report-footer">
                        <!-- Update Form -->
                        <form method="POST" class="update-form">
                            <!-- แก้ไข: เอา hidden action ออกจากตรงนี้ เพื่อให้ปุ่มควบคุม action เอง -->
                            <input type="hidden" name="report_id" value="<?= $r['report_id'] ?>">
                            
                            <!-- Dropdown เปลี่ยนสถานะ -->
                            <div class="form-row" style="max-width: 300px;">
                                <label class="form-label">Update Status</label>
                                <select name="status" class="admin-input">
                                    <option value="new" <?= $r['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                    <option value="in_progress" <?= $r['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $r['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </div>
                            
                            <!-- ช่องพิมพ์โน้ตแอดมิน -->
                            <div class="form-row">
                                <label class="form-label">Admin Notes / Response</label>
                                <textarea name="admin_notes" class="form-textarea" placeholder="Add notes or resolution details..."><?= htmlspecialchars($r['admin_notes'] ?? '') ?></textarea>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                                <!-- Delete Button: ปุ่มลบ (สีแดง) -->
                                <!-- แก้ไข Action: ตรงนี้จะส่งค่า action = 'delete' ไปที่ PHP ด้านบน -->
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to DELETE this report? This cannot be undone.');">
                                    Delete
                                </button>
                                
                                <!-- Save Button: ปุ่มบันทึก -->
                                <!-- แก้ไข Action: ตรงนี้จะส่งค่า action = 'update' ไปที่ PHP ด้านบน -->
                                <button type="submit" name="action" value="update" class="btn btn-primary" style="width: auto; padding: 0.5rem 1.5rem;">Save Update</button>
                            </div>
                        </form>
                    </div>
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