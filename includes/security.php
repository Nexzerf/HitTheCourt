<?php
// เช็คก่อนเลยว่า Session เริ่มต้นแล้วหรือยัง? ถ้ายัง (NONE) ก็สั่งเปิด Session ทันที
// เพราะเราต้องใช้ Session ในการเก็บ Token ตัวนี้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF Token
// ฟังก์ชันนี้คือ "โรงงานผลิตกุญแจลับ"
function generateCSRFToken() {
    // เช็คก่อนว่าใน Session มี Token อยู่แล้วหรือยัง? ถ้ายังไม่มี (empty) ถึงจะสร้างใหม่
    if (empty($_SESSION['csrf_token'])) {
        // สุ่มข้อมูลแบบไบนารี่ 32 ไบต์ (random_bytes) แล้วแปลงเป็นตัวอักษรฐาน 16 (bin2hex)
        // ได้ออกมาเป็นสตริงยาวๆ ที่เดาได้ยากมาก ใช้แทนตัวตนของฟอร์มนี้
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    // คืนค่า Token ตัวนี้กลับไปให้ฟอร์มเอาไปแนบส่งมาด้วย
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
// ฟังก์ชันนี้คือ "เจ้าหน้าที่ตรวจบัตร" คอยเช็คว่า Token ที่ส่งมาใช้ได้ไหม
function verifyCSRFToken($token) {
    // ใช้ hash_equals ในการเปรียบเทียบ แทนการใช้ === ตรงๆ
    // ทั้งนี้เพื่อป้องกันการโจมตีแบบ Timing Attack (การคาดเดารหัสผ่านจากเวลาที่ระบบใช้ในการเปรียบเทียบ)
    // ถ้าตรงกัน (True) แสดงว่าเป็นฟอร์มแท้จากเว็บเรา ไม่ใช่ของปลอม
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Add this to config.php:
// เตือนความจำว่าให้เอาโค้ดนี้ไปวางใน config.php หรือ include ไฟล์นี้เข้าไปด้วย
// require_once 'includes/security.php';