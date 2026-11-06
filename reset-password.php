<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$token = trim($_GET['token'] ?? '');
$error = $_GET['error'] ?? '';

$userRecord = null;
if (!empty($token)) {
    $userRecord = Database::verifyPasswordResetToken($token);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LIBRA Book Borrowing System</title>
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
                <h4 class="auth-title">Reset Password 🔒</h4>
                <p class="auth-subtitle">Create a new, secure password for your LIBRA library account</p>
            </div>

            <?php if (!$userRecord): ?>
                <div class="alert-sneat alert-sneat-danger">
                    <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                    <span>This password reset link is invalid or has expired.</span>
                </div>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="forgot-password.php" class="btn btn-primary" style="width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 0.5rem;">
                        <i class="bx bx-refresh"></i> Request New Reset Link
                    </a>
                    <div style="margin-top: 1rem;">
                        <a href="index.php" style="color: var(--primary); font-size: 0.875rem; text-decoration: none;">
                            ← Back to login
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- User Badge -->
                <div style="background: rgba(105, 108, 255, 0.08); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.75rem;">
                    <div class="avatar-sneat" style="width: 36px; height: 36px; font-size: 0.875rem;">
                        <?= strtoupper(substr($userRecord['full_name'], 0, 1)) ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.875rem; font-weight: 600; color: var(--text-heading);">
                            <?= htmlspecialchars($userRecord['full_name']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            Username: <strong><?= htmlspecialchars($userRecord['username']) ?></strong> &bull; <?= htmlspecialchars($userRecord['role']) ?>
                        </div>
                    </div>
                </div>

                <?php if ($error === 'empty_fields'): ?>
                    <div class="alert-sneat alert-sneat-danger">
                        <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                        <span>Please fill in both password fields.</span>
                    </div>
                <?php elseif ($error === 'password_too_short'): ?>
                    <div class="alert-sneat alert-sneat-danger">
                        <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                        <span>Password must be at least 6 characters long.</span>
                    </div>
                <?php elseif ($error === 'password_mismatch'): ?>
                    <div class="alert-sneat alert-sneat-danger">
                        <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                        <span>Passwords do not match. Please re-enter them.</span>
                    </div>
                <?php elseif ($error === 'invalid_or_expired'): ?>
                    <div class="alert-sneat alert-sneat-danger">
                        <i class="bx bx-error-circle" style="font-size: 1.25rem;"></i>
                        <span>Failed to update password. Token may have expired.</span>
                    </div>
                <?php endif; ?>

                <form action="api/auth.php" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autofocus minlength="6" style="padding-right: 2.5rem;">
                            <button type="button" id="togglePasswordBtn1" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.15rem; display: flex; align-items: center; padding: 0;">
                                <i class="bx bx-hide" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <small style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">Must be at least 6 characters</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirm New Password</label>
                        <div style="position: relative;">
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="••••••••" required minlength="6" style="padding-right: 2.5rem;">
                            <button type="button" id="togglePasswordBtn2" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.15rem; display: flex; align-items: center; padding: 0;">
                                <i class="bx bx-hide" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.65rem; margin-top: 0.5rem; font-size: 0.95rem; font-weight: 600;">
                        Set New Password
                    </button>

                    <div style="text-align: center; margin-top: 1.25rem;">
                        <a href="index.php" style="color: var(--primary); font-size: 0.875rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 500;">
                            <i class="bx bx-chevron-left" style="font-size: 1.2rem;"></i> Back to login
                        </a>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    function setupToggle(btnId, inputId, iconId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (btn && input && icon) {
            btn.addEventListener('click', () => {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bx-hide');
                    icon.classList.add('bx-show');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bx-show');
                    icon.classList.add('bx-hide');
                }
            });
        }
    }
    setupToggle('togglePasswordBtn1', 'password', 'toggleIcon1');
    setupToggle('togglePasswordBtn2', 'password_confirm', 'toggleIcon2');
});
</script>
</body>
</html>
