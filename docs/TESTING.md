# Testing record

Date: **23 October 2026**. Branch: **frontend-pages**.

This record is for students, teammates, and the reviewer. It shows what the automated tests found. The human review is still pending.

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
| Final changed-file syntax pass | **23 PHP/JavaScript files passed**, including PHP-bearing HTML views |
| Local guide-link check | **92 targets exist**; referenced source-line ranges stay within their files |

These are test case counts, not assertion counts. Some cases run several checks. The latest browser run used the updated safety test (unusual title only in its own case) and captured the mobile screenshot at phone size only. An earlier run took 45.8 seconds.

Tools used: **PHP 8.2.12**, **Node.js 22.23.2**, **npm 10.9.8**, **Playwright 1.58.2**. Browser checks ran on the installed Chromium headless shell. The database server version and exact browser build were not verified.

## What was checked

### PHP unit checks

Eight cases test fine calculation: negative/zero days, normal amounts, the cap, and values above it. Four cases check that amounts show two decimal places and round correctly. One case checks the error message and HTTP status.

### Isolated database/API checks

The 23 cases cover: login/logout and sessions, redirects and one-time messages, role restrictions, book search, due dates (default, selected, invalid), borrowing limits, unpaid fines, unavailable copies, returns (own, repeated, other student), late fines and the cap, all payment channels, rejected payment input, persistent receipt links, receipt privacy, and history.

Each case checks both the API response and the rows saved in the database. A declined payment stays recorded and the fine stays unpaid. A successful retry marks the fine as paid. Paying twice is rejected. The saved payment rows do not contain the test card numbers.

### Browser checks

The 18 cases use the real login form and pages on the local test server. They check: date selection and saving, return/history updates, borrowing errors and disabled controls, a title with HTML characters shown as plain text, a declined then successful payment, persistent receipt links, non-card payment channels, receipt errors, and access by another student.

Tests run on both the root URL and a `/library-demo` subfolder. Keyboard checks cover the borrowing dialog: initial focus, Tab/Shift+Tab at the edges, Escape, and focus returning to the right place. Some loading, error, and session-expiry states are faked by intercepting browser requests. These test how the frontend reacts, not a real server outage.

## Setup issue encountered and resolved

The first integration run gave 1 pass and 22 failures, mostly HTTP 500 errors. The test server could not use the Windows session-file path when set through a command-line PHP option.

The fix passes `LIBRARY_TEST_SESSION_PATH` to the test server and calls `session_save_path()` in the router before any app session starts. The rerun passed all 23 cases. This was a test-server setup issue, not a bug in the borrowing or payment logic.

## Real screenshot evidence

The screenshots below were captured by the browser tests using sample data. They are not real payments or human approval. They are project files and have not been shared externally.

| File | Caption and scenario |
| --- | --- |
| [history.png](screenshots/history.png) | Borrowing History after returning the overdue Macroeconomics fixture: Returned, with a saved unpaid fine capped at ₦5,000.00. |
| [failed-payment.png](screenshots/failed-payment.png) | A declined simulated card attempt: the record explicitly states that this attempt did not pay the fine. |
| [successful-receipt.png](screenshots/successful-receipt.png) | A successful simulated ₦700.00 Card record reopened through the paid fine's persistent receipt link. |
| [due-date-mobile.png](screenshots/due-date-mobile.png) | The catalogue borrowing dialog with a selectable return date at a 390 × 844 phone viewport. |


## Boundaries of this evidence

- Print checks replace `window.print()` with a test function and apply the print CSS. They confirm the button works and that navigation, footer, and controls are hidden. They do not test a real print dialog, printer, or PDF export.
- Phone checks compare page width on History, My Loans, and Receipt at 390 × 844. The borrowing dialog is also captured at that size. This does not cover every breakpoint, zoom level, touch interaction, or screen reader.
- The test router simulates a subfolder install. A real XAMPP/Apache setup still needs a manual check.
- The borrowing dialog has tested keyboard navigation. The payment dialog does not have the same level of focus handling. No full accessibility certification is claimed.
- Not every HTTP method, malformed JSON input, concurrent request, or production setup is covered. This is not a security audit.
- Normal cleanup was confirmed. Killing the process or shutting down the machine can skip cleanup. If the database owner does not match, deletion is refused on purpose.
- No real money moves. Card numbers are not saved in the database, but server logs or browser/proxy tools could still record requests. Never enter a real card number.
- All test traffic goes to localhost. The installer downloaded about 110.2 MiB for the headless browser and support files. Total npm transfer and session traffic were not measured. No extra browser download is needed.

## Human acceptance review — PENDING

A student or teammate must complete this section. Passing automated tests does not count as checking these boxes.

- [x] Sign in through the real XAMPP URL. Check that navigation works and the login message appears only once.
- [x] Pick a return date, borrow an available book, and check that the date is clear in My Loans and History.
- [ ] Try to borrow a book when you have an unpaid fine or when no copies are available. Check that the error message tells you what to do.
    - Sure, you can't borrow when you have an unpaid saved fine, but it's a different case when you have an unpaid accruing fine for not returning the book yet. It am still delibrating at what point a user should not be allowed to borrow again for accruing fines on unreturned books. I may not decide in time, since this is just a test project, but this is probably one of the limitations of the system. 
- [x] Check the Borrowed, Overdue, and Returned rows in History. Confirm the difference between a saved fine and an estimate for a book still out.
- [x] Using sample data only, try a declined and a successful payment. Check that the warning makes it clear no real money moved.
- [x] Reload My Loans and reopen a successful payment. Check that the earlier failed attempt still shows as Failed.
- [x] Open the real print preview. Check that details are readable, page breaks look right, and the navigation, footer, and print button are hidden.
- [x] Use a phone or narrow browser window and the keyboard. Check scrolling, the date picker, all payment controls, focus order, and text readability.
- [x] Confirm the pages, explanations, and evidence meet the assignment requirements. Note any remaining issues.

Reviewer: **Israel Olawuyi**. Review date: **24th October 2026**. Outcome/issues: **System can be considered usable**.

## Learning and workflow references

- [Beginner test walkthrough](../tests/README.md): setup, action, expected result, cleanup, with every file and case explained.
- [Implementation guide](IMPLEMENTATION_GUIDE.md): each changed line/block and how it connects to the PHP, API, and database logic.
- [Simulated payment workflow](PAYMENT_WORKFLOW.md): payment attempts, fine status, privacy, and reopening records.
- [Current status](STATUS.md): what is done and what still needs human review.
