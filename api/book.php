<?php
require_once '../config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/courts.php');
}

// 1. Get Data
 $sportId = intval($_POST['sport_id'] ?? 0);
 $bookingDate = $_POST['booking_date'] ?? '';
 $slotCourt = $_POST['slot_court'] ?? '';
 $equipmentData = $_POST['equipment'] ?? [];

// Validation
if (!$sportId || !$bookingDate || !$slotCourt) {
    die("Please select a time slot.");
}

list($courtId, $slotId) = explode('_', $slotCourt);

try {
    $pdo->beginTransaction();

    // --- CHECK MEMBERSHIP STATUS ---
    $userStmt = $pdo->prepare("SELECT is_member, member_expire FROM users WHERE user_id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch();
    
    $isPremium = false;
    if ($user['is_member'] && $user['member_expire'] >= date('Y-m-d')) {
        $isPremium = true;
    }

    // --- 1. CHECK ADVANCE BOOKING LIMIT ---
    $maxDays = $isPremium ? 7 : 3;
    $maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
    
    if ($bookingDate > $maxDate) {
        throw new Exception("Booking limit exceeded. You can only book up to {$maxDays} days in advance.");
    }

    // 2. Check Court Status
    $courtStmt = $pdo->prepare("SELECT status FROM courts WHERE court_id = ?");
    $courtStmt->execute([$courtId]);
    $courtData = $courtStmt->fetch();

    if (!$courtData || $courtData['status'] !== 'available') {
        throw new Exception("This court is currently unavailable.");
    }

    // 3. Double Check Overlap (ปรับปรุงใหม่: ไม่นับ Booking ที่หมดอายุแล้ว)
    // เช็คว่ามีการจองที่จ่ายเงินแล้ว หรือ จองไว้ชั่วคราวและยังไม่หมดอายุ
    $check = $pdo->prepare("
        SELECT booking_id FROM bookings 
        WHERE court_id = ? 
        AND slot_id = ? 
        AND booking_date = ? 
        AND (
            payment_status = 'paid' 
            OR (payment_status = 'pending' AND expires_at > NOW())
        )
    ");
    $check->execute([$courtId, $slotId, $bookingDate]);
    if ($check->fetch()) {
        throw new Exception("Sorry, this slot is currently reserved or booked by someone else.");
    }

    // 4. Calculate Prices
    $sportStmt = $pdo->prepare("SELECT price, duration_minutes, sport_name FROM sports WHERE sport_id = ?");
    $sportStmt->execute([$sportId]);
    $sport = $sportStmt->fetch();
    
    $baseCourtPrice = $sport['price'] ?? 0;
    $duration = $sport['duration_minutes'] ?? 60;
    $courtPrice = $baseCourtPrice; 
    
    $discountAmount = 0;
    $discountReason = [];

    // --- APPLY MEMBER DISCOUNTS ---
    if ($isPremium) {
        // A. Discount 30% on 1st & 16th
        $dayOfMonth = date('j', strtotime($bookingDate));
        if ($dayOfMonth == 1 || $dayOfMonth == 16) {
            $discountAmt = $courtPrice * 0.30;
            $discountAmount += $discountAmt;
            $courtPrice -= $discountAmt;
        }

        // B. Discount 10% First Booking of this Sport
        $firstCheck = $pdo->prepare("
            SELECT COUNT(*) FROM bookings b 
            JOIN courts c ON b.court_id = c.court_id 
            WHERE b.user_id = ? AND c.sport_id = ? AND b.payment_status = 'paid'
        ");
        $firstCheck->execute([$_SESSION['user_id'], $sportId]);
        if ($firstCheck->fetchColumn() == 0) {
            $discountAmt = $baseCourtPrice * 0.10;
            $discountAmount += $discountAmt;
            $courtPrice -= $discountAmt;
        }
    }

    $totalPrice = $courtPrice;
    $equipmentTotal = 0;
    $equipmentDetails = [];

    // --- EQUIPMENT LOGIC (MEMBER FREE UNITS) ---
    $freeUnitsMap = [
        'badminton racket' => 5, 'badminton' => 5,
        'football' => 2,
        'team bib' => 1, 'bib' => 1,
        'cone' => 1, 'training cone' => 1,
        'tennis racket' => 2, 'tennis' => 2,
        'tennis ball' => 3,
        'volleyball' => 2,
        'basketball' => 2,
        'ping-pong ball' => 5, 'table tennis ball' => 5,
        'ping-pong racket' => 2, 'table tennis racket' => 2,
        'futsal ball' => 3, 'futsal' => 3
    ];

    foreach ($equipmentData as $eqId => $qty) {
        $qty = intval($qty);
        if ($qty > 0) {
            $eqStmt = $pdo->prepare("SELECT * FROM equipment WHERE eq_id = ?");
            $eqStmt->execute([$eqId]);
            $eq = $eqStmt->fetch();

            if (!$eq) continue;
            if ($qty > $eq['stock']) {
                throw new Exception("Not enough stock for " . $eq['eq_name']);
            }

            // Calculate Free Units
            $freeQty = 0;
            if ($isPremium) {
                $eqNameLower = strtolower($eq['eq_name']);
                foreach ($freeUnitsMap as $name => $limit) {
                    if (strpos($eqNameLower, $name) !== false) {
                        $freeQty = $limit;
                        break;
                    }
                }
            }

            $paidQty = max(0, $qty - $freeQty);
            $subtotal = $paidQty * $eq['price'];
            
            $equipmentTotal += $subtotal;
            $totalPrice += $subtotal;

            $equipmentDetails[] = [
                'id' => $eqId,
                'qty' => $qty,
                'price' => $eq['price'],
                'subtotal' => $subtotal,
                'free_qty' => $freeQty
            ];
        }
    }

    // 5. Create Booking with Expiry (15 Minutes)
    $bookingCode = generateBookingCode();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes')); // จองได้ 15 นาที
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            user_id, court_id, slot_id, booking_date, booking_code, 
            duration_minutes, court_price, equipment_total, discount_amount, total_price, 
            payment_status, booking_status, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active', ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'], 
        $courtId, 
        $slotId, 
        $bookingDate, 
        $bookingCode, 
        $duration,
        $courtPrice, 
        $equipmentTotal, 
        $discountAmount, 
        $totalPrice,
        $expiresAt
    ]);
    
    $bookingId = $pdo->lastInsertId();

    // 6. Save Equipment & Update Stock
    foreach ($equipmentDetails as $item) {
        $stmt = $pdo->prepare("INSERT INTO booking_equipment (booking_id, eq_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bookingId, $item['id'], $item['qty'], $item['price'], $item['subtotal']]);
        
        // Deduct stock immediately (will be returned if expired via cron)
        $pdo->prepare("UPDATE equipment SET stock = stock - ? WHERE eq_id = ?")->execute([$item['qty'], $item['id']]);
    }

    $pdo->commit();

    redirect('/pages/pay_booking.php?id=' . $bookingId);

} catch (Exception $e) {
    $pdo->rollBack();
    die("Booking failed: " . $e->getMessage());
}
?>