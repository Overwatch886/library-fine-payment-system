# Test scripts: a beginner's walkthrough

This guide explains how to run the tests and what each added test file does. You do **not** need to write the scripts yourself.

Read the sections in order the first time. Later, use the commands below and return to a particular file when you want to understand it.

- [Implementation guide](../docs/IMPLEMENTATION_GUIDE.md): how the application features connect.
- [Testing record](../docs/TESTING.md): actual runs, screenshots and the human acceptance checklist.
- [API reference](../api/README.md): what each endpoint accepts and returns.
- [Development accounts](../database/README.md): the sample accounts used by the tests.

## 1. The three test layers

| Layer | What it checks | What must be running |
| --- | --- | --- |
| Unit | A small PHP calculation or amount formatter, directly | PHP only; no MySQL or browser |
| Integration | A real HTTP request, PHP business rules and saved database data together | Local MySQL; the script starts its own PHP server |
| Browser | What a student sees and clicks, using headless Chromium | Local MySQL, PHP, Node.js and the installed Playwright browser |

**Headless** means the browser runs without a visible window. It still loads HTML, applies CSS, runs JavaScript, submits forms and sends requests.

A test normally has four parts:

1. **Setup:** prepare known sample data and a student session.
2. **Action:** call a function, send a request or click a button.
3. **Expected result:** compare what happened with what should happen. This comparison is an **assertion**.
4. **Cleanup:** close owned resources so one test run does not leave its database or server behind.

For example, returning an overdue book is not tested just by checking a success message. The scripts also check the fine amount and, in the backend suite, the database row.

## 2. Run the existing scripts

Open a terminal in the **project root**, not inside the tests folder.

### Prerequisites

- PHP 8.2 or later available as `php` in the terminal. This project was run with PHP 8.2.12.
- PHP extensions `pdo_mysql` and `curl` for integration/browser tests. Unit tests do not open a database connection.
- Local MySQL/MariaDB running, normally through XAMPP.
- A local database account allowed to create and drop **generated test databases**, as well as create and use their tables. It need not be used to alter the application database.
- Node.js 18 or later and npm for the browser suite. This project was run with Node.js 22.
- Playwright dependencies and its Chromium headless shell installed for browser tests.

Check installed tools/extensions without changing anything:

```sh
php -v
php -m
node --version
npm --version
```

`php -v` prints the PHP version. `php -m` lists enabled extensions; look for `PDO`, `pdo_mysql` and `curl`. The other two commands print the Node/npm versions.

**Apache does not need to run for these suites.** The test launcher starts a separate PHP built-in server. You also do not need to import the schema into the application's database to run the tests.

### Unit tests

```sh
php tests/unit.php
```

This runs the direct PHP assertions. There are 13 named unit checks in the current script.

### Database/API tests

Start **local MySQL** in XAMPP, then run:

```sh
php tests/run.php integration
```

The launcher creates an isolated database, starts its server, runs 23 named integration checks and attempts cleanup. Do not start the application against that generated database yourself.

### Browser tests

The dependencies and headless shell have already been installed for this development session. **Do not reinstall them for every test run.**

For a fresh checkout **only when they are missing**:

```sh
npm ci --ignore-scripts --no-audit --no-fund
node node_modules/@playwright/test/cli.js install chromium --only-shell
```

- `npm ci` installs the exact package versions in the lockfile rather than choosing newer versions.
- `--ignore-scripts` stops dependency installation hooks from running.
- `--no-audit` and `--no-fund` avoid extra audit/funding requests.
- The second command explicitly installs Chromium's headless shell, not a set of full browsers. Do not add Firefox, WebKit or full Chromium when they are not needed.
- Installation uses internet data; normal test runs use the local loopback server. On a mobile-data connection, install only what is missing and monitor your device's usage. File sizes from an installer are not a measurement of total connection usage.

With local MySQL running:

```sh
php tests/run.php browser
```

This runs 18 named browser checks and regenerates four screenshot files under [docs/screenshots](../docs/screenshots/). Successful and failed-payment screenshots are written in both root/subfolder cases; the later case replaces the earlier file at the same name.

**Do not launch the suite against a manually chosen app URL.** Use this PHP launcher, not a bare Playwright test command: it supplies the generated database, ownership token and test-server URL together.

The npm shortcuts in [package.json](../package.json) run the same three PHP commands: `npm run test:unit`, `npm run test:integration`, and `npm run test:browser`.

### Syntax checks are different from tests

```sh
php -l tests/run.php
node --check tests/browser/library.spec.js
```

`php -l` checks that PHP can parse the file, without running the test. `node --check` does the same for JavaScript. Passing a syntax check alone does not prove borrowing or payments work.

## 3. Read the output

The PHP runner prints lines like:

```text
PASS: Fine for 7 days late
13 passed, 0 failed
```

`PASS` means all assertions inside that named test finished without an exception. A test may contain several assertions: **13 tests does not mean only 13 comparisons**.

`FAIL` prints the test name and the error message. The PHP runner continues to the next test so you can see more than one problem in a run.

The process also returns an **exit code**:

- `0`: that command completed successfully.
- `1`: a test, setup or cleanup failed in the PHP runner.
- The browser launcher forwards the browser process's exit code. Treat any nonzero value as failure.

In PowerShell, inspect `$LASTEXITCODE` immediately after the command. In Bash, inspect `$?` immediately after it. These values describe the last command, not every command you have run.

Normal progress uses **standard output** (`print` in PHP). Setup/cleanup diagnostics use **standard error** (`fwrite(STDERR, ...)`). Both appear in the terminal, but automated tools can handle them separately.

Only a completed run is evidence of a pass. Missing MySQL, missing Chromium, a terminated process or an unfinished run must not be reported as a successful test.

## 4. Safety: what the scripts are allowed to change

**Never execute [database/schema.sql](../database/schema.sql) unchanged as a test setup command. It drops and recreates `ui_library`. Never point these tests at ngrok, a shared server or the live application database.**

The tests do use the local database server, but they create a **different database** on that server. [TestEnvironment.php](support/TestEnvironment.php) gives each run a name beginning `ui_library_test_` followed by 16 generated hexadecimal characters.

Before resetting or deleting it, the code checks:

1. The database name matches that exact generated-name pattern.
2. The ownership token has the expected format.
3. `SELECT DATABASE()` reports that generated database, not `ui_library`.
4. The `test_run_owner` table holds this run's token.
5. For instance-level cleanup, the environment records that it created the database.

If a check fails, cleanup **refuses deletion**. This is called **failing closed**: a leftover test database is safer than deleting an unverified one. Do not weaken the checks to clear an error.

The reset helper removes the real-database commands from the application SQL, rejects unexpected remaining database directives, and drops only a fixed list of application tables/views inside the verified test database. The ownership table is kept between test resets.

Other boundaries:

- The server binds to `127.0.0.1` on a generated local port; it is not exposed through a public tunnel.
- Session files and server logs live in this run's own temporary directory.
- The browser's PHP fixture helper runs from the terminal, not through a database-reset HTTP endpoint.
- Test scripts are blocked over the test web server; Apache has its own tests-folder rule too.
- The test server and fixture processes receive local connection settings through environment variables. Do not print or commit those settings or actual credentials.
- The development logins come from the documented seed accounts, not real student credentials.
- Payments are simulated. Never use actual card details. Card inputs are not stored by the payment class, and browser traces/videos are disabled to avoid saving request bodies containing simulated digits.
- The launcher uses `finally` to attempt normal cleanup, including after caught failures. A forced process kill, machine shutdown or cleanup failure is **not** guaranteed to leave everything removed.

If cleanup refuses or fails, read the error and inspect the specific generated resources. Do not delete unrelated databases, session folders or PHP processes.

## 5. A few symbols you will meet

These explanations apply throughout the line/block walkthroughs below. Comments describe intent; blank lines and braces organise code rather than perform extra app actions.

| Syntax | Plain meaning |
| --- | --- |
| `$name` | A PHP variable holding a value |
| `new ClassName()` | Create an object: one working instance of that class |
| `$object->method()` | Call a method on that object |
| `ClassName::method()` | Call a static method without creating an object |
| `function (...) use (...)` | A small function that carries specified outer variables into its body |
| `callable` | A value that PHP can call as a function |
| `: void`, `: array`, `: int` | The declared kind of return value: nothing, array or integer |
| `mixed` | The value may have different types |
| `===`, `!==` | Same/different **value and type**; integer `1` is not string `'1'` |
| `??` | Use the fallback on the right if the left value is missing or null |
| `.` in PHP | Join strings |
| `foreach` | Repeat a block for each value in a list |
| `try / catch / finally` | Attempt work, handle an error, then perform the cleanup block |
| `throw` | Stop the current path with an exception that the caller can handle |
| `async / await` in JavaScript | Wait for browser/request work to finish before doing the next step |
| `{ page }` | Take the named browser-page property from the object Playwright supplies |
| `expect(...)` | Ask Playwright to check an expected result |
| `locator(...)` | Describe which page element the test wants to use |
| `?` inside prepared SQL | A value placeholder supplied separately by `execute()`; not string concatenation |

## 6. Unit script, line by line

Source: [unit.php](unit.php).

| Line/block | What it does and why |
| --- | --- |
| `<?php` | Starts PHP code. |
| `PHP_SAPI !== 'cli'` guard | Rejects a web request with 404 and exits. The script is for terminal execution. |
| First `require_once` | Loads the app configuration and autoloader from a path based on this file's directory. It can then find `Fine` and `AppException`. Loading the classes does not itself instantiate a database connection. |
| Second `require_once` | Loads our explicit assertion/reporting class once. |
| `$runner = new TestRunner()` | Creates fresh pass/fail counters for this suite. |
| First `foreach` | The keys are days late; the values are expected fine amounts. It produces eight named tests. |
| `$runner->test(..., function ... use ...)` | Names this case and hands its body to the runner. `use ($days, $expected)` carries this iteration's values into the callback. |
| `TestRunner::same(...)` | Calls the real fine calculation and compares its result with the expected integer. |
| Second `foreach` | Each pair contains an amount and expected formatted string. `as [$amount, $expected]` separates the pair into two variables. It produces four tests. |
| Format callback/assertion | Calls the real amount formatter and checks its exact output, including both decimal places. |
| `AppException` callback | Creates an expected application error, then checks the message and HTTP status were preserved. It is one named test with two assertions. |
| `exit($runner->finish())` | Prints the totals and returns the runner's success/failure code to the terminal. |

### One short test explained completely

This is the same pattern used by the calculation loop, with one case written out so it is easier to follow:

```php
$days = 7;
$expected = 700;
$runner->test('Seven days late', function () use ($days, $expected): void {
    TestRunner::same($expected, Fine::calculate($days));
});
```

1. `$days = 7;` is the input. No database record is needed.
2. `$expected = 700;` is the answer the app's current fine rule should produce.
3. `$runner->test(...)` supplies a readable name and a callback. `use` makes the two setup values available to that callback; `: void` says it returns no data.
4. `Fine::calculate($days)` is the **action**. `same($expected, ...)` is the **assertion**. A wrong answer throws an exception and the runner records a failure.
5. `});` closes the callback and the method call. This case allocates no database/server resources, so it needs no special cleanup.

### Every unit case

| Cases | Expected results | Reason |
| --- | --- | --- |
| Days `-5`, `0` | `0`, `0` | Early/on-time results do not create a negative fine. |
| Days `1`, `7`, `49` | `100`, `700`, `4900` | Normal multiplication by the daily rate. |
| Days `50`, `51`, `1000` | `5000` in each case | The boundary and values above it respect the cap. |
| Amounts `0`, `700` | `'0.00'`, `'700.00'` | Even whole amounts have two decimal places. |
| Amount `12.345` | `'12.35'` | The formatter rounds, rather than merely truncating. |
| Amount `5000` | `'5000.00'` | The capped amount is formatted consistently. |
| Application exception | Message `'Not found.'`, status `404` | APIs can later turn expected errors into the correct response. |

These expected values describe the current library rules. If the project deliberately changes those rules, review the tests too; do not change expectations simply to hide a defect.

## 7. The small PHP assertion runner

Source: [TestRunner.php](support/TestRunner.php).

| Line/block | Explanation |
| --- | --- |
| Class and private counters | Holds `$passed` and `$failed`, both initially zero. `private` means callers cannot edit them directly. |
| `test(string $name, callable $test): void` | Accepts a human-readable label and work to execute. |
| `try` then `$test()` | Runs the supplied callback. This includes its actions and assertions. |
| `$this->passed++` and `print` | Only after the callback finishes without error, increments the pass count and prints its name. `\n` starts a new output line. |
| `catch (Throwable $error)` | Catches assertion exceptions and other catchable PHP errors, not just one exception subclass. |
| Failure counter/print | Increments failures and prints the error message. The next named test may still run. |
| `same(mixed $expected, mixed $actual): void` | Allows different kinds of expected values but compares them strictly. |
| `if ($expected !== $actual)` | A different value **or type** fails. This is why database count assertions sometimes cast to `(int)` first. |
| `throw new RuntimeException(...)` | Produces a failure with expected/actual values. `var_export(..., true)` turns each value into readable text rather than printing it immediately. Do not put secrets in assertion messages/values. |
| `truth(bool $value, string $message = ...)` | Checks a yes/no condition; callers may supply a more helpful message. |
| `if (!$value) throw ...` | False fails; true returns normally. `!` means “not”. |
| `finish(): int` | Prints the accumulated totals and returns `0` if no failures exist, otherwise `1`. |

Why not PHP's built-in `assert()`? PHP configuration can disable those assertions. These explicit comparisons always execute when the test callback runs.

## 8. The suite launcher

Source: [run.php](run.php). This is the public terminal entry point for integration and browser suites.

| Line/block | Explanation |
| --- | --- |
| CLI guard | Stops web execution before test resources can be created. |
| Four `require_once` statements | Load app configuration/autoloading, assertions, isolated environment, and the HTTP client. |
| `$mode = $argv[1] ?? 'integration'` | Reads the first argument after the filename. Without one, it selects integration tests. |
| Strict `in_array` check, STDERR usage and `exit(1)` | Accepts only `integration` or `browser`; rejects a typo before creating a database. |
| `$environment = null` | Makes it possible to tell whether setup returned an environment successfully. |
| `$exitCode = 1` | Begins with failure, so an interrupted setup does not accidentally report success. |
| `new TestEnvironment()` and progress print | Creates the owned database/server before tests start, then reports readiness without printing connection secrets. |
| Integration branch | Creates a runner, includes the integration test file and receives the runner's result. The included file can use the local `$environment` and `$runner` variables. |
| Browser environment `foreach` and `putenv` | Exports each generated setting/token/path for the Node process and its PHP fixture children. Values are passed in memory, not saved in a credentials file. |
| `$root = dirname(__DIR__)` | Moves from this tests directory to the project root. |
| Playwright CLI path and `is_file` | Uses the installed project dependency, not a downloader. Missing dependencies raise a helpful error. |
| `proc_open(['node', $cli, 'test'], ...)` | Starts Node with arguments supplied as a list. Terminal input/output/error streams are attached, and its working directory is the project root. |
| `is_resource` check | Stops with an error if the child process could not be started. |
| `proc_close` | Waits for the child and obtains its exit status. |
| `catch` | Prints a setup/run error and local MySQL/dependency guidance. The default failure status remains. |
| `finally` with non-null check | Attempts cleanup if setup completed and returned an environment. Constructor failures have their own cleanup attempt. |
| `close()` and cleanup confirmation | Stops the owned server and removes the verified database and own temporary files during normal completion. |
| Cleanup `catch` | Forces failure and prints the cleanup error instead of pretending resources were removed. |
| Final `exit($exitCode)` | Makes the complete launcher result available to npm or other automation. |

## 9. How the isolated environment works

Source: [TestEnvironment.php](support/TestEnvironment.php). This file is setup infrastructure, not a second implementation of borrowing or payment rules.

### Properties and constructor

| Line/block | Explanation |
| --- | --- |
| PDO properties | `$admin` connects to the database server for create/drop work; public `$db` connects specifically to this generated database for assertions/reset. `PDO` is PHP's database-access object. |
| Name/token/directory properties | Record exactly which resources this instance owns. They are not request input. |
| `$server = null`, `$pipes = []`, `$created = false` | Begin with no process, no open process pipes, and no created database. This matters if setup stops part way through. |
| Public `$url` | Lets HTTP tests find the local server without hardcoding a port. |
| `random_bytes(8)` then `bin2hex` | Makes eight random bytes into 16 hexadecimal characters for a run-specific token. |
| Database/directory string construction | Uses that token for a distinct generated database and temporary directory. |
| Host-only PDO connection | Connects to the local server without selecting the real application database. Settings come from the existing config. |
| PDO options | Throw exceptions for database errors, return rows keyed by column name, and use native prepared statements instead of client-side imitation. |
| `mkdir` calls | Create this run's temporary directory and private session subdirectory. |
| `CREATE DATABASE` and `$created = true` | Create the generated database, then record ownership. Its name is internally generated, not user SQL input. SQL placeholders cannot stand in for identifiers such as database names. |
| Second PDO connection | Selects the generated name explicitly and applies the same error/fetch/prepare settings. |
| Ownership table/insert | Creates `test_run_owner` and stores the token through a prepared statement. That row survives test resets. |
| Initial `reset()` | Creates the application tables/views and inserts the sample data inside this verified database. |
| `stream_socket_server('tcp://127.0.0.1:0', ...)` | Asks the OS for a free loopback port. Failure produces a setup exception. |
| Get socket address, close socket, build URL | Saves the chosen address for the PHP server/HTTP client. The temporary port-selection socket is no longer needed. |
| `$env = getenv()` | Copies the current environment for the child process so ordinary runtime settings are preserved. |
| Database environment overrides | Replace host/name/user/pass for the child with the local server settings and generated name. The application's normal database defaults do not get rewritten. |
| Session-path environment override | Gives the child this run's session directory. Passing this through environment/router code avoids the Windows command-line PHP ini quoting issue encountered during setup. |
| Root/path calculations | Use the repo root as document root and use the support router explicitly. |
| `proc_open` argument list | Starts the current PHP executable (`PHP_BINARY`) with `-S` address, `-t` document root and router filename. No other Apache/PHP process is reused or killed. |
| Process descriptors | Creates stdin as a pipe and appends stdout/stderr to the run's own server log. The environment list is passed to the child. |
| Process check/close stdin/clear pipes | Verifies launch and closes the input pipe, which a server does not need here. |
| Deadline and `do` loop | Waits up to roughly ten seconds for startup, rather than assuming the socket is ready instantly. |
| cURL health request/options | Requests `/__test_health`, retrieves its body and limits each attempt to one second. |
| `curl_exec`, `curl_close`, `ready` check | Obtains a response, releases the temporary handle, and finishes construction when the expected health text arrives. |
| `usleep`/deadline/process-running condition | Briefly spaces startup checks and stops trying if the deadline passes or the child stops. This is a bounded readiness loop, not a hidden test retry. |
| Readiness exception | Makes a server that never becomes usable a failed setup. |
| Constructor `catch` | Tries its own cleanup because the outer launcher may never receive a completed object. |
| Nested cleanup `catch`, then rethrow | Reports a cleanup refusal without hiding the original setup error. The original exception still reaches the launcher. |

### Ownership and reset methods

| Method/block | Explanation |
| --- | --- |
| `verifyOwner()` created check | Instance methods refuse reset/delete unless this object records creating the database. |
| Call to `self::verifyDatabase` | Reuses the common selected-database/token checks. `self::` calls a static method on this class. |
| `verifyDatabase` regexes | Require the exact generated-name/token format. `/D` makes the end anchor strict. |
| Selected database/token queries | Check the PDO connection and ownership marker are the ones expected. Any mismatch throws before destructive work. |
| `reset()` | Verifies instance ownership and calls the shared `resetOwned` method. |
| `resetOwned(PDO $db, string $name, string $token)` | Lets the CLI browser helper reset the same owned database using its own PDO connection, with the same verification. |
| Schema read/newline normalisation/prefix lookup | Reads the application's SQL, converts Windows line endings for predictable matching and finds the beginning of table definitions at the People section. |
| Missing-prefix exception | Refuses an unexpectedly structured schema instead of guessing where to cut it. |
| `substr` from that offset | Excludes the schema's real-database DROP/CREATE/USE opening section. |
| Seed read/`preg_replace` | Removes exactly one expected `USE ui_library;` directive from the sample-data SQL. |
| Count and combined directive check | Requires that known directive was removed once and rejects remaining `USE`, `DROP DATABASE` or `CREATE DATABASE` instructions in either SQL string. |
| Fixed view drops | Removes only the two known application views within the selected test database. |
| Disable foreign-key checks | Allows the fixed test tables to be dropped without dependency-order errors during reset. |
| Fixed table `foreach` | Drops payments, fines, loans, copies, books, students and librarians, not arbitrary names from a request or SQL file. |
| `finally` restores FK checks | Re-enables relationship checks even if dropping a table fails. |
| Schema/seed `exec` | Recreates the app tables/views and sample records after sanitising the real-database directives. |

### Browser settings, logs and cleanup

| Method/block | Explanation |
| --- | --- |
| `browserEnvironment()` returned array | Supplies URL, local DB connection settings, generated DB name, ownership token and PHP executable to Node/the fixture helper. These are not receipt data and should not be printed into reports. |
| `serverLog()` | Reads the owned server log for diagnosis if needed. Tests do not routinely dump request data into it. Review logs for sensitive information before sharing any output. |
| `close()` resource check | Terminates/closes only the process this object opened, then clears its stored handle. |
| `$created` check | Does no database drop if creation never happened. |
| `isset($this->db)` check | If setup created a database but never produced a verifiable connection, refuse deletion and identify the generated name. |
| Ownership verification then database drop | Deletes the run's database only after the guard passes, then marks it no longer created. |
| Cleanup `finally` | Attempts removal of owned temporary files even if database cleanup throws. |
| Directory check/session-file loop | Removes files only under this run's own sessions directory. `glob(...) ?: []` means an empty match list does no work. |
| Session directory/log/directory removal | Deletes the known session directory, server log and enclosing generated directory, not other users' files. |

## 10. HTTP requests and PHP sessions

Source: [HttpClient.php](support/HttpClient.php).

A login is useful only if later requests belong to the same session. cURL's in-memory **cookie jar** remembers the PHP session cookie between calls, much like a browser.

| Line/block | Explanation |
| --- | --- |
| `CurlHandle` property | Holds one reusable cURL handle for this client/session. |
| Constructor promoted `$url` | `private string $url` both accepts and stores the base test URL. |
| `curl_init()` | Creates the handle. |
| `CURLOPT_RETURNTRANSFER => true` | Return response text to the test instead of immediately printing it. |
| `CURLOPT_COOKIEFILE => ''` | Enables cookie handling with an in-memory jar rather than loading a real person's browser cookies. |
| `CURLOPT_FOLLOWLOCATION => false` | Keeps 302 redirects visible to assertions instead of silently fetching their destination. |
| Ten-second timeout | Prevents an unavailable server from hanging a request indefinitely. |
| `request($path, $body = null, $form = false)` | One helper for reads and submissions. A null body means GET; an array, including an empty one, means POST. |
| Fresh `$headers = []` | Collects headers for this response only. Cookies still live on the reused cURL handle. |
| URL option | Joins the owned server URL with the given relative route. |
| Request-method option | Chooses GET or POST using whether a body exists. |
| POST-fields option | Uses no body for GET, form encoding for `$form`, otherwise JSON encoding. |
| Content-Type option | Tells PHP which body format is being sent; clears the custom list for GET. |
| Header callback and `use (&$headers)` | cURL calls this for each header line. `&` allows that callback to update the outer header array, not merely its own copy. |
| Colon test/`explode(..., 2)` | Separates a header's name/value at its first colon; ignores status lines that are not name/value headers. |
| Lowercase/trim/array assignment | Normalises names such as `Location` to `location` and strips spacing for consistent assertions. |
| `return strlen($line)` | Reports how many bytes of the header were processed, as cURL requires. It is not the HTTP status. |
| `curl_exec()` and false guard | Performs the request; a network-level failure throws with cURL's error. |
| Response array | Returns HTTP status, collected headers, raw text and decoded JSON. Classic HTML responses have text but usually no decoded JSON. |
| `json_decode($text, true)` | Converts a JSON object into a PHP associative array. Invalid/non-JSON text becomes null under the current helper. |
| `login()` request | Uses a documented development fixture account and role; callers can choose another sample user. Its shared password comes from the seed fixture, not real credentials. |
| Login assertions | Requires HTTP 200 and a successful JSON envelope before the caller's next action. |

A **new HttpClient** has a separate cookie jar. Tests use this to distinguish the owner of a loan/receipt from another student or librarian.

## 11. The local test router

Source: [router.php](support/router.php).

The built-in PHP server uses this router instead of Apache's rewrite/access rules. It emulates an optional project-folder URL; it is not evidence that every Apache configuration has been tested.

| Line/block | Explanation |
| --- | --- |
| Session-path read/check | Requires an existing run-owned session directory. Missing setup produces 500 and a useful text message. |
| `session_save_path` | Sets where subsequent PHP session calls store their files, before app code begins the session. |
| Root/path parsing | Finds the repo's real filesystem path and reads only the URL's path, not its query parameters. `rawurldecode` handles encoded path characters. |
| Health route | Returns `ready` for the constructor's startup check. It does not reset the database. |
| `/library-demo/` stripping | Maps a test project-subfolder URL to the same repo files. Browser-visible links still keep their subfolder context. |
| `realpath` plus inside-root check | Resolves a target and refuses missing/outside-root paths with 404; slash normalisation makes comparison work on Windows. |
| Relative-path calculation | Produces a repo-relative path for access checks and asset serving. |
| Private-folder regex | Returns 403 for tests, views, config, classes, database, docs and Git/agent metadata. PHP controllers may include views internally; direct web access is blocked. |
| PHP-extension branch | Sets script filename/name for the request, includes the public controller/API file, then tells the server the router handled it. |
| Assets branch/type mapping | Sends the known CSS/JS/SVG/PNG MIME type, or a binary fallback, then streams the asset. |
| Final 404 text/`return true` | Refuses other files rather than handing them to unrestricted static serving. |

## 12. Integration helpers and every case

Source: [integration.php](integration.php). Run it through the launcher, not by itself.

### Shared lines before the cases

| Line/block | Explanation |
| --- | --- |
| CLI/`isset` guard | Only proceeds when the launcher supplies a ready environment and runner. |
| `apiResult($response, $status, $success)` | A reusable assertion helper: check the exact HTTP status, require decoded JSON to be an array, check the success flag, then return `data` or an empty array. |
| `dbValue(...)` prepare | Prepares SQL against the owned test DB. Supplied values are separate parameters, never joined into SQL text. |
| `execute` then `fetchColumn` | Runs that query and returns its first column: useful for a count, status or date. |
| `$integrationTest = function (...) use (...)` | Captures the already-created environment/runner, so callers don't use ambiguous global state. |
| Inner runner callback | Captures the test and environment, resets sample records **before** the action, then invokes the case with that environment. |
| Closing `};` | Finishes the helper function value and assigns it to `$integrationTest`. |

Each callback follows the same pattern: `new HttpClient` for a fresh session, `login` for a known sample user, requests for actions, and `apiResult`/`same`/`truth` for assertions. The table below accounts for every case, including cases produced by loops.

| # | Setup/action lines | Assertions and how they connect |
| --- | --- | --- |
| 1 | Create client; submit wrong login; call valid login; request `me`; POST logout; request `me` again. | Wrong login is 401/false; profile matric and role match the logged-in fixture; logout is 200; later `me` is 401. The matric is in `profile`, not the login response's `id` field. |
| 2 | Loop over root and `/library-demo`; create a new client each time; open history, manually open login twice, then receipt. | History gives 302 with `../login.php`; login text shows flash once only; receipt redirects with the same nested prefix. One named test covers both prefixes. |
| 3 | Log in as librarian; open student history and call student APIs; use a separate student client on the admin page. | Web pages redirect appropriately; student-only API calls reject the librarian with 403. |
| 4 | Attempt unauthenticated search; log in; search encoded `Clean Code`. | Unauthenticated request is 401; authenticated result is one matching title with two available copies. `(int)` makes the copy count's type deliberate. |
| 5 | Borrow loop with `$days = null`; omit `due_date`. Calculate expected database calendar date with `?? 14`. | API returns 201 and the default due date; the loan stores it and the chosen copy is borrowed. |
| 6 | Borrow loop with `$days = 1`; add computed `due_date`. | The shortest permitted date is returned and persisted; copy status changes. |
| 7 | Borrow loop with `$days = 14`; add computed `due_date`. | The longest permitted date is returned and persisted; copy status changes. |
| 8 | Invalid-date loop: today (`0` days); save loan count first. | 400/false; no new loan and still two available copies. |
| 9 | Same loop: past (`-1` day). | Same rejection and unchanged database state. |
| 10 | Same loop: too far (`15` days). | Same rejection and unchanged database state. |
| 11 | Same loop: impossible calendar string. | Same rejection and unchanged database state. |
| 12 | Same loop: words instead of a date. | Same rejection and unchanged database state. |
| 13 | Sample user starts with two active loans; borrow one, then attempt another. | Third loan succeeds; fourth is 409; active count is three and rejected book availability remains two. |
| 14 | User with unpaid fine attempts borrowing; separate eligible user attempts unavailable book. | Both give 409 and total loans remain at the seed count. |
| 15 | Student attempts someone else's return, then their own on-time loan, then repeats it. | 404 for other student's loan; own return has no fine, loan returned/copy available; repeat is 409. |
| 16 | Overdue user lists two accruing fines; loop returns a moderately late and very late loan; repeat each return. | Saved amounts are 600.00 and capped 5000.00; repeat 409; one fine row per loan; accruing list ends empty. `count`/`fetchColumn` assertions check persisted state, not only messages. |
| 17 | Unpaid-fine user submits a simulated declined card then a valid simulated retry; list fines; reopen receipt; attempt paying again; query all payment rows. | Decline 402 is recorded but fine unpaid; retry 201 makes fine paid; list supplies that success reference; receipt has actual `payment_channel`/`time_paid` fields and student/book details; repeat 409; neither simulated digit string occurs in stored payment rows. An earlier seed attempt explains the count of two after the new decline. No request bodies are printed into this guide. |
| 18 | Channel loop chooses bank transfer for the unpaid fine. | Successful payment; reset gives this case its own unpaid starting fine. |
| 19 | Same loop chooses USSD. | Successful payment with the same clean starting data. |
| 20 | Loop submits unsupported channel, too-short simulated input and negative ID; then tries another student's fine. | Each invalid body is 400; other's fine is 404; total attempt count stays at the seed count. |
| 21 | Read an existing receipt reference from the test DB; request anonymously, as another student, with missing/nonexistent reference, as owner, and as librarian. | 401 anonymous; 404 wrong owner; 400 missing input; 404 nonexistent reference; 200 owner and librarian. `urlencode` safely places the generated reference in a URL query. |
| 22 | Student fetches history; verifies every row belongs to them; returns one loan; fetches again; separate paid-fine student fetches theirs. | Own history has two rows, includes returned status after action, and the paid student's saved fine amount/status are present. `array_column` gathers statuses and strict `in_array` checks membership. |
| 23 | Loop requests root/subfolder assets, then private scripts/views/config. | Assets 200; protected files 403 under this test router. This does not claim production Apache has identical configuration. |

Why the due-date loops calculate expected dates through MySQL: the seed/loan logic uses the database calendar. Browser helpers use Lagos time. Keep the local database calendar aligned with the library calendar; a timezone/midnight disagreement needs diagnosis, not hardcoded new test dates.

The current integration cases do not cover every possible method, malformed raw JSON body, deployment or concurrency scenario. Do not describe the suite as exhaustive API/security certification.

## 13. Package files and browser configuration

### Development packages

Sources: [package.json](../package.json), [package-lock.json](../package-lock.json).

| Key/block | Explanation |
| --- | --- |
| `name` | Identifies this local package; it does not alter PHP routing. |
| `private: true` | Helps prevent accidentally publishing the project as an npm package. |
| `scripts` | Names the three shortcuts that still go through PHP's controlled launchers. |
| `devDependencies` | Playwright is for development/testing, not a runtime requirement for a student browsing the PHP site. |
| Exact version | Pins `@playwright/test` to 1.58.2 rather than automatically taking a future version. |
| Lockfile version/packages | Records npm's dependency tree, exact versions, archive sources and integrity hashes so a fresh install is reproducible. |
| Playwright/core entries | List the matching browser test engine and driver dependencies. |
| Optional `fsevents` entry | A macOS-only optional dependency; its presence in the lockfile is not evidence it was installed on Windows. |
| Integrity values | Let npm check package archive contents. They are hashes, not passwords. |

### One-worker, local-only configuration

Source: [playwright.config.js](../playwright.config.js).

| Line/block | Explanation |
| --- | --- |
| `require`/`defineConfig` | Loads Playwright's configuration helper. |
| Environment URL read/regex | Requires an HTTP URL at `127.0.0.1` with a numeric port; rejects a missing or remote URL with instructions to use the PHP launcher. Database authenticity is additionally checked by the PHP ownership guards. |
| `module.exports` | Exposes this configuration to the Playwright CLI. |
| `testDir` | Finds browser cases inside `tests/browser`. |
| `fullyParallel: false`, `workers: 1` | Run cases serially because each resets the same generated database. |
| `retries: 0` | A failure is not silently retried into a pass. |
| `reporter: 'list'` | Prints readable per-test progress. |
| `baseURL` | Makes `page.goto('/login.php')` use the owned loopback server. |
| Browser/viewport | Chooses headless Chromium under the normal runner defaults and a 1280 × 900 desktop viewport. |
| `timezoneId` | Makes browser date behaviour use Africa/Lagos. |
| Trace/video/automatic screenshot `off` | Avoid saved request-body traces/videos and automatic screenshots. Explicit evidence screenshots are still taken by the helper at selected safe points. |

## 14. The browser-to-PHP fixture bridge

Source: [browser-fixture.php](support/browser-fixture.php).

A **fixture** is known sample data used to make a test repeatable. The bridge lets Node ask PHP to prepare/check that data, using PHP's existing PDO configuration rather than adding a JavaScript MySQL driver.

| Line/block | Explanation |
| --- | --- |
| CLI guard/requires | Refuses web execution and loads app configuration/autoloading plus the environment class. |
| Name/token reads | Receives the generated values from the launcher's environment, not page input. |
| Missing/pattern check | Rejects missing or unexpected database setup before obtaining data. |
| PDO connection/selected database/token check | Requires the connection to select exactly that generated DB and match its ownership marker. All actions below are behind this guard. |
| `$argv[1] ?? ''` | Chooses the requested helper action from terminal arguments. |
| `reset` branch | Calls the shared verified reset, then inserts one extra student with no loan history by copying a sample password hash/profile fields. This account exists only in the generated DB, not the submission seed. |
| Reset JSON response | Returns the database's calendar date so browser expectations start from known setup. |
| `quoted-title` prepare/execute | Changes book 5's title only for the special rendering-safety case. The unusual string is a prepared value, not executable SQL. Normal payment evidence uses normal titles after the next reset. |
| Update JSON response | Gives Node a valid JSON success value; it does not create a payment or app record. |
| `loan` prepared query | Finds the most recent loan for the provided student/book, returning only due date/status. Book ID and matric arguments are values, never SQL text. |
| Loan `execute`/JSON | Uses argument fallbacks and returns the fetched row for a database-persistence assertion. |
| Unknown-action exception | Refuses operations the helper does not explicitly support. |
| `catch`/STDERR/exit | Reports a helper failure and returns nonzero; `execFileSync` then fails the browser test rather than pretending setup succeeded. |

## 15. Browser script: helpers and an annotated example

Source: [library.spec.js](browser/library.spec.js).

### Shared lines before the tests

| Line/block | Explanation |
| --- | --- |
| `test`/`expect` import | Loads Playwright's test registration and assertions. |
| Child-process/path/fs imports | Use Node's built-in tools to call the PHP helper and save local screenshot evidence. No extra database package is needed. |
| `fixture(action, ...args)` | Accepts an action and any remaining arguments as a list. |
| `execFileSync` | Runs the exact PHP executable with the helper path and argument list, without joining a shell command. It waits for output and throws on a failed child process. |
| `encoding: 'utf8'` then `JSON.parse` | Converts the helper output into a usable JavaScript object. |
| `let today` | Holds the clean fixture's calendar date for the current case. |
| `test.beforeEach` | Resets the generated database before every named case. One worker prevents overlap. Playwright provides a fresh normal test page/context for each case. |
| Reset assignment | Stores the helper's returned `today`, not a hardcoded run date. |
| Dialog event handler | Accepts the app's confirmation/alert dialogs so a headless test can proceed. This is test automation, not a change to user-facing confirmation behaviour. |
| `login` defaults | Uses an eligible fixture student and optional root/project-folder prefix. |
| `goto`/two fills/click | Loads the real classic PHP login form and submits sample credentials. |
| URL and book-card waits | Requires redirect to catalogue and asynchronous search rendering before the next action. |
| `openBorrow` title-filtered locator | Finds the Clean Code card rather than assuming its visual position. |
| Borrow click/dialog wait/date return | Opens its form, waits for visibility and returns the date-input locator to the case. |
| `saveScreenshot` directory | Builds the local evidence path; recursive directory creation is harmless if it already exists. |
| Screenshot options | Saves a real rendered screenshot. `fullPage = true` is the default; the mobile case passes false to capture just the viewport, not a very tall background catalogue. |

### A small browser test, line by line

This existing case checks that an incorrect classic login remains understandable:

```js
test('incorrect classic login stays on page with useful error', async ({ page }) => {
    await page.goto('/login.php');
    await page.locator('#username').fill('214567');
    await page.locator('#password').fill('incorrect');
    await page.getByRole('button', { name: 'Log In', exact: true }).click();
    await expect(page.locator('#login-error')).toHaveText('Incorrect username or password.');
});
```

1. `test(...)` registers one named case. `async ({ page })` receives Playwright's page and allows waiting for browser work. Before this body, the shared reset has already prepared clean data.
2. `goto` loads the real page through the owned server.
3. The username locator fills a documented seed student, not a real user.
4. The password locator deliberately fills an invalid value: this is the scenario being tested.
5. The accessible-role locator finds the exact Log In button and clicks it. `await` waits for the interaction.
6. The assertion waits for the error element's text to match exactly. A missing/wrong message fails the test.
7. `});` closes the case. Playwright releases its normal test page/context; the outer PHP launcher later removes the shared run's DB/server.

`await expect(locator)...` checks retry briefly until the UI reaches the expected state or the assertion timeout expires. That is **waiting for asynchronous rendering**, not rerunning a failed test case.

## 16. Every browser case and its blocks

Loop expansion gives 18 named cases. Some cases check several states.

| # | Case/block actions | What the assertions establish |
| --- | --- | --- |
| 1 | Root login/navigation loop: collect page errors; open protected history; observe login; reload; sign in; follow history and catalogue links. | Login flash appears once; two history rows/table render; wrapper has computed horizontal-scroll CSS; catalogue loads; collected uncaught page errors are empty for this case. |
| 2 | The same body with `/library-demo`. | Nested redirects/assets/navigation work with the emulated project prefix. The regex's escaped dot means the literal `.php` suffix. |
| 3 | Incorrect classic login example above. | Wrong credentials leave a clear error on the login page. |
| 4 | Login/open date; compute expected min/max; check default/focus; focus confirm; Tab and Shift+Tab; Escape; evaluate calendar helpers with explicit boundary dates. | Tomorrow-to-14-day limits/default, boundary keyboard focus wrapping, cancellation and restored button focus work; leap/nonleap February, month/year change and Lagos midnight conversion are correct for the tested inputs. This is not a full accessibility audit. |
| 5 | Choose input minimum; start `waitForRequest` **before** confirm click; inspect JSON; wait for modal to close; query loan fixture; navigate history/My Loans; return; query/navigate again. | Exact book ID/chosen date is sent, DB stores it, active history shows Borrowed, return clears active loans, DB status becomes returned and history shows Returned. The request wait must be registered first to avoid missing a fast request. |
| 6 | Count borrow requests; fill today; click confirm; evaluate browser validity. | Date underflow is true, no API borrow request was sent and modal remains open. This checks client feedback, not replacement of server validation. |
| 7 | Unpaid-fine fixture chooses date and submits; then intercept borrow route with `abort` and submits again. | Real backend rejection shows unpaid-fine error and preserves date. Simulated network failure shows retry message, re-enables confirm and preserves date. The case proves retry is possible, not that it then performs a successful retry. |
| 8 | Create a promise gate; intercept borrow; increment count and wait on gate; submit; dispatch extra form-submit event; Escape; release gate. | Pending confirm/cancel disabled, modal cannot disappear during submission, duplicate event does not send a second request; normal continuation closes modal. `release` is the resolver saved by `new Promise`. |
| 9 | Empty-history fixture; pause route, observe loading, release; remove interception; supply API failure JSON; reload; remove; abort; reload; remove; supply 401; reload. | Loading/empty/API-error/network-error/login-redirect states render as designed. `unroute` removes each simulation before the next one. `fulfill` creates a deliberate test response; it is not evidence the actual API failed. |
| 10 | Overdue user opens loans, verifies two accruing entries, returns Macroeconomics, opens history and saves screenshot. | Capped saved fine is shown as 5000.00; returned history row shows Unpaid. This is a real return on test data, not route simulation. |
| 11 | Call quoted-title fixture only here; inspect history; open payment modal; use simulated USSD; open receipt; inspect elements/global flag. | Title is literal in all three places; no injected image appears; the sentinel JavaScript property is undefined. The unusual data is reset before later ordinary screenshot cases. |
| 12 | Root payment loop: decline simulated card, check cleared field, open failed record and screenshot; return to My Loans; succeed; reload; open persistent receipt and screenshot; stub `window.print`; click; emulate print media. | Failed record says it did not pay; successful record/amount/channel appear; paid fine has no Pay button and retains receipt link after reload; print click calls function; print CSS hides header/footer/button while keeping details visible. |
| 13 | Same payment body under `/library-demo`. | Receipt/navigation/payment results also work with nested project URLs. Both screenshots share filenames, so this later case becomes the saved payment evidence. |
| 14 | Channel loop selects bank transfer, checks hidden card input, confirms. | No card field needed; successful result supplies receipt link and fine displays Paid. |
| 15 | Same body selects USSD. | Same expectations for the second non-card channel. |
| 16 | Receipt with no reference; missing reference value; intercepted network abort on reload. | Helpful state text appears and Print remains hidden each time. A failed load is not presented as a printable receipt. |
| 17 | Paid-fine owner opens persistent receipt; save its URL; create a second browser context/page; log in as another fixture; open same URL; close context in `finally`. | Owner sees amount; other student sees not found and no Print. Separate context means separate cookies; sharing one page would not test simultaneous session separation. |
| 18 | Set 390 × 844 viewport; open borrow and save viewport screenshot; Escape; visit history/My Loans; open paid receipt. | Documents checked after navigation have no horizontal page overflow and receipt details appear. The modal screenshot captures its visual state; width comparisons are not full mobile usability, screen-reader, touch or all-breakpoint certification. |

### Why the special browser tools are used

- `page.evaluate(...)` runs a small function **inside the browser**. It can read `window`, the actual DOM, browser validity or global app helper functions. A returned value crosses back to Node for assertions.
- `postDataJSON()` inspects the request's body object; tests compare it without printing simulated payment data into a report.
- `route.continue()` eventually sends the actual request to PHP. `route.abort()` deliberately models a network failure. `route.fulfill()` supplies a controlled response to exercise the page's error states.
- `new Event('submit', { cancelable: true })` deliberately sends another form event while the first borrow is pending. It checks the JavaScript guard rather than relying only on a disabled button.
- `window.print` is replaced temporarily with a small function setting a test flag. That proves button wiring, not an operating-system print dialog, physical printer or PDF export.
- `emulateMedia({ media: 'print' })` applies print CSS in the browser. A human still needs to assess the real print preview.
- Only the navigation pair explicitly collects uncaught page errors. Do not claim every case checks every console message.

## 17. Access rules and ignored files

Sources: [tests/.htaccess](.htaccess), [.gitignore](../.gitignore), [config/config.php](../config/config.php).

| Added line/block | Explanation |
| --- | --- |
| `Require all denied` | Apache denies direct web requests into tests. The built-in test server does not read `.htaccess`; its router separately blocks tests. |
| `node_modules/` | Downloaded development dependencies are not committed; use the lockfile to reinstall if needed. |
| `playwright-report/`, `test-results/` | Generated reports/results do not clutter Git or accidentally become submission evidence. The explicitly selected screenshots are kept separately in docs. |
| `.env`, `.env.*` | Environment files stay out of commits. The current PHP config uses environment variables directly; ignoring an `.env` file does not automatically load it. |
| `.aider*`, `.claude/` | Personal agent configuration/worktrees are excluded from project changes. |
| `LIBRARY_DB_HOST/NAME/USER/PASS` definitions | For each config value, use the environment override when it exists; otherwise keep the normal app default. `!== false` distinguishes a missing setting from an intentionally empty string. The test server receives the generated database name through this path. |
| Existing charset/library-rule/timezone definitions | Stay shared with the app; the tests call real classes instead of copying their implementation. Browser calendar configuration also uses Lagos. |

Never add actual database credentials or real passwords to the scripts, lockfile, guides or Git. The login values already in the scripts are the project's documented development fixtures.

## 18. When a run fails

| Output/symptom | First thing to check |
| --- | --- |
| `could not find driver` | The terminal's PHP has `pdo_mysql` enabled; XAMPP's web PHP and terminal PHP can have different configuration. |
| Database connection refused | Local MySQL is running and the local host/port configuration is correct. Do not assume a PHP syntax bug. |
| CREATE/DROP permission error | The chosen local account has the necessary generated-test-database privileges. Ask for the correct configuration rather than probing remote services. |
| Missing development dependency | Run the documented dependency install only if missing. |
| Browser executable missing | Install the headless shell only if absent; do not download unrelated browsers. |
| HTTP 500 in many login-dependent checks | Diagnose the isolated server setup/session directory first, not 22 unrelated app features. The resolved Windows session-path issue is described in the testing record. |
| `Expected ... got ...` | Open the named case and compare its setup/action with the API or stored state; do not merely relax the assertion. |
| Ownership/cleanup refusal | Inspect the exact generated name/token/selected connection. Do not bypass protection or delete other resources. |
| Mobile/print screenshot looks wrong despite passing checks | Treat visual inspection as additional evidence: the automated assertions only cover their stated properties. |

This guide explains the existing scripts; it does not claim to run them again. See [the testing record](../docs/TESTING.md) for observed commands/results and the remaining human acceptance review.
