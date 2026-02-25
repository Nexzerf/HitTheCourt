<?php
// ดึงเอาไฟล์ config (ตั้งค่าระบบ) เข้ามาก่อน
require_once '../config.php';

// Redirect if already logged in
// เช็คดูก่อนว่า "เอ้ย คนนี้ล็อกอินอยู่แล้วหรือยัง?" ถ้าล็อกอินแล้วก็ส่งไปหน้าแรกเลย ไม่ต้องสมัครใหม่
if (isLoggedIn()) {
    redirect('../index.php');
}

// เตรียมตัวแปรไว้เก็บข้อผิดพลาด (Error) และข้อความสำเร็จ
 $errors = [];
 $success = '';

// Handle registration form
// ตรงนี้คือจัดการตอนที่ผู้ใช้กดปุ่ม "Sign Up" ส่งข้อมูลมา (Method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์มและทำความสะอาด (sanitize) ข้อมูลก่อน เพื่อกันโค้ดเลวๆ
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    // เช็คเบื้องต้นว่ากรอกครบทุกช่องไหม? ถ้าว่างก็เก็บ error ไว้
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($phone)) $errors['phone'] = 'Phone number is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    
    // ถ้าผ่านเงื่อนไขเบื้องต้น (ไม่มี error)
    if (empty($errors)) {
        // Check duplicates
        // เช็คในฐานข้อมูลอีกทีว่า "Username หรือ Email นี้มีคนใช้แล้วหรือยัง?"
        $check = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            // ถ้าเจอ แปลว่าซ้ำ ก็แจ้งเตือน
            $errors['general'] = 'Username or Email already exists';
        } else {
            // ถ้าไม่ซ้ำ ก็เข้าสู่ขั้นตอนบันทึก
            // เอารหัสผ่านไปแปลงเป็นรหัสลับ (Hash) ก่อนเก็บ เพื่อความปลอดภัย ไม่เก็บแบบตัวหนังสือเปล่าๆ
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            // เตรียมคำสั่ง SQL เพื่อเพิ่ม User ใหม่ลงไปในระบบ
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $phone, $hashedPassword])) {
                // ถ้าบันทึกสำเร็จ ก็แจ้งว่า "สมัครสำเร็จแล้ว ไปล็อกอินเลย"
                $success = 'Registration successful! You can now login.';
                $_POST = []; // Clear form (ล้างข้อมูลในฟอร์มที่กรอกไว้)
            } else {
                // ถ้าบันทึกไม่สำเร็จ (พัง) ก็แจ้ง error ทั่วไป
                $errors['general'] = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Hit The Court</title>
    
    <!-- Google Fonts -->
    <!-- โหลดฟอนต์สวยๆ จาก Google มาใช้งาน -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- NEW CSS File -->
    <!-- เรียกไฟล์ CSS มาแต่งหน้าตาเว็บให้สวยงาม -->
    <link rel="stylesheet" href="../auth.css">
</head>
<body>

    <!-- กล่องหลักของหน้าสมัครสมาชิก -->
    <div class="auth-wrapper">
        <!-- Left Side: Register Form (Match Reference Image) -->
        <!-- ฝั่งซ้าย: ฟอร์มสมัครสมาชิก -->
        <div class="auth-form-container">
            <div class="auth-form-box">
                <div class="auth-header">
                    <h2 class="auth-title">SIGN UP</h2>
                    <p class="auth-subtitle">Welcome to Hit The Court</p>
                </div>
                
                <!-- ถ้ามี Error ทั่วไป (เช่น User ซ้ำ) จะแสดงกล่องแดงตรงนี้ -->
                <?php if (!empty($errors['general'])): ?>
                <div class="alert-error"><?= $errors['general'] ?></div>
                <?php endif; ?>
                
                <!-- ถ้าสมัครสำเร็จ จะแสดงกล่องเขียวแจ้งเตือนพร้อมลิงก์ไปหน้า Login -->
                <?php if ($success): ?>
                <div class="alert-success"><?= $success ?> <a href="login.php">Login here</a></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- ช่องกรอก Username -->
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <!-- ถ้าเคยกรอกไว้แล้วเกิด Error ตอนโหลดใหม่ จะเอาค่าเก่ามาแสดง (value=...) -->
                        <input type="text" name="username" class="form-input <?= isset($errors['username']) ? 'error' : '' ?>" placeholder="Choose a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <!-- ถ้าช่องนี้มี Error จะแสดงข้อความแจ้งเตือนด้านล่าง -->
                        <?php if (isset($errors['username'])): ?><span class="form-error"><?= $errors['username'] ?></span><?php endif; ?>
                    </div>
                    
                    <!-- ช่องกรอก Password -->
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input <?= isset($errors['password']) ? 'error' : '' ?>" placeholder="Create a password">
                        <?php if (isset($errors['password'])): ?><span class="form-error"><?= $errors['password'] ?></span><?php endif; ?>
                    </div>
                    
                    <!-- ช่องกรอก Email -->
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input <?= isset($errors['email']) ? 'error' : '' ?>" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <?php if (isset($errors['email'])): ?><span class="form-error"><?= $errors['email'] ?></span><?php endif; ?>
                    </div>
                    
                    <!-- ช่องกรอก Phone -->
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input <?= isset($errors['phone']) ? 'error' : '' ?>" placeholder="Enter your phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <?php if (isset($errors['phone'])): ?><span class="form-error"><?= $errors['phone'] ?></span><?php endif; ?>
                    </div>
                    
                    <!-- ปุ่มกด "SIGN UP" เพื่อส่งข้อมูล -->
                    <button type="submit" class="btn-primary">
                        SIGN UP
                    </button>
                </form>
                
                <!-- ท้ายฟอร์ม: ถ้ามีบัญชีแล้วให้ไปล็อกอิน -->
                <div class="auth-footer">
                    <p>already have an account? <a href="<?= SITE_URL ?>/pages/login.php">login here</a></p>
                </div>
            </div>
        </div>

        <!-- Right Side: Hero / Branding (Match Reference Image) -->
        <!-- ฝั่งขวา: ส่วนตกแต่งโชว์ภาพสวยๆ และ Branding -->
        <div class="auth-hero">
            <!-- รูปภาพพื้นหลัง (สนามแบด) -->
            <img src="https://images.unsplash.com/photo-1554068865-24cecd4e34b8?auto=format&fit=crop&w=800&q=80" alt="Badminton Court" class="auth-hero-bg">
            
            <div class="auth-hero-content">
                <div class="auth-hero-logo">
                    HIT THE <span>COURT</span>
                </div>
                <h1 class="auth-hero-title">Join Our Community</h1>
                <p class="auth-hero-subtitle">
                    Book your favorite courts, track your progress, and enjoy the game.
                </p>
            </div>
        </div>
    </div>

</body>
 <!-- Script สำหรับจัดการเมนู Hamburger (ส่วนใหญ่จะใช้กับหน้าอื่น แต่เขียนไว้เผื่อ) -->
 <script>
        document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.getElementById('hamburger-btn');
    const navMenu = document.getElementById('nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }

    // ปิดเมนูเมื่อคลิกข้างนอก (Optional)
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !hamburger.contains(e.target)) {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        }
    });
});
</script>
</html>