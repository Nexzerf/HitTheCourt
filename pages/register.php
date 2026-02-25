<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

 $errors = [];
 $success = '';

// Handle registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($phone)) $errors['phone'] = 'Phone number is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    
    if (empty($errors)) {
        // Check duplicates
        $check = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            $errors['general'] = 'Username or Email already exists';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $phone, $hashedPassword])) {
                $success = 'Registration successful! You can now login.';
                $_POST = []; // Clear form
            } else {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- NEW CSS File -->
    <link rel="stylesheet" href="../auth.css">
</head>
<body>

    <div class="auth-wrapper">
        <!-- Left Side: Register Form (Match Reference Image) -->
        <div class="auth-form-container">
            <div class="auth-form-box">
                <div class="auth-header">
                    <h2 class="auth-title">SIGN UP</h2>
                    <p class="auth-subtitle">Welcome to Hit The Court</p>
                </div>
                
                <?php if (!empty($errors['general'])): ?>
                <div class="alert-error"><?= $errors['general'] ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert-success"><?= $success ?> <a href="login.php">Login here</a></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input <?= isset($errors['username']) ? 'error' : '' ?>" placeholder="Choose a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <?php if (isset($errors['username'])): ?><span class="form-error"><?= $errors['username'] ?></span><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input <?= isset($errors['password']) ? 'error' : '' ?>" placeholder="Create a password">
                        <?php if (isset($errors['password'])): ?><span class="form-error"><?= $errors['password'] ?></span><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input <?= isset($errors['email']) ? 'error' : '' ?>" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <?php if (isset($errors['email'])): ?><span class="form-error"><?= $errors['email'] ?></span><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input <?= isset($errors['phone']) ? 'error' : '' ?>" placeholder="Enter your phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <?php if (isset($errors['phone'])): ?><span class="form-error"><?= $errors['phone'] ?></span><?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        SIGN UP
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>already have an account? <a href="<?= SITE_URL ?>/pages/login.php">login here</a></p>
                </div>
            </div>
        </div>

        <!-- Right Side: Hero / Branding (Match Reference Image) -->
        <div class="auth-hero">
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