# 📚 LIBRA — Digital Library Book Borrowing & Management System

A digital library book borrowing system built strictly following the architecture, features, database entities, and flowchart specified in [`Library_Book_Borrowing_System.md`](Library_Book_Borrowing_System.md).

---

## 🌟 System Architecture & User Roles

The system implements Role-Based Access Control (RBAC) across three distinct user roles:

```
                                      ┌──────────────┐
                                      │  User Login  │
                                      └──────┬───────┘
                                             │
                       ┌─────────────────────┼─────────────────────┐
                       ▼                     ▼                     ▼
              ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
              │  Administrator  │   │    Librarian    │   │    Borrower     │
              └────────┬────────┘   └────────┬────────┘   └────────┬────────┘
                       │                     │                     │
      ┌────────────────┴──────────────┐      │            ┌────────┴──────────────┐
      │ - Manage User Accounts (D5)   │      │            │ - Search & View Books │
      │ - Manage Books & Categories   │      │            │ - Availability Badges │
      │ - View Dashboard & Reports    │      │            │ - Request to Borrow   │
      └───────────────────────────────┘      │            │ - Personal Due Dates  │
                                             │            └───────────────────────┘
                 ┌───────────────────────────┴───────────────────────────┐
                 │ - Manage Book Records (D1) & Stock Tracking           │
                 │ - Manage Borrower Records: Students & Employees (D2)  │
                 │ - Real-time Book Availability Checking                │
                 │ - Process Book Borrowing (Issue & Due Date) (D3)      │
                 │ - Approve / Decline Online Borrow Requests            │
                 │ - Process Book Returns & Restock Quantities (D4)      │
                 │ - Overdue Monitoring & Late Fine Assessment           │
                 │ - Circulation Dashboard & Analytics                   │
                 └───────────────────────────────────────────────────────┘
```

---

## 🔑 Demo User Accounts & One-Click Sign-In

The login screen provides **instant 1-click login buttons** for fast evaluation:

| Role | Username | Password | Full Name | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `Libraadmin` | Dr. Eleanor Vance | Full administrative control, user account management, analytics. |
| **Librarian** | `librarian` | `Libralib` | Marcus Sterling | Circulation desk: borrow, return, pending requests, overdue tracking. |
| **Borrower (Student)** | `student` | `Librastud` | Sophia Chen | Student borrower: browse books, request checkouts, view due dates. |
| **Borrower (Faculty)** | `faculty` | `Librafac` | Prof. Arthur Pendelton | Faculty borrower account. |
| **Borrower (Student)** | `alex` | `alex123` | Alex Rivera | Business student borrower. |

---

## 📋 Feature Breakdown

### 1. Book Management & Availability (D1)
- Add, edit, delete, and search books by **Title**, **Author**, **Category**, and **ISBN**.
- Live inventory tracking: **Total Copies** vs. **Available Copies**.
- Instant visual status badges:
  - `Available (X / Y copies)`: Green badge.
  - `0 Available (All Borrowed)`: Red badge with disabled requests.
- Filter catalog by categories (Computer Science, Software Engineering, AI & Data Science, Databases, Business, Literature) and switch between **Card Grid** and **Table View**.

### 2. Borrower Management (D2)
- Register student and faculty borrowers with auto-generated unique ID codes (`STU-YYYY-XXXX` and `EMP-YYYY-XXXX`).
- Manage contact information (email, phone, department).
- Track active and overdue loan counts per borrower.
- Toggle borrower status (`Active` / `Suspended`).

### 3. Book Borrowing & Requests (D3)
- **Direct Desk Checkout:** Select borrower, select book, customize borrow date and due date (defaults to 14 days), with automatic stock decrement.
- **Online Borrow Requests:** Borrowers can browse the catalog and submit a borrow request. Librarians can review, approve (auto-issuing the book), or decline requests.

### 4. Book Return & Restocking (D4)
- Process returns with recording of return date, condition assessment (*Good, Excellent, Light Wear, Damaged, Lost*), and notes.
- Automatically increments available book copies in real-time upon return completion.
- Dynamic fine computation for overdue books ($2.00/day default rate).

### 5. Overdue Monitoring
- Real-time scanner identifying all active borrowings where `due_date < CURRENT_DATE`.
- Displays dynamic calculation of **Days Overdue**, estimated late penalty, borrower email, and contact details.
- Quick actions: "Process Return" directly from the overdue screen or "Send Overdue Reminder" (logged to activity trail).

### 6. Borrower Portal (`my-borrows.php`)
- Borrowers can view their current active borrowings with color-coded **Due Date Countdown Badges**:
  - 🟢 Green: `Due in X days`
  - 🟡 Amber: `Due tomorrow / in 2 days`
  - 🔴 Red: `Overdue by X days!`
- View pending request approval status.
- View complete historical returned records.

### 7. Dashboard & Reports
- Key metrics: Total Books, Available Copies, Active Borrowings, Overdue Loans.
- Category circulation distribution chart.
- Most borrowed titles ranking.
- Export transactions or book inventory to CSV format.
- Print-ready clean report styling.

### 8. User Account Administration (`users.php`)
- Administrator-only screen to manage system credentials, assign roles (`Administrator`, `Librarian`, `Borrower`), link accounts to borrower profiles, and toggle active/inactive status.

---

## 🗄️ Database Structure

- **SQLite (Default):** Zero-configuration database stored in [`data/database.sqlite`](data/database.sqlite). Automatically created, migrated, and seeded on the very first run!
- **MySQL / MariaDB (`schema.sql`):** A standard [`schema.sql`](schema.sql) file is included if you prefer running it inside XAMPP MySQL/phpMyAdmin.

---

## 🚀 Running the System

### Option A: Using the PHP Built-in Server (Ready Now!)
Open a terminal in `C:\xampp\htdocs\libra` and run:
```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000
```
Open **`http://127.0.0.1:8000/`** in your web browser.

### Option B: Using XAMPP Apache
1. Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Click **Start** on Apache.
3. Open **`http://localhost/libra/`** in your browser.
