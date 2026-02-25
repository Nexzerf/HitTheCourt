<?php
// เริ่มต้นด้วยการดึงไฟล์ config.php เข้ามาเพื่อใช้งานฐานข้อมูลและฟังก์ชันระบบ
require_once '../config.php';
// ตรวจสอบสิทธิ์ทันทีว่า "เป็นแอดมินไหม?" ถ้าไม่ใช่จะโดนดีดออกไป
requireAdmin();

 $message = '';

// --- Sidebar Data ---
// ไปนับจำนวนรายงานที่ยังไม่ได้ดำเนินการ (สถานะ new หรือ in_progress)
// เพื่อเอาไว้แสดงเป็นป้ายแจ้งเตือนสีแดงที่เมนู Reports ด้านข้าง
 $pendingReports = $pdo->query("
    SELECT COUNT(*) as count 
    FROM reports 
    WHERE status IN ('new', 'in_progress')
")->fetch()['count'];

/* -----------------------------
   SEARCH USERS
------------------------------ */
// รับค่าค้นหาจาก URL (ถ้ามี) แล้วทำความสะอาดข้อมูล
 $search = sanitize($_GET['search'] ?? '');
// สร้างเงื่อนไข SQL เบื้องต้น (1=1 คือเลือกทั้งหมด)
 $whereSql = "1=1";
 $params = [];

// ถ้ามีการพิมพ์ค้นหา ก็จะเพิ่มเงื่อนไขไปค้นหาใน ชื่อ, เมล, หรือเบอร์โทร
if ($search) {
    $whereSql .= " AND (username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

/* -----------------------------
   FETCH USERS
------------------------------ */
// ดึงข้อมูลผู้ใช้ทั้งหมดจากฐานข้อมูล โดยเรียงลำดับจากคนใหม่สุด
 $stmt = $pdo->prepare("
    SELECT * 
    FROM users 
    WHERE $whereSql 
    ORDER BY created_at DESC
");
 $stmt->execute($params);
 $users = $stmt->fetchAll();

/* -----------------------------
   MEMBERSHIP REQUESTS
------------------------------ */
// ดึงข้อมูลคำขอสมัครสมาชิก มาแสดงในอีกแท็บนึง
// โดยจะดึงข้อมูลมาเชื่อมกัน 3 ตาราง คือ user_membership, users และ membership_plans
 $membershipRequests = $pdo->query("
    SELECT um.*, u.username, u.email, u.phone, mp.plan_name 
    FROM user_membership um
    JOIN users u ON um.user_id = u.user_id
    JOIN membership_plans mp ON um.plan_id = mp.plan_id
    ORDER BY um.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Members Management - Admin</title>

<!-- โหลดฟอนต์สวยๆ และ CSS ของแอดมิน -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">

<style>
/* CSS สำหรับแท็บเมนูด้านบน */
.admin-tabs{
    display:flex;
    gap:.5rem;
    margin-bottom:1.5rem;
    border-bottom:2px solid var(--admin-border);
}
.admin-tab{
    padding:.75rem 1.5rem;
    border:none;
    background:transparent;
    font-weight:600;
    cursor:pointer;
    color:var(--admin-muted);
    border-bottom:2px solid transparent;
}
/* ตอนเลือกแท็บไหน ให้มันเปลี่ยนสีและมีเส้นใต้ */
.admin-tab.active{
    color:var(--admin-primary);
    border-bottom-color:var(--admin-primary);
}
.tab-content{display:none;}
.tab-content.active{display:block;}

/* สีของป้ายสถานะ (Badge) */
.badge-pending{background:#FEF3C7;color:#92400E;}
.badge-verified{background:#DCFCE7;color:#166534;}

.slip-link{
    color:var(--admin-primary);
    text-decoration:underline;
    font-size:.85rem;
}
</style>
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
                <a href="payments.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Payments
                </a>
                <!-- เมนู Members -->
                <a href="members.php" class="admin-nav-item active">
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


<!-- MAIN -->
<!-- ส่วนเนื้อหาหลัก -->
<main class="admin-main">

<div class="admin-header">
<h1 class="admin-title">Members Management</h1>
</div>

<!-- TABS -->
<!-- แท็บเลือกว่าจะดูหน้าไหน (ระหว่าง User กับ Membership Requests) -->
<div class="admin-tabs">
<button class="admin-tab active" onclick="switchTab('users')">User Management</button>
<button class="admin-tab" onclick="switchTab('requests')">Membership Requests</button>
</div>


<!-- TAB USERS -->
<!-- เนื้อหาแท็บที่ 1: รายชื่อผู้ใช้งาน -->
<div id="tab-users" class="tab-content active">

<!-- SEARCH -->
<!-- ช่องค้นหาผู้ใช้ -->
<div class="admin-card" style="margin-bottom:1.5rem;">
<div class="admin-card-body" style="padding:1rem 1.5rem;">
<form method="GET" class="filter-bar">

<div class="filter-group" style="flex-grow:1;">
<label>Search User</label>
<input 
type="text" 
name="search" 
class="admin-input" 
placeholder="Username, Email, Phone..." 
value="<?= htmlspecialchars($search) ?>">
</div>

<button type="submit" class="btn btn-primary">Search</button>
<a href="members.php" class="btn">Clear</a>

</form>
</div>
</div>


<!-- USERS TABLE -->
<!-- ตารางแสดงรายชื่อผู้ใช้ -->
<div class="admin-card">
<div class="admin-card-body" style="padding:0;">
<table class="admin-table">

<thead>
<tr>
<th style="width:40%">User</th>
<th style="width:20%">Phone</th>
<th style="width:20%">Bookings</th>
<th style="width:20%">Points</th>
</tr>
</thead>

<tbody>

<!-- ถ้าไม่เจอผู้ใช้เลย ก็แสดงข้อความว่างๆ -->
<?php if(empty($users)): ?>
<tr>
<td colspan="4">
<div class="empty-state">
<h3>No Users Found</h3>
</div>
</td>
</tr>
<?php else: ?>

<!-- วนลูปแสดงรายชื่อผู้ใช้ทีละคน -->
<?php foreach($users as $u): ?>
<tr>

<td>
<!-- แสดง Avatar กับ ชื่อ-เมล -->
<div class="user-info-cell">
<div class="user-avatar-small">
<?= strtoupper(substr($u['username'],0,1)) ?>
</div>

<div class="user-details">
<strong><?= htmlspecialchars($u['username']) ?></strong>
<small><?= htmlspecialchars($u['email']) ?></small>
</div>
</div>
</td>

<td><?= htmlspecialchars($u['phone']) ?></td>
<td><?= $u['total_bookings'] ?? 0 ?></td>
<td><?= $u['points'] ?? 0 ?></td>

</tr>
<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>
</div>
</div>

</div>



<!-- TAB REQUESTS -->
<!-- เนื้อหาแท็บที่ 2: คำขอสมาชิก -->
<div id="tab-requests" class="tab-content">

<div class="admin-card">

<div class="admin-card-header">
<h3>Membership Applications</h3>
</div>

<div class="admin-card-body" style="padding:0;">

<table class="admin-table">

<thead>
<tr>
<th>User</th>
<th>Plan</th>
<th>Date</th>
<th>Status</th>
<th>Slip</th>
</tr>
</thead>

<tbody>

<!-- ถ้าไม่มีคำขอ ก็แจ้งว่าไม่มี -->
<?php if(empty($membershipRequests)): ?>
<tr>
<td colspan="5">No membership requests</td>
</tr>

<?php else: ?>

<!-- วนลูปแสดงรายการคำขอสมาชิก -->
<?php foreach($membershipRequests as $req): ?>
<tr>

<td>
<strong><?= htmlspecialchars($req['username']) ?></strong><br>
<small><?= htmlspecialchars($req['email']) ?></small>
</td>

<td><?= htmlspecialchars($req['plan_name']) ?></td>

<td>
<!-- แสดงวันเริ่มต้น-สิ้นสุด สมาชิก -->
Start: <?= date('d M Y',strtotime($req['start_date'])) ?><br>
End: <?= date('d M Y',strtotime($req['end_date'])) ?>
</td>

<td>
<?php
// กำหนดสีของป้ายสถานะตามการชำระเงิน
 $statusClass = 'badge-default';
if($req['payment_status'] === 'verified' || $req['payment_status'] === 'paid'){
 $statusClass='badge-verified';
}
if($req['payment_status'] === 'pending'){
 $statusClass='badge-pending';
}
?>
<span class="badge <?= $statusClass ?>">
<?= ucfirst($req['payment_status']) ?>
</span>
</td>

<td>
<!-- ถ้ามีรูปสลิป ก็ให้แสดงลิงก์ไปดู -->
<?php if(!empty($req['slip_image'])): ?>
<a href="<?= SITE_URL.'/'.$req['slip_image'] ?>" target="_blank" class="slip-link">
View Slip
</a>
<?php else: ?>
No Slip
<?php endif; ?>
</td>

</tr>
<?php endforeach; ?>

<?php endif; ?>

</tbody>
</table>

</div>
</div>

</div>

</main>
</div>


<script>
// สคริปต์สำหรับสลับแท็บ (Tab Switching)
function switchTab(tab){
// ซ่อนทุกเนื้อหาก่อน
document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
// ลบ active ออกจากทุกปุ่มแท็บ
document.querySelectorAll('.admin-tab').forEach(el=>el.classList.remove('active'));

// แล้วค่อยแสดงเฉพาะเนื้อหาของแท็บที่เลือก และเติม active เข้าไปที่ปุ่มนั้น
document.getElementById('tab-'+tab).classList.add('active');
event.target.classList.add('active');
}
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