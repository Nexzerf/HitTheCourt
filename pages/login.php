<?php
require_once '../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
     redirect('../index.php');
}

 $error = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_member'] = $user['is_member'];
            
            redirect('../index.php');
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hit The Court</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- NEW CSS File -->
    <link rel="stylesheet" href="../auth.css">
</head>
<body>

    <div class="auth-wrapper">
        <!-- Left Side: Hero / Branding -->
        <div class="auth-hero">
            <img src="https://images.unsplash.com/photo-1551698618-1dfe5d97d256?auto=format&fit=crop&w=800&q=80" alt="Tennis Court" class="auth-hero-bg">
            
            <div class="auth-hero-content">
                <div class="auth-hero-logo">
                    HIT THE <span>COURT</span>
                </div>
                <h1 class="auth-hero-title">Welcome to Hit The Court</h1>
                <p class="auth-hero-subtitle">
                    Where every match begins with your decision.<br>
                    Log in to secure your spot and own the game.
                </p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="auth-form-container">
            <div class="auth-form-box">
                <div class="auth-header">
                    <h2 class="auth-title">Welcome Back</h2>
                    <p class="auth-subtitle">Please log in to your account</p>
                </div>
                
                <?php if ($error): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
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
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" 
                               class="form-input" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password"
                               required>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        LOGIN
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>don't have an account? <a href="<?= SITE_URL ?>/pages/register.php">sign up here</a></p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>