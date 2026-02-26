<?php
// api/cron_cleanup.php
require_once '../config.php';

// ลบ Booking ที่หมดอายุแล้ว และยังไม่จ่ายเงิน
 $stmt = $pdo->query("
    SELECT booking_id FROM bookings 
    WHERE payment_status = 'pending' 
    AND expires_at IS NOT NULL 
    AND expires_at < NOW()
");

 $expiredBookings = $stmt->fetchAll();

if ($expiredBookings) {
    foreach ($expiredBookings as $b) {
        // คืน Stock อุปกรณ์
        $items = $pdo->prepare("SELECT eq_id, quantity FROM booking_equipment WHERE booking_id = ?");
        $items->execute([$b['booking_id']]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare("UPDATE equipment SET stock = stock + ? WHERE eq_id = ?")
                ->execute([$item['quantity'], $item['eq_id']]);
        }
        
        // ลบหรือเปลี่ยนสถานะ Booking
        $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', payment_status = 'expired' WHERE booking_id = ?")
            ->execute([$b['booking_id']]);
    }
    
    echo "Cleaned up " . count($expiredBookings) . " expired bookings.";
} else {
    echo "No expired bookings found.";
}
?>