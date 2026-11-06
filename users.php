<?php
/**
 * LIBRA User Accounts Management (Administrator Only)
 */

require_once __DIR__ . '/includes/auth_check.php';
requireRole(['Administrator']);

$pdo = Database::getConnection();
$user = currentUser();
$pageTitle = "Manage User Accounts";

// Fetch all users
$stmt = $pdo->query("
    SELECT u.*, b.borrower_code, b.department
    FROM users u
    LEFT JOIN borrowers b ON u.borrower_id = b.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();

// Fetch unlinked borrowers for account assignment
$unlinkedBorrowers = $pdo->query("
    SELECT id, full_name, borrower_code, department 
    FROM borrowers 
    ORDER BY full_name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Accounts - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                    <input type="text" id="tableSearchInput" class="form-control" style="padding-left: 2.5rem;" placeholder="Search user accounts...">
                </div>

                <button class="btn btn-primary" data-modal-target="modalAddUser">
                    <i class="bx bx-user-plus"></i>
                    <span>Create User Account</span>
                </button>
            </div>

            <!-- Users Table Card -->
            <div class="card filterable-table" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Linked Borrower</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="avatar-sneat" style="width: 34px; height: 34px; font-size: 0.8125rem;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <small style="color: var(--text-muted); font-family: monospace;">@<?= htmlspecialchars($u['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'Administrator'): ?>
                                        <span class="badge badge-pill badge-bg-primary">Administrator</span>
                                    <?php elseif ($u['role'] === 'Librarian'): ?>
                                        <span class="badge badge-pill badge-bg-warning">Librarian</span>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-bg-info">Borrower</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if ($u['borrower_id']): ?>
                                        <span style="font-size: 0.8125rem; font-family: monospace; font-weight: 600; color: var(--text-heading);">
                                            <?= htmlspecialchars($u['borrower_code']) ?>
                                        </span>
                                    <?php else: ?>
                                        <small style="color: var(--text-muted);">Staff / None</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                                        <span class="badge badge-pill badge-bg-danger" title="Locked until <?= $u['locked_until'] ?>">
                                            <i class="bx bx-lock-alt"></i> Locked (4 fails)
                                        </span>
                                    <?php elseif ($u['status'] === 'Active'): ?>
                                        <span class="badge badge-pill badge-bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-pill badge-bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--text-muted);">
                                    <?= substr($u['created_at'], 0, 10) ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                        <?php if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                                            <button class="btn btn-label-warning btn-sm" onclick="unlockUserAccount(<?= $u['id'] ?>)" title="Unlock account immediately">
                                                <i class="bx bx-lock-open-alt"></i>
                                                <span>Unlock</span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-label-secondary btn-sm" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                            <i class="bx bx-edit-alt"></i>
                                            <span>Edit</span>
                                        </button>
                                        <?php if ($u['id'] !== $user['id']): ?>
                                            <button class="btn btn-label-secondary btn-sm" onclick="toggleUserStatus(<?= $u['id'] ?>)">
                                                <?= $u['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<!-- ==================== MODAL: ADD USER ==================== -->
<div id="modalAddUser" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Create User Account</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="addUserForm" onsubmit="submitAddUser(event)">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_username">Username *</label>
                        <input type="text" id="add_username" name="username" class="form-control" required placeholder="e.g. jsmith">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_password">Initial Password *</label>
                        <input type="password" id="add_password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_full_name">Full Name *</label>
                    <input type="text" id="add_full_name" name="full_name" class="form-control" required placeholder="e.g. John Smith">
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_email">Email Address *</label>
                    <input type="email" id="add_email" name="email" class="form-control" required placeholder="john.smith@library.edu">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_role">System Role *</label>
                        <select id="add_role" name="role" class="form-control" required onchange="handleRoleChange(this.value)">
                            <option value="Borrower">Borrower (Student/Employee)</option>
                            <option value="Librarian">Librarian (Circulation Desk)</option>
                            <option value="Administrator">Administrator (Full Access)</option>
                        </select>
                    </div>
                    <div class="form-group" id="borrowerLinkGroup">
                        <label class="form-label" for="add_borrower_id">Link to Borrower Profile</label>
                        <select id="add_borrower_id" name="borrower_id" class="form-control">
                            <option value="">-- None / Select Borrower --</option>
                            <?php foreach ($unlinkedBorrowers as $ub): ?>
                                <option value="<?= $ub['id'] ?>"><?= htmlspecialchars($ub['full_name']) ?> (<?= htmlspecialchars($ub['borrower_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EDIT USER ==================== -->
<div id="modalEditUser" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Edit User Account</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="editUserForm" onsubmit="submitEditUser(event)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_user_id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_full_name">Full Name *</label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_email">Email Address *</label>
                    <input type="email" id="edit_email" name="email" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_role">System Role *</label>
                        <select id="edit_role" name="role" class="form-control" required>
                            <option value="Administrator">Administrator</option>
                            <option value="Librarian">Librarian</option>
                            <option value="Borrower">Borrower</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_status">Account Status *</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_password">Reset Password (Leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password" class="form-control" placeholder="New password...">
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
function handleRoleChange(role) {
    const group = document.getElementById('borrowerLinkGroup');
    if (role === 'Borrower') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
    }
}

async function submitAddUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/users.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error creating user', 'User Account Error', 'error');
    }
}

function openEditUserModal(userObj) {
    document.getElementById('edit_user_id').value = userObj.id;
    document.getElementById('edit_full_name').value = userObj.full_name;
    document.getElementById('edit_email').value = userObj.email;
    document.getElementById('edit_role').value = userObj.role;
    document.getElementById('edit_status').value = userObj.status;
    document.getElementById('edit_password').value = '';
    openModal('modalEditUser');
}

async function submitEditUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/users.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error updating user', 'Update Error', 'error');
    }
}

async function toggleUserStatus(userId) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('id', userId);

    const res = await fetch('api/users.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error updating status', 'Status Error', 'error');
    }
}

async function unlockUserAccount(userId) {
    const confirmed = await showConfirmModal('Unlock this user account and reset failed login attempts?', {
        title: 'Unlock Account',
        confirmText: 'Unlock Account',
        confirmBtnClass: 'btn-warning',
        type: 'info'
    });
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'unlock');
    formData.append('id', userId);

    const res = await fetch('api/users.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error unlocking account', 'Unlock Error', 'error');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
