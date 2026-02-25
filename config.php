<?php
/**
 * HIT THE COURT - Main Configuration File
 * 
 * ไฟล์นี้คือ "ศูนย์กลางการตั้งค่า" ของระบบทั้งหมดเลยครับ
 * ไม่ว่าจะเป็นการตั้งค่าฐานข้อมูล, การจัดการ Session (การล็อกอิน),
 * และฟังก์ชันช่วยเหลือ (Helper Functions) ที่ใช้ร่วมกันทั้งเว็บ
 */

// ============================================
// ERROR REPORTING (Development Mode)
// ============================================
// ส่วนนี้คือการเปิด "ไฟส่อง" เพื่อดูว่าโค้ดมีบั๊กตรงไหน
// ตอนนี้ตั้งค่าเป็น 1 (เปิด) เพราะอยู่ในช่วงพัฒนา
// พอจะขึ้นระบบจริงให้เปลี่ยนเป็น 0 เพื่อปิดไม่ให้ผู้ใช้เห็น Error เยอะแยะ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// DATABASE CONFIGURATION
// ============================================
// ตั้งค่า "ชื่อที่อยู่" และ "รหัสผ่าน" สำหรับเข้าถึงฐานข้อมูล MySQL
// เหมือนการบอกโปรแกรมว่า "เดี๋ยวเราจะไปเอาข้อมูลที่บ้านหลังนี้นะ"
define('DB_HOST', 'localhost');
define('DB_NAME', 'hit_the_court');
define('DB_USER', 'root');      // ตอนนี้ใช้ root นะ ถ้าขึ้นจริงต้องเปลี่ยนเป็นชื่ออื่นที่ปลอดภัยกว่านี้
define('DB_PASS', '');          // ตอนนี้ยังไม่มีพาส ถ้าขึ้นจริงต้องตั้งรหัสผ่านที่แข็งแกร่งหน่อย

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_NAME', 'Hit The Court');
// อันนี้สำคัญมาก: คือ "ที่อยู่เว็บไซต์" หลักของเรา
// ต้องเปลี่ยนให้ตรงกับโดเมนจริงของเราด้วย ไม่งั้นลิงก์จะพัง
// Example: http://localhost/hit_the_court OR https://hitthecourt.com
define('SITE_URL', 'http://localhost/hit_the_court'); 

// File Upload Paths
// กำหนดโฟลเดอร์สำหรับเก็บไฟล์ที่อัปโหลดเข้ามา
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// THUNDER API
// กุญแจสำคัญสำหรับเรียกใช้งานบริการภายนอก (API) ที่ชื่อว่า Thunder
define('THUNDER_API_KEY', 'dab3a4df-3ef5-497c-aad7-753343644c2d');

// ============================================
// SESSION CONFIGURATION
// ============================================
// Secure session settings
// ส่วนนี้คือการตั้งค่าความปลอดภัยให้กับ "Session" (ตัวยืนยันตัวตนตอนล็อกอิน)
ini_set('session.cookie_httponly', 1); // ป้องกันไม่ให้ JavaScript มาขโมย Session ID ได้
ini_set('session.use_only_cookies', 1); // บังคับให้ใช้ Cookie เท่านั้นในการเก็บ Session
ini_set('session.cookie_secure', 0);   // ถ้าเว็บมี HTTPS (กุญแจล็อก) ให้เปลี่ยนเป็น 1 นะจะปลอดภัยกว่า

// Start the session
// สั่งให้ Session เริ่มทำงาน ถ้ายังไม่ได้เริ่มก็ให้เริ่มเลย (ใช้คำสั่ง session_start)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONNECTION (PDO)
// ============================================
// ส่วนนี้คือการ "จับมือ" เชื่อมต่อกับฐานข้อมูลจริงๆ โดยใช้ PDO
// ข้อดีคือมันปลอดภัยกว่าวิธีเก่าๆ และป้องกัน SQL Injection ได้ดี
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ให้มันร้องบอกทันทีถ้ามี Error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ดึงข้อมูลมาเป็น Array ง่ายๆ
            PDO::ATTR_EMULATE_PREPARES   => false,                  // ปิดการจำลองเพื่อความปลอดภัยสูงสุด
        ]
    );
} catch (PDOException $e) {
    // ถ้าจับมือไม่สำเร็จ (เชื่อมไม่ติด) ให้บันทึก Log เก็บไว้ลับๆ แล้วแสดงข้อความแจ้งเตือนแบบกว้างๆ
    // ไม่แนะนำให้บอก Error จริงกับผู้ใช้ เดี๋ยวจะรู้โครงสร้างระบบเรา
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// ============================================
// SECURITY HELPER FUNCTIONS
// ============================================

/**
 * Generate a CSRF Token and store it in the session.
 * ตัวนี้คือการสร้าง "กุญแจลับ" (Token) ไว้ป้องกันการโจมตีแบบ CSRF
 * คือการป้องกันไม่ให้คนอื่นมาปลอมแปลงฟอร์มส่งข้อมูลเข้ามา
 * @return string
 */
function generateCSRFToken() {
    // ถ้ายังไม่มี Token ในระบบ ให้สุ่มสร้างขึ้นมาใหม่ซะ (แบบสุ่มยากมาก)
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF Token against the session token.
 * อันนี้คือการตรวจสอบว่า "กุญแจลับ" ที่ส่งมาจากฟอร์ม ตรงกับที่เราเก็บไว้ใน Session ไหม
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    // ใช้ hash_equals เพื่อป้องกันการโจมตีแบบ Timing Attack (การเดากุญแจผิดๆ ถูกๆ)
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// ============================================
// GENERAL HELPER FUNCTIONS
// ============================================

/**
 * Redirect to a specific URL relative to SITE_URL.
 * ฟังก์ชันนี้คือ "เครื่องเทเลพอร์ต" ครับ คือสั่งให้เว็บวิ่งไปหน้าอื่นทันที
 * @param string $url
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit(); // หยุดทำงานทุกอย่างหลังจากสั่งเปลี่ยนทิศทางแล้ว
}

/**
 * Check if a user is logged in.
 * เช็คง่ายๆ ว่า "ผู้ใช้ล็อกอินแล้วหรือยัง?"
 * ดูจากมี user_id ใน Session หรือเปล่า
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if an admin is logged in.
 * อันนี้ก็เหมือนกัน แต่เช็คว่าเป็น "แอดมิน" หรือยัง?
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['admin_id']);
}

/**
 * Force user login requirement.
 * ฟังก์ชันนี้เป็นเหมือน "บอดี้การ์ด" คอยขวางไว้
 * ถ้ายังไม่ได้ล็อกอิน จะไล่ไปหน้าล็อกอินทันที
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/pages/login.php');
    }
}

/**
 * Force admin login requirement.
 * อันนี้คือบอดี้การ์ดของหน้าแอดมิน ถ้าไม่ใช่แอดมินให้ไปหน้าล็อกอินแอดมินทันที
 */
function requireAdmin() {
    if (!isAdmin()) {
        redirect('/admin/login.php');
    }
}

/**
 * Sanitize user input.
 * ฟังก์ชันนี้คือ "เครื่องกรองน้ำ" ครับ ใช้ทำความสะอาดข้อมูลที่ผู้ใช้กรอกเข้ามา
 * ตัด Tag อันตราย, ตัดช่องว่าง และแปลงอักขระพิเศษเพื่อป้องกัน XSS
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate a unique booking code.
 * อันนี้คือเครื่องมือสร้าง "รหัสการจอง" แบบไม่ซ้ำใคร
 * รูปแบบจะเป็น BK + วันที่ + รหัสสุ่ม
 * @return string
 */
function generateBookingCode() {
    return 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Format price with Thai Baht suffix.
 * จัดรูปแบบราคาให้สวยงาม มีคอมม่าคั่น และต่อท้ายด้วย "THB"
 * เช่น 1500 -> 1,500 THB
 * @param float $price
 * @return string
 */
function formatPrice($price) {
    return number_format($price, 0) . ' THB';
}

/**
 * Format date to readable string.
 * เปลี่ยนวันที่จากตัวเลขอ่านยาก ให้เป็นรูปแบบที่คนอ่านออก เช่น 01 Jan 2025
 * @param string $date
 * @return string
 */
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

?>