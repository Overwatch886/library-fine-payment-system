# Status

Updated: 2026-10-18
Branch: `login-implementation` (from `main` at `bc056d9`)

## Done

- Backend, database and API merged into `main` (PR merged).
- `login-implementation` created from the updated `main`.

## Next

- Wire `login.php` to the `Auth` class (Step 2 in the plan).
- Test: wrong password, student `221345`, administrator `bakinyemi`.
- Commit and push on `login-implementation`.

## Notes

- `login.php`, `views/` and `assets/` belong to the frontend team.
- PHP is on PATH: run `php -S localhost:8000` from the project root and open
  `http://localhost:8000/login.php`. Lint changed files with `php -l <file>`.
- The project is not linked into `C:\xampp\htdocs`; the junction is only needed for the
  Apache demo URL.
- Update this file when a task finishes. Keep it under 15 lines.
