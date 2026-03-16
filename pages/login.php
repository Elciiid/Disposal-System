<?php
session_start();
if (isset($_SESSION['user_id']) || isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Production Waste Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link href="../styles/login.css" rel="stylesheet">
</head>
<body>

<div class="login-container">
    <div class="login-card shadow-lg">
        <div class="text-center mb-5 mt-2">
            <div class="brand justify-content-center mb-2" style="transform: translateX(-15px);">
                <span class="brand-icon">✦</span> Disposal
            </div>
            <h1 class="fw-bold mb-1" style="font-size: 2rem; color: #181a1f; letter-spacing: -1px;">Welcome Back</h1>
            <p class="text-muted" style="font-size: 0.95rem;">Enter your credentials to access the portal</p>
        </div>

        <?php if(isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert" style="border-radius: 16px; border: none; font-size: 0.9rem; font-weight: 500;">
                <ion-icon name="warning-outline" style="font-size: 1.2rem;"></ion-icon>
                <?= htmlspecialchars($_SESSION['login_error']) ?>
            </div>
            <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>

        <form action="../auth/process_login.php" method="POST">
            <div class="mb-4">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">Username</label>
                <div class="position-relative">
                    <ion-icon name="person-outline" class="input-icon"></ion-icon>
                    <input type="text" class="form-control" name="username" placeholder="e.g. 40021" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">Password</label>
                <div class="position-relative">
                    <ion-icon name="lock-closed-outline" class="input-icon"></ion-icon>
                    <input type="password" class="form-control" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" style="padding: 1rem; font-size: 1.05rem;">
                Sign In <ion-icon name="arrow-forward-outline"></ion-icon>
            </button>
        </form>

        <div class="text-center mt-5 text-muted" style="font-size: 0.8rem; font-weight: 500;">
            Secured by LRNPH Systems
        </div>
    </div>
</div>

</body>
</html>
