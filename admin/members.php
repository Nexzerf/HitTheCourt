<?php
require_once '../config.php';
requireAdmin();

$message = '';

// --- Sidebar Data ---
$pendingReports = $pdo->query("
    SELECT COUNT(*) as count 
    FROM reports 
    WHERE status IN ('new', 'in_progress')
")->fetch()['count'];

/* -----------------------------
   SEARCH USERS
------------------------------ */
$search = sanitize($_GET['search'] ?? '');
$whereSql = "1=1";
$params = [];

if ($search) {
    $whereSql .= " AND (username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

/* -----------------------------
   FETCH USERS
------------------------------ */
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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">

<style>
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
.admin-tab.active{
    color:var(--admin-primary);
    border-bottom-color:var(--admin-primary);
}
.tab-content{display:none;}
.tab-content.active{display:block;}

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

<div class="admin-layout">

<!-- SIDEBAR -->
<aside class="admin-sidebar">
<div class="admin-brand">Hit The Court</div>

<nav class="admin-nav">

<a href="dashboard.php" class="admin-nav-item">Dashboard</a>
<a href="analytics.php" class="admin-nav-item">Analytics</a>
<a href="sports.php" class="admin-nav-item">Sports & Courts</a>
<a href="bookings.php" class="admin-nav-item">Bookings</a>
<a href="payments.php" class="admin-nav-item">Payments</a>

<a href="members.php" class="admin-nav-item active">
Members
</a>

<a href="reports.php" class="admin-nav-item">
Reports
<?php if ($pendingReports > 0): ?>
<span style="background:#DC2626;color:white;padding:2px 8px;border-radius:999px;font-size:.7rem;margin-left:auto;">
<?= $pendingReports ?>
</span>
<?php endif; ?>
</a>

</nav>

<div style="margin-top:auto;padding:1rem;border-top:1px solid rgba(255,255,255,.1);">
<a href="<?= SITE_URL ?>/api/auth.php?action=admin_logout" class="admin-nav-item">
Logout
</a>
</div>

</aside>


<!-- MAIN -->
<main class="admin-main">

<div class="admin-header">
<h1 class="admin-title">Members Management</h1>
</div>

<!-- TABS -->
<div class="admin-tabs">
<button class="admin-tab active" onclick="switchTab('users')">User Management</button>
<button class="admin-tab" onclick="switchTab('requests')">Membership Requests</button>
</div>


<!-- TAB USERS -->
<div id="tab-users" class="tab-content active">

<!-- SEARCH -->
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

<?php if(empty($users)): ?>
<tr>
<td colspan="4">
<div class="empty-state">
<h3>No Users Found</h3>
</div>
</td>
</tr>
<?php else: ?>

<?php foreach($users as $u): ?>
<tr>

<td>
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

<?php if(empty($membershipRequests)): ?>
<tr>
<td colspan="5">No membership requests</td>
</tr>

<?php else: ?>

<?php foreach($membershipRequests as $req): ?>
<tr>

<td>
<strong><?= htmlspecialchars($req['username']) ?></strong><br>
<small><?= htmlspecialchars($req['email']) ?></small>
</td>

<td><?= htmlspecialchars($req['plan_name']) ?></td>

<td>
Start: <?= date('d M Y',strtotime($req['start_date'])) ?><br>
End: <?= date('d M Y',strtotime($req['end_date'])) ?>
</td>

<td>
<?php
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
function switchTab(tab){
document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));
document.querySelectorAll('.admin-tab').forEach(el=>el.classList.remove('active'));

document.getElementById('tab-'+tab).classList.add('active');
event.target.classList.add('active');
}
</script>

</body>
</html>