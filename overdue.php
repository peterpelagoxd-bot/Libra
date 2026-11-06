<?php
/**
 * LIBRA Overdue Monitoring Page
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator', 'Librarian']);

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "Overdue Monitoring";
$today = date('Y-m-d');

// Sync overdue status
$pdo->exec("UPDATE transactions SET status = 'Overdue' WHERE status = 'Borrowed' AND due_date < '$today'");

// Fetch overdue records with dynamic calculations
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.author, b.isbn, b.shelf_location,
           br.full_name, br.borrower_code, br.type, br.department, br.email, br.phone,
           CAST((JULIANDAY('$today') - JULIANDAY(t.due_date)) AS INT) as days_overdue
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN borrowers br ON t.borrower_id = br.id
    WHERE t.status = 'Overdue'
    ORDER BY days_overdue DESC
");
$stmt->execute();
$overdueRecords = $stmt->fetchAll();

// Statistics
$totalOverdue = count($overdueRecords);
$totalEstimatedFines = 0;
$maxDaysOverdue = 0;

foreach ($overdueRecords as $rec) {
    $totalEstimatedFines += ($rec['days_overdue'] * 2.00);
    if ($rec['days_overdue'] > $maxDaysOverdue) {
        $maxDaysOverdue = $rec['days_overdue'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overdue Monitoring - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Sneat Overdue Stat Cards -->
            <div class="stat-cards-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar danger">
                            <i class="bx bx-error-alt"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-danger">Late Items</span>
                    </div>
                    <div class="stat-label">Total Overdue Loans</div>
                    <div class="stat-value" style="color: var(--danger);"><?= number_format($totalOverdue) ?></div>
                    <div class="stat-growth negative">
                        <i class="bx bx-bell"></i>
                        <span>Active loans past due date</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar warning">
                            <i class="bx bx-time"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-warning">Max Delay</span>
                    </div>
                    <div class="stat-label">Longest Overdue</div>
                    <div class="stat-value" style="color: var(--warning);"><?= number_format($maxDaysOverdue) ?> Days</div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-calendar"></i>
                        <span>Longest unreturned borrowing</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-avatar primary">
                            <i class="bx bx-dollar-circle"></i>
                        </div>
                        <span class="badge badge-pill badge-bg-primary">$2.00 / day</span>
                    </div>
                    <div class="stat-label">Estimated Fines</div>
                    <div class="stat-value" style="color: var(--primary);">$<?= number_format($totalEstimatedFines, 2) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-receipt"></i>
                        <span>Pending collection at desk</span>
                    </div>
                </div>
            </div>

            <!-- Overdue Table Card -->
            <div class="card filterable-table" style="padding: 0;">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="bx bx-error" style="color: var(--danger);"></i>
                        Overdue Transactions Scanner
                    </h5>
                    <div style="position: relative; max-width: 320px; width: 100%;">
                        <i class="bx bx-search" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.25rem;"></i>
                        <input type="text" id="tableSearchInput" class="form-control" style="padding-left: 2.5rem;" placeholder="Search borrower or title...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Borrower Details</th>
                                <th>Overdue Book</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Accrued Fine</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdueRecords)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                        <i class="bx bx-badge-check" style="font-size: 2.5rem; color: var(--success); display: block; margin-bottom: 0.5rem;"></i>
                                        Fantastic! No books are currently overdue.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($overdueRecords as $od): 
                                    $fine = $od['days_overdue'] * 2.00;
                                ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                        <?= htmlspecialchars($od['transaction_code']) ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($od['full_name']) ?></div>
                                        <small style="color: var(--text-muted);">
                                            <?= htmlspecialchars($od['borrower_code']) ?> &bull; <?= htmlspecialchars($od['type']) ?> (<?= htmlspecialchars($od['department']) ?>)
                                        </small>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                            <i class="bx bx-envelope"></i> <?= htmlspecialchars($od['email']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($od['title']) ?></div>
                                        <small style="color: var(--text-muted);">ISBN: <?= htmlspecialchars($od['isbn']) ?> &bull; <?= htmlspecialchars($od['shelf_location']) ?></small>
                                    </td>
                                    <td class="text-danger" style="font-weight: 700;">
                                        <?= htmlspecialchars($od['due_date']) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-pill badge-bg-danger" style="font-weight: 700;">
                                            <?= $od['days_overdue'] ?> Days Late
                                        </span>
                                    </td>
                                    <td class="text-danger" style="font-weight: 700;">
                                        $<?= number_format($fine, 2) ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.4rem;">
                                            <button class="btn btn-primary btn-sm" onclick="openReturnModal(<?= $od['id'] ?>, '<?= addslashes(htmlspecialchars($od['title'])) ?>', '<?= addslashes(htmlspecialchars($od['full_name'])) ?>', '<?= $od['due_date'] ?>', <?= $od['days_overdue'] ?>)">
                                                <i class="bx bx-check-shield"></i>
                                                <span>Return</span>
                                            </button>
                                            <button class="btn btn-label-secondary btn-sm" onclick="sendOverdueNotice(<?= $od['id'] ?>)" title="Log notice & email borrower">
                                                <i class="bx bx-bell"></i>
                                                <span>Notify</span>
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

<!-- ==================== MODAL: PROCESS RETURN ==================== -->
<div id="modalProcessReturn" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Process Overdue Book Return</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="returnBookForm" onsubmit="submitReturnBook(event)">
            <input type="hidden" name="action" value="return">
            <input type="hidden" id="return_txn_id" name="txn_id">
            <div class="modal-body">
                <div style="background: rgba(255, 62, 29, 0.08); padding: 1rem; border-radius: var(--radius-md); border-left: 4px solid var(--danger); margin-bottom: 1.25rem;">
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
                    <label class="form-label" for="return_fine_amount">Calculated Overdue Fine ($)</label>
                    <input type="number" id="return_fine_amount" name="fine_amount" class="form-control" step="0.50" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="return_notes">Return Remarks</label>
                    <textarea id="return_notes" name="notes" class="form-control" rows="2" placeholder="Late return fee collected at circulation desk..."></textarea>
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
function openReturnModal(txnId, title, borrowerName, dueDate, daysOverdue) {
    document.getElementById('return_txn_id').value = txnId;
    document.getElementById('return_book_title').textContent = title;
    document.getElementById('return_borrower_name').textContent = "Borrower: " + borrowerName + " (Due: " + dueDate + ")";
    
    const fineInput = document.getElementById('return_fine_amount');
    const notice = document.getElementById('return_overdue_notice');

    const estFine = daysOverdue * 2.0;
    fineInput.value = estFine.toFixed(2);
    notice.innerHTML = `<span style="color: var(--danger); font-weight: 600;"><i class="bx bx-error-circle"></i> Book is ${daysOverdue} days overdue! Late fee: $${estFine.toFixed(2)}</span>`;

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
        showAlertModal(data.message || 'Error processing return', 'Return Processing Error', 'error');
    }
}

async function sendOverdueNotice(txnId) {
    const formData = new FormData();
    formData.append('action', 'send_overdue_notice');
    formData.append('txn_id', txnId);

    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        showToast('Overdue reminder logged & sent!', 'success');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
