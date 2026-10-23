# Testing record

Date: **23 October 2026**. Branch: **frontend-pages**.

This record is for students, teammates and the assignment reviewer. It separates observed automated results from the human acceptance review, which is still pending.

## Verified local runs

| Command | Observed result |
| --- | --- |
| `php tests/unit.php` | **13 passed, 0 failed** |
| `php tests/run.php integration` | **23 passed, 0 failed**; temporary test database/server removed |
| `php tests/run.php browser` | **18 passed** in **25.7 seconds** on the latest run; temporary test database/server removed |
| `php -l tests/run.php` | No syntax errors |
| `php -l tests/support/TestEnvironment.php` | No syntax errors |
| `php -l tests/support/browser-fixture.php` | No syntax errors |
| `node --check tests/browser/library.spec.js` | Passed |
| `node --check assets/js/my_loans.js` | Passed |
| `git diff --check` | Passed at the verification checkpoint |

These are named test cases, not the total number of assertions. Several cases perform multiple comparisons. The latest browser run followed the change that confined the unusual book title to its safety case and made the mobile screenshot capture only the phone viewport. An earlier successful browser run took 45.8 seconds.

Verified tools: **PHP 8.2.12**, **Node.js 22.23.2**, **npm 10.9.8**, **Playwright 1.58.2**. Browser checks used the installed Chromium headless shell. No claim is made here about an unverified database-server version or exact browser build.

## What was checked

### PHP unit checks

Eight fine-calculation cases cover negative/zero days, normal daily amounts, the cap boundary and values above it. Four amount-formatting cases check two decimal places and rounding. One application-error case checks its message and HTTP status.

### Isolated database/API checks

The 23 cases exercise login/logout and session behaviour, nested redirects and one-time flash, role restrictions, book search, default/selected/invalid due dates, borrowing limits, unpaid fines and unavailable copies, own/repeated/other-student returns, late fine creation and the cap, all simulated channels, rejected payment input, persistent successful references, receipt privacy and history.

Assertions check actual saved rows as well as API responses. A declined simulated payment remains recorded while its fine stays unpaid; a successful retry marks the fine paid. Repeated payment is rejected. The stored payment rows do not contain the simulated card strings used in the check.

### Browser checks

The 18 cases use the real login form and interactive pages on the isolated local PHP server. They check date selection and persistence, return/history updates, borrowing errors and pending controls, literal rendering of a quoted HTML-looking title, decline then success, persistent receipt links, non-card channels, receipt errors and another student's access.

Both root URLs and an emulated `/library-demo` project prefix are exercised. Keyboard checks cover the borrowing dialog's initial focus, boundary Tab/Shift+Tab, Escape and restored focus. Some loading/error/session-expiry states are deliberately supplied through intercepted browser requests; those test the frontend response, not an actual backend outage.

## Setup issue encountered and resolved

The first integration run produced **1 pass and 22 failures**, mostly HTTP 500 responses. The isolated server could not use the intended Windows session-file path when it was passed through a command-line PHP setting.

The fix passes `LIBRARY_TEST_SESSION_PATH` to the owned server and calls `session_save_path()` in its router before app sessions start. The integration rerun passed all 23 cases. This was a test-server setup problem; it did not require changing the app's borrowing or payment rules.

## Real screenshot evidence

The files below were captured by the successful browser suite from generated sample data. They are not fabricated records, real payments or human acceptance approval. They remain project files; they have not been published externally.

| File | Caption and scenario |
| --- | --- |
| [history.png](screenshots/history.png) | Borrowing History after returning the overdue Macroeconomics fixture: Returned, with a saved unpaid fine capped at ₦5,000.00. |
| [failed-payment.png](screenshots/failed-payment.png) | A declined simulated card attempt: the record explicitly states that this attempt did not pay the fine. |
| [successful-receipt.png](screenshots/successful-receipt.png) | A successful simulated ₦700.00 Card record reopened through the paid fine's persistent receipt link. |
| [due-date-mobile.png](screenshots/due-date-mobile.png) | The catalogue borrowing dialog with a selectable return date at a 390 × 844 phone viewport. |

Root/subfolder payment cases use the same screenshot names; the later subfolder case replaces those two files. The unusual title fixture is used only in its safety case, not in ordinary payment evidence. Screenshots are selected explicitly; automatic screenshots, traces and videos are disabled.

## Boundaries of this evidence

- Print checks replace `window.print()` with a test function and apply print CSS. They verify the button wiring and hidden navigation/footer/control, **not** an OS print dialog, printer or exported PDF.
- Phone checks compare document width on history, My Loans and receipt at 390 × 844. The borrowing dialog is captured at that size. This is not approval of every breakpoint, zoom level, touch interaction or assistive tool.
- The router emulates a project subfolder. Actual XAMPP/Apache path configuration still needs a manual check.
- The borrowing dialog has the tested keyboard boundaries; the payment dialog does not have the same complete focus handling. No full accessibility certification is claimed.
- Not every HTTP method, raw malformed JSON, concurrency scenario or production deployment is covered. This is not an exhaustive security audit.
- Normal cleanup was confirmed. Forced process termination or machine shutdown can prevent cleanup. Ownership mismatches intentionally refuse database deletion.
- No real money moves. Payment inserts exclude card digits, but externally configured logging or browser/proxy tools could record requests. Never use real card information.
- Normal test traffic targets localhost. The installer reported about 110.2 MiB for the headless shell/support files; npm transfer and total device/session traffic were not comprehensively measured. No additional browser download is needed for the installed setup.

## Human acceptance review — PENDING

A student or teammate must complete this section. Automated passes do not tick these boxes on their behalf.

- [ ] Sign in through the actual XAMPP project URL; confirm navigation and one-time login messages.
- [ ] Choose a return date, borrow an available book and confirm the saved date is understandable in My Loans and History.
- [ ] Read an unpaid-fine or unavailable-book rejection; confirm the message explains what to do.
- [ ] Review Borrowed, Overdue and Returned history rows and the distinction between saved fines and estimates for books still out.
- [ ] Using sample data only, try a declined and successful test payment; confirm the warning makes clear that no real money moved.
- [ ] Reload My Loans and reopen a successful record; confirm the failed attempt's status remains Failed after a successful retry.
- [ ] Open real print preview; check readable details, page breaks and absence of navigation/footer/print button.
- [ ] Use a phone or narrow browser and keyboard; review scrolling, date control, all payment controls, focus and text readability.
- [ ] Confirm these pages, explanations and evidence meet the assignment's requirements; record any remaining issues.

Reviewer: **not yet recorded**. Review date: **not yet recorded**. Outcome/issues: **pending**.

## Learning and workflow references

- [Beginner test walkthrough](../tests/README.md): setup → action → expected result → cleanup, with every file and case explained.
- [Implementation guide](IMPLEMENTATION_GUIDE.md): each changed feature line/block and its connection to PHP, API and database logic.
- [Simulated payment workflow](PAYMENT_WORKFLOW.md): attempts, fine status, privacy and reopening records.
- [Current status](STATUS.md): completed work and remaining human review.
