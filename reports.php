<?php
/**
 * LIBRA Reports and Analytics Dashboard
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator', 'Librarian']);

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "Reports & Analytics";
$today = date('Y-m-d');

// 1. Overall Transaction Breakdown
$totalTxns = (int)$pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$activeTxns = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('Borrowed', 'Overdue')")->fetchColumn();
$returnedTxns = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'Returned'")->fetchColumn();
$overdueTxns = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'Overdue'")->fetchColumn();
$totalFinesCollected = (float)$pdo->query("SELECT SUM(fine_amount) FROM transactions WHERE status = 'Returned'")->fetchColumn();

// 2. Most Borrowed Books
$popularBooks = $pdo->query("
    SELECT b.title, b.author, c.name as category, COUNT(t.id) as borrow_count
    FROM books b
    JOIN transactions t ON b.id = t.book_id
    JOIN categories c ON b.category_id = c.id
    WHERE t.status != 'Cancelled'
    GROUP BY b.id
    ORDER BY borrow_count DESC
    LIMIT 6
")->fetchAll();

// 3. Category Borrowing Distribution
$categoryStats = $pdo->query("
    SELECT c.name, COUNT(t.id) as txn_count
    FROM categories c
    JOIN books b ON c.id = b.category_id
    LEFT JOIN transactions t ON b.id = t.book_id AND t.status != 'Cancelled'
    GROUP BY c.id
    ORDER BY txn_count DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print {
            .layout-menu, .layout-navbar-container, .btn, .no-print {
                display: none !important;
            }
            .layout-page {
                margin-left: 0 !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Header Action Controls -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <h4 style="font-weight: 700; color: var(--text-heading); margin-bottom: 0.25rem;">Circulation & Library Analytics</h4>
                    <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
                        Real-time analytics on circulation volume, book turnover, and overdue metrics
                    </p>
                </div>

                <div style="display: flex; gap: 0.75rem;" class="no-print">
                    <button class="btn btn-label-secondary" onclick="window.print()">
                        <i class="bx bx-printer"></i>
                        <span>Print</span>
                    </button>
                    <a href="api/reports.php?type=transactions" class="btn btn-primary">
                        <i class="bx bx-export"></i>
                        <span>Export Transactions CSV</span>
                    </a>
                    <a href="api/reports.php?type=books" class="btn btn-label-secondary">
                        <i class="bx bx-download"></i>
                        <span>Export Catalog CSV</span>
                    </a>
                </div>
            </div>

            <!-- Sneat Stat Cards -->
            <div class="stat-cards-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar primary">
                            <i class="bx bx-transfer-alt"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-primary">Total Loans</span>
                    </div>
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-value"><?= number_format($totalTxns) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-history"></i>
                        <span>All recorded borrowings</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar success">
                            <i class="bx bx-check-double"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-success">Cleared</span>
                    </div>
                    <div class="stat-label">Completed Returns</div>
                    <div class="stat-value" style="color: var(--success);"><?= number_format($returnedTxns) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-badge-check"></i>
                        <span>Books returned on time</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar danger">
                            <i class="bx bx-error-circle"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-danger">Rate</span>
                    </div>
                    <div class="stat-label">Overdue Rate</div>
                    <div class="stat-value" style="color: var(--danger);">
                        <?= $totalTxns > 0 ? round(($overdueTxns / max($activeTxns, 1)) * 100, 1) : 0 ?>%
                    </div>
                    <div class="stat-growth negative">
                        <i class="bx bx-bell"></i>
                        <span><?= $overdueTxns ?> active loans late</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar warning">
                            <i class="bx bx-dollar-circle"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-warning">Revenue</span>
                    </div>
                    <div class="stat-label">Late Fines Collected</div>
                    <div class="stat-value" style="color: var(--warning);">$<?= number_format($totalFinesCollected, 2) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-receipt"></i>
                        <span>From returned overdue loans</span>
                    </div>
                </div>
            </div>

            <!-- Detailed Breakdowns Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <!-- Most Popular Books -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bx bx-trophy" style="color: var(--warning);"></i>
                            Most Borrowed Titles
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="table-sneat">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Category</th>
                                    <th>Times Borrowed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popularBooks as $pb): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($pb['title']) ?></div>
                                        <small style="color: var(--text-muted);">By <?= htmlspecialchars($pb['author']) ?></small>
                                    </td>
                                    <td><span class="badge badge-pill badge-bg-primary"><?= htmlspecialchars($pb['category']) ?></span></td>
                                    <td style="font-weight: 700; color: var(--primary);">
                                        <?= $pb['borrow_count'] ?> loans
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Category Circulation Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bx bx-pie-chart-alt-2" style="color: var(--info);"></i>
                            Circulation by Subject
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="table-sneat">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Volume</th>
                                    <th>Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $cs): 
                                    $pct = $totalTxns > 0 ? round(($cs['txn_count'] / $totalTxns) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($cs['name']) ?></td>
                                    <td><?= $cs['txn_count'] ?> checkouts</td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="flex: 1; height: 6px; background: rgba(67, 89, 113, 0.1); border-radius: var(--radius-pill); overflow: hidden;">
                                                <div style="height: 100%; width: <?= $pct ?>%; background: var(--primary); border-radius: var(--radius-pill);"></div>
                                            </div>
                                            <span style="font-size: 0.8125rem; font-weight: 600; min-width: 40px; color: var(--text-heading);"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
