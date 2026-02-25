<?php
require_once '../config.php';

if (isAdmin()) {
    redirect('/admin/dashboard.php');
}

 $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Update last login
        $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?")->execute([$admin['admin_id']]);
        
        redirect('/admin/dashboard.php');
    } else {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body style="background: var(--gray-900); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    
    <div class="card animate-slideUp" style="max-width: 400px; width: 100%; margin: 1rem;">
        <div class="card-body" style="padding: 2.5rem;">
            <div class="text-center mb-4">
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
            
            <?php if ($error): ?>
            <div class="toast error mb-3" style="display: block;"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="Admin username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    Sign In
                </button>
            </form>
            
            <div class="text-center mt-4">
                <a href="<?= SITE_URL ?>/" style="color: var(--gray-500); font-size: 0.875rem;">
                    ← Back to Website
                </a>
            </div>
        </div>
    </div>
    
</body>
</html>