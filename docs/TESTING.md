# Testing

## Approach

The system is verified at three automated layers plus a manual acceptance pass:

| Layer | Count | What it checks |
|---|---|---|
| Unit | 13 | Class logic in isolation (fine calculation, amount formatting, exception handling) |
| Integration | 23 | API endpoints against a real MySQL database (auth, search, borrow, return, fines, payments, receipts, history, access control) |
| Browser (E2E) | 22 | Full user flows in headless Chromium: login, catalogue, borrow modal, due-date picker, return, payment, receipt, mobile width, print CSS |
| Manual acceptance | — | Real XAMPP URLs, phone/keyboard usability, print preview |

All automated tests run against an isolated, auto-generated database (`ui_library_test_*`). They never touch the application database. The launcher creates the database, starts a local PHP server, runs the suite, and cleans up.

## What each layer covers

**Unit (13 tests)**

These tests call class methods directly with known inputs and assert the exact output, without any database or HTTP layer involved.

- **Fine calculation (8 cases):** Verifies that `Fine::calculate()` returns 0 for on-time or early returns, multiplies days late by ₦100 for normal ranges (1, 7, and 49 days), and caps the result at ₦5,000 once the late period reaches 50 days or more (tested at 50, 51, and 1000 days to confirm the cap holds well past the boundary).
- **Amount formatting (4 cases):** Confirms that `Fine::formatAmount()` always produces a string with two decimal places, rounds correctly when a third decimal exists (12.345 becomes "12.35" rather than truncating), and formats the capped ₦5,000 value consistently.
- **AppException (1 case):** Creates an `AppException` with a known message and HTTP status, then asserts both values are preserved on the object so the API bootstrap can later convert them into the correct JSON response.

**Integration (23 tests)**

Each test sends real HTTP requests through a cURL client to the local PHP server, then inspects both the JSON response and the database rows to confirm the business rules were applied correctly.

- **Authentication:** Verifies that a wrong password returns 401 with no session, a correct login returns the student's profile (matric and role), logout destroys the session so a subsequent profile request is rejected, and the session cookie persists across multiple requests within one client.
- **Role enforcement:** Confirms that a librarian calling student-only API endpoints receives a 403, and that a student opening the librarian dashboard page is redirected away. This ensures the role check is enforced at the API layer, not just hidden in the UI.
- **Search:** Checks that an unauthenticated search request is rejected with 401, and that an authenticated search for a known title returns the correct book with its available copy count read from the database.
- **Borrowing (9 cases):** Tests the full range of due-date validation (default 14 days when omitted, 1-day minimum, 14-day maximum, and rejection of today, past dates, 15+ days, and non-date strings with 400), the 3-active-loan limit (the fourth attempt returns 409), the unpaid-fine block (a student with an outstanding fine cannot borrow), and the unavailable-copy rule (a book with zero available copies cannot be borrowed).
- **Returning (3 cases):** Verifies that a student cannot return another student's loan (404), that an on-time return marks the loan as returned and the copy as available with no fine, that a repeat return is rejected with 409, and that overdue returns create a fine row with the correct amount (₦600 for 6 days, ₦5,000 for the capped case).
- **Payments (5 cases):** Confirms that a card ending in 0000 is declined with 402 and the fine remains unpaid, that a retry with a valid card succeeds (201) and marks the fine as paid, that bank transfer and USSD channels both succeed without requiring a card number, that invalid channels or inputs return 400, that another student's fine cannot be paid (404), and that no card digits are stored in the payments table.
- **Receipts (1 case, 6 assertions):** Requests the same receipt reference as an anonymous user (401), as another student (404), with a missing reference parameter (400), with a nonexistent reference (404), as the fine's owner (200 with full details), and as a librarian (200). This proves the receipt is accessible only to the right people.
- **History:** Fetches the logged-in student's borrowing history and asserts every row belongs to that student, that a returned loan shows the updated status, and that a paid fine displays its saved amount and "paid" status.
- **Access control:** Requests public CSS/JS assets (expecting 200) and private files such as config, classes, and views (expecting 403) to confirm the test router blocks direct web access to server-side code.

**Browser (22 tests)**

These tests drive a real headless Chromium browser through the actual pages, clicking buttons, filling forms, and asserting on rendered DOM content. They verify the full user experience, not just the API responses.

- **Login and navigation (2 cases):** Opens the login page at both the root URL and a nested `/library-demo` prefix, signs in, follows links to history and catalogue, and confirms the login flash message appears exactly once. This ensures the app works whether deployed at the domain root or in a subfolder.
- **Incorrect login:** Submits a wrong password and asserts the error message appears on the page without any redirect, so the user can correct their input.
- **Borrow modal and date picker:** Opens the borrow dialog and verifies the date input's minimum is tomorrow, maximum is 14 days out, and default is 14 days. Checks that Tab and Shift+Tab cycle focus within the modal, Escape closes it and restores focus to the trigger button, and the calendar helper functions handle leap-year February, month/year navigation, and Lagos timezone conversion correctly.
- **Borrow flow end-to-end:** Selects a book, picks a date, clicks confirm, and asserts the fetch request carries the correct book ID and date. Then queries the database to confirm the loan row exists and the copy is marked borrowed, navigates to My Loans to see "Borrowed," returns the book, and confirms the active list is empty and the history shows "Returned."
- **Client-side date validation:** Fills today's date (below the minimum) and clicks confirm. Asserts the browser's native validity check fires, no API request is sent, and the modal stays open with an inline error. This confirms the frontend catches invalid input before it reaches the server.
- **Unpaid-fine rejection and network failure:** Submits a borrow for a student with an outstanding fine and asserts the backend's 409 error message is displayed. Then intercepts the borrow route to simulate a network abort, confirms the retry message appears, the confirm button is re-enabled, and the chosen date is preserved.
- **Duplicate-submit guard:** Starts a borrow, intercepts the request so it never resolves, dispatches a second form-submit event, and asserts the confirm button is disabled, the modal cannot be closed, and only one request was sent. This prevents double-borrowing from rapid clicks.
- **History page states:** Uses route interception to simulate loading (paused response), empty data, an API error (500 JSON), a network abort, and a 401 redirect. Asserts each state renders the correct placeholder or error message in the DOM.
- **Overdue return with fine:** Logs in as a student with two overdue loans, returns one, and asserts the capped fine (₦5,000) is displayed in the UI and the history row shows "Unpaid." This is a real database mutation, not a mocked response.
- **HTML safety:** Uses a fixture to set a book title containing HTML markup characters, then asserts the title renders as literal text in the history, payment modal, and receipt. Confirms no injected `<img>` or script element appears and a sentinel global property is undefined.
- **Payment flow (2 cases, root and nested prefix):** Submits a card ending in 0000, asserts the field is cleared and a failed record is shown with a screenshot. Then submits a valid card, asserts the success message, receipt link, and "Paid" status appear. Reloads the page and confirms the receipt link is still accessible. Stubs `window.print`, clicks the print button, and emulates print media to verify the print CSS hides the header, footer, and action buttons while keeping receipt details visible.
- **Bank transfer and USSD (2 cases):** Selects each non-card channel, asserts the card input field is hidden, confirms the payment succeeds, and verifies the receipt link and "Paid" status appear.
- **Receipt edge cases:** Opens the receipt page with no reference parameter, with a reference that has no matching record, and with a network abort on reload. Asserts each shows a helpful message and the Print button remains hidden.
- **Session isolation:** Opens a paid receipt as the owner, saves the URL, creates a second browser context (separate cookies), logs in as a different student, and opens the same URL. Asserts the second student sees "not found" and no Print button, proving the receipt is not accessible across sessions.
- **Mobile viewport (390 × 844):** Resizes the browser to a phone width, opens the borrow modal and saves a viewport screenshot, then navigates to history and a paid receipt. Asserts no horizontal overflow exists on any page and the receipt details are visible at that width.

## Successful and failed operations

The guidelines require demonstrating both outcomes. The automated suite covers:

| Operation | Success | Failure |
|---|---|---|
| Login | Correct credentials → redirect to catalogue | Wrong password → error message, stays on page |
| Borrow | Valid book + date → loan created, copy marked borrowed | Unpaid fine / max loans / invalid date → 409 or 400, no loan |
| Return | On-time → no fine; overdue → fine saved | Other student's loan → 404; already returned → 409 |
| Payment (card) | Card ending ≠ 0000 → fine paid, receipt issued | Card ending 0000 → 402, fine stays unpaid, failed record saved |
| Payment (transfer/USSD) | Always succeeds → fine paid | N/A (simulated) |
| Receipt | Owner or librarian → full details | Anonymous / wrong student / missing ref → 401 / 404 / 400 |

## Evidence

- Four screenshots in `docs/screenshots/`: catalogue, borrow modal, successful payment receipt, failed payment record.
- Full test source in `tests/` (unit, integration, browser).
- Run commands and line-by-line walkthroughs in `tests/README.md`.
- Test results and manual acceptance log in this file's "Results" section below.

## Results

| Suite | Passed | Failed |
|---|---|---|
| Unit | 13 | 0 |
| Integration | 23 | 0 |
| Browser | 22 | 0 |

Cleanup confirmed: generated database dropped, server stopped, temp files removed.

## Manual acceptance checklist

Complete on a real XAMPP setup (not the test server):

- [ ] Student login via `http://localhost/library-fine-payment-system/login.php`
- [ ] Catalogue loads books asynchronously (no page reload)
- [ ] Search filters results without reload
- [ ] Borrow modal: due date defaults to 14 days, min is tomorrow
- [ ] Borrow a book → appears in "My Loans" as Borrowed
- [ ] Return a book (librarian) → status changes to Returned
- [ ] Overdue return → fine appears with correct amount
- [ ] Pay fine with simulated card (non-0000) → receipt shows
- [ ] Pay fine with card ending 0000 → declined, fine stays unpaid
- [ ] Open receipt link after reload → still accessible
- [ ] Librarian dashboard: view all loans, confirm returns
- [ ] Mobile width (phone): no horizontal scroll, modal usable
- [ ] Print preview: receipt prints cleanly, header/footer hidden
- [ ] Keyboard: Tab/Shift+Tab through borrow modal, Escape closes

## How to re-run

```sh
# Unit (no database needed)
php tests/unit.php

# Integration (local MySQL running)
php tests/run.php integration

# Browser (local MySQL running, Playwright installed)
php tests/run.php browser
```

See `tests/README.md` for prerequisites, safety notes, and troubleshooting.
