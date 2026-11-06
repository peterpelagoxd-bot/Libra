# Library Book Borrowing System

## System Description

A digital library system that manages books, borrowers, borrowing
transactions, and returns.

### User Roles

-   Administrator
-   Librarian
-   Borrower

## Main Features

1.  **Book Management** --- Add, edit, delete, and search books by
    title, author, category, and ISBN.
2.  **Book Availability** --- Track available and borrowed copies of
    each book.
3.  **Borrower Management** --- Register and manage student or employee
    borrowers.
4.  **Book Borrowing** --- Record borrowing transactions, borrowing
    dates, and due dates.
5.  **Book Return** --- Process returned books and automatically update
    available quantities.
6.  **Overdue Monitoring** --- Identify books that have not been
    returned by their due dates.
7.  **Borrowing History** --- View borrowing and return records for each
    user.
8.  **Library Dashboard** --- Display total books, available books,
    borrowed books, and overdue transactions.

## System Flowchart

``` mermaid
flowchart TD
    A([Start]) --> B[Open Library Book Borrowing System]
    B --> C[Login]
    C --> D{Validate Login}
    D -- Invalid --> E[Display Error Message]
    E --> C
    D -- Valid --> F{Identify User Role}

    F -- Administrator --> G[Manage User Accounts]
    G --> H[Manage Books and Categories]
    H --> I[View Dashboard and Reports]

    F -- Librarian --> J[Manage Book Records]
    J --> K[Manage Borrower Records]
    K --> L[Check Book Availability]
    L --> M[Process Book Borrowing]
    M --> N[Record Borrowing Date and Due Date]
    N --> O[Update Available Book Copies]
    O --> P[Process Book Return]
    P --> Q[Update Book Copies and Return Record]
    Q --> R[Monitor Overdue Books]

    F -- Borrower --> S[Search and View Books]
    S --> T{Book Available?}
    T -- No --> U[Display Unavailable Status]
    U --> S
    T -- Yes --> V[Request to Borrow Book]
    V --> W[View Borrowing History and Due Date]

    I --> X[Generate Dashboard and Reports]
    R --> X
    W --> Y[Logout]
    X --> Y
    Y --> Z([End])
```

## Main System Flow

### 1. Login and User Authentication

The system verifies the login credentials and identifies whether the
user is an Administrator, Librarian, or Borrower. Invalid credentials
return the user to the login screen.

### 2. Book and Borrower Management

The Administrator or Librarian manages book details, categories, ISBNs,
quantities, and borrower information.

### 3. Book Borrowing

The Borrower searches for a book and submits a request. The Librarian
records the transaction and due date, and the system updates the
available copies.

### 4. Book Return and Overdue Monitoring

The Librarian records returned books. The system updates book
quantities, saves the return history, and identifies overdue
transactions.

### 5. Dashboard and Reports

The system displays total books, available copies, borrowed books,
overdue transactions, and borrowing history.

## Main Database Records

-   **D1 --- Books Database:** Book titles, authors, categories, ISBNs,
    and quantities.
-   **D2 --- Borrowers Database:** Student or employee details and
    account information.
-   **D3 --- Borrowing Transactions:** Borrowing dates, due dates, and
    transaction status.
-   **D4 --- Return Records:** Return dates and return transaction
    details.
-   **D5 --- User Accounts:** Login credentials and user roles.
