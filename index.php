<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Auto-login from Remember Me cookie if session not set
if (empty($_SESSION['user']['id']) && !empty($_COOKIE['libra_remember'])) {
    $autoUser = Database::validateRememberToken($_COOKIE['libra_remember']);
    if ($autoUser) {
        $_SESSION['user'] = $autoUser;
        Database::log('User Login', "Auto-login via Remember Me for {$autoUser['username']} ({$autoUser['role']})");
        header("Location: dashboard.php");
        exit;
    }
}

if (!empty($_SESSION['user']['id'])) {
    header("Location: dashboard.php");
    exit;
}

$rememberedUsername = $_COOKIE['libra_remember_username'] ?? '';
$hasRememberCookie = !empty($_COOKIE['libra_remember_username']) || !empty($_COOKIE['libra_remember']);

$error = $_GET['error'] ?? '';
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LIBRA Book Borrowing System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .alert-sneat {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-sneat-danger {
            background-color: var(--danger-light);
            color: var(--danger);
            border: 1px solid rgba(255, 62, 29, 0.2);
        }
        .alert-sneat-info {
            background-color: var(--info-light);
            color: var(--info);
            border: 1px solid rgba(3, 195, 236, 0.2);
        }
        .alert-sneat-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(113, 221, 55, 0.2);
        }
        .alert-sneat-warning {
            background-color: var(--warning-light);
            color: var(--warning);
            border: 1px solid rgba(255, 171, 0, 0.2);
        }
    </style>
</head>
<body>

<div class="authentication-wrapper">
    <div class="authentication-inner">
        <div class="card-auth">
            <!-- LIBRA Brand Header -->
            <div class="auth-header">
                <div class="auth-brand" style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="assets/img/libra-logo.png" alt="LIBRA Logo" style="width: 44px; height: 44px; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0, 119, 200, 0.25);">
                    <span class="app-brand-text" style="font-size: 1.6rem; font-weight: 800; letter-spacing: 0.5px; color: var(--text-heading);">LIBRA</span>
                </div>
                <h4 class="auth-title">Welcome to LIBRA! 👋</h4>
                <p class="auth-subtitle">Please sign-in to your account and manage library books</p>
            </div>

            <?php if ($error === 'failed_attempt'): ?>
                <div class="alert-sneat alert-sneat-danger" style="flex-direction: column; align-items: flex-start; gap: 0.35rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                        <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                        <span>Incorrect Password &bull; Attempt <?= (int)($_GET['attempts'] ?? 1) ?> of 4</span>
                    </div>
                    <div style="font-size: 0.8125rem;">
                        You have <strong><?= (int)($_GET['remaining'] ?? 3) ?> <?= (int)($_GET['remaining'] ?? 3) === 1 ? 'attempt' : 'attempts' ?></strong> left before this account is locked for 15 minutes.
                    </div>
                </div>
            <?php elseif ($error === 'account_locked'): ?>
                <div class="alert-sneat alert-sneat-danger" style="flex-direction: column; align-items: flex-start; gap: 0.4rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                        <i class="bx bx-lock-alt" style="font-size: 1.35rem;"></i>
                        <span>Account Locked (4 Failed Attempts)</span>
                    </div>
                    <div style="font-size: 0.8125rem; line-height: 1.4;">
                        Maximum 4 login attempts reached. Your account has been temporarily locked for <strong><?= htmlspecialchars($_GET['lock_mins'] ?? '15') ?> minutes</strong>.
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.8125rem;">
                        Need access now? <a href="forgot-password.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Reset your password to unlock immediately</a>.
                    </div>
                </div>
            <?php elseif ($error === 'account_still_locked'): ?>
                <div class="alert-sneat alert-sneat-danger" style="flex-direction: column; align-items: flex-start; gap: 0.4rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                        <i class="bx bx-lock-alt" style="font-size: 1.35rem;"></i>
                        <span>Account Currently Locked</span>
                    </div>
                    <div style="font-size: 0.8125rem; line-height: 1.4;">
                        Please wait approximately <strong><?= htmlspecialchars($_GET['remaining_mins'] ?? '15') ?> minute(s)</strong> or <a href="forgot-password.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">reset your password</a> to unlock right away.
                    </div>
                </div>
            <?php elseif ($error === 'invalid_credentials'): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>Invalid username or password. Please try again.</span>
                </div>
            <?php elseif ($error === 'empty_fields'): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>Please enter both username and password.</span>
                </div>
            <?php elseif ($msg === 'logged_out'): ?>
                <div class="alert-sneat alert-sneat-info">
                    <i class="bx bx-check-circle" style="font-size: 1.25rem;"></i>
                    <span>You have been safely signed out.</span>
                </div>
            <?php elseif ($msg === 'session_expired'): ?>
                <div class="alert-sneat alert-sneat-warning">
                    <i class="bx bx-time" style="font-size: 1.25rem;"></i>
                    <span>Your session has expired. Please sign in again.</span>
                </div>
            <?php elseif ($msg === 'password_reset_success'): ?>
                <div class="alert-sneat alert-sneat-success">
                    <i class="bx bx-check-double" style="font-size: 1.25rem;"></i>
                    <span>Password successfully reset! You can now log in with your new password.</span>
                </div>
            <?php endif; ?>

            <form action="api/auth.php" method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label class="form-label" for="username">Email or Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username or email" value="<?= htmlspecialchars($rememberedUsername) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                    </div>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="padding-right: 2.5rem;">
                        <button type="button" id="togglePasswordBtn" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.15rem; display: flex; align-items: center; padding: 0;">
                            <i class="bx bx-hide" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password Row -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0 1.25rem 0;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; margin-bottom: 0;">
                        <input type="checkbox" id="remember" name="remember" value="1" <?= $hasRememberCookie ? 'checked' : '' ?> style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer; border-radius: 4px;">
                        <span style="font-size: 0.875rem; color: var(--text-color);">Remember me</span>
                    </label>
                    <a href="forgot-password.php" style="font-size: 0.875rem; color: var(--primary); text-decoration: none; font-weight: 500;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.65rem; font-size: 0.95rem; font-weight: 600;">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const pwdInput = document.getElementById('password');
    const pwdIcon = document.getElementById('togglePasswordIcon');
    if (toggleBtn && pwdInput && pwdIcon) {
        toggleBtn.addEventListener('click', () => {
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                pwdIcon.classList.remove('bx-hide');
                pwdIcon.classList.add('bx-show');
            } else {
                pwdInput.type = 'password';
                pwdIcon.classList.remove('bx-show');
                pwdIcon.classList.add('bx-hide');
            }
        });
    }
});
</script>
</body>
</html>
