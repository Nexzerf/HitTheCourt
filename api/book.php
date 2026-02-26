<?php
// ดึงเอาไฟล์ตั้งค่าหลัก (config.php) เข้ามาก่อน เพื่อเชื่อมฐานข้อมูลและใช้ฟังก์ชันต่างๆ
require_once '../config.php';
// เช็คว่า "ล็อกอินแล้วหรือยัง?" ถ้ายังไม่ล็อกอินจะไม่ให้ทำต่อ
requireLogin();

// ถ้าไม่ใช่การส่งข้อมูลแบบ POST (แอบพิมพ์ URL เข้ามาตรงๆ) ให้ดีดกลับไปหน้าเลือกสนาม
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/courts');
}

// 1. Get Data
// รับข้อมูลที่ส่งมาจากฟอร์มการจอง
 $sportId = intval($_POST['sport_id'] ?? 0);
 $bookingDate = $_POST['booking_date'] ?? '';
 $slotCourt = $_POST['slot_court'] ?? ''; // ค่านี้จะเป็นรูปแบบ "courtId_slotId"
 $equipmentData = $_POST['equipment'] ?? []; // รายการอุปกรณ์ที่เลือกมา

// Validation
// เช็คเบื้องต้นว่าข้อมูลครบไหม ถ้าไม่ครบให้แจ้งเตือน
if (!$sportId || !$bookingDate || !$slotCourt) {
    die("Please select a time slot.");
}

// แยก string ออกมาเป็น courtId กับ slotId (เช่น "1_5" แยกเป็น 1 กับ 5)
list($courtId, $slotId) = explode('_', $slotCourt);

try {
    // เปิด Transaction: เป็นการ "มัดรวม" ขั้นตอนทั้งหมดไว้ด้วยกัน
    // ถ้าทำสำเร็จทุกอย่างถึงจะบันทึก ถ้าผิดพลาดตรงไหนจะยกเลิกทั้งหมด (Rollback) ป้องกันข้อมูลพัง
    $pdo->beginTransaction();

    // --- CHECK MEMBERSHIP STATUS ---
    // ไปดึงข้อมูลผู้ใช้ว่าเป็นสมาชิกหรือเปล่า และสมาชิกหมดอายุหรือยัง
    $userStmt = $pdo->prepare("SELECT is_member, member_expire FROM users WHERE user_id = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $user = $userStmt->fetch();
    
    $isPremium = false;
    // ถ้าเป็นสมาชิก และ วันหมดอายุยังไม่ถึง ก็ถือว่าเป็น Premium
    if ($user['is_member'] && $user['member_expire'] >= date('Y-m-d')) {
        $isPremium = true;
    }

    // --- 1. CHECK ADVANCE BOOKING LIMIT ---
    // เช็คว่าจองล่วงหน้าได้กี่วัน (สมาชิกจองได้ 7 วัน, คนทั่วไป 3 วัน)
    $maxDays = $isPremium ? 7 : 3;
    $maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
    
    if ($bookingDate > $maxDate) {
        throw new Exception("Booking limit exceeded. You can only book up to {$maxDays} days in advance.");
    }

    // 2. Check Court Status
    // เช็คว่าสนามที่เลือกมา สถานะเป็น 'available' จริงๆ ไหม
    $courtStmt = $pdo->prepare("SELECT status FROM courts WHERE court_id = ?");
    $courtStmt->execute([$courtId]);
    $courtData = $courtStmt->fetch();

    if (!$courtData || $courtData['status'] !== 'available') {
        throw new Exception("This court is currently unavailable.");
    }

    // 3. Double Check Overlap (ปรับปรุงใหม่: ไม่นับ Booking ที่หมดอายุแล้ว)
    // ส่วนสำคัญมาก! เช็คว่าช่วงเวลานี้ถูกจองไปแล้วหรือยัง
    // เช็คว่ามีการจองที่จ่ายเงินแล้ว หรือ จองไว้ชั่วคราวและยังไม่หมดอายุ (expires_at > NOW)
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
    // ดึงราคาหลักของกีฬานั้นๆ มาคำนวณ
    $sportStmt = $pdo->prepare("SELECT price, duration_minutes, sport_name FROM sports WHERE sport_id = ?");
    $sportStmt->execute([$sportId]);
    $sport = $sportStmt->fetch();
    
    $baseCourtPrice = $sport['price'] ?? 0;
    $duration = $sport['duration_minutes'] ?? 60;
    $courtPrice = $baseCourtPrice; 
    
    $discountAmount = 0;
    $discountReason = [];

    // --- APPLY MEMBER DISCOUNTS ---
    // ถ้าเป็นสมาชิก Premium จะเริ่มมีสิทธิพิเศษลดราคา
    if ($isPremium) {
        // A. Discount 30% on 1st & 16th: ลด 30% ถ้าจองวันที่ 1 หรือ 16 ของเดือน
        $dayOfMonth = date('j', strtotime($bookingDate));
        if ($dayOfMonth == 1 || $dayOfMonth == 16) {
            $discountAmt = $courtPrice * 0.30;
            $discountAmount += $discountAmt;
            $courtPrice -= $discountAmt;
        }

        // B. Discount 10% First Booking of this Sport: ลด 10% ถ้าเป็นการจองกีฬานี้ครั้งแรกของเค้า
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
    // ตาร้ายผังกำหนดว่า ถ้าเป็นสมาชิก อุปกรณ์แบบไหนได้ฟรีกี่ชิ้น
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

    // วนลูปคำนวณราคาอุปกรณ์ที่เลือกมาทีละชิ้น
    foreach ($equipmentData as $eqId => $qty) {
        $qty = intval($qty);
        if ($qty > 0) {
            $eqStmt = $pdo->prepare("SELECT * FROM equipment WHERE eq_id = ?");
            $eqStmt->execute([$eqId]);
            $eq = $eqStmt->fetch();

            if (!$eq) continue;
            // เช็คว่าของใน Stock พอไหม
            if ($qty > $eq['stock']) {
                throw new Exception("Not enough stock for " . $eq['eq_name']);
            }

            // Calculate Free Units
            // คำนวณว่ามีสิทธิ์ฟรีกี่ชิ้น (ถ้าเป็นสมาชิก)
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

            // เอาจำนวนที่เลือก ลบ ด้วยจำนวนที่ฟรี = จำนวนที่ต้องจ่ายเงิน
            $paidQty = max(0, $qty - $freeQty);
            $subtotal = $paidQty * $eq['price'];
            
            $equipmentTotal += $subtotal;
            $totalPrice += $subtotal;

            // เก็บข้อมูลไว้เพื่อเตรียมบันทึก
            $equipmentDetails[] = [
                'id' => $eqId,
                'qty' => $qty,
                'price' => $eq['price'],
                'subtotal' => $subtotal,
                'free_qty' => $freeQty
            ];
        }
    }

    // [หมายเหตุ: ตามโค้ดต้นฉบับที่ได้มา ช่วงนี้จะขาดการ INSERT ข้อมูลลงตาราง bookings ไป
    // ทำให้ตัวแปร $bookingId ยังไม่มีค่า แต่โค้ดด้านล่างเรียกใช้ $bookingId อยู่
    // หากนำไปรันจริงจะต้องเพิ่มคำสั่ง INSERT INTO bookings ตรงนี้ก่อนครับ]

    // 5. Save Equipment & Update Stock
    $bookingCode = generateBookingCode();
    
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
    // บันทึกรายการอุปกรณ์ที่เลือก และตัด Stock ออกจากคลังทันที
    foreach ($equipmentDetails as $item) {
        $stmt = $pdo->prepare("INSERT INTO booking_equipment (booking_id, eq_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$bookingId, $item['id'], $item['qty'], $item['price'], $item['subtotal']]);
        
    }

    // ยืนยันการทำธุรกรรมทั้งหมด (Commit)
    $pdo->commit();

    // พาผู้ใช้ไปหน้าชำระเงิน
    redirect('/pay_booking?id=' . $bookingId);

} catch (Exception $e) {
    // ถ้ามี Error ตรงไหน ให้ยกเลิกการทำธุรกรรมทั้งหมด (Rollback)
    $pdo->rollBack();
    die("Booking failed: " . $e->getMessage());
}
?>