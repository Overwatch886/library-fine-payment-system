# Library API — guide for the frontend team

Every endpoint returns JSON in the **same shape**:

```json
{ "success": true, "message": "Text you can show to the user", "data": { } }
```

- `success`: `true` or `false`
- `message`: always a human-readable sentence, safe to display as-is (also on errors)
- `data`: the result (object, list, or `null`)

**Base URL** (XAMPP): `http://localhost/library-fine-payment-system/api/`
From pages inside this project, use relative paths, e.g. `fetch('../../api/books/search.php')` from `views/auth/`.

## HTTP status codes

| Code | Meaning | What the frontend should do |
|------|---------|-----------------------------|
| 200 | OK | show the data |
| 201 | Created (book borrowed, payment successful) | show the data |
| 400 | Bad input (missing field, wrong format) | show `message` next to the form |
| 401 | Not logged in / wrong password | send the user to the login page (or show `message` on the login form) |
| 402 | Payment declined (simulated) | show `message`; the attempt is still saved, the user can try again |
| 403 | Logged in but wrong role (student on an admin page, or vice versa) | show `message` / redirect |
| 404 | Not found (or it belongs to another student) | show `message` |
| 405 | Wrong method (GET instead of POST) | a bug in the frontend code |
| 409 | A library rule blocks it (no copies, 3 books already, unpaid fine, already paid/returned) | show `message` |
| 500 | Server error | show `message` ("Something went wrong…") |

## Logging in and sessions

Login uses a normal PHP session cookie. `fetch()` sends it automatically for pages on the same site,
so **you don't need to store any token**. After logging in, every other call just works.
Call `auth/me.php` when a page loads to find out who is logged in (401 = not logged in).

## fetch() helper (copy this)

```js
// Call the API. Returns the parsed JSON: {success, message, data}.
async function api(path, body = null) {
    const options = body
        ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) }
        : { method: "GET" };

    const response = await fetch("/library-fine-payment-system/api/" + path, options);
    const result = await response.json();
    result.status = response.status;   // 200, 401, 409, ...
    return result;
}

// Examples
const login = await api("auth/login.php", { user_type: "student", username: "221345", password: "Password123" });
if (!login.success) showError(login.message);

const search = await api("books/search.php?q=" + encodeURIComponent(query) + "&category=" + encodeURIComponent(category));
search.data.forEach(book => console.log(book.title, book.available_copies));

const borrow = await api("loans/borrow.php", { book_id: 2, due_date: "2026-10-24" });
alert(borrow.message);   // "You borrowed ..." or "You already have 3 books borrowed..."

const me = await api("auth/me.php");
if (me.status === 401) window.location.href = "/library-fine-payment-system/login.php";
```

POST endpoints accept **either JSON** (as above) **or normal form data** (`<form method="POST">` / `FormData`),
using the same field names.

### Value formats

- Dates: `"2026-10-31"` (YYYY-MM-DD). Date-times: `"2026-10-17 14:30:00"`.
- Money: a string with 2 decimals, e.g. `"700.00"` (naira). Format it for display, e.g. `"₦" + Number(x).toLocaleString()`.
- `is_overdue`: `1` or `0`. "Overdue" means not returned and past the due date.
- `fine_so_far`: the fine building up on a book that is still out (₦100/day, max ₦5,000). It is **not** saved until the book is returned.

---

## Endpoints

Who can call it: **any** = anyone, **logged in** = student or librarian, **student**, **librarian**.

| Endpoint | Method | Who |
|---|---|---|
| [auth/login.php](#authloginphp) | POST | any |
| [auth/logout.php](#authlogoutphp) | POST | any |
| [auth/me.php](#authmephp) | GET | logged in |
| [books/search.php](#bookssearchphp) | GET | logged in |
| [books/get.php](#booksgetphp) | GET | logged in |
| [books/categories.php](#bookscategoriesphp) | GET | logged in |
| [loans/borrow.php](#loansborrowphp) | POST | student |
| [loans/return.php](#loansreturnphp) | POST | student (own loans) or librarian |
| [loans/active.php](#loansactivephp) | GET | student |
| [loans/history.php](#loanshistoryphp) | GET | student |
| [fines/list.php](#fineslistphp) | GET | student |
| [payments/pay.php](#paymentspayphp) | POST | student |
| [payments/receipt.php](#paymentsreceiptphp) | GET | student (own) or librarian |
| [admin/stats.php](#adminstatsphp) | GET | librarian |
| [admin/loans.php](#adminloansphp) | GET | librarian |
| [admin/students.php](#adminstudentsphp) | GET | librarian |

---

### auth/login.php

`POST` · any · the same field names as the login form in `views/auth/login_view.html`

| Field | Required | Notes |
|---|---|---|
| `user_type` | yes | `"student"` or `"administrator"` (librarian) |
| `username` | yes | students: matric number **or** username; librarians: username |
| `password` | yes | |

Success `200`:
```json
{"success":true,"message":"Welcome, Oluwaseun Adebayo.","data":{"role":"student","id":"221345","name":"Oluwaseun Adebayo"}}
```
A librarian gets `"role":"librarian"` and a number `id`.

Failure `401`:
```json
{"success":false,"message":"Incorrect username or password.","data":null}
```
Failure `400`: `"password is required."`, `"user_type must be \"student\" or \"administrator\"."`

### auth/logout.php

`POST` · any · no fields

Success `200`:
```json
{"success":true,"message":"You have been logged out.","data":null}
```
(No failure case; calling it while logged out also returns 200.)

### auth/me.php

`GET` · logged in · no parameters

Success `200` (student):
```json
{"success":true,"message":"Logged in as student.","data":{"role":"student","profile":{
  "matric_number":"221345","full_name":"Oluwaseun Adebayo","username":"oadebayo","email":"oadebayo345@stu.ui.edu.ng",
  "phone":"08061112233","hall_of_residence":"Kenneth Mellanby Hall","department":"Computer Science","faculty":"Science",
  "level":300,"created_at":"2026-10-17 17:26:43","active_loans":2,"overdue_loans":0,"unpaid_fines_total":"0.00","can_borrow":true}}}
```
Librarian `profile`: `librarian_id, full_name, username, email, phone, created_at`.

Failure `401`:
```json
{"success":false,"message":"Please log in first.","data":null}
```

### books/search.php

`GET` · logged in

| Param | Required | Notes |
|---|---|---|
| `q` | no | matches title, author or ISBN (part of a word is fine) |
| `category` | no | exact category name from `books/categories.php` |

No parameters = all books (max 100), A–Z by title.

Success `200`:
```json
{"success":true,"message":"1 book(s) found.","data":[
  {"book_id":6,"title":"Half of a Yellow Sun","author":"Chimamanda Ngozi Adichie","isbn":"9781400095209",
   "publisher":"Anchor Books","publication_year":2007,"category":"Literature","total_copies":2,"available_copies":0}]}
```
Nothing found is still a success with `"data":[]`. `available_copies` is live: it changes as soon as someone borrows or returns.

Failure `401`: `"Please log in first."`

### books/get.php

`GET` · logged in · `id` (required, book_id)

Success `200`: `data` is one book object (same fields as in search).

Failure `404`:
```json
{"success":false,"message":"Book not found.","data":null}
```
Failure `400`: `"id is required and must be a positive whole number."`

### books/categories.php

`GET` · logged in · no parameters

Success `200`:
```json
{"success":true,"message":"Categories loaded.","data":["Biology","Computer Science","Economics","Engineering","History","Law","Literature","Mathematics","Medicine"]}
```
Failure `401`: `"Please log in first."`

### loans/borrow.php

`POST` · student

| Field | Required | Notes |
|---|---|---|
| `book_id` | yes | the system picks an available copy |
| `due_date` | no | `"YYYY-MM-DD"`, 1–14 days from today. Leave out for the default (14 days). Tip: `<input type="date">` gives this format. |

Success `201`:
```json
{"success":true,"message":"You borrowed \"Clean Code\". Please return it by 2026-10-31.","data":{
  "loan_id":9,"copy_id":4,"book_id":2,"book_title":"Clean Code","matric_number":"221345","student_name":"Oluwaseun Adebayo",
  "borrow_date":"2026-10-17","due_date":"2026-10-31","return_date":null,"status":"borrowed",
  "is_overdue":0,"days_overdue":0,"fine_so_far":"0.00"}}
```

Failures:
| Code | message |
|---|---|
| 409 | `Sorry, all copies of "Half of a Yellow Sun" are currently borrowed.` |
| 409 | `You already have 3 books borrowed. Return one before borrowing another.` |
| 409 | `You have an unpaid fine of ₦700.00. Please pay it before borrowing.` |
| 400 | `The due date must be between 1 and 14 days from today.` |
| 400 | `due_date must be a date like 2026-10-31 (YYYY-MM-DD).` |
| 404 | `Book not found.` |
| 403 | `Only students can do this.` (librarian logged in) |

### loans/return.php

`POST` · student (own loans only) or librarian (any loan) · `loan_id` (required)

Success `200`, returned on time (`fine` is `null`):
```json
{"success":true,"message":"Book returned on time. Thank you!","data":{"loan":{ ...same fields as a borrow... ,"return_date":"2026-10-17","status":"returned"},"fine":null}}
```
Success `200`, returned late (a fine is created):
```json
{"success":true,"message":"Book returned 6 day(s) late. A fine of ₦600.00 has been added.","data":{
  "loan":{"loan_id":3,"book_title":"Half of a Yellow Sun","due_date":"2026-10-11","return_date":"2026-10-17","status":"returned", ...},
  "fine":{"fine_id":3,"days_late":6,"amount":"600.00","status":"unpaid"}}}
```

Failures: `409` `This book has already been returned.` · `404` `Loan not found.` (also when a student tries someone else's loan) · `400` `loan_id is required...`

### loans/active.php

`GET` · student · no parameters

Success `200`: the student's books still out, soonest due first.
```json
{"success":true,"message":"2 active loan(s).","data":[
  {"loan_id":4,"copy_id":20,"book_id":10,"book_title":"Macroeconomics","matric_number":"232118","student_name":"Chiamaka Okafor",
   "borrow_date":"2026-08-08","due_date":"2026-08-22","return_date":null,"status":"borrowed",
   "is_overdue":1,"days_overdue":56,"fine_so_far":"5000.00"},
  {"loan_id":3, "...":"...", "is_overdue":1,"days_overdue":6,"fine_so_far":"600.00"}]}
```
Failure `403`: `Only students can do this.` · `401`: `Please log in first.`

### loans/history.php

`GET` · student · no parameters

Success `200`: every loan, newest first. Same fields as `active.php` **plus** `fine_id`, `fine_amount`, `fine_status` (all `null` if the loan had no fine). There is no `fine_so_far` field here.
```json
{"success":true,"message":"2 loan(s) in your history.","data":[
  {"loan_id":3,"book_title":"Half of a Yellow Sun","due_date":"2026-10-11","return_date":"2026-10-17","status":"returned",
   "is_overdue":0,"days_overdue":0,"fine_id":3,"fine_amount":"600.00","fine_status":"unpaid", "...":"..."}]}
```
Failure `403` / `401` as above.

### fines/list.php

`GET` · student · no parameters

Success `200`:
```json
{"success":true,"message":"You owe ₦700.00 in unpaid fines.","data":{
  "fines":[{"fine_id":1,"loan_id":6,"book_title":"Things Fall Apart","due_date":"2026-10-06","return_date":"2026-10-13",
            "days_late":7,"amount":"700.00","status":"unpaid","created_at":"2026-10-13 10:15:00","paid_at":null}],
  "accruing":[],
  "total_unpaid":"700.00"}}
```
- `fines`: saved fines (unpaid first). Only these can be paid.
- `accruing`: books still out and overdue: `loan_id, book_title, due_date, days_overdue, fine_so_far`. These become a fine when the book is returned.

Failure `403` / `401` as above.

### payments/pay.php

`POST` · student · **simulated**: card numbers ending in `0000` are declined; any other card, `bank_transfer` and `ussd` succeed. The full fine amount is always charged. Card numbers are never saved.

| Field | Required | Notes |
|---|---|---|
| `fine_id` | yes | from `fines/list.php` |
| `channel` | yes | `"card"`, `"bank_transfer"` or `"ussd"` |
| `card_number` | when channel is card | 12–19 digits; spaces/dashes allowed |

Success `201` (the fine is now paid; `data` is the receipt):
```json
{"success":true,"message":"Payment successful. Your fine has been cleared.","data":{
  "reference":"RCPT-20261017-8P6MN3","status":"successful","amount":"700.00","payment_channel":"card","failure_reason":null,
  "time_paid":"2026-10-17 17:25:30","matric_number":"220981","student_name":"Funmilayo Adeyemi",
  "fine_id":1,"days_late":7,"fine_status":"paid",
  "loan_id":6,"book_title":"Things Fall Apart","borrow_date":"2026-09-22","due_date":"2026-10-06","return_date":"2026-10-13"}}
```

Card declined `402` (the attempt IS saved and has its own reference; the fine stays unpaid, so the student can try again):
```json
{"success":false,"message":"Payment failed: Card declined by the bank (simulated: card numbers ending in 0000 always fail).","data":{
  "reference":"RCPT-20261017-NXXGW6","status":"failed","amount":"700.00","payment_channel":"card",
  "failure_reason":"Card declined by the bank (simulated: card numbers ending in 0000 always fail).","fine_status":"unpaid", "...":"..."}}
```

Other failures: `409` `This fine has already been paid.` · `404` `Fine not found.` · `400` `Please enter a valid card number (12 to 19 digits).` · `400` `channel must be one of: card, bank_transfer, ussd.`

### payments/receipt.php

`GET` · student (own receipts) or librarian (any) · `reference` (required)

Success `200`: same `data` as a pay response. Failed attempts also have a receipt, with `"status":"failed"`.

Failure `404`:
```json
{"success":false,"message":"Receipt not found.","data":null}
```

### admin/stats.php

`GET` · librarian · no parameters

Success `200`:
```json
{"success":true,"message":"Dashboard stats loaded.","data":{
  "total_books":20,"total_copies":39,"copies_out":5,"overdue_loans":2,"total_students":6,
  "unpaid_fines_count":1,"unpaid_fines_total":"700.00","payments_collected":"500.00"}}
```
Failure `403`:
```json
{"success":false,"message":"Only librarians can do this.","data":null}
```

### admin/loans.php

`GET` · librarian · all parameters optional

| Param | Notes |
|---|---|
| `status` | `active`, `overdue` or `returned` (leave out for all) |
| `matric_number` | one student's loans |
| `q` | search book title or student name |

Success `200`: loans newest first, with the same fields as `loans/history.php` plus `fine_so_far`.
```json
{"success":true,"message":"1 loan(s) found.","data":[{"loan_id":4,"book_title":"Macroeconomics","matric_number":"232118",
  "student_name":"Chiamaka Okafor","due_date":"2026-08-22","is_overdue":1,"days_overdue":56,"fine_so_far":"5000.00",
  "fine_id":null,"fine_amount":null,"fine_status":null, "...":"..."}]}
```
Failures: `400` `status must be active, overdue or returned.` · `403` `Only librarians can do this.`

### admin/students.php

`GET` · librarian

**Without** `matric_number`: all students with a summary (optional `q` searches name or matric number).
```json
{"success":true,"message":"6 student(s) found.","data":[
  {"matric_number":"205643","full_name":"Babatunde Bakare","department":"Public and International Law","faculty":"Law","level":500,
   "hall_of_residence":"Tedder Hall","email":"bbakare643@stu.ui.edu.ng","phone":"08105556677",
   "total_loans":1,"active_loans":0,"overdue_loans":0,"unpaid_fines_total":"0.00"}]}
```

**With** `matric_number=220981`: one student's full record.
```json
{"success":true,"message":"Student record loaded.","data":{
  "profile":{ ...same as auth/me.php student profile... },
  "loans":[ ...same as loans/history.php... ],
  "fines":{ ...same as fines/list.php data... }}}
```
Failures: `404` `Student not found.` · `403` `Only librarians can do this.`

---

## Using the backend from a normal PHP page (e.g. `login.php`)

The classes also work without the JSON API, for a classic form POST:

```php
require_once __DIR__ . '/config/init.php';

try {
    $user = (new Auth())->login($_POST['user_type'], $_POST['username'], $_POST['password']);
    // $user['role'] is 'student' or 'librarian' → redirect to the right dashboard
} catch (AppException $e) {
    $errorMessage = $e->getMessage();   // "Incorrect username or password."
}
```
