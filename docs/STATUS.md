# Status

Updated: 2026-10-19 | Branch: `login-implementation`

## Done
- Backend, database, API merged into `main`.
- `login.php` + `admin_dashboard.php` working; admin guarded with `requireLibrarian()`.
- MySQL running; `schema.sql` + `seed.sql` imported successfully.
- `views/student/catalogue_view.html` boilerplate built (search form, category filter, results container).

## Next
- Style the catalogue view in `assets/css/style.css`.
- Build `assets/js/catalogue.js` fetch() logic (load categories, search, render results, borrow).

## Test logins
- Student: `221345` / `Password123` | Librarian: `bakinyemi` / `Password123`

## Notes
- Frontend files: `login.php`, `views/`, `assets/`. Run `php -S localhost:8000` to test.
