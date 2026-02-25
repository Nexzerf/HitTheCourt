<?php
require_once '../config.php';

// รับค่า action จาก URL
 $action = $_GET['action'] ?? '';

// ==================================================
// LOGOUT สำหรับผู้ใช้ทั่วไป
// ==================================================
if ($action === 'logout') {
    // 1. ลบ Session Variables ทั้งหมด
    $_SESSION = array();

    // 2. ลบ Session Cookie (ถ้ามีการใช้งาน)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. ทำลาย Session อย่างสมบูรณ์
    session_destroy();

    // 4. Redirect ไปหน้าแรก
    // ใช้ header ตรงๆ เพื่อความเร็วและแน่ใจว่าไปถูกที่
    header("Location: " . SITE_URL . "/index.php");
    exit();
}

// ==================================================
// LOGOUT สำหรับแอดมิน
// ==================================================
if ($action === 'admin_logout') {
    // 1. Unset admin session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_role']);
    
    // หากต้องการลบ Session ทั้งก้อนเลยก็ใช้:
    // $_SESSION = array();
    // session_destroy();

    // 2. Redirect ไปหน้า Admin Login
    header("Location: " . SITE_URL . "/admin/login.php");
    exit();
}

// ==================================================
// DEFAULT: ถ้าไม่มี action ให้กลับหน้าแรก
// ==================================================
header("Location: " . SITE_URL);
exit();
?>