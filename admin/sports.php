<?php
// อันดับแรกเลย ดึงเอาไฟล์ตั้งค่าหลัก (config.php) เข้ามา เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะไม่ให้เข้าหน้านี้
requireAdmin();

 $message = '';

// ไปนับจำนวนรายงานที่ยังไม่ได้ดำเนินการ เพื่อเอาไว้แสดงเป็นป้ายแจ้งเตือนสีแดงที่เมนู Reports
 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

// Handle updates
// ส่วนนี้คือ "สมองกล" ในการจัดการ action ต่างๆ ที่แอดมินกด เช่น เปลี่ยนราคา อัปเดตอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ถ้า Action เป็นการอัปเดตราคากีฬา
    if ($action === 'update_price') {
        $sportId = intval($_POST['sport_id']);
        $price = intval($_POST['price']);
        // อัปเดตราคาในตาราง sports
        $stmt = $pdo->prepare("UPDATE sports SET price = ? WHERE sport_id = ?");
        $stmt->execute([$price, $sportId]);
        $message = 'Price updated successfully';
    }
    
    // ถ้า Action เป็นการอัปเดตอุปกรณ์ (ราคาและจำนวนสต็อก)
    if ($action === 'update_equipment') {
        $eqId = intval($_POST['eq_id']);
        $price = intval($_POST['price']);
        $stock = intval($_POST['stock']);
        // อัปเดตทั้งราคาและสต็อกในตาราง equipment
        $stmt = $pdo->prepare("UPDATE equipment SET price = ?, stock = ? WHERE eq_id = ?");
        $stmt->execute([$price, $stock, $eqId]);
        $message = 'Equipment updated successfully';
    }
    
    // ถ้า Action เป็นการสลับสถานะสนาม (Toggle)
    if ($action === 'toggle_court_status') {
        $courtId = intval($_POST['court_id']);
        // Toggle logic: ดึงสถานะปัจจุบันมาก่อน
        $stmt = $pdo->prepare("SELECT status FROM courts WHERE court_id = ?");
        $stmt->execute([$courtId]);
        $current = $stmt->fetchColumn();
        
        // ถ้าปัจจุบันเป็น available ก็ให้เปลี่ยนเป็น maintenance แต่ถ้าไม่ใช่ก็กลับเป็น available
        $newStatus = ($current === 'available') ? 'maintenance' : 'available';
        
        $update = $pdo->prepare("UPDATE courts SET status = ? WHERE court_id = ?");
        $update->execute([$newStatus, $courtId]);
        $message = 'Court status updated';
    }
}

// Fetch all sports with counts
// ดึงข้อมูลกีฬาทั้งหมดมาแสดง พร้อมกับนับจำนวนคอร์ททั้งหมดและคอร์ทที่ว่างอยู่ด้วย (Subquery)
 $sports = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM courts WHERE sport_id = s.sport_id) as court_count,
           (SELECT COUNT(*) FROM courts WHERE sport_id = s.sport_id AND status = 'available') as available_count
    FROM sports s
    ORDER BY s.sport_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Management - Admin</title>
    <!-- โหลดฟอนต์และ CSS สำหรับหน้า Admin -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Use new Admin CSS -->
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
                <!-- เมนู Sports & Courts (หน้าปัจจุบัน เลยใส่ class active) -->
                <a href="sports.php" class="admin-nav-item active">
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
                <h1 class="admin-title">Sports & Courts Management</h1>
            </div>
            
            <!-- ส่วนแสดงข้อความแจ้งเตือน (Toast) -->
            <?php if ($message): ?>
            <div class="admin-toast">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <!-- วนลูปแสดงข้อมูลกีฬาแต่ละประเภท -->
            <?php foreach ($sports as $sport): ?>
            <div class="admin-card">
                
                <!-- Sport Header -->
                <!-- หัวข้อกีฬา พร้อมแสดงสถิติสนามว่าง/ทั้งหมด -->
                <div class="sport-header">
                    <div class="sport-info">
                        <h2><?= htmlspecialchars($sport['sport_name']) ?></h2>
                        <p>
                            <span style="color: var(--admin-success); font-weight: 600;"><?= $sport['available_count'] ?></span> / <?= $sport['court_count'] ?> Courts Available 
                            &bull; <?= $sport['duration_minutes'] ?> min/round
                        </p>
                    </div>
                    
                    <!-- Price Edit Form -->
                    <!-- ฟอร์มสำหรับเปลี่ยนราคาค่าจองของกีฬงนี้ -->
                    <form method="POST" class="price-edit-form">
                        <input type="hidden" name="action" value="update_price">
                        <input type="hidden" name="sport_id" value="<?= $sport['sport_id'] ?>">
                        <label style="font-size: 0.875rem; color: var(--admin-muted);">Price (THB):</label>
                        <input type="number" name="price" value="<?= $sport['price'] ?>" class="input-sm" style="width: 100px;">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </form>
                </div>

                <!-- Courts Grid -->
                <!-- ส่วนจัดการสถานะสนาม (Court) แต่ละคอร์ท -->
                <div class="courts-section">
                    <h4 class="section-label">Manage Courts Status</h4>
                    <div class="courts-grid">
                        <?php 
                        // ดึงข้อมูลคอร์ททั้งหมดของกีฬานี้ออกมา
                        $courts = $pdo->prepare("SELECT * FROM courts WHERE sport_id = ?");
                        $courts->execute([$sport['sport_id']]);
                        foreach ($courts->fetchAll() as $court): 
                        ?>
                        <!-- ทำเป็นฟอร์มแบบปุ่มกด กดแล้วจะสลับสถานะทันที -->
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle_court_status">
                            <input type="hidden" name="court_id" value="<?= $court['court_id'] ?>">
                            <!-- Submit button acts as the card -->
                            <!-- ปุ่มนี้คือตัวการ์ดเลย มีสีตามสถานะ (available/maintenance) -->
                            <button type="submit" class="court-btn <?= htmlspecialchars($court['status']) ?>" title="Click to toggle status">
                                <div class="court-btn-number">C<?= $court['court_number'] ?></div>
                                <div class="court-btn-status"><?= ucfirst($court['status']) ?></div>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Equipment Table -->
                <!-- ส่วนจัดการอุปกรณ์ (Equipment) และสต็อก -->
                <div class="equipment-section">
                    <h4 class="section-label">Equipment Inventory</h4>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Item Name</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Fine (Damaged)</th>
                                <th style="width: 15%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // ดึงข้อมูลอุปกรณ์ของกีฬานี้ออกมา
                            $equipments = $pdo->prepare("SELECT * FROM equipment WHERE sport_id = ?");
                            $equipments->execute([$sport['sport_id']]);
                            if ($equipments->rowCount() > 0):
                                foreach ($equipments->fetchAll() as $eq): 
                            ?>
                            <tr>
                                <!-- แต่ละแถวคืออุปกรณ์ชิ้นนึง ห่อด้วยฟอร์มเพื่ออัปเดตได้ทันที -->
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_equipment">
                                    <input type="hidden" name="eq_id" value="<?= $eq['eq_id'] ?>">
                                    <td><strong><?= htmlspecialchars($eq['eq_name']) ?></strong></td>
                                    <td>
                                        <!-- ช่องกรอกจำนวนสต็อก -->
                                        <input type="number" name="stock" value="<?= $eq['stock'] ?>" class="input-sm" min="0">
                                    </td>
                                    <td>
                                        <!-- ช่องกรอกราคาเช่า -->
                                        <input type="number" name="price" value="<?= $eq['price'] ?>" class="input-sm" min="0">
                                    </td>
                                    <td><?= number_format($eq['fine_amount']) ?> THB</td>
                                    <td><button type="submit" class="btn btn-sm btn-primary">Update</button></td>
                                </form>
                            </tr>
                            <?php 
                                endforeach; 
                            else:
                            ?>
                            <!-- ถ้ากีฬานี้ไม่มีอุปกรณ์ ก็แสดงข้อความว่างๆ -->
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--admin-muted);">No equipment defined for this sport.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
            <?php endforeach; ?>
            
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