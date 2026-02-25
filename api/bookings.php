<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

 $action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'cancel':
        $bookingCode = sanitize($_POST['booking_code'] ?? '');
        
        // Verify booking belongs to user
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_code = ? AND user_id = ?");
        $stmt->execute([$bookingCode, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            redirect('/pages/reservations.php?error=not_found');
            exit;
        }
        
        // Check if cancellation is allowed (24h before)
        $bookingTime = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
        if ($bookingTime < strtotime('+24 hours')) {
            redirect('/pages/reservations.php?error=too_late');
            exit;
        }
        
        // Update status
        $updateStmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = ?");
        $updateStmt->execute([$booking['booking_id']]);
        
        redirect('/pages/reservations.php?cancelled=true');
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}