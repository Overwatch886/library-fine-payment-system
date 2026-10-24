# Simulated payment workflow

## What the student does

1. Open **My Loans** and look under saved fines.
2. Select **Pay** beside an unpaid fine. The dialog shows that fine's book and full amount.
3. Select Card, Bank Transfer or USSD. Use only random card number inputs; never enter a real card number.
4. Select **Pay**. Pay and Cancel are disabled while the request is pending.
5. Read the result. A success offers **View Receipt**; a decline offers **View Failed Attempt**.
6. After a success, the paid fine also keeps a **View Receipt** link. Reloading the page does not remove it.
7. Open the record and optionally select **Print Receipt**.

## Decisions in this simulation

| Input or state | Result | Saved fine |
| --- | --- | --- |
| Card input has 12–19 digits after spaces/hyphens are removed, and ends in `0000` | Declined; failed attempt is recorded | Remains unpaid |
| Other input that passes that card-format check | Successful attempt is recorded | Becomes paid |
| Bank Transfer or USSD | Successful attempt is recorded; no card input needed | Becomes paid |
| Unsupported channel or invalid card format | Input rejected | Unchanged; no attempt created |
| Fine belongs to another student or does not exist | Not found | Unchanged |
| Fine is already paid | Repeated payment rejected | Remains paid; no additional attempt |

These are deterministic rules. The code does not contact a bank, verify card ownership, implement card validation or move money. Selecting Bank Transfer/USSD does not initiate an actual transfer.

## Related Documents
For the endpoints used, the `payments/fines` tables setup, and the `Payment` class code structure and functionality, see the `API/AJAX`, `Database`, and `OOP` sections