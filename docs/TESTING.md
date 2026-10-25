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
- Fine calculation: on-time (0), early (negative), normal (1, 7, 49 days), cap boundary (50, 51, 1000 days → ₦5,000).
- Amount formatting: whole numbers, rounding (12.345 → "12.35"), capped value.
- `AppException`: message and HTTP status are preserved.

**Integration (23 tests)**
- Authentication: valid/invalid login, profile, logout, session persistence.
- Role enforcement: student APIs reject librarians (403); librarian pages reject students.
- Search: unauthenticated (401), authenticated results with copy counts.
- Borrowing: default 14-day due date, 1-day minimum, 14-day maximum, invalid dates (today, past, 15+ days, non-date strings), max 3 active loans, unpaid-fine block, unavailable copy.
- Returning: other student's loan (404), own on-time return, repeat return (409), overdue returns with saved fine (₦600 and capped ₦5,000).
- Payments: declined card (402, fine stays unpaid), successful retry (201, fine marked paid), bank transfer, USSD, invalid channel/input, other student's fine (404), card digits never stored.
- Receipts: anonymous (401), wrong owner (404), missing reference (400), nonexistent (404), owner and librarian (200).
- History: rows belong to the logged-in student, status updates after return, paid fine visible.
- Access control: public assets (200), private files (403).

**Browser (22 tests)**
- Login and navigation (root and nested `/library-demo` prefix).
- Incorrect login shows error without redirect.
- Borrow modal: due-date min/max/default, keyboard focus, Escape cancel, calendar helpers (leap year, month/year change, Lagos timezone).
- Borrow flow: correct book ID and date sent via fetch, DB updated, history shows "Borrowed," return clears it.
- Client-side validation: today's date rejected before any API call.
- Unpaid-fine rejection and simulated network failure with retry.
- Duplicate-submit guard: confirm disabled, modal locked, extra form event ignored.
- History page states: loading, empty, API error, network error, login redirect.
- Overdue return: capped fine (₦5,000) shown, history row shows "Unpaid."
- HTML safety: unusual book title rendered literally, no injected markup.
- Payment: declined card (field cleared, failed record shown), successful payment (receipt link, fine shows "Paid"), persistent receipt after reload, print CSS hides chrome.
- Bank transfer and USSD: no card field, success, receipt.
- Receipt edge cases: no reference, missing value, network abort.
- Session isolation: another student cannot open the same receipt URL.
- Mobile (390 × 844): no horizontal overflow, modal and receipt render.

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
