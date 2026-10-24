# Implementation guide

This guide explains the Borrowing History, simulated Payment Receipt and selectable due-date changes. It is written for a student learning how this PHP/JavaScript app works. Read one feature at a time; you do not need to memorise the whole guide.

The explanations below refer to the current source on 23 October 2026. Line links are navigation aids, not a promise that line numbers will never change. Comments, blank lines and closing braces organise the code; the tables explain the executable lines and HTML/CSS declarations in each changed block.

For running and understanding the tests, use [the test walkthrough](../tests/README.md). Actual results and the still-pending human checks belong in [the testing log](TESTING.md). The payment process is described separately in [Payment workflow](PAYMENT_WORKFLOW.md).

## 1. The overall connection

A **controller** is the PHP file the browser opens. A **view** is the file containing the page's HTML. An **API endpoint** is a URL that returns data rather than a complete page. **PDO** is PHP's interface for talking to the database. The **DOM** is the browser's in-memory representation of the HTML page; JavaScript changes that representation to show results.

For history, the sequence is:

1. The browser requests [loans/history.php](../loans/history.php).
2. PHP loads the shared configuration and class loader, then checks the session with `Auth`.
3. PHP includes [views/loans/history.html](../views/loans/history.html). The shared header/footer are included too.
4. The browser receives the finished HTML and loads [loans_history.js](../assets/js/loans_history.js).
5. JavaScript requests [api/loans/history.php](../api/loans/history.php).
6. The endpoint checks the logged-in student and calls the existing `Loan::getHistoryForStudent()` method.
7. The method reads saved loans and optional fines through PDO. The endpoint returns JSON.
8. JavaScript builds table cells from that JSON.

Receipts follow the same pattern, using `Payment::getReceipt()`. Borrowing starts in the catalogue dialog and sends the book ID and selected date to the existing borrow endpoint.

**This is a bridge between server-rendered pages and browser data requests.** Login and page layout still use PHP rendering; the interactive sections use `fetch()`. Adding history and receipts did not require replacing the login flow or creating another set of database rules.

### Shared initialisation: existing code these changes reuse

See [config/init.php:11–18](../config/init.php#L11-L18).

| Code | Meaning and connection |
|---|---|
| `require_once __DIR__ . '/config.php';` | Load the settings once in this request. PHP stops if a required file cannot load. `require` would load the file every time; `require_once` avoids duplicate loading. |
| `spl_autoload_register(function (string $className): void { ... });` | Register a function PHP calls when a class has not yet been loaded. For example, `new Auth()` triggers the loader for `Auth`. |
| `$file = __DIR__ . '/../classes/' . $className . '.php';` | Build the class's filesystem path. The dot joins strings; it is not a URL. |
| `if (is_file($file)) { require_once $file; }` | Load that class file if it exists, once per request. |

[Database::getConnection()](../classes/Database.php#L13-L28) creates one PDO connection per request and reuses it across classes. Exception mode reports SQL errors, associative fetching gives keys such as `book_title`, and native prepared statements keep supplied values separate from SQL commands. These existing responsibilities remain in the backend.

### Filesystem paths are not browser URLs

- `__DIR__` means the directory of the PHP file being executed. `__DIR__ . '/../config/init.php'` lets PHP locate a file on disk.
- A link such as `../catalogue.php` tells the browser to go up one URL folder. From `loans/history.php`, that reaches the project's catalogue page.
- JavaScript's `fetch('../api/loans/history.php')` resolves relative to the **page URL**, not the directory where the JavaScript file is stored.
- `header('Location: ../login.php')` sends an HTTP instruction to the browser. PHP does not execute `login.php` because its name appears in the string; the browser makes a new request to that URL.
- Web URLs use `/`, including on Windows. Do not put `C:\...` or `__DIR__` in a public link.
- Avoid a leading `/` for these project-relative links: `/catalogue.php` would go to the web server's root, not necessarily the project's folder.

## 2. Nested controllers and login redirects

### The two entry-point files

[loans/history.php:1–7](../loans/history.php#L1-L7) and [payments/receipt.php:1–7](../payments/receipt.php#L1-L7) are deliberately short. Here is the history controller, annotated rather than replaced:

```php
<?php                                      // Start PHP code.
require_once __DIR__ . '/../config/init.php'; // Load settings and the class loader.
$auth = new Auth();                         // Obtain the shared PDO connection and start/read the session.
$auth->requireStudentForWeb('../');         // Allow students; redirect others using the parent URL folder.
include __DIR__ . '/../views/loans/history.html'; // Render the view only after the guard succeeds.
```

The receipt controller has the same first steps, but calls `requireLoginForWeb('../')` rather than the student-only guard and includes `views/payments/receipt.html`. This allows both students and librarians to open the receipt page. The receipt API still controls which record they may read.

`include` executes any PHP inside the view and outputs its HTML at that location. Included files share the current scope, so the view can use the controller's `$auth`. The `.html` extension does **not** prevent PHP code from executing when a PHP controller includes that file. Open the controller URL, not the view file directly.

### The optional URL prefix in Auth

See [Auth.php:139–177](../classes/Auth.php#L139-L177). The new part is the optional `$basePath` argument and its use in redirects. The role/session checks were already the guards' responsibility.

| Line/block | What each line does |
|---|---|
| 139: `requireLoginForWeb(string $basePath = ''): array` | Accept a text URL prefix. Existing root callers omit it and retain the empty prefix. Return the session user on success. |
| 141: `$user = $this->currentUser();` | Read the existing session user rather than trusting request input. |
| 142: `if ($user === null)` | Take the redirect branch only if nobody is logged in. |
| 143: `$_SESSION['flash'] = ...` | Save a one-time explanation for the login page. The login page reads and removes the flash message. |
| 144: `header('Location: ' . $basePath . 'login.php');` | Join the supplied prefix to the login filename and send the redirect. For a nested page, the result is `../login.php`. |
| 145: `exit;` | Stop this request so the protected view is not rendered after redirecting. |
| 147: `return $user;` | Give an allowed caller the session user. |
| 155: `requireStudentForWeb(string $basePath = ''): string` | Give the student-only web guard the same optional prefix; return a matric number. |
| 157: `requireLoginForWeb($basePath)` | First check login, passing the prefix onward. This also fixes nested unauthenticated redirects. |
| 158: check `$user['role'] !== 'student'` | Reject a logged-in librarian from a student-only page. |
| 159: prefixed `admin_dashboard.php` redirect | Send that librarian to their dashboard, not to a nonexistent dashboard inside `loans/`. |
| 160: `exit;`; 162: `return $user['id'];` | Stop a redirected request; otherwise return the student's ID. |
| 170: `requireLibrarianForWeb(string $basePath = ''): int` | Apply the same optional prefix to librarian pages, returning an integer ID. |
| 172: `requireLoginForWeb($basePath)` | Reuse the login check with the caller's prefix. |
| 173–175 | If the role is not librarian, redirect to the prefixed catalogue URL and stop. |
| 177: `(int) $user['id']` | Return the allowed librarian's ID as an integer. |

The API guards still throw errors with HTTP 401/403 instead of redirecting. That distinction is intentional: a web page needs navigation; a data request needs an error response JavaScript can read.

## 3. Borrowing History

### View: layout, navigation and an initially empty table

See [history.html](../views/loans/history.html).

| Lines | Explanation |
|---|---|
| 1–6 | Use HTML5, declare English, UTF-8 text and a device-width viewport, and give the browser tab its page title. UTF-8 supports `₦` and other characters. |
| 7–8 | Load the shared favicon and stylesheet from one URL folder above this page. |
| 11–12 | Enter PHP and set the heading passed to the shared header. |
| 13–18 | Build the header's navigation array. Catalogue, My Loans and Logout are in the parent folder; the history self-link is in this folder. Each entry has a destination and a readable label. |
| 19 | Include the shared header using a filesystem path relative to the view. Its existing `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` escapes both labels and link attributes. |
| 21–23 | Use the existing loans-page/panel layout, identify the section by its heading, and display “All My Loans”. |
| 24 | Show loading text before the request finishes. `id` gives JavaScript a handle; `role="status"` helps assistive technology announce updates. |
| 25 | Wrap the table in a horizontally scrollable region instead of forcing the entire page wider. |
| 26 | Give the table its ID/shared class and start it hidden. JavaScript reveals it only when there are rows. |
| 27 | Provide a table caption for assistive technology while visually hiding it with CSS. |
| 28–37 | Create the heading row. Each `th scope="col"` identifies a column: book, borrow/due/return dates, status and saved fine. |
| 38 | Provide an empty table body that JavaScript will populate. No loan is invented in the HTML. |
| 39–42 | Close the table, scrolling wrapper, section and main content. |
| 43 | Include the existing shared footer. |
| 44 | Load the history script after its target elements exist in the page. |
| 45–46 | Finish the document. |

Root student pages also use the forward-slash history link: [catalogue_view.html:17](../views/student/catalogue_view.html#L17) and [my_loans.html:17](../views/student/my_loans.html#L17). They both point to the guarded controller `loans/history.php`, not directly to the view.

### JavaScript: formatting and safe cell creation

See [loans_history.js:2–15](../assets/js/loans_history.js#L2-L15).

| Line | Explanation |
|---|---|
| 2 | `API = '../api'` keeps requests within the project when history is opened from `loans/`. |
| 4 | Declare a reusable date formatter; its argument is an API date string. |
| 5 | A missing return date is normal for a book still out, so display an em dash. |
| 6 | Append local midnight to a date-only value before constructing `Date`. This treats it as a calendar date rather than relying on date-only UTC parsing. It does not choose a new due date. |
| 7 | Format as an English day, short month and year; for example `23 Oct 2026`. |
| 10 | Declare a helper that takes a row and the text for a cell. |
| 11 | Create a real `td` element. |
| 12 | Set its `textContent`: characters such as `<` are shown, not interpreted as markup. |
| 13 | Attach the cell to its row. |
| 14 | Return the cell so a caller can put a status badge inside it. |

A short example of the safety pattern:

```js
const cell = document.createElement('td'); // Make a table-cell node.
cell.textContent = text;                  // Put literal text in it, not executable HTML.
row.appendChild(cell);                    // Add that node to the current row.
```

### JavaScript: the request, every row and all outcome states

See [loans_history.js:17–67](../assets/js/loans_history.js#L17-L67).

| Lines | What the block does |
|---|---|
| 17 | Declare an `async` loader. It can wait for network responses without freezing the page. |
| 18, 19, 20 | Locate the loading/error text, the table and its body respectively. These IDs come from the view. |
| 22 | Begin `try`; exceptions from fetching/parsing/rendering reach `catch`. |
| 23 | `await fetch(...)` sends a GET to the existing history endpoint. Same-origin requests normally include the session cookie. |
| 24–26 | If the API returns 401, navigate to parent-folder login and return immediately. |
| 28 | Wait for the response body and parse its JSON. The app's envelope contains `success`, `message` and `data`. |
| 29–31 | If `success` is false, show the returned message or a fallback and stop. The table stays hidden. |
| 33–35 | If the successful data array is empty, show the explicit empty-history message and stop. |
| 38 | Remove old rows before building the current results. This avoids accumulating duplicate rows if the loader is reused. |
| 39 | Process each loan in the returned array. The backend supplies the order. |
| 40 | Create one `tr` for this loan. |
| 41 | Put `book_title` in the first text-only cell. |
| 42, 43, 44 | Format and add `borrow_date`, `due_date`, and `return_date` in that order. |
| 46 | Convert `is_overdue` to a number before comparing with 1, tolerating either JSON number `1` or string `"1"`. |
| 47 | Choose Returned if a return date exists; otherwise choose Overdue when flagged, or Borrowed. The frontend displays a result; it does not save “overdue” as a new loan status. |
| 48 | Create a `span` for the status badge. |
| 49 | Assign shared badge and colour classes using the overdue flag. These class names are fixed by the app, not supplied as arbitrary HTML by the book title. |
| 50 | Put the chosen status text into the badge. |
| 51 | Make the status cell and append the badge returned by the helper call. |
| 53 | If `fine_amount` is null, choose an em dash: no saved fine exists for that loan. |
| 54 | Otherwise convert the amount to a number, format Nigerian grouping with two decimal places, and add the naira symbol. |
| 55 | Add Paid/Unpaid based on `fine_status`. This is a **saved** fine, not an estimate for a book still out. |
| 56 | Add that fine text to its cell. |
| 57 | Attach the completed row to the table body. |
| 58 | Finish processing the data array. |
| 59, 60 | Hide the placeholder and reveal the populated table. |
| 61–63 | Catch unexpected/network/parsing errors, log the error for development, and display a simple retry message. No book title is inserted as HTML here either. |
| 67 | Start the loader when the script runs. |

### Existing backend contract reused by history

[api/loans/history.php:6–13](../api/loans/history.php#L6-L13) loads the API bootstrap, requires GET, obtains the student ID from `Auth`, calls `Loan`, and responds with the envelope. The student ID is **not** selected by a query-string parameter.

[Loan::getHistoryForStudent():198–208](../classes/Loan.php#L198-L208) uses a prepared query with `WHERE v.matric_number = ?`. Its `LEFT JOIN fines` keeps loans even if they have no fine; aliases expose `fine_amount` and `fine_status`. The latest borrow date and loan ID come first. No history table or second borrowing system was added.

## 4. Persistent receipt links in My Loans

Previously a payment result could be lost after a reload. The fines API now supplies the successful payment reference alongside each fine, so the UI can rebuild a receipt link from saved data.

### The small Fine query change

See [Fine.php:51–63](../classes/Fine.php#L51-L63).

```sql
(SELECT p.reference FROM payments p
  WHERE p.fine_id = f.fine_id AND p.status = 'successful'
  ORDER BY p.payment_id DESC LIMIT 1) AS payment_reference
```

| Query line | Explanation |
|---|---|
| `SELECT p.reference FROM payments p` | Read the reference from a payment row. `p` is a short name for `payments`. This small query runs as part of the outer fine query. |
| `p.fine_id = f.fine_id` | Match this payment to the current outer fine (`f`). This is called a correlated subquery: it refers to the outer row. |
| `AND p.status = 'successful'` | Do not turn a declined attempt into the persistent proof-of-payment link beside a paid fine. |
| `ORDER BY p.payment_id DESC LIMIT 1` | If more than one matching row exists, choose the latest ID and return only its reference. With no match, the result is null. |
| `AS payment_reference` | Give the selected value the JSON field name the frontend reads. |

The surrounding outer query still restricts results to the authenticated student's matric number, supplied with `prepare()`/`execute([$matric])`. This change reads another saved field; it does not alter fine calculation, the payment decision or the fine status.

### Rendering the saved reference and avoiding title-in-attribute bugs

See [my_loans.js:160–194](../assets/js/my_loans.js#L160-L194).

| Lines | Explanation |
|---|---|
| 160–163 | For each fine, determine Paid/Unpaid and the existing status class/text. |
| 165 | Check whether a successful reference was supplied. |
| 166 | Build a View Receipt anchor when present, or empty text when absent. `encodeURIComponent()` encodes the reference as one URL value so characters cannot change the query structure. |
| 167–168 | For a paid fine, show its paid date and the saved receipt link. Reloading the page works because the reference comes from the API again. |
| 169 | For an unpaid fine, show Pay with only the numeric fine ID and amount as data attributes. The title is deliberately **not** inserted into a quoted `data-title` attribute. |
| 171–181 | Retain the existing fine-row markup. The escaped title is displayed inside `.fine-book`, the amount is formatted, and the chosen status/action are inserted into their intended locations. |
| 182, 184 | Join each fine's markup and render the list. `escapeHtml()` protects the title in its element-text position. |
| 186–187 | Find every newly rendered Pay button and attach a click handler. |
| 188 | Open the existing payment dialog with three arguments. |
| 189 | Convert the button's fine-ID attribute from text to a number. |
| 190 | Start at that button; `closest('.fine-item')` finds its fine row, `querySelector('.fine-book')` finds its displayed title, and `textContent` reads the complete literal title. |
| 191 | Convert the amount attribute to a number. |
| 192–194 | Finish the call and handler for each button. |

Why remove `data-title`? The existing [escapeHtml helper](../assets/js/my_loans.js#L27-L31) creates a temporary element, writes text and reads its HTML. That protects **element text** such as `<span>title</span>`, but does not necessarily encode quotation marks for a quoted attribute. A title containing `"` could break the old attribute. Reading the already-rendered text avoids that separate attribute context entirely. The payment dialog uses `payBookTitle.textContent`, so the title remains text there too.

### Payment-result container and request handling

[my_loans.html:41](../views/student/my_loans.html#L41) adds an empty `payment-result` paragraph with `role="status"`. It is where JavaScript places the response message and attempt link. Existing modal controls and their IDs remain in [my_loans.html:50–70](../views/student/my_loans.html#L50-L70).

See [my_loans.js:224–291](../assets/js/my_loans.js#L224-L291). Existing context is included below so the new lines make sense.

| Lines | Explanation |
|---|---|
| 224–227 | Open the modal for a fine, remember its ID, and show its title/amount as text. |
| 228–231 | Clear old card input, reset the selected channel to card, show its input wrapper, and show the modal. |
| 234–236 | Close by hiding the modal and clearing the pending fine ID. This does not mark a fine paid. |
| 239–240 | Declare the asynchronous Pay handler and stop if no fine was selected. |
| 242–243 | Read the channel; only read/trim card input for card payments. Other channels use null. |
| 245–247 | A missing card value produces an alert and no request. Backend validation still decides whether supplied input is valid. |
| 250 | Build the request object with the selected fine ID and channel. It does **not** accept an amount from the browser; the backend reads the fine's saved amount. |
| 251 | Include card input only when a nonempty card value exists. Do not log the body. |
| 253, 254 | Disable Pay and Cancel while waiting. Normal repeated clicks cannot start another request or close this dialog halfway through. The backend's locked fine/check still protects against duplicate payment requests outside the UI. |
| 255 | Start guarded request handling. |
| 256–260 | POST to the existing pay endpoint, label the body JSON, and serialise the object. This is a simulated payment request, not an external bank call. |
| 261 | Parse the API's response, including declined attempts returned with `success: false`. |
| 263 | Close the dialog after a readable API response. Declines and validation responses also follow this response branch. |
| 264 | Locate the new result paragraph. |
| 265 | Replace its previous content with the API message as text, followed by a separating space. This removes any old attempt link. |
| 266 | Only create a link if `json.data` exists and has a reference. `?.` safely handles missing/null data. |
| 267 | Create a real anchor element, rather than constructing the response message as HTML. |
| 268 | Point it to the guarded receipt controller with the encoded reference. |
| 269 | Label successful attempts View Receipt and declined attempts View Failed Attempt. The latter is not presented as proof the fine was paid. |
| 270 | Append the link after the message. |
| 272 | Refresh saved/accruing fine displays only after success, so the paid status and persistent link appear. |
| 273–276 | Catch an unexpected/network/parsing error, log the error (not the request/card), close the dialog and tell the user to try again. A network error does not itself prove whether the server saved an attempt. |
| 277 | `finally` runs after success or an error. |
| 278, 279 | Re-enable the controls. |
| 280 | Clear the visible card field after the request finishes. The field is not saved to browser storage. |
| 286–288 | The channel-change listener shows card input only for card. |
| 290, 291 | Connect Pay and Cancel to their handlers. |

Clearing an input is not a promise to erase every temporary variable from browser memory instantly. The payment INSERT excludes card digits, and the frontend does not put them in persistent browser storage. Test traces and videos are disabled, but browsers, proxies or externally configured logging can record request bodies. This remains a local test-payment feature: use only simulated fixture values, never real card information.

The payment modal did not receive the borrowing modal's keyboard focus boundary handling. Do not treat these changes as full accessibility approval of every dialog.

## 5. The simulated Payment Receipt page

### View and role-based navigation

See [receipt.html](../views/payments/receipt.html).

| Lines | Explanation |
|---|---|
| 1–6 | Declare HTML5, English, UTF-8, responsive viewport and the tab title. |
| 7–8 | Load shared favicon/styles from the project's parent URL folder. |
| 11–12 | Enter PHP and set the shared page heading. |
| 13 | Read the authenticated session role and choose one of two navigation arrays. The controller has already required login. |
| 14–17 | Students get catalogue, My Loans, history and logout links. Every target begins with `../` because receipt is inside `payments/`. |
| 18–21 | Librarians instead get dashboard and logout links; the menu does not give them student-only actions. |
| 22 | Include the existing shared header; it escapes its labels and attributes. |
| 24–25 | Reuse loans-page/panel styling and identify the receipt section by its heading. `receipt-card` also gives print CSS a target. |
| 26–27 | State “Simulated Payment Record” and that no real money moved. This distinguishes the project demonstration from a genuine bank receipt. |
| 28–30 | Provide the dynamic container and loading placeholder; `aria-live="polite"` requests a noninterrupting announcement when content changes. |
| 31 | Add a normal button that does not submit a form. Start it hidden; only a loaded record reveals it. |
| 32–34 | Finish the section/main and include the shared footer. |
| 35 | Load the receipt script after its elements exist. |
| 36–37 | Finish the document. |

### Safe label/value rows

See [payments_receipt.js:2–10](../assets/js/payments_receipt.js#L2-L10).

| Line | Explanation |
|---|---|
| 2 | Use parent-folder `../api` for this nested page's data request. |
| 4 | Declare `addDetail(list, label, value)` to add one label/value pair. |
| 5, 6 | Create a `dt` (description term) and put the label in it as text. |
| 7, 8 | Create a `dd` (description detail) and put the value in it as text; `?? '—'` uses an em dash only for null/undefined, preserving a legitimate zero. |
| 9 | Append both nodes in order to the `dl` description list. |

Every title, reference, student name and failure reason passes through `textContent` rather than becoming HTML.

### Reading the reference and requesting one record

See [payments_receipt.js:12–30](../assets/js/payments_receipt.js#L12-L30).

| Lines | Explanation |
|---|---|
| 12–13 | Declare the asynchronous loader and locate its output container. |
| 14 | Read `reference` from the page's query string with `URLSearchParams`; this decodes the parameter for use in JavaScript. |
| 15–17 | If absent/empty, display instructions and return without requesting a record. The print button stays hidden. |
| 20–21 | Start `try` and fetch the receipt API with the reference encoded as one query value. |
| 22–24 | Handle API 401 by navigating to login and stopping. |
| 26 | Parse the JSON response. |
| 27–29 | On a reported API failure, show its message or the not-found fallback and return. No printable details are created. |

### Building the record: every field and outcome

See [payments_receipt.js:32–65](../assets/js/payments_receipt.js#L32-L65).

| Line(s) | Explanation |
|---|---|
| 32 | Take the successful response's `data` object. “Successful response” here means the record loaded; the payment attempt inside it may still be failed. |
| 33 | Check the **attempt's** `status` against `successful`. |
| 34–35 | Create a notice paragraph and give it the appropriate shared colour class and receipt-notice spacing class. |
| 36–37 | Explicitly say that a success paid the fine or a decline did not pay it. |
| 38–39 | Create the `dl` and assign receipt-details layout styling. |
| 40 | Display `reference`: identifies this attempt and is used when reopening the page. |
| 41 | Display `student_name`. |
| 42 | Display `matric_number`. |
| 43 | Display `book_title`. |
| 44 | Display `loan_id`: connects this payment's fine to the borrowing record. |
| 45 | Convert `amount` to a number, format it with Nigerian grouping/two decimal places, and prefix `₦`. |
| 46 | Map the stored channel names to readable labels. |
| 47 | Use **`payment_channel`**, the actual API field; use its mapping or the original value if no mapping exists. |
| 48 | Use **`time_paid`**, the actual API field, not the old mismatched `paid_at` name. This timestamp describes the attempt, even a failed one, and is displayed as supplied by the library backend. |
| 49 | Display `borrow_date`. |
| 50 | Display `due_date`. |
| 51 | Display `return_date`. |
| 52 | Display saved `days_late`. |
| 53 | Display Successful/Failed based on this attempt. |
| 54 | Separately display current Paid/Unpaid based on `fine_status`. |
| 55 | Display `failure_reason` only for a failed attempt. |
| 56 | Replace the loading/error content with the notice and description list. |
| 57 | Reveal Print only after a record has rendered. Failed attempts may be printed too, but their failure warning and status remain visible. |
| 58–60 | Catch unexpected/network/parsing errors, log the development error, and show a simple failure message. |
| 64 | Connect the button's click to the browser's `window.print()` function. The user controls the print dialog. |
| 65 | Load the record when the script starts. |

**Attempt status and fine status are different facts.** If attempt A fails and attempt B later succeeds, reopening A must still say Failed. Its “Current fine status” may now say Paid because B paid the fine. The app does not rewrite A into a successful receipt.

### Existing backend access control reused by receipts

[api/payments/receipt.php:6–16](../api/payments/receipt.php#L6-L16) loads the bootstrap, requires GET/login, validates the reference, and passes the student's session ID to `Payment::getReceipt()`. A librarian passes null to permit reading any student's receipt.

[Payment::getReceipt():93–111](../classes/Payment.php#L93-L111) reads the attempt with a prepared reference query, joins its fine and loan details, and throws “Receipt not found” for absent records or another student's record. The page's reference is only a lookup key; it does not grant permission. Hiding a link in the UI is not the security check—the backend check is.

The existing [Payment::pay()](../classes/Payment.php#L26-L85) validates simulated input, locks/checks the fine, records valid declined or successful attempts, changes the fine to paid only on success, and commits the related changes together. Card digits are not included in the INSERT. Invalid input and rejected ownership/repeat-payment requests are not recorded as simulated bank attempts. These rules were reused, not duplicated in the receipt page.

## 6. Choosing the return/due date

### View: a real date form using the shared limit

See [catalogue_view.html:48–64](../views/student/catalogue_view.html#L48-L64).

| Line | Explanation |
|---|---|
| 48 | Keep the overlay hidden at first. Output `MAX_LOAN_DAYS` into `data-max-loan-days` so JavaScript reads the existing PHP rule instead of keeping another maximum constant. `<?= ... ?>` is PHP's short echo syntax. |
| 49 | Make the dialog a form. `id` connects the submit handler, `role="dialog"` identifies it, `aria-modal="true"` describes it as modal, and `aria-labelledby` points to its heading. |
| 50 | Give the heading the matching ID. |
| 51 | Keep the title's span for JavaScript to fill safely. |
| 52 | Keep the borrow-date span for display. |
| 53 | Add a visible label whose `for` matches the date input ID. |
| 54 | Add a browser-native `type="date"` input. `required` rejects a blank date; `aria-describedby` connects the hint and error. JavaScript supplies its min/max/value when opening. |
| 55 | Display the permitted range using the same `MAX_LOAN_DAYS` configuration value. |
| 56 | Add an initially empty error paragraph with `role="alert"`; rejected borrowing can explain the problem without losing the date selection. |
| 57 | Reuse the action-row layout. |
| 58 | Cancel has `type="button"`, so it does not submit the form. |
| 59 | Confirm has `type="submit"`, so clicking it or submitting the form uses the same validation/request handler. |
| 60–62 | Close the action wrapper, form and overlay. |
| 64 | Load the existing catalogue script. |

[config/config.php:18](../config/config.php#L18) defines the current maximum as 14 days. Reading that value into the view avoids a second frontend maximum. The earliest permitted choice is tomorrow.

### New state and reused element references

See [catalogue.js:14–24](../assets/js/catalogue.js#L14-L24).

| Line(s) | Explanation |
|---|---|
| 14–19 | Existing DOM references locate overlay, title, borrow date, the now-editable return date, Confirm and Cancel. The input keeps the old return-date ID so the script's handle stays connected. |
| 21 | `pendingBookId` remembers which selected book the request will borrow; null means none. |
| 22 | `previousFocus` remembers where keyboard focus should return after cancellation/success. |
| 23 | Locate the new form to handle submit/validation. |
| 24 | Locate the inline error paragraph. |

The existing book-card click handler [catalogue.js:110–115](../assets/js/catalogue.js#L110-L115) reads the numeric book ID and the displayed heading's `textContent`, then calls `openBorrowModal()`. Catalogue search/category/loading code remains the entry to that handler, not a new borrowing endpoint.

### Work out the library's calendar date

See [catalogue.js:121–133](../assets/js/catalogue.js#L121-L133).

| Line | Explanation |
|---|---|
| 121 | `libraryToday(now = new Date())` normally uses the current instant, but accepts a supplied instant for a test. |
| 122 | Create a date formatter and store its component parts. |
| 123 | Explicitly use Africa/Lagos and request year, two-digit month and day. Browser/device timezone does not decide the library's “today”. |
| 124 | `formatToParts(now)` returns labelled pieces such as `{type: 'year', value: '2026'}` instead of one locale-dependent date string. |
| 125 | `map()` converts each piece to `[type, value]`; `Object.fromEntries()` turns these pairs into a lookup object. |
| 126 | Assemble the exact date-input format `YYYY-MM-DD`. |
| 129 | Declare the day-addition helper. |
| 130 | Construct a date at noon UTC from the supplied calendar date; `Z` means UTC. |
| 131 | Add days using **UTC** getters/setters. JavaScript carries across month/year/leap-day boundaries; mixing local methods with UTC output could shift dates unexpectedly. |
| 132 | Convert to ISO text and take its first ten characters, returning only `YYYY-MM-DD`. |

This is calendar-day arithmetic, not “add N times 24 hours in whatever timezone the user has”. The functions display suggested limits; MySQL supplies the backend's authoritative calendar when validating and saving a loan.

### Open and close the dialog

See [catalogue.js:135–155](../assets/js/catalogue.js#L135-L155).

| Line | Explanation |
|---|---|
| 135 | Accept the selected book ID and title. |
| 136 | Save the currently focused element, normally the Borrow button. |
| 137 | Store the book ID for submission. |
| 138 | Display the title as text. |
| 139 | Obtain today's Lagos calendar date. |
| 140 | Read `data-max-loan-days` from the overlay; data attributes are strings, so convert to Number. |
| 141 | Format the calendar date at local noon for the borrow-date label. The date string was already chosen using Lagos; this display conversion does not determine the permitted day count. |
| 142 | Set `min` to tomorrow. |
| 143 | Set `max` to today plus the configured maximum. |
| 144 | Default the input to that maximum date; the student can change it. |
| 145 | Remove an error left by a previous attempt. |
| 146 | Show the overlay as flex so its existing centring CSS applies. |
| 147 | Focus the date input, making it immediately editable. |
| 150 | Declare the close helper. |
| 151 | Do not close while Confirm is disabled for a pending request. This applies to Escape as well as normal close calls. |
| 152 | Hide the overlay. |
| 153 | Clear the selected book ID. |
| 154 | Return focus to the saved element if it exists. Optional chaining `?.` avoids calling a method on null. |

### Submit once, preserve failed selections, refresh on success

See [catalogue.js:157–188](../assets/js/catalogue.js#L157-L188).

| Line | Explanation |
|---|---|
| 157 | Declare an asynchronous submit handler that receives the form event. |
| 158 | `preventDefault()` stops the normal full-page form submission; this action uses the API instead. |
| 159 | Return if no book is selected, a request is already pending, or `reportValidity()` finds a blank/out-of-range/invalid input. `||` short-circuits: later checks are not needed once an earlier one is true. |
| 160 | Disable Confirm before starting the request so another submit is ignored by the guard. |
| 161 | Disable Cancel while the request is in progress. |
| 162 | Clear any old inline error. |
| 163 | Start with `successful = false` so an exception never takes the success path. |
| 164 | Start guarded network handling. |
| 165 | Fetch the existing borrow endpoint. |
| 166 | Use POST because borrowing changes saved data. |
| 167 | Tell the endpoint the body is JSON. |
| 168 | Serialise both `book_id` and the selected input's `due_date`. This is the line that connects the new date control to existing backend support. |
| 169–170 | Finish request options and parse the response JSON. |
| 171 | Copy `json.success` to the outcome flag for use after cleanup. |
| 172–173 | On success, show the backend's confirmation. |
| 174–175 | On a business-rule/input rejection, put the backend explanation in the error paragraph. The modal and chosen date remain in place. |
| 177–179 | Catch unexpected/network/parsing errors, log the development error, and show retry text without clearing the selection. |
| 180 | Enter `finally`; it runs after a response or exception. |
| 181–182 | Re-enable Confirm and Cancel. Doing this first lets the close helper proceed after success. |
| 184–185 | Only after confirmed success, close the dialog and restore focus. |
| 186 | Rerun the existing search so availability counts reflect the borrowed copy. |

The browser's date input is convenience validation, **not** an access/security boundary. A caller could bypass the page and send an API request directly. The backend must still check dates, the three-book limit, unpaid fines, ownership and available copies.

### Events, Escape and Tab boundaries

See [catalogue.js:190–205](../assets/js/catalogue.js#L190-L205).

| Line | Explanation |
|---|---|
| 190 | Listen to form submission, not just a Confirm click; pressing Enter also uses the handler. |
| 191 | Connect Cancel to the close helper. |
| 192 | Listen for key presses that reach the overlay. |
| 193 | Escape requests closing. The close helper refuses it while borrowing is pending. |
| 194 | Apply boundary handling only for Tab. |
| 195, 196 | Identify the intended first and last focus targets: date input and Confirm. Cancel lies between them in normal document order. |
| 197 | Detect Shift+Tab when focus is at the first target. |
| 198–199 | Stop the normal move out of the dialog and focus the last target. |
| 200 | Detect forward Tab when focus is at the last target. |
| 201–202 | Stop the move out and focus the first target. |
| 203–205 | Finish the conditions/listener. |

[Lines 209–217](../assets/js/catalogue.js#L209-L217) retain search-form submit handling and initial category/search loading. The new borrow form does not replace those behaviours.

This provides the tested Escape and first/last Tab boundary behaviour. It is not a claim of complete accessibility certification: background content has not been made inert, and human keyboard/screen-reader acceptance remains a separate check.

### Existing backend date support reused, not rewritten

[api/loans/borrow.php:8–17](../api/loans/borrow.php#L8-L17) requires POST/student login, reads validated book ID and optional due date, and calls `Loan::borrow()`. The session supplies the student ID; JSON does not get to choose a different student.

[Loan::loanDaysFor():215–234](../classes/Loan.php#L215-L234) returns `MAX_LOAN_DAYS` for an omitted date, rejects invalid calendar strings, asks MySQL for `DATEDIFF(chosen_date, CURDATE())`, and accepts only 1 through the configured maximum. [Loan::borrow():28–96](../classes/Loan.php#L28-L96) then checks/locks the student's borrowing state and an available copy, saves the loan/copy change in one transaction, and returns the saved record. A rejected request rolls back its changes.

PHP uses Africa/Lagos in [config.php:22](../config/config.php#L22). MySQL's `CURDATE()` is the authoritative database day; PHP setting its own timezone does not automatically configure MySQL's session timezone. If the local database clock/timezone differs from the library calendar, check that deployment configuration rather than weakening date validation. The browser and integration tests check the configured local setup, not every possible server timezone.

## 7. Shared CSS: why each declaration was added

See [style.css:332–377](../assets/css/style.css#L332-L377) and [style.css:433–447](../assets/css/style.css#L433-L447). These selectors use existing page classes, avoiding separate page-specific stylesheets.

| Rule/declaration | What it does and where it fits |
|---|---|
| `.table-scroll { overflow-x: auto; }` | Allow a wide history table to scroll inside its wrapper when necessary. It does not hide any columns. |
| `.visually-hidden` / `position: absolute` | Take the caption out of normal visual flow while keeping it in the document. |
| `width: 1px; height: 1px; padding: 0` | Make its visual box tiny without extra padding. |
| `overflow: hidden; clip-path: inset(50%)` | Clip the caption's visible contents rather than using `display: none`, which would also hide it from assistive technology. |
| `white-space: nowrap` | Avoid a wrapped hidden caption affecting its tiny box. |
| `.receipt-details { display: grid; }` | Arrange the alternating `dt`/`dd` nodes in rows/columns. |
| `grid-template-columns: minmax(0, 1fr) minmax(0, 2fr)` | Give labels one share and values two shares of the width; zero minima allow the columns to shrink on phones. |
| `gap: 12px` | Separate the label/value cells and rows. |
| `.receipt-details dt { font-weight: bold; }` | Make labels distinguishable from values. |
| `.receipt-details dd { margin: 0; }` | Remove the browser's default description-detail indentation. |
| `overflow-wrap: anywhere` | Let long references/titles break within the value column instead of widening the whole page. |
| `.receipt-notice { padding: 12px; border-radius: 8px; }` | Add space and rounded corners to the visible success/decline notice. Existing status classes supply its status colour. |
| `[hidden] { display: none !important; }` | Make the HTML `hidden` attribute reliable even when another class sets `display`. JavaScript's `.hidden = true/false` controls both the initial history table and Print button. |
| `@media print` | Apply the following rules only when the browser renders a print layout. It does not send paper to a printer itself. |
| Print `body { background: white; }` | Use a plain page background. |
| Print `.page-header, .page-footer, #print-receipt { display: none; }` | Omit navigation, footer and the print control, keeping the actual record. |
| Print `.receipt-card { border: none; }` | Remove the decorative on-screen card border. |
| Print `.loans-page { max-width: none; padding: 0; }` | Let the record use the printable space rather than the screen layout's width/padding restrictions. |
| `.modal-box input[type="date"] { display: block; width: 100%; }` | Give the return-date control a full-width line inside the existing modal. |
| Date input `margin: 8px 0; padding: 8px` | Add spacing around/inside that input. |
| `#borrow-error { color: #842029; }` | Visually distinguish the inline borrowing error; its text/alert role also communicates the error. |
| `.buttons:disabled { opacity: 0.6; cursor: wait; }` | Make disabled pending controls look different and show a wait cursor. The actual prevention comes from the HTML `disabled` property and handler guard, not from appearance alone. |

Existing `.modal-overlay` makes a fixed, full-viewport dimmed layer and centres the modal; `.modal-box` has a maximum width with a percentage bound. These were reused. A phone-width automated check and screenshot provide evidence of specific tested layouts, not approval of every phone, zoom level or assistive tool.

## 8. What changed, and what stayed in charge

| Area | New/finished piece | Existing responsibility retained |
|---|---|---|
| Nested pages | Parent-folder URL prefix, correct assets/links and included views | `Auth` controls page entry; API guards still control data access. |
| History | Safe rows, saved fine/status display and loading/empty/error states | `Loan` reads the current student's saved history; DB view computes overdue information. |
| My Loans | Successful reference field, persistent receipt links, safer title retrieval and visible attempt result | `Fine` supplies amounts; `Payment` decides/records the simulated payment. |
| Receipt | Correct API fields, explicit failed/success status, current fine status and print layout | `Payment::getReceipt()` checks ownership and joins the saved data. |
| Due date | Labelled chooser, shared limits, chosen-date JSON, pending/error/keyboard behaviour | `Loan` validates dates and enforces availability, unpaid-fine and active-loan rules transactionally. |

The special quoted/HTML-like title used by browser tests is a **fixture**, meaning temporary sample data created for one test. It is not a change to the application seed book title or the user's database. The test asks whether characters remain literal text in history, the payment dialog and the receipt. Ordinary screenshot cases use normal fixture titles.

No real payment integration, librarian approval workflow or human acceptance sign-off was added by this work. The remaining human checklist in [TESTING.md](TESTING.md) must be completed by a student or teammate, not inferred from automated passes.
