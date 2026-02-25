<?php
require_once '../config.php';
requireAdmin();

// Filters
 $statusFilter = sanitize($_GET['status'] ?? 'all');
 $dateFilter = sanitize($_GET['date'] ?? '');

 $pendingReports = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status IN ('new', 'in_progress')")->fetch()['count'];

 $whereClause = "1=1";
 $params = [];

if ($statusFilter !== 'all') {
    $whereClause .= " AND b.payment_status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $whereClause .= " AND b.booking_date = ?";
    $params[] = $dateFilter;
}

// Fetch bookings
 $sql = "
    SELECT b.*, u.username, u.email, u.phone, s.sport_name, c.court_number, ts.start_time, ts.end_time, p.slip_image
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN courts c ON b.court_id = c.court_id
    JOIN sports s ON c.sport_id = s.sport_id
    JOIN time_slots ts ON b.slot_id = ts.slot_id
    LEFT JOIN payments p ON b.booking_id = p.booking_id
    WHERE {$whereClause}
    ORDER BY b.created_at DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $bookings = $stmt->fetchAll();

 $message = '';
 $error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($bookingId && $action) {
        try {
            $pdo->beginTransaction();
            
            // --- ACTION: VERIFY ---
            if ($action === 'verify') {
                // 1. Update Payment
                $pdo->prepare("UPDATE payments SET payment_status = 'verified', verified_by = ?, verified_at = NOW() WHERE booking_id = ?")
                    ->execute([$_SESSION['admin_id'], $bookingId]);
                
                // 2. Update Booking
                $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE booking_id = ?")
                    ->execute([$bookingId]);
                
                // 3. Update User Points (Changed to +10)
                $userStmt = $pdo->prepare("SELECT user_id FROM bookings WHERE booking_id = ?");
                $userStmt->execute([$bookingId]);
                $userId = $userStmt->fetchColumn();
                
                if ($userId) {
                    $pdo->prepare("UPDATE users SET points = points + 1, total_bookings = total_bookings + 1 WHERE user_id = ?")
                        ->execute([$userId]);
                }
                
                $message = 'Payment verified successfully.';
            }
            
            // --- ACTION: REJECT ---
            if ($action === 'reject') {
                $pdo->prepare("UPDATE payments SET payment_status = 'rejected' WHERE booking_id = ?")
                    ->execute([$bookingId]);
                $pdo->prepare("UPDATE bookings SET payment_status = 'failed' WHERE booking_id = ?")
                    ->execute([$bookingId]);
                $message = 'Payment rejected.';
            }

            // --- ACTION: DELETE (New) ---
            if ($action === 'delete') {
                // 1. Restore Equipment Stock
                $itemsStmt = $pdo->prepare("SELECT eq_id, quantity FROM booking_equipment WHERE booking_id = ?");
                $itemsStmt->execute([$bookingId]);
                $items = $itemsStmt->fetchAll();

                foreach ($items as $item) {
                    $pdo->prepare("UPDATE equipment SET stock = stock + ? WHERE eq_id = ?")
                        ->execute([$item['quantity'], $item['eq_id']]);
                }

                // 2. Delete Related Data (Payments, Equipment records)
                // หมายเหตุ: ถ้าใช้ ON DELETE CASCADE ใน Database จะลบอัตโนมัติ แต่เราจะลบ manual เพื่อแน่ใจ
                $pdo->prepare("DELETE FROM booking_equipment WHERE booking_id = ?")->execute([$bookingId]);
                $pdo->prepare("DELETE FROM payments WHERE booking_id = ?")->execute([$bookingId]);
                
                // 3. Delete Booking
                $pdo->prepare("DELETE FROM bookings WHERE booking_id = ?")->execute([$bookingId]);
                
                $message = 'Booking deleted successfully. Equipment stock restored.';
            }
            
            $pdo->commit();
            
            // Refresh data
            $stmt->execute($params);
            $bookings = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
    <style>
        /* Quick style for delete button if needed */
        .btn-delete {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        .btn-delete:hover { background: #FECACA; }
    </style>
</head>
<body>

    <div class="admin-layout">
        
              <!-- Sidebar (Consistent with other pages) -->
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
                <a href="dashboard.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="analytics.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    Analytics
                </a>
                <a href="sports.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                    Sports & Courts
                </a>
                <a href="bookings.php" class="admin-nav-item active">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    Bookings
                </a>
                <a href="payments.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                    Payments
                </a>
                <a href="members.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Members
                </a>
                <a href="reports.php" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Reports
                    <?php if ($pendingReports > 0): ?>
                    <span style="background: #DC2626; color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; margin-left: auto;"><?= $pendingReports ?></span>
                    <?php endif; ?>
                </a>
            </nav>
            
            <div style="margin-top: auto; padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                <a href="<?= SITE_URL ?>/api/auth.php?action=admin_logout" class="admin-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Main -->
        <main class="admin-main">
            
            <div class="admin-header">
                <h1 class="admin-title">Booking Management</h1>
            </div>
            
            <?php if ($message): ?>
            <div class="admin-toast" style="background: #DCFCE7; border-color: #BBF7D0; color: #166534;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= $message ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="admin-toast" style="background: #FEF2F2; border-color: #FECACA; color: #991B1B;">
                <strong>Error:</strong> <?= $error ?>
            </div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <div class="admin-card-body" style="padding: 1rem 1.5rem;">
                    <form method="GET" class="filter-bar">
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="admin-input">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Date</label>
                            <input type="date" name="date" value="<?= $dateFilter ?>" class="admin-input">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Apply Filter
                        </button>
                        
                        <a href="bookings.php" class="btn" style="background: #F1F5F9; color: var(--admin-text);">Clear</a>
                    </form>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="admin-card">
                <div class="admin-card-body" style="padding: 0;">
                    <div style="overflow-x: auto;">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Booking Info</th>
                                    <th style="width: 15%;">Customer</th>
                                    <th style="width: 20%;">Details</th>
                                    <th style="width: 10%;">Amount</th>
                                    <th style="width: 10%;">Slip</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 20%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                            <h3>No Bookings Found</h3>
                                            <p>Try adjusting your filters.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td>
                                            <span class="admin-code"><?= htmlspecialchars($b['booking_code']) ?></span><br>
                                            <small style="color: var(--admin-muted);"><?= date('d M Y', strtotime($b['booking_date'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($b['username']) ?></strong><br>
                                            <small style="color: var(--admin-muted);"><?= htmlspecialchars($b['phone']) ?></small>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--admin-secondary);"><?= htmlspecialchars($b['sport_name']) ?></div>
                                            <small>Court <?= $b['court_number'] ?> • <?= date('g:i A', strtotime($b['start_time'])) ?></small>
                                        </td>
                                        <td>
                                            <strong>฿<?= number_format($b['total_price']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($b['slip_image']): ?>
                                            <a href="<?= SITE_URL ?>/<?= $b['slip_image'] ?>" target="_blank" class="btn btn-sm" style="background: #EFF6FF; color: var(--admin-primary);">
                                                View
                                            </a>
                                            <?php else: ?>
                                            <span style="color: var(--admin-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-pill status-<?= htmlspecialchars($b['payment_status']) ?>">
                                                <?= ucfirst($b['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <!-- Verify/Reject Buttons (Only for Pending with Slip) -->
                                                <?php if ($b['payment_status'] === 'pending' && $b['slip_image']): ?>
                                                    <form method="POST" style="display: contents;">
                                                        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                        <input type="hidden" name="action" value="verify">
                                                        <button type="submit" class="btn btn-sm btn-success">Verify</button>
                                                    </form>
                                                    <form method="POST" style="display: contents;" onsubmit="return confirm('Reject this payment?');">
                                                        <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                    </form>
                                                <?php elseif ($b['payment_status'] === 'pending'): ?>
                                                    <span style="color: var(--admin-warning); font-size: 0.8rem;">Awaiting Slip</span>
                                                <?php else: ?>
                                                    <span style="color: var(--admin-muted);">-</span>
                                                <?php endif; ?>

                                                <!-- DELETE BUTTON (Always Visible) -->
                                                <form method="POST" style="display: contents;" onsubmit="return confirm('Are you sure you want to DELETE this booking? This cannot be undone.');">
                                                    <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-delete" title="Delete Booking">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
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
</body>
</html>