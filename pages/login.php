<?php
// เริ่มแรกสุดเลย คือดึงเอาไฟล์ตั้งค่าต่างๆ (เช่น การเชื่อมฐานข้อมูล, ฟังก์ชันช่วยเหลือ) มาใช้งานก่อน
require_once '../config.php';

// Redirect if already logged in
// เช็คดูก่อนว่า "เอ้ย คนนี้ล็อกอินอยู่แล้วหรือยัง?" ถ้าใช่ ก็ไม่ต้องให้มากรอกฟอร์มอีก ส่งตัวไปหน้าหลักเลย
if (isLoggedIn()) {
     redirect('../index.php');
}

 // สร้างตัวแปรเก็บข้อความ error เตรียมไว้ก่อน เผื่อไว้ด่าเวลาผู้ใช้กรอกอะไรผิดๆ
 $error = '';

// Handle login form
// ตรงนี้คือจุดสำคัญ เช็คว่า "มีคนกดปุ่ม Login ส่งข้อมูลมาหรือยัง?" (Method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ถ้ามาแล้ว ก็เอาข้อมูลที่ส่งมาเก็บใส่ตัวแปร และอย่าลืม "sanitize" คือทำความสะอาดข้อมูล username ด้วยกันคนใจร้ายแอบใส่โค้ดมุดเข้ามา
    $username = sanitize($_POST['username'] ?? '');
    // ส่วนรหัสผ่านเอามาเลย ยังไม่ต้องทำอะไร
    $password = $_POST['password'] ?? '';
    
    // เช็คความป่าวๆ ก่อน: "เฮ้ย กรอกครบไหม?" ถ้าเว้นวรรคไว้ก็ด่าทันที
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        // ถ้ากรอกครบแล้ว ก็ไปถามฐานข้อมูลเลยว่า "มี User นี้ไหม?" (ค้นหาจาก username หรือ email ก็ได้)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        // ดึงข้อมูล user ออกมา
        $user = $stmt->fetch();
        
        // เจอ User แล้วเหรอ? ถ้าเจอ ก็มาต่อว่า "รหัสผ่านที่กรอกมาตรงกับที่เข้ารหัสไว้ในระบบไหม?"
        // ใช้ password_verify ในการเทียบรหัสผ่านแบบปลอดภัย
        if ($user && password_verify($password, $user['password'])) {
            // โอเค ผ่านเลย! รหัสถูกต้อง ก็ทำการ "จดจำ" ตัวตนของเขาไว้ใน Session
            // เอา ID, Username และสถานะ Member ไปเก็บไว้ เพื่อจะได้รู้ว่าใครล็อกอินอยู่
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_member'] = $user['is_member'];
            
            // จบขั้นตอน ส่งตัวไปหน้าหลัก (Index) ทันที
            redirect('../index.php');
        } else {
            // แต่ถ้าหา User ไม่เจอ หรือรหัสผ่านผิด ก็แจ้งเตือนว่า "ข้อมูลไม่ถูกต้อง"
            $error = 'Invalid username or password';
        }
    }
}
?>

<!-- ตรงนี้เริ่มส่วนของ HTML หน้าตาเว็บไซต์ละ -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- ตั้งชื่อหน้าเว็บว่า Login - Hit The Court -->
    <title>Login - Hit The Court</title>
    
    <!-- Google Fonts -->
    <!-- ดึงฟอนต์สวยๆ จาก Google มาใช้ 2 ตัวคือ Inter กับ Space Grotesk -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- NEW CSS File -->
    <!-- เรียกไฟล์ CSS มาแต่งหน้าตาให้สวยงาม -->
    <link rel="stylesheet" href="../auth.css">
</head>
<body>

    <!-- สร้างกล่องหลักครอบทุกอย่างไว้ -->
    <div class="auth-wrapper">
        <!-- Left Side: Hero / Branding -->
        <!-- ส่วนนี้คือ "ฝั่งซ้าย" ของหน้าจอ มีไว้อวดโฉมแบรนด์ -->
        <div class="auth-hero">
            <!-- รูปพื้นหลังสวยๆ สนามเทนนิส -->
            <img src="https://images.unsplash.com/photo-1551698618-1dfe5d97d256?auto=format&fit=crop&w=800&q=80" alt="Tennis Court" class="auth-hero-bg">
            
            <div class="auth-hero-content">
                <!-- โลโก้ใหญ่ HIT THE COURT -->
                <div class="auth-hero-logo">
                    HIT THE <span>COURT</span>
                </div>
                <!-- หัวข้อต้อนรับ -->
                <h1 class="auth-hero-title">Welcome to Hit The Court</h1>
                <!-- ข้อความก่อนใจ ประมาณว่า มาจองสนามกันเถอะ -->
                <p class="auth-hero-subtitle">
                    Where every match begins with your decision.<br>
                    Log in to secure your spot and own the game.
                </p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <!-- ส่วนนี้คือ "ฝั่งขวา" คือฟอร์มสำหรับกรอกข้อมูลล็อกอิน -->
        <div class="auth-form-container">
            <div class="auth-form-box">
                <!-- หัวข้อฟอร์ม -->
                <div class="auth-header">
                    <h2 class="auth-title">Welcome Back</h2>
                    <p class="auth-subtitle">Please log in to your account</p>
                </div>
                
                <!-- ตรงนี้คือช่องแจ้งเตือน: ถ้ามี error จากการกรอกผิดด้านบน ก็จะโชว์กล่องแดงๆ ตรงนี้ -->
                <?php if ($error): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <!-- เริ่มฟอร์มกรอกข้อมูล -->
                <form method="POST" action="">
                    <!-- ช่องแรก: ให้กรอก Username หรือ Email -->
                    <div class="form-group">
                        <label class="form-label" for="username">Username or Email</label>
                        <input type="text" 
                               class="form-input" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username or email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required>
                    </div>
                    
                    <!-- ช่องที่สอง: รหัสผ่าน (ปิดบังรหัสด้วย type="password") -->
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" 
                               class="form-input" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                    </div>
                    
                    <!-- ปุ่มกด "LOGIN" เพื่อส่งข้อมูล -->
                    <button type="submit" class="btn-primary">
                        LOGIN
                    </button>
                </form>
                
                <!-- ส่วนท้ายฟอร์ม: ถ้าใครยังไม่มีบัญชี ก็มีลิงก์ให้ไปสมัคร -->
                <div class="auth-footer">
                    <p>don't have an account? <a href="<?= SITE_URL ?>/pages/register.php">sign up here</a></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>