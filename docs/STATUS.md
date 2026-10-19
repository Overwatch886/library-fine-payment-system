# Status

Updated: 2026-10-19
Branch: `login-implementation` (from `main` at `bc056d9`)

## Done

- Backend, database and API merged into `main` (PR merged).
- `login.php` authenticates through `Auth`: wrong credentials re-render the form with
  `$errorMessage`; students redirect to `catalogue.php`, librarians to `admin_dashboard.php`.
- `admin_dashboard.php` controller added; login commits landed (`4beb5bf`).

## Next

- Start MySQL, import `schema.sql` + `seed.sql`, then test the login cases below.
- Build the catalogue `fetch()` in `assets/js/catalogue.js` (Step 3 in the plan).
- Guard `admin_dashboard.php` with `Auth::requireLibrarian()`.

## Test logins

- Student: `221345` / `Password123`
- Librarian: `bakinyemi` / `Password123`

## Notes

- `login.php`, `views/` and `assets/` belong to the frontend team.
- PHP is on PATH: run `php -S localhost:8000` from the project root and open
  `http://localhost:8000/login.php`. Lint changed files with `php -l <file>`.
- The project is not linked into `C:\xampp\htdocs`; the junction is only needed for the
  Apache demo URL.
- Update this file when a task finishes. Keep it under 25 lines.
