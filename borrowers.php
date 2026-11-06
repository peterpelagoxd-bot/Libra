<?php
/**
 * LIBRA Borrower Management Page
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator', 'Librarian']);

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "Borrower Management";
$today = date('Y-m-d');

// Fetch borrowers with active and overdue loan counts
$stmt = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM transactions WHERE borrower_id = b.id AND status IN ('Borrowed', 'Overdue')) as active_loans,
           (SELECT COUNT(*) FROM transactions WHERE borrower_id = b.id AND status = 'Overdue') as overdue_loans
    FROM borrowers b
    ORDER BY b.created_at DESC
");
$borrowers = $stmt->fetchAll();

$totalBorrowers = count($borrowers);
$activeCount = count(array_filter($borrowers, fn($b) => $b['status'] === 'Active'));
$studentCount = count(array_filter($borrowers, fn($b) => $b['type'] === 'Student'));
$employeeCount = count(array_filter($borrowers, fn($b) => $b['type'] === 'Employee'));

// Filter table list if filter parameter is provided
$selectedFilter = strtolower($_GET['filter'] ?? 'all');
if ($selectedFilter === 'student') {
    $borrowers = array_values(array_filter($borrowers, fn($b) => strtolower($b['type']) === 'student'));
} elseif ($selectedFilter === 'employee') {
    $borrowers = array_values(array_filter($borrowers, fn($b) => strtolower($b['type']) === 'employee'));
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower Management - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Sneat Metrics Summary (Interactive & Filterable) -->
            <div class="stat-cards-grid">
                <a href="borrowers.php?filter=all" class="stat-card stat-card-link" title="Click to view all registered borrowers">
                    <div class="stat-card-header">
                        <div class="stat-avatar primary">
                            <i class="bx bx-user-pin"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-primary">Directory</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Total Registered</div>
                    <div class="stat-value"><?= number_format($totalBorrowers) ?></div>
                    <div class="stat-growth positive">
                        <i class="bx bx-check-shield"></i>
                        <span><?= $activeCount ?> Active Members</span>
                    </div>
                </a>

                <a href="borrowers.php?filter=student" class="stat-card stat-card-link" title="Click to filter by Student borrowers">
                    <div class="stat-card-header">
                        <div class="stat-avatar info">
                            <i class="bx bx-graduation"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-info">Students</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Students Enrolled</div>
                    <div class="stat-value" style="color: var(--info);"><?= number_format($studentCount) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-book-reader"></i>
                        <span>Undergraduate & Graduate</span>
                    </div>
                </a>

                <a href="borrowers.php?filter=employee" class="stat-card stat-card-link" title="Click to filter by Faculty & Employee borrowers">
                    <div class="stat-card-header">
                        <div class="stat-avatar warning">
                            <i class="bx bx-briefcase-alt-2"></i>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <span class="badge badge-pill badge-bg-warning">Employees</span>
                            <i class="bx bx-chevron-right stat-card-arrow"></i>
                        </div>
                    </div>
                    <div class="stat-label">Faculty & Staff</div>
                    <div class="stat-value" style="color: var(--warning);"><?= number_format($employeeCount) ?></div>
                    <div class="stat-growth neutral">
                        <i class="bx bx-buildings"></i>
                        <span>Professors and University Staff</span>
                    </div>
                </a>
            </div>

            <!-- Top Filter and Action Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="position: relative; max-width: 360px; width: 100%;">
                    <i class="bx bx-search" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.25rem;"></i>
                    <input type="text" id="tableSearchInput" class="form-control" style="padding-left: 2.5rem;" placeholder="Search name, code, or department...">
                </div>

                <button class="btn btn-primary" data-modal-target="modalAddBorrower">
                    <i class="bx bx-user-plus"></i>
                    <span>Register New Borrower</span>
                </button>
            </div>

            <!-- Borrowers Table Card -->
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Borrower Code</th>
                                <th>Name & Profile</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Contact Information</th>
                                <th>Active Loans</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowers as $b): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: var(--text-heading);">
                                    <?= htmlspecialchars($b['borrower_code']) ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($b['full_name']) ?></div>
                                    <small style="color: var(--text-muted);">Joined: <?= substr($b['created_at'], 0, 10) ?></small>
                                </td>
                                <td>
                                    <?php if ($b['type'] === 'Employee'): ?>
                                        <span class="badge badge-pill badge-bg-warning">Employee</span>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-bg-info">Student</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($b['department']) ?></td>
                                <td>
                                    <div style="font-size: 0.8125rem; color: var(--text-heading);"><?= htmlspecialchars($b['email']) ?></div>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($b['phone'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <?php if ($b['active_loans'] > 0): ?>
                                        <span class="badge badge-pill badge-bg-primary"><?= $b['active_loans'] ?> Books</span>
                                        <?php if ($b['overdue_loans'] > 0): ?>
                                            <span class="badge badge-pill badge-bg-danger" style="margin-left: 0.25rem;">(<?= $b['overdue_loans'] ?> Overdue)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-bg-secondary">0 Books</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'Active'): ?>
                                        <span class="badge badge-pill badge-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-bg-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem;">
                                        <button class="btn btn-label-secondary btn-sm" onclick="openEditBorrowerModal(<?= $b['id'] ?>)">
                                            <i class="bx bx-edit-alt"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button class="btn btn-label-secondary btn-sm" onclick="toggleBorrowerStatus(<?= $b['id'] ?>)">
                                            <?= $b['status'] === 'Active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<!-- ==================== MODAL: ADD BORROWER ==================== -->
<div id="modalAddBorrower" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Register New Borrower</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="addBorrowerForm" onsubmit="submitAddBorrower(event)">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_full_name">Full Name *</label>
                    <input type="text" id="add_full_name" name="full_name" class="form-control" required placeholder="e.g. Jordan Miller">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_type">Borrower Type *</label>
                        <select id="add_type" name="type" class="form-control" required>
                            <option value="Student">Student</option>
                            <option value="Employee">Employee / Faculty</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_department">Department / Course *</label>
                        <input type="text" id="add_department" name="department" class="form-control" required placeholder="e.g. Computer Science">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_email">Email Address *</label>
                        <input type="email" id="add_email" name="email" class="form-control" required placeholder="jordan.miller@university.edu">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_phone">Phone Number</label>
                        <input type="tel" id="add_phone" name="phone" class="form-control" placeholder="09123456789" inputmode="numeric" pattern="[0-9]*" maxlength="15" autocomplete="tel">
                        <small style="font-size: 0.725rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">Numbers only (letters cannot be entered)</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Register Borrower</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EDIT BORROWER ==================== -->
<div id="modalEditBorrower" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Edit Borrower Details</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="editBorrowerForm" onsubmit="submitEditBorrower(event)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_borrower_id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_type">Borrower Type</label>
                        <select id="edit_type" name="type" class="form-control" required>
                            <option value="Student">Student</option>
                            <option value="Employee">Employee / Faculty</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_status">Account Status</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_department">Department</label>
                        <input type="text" id="edit_department" name="department" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_phone">Phone Number</label>
                    <input type="tel" id="edit_phone" name="phone" class="form-control" placeholder="09123456789" inputmode="numeric" pattern="[0-9]*" maxlength="15" autocomplete="tel">
                    <small style="font-size: 0.725rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">Numbers only (letters cannot be entered)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
async function submitAddBorrower(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/borrowers.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error registering borrower', 'Registration Error', 'error');
    }
}

async function openEditBorrowerModal(borrowerId) {
    const res = await fetch(`api/borrowers.php?action=get&id=${borrowerId}`);
    const data = await res.json();
    if (data.success) {
        const b = data.borrower;
        document.getElementById('edit_borrower_id').value = b.id;
        document.getElementById('edit_full_name').value = b.full_name;
        document.getElementById('edit_type').value = b.type;
        document.getElementById('edit_department').value = b.department;
        document.getElementById('edit_email').value = b.email;
        document.getElementById('edit_phone').value = b.phone || '';
        document.getElementById('edit_status').value = b.status;
        openModal('modalEditBorrower');
    }
}

async function submitEditBorrower(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/borrowers.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error updating borrower', 'Update Error', 'error');
    }
}

async function toggleBorrowerStatus(borrowerId) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', borrowerId);

    const res = await fetch('api/borrowers.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    }
}

// Strictly enforce numeric-only input on phone fields (prevent all letters)
function enforceNumericPhoneInputs() {
    const inputs = document.querySelectorAll('input[name="phone"], #add_phone, #edit_phone');
    inputs.forEach(input => {
        // 1. Block letters during typing (keydown)
        input.addEventListener('keydown', function(e) {
            // Allow control keys: Backspace, Tab, Enter, Escape, Delete, Arrows, Home, End
            const allowedKeys = ['Backspace', 'Tab', 'Enter', 'Escape', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
            if (allowedKeys.includes(e.key)) return;
            // Allow Ctrl / Cmd shortcuts (Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X, Ctrl+Z)
            if (e.ctrlKey || e.metaKey) return;
            // Block all keys that are not numbers (0-9)
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });

        // 2. Sanitize on input (handles mobile keyboards, IME, autofill, dragging)
        input.addEventListener('input', function() {
            const numbersOnly = this.value.replace(/[^0-9]/g, '');
            if (this.value !== numbersOnly) {
                this.value = numbersOnly;
            }
        });

        // 3. Sanitize on paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text') || '';
            const numbersOnly = pastedText.replace(/[^0-9]/g, '');
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const currentVal = this.value;
            this.value = currentVal.substring(0, start) + numbersOnly + currentVal.substring(end);
            this.selectionStart = this.selectionEnd = start + numbersOnly.length;
        });
    });
}

document.addEventListener('DOMContentLoaded', enforceNumericPhoneInputs);
enforceNumericPhoneInputs();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
