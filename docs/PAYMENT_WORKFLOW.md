# Simulated payment workflow

Written for students, teammates and the assignment reviewer. This describes the implemented test-payment system, not a live payment service.

## What the student does

1. Open **My Loans** and look under saved fines. An estimate for a book still out is not yet a payable saved fine: return the book first.
2. Select **Pay** beside an unpaid fine. The dialog shows that fine's book and full amount.
3. Select Card, Bank Transfer or USSD. Use only the documented development/test inputs; never enter a real card number.
4. Select **Pay**. Pay and Cancel are disabled while the request is pending.
5. Read the result. A success offers **View Receipt**; a decline offers **View Failed Attempt**.
6. After a success, the paid fine also keeps a **View Receipt** link. Reloading the page does not remove it.
7. Open the record and optionally select **Print Receipt**. The record explicitly says no real money was transferred.

## Decisions in this simulation

| Input or state | Result | Saved fine |
| --- | --- | --- |
| Card input has 12–19 digits after spaces/hyphens are removed, and ends in `0000` | Declined; failed attempt is recorded | Remains unpaid |
| Other input that passes that card-format check | Successful attempt is recorded | Becomes paid |
| Bank Transfer or USSD | Successful attempt is recorded; no card input needed | Becomes paid |
| Unsupported channel or invalid card format | Input rejected | Unchanged; no attempt created |
| Fine belongs to another student or does not exist | Not found | Unchanged |
| Fine is already paid | Repeated payment rejected | Remains paid; no additional attempt |

These are deterministic classroom rules. The code does not contact a bank, verify card ownership, implement Luhn validation or move money. Selecting Bank Transfer/USSD does not initiate an actual transfer.

## How the parts connect

### 1. The page prepares the request

[my_loans.js](../assets/js/my_loans.js) keeps the selected fine ID in `pendingFineId`. The book name is read from the displayed `.fine-book` text, not placed in a quoted HTML attribute. This preserves unusual titles without turning them into HTML.

The request body contains `fine_id` and `channel`, plus `card_number` for Card. It deliberately does **not** accept an amount from the student: the backend reads the amount saved on the fine.

`fetch()` sends JSON to [api/payments/pay.php](../api/payments/pay.php). The browser's same-origin session cookie identifies the signed-in student. Hiding the Pay button is not the access check; the server checks ownership too.

### 2. The API checks method, session and input

The endpoint requires POST, calls the student authentication guard, reads the input, and passes it to [Payment::pay()](../classes/Payment.php). The shared [bootstrap](../api/bootstrap.php) handles input helpers, JSON responses and expected errors.

The response envelope is `success`, `message`, `data`. A declined simulated payment returns HTTP **402** with `success: false` and its attempt record. A success returns **201** with `success: true`. Invalid input, wrong role, missing records and repeated payments use their separate error statuses; they are not simulated bank declines.

### 3. The class changes the database together

`Payment::pay()` validates the channel/card format, then starts a **transaction**: the attempt and fine update either finish together or are rolled back together.

It selects the fine with `FOR UPDATE`. This temporarily locks the row so simultaneous requests cannot both mark the same fine paid. It checks that the fine belongs to the student and is still unpaid.

The simulated decision chooses `failed` or `successful`. A generated reference identifies the attempt. A prepared INSERT records:

- fine ID and student matric number;
- the full stored fine amount;
- payment channel and attempt status;
- reference, failure reason if any, and attempt timestamp (`time_paid`).

**Card digits are not included in that INSERT or saved to the payments table.** For a success only, a prepared UPDATE sets `fines.status = 'paid'` and `paid_at`. A decline still commits its attempt but does not change the fine to paid. Exceptions roll back the transaction.

After committing, the class retrieves the record by its reference. Prepared statements keep request values separate from SQL instructions.

### 4. The page shows the result safely

The frontend uses `textContent` for the result and creates an actual anchor element for the record. `encodeURIComponent()` makes the reference safe for the URL query parameter. It refreshes saved fines on success, re-enables controls, and clears the card field in `finally`.

If the network request fails, the page cannot reliably tell whether the server already committed a payment. Check the saved fine after reloading before retrying; a paid fine is rejected by the backend. There is no general payment-request idempotency key in this simulation.

### 5. The record can be reopened

[Fine::listForStudent()](../classes/Fine.php) supplies the most recent **successful** payment reference for each saved fine. It does not link a failed attempt as proof of payment.

[payments/receipt.php](../payments/receipt.php) is the PHP page controller; [api/payments/receipt.php](../api/payments/receipt.php) retrieves the JSON record. The API passes the student's matric number to `Payment::getReceipt()`. A student requesting another student's record gets **Receipt not found**, without disclosing its details. A signed-in librarian may retrieve any student's record.

The view and [receipt script](../assets/js/payments_receipt.js) use the API's actual names: `payment_channel` and `time_paid`. Missing references, inaccessible/not-found records and network failures show messages rather than a printable record.

## Two statuses, not one

A receipt describes **one attempt**. Its `status` never becomes successful just because a later retry succeeds.

`fine_status` describes the fine **now**. For example:

| Record opened | Payment status | Current fine status |
| --- | --- | --- |
| Declined attempt before a retry | Failed | Unpaid |
| Same declined attempt after a successful retry | Failed | Paid |
| Successful retry | Successful | Paid |

This is why a failed record says that this attempt did not pay the fine, while separately displaying the current fine status. `time_paid` is the database field name for the attempt time even when the attempt failed.

## Print and privacy limits

The stylesheet hides navigation, footer and the print button in print media, while keeping record details visible. Browser tests checked the call to `window.print()` and the print CSS; an actual print preview/printer still needs human review.

The app's no-card-storage rule does not guarantee that a browser, proxy or externally configured server will never record requests. Keep real financial information out of this system. Automated browser traces and videos are disabled because payment requests contain simulated card input. Screenshots contain sample record details, not card numbers, and stay in the repository rather than being published externally.

## Evidence and learning

- [Testing log and pending human checklist](TESTING.md)
- [Beginner test walkthrough](../tests/README.md)
- [Implementation walkthrough](IMPLEMENTATION_GUIDE.md)
- [API reference](../api/README.md)
