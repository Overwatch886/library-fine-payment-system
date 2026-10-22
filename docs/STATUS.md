# Status
Updated: 2026-10-22 | Branch: `frontend-pages`

## Done
- Backend/DB/API complete; live-tested auth, search, borrow, return, fines, pay, receipts, admin endpoints.
- Student and librarian pages work; role guards and redirects verified.
- Login flash fix implemented: session starts before reading the message; PHP lint passed.

## Next
- Admin table: show real fine amounts for returned loans and escape data before `innerHTML`.
- Add Borrowing History and Payment Receipt pages (`loans/history.php`, `payments/receipt.php` already exist).
- Add a due-date picker to the borrow modal (API already accepts `due_date`, 1-14 days).
- Block direct web access to `views/`;
- Write-up: Payment flow, screenshots, testing log.
