<?php
/**
 * LIBRA Activity Logs & Audit Trail Page
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator', 'Librarian']);

$pdo = Database::getConnection();
$user = currentUser();
$role = $user['role'];
$pageTitle = "Activity Logs";

// 1. KPI Metrics
$totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$authLogs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%Login%' OR action LIKE '%Logout%' OR action LIKE '%Password%'")->fetchColumn();
$circLogs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%Borrow%' OR action LIKE '%Return%' OR action LIKE '%Checkout%'")->fetchColumn();
$secLogs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action LIKE '%Lock%' OR action LIKE '%Failed%' OR action LIKE '%Blocked%'")->fetchColumn();

// 2. Filter Category
$category = $_GET['cat'] ?? 'all';
$whereClause = "";
if ($category === 'auth') {
    $whereClause = "WHERE a.action LIKE '%Login%' OR a.action LIKE '%Logout%' OR a.action LIKE '%Password%'";
} elseif ($category === 'circulation') {
    $whereClause = "WHERE a.action LIKE '%Borrow%' OR a.action LIKE '%Return%' OR a.action LIKE '%Checkout%'";
} elseif ($category === 'security') {
    $whereClause = "WHERE a.action LIKE '%Lock%' OR a.action LIKE '%Failed%' OR a.action LIKE '%Blocked%'";
} elseif ($category === 'admin') {
    $whereClause = "WHERE a.action LIKE '%User%' OR a.action LIKE '%Initialization%'";
}

$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.full_name, u.role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $whereClause
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 250
");
$stmt->execute();
$logs = $stmt->fetchAll();

function getActionBadge(string $action): string {
    if (str_contains($action, 'Failed') || str_contains($action, 'Lock') || str_contains($action, 'Blocked')) {
        return 'badge-bg-danger';
    }
    if (str_contains($action, 'Password')) {
        return 'badge-bg-warning';
    }
    if (str_contains($action, 'Return') || str_contains($action, 'Checkout') || str_contains($action, 'Borrow')) {
        return 'badge-bg-success';
    }
    if (str_contains($action, 'Logout')) {
        return 'badge-bg-secondary';
    }
    return 'badge-bg-primary';
}

function getActionIcon(string $action): string {
    if (str_contains($action, 'Failed') || str_contains($action, 'Blocked')) return 'bx-error-circle';
    if (str_contains($action, 'Lock')) return 'bx-lock-alt';
    if (str_contains($action, 'Password')) return 'bx-key';
    if (str_contains($action, 'Checkout') || str_contains($action, 'Borrow')) return 'bx-book-bookmark';
    if (str_contains($action, 'Return')) return 'bx-archive-in';
    if (str_contains($action, 'Logout')) return 'bx-log-out';
    if (str_contains($action, 'Login')) return 'bx-log-in';
    return 'bx-pulse';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.95rem;
            border-radius: var(--radius-pill);
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            transition: var(--transition-fast);
        }
        .filter-pill:hover {
            color: var(--primary);
            border-color: var(--primary);
        }
        .filter-pill.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Metrics Summary Cards -->
            <div class="stat-cards-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar primary">
                            <i class="bx bx-history"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-primary">Audit</span>
                    </div>
                    <div class="stat-label">Total Recorded Events</div>
                    <div class="stat-value"><?= number_format($totalLogs) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-check-shield"></i>
                        <span>System-wide audit trail</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar info">
                            <i class="bx bx-shield-quarter"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-info">Access</span>
                    </div>
                    <div class="stat-label">Authentication Events</div>
                    <div class="stat-value"><?= number_format($authLogs) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-log-in-circle"></i>
                        <span>Logins, Logouts & Resets</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar success">
                            <i class="bx bx-transfer-alt"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-success">Circulation</span>
                    </div>
                    <div class="stat-label">Circulation Operations</div>
                    <div class="stat-value"><?= number_format($circLogs) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-book-reader"></i>
                        <span>Checkouts, Returns & Issues</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar danger">
                            <i class="bx bx-lock-alt"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-danger">Security</span>
                    </div>
                    <div class="stat-label">Security Alerts & Fails</div>
                    <div class="stat-value"><?= number_format($secLogs) ?></div>
                    <div class="stat-growth negative">
                        <i class="bx bx-error-alt"></i>
                        <span>Failed logins & 4-attempt locks</span>
                    </div>
                </div>
            </div>

            <!-- Filter and Actions Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                <!-- Category Pills -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="logs.php?cat=all" class="filter-pill <?= $category === 'all' ? 'active' : '' ?>">
                        <i class="bx bx-grid-alt"></i> All Logs
                    </a>
                    <a href="logs.php?cat=auth" class="filter-pill <?= $category === 'auth' ? 'active' : '' ?>">
                        <i class="bx bx-user-check"></i> Auth & Logins
                    </a>
                    <a href="logs.php?cat=circulation" class="filter-pill <?= $category === 'circulation' ? 'active' : '' ?>">
                        <i class="bx bx-transfer"></i> Circulation
                    </a>
                    <a href="logs.php?cat=security" class="filter-pill <?= $category === 'security' ? 'active' : '' ?>">
                        <i class="bx bx-shield-x"></i> Security & Lockouts
                    </a>
                    <a href="logs.php?cat=admin" class="filter-pill <?= $category === 'admin' ? 'active' : '' ?>">
                        <i class="bx bx-cog"></i> Admin & Setup
                    </a>
                </div>

                <!-- Search and Export Controls -->
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <div style="position: relative; width: 260px;">
                        <i class="bx bx-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.15rem;"></i>
                        <input type="text" id="tableSearchInput" class="form-control" style="padding-left: 2.4rem;" placeholder="Search activity logs...">
                    </div>
                    <button class="btn btn-label-secondary" onclick="exportLogsToCSV()" title="Export CSV">
                        <i class="bx bx-download"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- Activity Logs Table Card -->
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat" id="activityLogsTable">
                        <thead>
                            <tr>
                                <th style="width: 170px;">Timestamp</th>
                                <th style="width: 200px;">Action</th>
                                <th style="width: 220px;">User Account</th>
                                <th>Activity Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                    <i class="bx bx-history" style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    No activity logs found for the selected category.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="font-size: 0.8125rem; font-family: monospace; color: var(--text-muted); white-space: nowrap;">
                                        <i class="bx bx-time-five" style="margin-right: 0.25rem;"></i>
                                        <?= htmlspecialchars($log['created_at']) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-pill <?= getActionBadge($log['action']) ?>" style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8125rem;">
                                            <i class="bx <?= getActionIcon($log['action']) ?>"></i>
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['username'])): ?>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div class="avatar-sneat" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                                    <?= strtoupper(substr($log['full_name'] ?? $log['username'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; font-size: 0.8125rem; color: var(--text-heading);">
                                                        <?= htmlspecialchars($log['full_name'] ?? $log['username']) ?>
                                                    </div>
                                                    <small style="color: var(--text-muted); font-size: 0.6875rem;">
                                                        @<?= htmlspecialchars($log['username']) ?> &bull; <?= htmlspecialchars($log['role'] ?? '') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="display: flex; align-items: center; gap: 0.4rem; color: var(--text-muted); font-size: 0.8125rem;">
                                                <i class="bx bx-terminal" style="font-size: 1.1rem;"></i>
                                                <em>System / Guest</em>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.875rem; color: var(--text-main); line-height: 1.45;">
                                        <?= htmlspecialchars($log['details']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<script>
// Real-time table search filter
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('tableSearchInput');
    const table = document.getElementById('activityLogsTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});

function exportLogsToCSV() {
    const table = document.getElementById('activityLogsTable');
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        let cols = [];
        row.querySelectorAll('th, td').forEach(c => {
            let text = c.innerText.replace(/"/g, '""').trim();
            cols.push('"' + text + '"');
        });
        csv.push(cols.join(','));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'libra_activity_logs_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
