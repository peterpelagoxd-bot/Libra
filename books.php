<?php
/**
 * LIBRA Books Management & Catalog Page
 */

require_once __DIR__ . '/includes/auth_check.php';
checkAuth();

$pdo = Database::getConnection();
$user = currentUser();
$role = $user['role'];
$pageTitle = "Books & Catalog";

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch books with category info
$stmt = $pdo->query("
    SELECT b.*, c.name as category_name
    FROM books b
    JOIN categories c ON b.category_id = c.id
    ORDER BY b.title ASC
");
$books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books & Catalog - LIBRA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .filter-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .category-pills-sneat {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .view-toggle-sneat {
            display: flex;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.2rem;
        }
        .view-btn-sneat {
            border: none;
            background: transparent;
            color: var(--text-muted);
            padding: 0.35rem 0.65rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8125rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .view-btn-sneat.active {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: var(--primary-shadow);
        }
        .books-grid-sneat {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .book-card-sneat {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-subtle);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }
        .book-card-sneat:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }
        .book-header-banner {
            padding: 1.25rem;
            color: white;
            position: relative;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .bg-gradient-sneat-1 { background: linear-gradient(135deg, #696cff, #3f42c9); }
        .bg-gradient-sneat-2 { background: linear-gradient(135deg, #71dd37, #3da107); }
        .bg-gradient-sneat-3 { background: linear-gradient(135deg, #03c3ec, #0288a5); }
        .bg-gradient-sneat-4 { background: linear-gradient(135deg, #ffab00, #c48300); }
        .bg-gradient-sneat-5 { background: linear-gradient(135deg, #ff3e1d, #c4260a); }
        .bg-gradient-sneat-6 { background: linear-gradient(135deg, #8592a3, #566a7f); }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="layout-page">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-wrapper">
            <!-- Filter & Action Controls -->
            <div class="filter-toolbar">
                <div style="position: relative; max-width: 360px; width: 100%;">
                    <i class="bx bx-search" style="position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.25rem;"></i>
                    <input type="text" id="bookSearchInput" class="form-control" style="padding-left: 2.5rem;" placeholder="Search title, author, ISBN, category...">
                </div>

                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <div class="view-toggle-sneat">
                        <button id="viewGridBtn" class="view-btn-sneat active" onclick="switchView('grid')">
                            <i class="bx bx-grid-alt"></i>
                            <span>Cards</span>
                        </button>
                        <button id="viewTableBtn" class="view-btn-sneat" onclick="switchView('table')">
                            <i class="bx bx-list-ul"></i>
                            <span>Table</span>
                        </button>
                    </div>

                    <?php if ($role === 'Administrator' || $role === 'Librarian'): ?>
                        <button class="btn btn-primary" data-modal-target="modalAddBook">
                            <i class="bx bx-plus"></i>
                            <span>Add New Book</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Toolbar (Availability & Category Pills) -->
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
                <!-- Availability Pills -->
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--text-muted); margin-right: 0.25rem;">Availability:</span>
                    <button class="btn <?= (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'btn-primary' : 'btn-label-secondary' ?> btn-sm filter-avail-btn" data-filter-avail="all">
                        <i class="bx bx-collection"></i> All Books
                    </button>
                    <button class="btn <?= (isset($_GET['filter']) && $_GET['filter'] === 'available') ? 'btn-primary' : 'btn-label-secondary' ?> btn-sm filter-avail-btn" data-filter-avail="available">
                        <i class="bx bx-check-circle text-success"></i> In Stock (Available)
                    </button>
                    <button class="btn <?= (isset($_GET['filter']) && $_GET['filter'] === 'borrowed') ? 'btn-primary' : 'btn-label-secondary' ?> btn-sm filter-avail-btn" data-filter-avail="borrowed">
                        <i class="bx bx-x-circle text-danger"></i> Out of Stock (All Borrowed)
                    </button>
                </div>

                <!-- Category Filter Pills -->
                <div class="category-pills-sneat" style="margin-bottom: 0;">
                    <button class="btn btn-primary btn-sm filter-cat-btn" data-filter-category="all">All Categories</button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="btn btn-label-secondary btn-sm filter-cat-btn" data-filter-category="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- VIEW 1: SNEAT BOOK CARDS GRID -->
            <div id="booksGridView" class="books-grid-sneat">
                <?php foreach ($books as $index => $book): 
                    $gradClass = "bg-gradient-sneat-" . (($index % 6) + 1);
                    $isAvailable = $book['available_copies'] > 0;
                    $borrowedCopies = max(0, (int)$book['total_copies'] - (int)$book['available_copies']);
                ?>
                <div class="book-card-sneat book-card-item" 
                     data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>" 
                     data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>" 
                     data-isbn="<?= htmlspecialchars(strtolower($book['isbn'])) ?>" 
                     data-category="<?= htmlspecialchars(strtolower($book['category_name'])) ?>"
                     data-category-id="<?= $book['category_id'] ?>"
                     data-available="<?= $isAvailable ? '1' : '0' ?>">
                    
                    <div class="book-header-banner <?= $gradClass ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <span class="badge badge-pill book-avail-badge" style="background-color: rgba(255,255,255,0.98); box-shadow: 0 2px 6px rgba(0,0,0,0.15); font-weight: 700; padding: 0.35rem 0.65rem; border: 1px solid rgba(0,0,0,0.1); display: inline-flex; align-items: center; gap: 0.25rem;">
                                <i class="bx <?= $isAvailable ? 'bx-check-circle avail-available' : 'bx-x-circle avail-unavailable' ?>" style="color: <?= $isAvailable ? '#0f5132' : '#842029' ?>; font-size: 0.95rem; font-weight: 700;"></i>
                                <span class="<?= $isAvailable ? 'avail-available' : 'avail-unavailable' ?>" style="color: <?= $isAvailable ? '#0f5132' : '#842029' ?>; font-weight: 700; letter-spacing: -0.2px;"><?= $book['available_copies'] ?> Available</span>
                                <span class="avail-bullet" style="color: #495057; font-weight: 900; margin: 0 1px;">&bull;</span>
                                <span class="avail-borrowed" style="color: #212529; font-weight: 700; letter-spacing: -0.2px;"><?= $borrowedCopies ?> Borrowed</span>
                            </span>
                            <span style="font-size: 0.75rem; background: rgba(0,0,0,0.25); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 600;">
                                <?= htmlspecialchars($book['shelf_location']) ?>
                            </span>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?= htmlspecialchars($book['category_name']) ?>
                        </div>
                    </div>

                    <div style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1;">
                        <h6 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-heading); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= htmlspecialchars($book['title']) ?>">
                            <?= htmlspecialchars($book['title']) ?>
                        </h6>
                        <small style="color: var(--text-muted); margin-bottom: 0.75rem;">By <?= htmlspecialchars($book['author']) ?></small>
                        
                        <p style="font-size: 0.8125rem; color: var(--text-main); line-height: 1.45; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($book['description']) ?>
                        </p>

                        <div style="margin-top: auto; padding-top: 0.75rem; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;">
                            <span>ISBN: <?= htmlspecialchars($book['isbn']) ?></span>
                            <span><?= htmlspecialchars($book['published_year']) ?></span>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($role === 'Borrower'): ?>
                                <?php if ($isAvailable): ?>
                                    <button class="btn btn-primary btn-sm" style="width: 100%;" onclick="openBorrowRequestModal(<?= $book['id'] ?>, '<?= addslashes(htmlspecialchars($book['title'])) ?>')">
                                        <i class="bx bx-bookmark-plus"></i>
                                        <span>Request to Borrow</span>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-label-secondary btn-sm" style="width: 100%; opacity: 0.6; cursor: not-allowed;" disabled>
                                        <i class="bx bx-lock-alt"></i>
                                        <span>Unavailable (All Loaned)</span>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-label-secondary btn-sm" style="flex: 1;" onclick="openEditBookModal(<?= $book['id'] ?>)">
                                    <i class="bx bx-edit-alt"></i>
                                    <span>Edit</span>
                                </button>
                                <button class="btn btn-primary btn-sm" style="flex: 1;" onclick="quickIssueBook(<?= $book['id'] ?>)">
                                    <i class="bx bx-plus-circle"></i>
                                    <span>Issue</span>
                                </button>
                                <button class="btn btn-label-danger btn-sm" onclick="deleteBook(<?= $book['id'] ?>, '<?= addslashes(htmlspecialchars($book['title'])) ?>')">
                                    <i class="bx bx-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- VIEW 2: SNEAT BOOK TABLE VIEW -->
            <div id="booksTableView" class="card filterable-table" style="display: none; padding: 0;">
                <div class="table-responsive">
                    <table class="table-sneat">
                        <thead>
                            <tr>
                                <th>Book Title & Details</th>
                                <th>Category</th>
                                <th>ISBN</th>
                                <th>Shelf</th>
                                <th>Availability</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $b): ?>
                            <tr class="book-row-item"
                                data-title="<?= htmlspecialchars(strtolower($b['title'])) ?>"
                                data-author="<?= htmlspecialchars(strtolower($b['author'])) ?>"
                                data-isbn="<?= htmlspecialchars(strtolower($b['isbn'])) ?>"
                                data-category="<?= htmlspecialchars(strtolower($b['category_name'])) ?>"
                                data-category-id="<?= $b['category_id'] ?>"
                                data-available="<?= $b['available_copies'] > 0 ? '1' : '0' ?>">
                                <td>
                                    <div style="font-weight: 600; color: var(--text-heading);"><?= htmlspecialchars($b['title']) ?></div>
                                    <small style="color: var(--text-muted);">By <?= htmlspecialchars($b['author']) ?> &bull; <?= $b['published_year'] ?></small>
                                </td>
                                <td><span class="badge badge-pill badge-bg-primary"><?= htmlspecialchars($b['category_name']) ?></span></td>
                                <td style="font-family: monospace; font-size: 0.8125rem;"><?= htmlspecialchars($b['isbn']) ?></td>
                                <td><?= htmlspecialchars($b['shelf_location']) ?></td>
                                <td>
                                    <?php $borrowedCount = $b['total_copies'] - $b['available_copies']; ?>
                                    <div style="display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap;">
                                        <span class="badge badge-pill <?= $b['available_copies'] > 0 ? 'badge-bg-success' : 'badge-bg-danger' ?>">
                                            <?= $b['available_copies'] ?> Available
                                        </span>
                                        <span class="badge badge-pill <?= $borrowedCount > 0 ? 'badge-bg-warning' : 'badge-bg-secondary' ?>">
                                            <?= $borrowedCount ?> Borrowed
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($role === 'Borrower'): ?>
                                        <?php if ($b['available_copies'] > 0): ?>
                                            <button class="btn btn-primary btn-sm" onclick="openBorrowRequestModal(<?= $b['id'] ?>, '<?= addslashes(htmlspecialchars($b['title'])) ?>')">
                                                Request
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-pill badge-bg-danger">Unavailable</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 0.4rem;">
                                            <button class="btn btn-label-secondary btn-sm" onclick="openEditBookModal(<?= $b['id'] ?>)">Edit</button>
                                            <button class="btn btn-label-danger btn-sm" onclick="deleteBook(<?= $b['id'] ?>, '<?= addslashes(htmlspecialchars($b['title'])) ?>')">Delete</button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<!-- ==================== MODAL: ADD BOOK ==================== -->
<div id="modalAddBook" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Add New Book to Library</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="addBookForm" onsubmit="submitAddBook(event)">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_title">Book Title *</label>
                    <input type="text" id="add_title" name="title" class="form-control" required placeholder="e.g. Clean Architecture">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_author">Author(s) *</label>
                        <input type="text" id="add_author" name="author" class="form-control" required placeholder="e.g. Robert C. Martin">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_category_id">Category *</label>
                        <select id="add_category_id" name="category_id" class="form-control" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_isbn">ISBN (Unique) *</label>
                        <input type="text" id="add_isbn" name="isbn" class="form-control" required placeholder="e.g. 978-0134494166">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_total_copies">Total Copies *</label>
                        <input type="number" id="add_total_copies" name="total_copies" class="form-control" min="1" max="100" value="3" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_publisher">Publisher</label>
                        <input type="text" id="add_publisher" name="publisher" class="form-control" placeholder="e.g. Prentice Hall">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_published_year">Year</label>
                        <input type="number" id="add_published_year" name="published_year" class="form-control" value="<?= date('Y') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_shelf_location">Shelf</label>
                        <input type="text" id="add_shelf_location" name="shelf_location" class="form-control" placeholder="e.g. Shelf B2-01">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_description">Description / Synopsis</label>
                    <textarea id="add_description" name="description" class="form-control" rows="3" placeholder="Book summary..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Book</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EDIT BOOK ==================== -->
<div id="modalEditBook" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Edit Book Details</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="editBookForm" onsubmit="submitEditBook(event)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_title">Book Title *</label>
                    <input type="text" id="edit_title" name="title" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_author">Author(s) *</label>
                        <input type="text" id="edit_author" name="author" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_category_id">Category *</label>
                        <select id="edit_category_id" name="category_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_isbn">ISBN</label>
                        <input type="text" id="edit_isbn" name="isbn" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_total_copies">Total Copies</label>
                        <input type="number" id="edit_total_copies" name="total_copies" class="form-control" min="1" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_publisher">Publisher</label>
                        <input type="text" id="edit_publisher" name="publisher" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_published_year">Year</label>
                        <input type="number" id="edit_published_year" name="published_year" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_shelf_location">Shelf</label>
                        <input type="text" id="edit_shelf_location" name="shelf_location" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Update Book</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: BORROWER REQUEST ==================== -->
<div id="modalBorrowRequest" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <h5 class="modal-title">Submit Book Borrow Request</h5>
            <button class="btn-icon" data-modal-close style="border: none; background: transparent;"><i class="bx bx-x" style="font-size: 1.5rem;"></i></button>
        </div>
        <form id="borrowRequestForm" onsubmit="submitBorrowRequest(event)">
            <input type="hidden" name="action" value="request">
            <input type="hidden" id="request_book_id" name="book_id">
            <div class="modal-body">
                <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.875rem;">
                    You are submitting a borrowing request for:
                </p>
                <div style="background: rgba(105, 108, 255, 0.08); padding: 1rem; border-radius: var(--radius-md); border-left: 4px solid var(--primary); margin-bottom: 1.25rem;">
                    <div id="request_book_title" style="font-weight: 700; font-size: 1rem; color: var(--text-heading);">Book Title</div>
                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Standard Loan: 14 Days from circulation desk pickup</small>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-main); line-height: 1.5;">
                    Once submitted, our librarians will verify stock and prepare your book at the circulation desk.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyBookFilters() {
    const query = (document.getElementById('bookSearchInput')?.value || '').toLowerCase().trim();
    const activeCatBtn = document.querySelector('.filter-cat-btn.btn-primary');
    const catId = activeCatBtn ? activeCatBtn.getAttribute('data-filter-category') : 'all';
    
    const activeAvailBtn = document.querySelector('.filter-avail-btn.btn-primary');
    const availFilter = activeAvailBtn ? activeAvailBtn.getAttribute('data-filter-avail') : 'all';

    // 1. Filter Cards
    let visibleCards = 0;
    document.querySelectorAll('.book-card-item').forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const author = card.getAttribute('data-author') || '';
        const isbn = card.getAttribute('data-isbn') || '';
        const category = card.getAttribute('data-category') || '';
        const cardCatId = card.getAttribute('data-category-id');
        const isAvailable = card.getAttribute('data-available') === '1';

        const matchSearch = !query || title.includes(query) || author.includes(query) || isbn.includes(query) || category.includes(query);
        const matchCat = (catId === 'all' || cardCatId === catId);
        const matchAvail = (availFilter === 'all') || 
                           (availFilter === 'available' && isAvailable) || 
                           (availFilter === 'borrowed' && !isAvailable);

        if (matchSearch && matchCat && matchAvail) {
            card.style.display = '';
            visibleCards++;
        } else {
            card.style.display = 'none';
        }
    });

    // 2. Filter Table Rows
    let visibleRows = 0;
    document.querySelectorAll('.book-row-item').forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const author = row.getAttribute('data-author') || '';
        const isbn = row.getAttribute('data-isbn') || '';
        const category = row.getAttribute('data-category') || '';
        const rowCatId = row.getAttribute('data-category-id');
        const isAvailable = row.getAttribute('data-available') === '1';

        const matchSearch = !query || title.includes(query) || author.includes(query) || isbn.includes(query) || category.includes(query);
        const matchCat = (catId === 'all' || rowCatId === catId);
        const matchAvail = (availFilter === 'all') || 
                           (availFilter === 'available' && isAvailable) || 
                           (availFilter === 'borrowed' && !isAvailable);

        if (matchSearch && matchCat && matchAvail) {
            row.style.display = '';
            visibleRows++;
        } else {
            row.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Search input listener
    const searchInput = document.getElementById('bookSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', applyBookFilters);
    }

    // Category button listeners
    document.querySelectorAll('.filter-cat-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.filter-cat-btn').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-label-secondary');
            });
            btn.classList.remove('btn-label-secondary');
            btn.classList.add('btn-primary');
            applyBookFilters();
        });
    });

    // Availability button listeners
    document.querySelectorAll('.filter-avail-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.filter-avail-btn').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-label-secondary');
            });
            btn.classList.remove('btn-label-secondary');
            btn.classList.add('btn-primary');
            applyBookFilters();
        });
    });

    // Auto-check URL query parameter
    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter');
    if (filterParam === 'available' || filterParam === 'borrowed') {
        const targetBtn = document.querySelector(`.filter-avail-btn[data-filter-avail="${filterParam}"]`);
        if (targetBtn) {
            document.querySelectorAll('.filter-avail-btn').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-label-secondary');
            });
            targetBtn.classList.remove('btn-label-secondary');
            targetBtn.classList.add('btn-primary');
            applyBookFilters();
        }
    }
});

function switchView(mode) {
    const grid = document.getElementById('booksGridView');
    const table = document.getElementById('booksTableView');
    const gridBtn = document.getElementById('viewGridBtn');
    const tableBtn = document.getElementById('viewTableBtn');

    if (mode === 'table') {
        grid.style.display = 'none';
        table.style.display = 'block';
        tableBtn.classList.add('active');
        gridBtn.classList.remove('active');
    } else {
        grid.style.display = 'grid';
        table.style.display = 'none';
        gridBtn.classList.add('active');
        tableBtn.classList.remove('active');
    }
}

async function submitAddBook(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/books.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error adding book', 'Catalog Error', 'error');
    }
}

async function openEditBookModal(bookId) {
    const res = await fetch(`api/books.php?action=get&id=${bookId}`);
    const data = await res.json();
    if (data.success) {
        const b = data.book;
        document.getElementById('edit_id').value = b.id;
        document.getElementById('edit_title').value = b.title;
        document.getElementById('edit_author').value = b.author;
        document.getElementById('edit_category_id').value = b.category_id;
        document.getElementById('edit_isbn').value = b.isbn;
        document.getElementById('edit_total_copies').value = b.total_copies;
        document.getElementById('edit_publisher').value = b.publisher || '';
        document.getElementById('edit_published_year').value = b.published_year || '';
        document.getElementById('edit_shelf_location').value = b.shelf_location || '';
        document.getElementById('edit_description').value = b.description || '';
        openModal('modalEditBook');
    }
}

async function submitEditBook(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('api/books.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error updating book', 'Update Error', 'error');
    }
}

async function deleteBook(id, title) {
    const confirmed = await showConfirmModal(`Are you sure you want to delete "${title}"?`, {
        title: 'Delete Book',
        confirmText: 'Delete Book',
        confirmBtnClass: 'btn-danger',
        type: 'danger'
    });
    if (!confirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);

    const res = await fetch('api/books.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.reload();
    } else {
        showAlertModal(data.message || 'Error deleting book', 'Delete Error', 'error');
    }
}

function openBorrowRequestModal(bookId, title) {
    document.getElementById('request_book_id').value = bookId;
    document.getElementById('request_book_title').textContent = title;
    openModal('modalBorrowRequest');
}

async function submitBorrowRequest(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    // Automatically close and remove the request modal (Image 4)
    closeModal('modalBorrowRequest');
    
    const res = await fetch('api/transactions.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        window.location.href = 'my-borrows.php';
    } else {
        await showAlertModal(data.message || 'Error submitting request', 'Request Notice', 'warning');
        closeModal('modalBorrowRequest');
    }
}

function quickIssueBook(bookId) {
    window.location.href = `transactions.php?action=issue&book_id=${bookId}`;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
