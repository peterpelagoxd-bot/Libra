<?php
/**
 * LIBRA Transactions Management: Book Borrowing & Return Processing
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator', 'Librarian']);

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "Borrow & Return Desk";
$today = date('Y-m-d');

// Automatically update overdue status
$pdo->exec("UPDATE transactions SET status = 'Overdue' WHERE status = 'Borrowed' AND due_date < '$today'");

$activeTab = $_GET['tab'] ?? 'active';

// 1. Fetch Active Borrowings
$activeStmt = $pdo->prepare("
    SELECT t.*, b.title, b.isbn, br.full_name, br.borrower_code, br.department, br.email,
           (JULIANDAY(t.due_date) - JULIANDAY('$today')) as days_remaining,
           u.full_name as librarian_name
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    LEFT JOIN users u ON t.processed_by = u.id
    WHERE t.status IN ('Borrowed', 'Overdue')
    ORDER BY t.due_date ASC
");
$activeStmt->execute();
$activeBorrows = $activeStmt->fetchAll();

// 2. Fetch Pending Requests
$pendingStmt = $pdo->prepare("
    SELECT t.*, b.title, b.isbn, b.available_copies, br.full_name, br.borrower_code, br.department
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    WHERE t.status = 'Requested'
    ORDER BY t.created_at ASC
");
$pendingStmt->execute();
$pendingRequests = $pendingStmt->fetchAll();

// 3. Fetch Returned Transactions
$returnedStmt = $pdo->prepare("
    SELECT t.*, b.title, b.isbn, br.full_name, br.borrower_code, u.full_name as librarian_name
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    LEFT JOIN users u ON t.processed_by = u.id
    WHERE t.status = 'Returned'
    ORDER BY t.return_date DESC
    LIMIT 50
");
$returnedStmt->execute();
$returnedList = $returnedStmt->fetchAll();

// Fetch books and borrowers for modal
$allBooks = $pdo->query("SELECT id, title, available_copies, isbn FROM books WHERE available_copies > 0 ORDER BY title ASC")->fetchAll();
$allBorrowers = $pdo->query("SELECT id, full_name, borrower_code, department FROM borrowers WHERE status = 'Active' ORDER BY full_name ASC")->fetchAll();
$preselectedBookId = (int)($_GET['book_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow & Return Processing - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .nav-tabs-sneat {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        .nav-tab-link {
            padding: 0.75rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        .nav-tab-link:hover {
            color: var(--primary);
        }
        .nav-tab-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Top Controls -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="position: relative; max-width: 360px; width: 100%;">
                    <i class="bx bx-search" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.25rem;"></i>
                    <input type="text" id="tableSearchInput" class="form-control" style="padding-left: 2.5rem;" placeholder="Search transactions, borrower, or book...">
                </div>

                <button class="btn btn-primary" data-modal-target="modalIssueBook">
                    <i class="bx bx-plus"></i>
                    <span>Issue Book Checkout</span>
                </button>
            </div>

            <!-- Sneat Tabs Navigation -->
            <div class="nav-tabs-sneat">
                <a href="transactions.php?tab=active" class="nav-tab-link <?= $activeTab === 'active' ? 'active' : '' ?>">
                    <i class="bx bx-time-five"></i>
                    <span>Active Borrowings (<?= count($activeBorrows) ?>)</span>
                </a>
                <a href="transactions.php?tab=requests" class="nav-tab-link <?= $activeTab === 'requests' ? 'active' : '' ?>">
                    <i class="bx bx-git-pull-request"></i>
                    <span>Pending Requests</span>
                    <?php if (count($pendingRequests) > 0): ?>
                        <span class="badge badge-pill badge-bg-warning"><?= count($pendingRequests) ?></span>
                    <?php endif; ?>
                </a>
                <a href="transactions.php?tab=returned" class="nav-tab-link <?= $activeTab === 'returned' ? 'active' : '' ?>">
                    <i class="bx bx-check-double"></i>
                    <span>Returned History (<?= count($returnedList) ?>)</span>
                </a>
            </div>

            <!-- TAB 1: ACTIVE BORROWINGS -->
            <?php if ($activeTab === 'active'): ?>
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Transaction Code</th>
                                <th>Borrower</th>
                                <th>Book Details</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Status / Timeline</th>
                                <th>Processed By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeBorrows)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                        No active book loans at this moment.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activeBorrows as $tx): 
                                    $isOverdue = $tx['status'] === 'Overdue' || $tx['days_remaining'] < 0;
                                ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                        <?= htmlspecialchars($tx['transaction_code']) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($tx['full_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($tx['borrower_code']) ?> &bull; <?= htmlspecialchars($tx['department']) ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($tx['title']) ?></div>
                                        <small style="color: var(--text-muted);">ISBN: <?= htmlspecialchars($tx['isbn']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($tx['borrow_date']) ?></td>
                                    <td class="<?= $isOverdue ? 'text-danger' : '' ?>" style="font-weight: 600;">
                                        <?= htmlspecialchars($tx['due_date']) ?>
                                    </td>
                                    <td>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge badge-pill badge-bg-danger">
                                                Overdue (<?= abs((int)$tx['days_remaining']) ?> days)
                                            </span>
                                        <?php elseif ($tx['days_remaining'] <= 3): ?>
                                            <span class="badge badge-pill badge-bg-warning">
                                                Due in <?= (int)$tx['days_remaining'] ?> day(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-pill badge-bg-success">
                                                Due in <?= (int)$tx['days_remaining'] ?> days
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8125rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($tx['librarian_name'] ?? 'Desk Staff') ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openReturnModal(<?= $tx['id'] ?>, '<?= addslashes(htmlspecialchars($tx['title'])) ?>', '<?= addslashes(htmlspecialchars($tx['full_name'])) ?>', '<?= $tx['due_date'] ?>', <?= $isOverdue ? abs((int)$tx['days_remaining']) : 0 ?>)">
                                            <i class="bx bx-check-shield"></i>
                                            <span>Process Return</span>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 2: PENDING REQUESTS -->
            <?php if ($activeTab === 'requests'): ?>
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Request Code</th>
                                <th>Borrower</th>
                                <th>Requested Book</th>
                                <th>Stock Availability</th>
                                <th>Date Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingRequests)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                        No pending borrow requests right now.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingRequests as $p): ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                        <?= htmlspecialchars($p['transaction_code']) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($p['full_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($p['borrower_code']) ?> &bull; <?= htmlspecialchars($p['department']) ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($p['title']) ?></div>
                                        <small style="color: var(--text-muted);">ISBN: <?= htmlspecialchars($p['isbn']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($p['available_copies'] > 0): ?>
                                            <span class="badge badge-pill badge-bg-success"><?= $p['available_copies'] ?> Copies Available</span>
                                        <?php else: ?>
                                            <span class="badge badge-pill badge-bg-danger">0 Copies Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['created_at']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn btn-success btn-sm" onclick="approveRequest(<?= $p['id'] ?>)">
                                                <i class="bx bx-check"></i>
                                                <span>Approve & Issue</span>
                                            </button>
                                            <button class="btn btn-label-danger btn-sm" onclick="declineRequest(<?= $p['id'] ?>)">
                                                <i class="bx bx-x"></i>
                                                <span>Decline</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 3: RETURNED HISTORY -->
            <?php if ($activeTab === 'returned'): ?>
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Borrower</th>
                                <th>Book Title</th>
                                <th>Borrow Date</th>
                                <th>Return Date</th>
                                <th>Condition</th>
                                <th>Fine Paid</th>
                                <th>Librarian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($returnedList)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                        No returned transactions yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($returnedList as $r): ?>
                                <tr>
                                    <td style="font-family: monospace; font-size: 0.8125rem;">
                                        <?= htmlspecialchars($r['transaction_code']) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($r['full_name']) ?></div>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($r['borrower_code']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td><?= htmlspecialchars($r['borrow_date']) ?></td>
                                    <td style="font-weight: 600; color: var(--success);"><?= htmlspecialchars($r['return_date']) ?></td>
                                    <td><span class="badge badge-pill badge-bg-info"><?= htmlspecialchars($r['return_condition'] ?? 'Good') ?></span></td>
                                    <td>
                                        <?php if ((float)$r['fine_amount'] > 0): ?>
                                            <span style="color: var(--danger); font-weight: 600;">$<?= number_format($r['fine_amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">$0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8125rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($r['librarian_name'] ?? 'Desk Staff') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                    <label class="form-label" for="issue_borrower_id">Select Borrower *</label>
                    <select id="issue_borrower_id" name="borrower_id" class="form-control" required>
                        <option value="">-- Choose Registered Borrower --</option>
                        <?php foreach ($allBorrowers as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['full_name']) ?> (<?= htmlspecialchars($b['borrower_code']) ?> - <?= htmlspecialchars($b['department']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="issue_book_id">Select Book *</label>
                    <select id="issue_book_id" name="book_id" class="form-control" required>
                        <option value="">-- Choose Available Book --</option>
                        <?php foreach ($allBooks as $bk): ?>
                            <option value="<?= $bk['id'] ?>" <?= $preselectedBookId === (int)$bk['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bk['title']) ?> (<?= $bk['available_copies'] ?> copies available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="issue_borrow_date">Borrow Date</label>
                        <input type="date" id="issue_borrow_date" name="borrow_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="issue_due_date">Due Date</label>
                        <input type="date" id="issue_due_date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="issue_notes">Transaction Notes</label>
                    <textarea id="issue_notes" name="notes" class="form-control" rows="2" placeholder="Course loan, student research, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Checkout</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: PROCESS RETURN ==================== -->
<div id="modalProcessReturn" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Process Book Return</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="returnBookForm" onsubmit="submitReturnBook(event)">
            <input type="hidden" name="action" value="return">
            <input type="hidden" id="return_txn_id" name="txn_id">
            <div class="modal-body">
                <div style="background: rgba(105, 108, 255, 0.08); padding: 1rem; border-radius: var(--radius-md); border-left: 4px solid var(--primary); margin-bottom: 1.25rem;">
                    <div id="return_book_title" style="font-weight: 700; font-size: 1rem; color: var(--text-heading);">Book Title</div>
                    <div id="return_borrower_name" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Borrower Name</div>
                    <div id="return_overdue_notice" style="font-size: 0.8125rem; margin-top: 0.4rem;"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="return_date">Return Date *</label>
                        <input type="date" id="return_date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="return_condition">Book Condition *</label>
                        <select id="return_condition" name="condition" class="form-control" required>
                            <option value="Good">Good Condition</option>
                            <option value="Excellent">Excellent / Mint</option>
                            <option value="Light Wear">Light Wear</option>
                            <option value="Damaged">Damaged (Needs repair)</option>
                            <option value="Lost">Lost Copy</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="return_fine_amount">Fine / Penalty Amount ($)</label>
                    <input type="number" id="return_fine_amount" name="fine_amount" class="form-control" step="0.50" min="0" value="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label" for="return_notes">Return Remarks</label>
                    <textarea id="return_notes" name="notes" class="form-control" rows="2" placeholder="Returned on time, no markings observed..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-success">Complete Return & Restock</button>
            </div>
        </form>
    </div>
</div>

<script>
async function submitIssueBook(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error issuing book', 'Checkout Error', 'error');
    }
}

function openReturnModal(txnId, title, borrowerName, dueDate, daysOverdue) {
    document.getElementById('return_txn_id').value = txnId;
    document.getElementById('return_book_title').textContent = title;
    document.getElementById('return_borrower_name').textContent = "Borrower: " + borrowerName + " (Due: " + dueDate + ")";
    
    const fineInput = document.getElementById('return_fine_amount');
    const notice = document.getElementById('return_overdue_notice');

    if (daysOverdue > 0) {
        const estFine = daysOverdue * 2.0;
        fineInput.value = estFine.toFixed(2);
        notice.innerHTML = `<span style="color: var(--danger); font-weight: 600;"><i class="bx bx-error-circle"></i> Book is ${daysOverdue} days overdue! Suggested late fee: $${estFine.toFixed(2)}</span>`;
    } else {
        fineInput.value = "0.00";
        notice.innerHTML = `<span style="color: var(--success); font-weight: 600;"><i class="bx bx-check-circle"></i> Book returned within due date. No late penalty.</span>`;
    }

    openModal('modalProcessReturn');
}

async function submitReturnBook(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error processing return', 'Return Error', 'error');
    }
}

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

async function declineRequest(txnId) {
    const confirmed = await showConfirmModal('Are you sure you want to decline this borrow request?', {
        title: 'Decline Borrow Request',
        confirmText: 'Decline Request',
        confirmBtnClass: 'btn-danger',
        type: 'warning'
    });
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'decline_request');
    formData.append('txn_id', txnId);
    formData.append('reason', 'Out of stock or limit reached');

    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error declining request', 'Decline Error', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'issue') {
        openModal('modalIssueBook');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
