<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user']['id'])) {
    header("Location: dashboard.php");
    exit;
}

$status = $_GET['status'] ?? '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = $_GET['error'] ?? '';
$identifier = $_GET['identifier'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LIBRA Book Borrowing System</title>
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
        .alert-sneat-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(113, 221, 55, 0.2);
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
                <h4 class="auth-title">Forgot Password? 🔒</h4>
                <p class="auth-subtitle">Enter your email or username and we'll send you instructions to reset your password</p>
            </div>

            <?php if ($error === 'empty_field'): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>Please enter your username or registered email.</span>
                </div>
            <?php elseif ($error === 'user_not_found'): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>No active user account found matching "<strong><?= htmlspecialchars($identifier) ?></strong>".</span>
                </div>
            <?php elseif ($error === 'invalid_token'): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>Invalid or expired password reset link. Please submit a new request.</span>
                </div>
            <?php endif; ?>

            <?php if ($status === 'sent' && !empty($token)): ?>
                <div class="alert-sneat alert-sneat-success" style="flex-direction: column; align-items: flex-start; gap: 0.4rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="bx bx-check-double" style="font-size: 1.35rem;"></i>
                        <span style="font-weight: 600;">Reset Instructions Generated!</span>
                    </div>
                    <div style="font-size: 0.8125rem; line-height: 1.4;">
                        Password reset authorization has been created for <strong><?= htmlspecialchars($email) ?></strong>.
                    </div>
                </div>

                <div style="background: rgba(105, 108, 255, 0.08); border: 1px dashed var(--primary); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem; text-align: center;">
                    <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                        <i class="bx bx-link-external"></i> Click below to proceed with setting a new password:
                    </div>
                    <a href="reset-password.php?token=<?= urlencode($token) ?>" class="btn btn-primary" style="width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 0.5rem; text-decoration: none;">
                        <i class="bx bx-key"></i> Proceed to Reset Password
                    </a>
                </div>
            <?php endif; ?>

            <form action="api/auth.php" method="POST">
                <input type="hidden" name="action" value="forgot_password">
                
                <div class="form-group">
                    <label class="form-label" for="identifier">Email or Username</label>
                    <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Enter your username or email" value="<?= htmlspecialchars($identifier) ?>" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.65rem; margin-top: 0.5rem; font-size: 0.95rem; font-weight: 600;">
                    Send Reset Link
                </button>

                <div style="text-align: center; margin-top: 1.25rem;">
                    <a href="index.php" style="color: var(--primary); font-size: 0.875rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 500;">
                        <i class="bx bx-chevron-left" style="font-size: 1.2rem;"></i> Back to login
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
