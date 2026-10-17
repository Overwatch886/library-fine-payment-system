# Database

MySQL/MariaDB database `ui_library` for the UI library borrowing + fine payment system.

| File | Purpose |
|------|---------|
| `schema.sql` | **Drops** and recreates the `ui_library` database (tables + views). |
| `seed.sql`   | Sample librarians, students, books, copies, loans, fines and payments. |

> Re-running `schema.sql` deletes all data in `ui_library`. Always run `seed.sql` after it.

## Import

Start **Apache** and **MySQL** in the XAMPP Control Panel first.

### phpMyAdmin

1. Open <http://localhost/phpmyadmin>.
2. Click the **Import** tab at the top **without selecting a database** (server level).
3. Choose `database/schema.sql` → **Import**.
4. Click **Import** again, choose `database/seed.sql` → **Import**.
5. `ui_library` should now appear in the left sidebar.

### Command line (PowerShell, from the repo root)

XAMPP's default root user has no password; add `-p` if you set one.

```powershell
Get-Content database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root
Get-Content database\seed.sql   | C:\xampp\mysql\bin\mysql.exe -u root
```

Or with cmd.exe: `C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql` (then the same for `seed.sql`).

## Test logins

Password for **every** account: `Password123`

The login form uses the **matric number** for students and the **username** for administrators (librarians).

| Role | Login | Name | Seeded situation |
|------|-------|------|------------------|
| Librarian | `bakinyemi` | Bolanle Akinyemi | — |
| Librarian | `enwosu` | Emeka Nwosu | — |
| Student | `221345` | Oluwaseun Adebayo | 2 active loans, on time |
| Student | `232118` | Chiamaka Okafor | 2 active **overdue** loans (6 days; 56 days → fine so far capped at ₦5,000) |
| Student | `214567` | Ibrahim Musa | 1 loan returned on time |
| Student | `220981` | Funmilayo Adeyemi | **Unpaid ₦700 fine** (7 days late) + 1 failed card payment → blocked from borrowing |
| Student | `205643` | Babatunde Bakare | ₦500 fine **paid** (successful card payment) |
| Student | `245210` | Ngozi Eze | 1 active loan, on time |

Students also have a `username` column (e.g. `oadebayo`) if the backend ever needs it.

"Half of a Yellow Sun" (book 6) has **all copies borrowed** — use it to test the "no copies available" case.
To test a failed payment, use any card number ending in `0000`.

## Tables

- **students** — registered students; matric number is the primary key.
- **librarians** — library staff who log in as administrators.
- **books** — catalogue entries (one row per title / ISBN).
- **book_copies** — physical copies of each book and whether each is available or borrowed.
- **loans** — one row per borrowing of a copy; `return_date` is NULL while the book is out.
- **fines** — one fine per late-returned loan (₦100/day, max ₦5,000), unpaid or paid.
- **payments** — every payment attempt on a fine, successful or failed.

### Views

- **v_book_availability** — each book with `total_copies` and `available_copies`.
- **v_loan_details** — loans with student name, book title, and computed `is_overdue` / `days_overdue`.

"Overdue" is never stored: it is `return_date IS NULL AND due_date < CURDATE()`.
