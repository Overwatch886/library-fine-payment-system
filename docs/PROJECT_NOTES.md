 # Project notes

University of Ibadan (Kenneth Dike Library) book borrowing and fine payment system.

Stack: HTML, CSS, JavaScript, PHP (PDO) and MySQL/MariaDB, running on XAMPP for Windows.

## Who owns what

- **Frontend team:** `assets/`, `views/`, `login.php`
- **Backend team:** `config/`, `classes/`, `api/`, `database/`

Please don't edit the other team's files. If you need something changed, ask the team that owns it.

## Folder layout

```
assets/            CSS and JavaScript (frontend)
views/             HTML views (frontend)
login.php          login page entry point (frontend)
config/
  config.php       database settings and rule numbers (fine rate/cap, loan days, max loans)
  init.php         include this to load the config and all classes
classes/           one class per file: Database, Auth, Book, Loan, Fine, Payment,
                   Student, Librarian, AppException
api/
  bootstrap.php    shared JSON response and error handling, used by every endpoint
  auth/ books/ loans/ fines/ payments/ admin/   the JSON endpoints
  README.md        endpoint reference for the frontend
database/
  schema.sql       drops and recreates the ui_library database
  seed.sql         test data
  README.md        import steps, test logins, what each table is for
docs/
  PROJECT_NOTES.md this file
```

`config/`, `classes/` and `database/` each have an `.htaccess` that blocks direct access from the browser.

## Running it locally

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Import the database: see [database/README.md](../database/README.md).
3. Make the project folder visible to Apache. Either clone it into `C:\xampp\htdocs\`, or keep it where
   it is and link it in (run in an admin Command Prompt, with your own path):
   ```
   mklink /J "C:\xampp\htdocs\library-fine-payment-system" "C:\path\to\library-fine-payment-system"
   ```
4. The API is then at `http://localhost/library-fine-payment-system/api/...`.
   See [api/README.md](../api/README.md) for every endpoint.

## Backend conventions

- One PHP class per file in `classes/`. Every class gets the shared PDO connection from `Database`
  (exceptions on errors, `FETCH_ASSOC`, `utf8mb4`).
- **Prepared statements only.** Never put request data directly into SQL.
- Passwords use `password_hash()` / `password_verify()` (bcrypt).
- Endpoints return JSON and are called from the frontend with `fetch()`. Every response looks like
  `{"success": bool, "message": string, "data": ...}` with a matching HTTP status:
  400 bad input, 401 not logged in, 402 payment declined, 403 wrong role, 404 not found, 409 rule broken.
- Expected errors are thrown as `AppException($message, $httpStatus)`; `api/bootstrap.php` turns them
  into JSON. Anything else becomes a generic 500 and goes to the PHP error log.
- Keep endpoints short: check the method and role, read the input, call one class method, `respond()`.
- Database: snake_case names, InnoDB, utf8mb4. All foreign keys are `ON DELETE RESTRICT`.
- Borrowing, returning and paying change several tables, so each runs inside a transaction.

## Business rules

### Loans
- When borrowing, the student picks a due date **1–14 days** from today (default 14).
- A student can have at most **3 books out** at once (`return_date IS NULL`).
- A student with an **unpaid fine** cannot borrow.
- Borrowing sets the copy's `book_copies.status` to `borrowed`; returning sets it back to `available`.
- **"Overdue" is worked out, never stored:** `return_date IS NULL AND due_date < CURDATE()`.
  `loans.status` is only `borrowed` or `returned`. Use the `v_loan_details` view to get overdue info.

### Fines
- ₦100 per day late, capped at ₦5,000.
- A fine is created **when a late book is returned**, one per loan (`fines.loan_id` is unique).
- While a book is still out, the fine so far is calculated on the fly (days overdue × 100, capped), not saved.

### Payments (simulated)
- Card numbers ending in `0000` are declined; any other card succeeds. `bank_transfer` and `ussd` always succeed.
- Card numbers are never saved.
- Every attempt is saved in `payments`, failed ones too (`status = 'failed'` with a `failure_reason`).
- A fine is marked `paid` (with `paid_at`) only after a **successful payment of the full amount**.
