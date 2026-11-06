<?php
/**
 * LIBRA Dashboard - Library Book Borrowing System
 */

require_once __DIR__ . '/includes/auth_check.php';
checkAuth();

$pdo = Database::getConnection();
$user = currentUser();
$role = $user['role'];
$pageTitle = "Dashboard";

// Update any overdue transactions automatically on dashboard load
$today = date('Y-m-d');
$pdo->exec("UPDATE transactions SET status = 'Overdue' WHERE status = 'Borrowed' AND due_date < '$today'");

// 1. Core KPIs
$totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalCopies = (int)$pdo->query("SELECT SUM(total_copies) FROM books")->fetchColumn();
$availableCopies = (int)$pdo->query("SELECT SUM(available_copies) FROM books")->fetchColumn();
$borrowedBooks = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status IN ('Borrowed', 'Overdue')")->fetchColumn();
$overdueCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'Overdue'")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'Requested'")->fetchColumn();
$totalBorrowers = (int)$pdo->query("SELECT COUNT(*) FROM borrowers WHERE status = 'Active'")->fetchColumn();

// 2. Fetch Pending Requests
$pendingStmt = $pdo->prepare("
    SELECT t.*, b.title, b.isbn, b.available_copies, br.full_name, br.borrower_code, br.department
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    WHERE t.status = 'Requested'
    ORDER BY t.created_at ASC
    LIMIT 5
");
$pendingStmt->execute();
$pendingList = $pendingStmt->fetchAll();

// 3. Fetch Overdue Loans
$overdueStmt = $pdo->prepare("
    SELECT t.*, b.title, br.full_name, br.email, br.phone,
           (JULIANDAY('$today') - JULIANDAY(t.due_date)) as days_overdue
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    WHERE t.status = 'Overdue'
    ORDER BY days_overdue DESC
    LIMIT 5
");
$overdueStmt->execute();
$overdueList = $overdueStmt->fetchAll();


// 5. Borrower specific data
$myActiveBorrows = [];
$myPendingBorrows = [];
if ($role === 'Borrower' && !empty($user['borrower_id'])) {
    $bId = (int)$user['borrower_id'];
    $myStmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, b.isbn,
               (JULIANDAY(t.due_date) - JULIANDAY('$today')) as days_remaining
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.borrower_id = ? AND t.status IN ('Borrowed', 'Overdue')
        ORDER BY t.due_date ASC
    ");
    $myStmt->execute([$bId]);
    $myActiveBorrows = $myStmt->fetchAll();

    $myReqStmt = $pdo->prepare("
        SELECT t.*, b.title, b.author
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        WHERE t.borrower_id = ? AND t.status = 'Requested'
        ORDER BY t.created_at DESC
    ");
    $myReqStmt->execute([$bId]);
    $myPendingBorrows = $myReqStmt->fetchAll();
}

// Fetch all books & borrowers for modal dropdowns
$allBooks = $pdo->query("SELECT id, title, available_copies, isbn FROM books WHERE available_copies > 0 ORDER BY title ASC")->fetchAll();
$allBorrowers = $pdo->query("SELECT id, full_name, borrower_code, department FROM borrowers WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Sneat Iconic Welcome Banner Card -->
            <div class="card card-welcome-sneat">
                <div class="card-body" style="padding: 1.75rem 2rem; max-width: 65%;">
                    <h5 style="color: var(--primary); font-weight: 700; margin-bottom: 0.5rem; font-size: 1.25rem;">
                        Welcome back, <?= htmlspecialchars($user['full_name']) ?>! 🎉
                    </h5>
                    <p style="margin-bottom: 1.25rem; color: var(--text-main); font-size: 0.9375rem; line-height: 1.5;">
                        You are signed in as <strong><?= htmlspecialchars($role) ?></strong>. The library database currently manages <strong><?= number_format($totalCopies) ?> book copies</strong> with <strong><?= number_format($availableCopies) ?> copies</strong> ready for immediate checkout.
                    </p>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <?php if ($role === 'Administrator' || $role === 'Librarian'): ?>
                            <button class="btn btn-primary" data-modal-target="modalIssueBook">
                                <i class="bx bx-plus-circle"></i>
                                <span>Issue Book</span>
                            </button>
                            <a href="transactions.php" class="btn btn-label-secondary">
                                <i class="bx bx-check-shield"></i>
                                <span>Process Return</span>
                            </a>
                        <?php else: ?>
                            <a href="books.php" class="btn btn-primary">
                                <i class="bx bx-search-alt"></i>
                                <span>Browse Catalog</span>
                            </a>
                            <a href="my-borrows.php" class="btn btn-label-secondary">
                                <i class="bx bx-book-bookmark"></i>
                                <span>My Loans</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <img src="assets/sneat/cards/illustration-john-light.png" class="card-welcome-illustration" alt="LIBRA Welcome">
            </div>

            <!-- Sneat 4 Stat Cards Grid (Interactive & Functional) -->
            <div class="stat-cards-grid">
                <!-- Total Books -->
                <a href="books.php" class="stat-card stat-card-link" title="Click to view all books in the catalog">
                    <div class="stat-card-header">
                        <div class="stat-avatar primary">
                            <i class="bx bx-book"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-primary">Catalog</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Total Books</div>
                    <div class="stat-value"><?= number_format($totalBooks) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-layer"></i>
                        <span><?= number_format($totalCopies) ?> total copies</span>
                    </div>
                </a>

                <!-- Available Books -->
                <a href="books.php?filter=available" class="stat-card stat-card-link" title="Click to view available books in stock">
                    <div class="stat-card-header">
                        <div class="stat-avatar success">
                            <i class="bx bx-check-double"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-success">In Stock</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Available Books</div>
                    <div class="stat-value text-success"><?= number_format($availableCopies) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-up-arrow-alt"></i>
                        <span>Ready to borrow</span>
                    </div>
                </a>

                <!-- Borrowed Books -->
                <a href="<?= ($role === 'Borrower') ? 'my-borrows.php' : 'transactions.php?tab=active' ?>" class="stat-card stat-card-link" title="Click to view active book loans">
                    <div class="stat-card-header">
                        <div class="stat-avatar info">
                            <i class="bx bx-time-five"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-info">Circulation</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Borrowed Books</div>
                    <div class="stat-value text-info"><?= number_format($borrowedBooks) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-user-check"></i>
                        <span>With borrowers</span>
                    </div>
                </a>

                <!-- Overdue Transactions -->
                <a href="<?= ($role === 'Borrower') ? 'my-borrows.php' : 'overdue.php' ?>" class="stat-card stat-card-link" title="Click to view overdue transactions & notices">
                    <div class="stat-card-header">
                        <div class="stat-avatar danger">
                            <i class="bx bx-error-alt"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-danger">Alerts</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Overdue Transactions</div>
                    <div class="stat-value <?= $overdueCount > 0 ? 'text-danger' : '' ?>">
                        <?= number_format($overdueCount) ?>
                    </div>
                    <div class="stat-growth <?= $overdueCount > 0 ? 'negative' : 'positive' ?>">
                        <i class="bx <?= $overdueCount > 0 ? 'bx-bell' : 'bx-badge-check' ?>"></i>
                        <span><?= $overdueCount > 0 ? 'Action required' : 'All loans on time' ?></span>
                    </div>
                </a>
            </div>

            <?php if ($role === 'Borrower'): ?>
                <!-- ==================== BORROWER SNEAT VIEW ==================== -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
                    <div>
                        <!-- My Active Borrowed Books -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-book-reader" style="color: var(--primary);"></i>
                                    My Active Borrowed Books
                                </h5>
                                <a href="my-borrows.php" class="btn btn-sm btn-label-primary">View Full History</a>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <?php if (empty($myActiveBorrows)): ?>
                                    <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                        <i class="bx bx-smile" style="font-size: 2.5rem; color: var(--primary); display: block; margin-bottom: 0.5rem;"></i>
                                        <p>You have no active borrowed books right now.</p>
                                        <a href="books.php" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Explore Catalog & Borrow</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table-sneat">
                                            <thead>
                                                <tr>
                                                    <th>Book Title</th>
                                                    <th>Borrowed Date</th>
                                                    <th>Due Date</th>
                                                    <th>Status / Countdown</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($myActiveBorrows as $b): ?>
                                                <tr>
                                                    <td>
                                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($b['title']) ?></div>
                                                        <small style="color: var(--text-muted);">By <?= htmlspecialchars($b['author']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($b['borrow_date']) ?></td>
                                                    <td style="font-weight: 600;"><?= htmlspecialchars($b['due_date']) ?></td>
                                                    <td>
                                                        <?php if ($b['status'] === 'Overdue' || $b['days_remaining'] < 0): ?>
                                                            <span class="badge badge-pill badge-bg-danger">
                                                                Overdue (<?= abs((int)$b['days_remaining']) ?> days)
                                                            </span>
                                                        <?php elseif ($b['days_remaining'] <= 2): ?>
                                                            <span class="badge badge-pill badge-bg-warning">
                                                                Due in <?= (int)$b['days_remaining'] ?> day(s)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-pill badge-bg-success">
                                                                Due in <?= (int)$b['days_remaining'] ?> days
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

                        <!-- My Pending Requests -->
                        <?php if (!empty($myPendingBorrows)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-time" style="color: var(--warning);"></i>
                                    My Pending Borrow Requests
                                </h5>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <table class="table-sneat">
                                    <thead>
                                        <tr>
                                            <th>Book Title</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($myPendingBorrows as $req): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($req['title']) ?></div>
                                                <small style="color: var(--text-muted);"><?= htmlspecialchars($req['author']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars(substr($req['created_at'], 0, 10)) ?></td>
                                            <td>
                                                <span class="badge badge-pill badge-bg-warning">Awaiting Approval</span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <!-- Borrowing Guidelines Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="bx bx-info-circle" style="color: var(--info);"></i>
                                    Borrowing Rules
                                </h5>
                            </div>
                            <div class="card-body" style="font-size: 0.875rem; line-height: 1.6; color: var(--text-main);">
                                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                                    <i class="bx bx-calendar-event" style="font-size: 1.25rem; color: var(--primary);"></i>
                                    <div>
                                        <strong>14-Day Loan:</strong> Books may be kept for up to 14 days before due date.
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                                    <i class="bx bx-shield-quarter" style="font-size: 1.25rem; color: var(--success);"></i>
                                    <div>
                                        <strong>Return Desk:</strong> Bring items to the circulation desk to clear transactions.
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem;">
                                    <i class="bx bx-alarm-exclamation" style="font-size: 1.25rem; color: var(--danger);"></i>
                                    <div>
                                        <strong>Overdue Fines:</strong> Late returns accrue a $2.00/day penalty.
                                    </div>
                                </div>
                                <a href="books.php" class="btn btn-primary" style="width: 100%;">
                                    <i class="bx bx-search"></i>
                                    <span>Browse Catalog</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ==================== ADMIN & LIBRARIAN SNEAT VIEW ==================== -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Pending Requests Queue -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bx bx-git-pull-request" style="color: var(--warning);"></i>
                                Pending Borrow Requests
                                <?php if ($pendingRequests > 0): ?>
                                    <span class="badge badge-pill badge-bg-warning"><?= $pendingRequests ?> New</span>
                                <?php endif; ?>
                            </h5>
                            <a href="transactions.php?tab=requests" class="btn btn-sm btn-label-primary">View All</a>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php if (empty($pendingList)): ?>
                                <div style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                    <i class="bx bx-check-circle" style="font-size: 2rem; color: var(--success); display: block; margin-bottom: 0.25rem;"></i>
                                    No pending borrow requests right now.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table-sneat">
                                        <thead>
                                            <tr>
                                                <th>Borrower</th>
                                                <th>Requested Book</th>
                                                <th>Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingList as $p): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($p['full_name']) ?></div>
                                                    <small style="color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($p['borrower_code']) ?></small>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($p['title']) ?></div>
                                                    <small style="color: var(--text-muted);">ISBN: <?= htmlspecialchars($p['isbn']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($p['available_copies'] > 0): ?>
                                                        <span class="badge badge-pill badge-bg-success"><?= $p['available_copies'] ?> Left</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-pill badge-bg-danger">0 Left</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="approveRequest(<?= $p['id'] ?>)">
                                                        <i class="bx bx-check"></i>
                                                        <span>Approve</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Overdue Monitor Alert Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bx bx-error" style="color: var(--danger);"></i>
                                Overdue Books Monitor
                            </h5>
                            <a href="overdue.php" class="btn btn-sm btn-label-danger">Full Overdue List (<?= $overdueCount ?>)</a>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php if (empty($overdueList)): ?>
                                <div style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                    <i class="bx bx-badge-check" style="font-size: 2rem; color: var(--success); display: block; margin-bottom: 0.25rem;"></i>
                                    All borrowings are returned or within due date.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table-sneat">
                                        <thead>
                                            <tr>
                                                <th>Borrower</th>
                                                <th>Book Title</th>
                                                <th>Due Date</th>
                                                <th>Days Overdue</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overdueList as $od): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($od['full_name']) ?></div>
                                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($od['email']) ?></small>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($od['title']) ?></div>
                                                </td>
                                                <td style="color: var(--danger); font-weight: 600;"><?= htmlspecialchars($od['due_date']) ?></td>
                                                <td>
                                                    <span class="badge badge-pill badge-bg-danger"><?= (int)$od['days_overdue'] ?> Days Late</span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-label-primary" onclick="sendOverdueNotice(<?= $od['id'] ?>)">
                                                        <i class="bx bx-bell"></i>
                                                        <span>Notify</span>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

<!-- ==================== MODAL: ISSUE BOOK ==================== -->
<div id="modalIssueBook" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Issue Book to Borrower</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="issueBookForm" onsubmit="submitIssueBook(event)">
            <input type="hidden" name="action" value="issue">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="issue_borrower_id">Select Borrower (Student or Employee)</label>
                    <select id="issue_borrower_id" name="borrower_id" class="form-control" required>
                        <option value="">-- Choose Registered Borrower --</option>
                        <?php foreach ($allBorrowers as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?> (<?= htmlspecialchars($b['borrower_code']) ?> - <?= htmlspecialchars($b['department']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="issue_book_id">Select Available Book</label>
                    <select id="issue_book_id" name="book_id" class="form-control" required>
                        <option value="">-- Choose Available Book --</option>
                        <?php foreach ($allBooks as $bk): ?>
                            <option value="<?= $bk['id'] ?>"><?= htmlspecialchars($bk['title']) ?> (<?= $bk['available_copies'] ?> copies available)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="issue_borrow_date">Borrow Date</label>
                        <input type="date" id="issue_borrow_date" name="borrow_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="issue_due_date">Due Date (Default: 14 Days)</label>
                        <input type="date" id="issue_due_date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="issue_notes">Transaction Notes</label>
                    <textarea id="issue_notes" name="notes" class="form-control" rows="2" placeholder="Course study, semester research, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm & Issue Book</button>
            </div>
        </form>
    </div>
</div>

<script>
async function approveRequest(txnId) {
    const confirmed = await showConfirmModal('Approve this borrow request and check out book?', {
        title: 'Approve Borrow Request',
        confirmText: 'Approve & Issue',
        confirmBtnClass: 'btn-success',
        type: 'info'
    });
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'approve_request');
    formData.append('txn_id', txnId);

    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error approving request', 'Approval Error', 'error');
    }
}

async function sendOverdueNotice(txnId) {
    const formData = new FormData();
    formData.append('action', 'send_overdue_notice');
    formData.append('txn_id', txnId);

    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        showToast('Overdue reminder notice dispatched!', 'success');
    }
}

async function submitIssueBook(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);

    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error issuing book', 'Checkout Error', 'error');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
