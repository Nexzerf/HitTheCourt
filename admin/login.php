<?php
// เริ่มต้นด้วยการดึงไฟล์ config.php เข้ามา เพื่อใช้ฟังก์ชันฐานข้อมูลและตัวแปรระบบ
require_once '../config.php';

// เช็คก่อนเลยว่า "แอดมินล็อกอินแล้วหรือยัง?"
// ถ้าล็อกอินแล้วก็ไม่ต้องมาไถพาสเวิร์ดอีก จัดการพาไปหน้า Dashboard ทันที
if (isAdmin()) {
    redirect('/admin/dashboard.php');
}

// ตัวแปรสำหรับเก็บข้อความ Error ไว้แสดงผลถ้าล็อกอินไม่สำเร็จ
 $error = '';

// ถ้าผู้ใช้กดปุ่ม "Sign In" (Method เป็น POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่า Username มาและทำความสะอาดข้อมูล (sanitize) เพื่อความปลอดภัย
    $username = sanitize($_POST['username'] ?? '');
    // รับค่า Password (อันนี้เราจะไม่ sanitize เพราะต้องการรหัสผ่านตรงๆ ไป verify)
    $password = $_POST['password'] ?? '';
    
    // ไปค้นหาในฐานข้อมูลว่ามี User ที่เป็น Admin ชื่อนี้ไหม และสถานะต้องเป็น 'active' ด้วย
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    // ถ้าเจอ Admin และ รหัสผ่านที่กรอกตรงกับรหัสผ่านที่เข้ารหัสไว้ในระบบ (password_verify)
    if ($admin && password_verify($password, $admin['password'])) {
        // ถ้าผ่านเงื่อนไข ก็ทำการ "เก็บข้อมูล Session" ไว้ว่าคนนี้คือใคร
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        // อัปเดตเวลาล็อกอินล่าสุดในฐานข้อมูล เผื่อจะดู Log ภายหลัง
        $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?")->execute([$admin['admin_id']]);
        
        // พาท่านแอดมินไปหน้า Dashboard ทันที
        redirect('/admin/dashboard.php');
    } else {
        // ถ้าชื่อผู้ใช้ไม่มี หรือ รหัสผ่านผิด ก็ให้แสดงข้อความแจ้งเตือน
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Hit The Court</title>
    <!-- ดึงฟอนต์สวยๆ จาก Google Fonts มาใช้ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- ดึงไฟล์ CSS หลักมาแต่งหน้าตาเว็บ -->
    <link rel="stylesheet" href="../style.css">
</head>
<!-- ตกแต่ง Body ให้พื้นหลังเป็นสีเข้ม (gray-900) และจัดให้ฟอร์มอยู่กึ่งกลางจอ (flex center) -->
<body style="background: var(--gray-900); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    
    <!-- กล่อง Card สำหรับฟอร์มล็อกอิน จำกัดความกว้างไม่ให้กว้างเกินไป -->
    <div class="card animate-slideUp" style="max-width: 400px; width: 100%; margin: 1rem;">
        <div class="card-body" style="padding: 2.5rem;">
            <!-- ส่วนหัวของ Card: แสดงโลโก้และชื่อระบบ -->
            <div class="text-center mb-4">
                <!-- โลโก้รูปสี่เหลี่ยมมุมมน สื่อว่าเป็นระบบหลังบ้าน -->
                <div style="width: 64px; height: 64px; background: var(--primary); border-radius: 1rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                </div>
                <h2>Admin Panel</h2>
                <p class="text-muted">Hit The Court Management</p>
            </div>
            
            <!-- ถ้ามี Error (รหัสผ่านผิด) จะแสดงข้อความตรงนี้ -->
            <?php if ($error): ?>
            <div class="toast error mb-3" style="display: block;"><?= $error ?></div>
            <?php endif; ?>
            
            <!-- ฟอร์มสำหรับกรอกข้อมูลเข้าสู่ระบบ -->
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <!-- ช่องกรอก Username -->
                    <input type="text" name="username" class="form-control" required placeholder="Admin username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <!-- ช่องกรอก Password (type="password" เพื่อซ่อนตัวอักษร) -->
                    <input type="password" name="password" class="form-control" required placeholder="Password">
                </div>
                
                <!-- ปุ่มกดเข้าสู่ระบบ -->
                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    Sign In
                </button>
            </form>
            
            <!-- ลิงก์สำหรับกลับไปหน้าเว็บหลัก (หน้าแรกของผู้ใช้ทั่วไป) -->
            <div class="text-center mt-4">
                <a href="<?= SITE_URL ?>/" style="color: var(--gray-500); font-size: 0.875rem;">
                    ← Back to Website
                </a>
            </div>
        </div>
    </div>
    
</body>
</html>