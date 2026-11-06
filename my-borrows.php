<?php
/**
 * LIBRA Borrower Portal: My Borrowings and History
 */

require_once __DIR__ . '/includes/auth_check.php';
checkAuth();

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "My Borrowing History";
$today = date('Y-m-d');

$borrowerId = $user['borrower_id'] ?? null;

// Fetch borrower profile info
$borrowerInfo = null;
if ($borrowerId) {
    $bStmt = $pdo->prepare("SELECT * FROM borrowers WHERE id = ?");
    $bStmt->execute([$borrowerId]);
    $borrowerInfo = $bStmt->fetch();
}

// 1. Active Loans
$activeLoans = [];
if ($borrowerId) {
    $stmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, b.isbn, b.shelf_location,
               (JULIANDAY(t.due_date) - JULIANDAY('$today')) as days_remaining
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.borrower_id = ? AND t.status IN ('Borrowed', 'Overdue')
        ORDER BY t.due_date ASC
    ");
    $stmt->execute([$borrowerId]);
    $activeLoans = $stmt->fetchAll();
}

// 2. Pending Requests
$pendingRequests = [];
if ($borrowerId) {
    $pStmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, b.isbn
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.borrower_id = ? AND t.status = 'Requested'
        ORDER BY t.created_at DESC
    ");
    $pStmt->execute([$borrowerId]);
    $pendingRequests = $pStmt->fetchAll();
}

// 3. Returned History
$returnedHistory = [];
if ($borrowerId) {
    $rStmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, b.isbn
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.borrower_id = ? AND t.status = 'Returned'
        ORDER BY t.return_date DESC
    ");
    $rStmt->execute([$borrowerId]);
    $returnedHistory = $rStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowings - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Profile Overview Card -->
            <?php if ($borrowerInfo): ?>
            <div class="card card-welcome-sneat" style="margin-bottom: 1.5rem;">
                <div class="card-body" style="padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <span class="badge badge-pill badge-bg-primary" style="margin-bottom: 0.5rem;"><?= htmlspecialchars($borrowerInfo['type']) ?> Account</span>
                        <h4 style="font-weight: 700; color: var(--text-heading); margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($borrowerInfo['full_name']) ?>
                        </h4>
                        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0;">
                            Borrower ID: <strong style="font-family: monospace; color: var(--text-heading);"><?= htmlspecialchars($borrowerInfo['borrower_code']) ?></strong> &bull; <?= htmlspecialchars($borrowerInfo['department']) ?>
                        </p>
                    </div>
                    <a href="books.php" class="btn btn-primary">
                        <i class="bx bx-book-open"></i>
                        <span>Browse Catalog & Borrow</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- SECTION 1: ACTIVE BORROWINGS -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bx bx-book-reader" style="color: var(--primary);"></i>
                        Current Active Borrowings (<?= count($activeLoans) ?>)
                    </h5>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($activeLoans)): ?>
                        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                            <i class="bx bx-book" style="font-size: 2.5rem; color: var(--primary); display: block; margin-bottom: 0.5rem;"></i>
                            <p style="margin-bottom: 1rem;">You currently do not have any borrowed books in your possession.</p>
                            <a href="books.php" class="btn btn-primary btn-sm">Explore Books to Borrow</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-sneat">
                                <thead>
                                    <tr>
                                        <th>Transaction Code</th>
                                        <th>Book Title</th>
                                        <th>Borrow Date</th>
                                        <th>Due Date</th>
                                        <th>Shelf Location</th>
                                        <th>Status & Countdown</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeLoans as $al): 
                                        $isOverdue = $al['status'] === 'Overdue' || $al['days_remaining'] < 0;
                                    ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                            <?= htmlspecialchars($al['transaction_code']) ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($al['title']) ?></div>
                                            <small style="color: var(--text-muted);">By <?= htmlspecialchars($al['author']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($al['borrow_date']) ?></td>
                                        <td class="<?= $isOverdue ? 'text-danger' : '' ?>" style="font-weight: 700;">
                                            <?= htmlspecialchars($al['due_date']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($al['shelf_location']) ?></td>
                                        <td>
                                            <?php if ($isOverdue): ?>
                                                <span class="badge badge-pill badge-bg-danger" style="font-weight: 700;">
                                                    <i class="bx bx-error-circle"></i> Overdue (<?= abs((int)$al['days_remaining']) ?> days)
                                                </span>
                                            <?php elseif ($al['days_remaining'] <= 2): ?>
                                                <span class="badge badge-pill badge-bg-warning" style="font-weight: 700;">
                                                    <i class="bx bx-time"></i> Due in <?= (int)$al['days_remaining'] ?> day(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-pill badge-bg-success">
                                                    <i class="bx bx-check-circle"></i> Due in <?= (int)$al['days_remaining'] ?> days
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECTION 2: PENDING REQUESTS -->
            <?php if (!empty($pendingRequests)): ?>
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bx bx-time" style="color: var(--warning);"></i>
                        Pending Requests Awaiting Librarian Approval
                    </h5>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table-sneat">
                            <thead>
                                <tr>
                                    <th>Request Code</th>
                                    <th>Book Title</th>
                                    <th>Request Submitted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $pr): ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                        <?= htmlspecialchars($pr['transaction_code']) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($pr['title']) ?></div>
                                        <small style="color: var(--text-muted);">By <?= htmlspecialchars($pr['author']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($pr['created_at']) ?></td>
                                    <td>
                                        <span class="badge badge-pill badge-bg-warning">Awaiting Approval</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- SECTION 3: RETURNED RECORDS HISTORY -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bx bx-check-double" style="color: var(--success);"></i>
                        Completed Return History
                    </h5>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($returnedHistory)): ?>
                        <div style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                            No past return records found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-sneat">
                                <thead>
                                    <tr>
                                        <th>Transaction</th>
                                        <th>Book Title</th>
                                        <th>Borrowed Date</th>
                                        <th>Returned Date</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returnedHistory as $rh): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-size: 0.8125rem;">
                                            <?= htmlspecialchars($rh['transaction_code']) ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($rh['title']) ?></div>
                                            <small style="color: var(--text-muted);">By <?= htmlspecialchars($rh['author']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($rh['borrow_date']) ?></td>
                                        <td style="color: var(--success); font-weight: 600;"><?= htmlspecialchars($rh['return_date']) ?></td>
                                        <td><span class="badge badge-pill badge-bg-info"><?= htmlspecialchars($rh['return_condition'] ?? 'Good') ?></span></td>
                                        <td><span class="badge badge-pill badge-bg-success">Cleared</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
